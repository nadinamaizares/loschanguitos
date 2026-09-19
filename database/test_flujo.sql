-- =============================================================================
--  Prueba del flujo completo (cargar stock -> vender fiado -> ver libreta)
--    mysql -u root --table polleria < test_flujo.sql
--
--  Los IDs de producto corresponden al seed de 02_seed_y_vistas.sql:
--     1 Pollo entero | 2 Pechuga | 3 Pata y muslo | 4 Alitas | 5 Suprema
-- =============================================================================
USE polleria;

-- 1) Cargar mercaderia (producto, cantidad, usuario, motivo)
SELECT '=== CARGA DE STOCK ===' AS x;
CALL sp_cargar_stock(2, 15, 1, 'Ingreso pechuga');   -- 15kg de Pechuga
CALL sp_cargar_stock(4,  8, 1, 'Ingreso alitas');    -- 8kg de Alitas

-- 2) Cliente nuevo
INSERT IGNORE INTO 004_clientes (c004_nombre, c004_telefono)
VALUES ('Don Ramirez', '11-5555-1234');

-- 3) VENTA FIADA: 2.5kg de pechuga + 1kg de alitas
--    El formato de items es "producto_id:kilos" separado por ';'
--    (MariaDB 10.1 no soporta JSON, ver 03_procedimientos.sql)
SELECT '=== VENTA FIADA ===' AS x;
CALL sp_registrar_venta(
    1,                  -- usuario
    1,                  -- cliente (Don Ramirez)
    'fiado',
    '2:2.5;4:1',        -- 2.5kg Pechuga + 1kg Alitas
    'Prueba fiado'
);

-- 4) Resultados
SELECT '=== STOCK (deberia bajar 2.5 y 1) ===' AS x;
SELECT c003_id, c003_descripcion, c003_stock
  FROM 003_productos WHERE c003_id IN (2,4);

SELECT '=== LIBRETA (deberia deber 23.700) ===' AS x;
SELECT cliente, total_fiado, total_pagado, saldo_deudor, kilos_totales
  FROM v_libreta_clientes;

SELECT '=== DETALLE DE LA DEUDA ===' AS x;
SELECT fiado_id, cliente, monto_total, saldo, kilos, detalle, estado
  FROM v_libreta_detalle;

SELECT '=== VENTA DE CONTADO (sin cliente) ===' AS x;
CALL sp_registrar_venta(
    1,
    NULL,
    'contado',
    '2:1',
    'Prueba contado - debe funcionar sin cliente'
);
SELECT '=== VENTAS DEL DIA ===' AS x;
SELECT tipo, cant_ventas, kilos, total FROM v_ventas_dia;