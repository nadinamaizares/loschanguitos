<?php
/**
 * Logica de negocio: la libreta de fiado.
 *
 * Antes usaba las vistas v_libreta_clientes / v_libreta_detalle y el
 * procedimiento sp_pagar_fiado. Como InfinityFree no deja crear vistas ni
 * procedimientos, esas consultas se escriben aca directamente.
 */
namespace Polleria;

class FiadoService
{
    /** SQL de la "vista" deuda por cliente (reutilizado abajo). */
    public static function sqlLibretaClientes(): string
    {
        return
            "SELECT cl.c004_id AS cliente_id,
                    cl.c004_nombre AS cliente,
                    cl.c004_telefono AS telefono,
                    COUNT(DISTINCT f.c008_id) AS cant_fiados,
                    COALESCE(SUM(f.c008_monto_total), 0) AS total_fiado,
                    COALESCE(SUM(f.c008_monto_pagado), 0) AS total_pagado,
                    COALESCE(SUM(f.c008_monto_total - f.c008_monto_pagado), 0) AS saldo_deudor,
                    COALESCE(SUM(f.c008_kilos_totales), 0) AS kilos_totales,
                    MIN(CASE WHEN f.c008_estado <> 'pagado' THEN f.c008_fecha END) AS deuda_mas_antigua
               FROM 004_clientes cl
          LEFT JOIN 008_fiados f ON f.c008_cliente_id = cl.c004_id
              WHERE cl.c004_activo = 1
           GROUP BY cl.c004_id, cl.c004_nombre, cl.c004_telefono";
    }

    /** La libreta completa: cuanto debe cada cliente. */
    public static function libreta()
    {
        return Db::select(
            self::sqlLibretaClientes() . " ORDER BY saldo_deudor DESC, cliente"
        );
    }

    /** Detalle de un cliente: en que gasto y cuanto pago. */
    public static function detalleCliente($clienteId)
    {
        $cliente = Db::selectOne(
            "SELECT * FROM (" . self::sqlLibretaClientes() . ") AS libro WHERE cliente_id = ?",
            [$clienteId]
        );

        if ($cliente === null) {
            Response::error('Cliente inexistente', 404);
        }

        // Sus fiados, con el detalle de lo que se llevo (antes v_libreta_detalle).
        $cliente['fiados'] = Db::select(
            "SELECT f.c008_id AS fiado_id,
                    f.c008_fecha AS fecha,
                    f.c008_monto_total AS monto_total,
                    f.c008_monto_pagado AS monto_pagado,
                    (f.c008_monto_total - f.c008_monto_pagado) AS saldo,
                    f.c008_kilos_totales AS kilos,
                    f.c008_estado AS estado,
                    GROUP_CONCAT(CONCAT(it.c009_kilos, 'kg ', p.c003_descripcion)
                                 ORDER BY it.c009_id ASC SEPARATOR ' + ') AS detalle
               FROM 008_fiados f
          LEFT JOIN 009_fiado_items it ON it.c009_fiado_id = f.c008_id
          LEFT JOIN 003_productos p    ON p.c003_id = it.c009_producto_id
              WHERE f.c008_cliente_id = ?
           GROUP BY f.c008_id, f.c008_fecha, f.c008_monto_total,
                    f.c008_monto_pagado, f.c008_kilos_totales, f.c008_estado
           ORDER BY f.c008_id DESC",
            [$clienteId]
        );

        // Pagos que fue haciendo el cliente.
        $cliente['pagos'] = Db::select(
            "SELECT pa.c010_id          AS pago_id,
                    pa.c010_fiado_id      AS fiado_id,
                    pa.c010_monto         AS monto,
                    pa.c010_medio_pago    AS medio,
                    pa.c010_observaciones AS observaciones,
                    pa.c010_fecha         AS fecha
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

        return Db::transaction(function () use ($fiadoId, $monto, $usuario, $medio, $obs) {
            $fiado = Db::selectOne(
                "SELECT c008_id, c008_cliente_id, c008_monto_total, c008_monto_pagado
                    FROM 008_fiados WHERE c008_id = ? FOR UPDATE",
                [$fiadoId]
            );
            if ($fiado === null) {
                Response::error('Fiado inexistente', 404);
            }

            $pagado  = (float)$fiado['c008_monto_pagado'];
            $total   = (float)$fiado['c008_monto_total'];
            $nuevoP  = $pagado + $monto;
            if ($nuevoP > $total + 0.001) {
                $saldo = $total - $pagado;
                Response::error("El pago supera lo que debe. Saldo pendiente: " . number_format($saldo, 2, '.', ''), 422);
            }

            // Si con este pago salda la deuda, queda 'pagado' con fecha; si no, 'parcial'.
            $estado = ($nuevoP >= $total - 0.001) ? 'pagado' : 'parcial';

            Db::execute(
                "INSERT INTO 010_fiado_pagos
                    (c010_fiado_id, c010_monto, c010_usuario_id, c010_medio_pago, c010_observaciones)
                 VALUES (?, ?, ?, ?, ?)",
                [$fiadoId, $monto, $usuario, $medio, $obs]
            );
            $pagoId = Db::lastInsertId();

            if ($estado === 'pagado') {
                Db::execute(
                    "UPDATE 008_fiados
                          SET c008_monto_pagado = ?, c008_estado = 'pagado', c008_fecha_pago = NOW()
                        WHERE c008_id = ?",
                    [$nuevoP, $fiadoId]
                );
            } else {
                Db::execute(
                    "UPDATE 008_fiados
                          SET c008_monto_pagado = ?, c008_estado = 'parcial'
                        WHERE c008_id = ?",
                    [$nuevoP, $fiadoId]
                );
            }

            return [
                'pago_id'        => $pagoId,
                'fiado_id'       => $fiadoId,
                'monto_pagado'   => $monto,
                'total_pagado'   => $nuevoP,
                'saldo_restante' => round($total - $nuevoP, 2),
                'estado'         => $estado,
            ];
        });
    }
}