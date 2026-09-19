DELIMITER //
CREATE PROCEDURE sp_cargar_stock(
    IN p_producto_id INT,
    IN p_cantidad    DECIMAL(10,3),
    IN p_usuario_id  INT,
    IN p_motivo      VARCHAR(255)
BEGIN
    DECLARE v_stock_ant DECIMAL(10,3) DEFAULT 0;

    IF p_cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cantidad debe ser mayor a 0';
    END IF;

    START TRANSACTION;

    SELECT c003_stock INTO v_stock_ant
      FROM 003_productos WHERE c003_id = p_producto_id FOR UPDATE;

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
DELIMITER ;
