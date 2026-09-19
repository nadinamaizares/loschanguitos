<?php
/**
 * Layout comun: barra de navegacion + apertura del HTML.
 *
 * Cada pagina incluye este archivo al principio y 'pie.php' al final.
 * La variable $titulo define el <title> y cual pestana queda marcada.
 *
 * SEGURIDAD: aca vive la guardia de sesion del lado del servidor. Si no hay
 * usuario logueado, se redirige a login.php y no se muestra NINGUNA pagina.
 * El backend igual valida en cada ruta (Auth::exigir()), esto es la primera
 * barrera para no mostrar ni el HTML sin sesion.
 */
// --- Guardia de sesion ------------------------------------------------------
require_once __DIR__ . '/../../backend/src/bootstrap.php';
$usuarioActual = \Polleria\Auth::usuario();
if ($usuarioActual === null) {
    header('Location: login.php');
    exit;
}

$paginaActual = basename($_SERVER['PHP_SELF']);
/** Marca la clase 'activa' en la pestana que corresponde. */
function navActiva(string $archivo, string $actual): string
{
    return $archivo === $actual ? ' class="activa"' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title><?= htmlspecialchars($titulo ?? 'Inicio') ?> - Avicola Los Changuitos</title>
 <link rel="stylesheet" href="assets/css/estilos.css">
 <!-- api.js va aca, en el head: deja API_BASE y apiGet/apiPost definidos ANTES
      de que corran los scripts de cada pagina (venta.js, stock.js, ...). -->
 <script src="assets/js/api.js"></script>
 <?php
 // Le pasamos al JS quien es el usuario, para ocultar lo que no le corresponde.
 // (La seguridad real esta en el backend; esto es solo para no mostrar botones
 //  que igual le van a dar 403.)
 ?>
 <script>
   window.USUARIO = {
     nombre: <?= json_encode($usuarioActual['nombre'], JSON_UNESCAPED_UNICODE) ?>,
     rol:    <?= json_encode($usuarioActual['rol']) ?>,
     esAdmin: <?= $usuarioActual['rol'] === 'admin' ? 'true' : 'false' ?>
   };
 </script>
</head>
<body>
<div class="topbar">
 <span class="marca">🐔 Avicola Los Changuitos</span>
 <nav>
    <a href="index.php"       <?= navActiva('index.php', $paginaActual) ?>>Vender</a>
    <a href="stock.php"       <?= navActiva('stock.php', $paginaActual) ?>>Stock</a>
    <a href="libreta.php"     <?= navActiva('libreta.php', $paginaActual) ?>>Libreta</a>
    <a href="ventas.php"      <?= navActiva('ventas.php', $paginaActual) ?>>Ventas</a>
    <a href="cierre.php"      <?= navActiva('cierre.php', $paginaActual) ?>>Cierre de caja</a>
    <?php if ($usuarioActual['rol'] === 'admin'): ?>
    <a href="productos.php"   <?= navActiva('productos.php', $paginaActual) ?>>Productos</a>
    <?php endif; ?>
 </nav>
 <!-- Usuario logueado + salir -->
 <div class="usuario-box">
    <span class="usuario-nombre" title="<?= htmlspecialchars($usuarioActual['rol']) ?>">
      <?= htmlspecialchars($usuarioActual['nombre']) ?>
    </span>
    <button type="button" class="btn btn-gris btn-chico" onclick="cerrarSesion()">Salir</button>
 </div>
</div>
<div class="contenedor">
 <div id="aviso" class="aviso"></div>