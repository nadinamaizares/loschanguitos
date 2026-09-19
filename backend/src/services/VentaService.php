<?php
/**
 * Logica de negocio: ventas y stock.
 *
 * Aca NO se escribe SQL de actualizacion a mano: todo lo que modifica datos
 * pasa por los procedimientos almacenados, que son los que garantizan la
 * atomicidad. Esta clase solo valida la entrada, arma los parametros y
 * traduce la respuesta.
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
        // Si es fiado, el SP lo fuerza a 'efectivo' (no entra plata).
        $medio   = Input::opcional($data, 'medio_pago', 'efectivo');
        if (!in_array($medio, ['efectivo', 'virtual', 'tarjeta'], true)) {
            Response::error("El medio de pago debe ser efectivo, virtual o tarjeta", 422);
        }

        if (!in_array($tipo, ['contado', 'fiado'], true)) {
            Response::error("El tipo debe ser 'contado' o 'fiado'", 422);
        }
        // Fiar SI lo puede hacer el vendedor: es una operacion del mostrador.
        // (No se restringe por rol a proposito.)

        if (!is_array($items) || count($items) === 0) {
            Response::error('La venta necesita al menos un item', 422);
        }

        // Armamos el formato que entiende el SP: "producto_id:kilos;producto_id:kilos"
        // (MariaDB 10.1 no soporta JSON, ver comentario en 03_procedimientos.sql)
        $pares = [];
        foreach ($items as $i => $it) {
            if (!isset($it['producto_id']) || !isset($it['kilos'])) {
                Response::error("El item #{$i} necesita producto_id y kilos", 422);
            }
            $pid   = Input::entero($it['producto_id'], "items[{$i}].producto_id");
            $kilos = Input::decimal($it['kilos'], "items[{$i}].kilos");

            if ($kilos <= 0) {
                Response::error("Los kilos del item #{$i} deben ser mayores a 0", 422);
            }

            $pares[] = $pid . ':' . $kilos;
        }
        $itemsStr = implode(';', $pares);

        $cliId = 0;
        if ($tipo === 'fiado') {
            if ($cliente === null) {
                Response::error('Una venta fiada necesita cliente_id', 422);
            }
            $cliId = Input::entero($cliente, 'cliente_id');
        } elseif ($cliente !== null) {
            $cliId = Input::entero($cliente, 'cliente_id');
        }

        return Db::call('sp_registrar_venta', [
            $usuario,
            $cliId,
            $tipo,
            $itemsStr,
            $obs,
            $medio,
        ]);
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