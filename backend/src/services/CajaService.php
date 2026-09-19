<?php
/**
 * Logica de negocio: cierre de caja y reportes del dia.
 */

namespace Polleria;

class CajaService
{
    /**
     * Resumen del dia: cuanto se vendio de contado y cuanto fiado.
     *
     * La idea es que al cerrar, el pollero vea en una pantalla cuanto deberia
     * tener en la caja (contado) y cuanto se fue a la libreta (fiado).
     */
    public static function resumen($fecha = null)
    {
        $fecha = $fecha ?: date('Y-m-d');

        $porTipo = Db::select(
            "SELECT v.c005_tipo            AS tipo,
                    COUNT(*)               AS cant_ventas,
                    COALESCE(SUM(v.c005_kilos_totales),0) AS kilos,
                    COALESCE(SUM(v.c005_total),0)         AS total
               FROM 005_ventas v
              WHERE DATE(v.c005_fecha) = ?
           GROUP BY v.c005_tipo",
            [$fecha]
        );

        // Normalizamos para que siempre existan las dos claves.
        $resumen = [
            'fecha'   => $fecha,
            'contado' => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
            'fiado'   => ['cant_ventas' => 0, 'kilos' => 0, 'total' => 0],
        ];
        foreach ($porTipo as $f) {
            $resumen[$f['tipo']] = [
                'cant_ventas' => (int)$f['cant_ventas'],
                'kilos'       => $f['kilos'],
                'total'       => $f['total'],
            ];
        }

        // Pagos de fiado recibidos hoy: esa plata TAMBIEN entro a la caja.
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

        // Lo que deberia haber fisicamente en la caja: contado + cobros de libreta.
        $resumen['total_en_caja'] = (float)$resumen['contado']['total']
                                  + (float)$resumen['cobros_fiado']['total'];

        // Lo que se fue fiado hoy (no entro plata, pero salio mercaderia).
        $resumen['total_fiado_dia'] = (float)$resumen['fiado']['total'];

        // Deuda total acumulada de todos los clientes (libreta completa).
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