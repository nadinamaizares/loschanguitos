DELIMITER //
CREATE PROCEDURE sp_pagar_fiado(IN p_fiado_id INT, IN p_monto DECIMAL(12,2), IN p_usuario_id INT, IN p_medio VARCHAR(20), IN p_obs VARCHAR(255))
BEGIN
    DECLARE v_total DECIMAL(12,2);
    DECLARE v_pagado DECIMAL(12,2);
    DECLARE v_nuevo DECIMAL(12,2);
    DECLARE v_estado VARCHAR(20);

    IF p_monto <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor a 0';
    END IF;

    START TRANSACTION;

    SELECT c008_monto_total, c008_monto_pagado INTO v_total, v_pagado FROM 008_fiados WHERE c008_id = p_fiado_id FOR UPDATE;

    SET v_nuevo = v_pagado + p_monto;

    IF v_nuevo > v_total THEN
        SET @msg = CONCAT('El pago excede la deuda. Saldo actual: ', (v_total - v_pagado));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    END IF;

    SET v_estado = IF(v_nuevo = v_total, 'pagado', 'parcial');

    UPDATE 008_fiados SET c008_monto_pagado = v_nuevo, c008_estado = v_estado, c008_fecha_pago = IF(v_estado = 'pagado', NOW(), NULL) WHERE c008_id = p_fiado_id;

    INSERT INTO 010_fiado_pagos (c010_fiado_id, c010_usuario_id, c010_monto, c010_medio_pago, c010_observaciones) VALUES (p_fiado_id, p_usuario_id, p_monto, IFNULL(p_medio,'efectivo'), p_obs);

    COMMIT;

    SELECT v_nuevo AS total_pagado, v_total - v_nuevo AS saldo_restante, v_estado AS estado;
END//
DELIMITER ;
