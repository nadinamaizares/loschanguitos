<?php
/**
 * Definicion de todas las rutas de la API.
 *
 * Es a proposito un mapa plano y legible: cada linea dice que URL llama a
 * que metodo del servicio. Devuelve el Router ya armado.
 *
 * Convencion de respuesta, en todos los casos:
 *   { "ok": true,  "data": ... }
 *   { "ok": false, "error": "..." }
 */

use Polleria\Router;
use Polleria\Response;
use Polleria\Input;
use Polleria\CatalogoService;
use Polleria\StockService;
use Polleria\VentaService;
use Polleria\FiadoService;
use Polleria\CajaService;

$r = new Router();

// --- Estado de la API (util para chequear que responde) ---------------------
$r->get('/api/ping', function () {
    Response::ok(['mensaje' => 'API de la polleria funcionando', 'fecha' => date('c')]);
});

// --- Catalogo ---------------------------------------------------------------
$r->get('/api/categorias', function () {
    Response::ok(CatalogoService::categorias());
});

$r->get('/api/productos', function () {
    Response::ok(CatalogoService::productos());
});

$r->post('/api/productos', function () {
    Response::ok(CatalogoService::crearProducto(Input::json()), 201);
});

$r->get('/api/clientes', function () {
    Response::ok(CatalogoService::clientes());
});

$r->post('/api/clientes', function () {
    Response::ok(CatalogoService::crearCliente(Input::json()), 201);
});

// --- Stock ------------------------------------------------------------------
$r->get('/api/stock', function () {
    Response::ok(StockService::actual());
});

$r->get('/api/stock/alertas', function () {
    Response::ok(StockService::alertas());
});

$r->get('/api/stock/movimientos', function () {
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    Response::ok(StockService::movimientos($limite));
});

$r->post('/api/stock/cargar', function () {
    Response::ok(StockService::cargar(Input::json()), 201);
});

$r->post('/api/stock/ajustar', function () {
    Response::ok(StockService::ajustar(Input::json()), 201);
});

// --- Ventas -----------------------------------------------------------------
$r->post('/api/ventas', function () {
    Response::ok(VentaService::registrar(Input::json()), 201);
});

$r->get('/api/ventas', function () {
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    Response::ok(VentaService::ultimas($limite));
});

$r->get('/api/ventas/{id}', function ($id) {
    Response::ok(VentaService::detalle((int)$id));
});

// --- Fiado (la libreta) -----------------------------------------------------
$r->get('/api/libreta', function () {
    Response::ok(FiadoService::libreta());
});

$r->get('/api/libreta/{clienteId}', function ($clienteId) {
    Response::ok(FiadoService::detalleCliente((int)$clienteId));
});

$r->post('/api/fiado/pagar', function () {
    Response::ok(FiadoService::pagar(Input::json()), 201);
});

// --- Caja -------------------------------------------------------------------
$r->get('/api/caja/resumen', function () {
    $fecha = $_GET['fecha'] ?? null;
    Response::ok(CajaService::resumen($fecha));
});

$r->get('/api/caja/ventas', function () {
    $fecha = $_GET['fecha'] ?? null;
    Response::ok(CajaService::ventasDia($fecha));
});

return $r;