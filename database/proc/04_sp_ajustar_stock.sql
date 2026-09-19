DELIMITER //
CREATE PROCEDURE sp_ajustar_stock(IN p_producto_id INT, IN p_stock_nuevo DECIMAL(10,3), IN p_usuario_id INT, IN p_tipo VARCHAR(10), IN p_motivo VARCHAR(255))
BEGIN
    DECLARE v_stock_ant DECIMAL(10,3) DEFAULT 0;

    START TRANSACTION;

    SELECT c003_stock INTO v_stock_ant FROM 003_productos WHERE c003_id = p_producto_id FOR UPDATE;

    UPDATE 003_productos SET c003_stock = p_stock_nuevo WHERE c003_id = p_producto_id;

    INSERT INTO 007_movimientos (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad, c007_stock_anterior, c007_stock_nuevo, c007_motivo) VALUES (p_producto_id, p_usuario_id, p_tipo, ABS(p_stock_nuevo - v_stock_ant), v_stock_ant, p_stock_nuevo, p_motivo);

    COMMIT;

    SELECT v_stock_ant AS stock_anterior, p_stock_nuevo AS stock_nuevo;
END//
DELIMITER ;
