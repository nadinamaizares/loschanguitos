-- =============================================================================
--  POLLERIA - Esquema de base de datos
--  Convencion: NNN_TABLA  ->  cNNN_campo   (igual que el sistema movimientos.docentes)
--
--  Nomenclatura de tablas:
--    001 -> usuarios      (empleados que usan el sistema)
--    002 -> categorias    (Pollo, Achuras, Milanesas, Bebidas, ...)
--    003 -> productos     (se venden por KG)
--    004 -> clientes      (para el fiado / libreta)
--    005 -> ventas        (cabecera de cada venta)
--    006 -> venta_items   (detalle: producto + kg + precio)
--    007 -> movimientos   (entradas/salidas de stock, en KG)
--    008 -> fiados        (cabecera deuda)
--    009 -> fiado_items   (detalle de la deuda)
--    010 -> fiado_pagos   (pagos parciales que va haciendo el cliente)
-- =============================================================================

-- La base se crea aparte:  CREATE DATABASE polleria;
USE polleria;

-- =============================================================================
-- 001_USUARIOS - Empleados que entran al sistema
-- =============================================================================
CREATE TABLE IF NOT EXISTS 001_usuarios (
  c001_id              INT AUTO_INCREMENT PRIMARY KEY,
  c001_nombre          VARCHAR(100)  NOT NULL,
  c001_usuario         VARCHAR(50)   NOT NULL UNIQUE,
  c001_password_hash   VARCHAR(255)  NOT NULL,
  c001_rol             ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  c001_activo          TINYINT(1)    NOT NULL DEFAULT 1,
  c001_ultimo_acceso   DATETIME      NULL,
  c001_fcreacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  c001_fmodificacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================================================
-- 002_CATEGORIAS - Pollo, Achuras, Milanesas, Empanadas, Bebidas, Almacen
-- =============================================================================
CREATE TABLE IF NOT EXISTS 002_categorias (
  c002_id              INT AUTO_INCREMENT PRIMARY KEY,
  c002_descripcion     VARCHAR(100)  NOT NULL UNIQUE,
  c002_orden           INT           NOT NULL DEFAULT 0,
  c002_activo          TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- =============================================================================
-- 003_PRODUCTOS - Todo se vende por KG
--   c003_unidad:    'kg'      -> se pesa (pechuga, alitas, milanesa)
--                   'unidad'  -> se cuenta (bebida, pan)
--   c003_stock:     SIEMPRE en KG si es 'kg', o unidades si es 'unidad'
--   c003_precio_kg: precio por kilo (o por unidad si c003_unidad='unidad')
-- =============================================================================
CREATE TABLE IF NOT EXISTS 003_productos (
  c003_id              INT AUTO_INCREMENT PRIMARY KEY,
  c003_categoria_id    INT           NOT NULL,
  c003_descripcion     VARCHAR(150)  NOT NULL,
  c003_unidad          ENUM('kg','unidad') NOT NULL DEFAULT 'kg',
  c003_precio_kg       DECIMAL(10,2) NOT NULL DEFAULT 0,
  c003_stock           DECIMAL(10,3) NOT NULL DEFAULT 0,
  c003_stock_minimo    DECIMAL(10,3) NOT NULL DEFAULT 0,
  c003_activo          TINYINT(1)    NOT NULL DEFAULT 1,
  c003_fcreacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  c003_fmodificacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_003_002 FOREIGN KEY (c003_categoria_id)
      REFERENCES 002_categorias(c002_id),
  INDEX idx_003_desc (c003_descripcion),
  INDEX idx_003_cat  (c003_categoria_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 004_CLIENTES - Libreta de fiado
-- =============================================================================
CREATE TABLE IF NOT EXISTS 004_clientes (
  c004_id              INT AUTO_INCREMENT PRIMARY KEY,
  c004_nombre          VARCHAR(150)  NOT NULL,
  c004_telefono        VARCHAR(50)   NULL,
  c004_direccion       VARCHAR(200)  NULL,
  c004_observaciones   TEXT          NULL,
  c004_activo          TINYINT(1)    NOT NULL DEFAULT 1,
  c004_fcreacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_004_nombre UNIQUE (c004_nombre)
) ENGINE=InnoDB;

-- =============================================================================
-- 005_VENTAS - Cabecera de cada venta
--   c005_tipo:  'contado' -> se paga al momento
--               'fiado'   -> se carga a la libreta del cliente
-- =============================================================================
CREATE TABLE IF NOT EXISTS 005_ventas (
  c005_id              INT AUTO_INCREMENT PRIMARY KEY,
  c005_cliente_id      INT           NULL,          -- obligatorio si es fiado
  c005_usuario_id      INT           NOT NULL,
  c005_tipo            ENUM('contado','fiado') NOT NULL DEFAULT 'contado',
  c005_total           DECIMAL(12,2) NOT NULL DEFAULT 0,
  c005_kilos_totales   DECIMAL(10,3) NOT NULL DEFAULT 0,
  c005_observaciones   VARCHAR(255)  NULL,
  c005_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_005_004 FOREIGN KEY (c005_cliente_id) REFERENCES 004_clientes(c004_id),
  CONSTRAINT fk_005_001 FOREIGN KEY (c005_usuario_id) REFERENCES 001_usuarios(c001_id),
  INDEX idx_005_fecha   (c005_fecha),
  INDEX idx_005_cliente (c005_cliente_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 006_VENTA_ITEMS - Detalle de la venta (aqui esta el peso)
-- =============================================================================
CREATE TABLE IF NOT EXISTS 006_venta_items (
  c006_id              INT AUTO_INCREMENT PRIMARY KEY,
  c006_venta_id        INT           NOT NULL,
  c006_producto_id     INT           NOT NULL,
  c006_kilos           DECIMAL(10,3) NOT NULL DEFAULT 0,   -- cantidad vendida
  c006_precio_kg       DECIMAL(10,2) NOT NULL DEFAULT 0,   -- precio al momento de la venta
  c006_subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,   -- kilos * precio
  CONSTRAINT fk_006_005 FOREIGN KEY (c006_venta_id)    REFERENCES 005_ventas(c005_id) ON DELETE CASCADE,
  CONSTRAINT fk_006_003 FOREIGN KEY (c006_producto_id) REFERENCES 003_productos(c003_id),
  INDEX idx_006_venta (c006_venta_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 007_MOVIMIENTOS - Entradas y salidas de stock (en KG)
--   c007_tipo: 'entrada' (carga de mercaderia)
--              'salida'  (venta)
--              'ajuste'  (correccion manual)
--              'merma'   (lo que se descarta)
--   c007_cantidad es SIEMPRE positiva; el tipo define el signo.
-- =============================================================================
CREATE TABLE IF NOT EXISTS 007_movimientos (
  c007_id              INT AUTO_INCREMENT PRIMARY KEY,
  c007_producto_id     INT           NOT NULL,
  c007_usuario_id      INT           NOT NULL,
  c007_tipo            ENUM('entrada','salida','ajuste','merma') NOT NULL,
  c007_cantidad        DECIMAL(10,3) NOT NULL,
  c007_stock_anterior  DECIMAL(10,3) NOT NULL DEFAULT 0,
  c007_stock_nuevo     DECIMAL(10,3) NOT NULL DEFAULT 0,
  c007_motivo          VARCHAR(255)  NULL,
  c007_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_007_003 FOREIGN KEY (c007_producto_id) REFERENCES 003_productos(c003_id),
  CONSTRAINT fk_007_001 FOREIGN KEY (c007_usuario_id)  REFERENCES 001_usuarios(c001_id),
  INDEX idx_007_producto (c007_producto_id),
  INDEX idx_007_fecha    (c007_fecha)
) ENGINE=InnoDB;

-- =============================================================================
-- 008_FIADOS - Cabecera deuda (la libreta)
-- =============================================================================
CREATE TABLE IF NOT EXISTS 008_fiados (
  c008_id              INT AUTO_INCREMENT PRIMARY KEY,
  c008_cliente_id      INT           NOT NULL,
  c008_venta_id        INT           NULL,          -- venta que la origino
  c008_monto_total     DECIMAL(12,2) NOT NULL DEFAULT 0,  -- lo que se llevo
  c008_monto_pagado    DECIMAL(12,2) NOT NULL DEFAULT 0,  -- lo que pago
  c008_kilos_totales   DECIMAL(10,3) NOT NULL DEFAULT 0,
  c008_estado          ENUM('pendiente','pagado','parcial') NOT NULL DEFAULT 'pendiente',
  c008_observaciones   VARCHAR(255)  NULL,
  c008_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  c008_fecha_pago      DATETIME      NULL,
  CONSTRAINT fk_008_004 FOREIGN KEY (c008_cliente_id) REFERENCES 004_clientes(c004_id),
  CONSTRAINT fk_008_005 FOREIGN KEY (c008_venta_id)   REFERENCES 005_ventas(c005_id),
  INDEX idx_008_cliente (c008_cliente_id),
  INDEX idx_008_estado  (c008_estado)
) ENGINE=InnoDB;

-- =============================================================================
-- 009_FIADO_ITEMS - Que se llevo (peso y monto por producto)
-- =============================================================================
CREATE TABLE IF NOT EXISTS 009_fiado_items (
  c009_id              INT AUTO_INCREMENT PRIMARY KEY,
  c009_fiado_id        INT           NOT NULL,
  c009_producto_id     INT           NOT NULL,
  c009_kilos           DECIMAL(10,3) NOT NULL DEFAULT 0,
  c009_precio_kg       DECIMAL(10,2) NOT NULL DEFAULT 0,
  c009_subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_009_008 FOREIGN KEY (c009_fiado_id)    REFERENCES 008_fiados(c008_id) ON DELETE CASCADE,
  CONSTRAINT fk_009_003 FOREIGN KEY (c009_producto_id) REFERENCES 003_productos(c003_id),
  INDEX idx_009_fiado (c009_fiado_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 010_FIADO_PAGOS - Pagos parciales (la libreta: "me dio 5000")
-- =============================================================================
CREATE TABLE IF NOT EXISTS 010_fiado_pagos (
  c010_id              INT AUTO_INCREMENT PRIMARY KEY,
  c010_fiado_id        INT           NOT NULL,
  c010_usuario_id      INT           NOT NULL,
  c010_monto           DECIMAL(12,2) NOT NULL,
  c010_medio_pago      ENUM('efectivo','transferencia','otro') NOT NULL DEFAULT 'efectivo',
  c010_observaciones   VARCHAR(255)  NULL,
  c010_fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_010_008 FOREIGN KEY (c010_fiado_id)   REFERENCES 008_fiados(c008_id) ON DELETE CASCADE,
  CONSTRAINT fk_010_001 FOREIGN KEY (c010_usuario_id) REFERENCES 001_usuarios(c001_id),
  INDEX idx_010_fiado (c010_fiado_id)
) ENGINE=InnoDB;
