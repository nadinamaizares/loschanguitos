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
 *
 * SEGURIDAD: todas las rutas de negocio (productos, ventas, stock, libreta,
 * caja) estan detras de Auth::exigir(), que corta con 401 si no hay sesion.
 * Solo quedan abiertas las de autenticacion (login/logout/sesion) y el ping.
 */
use Polleria\Router;
use Polleria\Response;
use Polleria\Input;
use Polleria\Auth;
use Polleria\AuthService;
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
// --- Autenticacion (abiertas: son las que se usan ANTES de tener sesion) ----
$r->post('/api/login', function () {
    Response::ok(AuthService::login(Input::json()));
});
$r->post('/api/logout', function () {
    Response::ok(AuthService::logout());
});
// Quien soy: la usa el frontend al cargar para saber si hay sesion activa.
$r->get('/api/sesion', function () {
    $u = Auth::usuario();
    if ($u !== null) {
        // Le sumamos si es admin, para que el frontend sepa que mostrar.
        $u['es_admin'] = Auth::esAdmin();
    }
    Response::ok($u);
});
// --- Catalogo ---------------------------------------------------------------
$r->get('/api/categorias', function () {
    Auth::exigir();
    Response::ok(CatalogoService::categorias());
});
$r->get('/api/productos', function () {
    Auth::exigir();
    Response::ok(CatalogoService::productos());
});
$r->post('/api/productos', function () {
    Auth::exigirAdmin();   // solo el admin da de alta productos
    Response::ok(CatalogoService::crearProducto(Input::json()), 201);
});
$r->post('/api/productos/{id}', function ($id) {
    Auth::exigirAdmin();   // cambiar precio / datos: tambien admin
    Response::ok(CatalogoService::actualizarProducto((int)$id, Input::json()));
});
$r->put('/api/productos/{id}', function ($id) {
    Auth::exigirAdmin();
    Response::ok(CatalogoService::actualizarProducto((int)$id, Input::json()));
});
$r->get('/api/clientes', function () {
    Auth::exigir();   // el vendedor los necesita para poder fiar
    Response::ok(CatalogoService::clientes());
});
$r->post('/api/clientes', function () {
    Auth::exigirAdmin();   // solo el admin da de alta clientes
    Response::ok(CatalogoService::crearCliente(Input::json()), 201);
});
// --- Stock ------------------------------------------------------------------
$r->get('/api/stock', function () {
    Auth::exigir();
    Response::ok(StockService::actual());
});
$r->get('/api/stock/alertas', function () {
    Auth::exigir();
    Response::ok(StockService::alertas());
});
$r->get('/api/stock/movimientos', function () {
    Auth::exigir();
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    Response::ok(StockService::movimientos($limite));
});
$r->post('/api/stock/cargar', function () {
    Auth::exigir();
    Response::ok(StockService::cargar(Input::json()), 201);
});
$r->post('/api/stock/ajustar', function () {
    Auth::exigir();
    Response::ok(StockService::ajustar(Input::json()), 201);
});
// --- Ventas -----------------------------------------------------------------
$r->post('/api/ventas', function () {
    Auth::exigir();
    Response::ok(VentaService::registrar(Input::json()), 201);
});
$r->get('/api/ventas', function () {
    Auth::exigir();
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    Response::ok(VentaService::ultimas($limite));
});
$r->get('/api/ventas/{id}', function ($id) {
    Auth::exigir();
    Response::ok(VentaService::detalle((int)$id));
});
//// --- Fiado (la libreta) -----------------------------------------------------
// Fiar y cobrar es operacion de mostrador: la puede hacer el vendedor.
// (Solo admin: altas de productos, clientes y usuarios.)
$r->get('/api/libreta', function () {
    Auth::exigir();
    Response::ok(FiadoService::libreta());
});
$r->get('/api/libreta/{clienteId}', function ($clienteId) {
    Auth::exigir();
    Response::ok(FiadoService::detalleCliente((int)$clienteId));
});
$r->post('/api/fiado/pagar', function () {
    Auth::exigir();
    Response::ok(FiadoService::pagar(Input::json()), 201);
});
// --- Usuarios (solo admin) --------------------------------------------------
$r->get('/api/usuarios', function () {
    Auth::exigirAdmin();
    Response::ok(AuthService::usuarios());
});
$r->post('/api/usuarios', function () {
    Auth::exigirAdmin();
    Response::ok(AuthService::crear(Input::json()), 201);
});
// --- Caja -------------------------------------------------------------------
$r->get('/api/caja/resumen', function () {
    Auth::exigir();
    $fecha = $_GET['fecha'] ?? null;
    Response::ok(CajaService::resumen($fecha));
});
$r->get('/api/caja/ventas', function () {
    Auth::exigir();
    $fecha = $_GET['fecha'] ?? null;
    Response::ok(CajaService::ventasDia($fecha));
});
return $r;