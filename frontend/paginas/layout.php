<?php
/**
 * Layout comun: barra de navegacion + apertura del HTML.
 *
 * Cada pagina incluye este archivo al principio y 'pie.php' al final.
 * La variable $titulo define el <title> y cual pestana queda marcada.
 */

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
 <title><?= htmlspecialchars($titulo ?? 'Inicio') ?> - Avícola Los Changuitos</title>
 <link rel="stylesheet" href="assets/css/estilos.css">
 <!-- api.js va aca, en el head: deja API_BASE y apiGet/apiPost definidos ANTES
      de que corran los scripts de cada pagina (venta.js, stock.js, ...). -->
 <script src="assets/js/api.js"></script>
</head>
<body>

<div class="topbar">
 <span class="marca">🐔 Avícola Los Changuitos</span>
 <nav>
    <a href="index.php"       <?= navActiva('index.php', $paginaActual) ?>>Vender</a>
    <a href="stock.php"       <?= navActiva('stock.php', $paginaActual) ?>>Stock</a>
    <a href="libreta.php"     <?= navActiva('libreta.php', $paginaActual) ?>>Libreta</a>
    <a href="ventas.php"      <?= navActiva('ventas.php', $paginaActual) ?>>Ventas</a>
    <a href="cierre.php"      <?= navActiva('cierre.php', $paginaActual) ?>>Cierre de caja</a>
    <a href="productos.php"   <?= navActiva('productos.php', $paginaActual) ?>>Productos</a>
 </nav>
</div>

<div class="contenedor">
 <div id="aviso" class="aviso"></div>