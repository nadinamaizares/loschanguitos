-- =============================================================================
--  POLLERIA - Modulo de PROVEEDORES (compra de mercaderia + cuenta corriente)
--
--  Convencion: NNN_TABLA -> cNNN_campo (igual que el resto del sistema)
--
--  Nomenclatura de tablas nuevas:
--    011 -> proveedores         (el pollero, el huevero, ...)
--    012 -> proveedor_productos (que le compro a cada uno y a cuanto)
--    013 -> compras             (cabecera: "me dejo 10 cajones de pollo")
--    014 -> compra_items        (detalle: producto + cantidad + precio compra)
--    015 -> proveedor_pagos     (plata que le voy pagando: "le deje 20 mil")
--
--  Cuenta corriente del proveedor:  SUM(compras) - SUM(pagos) = saldo a pagar
--
--  IMPORTANTE (InfinityFree): sin vistas ni procedimientos, por eso aca solo
--  hay CREATE TABLE. La logica va en ProveedorService.php con transacciones.
-- =============================================================================
-- =============================================================================
-- 011_PROVEEDORES - A quien le compro
-- =============================================================================
CREATE TABLE IF NOT EXISTS 011_proveedores (
  c011_id              INT AUTO_INCREMENT PRIMARY KEY,
  c011_nombre          VARCHAR(150)  NOT NULL,
  c011_telefono        VARCHAR(50)   NULL,
  c011_direccion       VARCHAR(200)  NULL,
  c011_rubro           VARCHAR(100)  NULL,   -- Pollo, Huevos, Verduras, ...
  c011_observaciones   TEXT          NULL,
  c011_activo          TINYINT(1)    NOT NULL DEFAULT 1,
  c011_fcreacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_011_nombre UNIQUE (c011_nombre)
) ENGINE=InnoDB;
-- =============================================================================
-- 012_PROVEEDOR_PRODUCTOS - Que articulos trae cada proveedor
--   c012_precio_compra: lo que ME cuesta (distinto del precio de venta, que
--   sigue viviendo en 003_productos.c003_precio_kg).
--   Es una lista de apoyo: al cargar una compra, elegir el proveedor + producto
--   ya te trae el ultimo precio de compra sugerido.
-- =============================================================================
CREATE TABLE IF NOT EXISTS 012_proveedor_productos (
  c012_id              INT AUTO_INCREMENT PRIMARY KEY,
  c012_proveedor_id    INT           NOT NULL,
  c012_producto_id     INT           NOT NULL,
  c012_precio_compra   DECIMAL(10,2) NOT NULL DEFAULT 0,
  c012_activo          TINYINT(1)    NOT NULL DEFAULT 1,
  c012_fcreacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  c012_fmodificacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_012_011 FOREIGN KEY (c012_proveedor_id) REFERENCES 011_proveedores(c011_id),
  CONSTRAINT fk_012_003 FOREIGN KEY (c012_producto_id) REFERENCES 003_productos(c003_id),
  CONSTRAINT uq_012_par UNIQUE (c012_proveedor_id, c012_producto_id),
  INDEX idx_012_proveedor (c012_proveedor_id)
) ENGINE=InnoDB;
-- =============================================================================
-- 013_COMPRAS - Cabecera de la compra ("vino el pollero y me dejo mercaderia")
--   c013_medio_pago: como se pago AL MOMENTO (si pago algo).
--   Si no se pago nada al momento, queda con saldo pendiente (cuenta corriente).
-- =============================================================================
CREATE TABLE IF NOT EXISTS 013_compras (
  c013_id              INT AUTO_INCREMENT PRIMARY KEY,
  c013_proveedor_id    INT           NOT NULL,
  c013_usuario_id      INT           NOT NULL,
  c013_total           DECIMAL(12,2) NOT NULL DEFAULT 0,
  c013_observaciones   VARCHAR(255)  NULL,
  c013_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_013_011 FOREIGN KEY (c013_proveedor_id) REFERENCES 011_proveedores(c011_id),
  CONSTRAINT fk_013_001 FOREIGN KEY (c013_usuario_id)   REFERENCES 001_usuarios(c001_id),
  INDEX idx_013_proveedor (c013_proveedor_id),
  INDEX idx_013_fecha     (c013_fecha)
) ENGINE=InnoDB;
-- =============================================================================
-- 014_COMPRA_ITEMS - Detalle de la compra
--   c014_precio_compra: el precio del cajon / kilo al momento de comprar.
--   c014_suma_stock: 1 si esta mercaderia suma al stock, 0 si no.
--     El pollo entero NO suma stock (despues se fracciona en pechuga, etc.).
--     El huevo SI suma stock.
-- =============================================================================
CREATE TABLE IF NOT EXISTS 014_compra_items (
  c014_id              INT AUTO_INCREMENT PRIMARY KEY,
  c014_compra_id       INT           NOT NULL,
  c014_producto_id     INT           NOT NULL,
  c014_descripcion     VARCHAR(150)  NULL,   -- ej: "Cajon de pollo" si es generico
  c014_cantidad        DECIMAL(10,3) NOT NULL DEFAULT 0,
  c014_precio_compra   DECIMAL(10,2) NOT NULL DEFAULT 0,
  c014_subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
  c014_suma_stock      TINYINT(1)    NOT NULL DEFAULT 0,
  CONSTRAINT fk_014_013 FOREIGN KEY (c014_compra_id)    REFERENCES 013_compras(c013_id) ON DELETE CASCADE,
  CONSTRAINT fk_014_003 FOREIGN KEY (c014_producto_id)  REFERENCES 003_productos(c003_id),
  INDEX idx_014_compra (c014_compra_id)
) ENGINE=InnoDB;
-- =============================================================================
-- 015_PROVEEDOR_PAGOS - Plata que le voy pagando al proveedor
--   Cuenta corriente GLOBAL: el pago baja el saldo total del proveedor, no se
--   ata a una compra en particular (podes deberle de 3 compras y pagar una
--   parte de todo junto).
-- =============================================================================
CREATE TABLE IF NOT EXISTS 015_proveedor_pagos (
  c015_id              INT AUTO_INCREMENT PRIMARY KEY,
  c015_proveedor_id    INT           NOT NULL,
  c015_usuario_id      INT           NOT NULL,
  c015_monto           DECIMAL(12,2) NOT NULL,
  c015_medio_pago      ENUM('efectivo','transferencia','otro') NOT NULL DEFAULT 'efectivo',
  c015_observaciones   VARCHAR(255)  NULL,
  c015_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_015_011 FOREIGN KEY (c015_proveedor_id) REFERENCES 011_proveedores(c011_id),
  CONSTRAINT fk_015_001 FOREIGN KEY (c015_usuario_id)   REFERENCES 001_usuarios(c001_id),
  INDEX idx_015_proveedor (c015_proveedor_id),
  INDEX idx_015_fecha     (c015_fecha)
) ENGINE=InnoDB;
