-- =============================================================================
--  POLLERIA - Procedimientos almacenados (la logica de negocio en la BD)
--    mysql -u root polleria < 03_procedimientos.sql
--
--  Por que en la BD: garantizan atomicidad. Si la venta falla a mitad de camino,
--  no queda stock descontado sin venta registrada.
--
--  OJO: el MySQL de XAMPP es MariaDB 10.1, que NO soporta el tipo JSON ni las
--  funciones JSON_EXTRACT / JSON_LENGTH. Por eso los items de una venta se
--  pasan como texto simple "producto_id:kilos" separado por punto y coma:
--      '2:2.5;4:1'  ->  2.5kg del producto 2 + 1kg del producto 4
-- =============================================================================

USE polleria;

DROP PROCEDURE IF EXISTS sp_cargar_stock;
DROP PROCEDURE IF EXISTS sp_registrar_venta;
DROP PROCEDURE IF EXISTS sp_pagar_fiado;
DROP PROCEDURE IF EXISTS sp_ajustar_stock;

DELIMITER //

-- -----------------------------------------------------------------------------
-- sp_cargar_stock - El pollero ingresa mercaderia
--   Ej: 10kg de pechuga, 5kg de alitas
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_cargar_stock(
    IN p_producto_id INT,
    IN p_cantidad    DECIMAL(10,3),
    IN p_usuario_id  INT,
    IN p_motivo      VARCHAR(255))
BEGIN
    DECLARE v_stock_ant DECIMAL(10,3);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cantidad debe ser mayor a 0';
    END IF;

    START TRANSACTION;

    SELECT c003_stock INTO v_stock_ant
      FROM 003_productos WHERE c003_id = p_producto_id FOR UPDATE;

    IF v_stock_ant IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto inexistente';
    END IF;

    UPDATE 003_productos
       SET c003_stock = c003_stock + p_cantidad
     WHERE c003_id = p_producto_id;

    INSERT INTO 007_movimientos
        (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
         c007_stock_anterior, c007_stock_nuevo, c007_motivo)
    VALUES
        (p_producto_id, p_usuario_id, 'entrada', p_cantidad,
         v_stock_ant, v_stock_ant + p_cantidad, p_motivo);

    COMMIT;

    SELECT v_stock_ant AS stock_anterior,
           v_stock_ant + p_cantidad AS stock_nuevo;
END//

-- -----------------------------------------------------------------------------
-- sp_registrar_venta - Venta de contado o fiada
--   p_items: lista "producto_id:kilos" separada por punto y coma.
--            Ej:  '2:2.5;4:1'   -> 2.5kg del producto 2 + 1kg del producto 4
--   p_tipo:  'contado' | 'fiado'
--   Si es fiado, genera automaticamente la deuda en la libreta.
--
--   p_cliente_id: si viene 0 (o NULL) y la venta es de contado, se guarda NULL.
--   Es importante porque c005_cliente_id es FK a 004_clientes: un 0 seria
--   rechazado por la base.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_registrar_venta(
    IN p_usuario_id  INT,
    IN p_cliente_id  INT,
    IN p_tipo        VARCHAR(10),
    IN p_items       TEXT,
    IN p_obs         VARCHAR(255))
BEGIN
    DECLARE v_venta_id   INT;
    DECLARE v_total      DECIMAL(12,2) DEFAULT 0;
    DECLARE v_kilos      DECIMAL(10,3) DEFAULT 0;
    DECLARE v_fiado_id   INT DEFAULT NULL;
    DECLARE v_cli_id     INT DEFAULT NULL;
    DECLARE v_n          INT DEFAULT 0;
    DECLARE v_i          INT DEFAULT 0;
    DECLARE v_items      TEXT;
    DECLARE v_par        VARCHAR(50);
    DECLARE v_sep        INT;
    DECLARE v_prod_id    INT;
    DECLARE v_kilos_item DECIMAL(10,3);
    DECLARE v_precio     DECIMAL(10,2);
    DECLARE v_stock      DECIMAL(10,3);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_tipo NOT IN ('contado','fiado') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de venta invalido';
    END IF;

    IF p_tipo = 'fiado' AND (p_cliente_id IS NULL OR p_cliente_id = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Venta fiada requiere cliente';
    END IF;

    -- Normalizamos el cliente: 0 / NULL  ->  NULL (venta de contado sin cliente)
    IF p_cliente_id IS NULL OR p_cliente_id = 0 THEN
        SET v_cli_id = NULL;
    ELSE
        SET v_cli_id = p_cliente_id;
    END IF;

    -- Normalizamos los items: sacamos espacios y punto y coma sobrantes
    SET v_items = TRIM(BOTH ';' FROM REPLACE(p_items, ' ', ''));
    IF v_items IS NULL OR v_items = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La venta no tiene items';
    END IF;

    -- Contamos los items (separador ';')
    SET v_n = 1 + (LENGTH(v_items) - LENGTH(REPLACE(v_items, ';', '')));

    -- ---- PASADA 1: validar stock y calcular totales ----
    START TRANSACTION;

    SET v_i = 1;
    WHILE v_i <= v_n DO
        SET v_par = SUBSTRING_INDEX(SUBSTRING_INDEX(v_items, ';', v_i), ';', -1);
        SET v_sep = LOCATE(':', v_par);

        IF v_sep = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Formato de item invalido (se espera producto_id:kilos)';
        END IF;

        SET v_prod_id    = CAST(SUBSTRING(v_par, 1, v_sep - 1) AS SIGNED);
        SET v_kilos_item = CAST(SUBSTRING(v_par, v_sep + 1) AS DECIMAL(10,3));

        IF v_kilos_item IS NULL OR v_kilos_item <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los kilos de cada item deben ser mayores a 0';
        END IF;

        SELECT c003_precio_kg, c003_stock INTO v_precio, v_stock
          FROM 003_productos WHERE c003_id = v_prod_id AND c003_activo = 1 FOR UPDATE;

        IF v_precio IS NULL THEN
            SET @msg = CONCAT('Producto inexistente o inactivo: ', v_prod_id);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;

        IF v_stock < v_kilos_item THEN
            SET @msg = CONCAT('Stock insuficiente producto ', v_prod_id, '. Disponible: ', v_stock);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;

        SET v_total = v_total + (v_kilos_item * v_precio);
        SET v_kilos = v_kilos + v_kilos_item;
        SET v_i = v_i + 1;
    END WHILE;

    INSERT INTO 005_ventas
        (c005_cliente_id, c005_usuario_id, c005_tipo, c005_total,
         c005_kilos_totales, c005_observaciones)
    VALUES
        (v_cli_id, p_usuario_id, p_tipo, v_total, v_kilos, p_obs);

    SET v_venta_id = LAST_INSERT_ID();

    -- ---- PASADA 2: grabar el detalle y descontar stock ----
    SET v_i = 1;
    WHILE v_i <= v_n DO
        SET v_par = SUBSTRING_INDEX(SUBSTRING_INDEX(v_items, ';', v_i), ';', -1);
        SET v_sep = LOCATE(':', v_par);

        SET v_prod_id    = CAST(SUBSTRING(v_par, 1, v_sep - 1) AS SIGNED);
        SET v_kilos_item = CAST(SUBSTRING(v_par, v_sep + 1) AS DECIMAL(10,3));

        SELECT c003_precio_kg, c003_stock INTO v_precio, v_stock
          FROM 003_productos WHERE c003_id = v_prod_id;

        INSERT INTO 006_venta_items
            (c006_venta_id, c006_producto_id, c006_kilos, c006_precio_kg, c006_subtotal)
        VALUES
            (v_venta_id, v_prod_id, v_kilos_item, v_precio, v_kilos_item * v_precio);

        UPDATE 003_productos SET c003_stock = c003_stock - v_kilos_item
         WHERE c003_id = v_prod_id;

        INSERT INTO 007_movimientos
            (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
             c007_stock_anterior, c007_stock_nuevo, c007_motivo)
        VALUES
            (v_prod_id, p_usuario_id, 'salida', v_kilos_item,
             v_stock, v_stock - v_kilos_item, CONCAT('Venta #', v_venta_id));

        SET v_i = v_i + 1;
    END WHILE;

    IF p_tipo = 'fiado' THEN
        INSERT INTO 008_fiados
            (c008_cliente_id, c008_venta_id, c008_monto_total,
             c008_monto_pagado, c008_kilos_totales, c008_estado)
        VALUES
            (v_cli_id, v_venta_id, v_total, 0, v_kilos, 'pendiente');

        SET v_fiado_id = LAST_INSERT_ID();

        INSERT INTO 009_fiado_items
            (c009_fiado_id, c009_producto_id, c009_kilos, c009_precio_kg, c009_subtotal)
        SELECT v_fiado_id, c006_producto_id, c006_kilos, c006_precio_kg, c006_subtotal
          FROM 006_venta_items WHERE c006_venta_id = v_venta_id;
    END IF;

    COMMIT;

    SELECT v_venta_id AS venta_id,
           v_total    AS total,
           v_kilos    AS kilos,
           v_fiado_id AS fiado_id;
END//

-- -----------------------------------------------------------------------------
-- sp_pagar_fiado - El cliente trae plata ("te dejo 5000 de la libreta")
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_pagar_fiado(
    IN p_fiado_id   INT,
    IN p_monto      DECIMAL(12,2),
    IN p_usuario_id INT,
    IN p_medio      VARCHAR(20),
    IN p_obs        VARCHAR(255))
BEGIN
    DECLARE v_total  DECIMAL(12,2);
    DECLARE v_pagado DECIMAL(12,2);
    DECLARE v_nuevo  DECIMAL(12,2);
    DECLARE v_estado VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_monto <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor a 0';
    END IF;

    START TRANSACTION;

    SELECT c008_monto_total, c008_monto_pagado INTO v_total, v_pagado
      FROM 008_fiados WHERE c008_id = p_fiado_id FOR UPDATE;

    IF v_total IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fiado inexistente';
    END IF;

    SET v_nuevo = v_pagado + p_monto;

    IF v_nuevo > v_total THEN
        SET @msg = CONCAT('El pago excede la deuda. Saldo actual: ', (v_total - v_pagado));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    END IF;

    SET v_estado = IF(v_nuevo = v_total, 'pagado', 'parcial');

    UPDATE 008_fiados
       SET c008_monto_pagado = v_nuevo,
           c008_estado       = v_estado,
           c008_fecha_pago   = IF(v_estado = 'pagado', NOW(), NULL)
     WHERE c008_id = p_fiado_id;

    INSERT INTO 010_fiado_pagos
        (c010_fiado_id, c010_usuario_id, c010_monto, c010_medio_pago, c010_observaciones)
    VALUES
        (p_fiado_id, p_usuario_id, p_monto, IFNULL(p_medio,'efectivo'), p_obs);

    COMMIT;

    SELECT v_nuevo AS total_pagado,
           v_total - v_nuevo AS saldo_restante,
           v_estado AS estado;
END//

-- -----------------------------------------------------------------------------
-- sp_ajustar_stock - Correccion manual o merma
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_ajustar_stock(
    IN p_producto_id INT,
    IN p_stock_nuevo DECIMAL(10,3),
    IN p_usuario_id  INT,
    IN p_tipo        VARCHAR(10),
    IN p_motivo      VARCHAR(255))
BEGIN
    DECLARE v_stock_ant DECIMAL(10,3);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT c003_stock INTO v_stock_ant
      FROM 003_productos WHERE c003_id = p_producto_id FOR UPDATE;

    UPDATE 003_productos SET c003_stock = p_stock_nuevo WHERE c003_id = p_producto_id;

    INSERT INTO 007_movimientos
        (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
         c007_stock_anterior, c007_stock_nuevo, c007_motivo)
    VALUES
        (p_producto_id, p_usuario_id, p_tipo, ABS(p_stock_nuevo - v_stock_ant),
         v_stock_ant, p_stock_nuevo, p_motivo);

    COMMIT;

    SELECT v_stock_ant AS stock_anterior, p_stock_nuevo AS stock_nuevo;
END//

DELIMITER ;
