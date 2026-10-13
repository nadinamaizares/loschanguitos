<?php
/**
 * Logica de negocio: proveedores (compras de mercaderia + cuenta corriente).
 *
 * Tablas: 011_proveedores, 012_proveedor_productos, 013_compras,
 * 014_compra_items y 015_proveedor_pagos (ver database/04_proveedores.sql).
 *
 * Cuenta corriente GLOBAL por proveedor:
 *   saldo a pagar = SUM(compras) - SUM(pagos)
 * Los pagos no se atan a una compra en particular.
 *
 * Igual que el resto del sistema: sin vistas ni procedimientos (InfinityFree),
 * la logica va aca con transacciones.
 */
namespace Polleria;

class ProveedorService
{
    /** SQL de la "vista" saldo por proveedor (reutilizado abajo). */
    public static function sqlCuentaProveedores(): string
    {
        return
            "SELECT pr.c011_id        AS proveedor_id,
                    pr.c011_nombre    AS proveedor,
                    pr.c011_telefono  AS telefono,
                    pr.c011_direccion AS direccion,
                    pr.c011_rubro     AS rubro,
                    pr.c011_observaciones AS observaciones,
                    COALESCE(co.total, 0) AS total_comprado,
                    COALESCE(pa.total, 0) AS total_pagado,
                    COALESCE(co.total, 0) - COALESCE(pa.total, 0) AS saldo,
                    co.ultima AS ultima_compra
               FROM 011_proveedores pr
          LEFT JOIN (SELECT c013_proveedor_id AS id, SUM(c013_total) AS total, MAX(c013_fecha) AS ultima
                       FROM 013_compras GROUP BY c013_proveedor_id) co ON co.id = pr.c011_id
          LEFT JOIN (SELECT c015_proveedor_id AS id, SUM(c015_monto) AS total
                       FROM 015_proveedor_pagos GROUP BY c015_proveedor_id) pa ON pa.id = pr.c011_id
              WHERE pr.c011_activo = 1";
    }

    /** Todos los proveedores con lo que se les debe. */
    public static function listar()
    {
        return Db::select(
            self::sqlCuentaProveedores() . " ORDER BY saldo DESC, proveedor"
        );
    }

    /** Detalle de un proveedor: compras, pagos y precios de compra conocidos. */
    public static function detalle($proveedorId)
    {
        $prov = Db::selectOne(
            "SELECT * FROM (" . self::sqlCuentaProveedores() . ") AS cuenta WHERE proveedor_id = ?",
            [$proveedorId]
        );
        if ($prov === null) {
            Response::error('Proveedor inexistente', 404);
        }

        $prov['compras'] = Db::select(
            "SELECT co.c013_id    AS compra_id,
                    co.c013_fecha AS fecha,
                    co.c013_total AS total,
                    co.c013_observaciones AS observaciones,
                    GROUP_CONCAT(CONCAT(it.c014_cantidad, ' ', p.c003_unidad, ' ',
                                        COALESCE(it.c014_descripcion, p.c003_descripcion))
                                 ORDER BY it.c014_id ASC SEPARATOR ' + ') AS detalle
               FROM 013_compras co
          LEFT JOIN 014_compra_items it ON it.c014_compra_id = co.c013_id
          LEFT JOIN 003_productos p     ON p.c003_id = it.c014_producto_id
              WHERE co.c013_proveedor_id = ?
           GROUP BY co.c013_id, co.c013_fecha, co.c013_total, co.c013_observaciones
           ORDER BY co.c013_id DESC",
            [$proveedorId]
        );

        $prov['pagos'] = Db::select(
            "SELECT c015_id            AS pago_id,
                    c015_monto         AS monto,
                    c015_medio_pago    AS medio,
                    c015_observaciones AS observaciones,
                    c015_fecha         AS fecha
               FROM 015_proveedor_pagos
              WHERE c015_proveedor_id = ?
           ORDER BY c015_id DESC",
            [$proveedorId]
        );

        // Ultimo precio de compra por producto: sirve para sugerirlo al cargar.
        $prov['precios'] = Db::select(
            "SELECT pp.c012_producto_id  AS producto_id,
                    p.c003_descripcion   AS producto,
                    pp.c012_precio_compra AS precio_compra
               FROM 012_proveedor_productos pp
               JOIN 003_productos p ON p.c003_id = pp.c012_producto_id
              WHERE pp.c012_proveedor_id = ? AND pp.c012_activo = 1
           ORDER BY p.c003_descripcion",
            [$proveedorId]
        );

        return $prov;
    }

    /** Alta de proveedor. */
    public static function crear($data)
    {
        $nombre = trim((string)Input::requerido($data, 'nombre'));
        if ($nombre === '') {
            Response::error('El nombre no puede estar vacio', 422);
        }

        $existe = Db::selectOne(
            "SELECT c011_id FROM 011_proveedores WHERE c011_nombre = ?",
            [$nombre]
        );
        if ($existe !== null) {
            Response::error('Ya existe un proveedor con ese nombre', 409);
        }

        Db::execute(
            "INSERT INTO 011_proveedores
                (c011_nombre, c011_telefono, c011_direccion, c011_rubro, c011_observaciones)
             VALUES (?, ?, ?, ?, ?)",
            [
                $nombre,
                Input::opcional($data, 'telefono', null),
                Input::opcional($data, 'direccion', null),
                Input::opcional($data, 'rubro', null),
                Input::opcional($data, 'observaciones', null),
            ]
        );

        return [
            'proveedor_id' => Db::lastInsertId(),
            'proveedor'    => $nombre,
        ];
    }

    /**
     * Registra una compra ("vino el pollero y me dejo mercaderia").
     *
     * Espera:
     *   proveedor_id, observaciones (opcional),
     *   items: [{ producto_id, cantidad, precio_compra, suma_stock (0/1) }],
     *   pago_monto (opcional): lo que se le pago en el momento,
     *   pago_medio (opcional): efectivo | transferencia | otro
     *
     * Todo en una transaccion: la compra, sus items, la suba de stock (los que
     * suman), el ultimo precio de compra y el pago al momento.
     */
    public static function registrarCompra($data)
    {
        $usuario     = Auth::id();
        $proveedorId = Input::entero(Input::requerido($data, 'proveedor_id'), 'proveedor_id');
        $obs         = Input::opcional($data, 'observaciones', null);
        $items       = $data['items'] ?? [];
        $pagoMonto   = Input::decimal(Input::opcional($data, 'pago_monto', 0), 'pago_monto');
        $pagoMedio   = Input::opcional($data, 'pago_medio', 'efectivo');

        if (!is_array($items) || count($items) === 0) {
            Response::error('La compra tiene que tener al menos un producto', 422);
        }
        if ($pagoMonto < 0) {
            Response::error('El pago no puede ser negativo', 422);
        }
        if (!in_array($pagoMedio, ['efectivo', 'transferencia', 'otro'], true)) {
            Response::error('El medio de pago debe ser efectivo, transferencia u otro', 422);
        }

        // Validamos y normalizamos los items antes de abrir la transaccion.
        $lineas = [];
        $total  = 0.0;
        foreach ($items as $i => $it) {
            $n        = $i + 1;
            $producto = Input::entero(Input::requerido($it, 'producto_id'), "producto_id (item {$n})");
            $cantidad = Input::decimal(Input::requerido($it, 'cantidad'), "cantidad (item {$n})");
            $precio   = Input::decimal(Input::requerido($it, 'precio_compra'), "precio_compra (item {$n})");
            $sumaStk  = !empty($it['suma_stock']) ? 1 : 0;

            if ($cantidad <= 0) {
                Response::error("La cantidad del item {$n} debe ser mayor a 0", 422);
            }
            if ($precio < 0) {
                Response::error("El precio del item {$n} no puede ser negativo", 422);
            }

            $subtotal = round($cantidad * $precio, 2);
            $total   += $subtotal;
            $lineas[] = [
                'producto'    => $producto,
                'descripcion' => Input::opcional($it, 'descripcion', null),
                'cantidad'    => $cantidad,
                'precio'      => $precio,
                'subtotal'    => $subtotal,
                'suma_stock'  => $sumaStk,
            ];
        }
        $total = round($total, 2);

        if ($pagoMonto > $total + 0.001) {
            Response::error('El pago al momento no puede superar el total de la compra (' .
                number_format($total, 2, '.', '') . ')', 422);
        }

        return Db::transaction(function () use ($usuario, $proveedorId, $obs, $lineas, $total, $pagoMonto, $pagoMedio) {
            $prov = Db::selectOne(
                "SELECT c011_id, c011_nombre FROM 011_proveedores WHERE c011_id = ? AND c011_activo = 1",
                [$proveedorId]
            );
            if ($prov === null) {
                Response::error('Proveedor inexistente', 404);
            }

            Db::execute(
                "INSERT INTO 013_compras (c013_proveedor_id, c013_usuario_id, c013_total, c013_observaciones)
                 VALUES (?, ?, ?, ?)",
                [$proveedorId, $usuario, $total, $obs]
            );
            $compraId = Db::lastInsertId();

            foreach ($lineas as $l) {
                $prod = Db::selectOne(
                    "SELECT c003_id, c003_stock FROM 003_productos WHERE c003_id = ? FOR UPDATE",
                    [$l['producto']]
                );
                if ($prod === null) {
                    Response::error("Producto inexistente: {$l['producto']}", 422);
                }

                Db::execute(
                    "INSERT INTO 014_compra_items
                        (c014_compra_id, c014_producto_id, c014_descripcion, c014_cantidad,
                         c014_precio_compra, c014_subtotal, c014_suma_stock)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$compraId, $l['producto'], $l['descripcion'], $l['cantidad'],
                     $l['precio'], $l['subtotal'], $l['suma_stock']]
                );

                // Lo que suma al stock entra igual que una carga de mercaderia.
                if ($l['suma_stock'] === 1) {
                    $antes = (float)$prod['c003_stock'];
                    $nuevo = $antes + $l['cantidad'];
                    Db::execute(
                        "UPDATE 003_productos SET c003_stock = ? WHERE c003_id = ?",
                        [$nuevo, $l['producto']]
                    );
                    Db::execute(
                        "INSERT INTO 007_movimientos
                            (c007_producto_id, c007_usuario_id, c007_tipo, c007_cantidad,
                             c007_stock_anterior, c007_stock_nuevo, c007_motivo)
                         VALUES (?, ?, 'entrada', ?, ?, ?, ?)",
                        [$l['producto'], $usuario, $l['cantidad'], $antes, $nuevo,
                         "Compra #{$compraId} a {$prov['c011_nombre']}"]
                    );
                }

                // Guardamos el ultimo precio de compra para sugerirlo la proxima vez.
                Db::execute(
                    "INSERT INTO 012_proveedor_productos (c012_proveedor_id, c012_producto_id, c012_precio_compra)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE c012_precio_compra = VALUES(c012_precio_compra), c012_activo = 1",
                    [$proveedorId, $l['producto'], $l['precio']]
                );
            }

            $pagoId = null;
            if ($pagoMonto > 0) {
                Db::execute(
                    "INSERT INTO 015_proveedor_pagos
                        (c015_proveedor_id, c015_usuario_id, c015_monto, c015_medio_pago, c015_observaciones)
                     VALUES (?, ?, ?, ?, ?)",
                    [$proveedorId, $usuario, $pagoMonto, $pagoMedio, "Pago al recibir la compra #{$compraId}"]
                );
                $pagoId = Db::lastInsertId();
            }

            return [
                'compra_id'    => $compraId,
                'total'        => $total,
                'pagado_ahora' => $pagoMonto,
                'pago_id'      => $pagoId,
                'quedo_debiendo' => round($total - $pagoMonto, 2),
            ];
        });
    }

    /** Registra un pago a cuenta ("le deje 20 mil al pollero"). */
    public static function pagar($data)
    {
        $usuario     = Auth::id();
        $proveedorId = Input::entero(Input::requerido($data, 'proveedor_id'), 'proveedor_id');
        $monto       = Input::decimal(Input::requerido($data, 'monto'), 'monto');
        $medio       = Input::opcional($data, 'medio_pago', 'efectivo');
        $obs         = Input::opcional($data, 'observaciones', null);

        if ($monto <= 0) {
            Response::error('El monto debe ser mayor a 0', 422);
        }
        if (!in_array($medio, ['efectivo', 'transferencia', 'otro'], true)) {
            Response::error('El medio de pago debe ser efectivo, transferencia u otro', 422);
        }

        return Db::transaction(function () use ($usuario, $proveedorId, $monto, $medio, $obs) {
            // Bloqueamos el proveedor para que dos pagos a la vez no pasen el saldo.
            $prov = Db::selectOne(
                "SELECT c011_id FROM 011_proveedores WHERE c011_id = ? AND c011_activo = 1 FOR UPDATE",
                [$proveedorId]
            );
            if ($prov === null) {
                Response::error('Proveedor inexistente', 404);
            }

            $cuenta = Db::selectOne(
                "SELECT saldo FROM (" . self::sqlCuentaProveedores() . ") AS cuenta WHERE proveedor_id = ?",
                [$proveedorId]
            );
            $saldo = (float)$cuenta['saldo'];
            if ($monto > $saldo + 0.001) {
                Response::error('El pago supera lo que se le debe. Saldo: ' . number_format($saldo, 2, '.', ''), 422);
            }

            Db::execute(
                "INSERT INTO 015_proveedor_pagos
                    (c015_proveedor_id, c015_usuario_id, c015_monto, c015_medio_pago, c015_observaciones)
                 VALUES (?, ?, ?, ?, ?)",
                [$proveedorId, $usuario, $monto, $medio, $obs]
            );

            return [
                'pago_id'        => Db::lastInsertId(),
                'proveedor_id'   => $proveedorId,
                'monto'          => $monto,
                'saldo_restante' => round($saldo - $monto, 2),
            ];
        });
    }
}
