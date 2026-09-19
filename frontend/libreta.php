<?php
/** Libreta: quien debe, cuanto y el historial de cada cliente. */
$titulo = 'Libreta';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Libreta de fiado</h1>
<p class="subtitulo">Quién debe, cuánto y qué se llevó. Tocá un cliente para ver el detalle.</p>

<div class="tarjeta">
 <h2>👥 Clientes</h2>
 <div id="tabla"><div class="cargando">Cargando...</div></div>
</div>

<!-- Detalle del cliente (se muestra al hacer clic) -->
<div class="tarjeta" id="detalle" style="display:none">
 <h2 id="detalle-titulo"></h2>
 <div id="detalle-cuerpo"></div>
</div>

<script src="assets/js/libreta.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>