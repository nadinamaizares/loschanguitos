DROP PROCEDURE IF EXISTS sp_registrar_venta;
DELIMITER //
CREATE PROCEDURE sp_registrar_venta(
    IN p_usuario_id  INT,
    IN p_cliente_id  INT,
    IN p_tipo        VARCHAR(10),
    IN p_items       TEXT,
    IN p_obs         VARCHAR(255),
    IN p_medio_pago  VARCHAR(10))
BEGIN
    DECLARE v_venta_id   INT;
    DECLARE v_total      DECIMAL(12,2) DEFAULT 0;
    DECLARE v_kilos      DECIMAL(10,3) DEFAULT 0;
    DECLARE v_fiado_id   INT DEFAULT NULL;
    DECLARE v_cli_id     INT DEFAULT NULL;
    DECLARE v_medio      VARCHAR(10);
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

    -- Medio de pago: efectivo / virtual / tarjeta. En las ventas fiadas no
    -- aplica (no entra plata), asi que lo dejamos en 'efectivo' por defecto.
    IF p_tipo = 'fiado' THEN
        SET v_medio = 'efectivo';
    ELSE
        IF p_medio_pago IS NULL OR p_medio_pago = '' THEN
            SET v_medio = 'efectivo';
        ELSEIF p_medio_pago NOT IN ('efectivo','virtual','tarjeta') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Medio de pago invalido';
        ELSE
            SET v_medio = p_medio_pago;
        END IF;
    END IF;

    IF p_cliente_id IS NULL OR p_cliente_id = 0 THEN
        SET v_cli_id = NULL;
    ELSE
        SET v_cli_id = p_cliente_id;
    END IF;

    SET v_items = TRIM(BOTH ';' FROM REPLACE(p_items, ' ', ''));
    IF v_items IS NULL OR v_items = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La venta no tiene items';
    END IF;

    SET v_n = 1 + (LENGTH(v_items) - LENGTH(REPLACE(v_items, ';', '')));

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
        (c005_cliente_id, c005_usuario_id, c005_tipo, c005_medio_pago, c005_total,
         c005_kilos_totales, c005_observaciones)
    VALUES
        (v_cli_id, p_usuario_id, p_tipo, v_medio, v_total, v_kilos, p_obs);
    SET v_venta_id = LAST_INSERT_ID();

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
           v_medio    AS medio_pago,
           v_fiado_id AS fiado_id;
END//
DELIMITER ;