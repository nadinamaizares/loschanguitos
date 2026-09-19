<?php
/**
 * Logica de negocio: catalogo (categorias, productos y clientes).
 */

namespace Polleria;

class CatalogoService
{
    /** Productos activos, con su categoria. Es lo que carga la pantalla de venta. */
    public static function productos()
    {
        return Db::select(
            "SELECT p.c003_id          AS producto_id,
                    p.c003_descripcion AS producto,
                    p.c003_unidad      AS unidad,
                    p.c003_precio_kg   AS precio,
                    p.c003_stock       AS stock,
                    c.c002_id          AS categoria_id,
                    c.c002_descripcion AS categoria
               FROM 003_productos p
               JOIN 002_categorias c ON c.c002_id = p.c003_categoria_id
              WHERE p.c003_activo = 1
           ORDER BY c.c002_orden, p.c003_descripcion"
        );
    }

    /** Categorias activas, ordenadas. */
    public static function categorias()
    {
        return Db::select(
            "SELECT c002_id AS categoria_id, c002_descripcion AS categoria
               FROM 002_categorias
              WHERE c002_activo = 1
           ORDER BY c002_orden, c002_descripcion"
        );
    }

    /** Clientes activos, con su saldo deudor actual. */
    public static function clientes()
    {
        return Db::select(
            "SELECT cl.c004_id       AS cliente_id,
                    cl.c004_nombre   AS cliente,
                    cl.c004_telefono AS telefono,
                    COALESCE(lc.saldo_deudor, 0) AS saldo_deudor
               FROM 004_clientes cl
          LEFT JOIN v_libreta_clientes lc ON lc.cliente_id = cl.c004_id
              WHERE cl.c004_activo = 1
           ORDER BY cl.c004_nombre"
        );
    }

    /** Alta de cliente (para la libreta). */
    public static function crearCliente($data)
    {
        $nombre = trim((string)Input::requerido($data, 'nombre'));
        if ($nombre === '') {
            Response::error('El nombre no puede estar vacio', 422);
        }

        $tel  = Input::opcional($data, 'telefono', null);
        $dir  = Input::opcional($data, 'direccion', null);
        $obs  = Input::opcional($data, 'observaciones', null);

        $existe = Db::selectOne(
            "SELECT c004_id FROM 004_clientes WHERE c004_nombre = ?",
            [$nombre]
        );
        if ($existe !== null) {
            Response::error('Ya existe un cliente con ese nombre', 409);
        }

        Db::execute(
            "INSERT INTO 004_clientes
                (c004_nombre, c004_telefono, c004_direccion, c004_observaciones)
             VALUES (?, ?, ?, ?)",
            [$nombre, $tel, $dir, $obs]
        );

        return [
            'cliente_id' => (int)Db::conn()->lastInsertId(),
            'cliente'    => $nombre,
        ];
    }

    /** Alta de producto (para el alta de mercaderia nueva). */
    public static function crearProducto($data)
    {
        $catId  = Input::entero(Input::requerido($data, 'categoria_id'), 'categoria_id');
        $descr  = trim((string)Input::requerido($data, 'producto'));
        $unidad = Input::opcional($data, 'unidad', 'kg');
        $precio = Input::decimal(Input::requerido($data, 'precio'), 'precio');
        $minimo = Input::decimal(Input::opcional($data, 'stock_minimo', 0), 'stock_minimo');

        if (!in_array($unidad, ['kg', 'unidad'], true)) {
            Response::error("La unidad debe ser 'kg' o 'unidad'", 422);
        }
        if ($precio < 0) {
            Response::error('El precio no puede ser negativo', 422);
        }

        Db::execute(
            "INSERT INTO 003_productos
                (c003_categoria_id, c003_descripcion, c003_unidad,
                 c003_precio_kg, c003_stock, c003_stock_minimo)
             VALUES (?, ?, ?, ?, 0, ?)",
            [$catId, $descr, $unidad, $precio, $minimo]
        );

        return [
            'producto_id' => (int)Db::conn()->lastInsertId(),
            'producto'    => $descr,
        ];
    }
}