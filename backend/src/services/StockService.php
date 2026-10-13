<?php
/**
 * Logica de negocio: stock (carga de mercaderia y ajustes).
 *
 * Antes usaba la vista v_stock_actual y los procedimientos sp_cargar_stock /
 * sp_ajustar_stock. Como InfinityFree no deja crear vistas ni procedimientos,
 * todo eso se hace ahora con SQL y transacciones desde PHP.
 */
namespace Polleria;

class StockService
{
    /**
     * Stock actual de todos los productos.
     *
     * Antes era la vista v_stock_actual. El "estado" sale de comparar el stock
     * con el minimo cargado: SIN STOCK (0), BAJO (por debajo del minimo),
     * OK (todo bien).
     */
    public static function actual()
    {
        return Db::select(
            "SELECT p.c003_id        AS producto_id,
                    p.c003_descripcion AS producto,
                    c.c002_descripcion    AS categoria,
                    p.c003_unidad    AS unidad,
                    p.c003_stock     AS stock,
                    p.c003_stock_minimo AS stock_minimo,
                    p.c003_precio_kg AS precio,
                    (p.c003_stock * p.c003_precio_kg) AS valorizado,
                    CASE
                      WHEN p.c003_stock <= 0 THEN 'SIN STOCK'
                      WHEN p.c003_stock <= p.c003_stock_minimo THEN 'BAJO'
                      ELSE 'OK'
                    END AS estado
               FROM 003_productos p
               JOIN 002_categorias c ON c.c002_id = p.c003_categoria_id
              WHERE p.c003_activo = 1
           ORDER BY c.c002_descripcion, p.c003_descripcion"
        );
    }

    /** Solo los productos con stock bajo o agotado (para el aviso del mostrador). */
    public static function alertas()
    {
        return Db::select(
            "SELECT p.c003_id        AS producto_id,
                    p.c003_descripcion AS producto,
                    c.c002_descripcion    AS categoria,
                    p.c003_stock     AS stock,
                    p.c003_stock_minimo AS stock_minimo,
                    CASE
                      WHEN p.c003_stock <= 0 THEN 'SIN STOCK'
                      WHEN p.c003_stock <= p.c003_stock_minimo THEN 'BAJO'
                      ELSE 'OK'
                    END AS estado
               FROM 003_productos p
               JOIN 002_categorias c ON c.c002_id = p.c003_categoria_id
              WHERE p.c003_activo = 1
                AND p.c003_stock <= p.c003_stock_minimo
           ORDER BY (p.c003_stock <= 0) DESC, p.c003_stock ASC"
        );
    }

    /** Carga mercaderia: suma kilos al stock de un producto. */
    public static function cargar($data)
    {
        // El usuario de la sesion es el que carga / ajusta (ver Auth.php).
        $usuario = Auth::id();

        $producto = Input::entero(Input::requerido($data, 'producto_id'), 'producto_id');
        $cantidad = Input::decimal(Input::requerido($data, 'cantidad'), 'cantidad');
        $motivo   = Input::opcional($data, 'motivo', 'Carga de mercaderia');

        if ($cantidad <= 0) {
            Response::error('La cantidad debe ser mayor a 0', 422);
        }

        return Db::transaction(function () use ($producto, $cantidad, $usuario, $motivo) {
            $prod = Db::selectOne(
                "SELECT c003_id, c003_stock FROM 003_productos WHERE c003_id = ? FOR UPDATE",
                [$producto]
            );
            if ($prod === null) {
                Response::error("Producto inexistente: {$producto}", 422);
            }
            $antes = (float)$prod['c003_stock'];
            $nuevo = $antes + $cantidad;

            Db::execute(
                "UPDATE 003_productos SET c003_stock = ? WHERE c003_id = ?",
                [$nuevo, $producto]
            );
            Db::execute(
                "INSERT INTO 007_movimientos
                    (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
                     c007_stock_anterior, c007_stock_nuevo, c007_motivo)
                 VALUES (?, ?, 'entrada', ?, ?, ?, ?)",
                [$producto, $usuario, $cantidad, $antes, $nuevo, $motivo]
            );

            return [
                'producto_id'    => $producto,
                'stock_anterior' => $antes,
                'stock_nuevo'    => $nuevo,
                'cargado'        => $cantidad,
            ];
        });
    }

    /** Ajuste manual (correccion) o merma. */
    public static function ajustar($data)
    {
        // El usuario de la sesion es el que carga / ajusta (ver Auth.php).
        $usuario = Auth::id();

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

        return Db::transaction(function () use ($producto, $nuevo, $usuario, $tipo, $motivo) {
            $prod = Db::selectOne(
                "SELECT c003_id, c003_stock FROM 003_productos WHERE c003_id = ? FOR UPDATE",
                [$producto]
            );
            if ($prod === null) {
                Response::error("Producto inexistente: {$producto}", 422);
            }
            $antes = (float)$prod['c003_stock'];
            $dif   = $nuevo - $antes;

            Db::execute(
                "UPDATE 003_productos SET c003_stock = ? WHERE c003_id = ?",
                [$nuevo, $producto]
            );
            Db::execute(
                "INSERT INTO 007_movimientos
                    (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
                     c007_stock_anterior, c007_stock_nuevo, c007_motivo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$producto, $usuario, $tipo, abs($dif), $antes, $nuevo, $motivo]
            );

            return [
                'producto_id'    => $producto,
                'stock_anterior' => $antes,
                'stock_nuevo'    => $nuevo,
                'diferencia'     => $dif,
            ];
        });
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
