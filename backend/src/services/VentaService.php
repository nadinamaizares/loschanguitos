<?php
/**
 * Logica de negocio: ventas y stock.
 *
 * IMPORTANTE: antes la venta se registraba con el procedimiento almacenado
 * sp_registrar_venta. Pero los hostings gratis (InfinityFree) NO dejan crear
 * procedimientos ni vistas, asi que esa logica se paso a PHP. La atomicidad
 * la da Db::transaction(): si algo falla, se deshace todo (no queda una venta
 * sin descontar el stock, ni al reves).
 */
namespace Polleria;

class VentaService
{
    /**
     * Registra una venta (contado o fiado).
     *
     * Espera:
     *   tipo       => 'contado' | 'fiado'
     *   cliente_id => obligatorio si es fiado
     *   items      => [ ['producto_id'=>2,'kilos'=>2.5], ... ]
     *   medio_pago => efectivo | virtual | tarjeta  (solo si es contado)
     *   observaciones (opcional)
     */
    public static function registrar($data)
    {
        // El usuario que vende es SIEMPRE el de la sesion (ya validado por
        // Auth::exigir() en la ruta). Nunca se toma del cuerpo de la peticion.
        $usuario = Auth::id();

        $tipo    = Input::requerido($data, 'tipo');
        $items   = Input::requerido($data, 'items');
        $obs     = Input::opcional($data, 'observaciones', null);
        $cliente = Input::opcional($data, 'cliente_id', null);
        // Como se paga la venta (solo tiene sentido si es de contado):
        //   efectivo | virtual (transferencia/QR) | tarjeta (posnet)
        $medio   = Input::opcional($data, 'medio_pago', 'efectivo');
        if (!in_array($medio, ['efectivo', 'virtual', 'tarjeta'], true)) {
            Response::error("El medio de pago debe ser efectivo, virtual o tarjeta", 422);
        }

        if (!in_array($tipo, ['contado', 'fiado'], true)) {
            Response::error("El tipo debe ser 'contado' o 'fiado'", 422);
        }
        // Fiar SI lo puede hacer el vendedor: es una operacion del mostrador.

        if (!is_array($items) || count($items) === 0) {
            Response::error('La venta necesita al menos un item', 422);
        }

        $cliId = null;
        if ($tipo === 'fiado') {
            if ($cliente === null) {
                Response::error('Una venta fiada necesita cliente_id', 422);
            }
            $cliId = Input::entero($cliente, 'cliente_id');
        } elseif ($cliente !== null) {
            $cliId = Input::entero($cliente, 'cliente_id');
        }

        // La venta fiada no manda medio de pago (no entra plata).
        if ($tipo === 'fiado') {
            $medio = 'efectivo';
        }

        return Db::transaction(function () use ($usuario, $cliId, $tipo, $items, $obs, $medio) {
            $total = 0;
            $kilos = 0;
            $preparados = [];

            // 1) Validar stock y calcular totales con el precio ACTUAL.
            foreach ($items as $i => $it) {
                if (!isset($it['producto_id']) || !isset($it['kilos'])) {
                    Response::error("El item #{$i} necesita producto_id y kilos", 422);
                }
                $pid     = Input::entero($it['producto_id'], "items[{$i}].producto_id");
                $kilosIt = Input::decimal($it['kilos'], "items[{$i}].kilos");
                if ($kilosIt <= 0) {
                    Response::error("Los kilos del item #{$i} deben ser mayores a 0", 422);
                }

                $prod = Db::selectOne(
                    "SELECT c003_id, c003_descripcion, c003_precio_kg, c003_stock
                       FROM 003_productos
                      WHERE c003_id = ? AND c003_activo = 1
                        FOR UPDATE",
                    [$pid]
                );
                if ($prod === null) {
                    Response::error("Producto inexistente o inactivo: {$pid}", 422);
                }
                if ((float)$prod['c003_stock'] < $kilosIt) {
                    Response::error(
                        "Stock insuficiente de {$prod['c003_descripcion']}. " .
                        "Disponible: {$prod['c003_stock']} kg",
                        422
                    );
                }
                $precio   = (float)$prod['c003_precio_kg'];
                $subtotal = $kilosIt * $precio;
                $total   += $subtotal;
                $kilos   += $kilosIt;
                $preparados[] = [
                    'producto_id' => $pid,
                    'kilos'       => $kilosIt,
                    'precio'      => $precio,
                    'subtotal'    => $subtotal,
                    'stock_antes' => (float)$prod['c003_stock'],
                ];
            }

            // 2) Cabecera de la venta.
            Db::execute(
                "INSERT INTO 005_ventas
                    (c005_cliente_id, c005_usuario_id, c005_tipo, c005_medio_pago,
                     c005_total, c005_kilos_totales, c005_observaciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$cliId, $usuario, $tipo, $medio, $total, $kilos, $obs]
            );
            $ventaId = Db::lastInsertId();

            // 3) Items, descuento de stock y movimiento de mercaderia.
            foreach ($preparados as $pr) {
                Db::execute(
                    "INSERT INTO 006_venta_items
                        (c006_venta_id, c006_producto_id, c006_kilos, c006_precio_kg, c006_subtotal)
                     VALUES (?, ?, ?, ?, ?)",
                    [$ventaId, $pr['producto_id'], $pr['kilos'], $pr['precio'], $pr['subtotal']]
                );
                Db::execute(
                    "UPDATE 003_productos SET c003_stock = c003_stock - ? WHERE c003_id = ?",
                    [$pr['kilos'], $pr['producto_id']]
                );
                Db::execute(
                    "INSERT INTO 007_movimientos
                        (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
                         c007_stock_anterior, c007_stock_nuevo, c007_motivo)
                     VALUES (?, ?, 'salida', ?, ?, ?, ?)",
                    [
                        $pr['producto_id'], $usuario, $pr['kilos'],
                        $pr['stock_antes'], $pr['stock_antes'] - $pr['kilos'],
                        "Venta #{$ventaId}",
                    ]
                );
            }

            // 4) Si es fiado, crear la deuda en la libreta.
            $fiadoId = null;
            if ($tipo === 'fiado') {
                Db::execute(
                    "INSERT INTO 008_fiados
                        (c008_cliente_id, c008_venta_id, c008_monto_total,
                         c008_monto_pagado, c008_kilos_totales, c008_estado)
                     VALUES (?, ?, ?, 0, ?, 'pendiente')",
                    [$cliId, $ventaId, $total, $kilos]
                );
                $fiadoId = Db::lastInsertId();
                Db::execute(
                    "INSERT INTO 009_fiado_items
                        (c009_fiado_id, c009_producto_id, c009_kilos, c009_precio_kg, c009_subtotal)
                     SELECT ?, c006_producto_id, c006_kilos, c006_precio_kg, c006_subtotal
                       FROM 006_venta_items WHERE c006_venta_id = ?",
                    [$fiadoId, $ventaId]
                );
            }

            return [
                'venta_id'   => $ventaId,
                'total'      => $total,
                'kilos'      => $kilos,
                'medio_pago' => $medio,
                'fiado_id'   => $fiadoId,
            ];
        });
    }

    /** Ultimas ventas, con su detalle y el nombre del cliente. */
    public static function ultimas($limite = 20)
    {
        $limite = (int)$limite;
        return Db::select(
            "SELECT v.c005_id            AS venta_id,
                    v.c005_tipo          AS tipo,
                    v.c005_medio_pago    AS medio_pago,
                    v.c005_total         AS total,
                    v.c005_kilos_totales AS kilos,
                    v.c005_fecha         AS fecha,
                    v.c005_observaciones AS observaciones,
                    cl.c004_nombre       AS cliente,
                    u.c001_nombre        AS vendedor
               FROM 005_ventas v
          LEFT JOIN 004_clientes cl ON cl.c004_id = v.c005_cliente_id
               JOIN 001_usuarios u  ON u.c001_id  = v.c005_usuario_id
           ORDER BY v.c005_id DESC
              LIMIT {$limite}"
        );
    }

    /** Detalle (productos y kilos) de una venta. */
    public static function detalle($ventaId)
    {
        $venta = Db::selectOne(
            "SELECT v.c005_id            AS venta_id,
                    v.c005_tipo          AS tipo,
                    v.c005_medio_pago    AS medio_pago,
                    v.c005_total         AS total,
                    v.c005_kilos_totales AS kilos,
                    v.c005_fecha         AS fecha,
                    v.c005_observaciones AS observaciones,
                    cl.c004_nombre       AS cliente
               FROM 005_ventas v
          LEFT JOIN 004_clientes cl ON cl.c004_id = v.c005_cliente_id
              WHERE v.c005_id = ?",
            [$ventaId]
        );

        if ($venta === null) {
            Response::error('Venta inexistente', 404);
        }

        $venta['items'] = Db::select(
            "SELECT it.c006_id          AS item_id,
                    p.c003_descripcion  AS producto,
                    it.c006_kilos       AS kilos,
                    it.c006_precio_kg   AS precio,
                    it.c006_subtotal    AS subtotal
               FROM 006_venta_items it
               JOIN 003_productos p ON p.c003_id = it.c006_producto_id
              WHERE it.c006_venta_id = ?
           ORDER BY it.c006_id",
            [$ventaId]
        );

        return $venta;
    }
}
