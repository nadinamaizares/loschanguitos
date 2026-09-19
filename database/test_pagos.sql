-- =============================================================================
--  Prueba de pagos de la libreta (parcial -> final -> intento de pagar de mas)
--    mysql -u root --table polleria < test_pagos.sql
-- =============================================================================
USE polleria;

SELECT '=== ESTADO INICIAL ===' AS x;
SELECT fiado_id, cliente, monto_total, saldo, estado FROM v_libreta_detalle;

SELECT '=== PAGO PARCIAL: deja $10.000 ===' AS x;
CALL sp_pagar_fiado(1, 10000, 1, 'efectivo', 'Pago parcial');

SELECT '=== LIBRETA despues del pago parcial ===' AS x;
SELECT cliente, total_fiado, total_pagado, saldo_deudor FROM v_libreta_clientes;

SELECT '=== PAGO FINAL: el saldo restante ===' AS x;
CALL sp_pagar_fiado(1, (SELECT saldo FROM v_libreta_detalle WHERE fiado_id = 1), 1, 'transferencia', 'Cancela deuda');

SELECT '=== LIBRETA saldada ===' AS x;
SELECT cliente, total_fiado, total_pagado, saldo_deudor FROM v_libreta_clientes;

SELECT '=== HISTORIAL DE PAGOS ===' AS x;
SELECT c010_id, c010_monto, c010_medio_pago, c010_observaciones
  FROM 010_fiado_pagos ORDER BY c010_id;

SELECT '=== INTENTO DE PAGAR DE MAS (debe fallar con error) ===' AS x;
CALL sp_pagar_fiado(1, 1000, 1, 'efectivo', 'Prueba exceso');