<?php
/**
 * Logica de negocio: stock (carga de mercaderia y ajustes).
 */

namespace Polleria;

class StockService
{
    /** Stock actual de todos los productos (usa la vista v_stock_actual). */
    public static function actual()
    {
        return Db::select(
            "SELECT producto_id, producto, categoria, unidad, stock,
                    stock_minimo, precio, valorizado, estado
               FROM v_stock_actual
           ORDER BY categoria, producto"
        );
    }

    /** Solo los productos con stock bajo o agotado (para el aviso del mostrador). */
    public static function alertas()
    {
        return Db::select(
            "SELECT producto_id, producto, categoria, stock, stock_minimo, estado
               FROM v_stock_actual
              WHERE estado IN ('BAJO','SIN STOCK')
           ORDER BY estado DESC, stock ASC"
        );
    }

    /** Carga mercaderia: suma kilos al stock de un producto. */
    public static function cargar($data)
    {
        $cfg     = require __DIR__ . '/../../config.php';
        $usuario = isset($data['usuario_id'])
            ? Input::entero($data['usuario_id'], 'usuario_id')
            : $cfg['usuario_default_id'];

        $producto = Input::entero(Input::requerido($data, 'producto_id'), 'producto_id');
        $cantidad = Input::decimal(Input::requerido($data, 'cantidad'), 'cantidad');
        $motivo   = Input::opcional($data, 'motivo', 'Carga de mercaderia');

        if ($cantidad <= 0) {
            Response::error('La cantidad debe ser mayor a 0', 422);
        }

        return Db::call('sp_cargar_stock', [$producto, $cantidad, $usuario, $motivo]);
    }

    /** Ajuste manual (correccion) o merma. */
    public static function ajustar($data)
    {
        $cfg     = require __DIR__ . '/../../config.php';
        $usuario = isset($data['usuario_id'])
            ? Input::entero($data['usuario_id'], 'usuario_id')
            : $cfg['usuario_default_id'];

        $producto = Input::entero(Input::requerido($data, 'producto_id'), 'producto_id');
        $nuevo    = Input::decimal(Input::requerido($data, 'stock_nuevo'), 'stock_nuevo');
        $tipo     = Input::opcional($data, 'tipo', 'ajuste');
        $motivo   = Input::opcional($data, 'motivo', null);

        if (!in_array($tipo, ['ajuste', 'merma'], true)) {
            Response::error("El tipo debe ser 'ajuste' o 'merma'", 422);
        }
        if ($nuevo < 0) {
            Response::error('El stock no puede ser negativo', 422);
        }

        return Db::call('sp_ajustar_stock', [$producto, $nuevo, $usuario, $tipo, $motivo]);
    }

    /** Historial de movimientos (entradas, salidas, ajustes, mermas). */
    public static function movimientos($limite = 50)
    {
        $limite = (int)$limite;
        return Db::select(
            "SELECT m.c007_id            AS movimiento_id,
                    p.c003_descripcion   AS producto,
                    m.c007_tipo          AS tipo,
                    m.c007_cantidad      AS cantidad,
                    m.c007_stock_anterior AS stock_anterior,
                    m.c007_stock_nuevo   AS stock_nuevo,
                    m.c007_motivo        AS motivo,
                    m.c007_fecha         AS fecha,
                    u.c001_nombre        AS usuario
               FROM 007_movimientos m
               JOIN 003_productos p ON p.c003_id = m.c007_producto_id
               JOIN 001_usuarios u  ON u.c001_id = m.c007_usuario_id
           ORDER BY m.c007_id DESC
              LIMIT {$limite}"
        );
    }
}