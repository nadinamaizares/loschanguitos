<?php
/**
 * Logica de negocio: cierre de caja y reportes del dia.
 */
namespace Polleria;
class CajaService
{
    /**
     * Resumen del dia.
     *
     * Al cerrar, el pollero ve:
     *   - cuanto entro en EFECTIVO (lo que tiene que haber en la caja)
     *   - cuanto entro por VIRTUAL (transferencia / QR)
     *   - cuanto entro por TARJETA (posnet)
     *   - cuanto se fue FIADO (no entro plata, pero salio mercaderia)
     */
    public static function resumen($fecha = null)
    {
        $fecha = $fecha ?: date('Y-m-d');

        // Ventas del dia agrupadas por medio de pago.
        $porMedio = Db::select(
            "SELECT v.c005_medio_pago                        AS medio,
                    COUNT(*)                                AS cant_ventas,
                    COALESCE(SUM(v.c005_kilos_totales),0)   AS kilos,
                    COALESCE(SUM(v.c005_total),0)           AS total
               FROM 005_ventas v
              WHERE DATE(v.c005_fecha) = ?
                AND v.c005_tipo = 'contado'
           GROUP BY v.c005_medio_pago",
            [$fecha]
        );

        // Normalizamos para que SIEMPRE existan las tres claves.
        $resumen = [
            'fecha'    => $fecha,
            'efectivo' => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
            'virtual'  => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
            'tarjeta'  => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
            'fiado'    => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
        ];
        foreach ($porMedio as $f) {
            $resumen[$f['medio']] = [
                'cant_ventas' => (int)$f['cant_ventas'],
                'kilos'       => $f['kilos'],
                'total'       => $f['total'],
            ];
        }

        // Ventas fiadas del dia (por separado: no son un medio de pago).
        $fiado = Db::selectOne(
            "SELECT COUNT(*) AS cant_ventas,
                    COALESCE(SUM(v.c005_kilos_totales),0) AS kilos,
                    COALESCE(SUM(v.c005_total),0)         AS total
               FROM 005_ventas v
              WHERE DATE(v.c005_fecha) = ? AND v.c005_tipo = 'fiado'",
            [$fecha]
        );
        $resumen['fiado'] = [
            'cant_ventas' => (int)$fiado['cant_ventas'],
            'kilos'       => $fiado['kilos'],
            'total'       => $fiado['total'],
        ];

        // Pagos de la libreta recibidos hoy (tambien entran a la caja).
        $cobros = Db::selectOne(
            "SELECT COUNT(*) AS cant, COALESCE(SUM(c010_monto),0) AS total
               FROM 010_fiado_pagos
              WHERE DATE(c010_fecha) = ?",
            [$fecha]
        );
        $resumen['cobros_fiado'] = [
            'cant'  => (int)$cobros['cant'],
            'total' => $cobros['total'],
        ];

        // Lo que deberia haber fisicamente en la caja:
        //   efectivo de las ventas de contado + cobros de libreta (en efectivo).
        $resumen['total_en_caja'] = (float)$resumen['efectivo']['total']
                                  + (float)$resumen['cobros_fiado']['total'];

        // Total cobrado de forma NO efectiva (plata que entro al banco/posnet).
        $resumen['total_virtual'] = (float)$resumen['virtual']['total'];
        $resumen['total_tarjeta'] = (float)$resumen['tarjeta']['total'];

        // Lo que se fue fiado hoy.
        $resumen['total_fiado_dia'] = (float)$resumen['fiado']['total'];

        // Deuda total acumulada de todos los clientes.
        $libreta = Db::selectOne(
            "SELECT COALESCE(SUM(saldo_deudor),0) AS deuda_total,
                    COUNT(CASE WHEN saldo_deudor > 0 THEN 1 END) AS clientes_con_deuda
               FROM v_libreta_clientes"
        );
        $resumen['libreta'] = [
            'deuda_total'         => $libreta['deuda_total'],
            'clientes_con_deuda'  => (int)$libreta['clientes_con_deuda'],
        ];

        return $resumen;
    }

    /** Ventas de un dia en particular (para el listado del cierre). */
    public static function ventasDia($fecha = null)
    {
        $fecha = $fecha ?: date('Y-m-d');
        return Db::select(
            "SELECT v.c005_id            AS venta_id,
                    v.c005_tipo          AS tipo,
                    v.c005_medio_pago    AS medio_pago,
                    v.c005_total         AS total,
                    v.c005_kilos_totales AS kilos,
                    v.c005_fecha         AS fecha,
                    cl.c004_nombre       AS cliente
               FROM 005_ventas v
          LEFT JOIN 004_clientes cl ON cl.c004_id = v.c005_cliente_id
              WHERE DATE(v.c005_fecha) = ?
           ORDER BY v.c005_id DESC",
            [$fecha]
        );
    }
}