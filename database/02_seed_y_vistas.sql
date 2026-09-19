-- =============================================================================
--  POLLERIA - Datos iniciales + Vistas
--  Ejecutar despues de 01_schema.sql
--     mysql -u root polleria < 02_seed_y_vistas.sql
-- =============================================================================

USE polleria;

-- -----------------------------------------------------------------------------
-- Categorias base de una polleria
-- -----------------------------------------------------------------------------
INSERT INTO 002_categorias (c002_descripcion, c002_orden) VALUES
  ('Pollo',      1),
  ('Achuras',    2),
  ('Milanesas',  3),
  ('Empanadas',  4),
  ('Bebidas',    5),
  ('Almacen',    6)
ON DUPLICATE KEY UPDATE c002_orden = VALUES(c002_orden);

-- -----------------------------------------------------------------------------
-- Productos de ejemplo (todos por KG salvo bebidas)
-- Los precios son de muestra: cambiar desde la app.
-- -----------------------------------------------------------------------------
-- El ORDER BY final es importante: sin el, MySQL inserta en el orden que se le
-- ocurre y los IDs quedan impredecibles. Fijandolo, los IDs salen siempre:
--   1 pollo entero, 2 pechuga, 3 pata y muslo, 4 alitas, 5 suprema, ...
INSERT INTO 003_productos (c003_id, c003_categoria_id, c003_descripcion, c003_unidad, c003_precio_kg, c003_stock, c003_stock_minimo)
SELECT p.id, c.c002_id, p.descr, p.un, p.precio, 0, p.minimo
FROM (
  SELECT  1 AS id, 'Pollo'     AS cat, 'Pollo entero'          AS descr, 'kg'     AS un, 4500.00 AS precio, 10 AS minimo UNION ALL
  SELECT  2,       'Pollo',           'Pechuga',                        'kg',     6800.00,  8 UNION ALL
  SELECT  3,       'Pollo',           'Pata y muslo',                   'kg',     5200.00,  8 UNION ALL
  SELECT  4,       'Pollo',           'Alitas',                         'kg',     5000.00,  5 UNION ALL
  SELECT  5,       'Pollo',           'Suprema',                        'kg',     8500.00,  5 UNION ALL
  SELECT  6,       'Achuras',         'Higado',                         'kg',     3800.00,  3 UNION ALL
  SELECT  7,       'Achuras',         'Mollejas',                       'kg',     9500.00,  2 UNION ALL
  SELECT  8,       'Milanesas',       'Milanesa de pechuga',            'kg',     8900.00,  5 UNION ALL
  SELECT  9,       'Empanadas',       'Empanada de pollo',              'unidad',   900.00, 24 UNION ALL
  SELECT 10,       'Bebidas',         'Coca-Cola 1.5L',                 'unidad',  3200.00, 12 UNION ALL
  SELECT 11,       'Bebidas',         'Agua 500ml',                     'unidad',  1200.00, 12
) p
JOIN 002_categorias c ON c.c002_descripcion = p.cat
ORDER BY p.id;

-- -----------------------------------------------------------------------------
-- Usuarios de prueba
-- -----------------------------------------------------------------------------
-- El hash de abajo es un bcrypt REAL (generado con password_hash), porque con
-- el que habia antes (de relleno) el login NUNCA iba a funcionar.
--   admin    / polleria2024   (rol admin)
--   vendedor / vendedor2024   (rol vendedor)
-- IMPORTANTE: cambiar estas claves apenas se ponga en produccion.
INSERT INTO 001_usuarios (c001_nombre, c001_usuario, c001_password_hash, c001_rol) VALUES
  ('Administrador', 'admin',    '$2y$10$kFYu0Meu4yMz5GrLf4GEKuYomAtM.L0GSHk70n0OFz1NXybmOhPMG', 'admin'),
  ('Vendedor',      'vendedor', '$2y$10$Fm6ViNFp2rLmDmVAGQ2dAOsExkFa16WM3KGtXI51yOhClaS7KxCTK', 'vendedor')
ON DUPLICATE KEY UPDATE c001_nombre = VALUES(c001_nombre);

-- =============================================================================
--  V I S T A S
-- =============================================================================

-- -----------------------------------------------------------------------------
-- v_stock_actual - Stock por producto con su categoria y valorizado
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_stock_actual AS
SELECT
    p.c003_id                              AS producto_id,
    p.c003_descripcion                     AS producto,
    c.c002_descripcion                     AS categoria,
    p.c003_unidad                          AS unidad,
    p.c003_stock                           AS stock,
    p.c003_stock_minimo                    AS stock_minimo,
    p.c003_precio_kg                       AS precio,
    ROUND(p.c003_stock * p.c003_precio_kg, 2) AS valorizado,
    CASE
      WHEN p.c003_stock <= 0                 THEN 'SIN STOCK'
      WHEN p.c003_stock <= p.c003_stock_minimo THEN 'BAJO'
      ELSE 'OK'
    END                                    AS estado
FROM 003_productos p
JOIN 002_categorias c ON c.c002_id = p.c003_categoria_id
WHERE p.c003_activo = 1;

-- -----------------------------------------------------------------------------
-- v_libreta_clientes - La libreta: cuanto debe cada cliente
--   Es la vista central del fiado.
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_libreta_clientes AS
SELECT
    cl.c004_id                                  AS cliente_id,
    cl.c004_nombre                              AS cliente,
    cl.c004_telefono                            AS telefono,
    COUNT(DISTINCT f.c008_id)                   AS cant_fiados,
    COALESCE(SUM(f.c008_monto_total), 0)        AS total_fiado,
    COALESCE(SUM(f.c008_monto_pagado), 0)       AS total_pagado,
    COALESCE(SUM(f.c008_monto_total - f.c008_monto_pagado), 0) AS saldo_deudor,
    COALESCE(SUM(f.c008_kilos_totales), 0)      AS kilos_totales,
    MIN(CASE WHEN f.c008_estado <> 'pagado' THEN f.c008_fecha END) AS deuda_mas_antigua
FROM 004_clientes cl
LEFT JOIN 008_fiados f ON f.c008_cliente_id = cl.c004_id
WHERE cl.c004_activo = 1
GROUP BY cl.c004_id, cl.c004_nombre, cl.c004_telefono;

-- -----------------------------------------------------------------------------
-- v_libreta_detalle - Cada fiado con el detalle de que se llevo
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_libreta_detalle AS
SELECT
    f.c008_id                                  AS fiado_id,
    cl.c004_id                                 AS cliente_id,
    cl.c004_nombre                             AS cliente,
    f.c008_fecha                               AS fecha,
    f.c008_monto_total                         AS monto_total,
    f.c008_monto_pagado                        AS monto_pagado,
    (f.c008_monto_total - f.c008_monto_pagado) AS saldo,
    f.c008_kilos_totales                       AS kilos,
    f.c008_estado                              AS estado,
    GROUP_CONCAT(
        CONCAT(it.c009_kilos, 'kg ', p.c003_descripcion)
        ORDER BY it.c009_id SEPARATOR ' + '
    )                                          AS detalle
FROM 008_fiados f
JOIN 004_clientes cl ON cl.c004_id = f.c008_cliente_id
LEFT JOIN 009_fiado_items it ON it.c009_fiado_id = f.c008_id
LEFT JOIN 003_productos   p  ON p.c003_id = it.c009_producto_id
GROUP BY f.c008_id, cl.c004_id, cl.c004_nombre, f.c008_fecha,
         f.c008_monto_total, f.c008_monto_pagado, f.c008_kilos_totales, f.c008_estado;

-- -----------------------------------------------------------------------------
-- v_ventas_dia - Resumen de ventas del dia (para el cierre de caja)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_ventas_dia AS
SELECT
    DATE(v.c005_fecha)                          AS fecha,
    v.c005_tipo                                 AS tipo,
    COUNT(*)                                    AS cant_ventas,
    SUM(v.c005_kilos_totales)                   AS kilos,
    SUM(v.c005_total)                           AS total
FROM 005_ventas v
GROUP BY DATE(v.c005_fecha), v.c005_tipo;
