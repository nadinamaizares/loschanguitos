<?php
/** Cierre de caja: cuanto deberia haber y que se fue fiado. */
$titulo = 'Cierre de caja';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Cierre de caja</h1>
<p class="subtitulo">Lo vendido del día, lo cobrado de la libreta y lo que quedó fiado.</p>

<div class="campo" style="max-width:260px">
 <label>Fecha</label>
 <input type="date" id="fecha">
</div>

<div class="indicadores" id="indicadores"><div class="cargando">Cargando...</div></div>

<div class="tarjeta" style="margin-top:20px">
 <h2>🧾 Ventas del día</h2>
 <div id="ventas-dia"><div class="cargando">Cargando...</div></div>
</div>

<script src="assets/js/cierre.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>