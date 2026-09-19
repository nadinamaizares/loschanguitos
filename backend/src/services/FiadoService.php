<?php
/**
 * Logica de negocio: la libreta de fiado.
 */

namespace Polleria;

class FiadoService
{
    /** La libreta completa: cuanto debe cada cliente (vista v_libreta_clientes). */
    public static function libreta()
    {
        return Db::select(
            "SELECT cliente_id, cliente, telefono, cant_fiados,
                    total_fiado, total_pagado, saldo_deudor,
                    kilos_totales, deuda_mas_antigua
               FROM v_libreta_clientes
           ORDER BY saldo_deudor DESC, cliente"
        );
    }

    /** Detalle de un cliente: sus fiados, con que se llevo y cuanto pago. */
    public static function detalleCliente($clienteId)
    {
        $cliente = Db::selectOne(
            "SELECT cliente_id, cliente, telefono, cant_fiados,
                    total_fiado, total_pagado, saldo_deudor,
                    kilos_totales, deuda_mas_antigua
               FROM v_libreta_clientes
              WHERE cliente_id = ?",
            [$clienteId]
        );

        if ($cliente === null) {
            Response::error('Cliente inexistente', 404);
        }

        $cliente['fiados'] = Db::select(
            "SELECT fiado_id, fecha, monto_total, monto_pagado, saldo,
                    kilos, estado, detalle
               FROM v_libreta_detalle
              WHERE cliente_id = ?
           ORDER BY fiado_id DESC",
            [$clienteId]
        );

        // Pagos que fue haciendo el cliente (historial de la libreta).
        $cliente['pagos'] = Db::select(
            "SELECT pa.c010_id          AS pago_id,
                    pa.c010_fiado_id    AS fiado_id,
                    pa.c010_monto       AS monto,
                    pa.c010_medio_pago  AS medio,
                    pa.c010_observaciones AS observaciones,
                    pa.c010_fecha       AS fecha
               FROM 010_fiado_pagos pa
               JOIN 008_fiados f ON f.c008_id = pa.c010_fiado_id
              WHERE f.c008_cliente_id = ?
           ORDER BY pa.c010_id DESC",
            [$clienteId]
        );

        return $cliente;
    }

    /** Registra un pago ("el cliente dejo $5000 de la libreta"). */
    public static function pagar($data)
    {
        // El usuario que registra el pago es el de la sesion (ver Auth.php).
        $usuario = Auth::id();

        $fiadoId = Input::entero(Input::requerido($data, 'fiado_id'), 'fiado_id');
        $monto   = Input::decimal(Input::requerido($data, 'monto'), 'monto');
        $medio   = Input::opcional($data, 'medio_pago', 'efectivo');
        $obs     = Input::opcional($data, 'observaciones', null);

        if ($monto <= 0) {
            Response::error('El monto debe ser mayor a 0', 422);
        }
        if (!in_array($medio, ['efectivo', 'transferencia', 'otro'], true)) {
            Response::error("El medio de pago debe ser efectivo, transferencia u otro", 422);
        }

        return Db::call('sp_pagar_fiado', [$fiadoId, $monto, $usuario, $medio, $obs]);
    }
}