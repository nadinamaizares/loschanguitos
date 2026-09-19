<?php
/** Ventas: historial del dia y detalle de cada una. */
$titulo = 'Ventas';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Ventas</h1>
<p class="subtitulo">Historial de ventas registradas.</p>

<div class="tarjeta">
 <h2>🧾 Últimas ventas</h2>
 <div id="tabla"><div class="cargando">Cargando...</div></div>
</div>

<div class="tarjeta" id="detalle" style="display:none">
 <h2 id="detalle-titulo"></h2>
 <div id="detalle-cuerpo"></div>
</div>

<script src="assets/js/ventas.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>