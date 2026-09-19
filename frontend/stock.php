<?php
/** Stock: que hay, que falta, cargar mercaderia y ajustar. */
$titulo = 'Stock';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Stock</h1>
<p class="subtitulo">Mercadería disponible en el mostrador y depósito.</p>

<div class="grilla grilla-2">

  <!-- Cargar mercaderia -->
 <div>
    <div class="tarjeta">
      <h2>📦 Cargar mercadería</h2>
      <form id="f-cargar">
        <div class="campo">
          <label>Producto</label>
          <select id="c-producto" required></select>
        </div>
        <div class="campo">
          <label>Cantidad (kg o unidades según el producto)</label>
          <input type="number" step="0.001" min="0.001" id="c-cantidad" required placeholder="Ej: 10">
        </div>
        <div class="campo">
          <label>Motivo (opcional)</label>
          <input type="text" id="c-motivo" placeholder="Ej: compra a proveedor">
        </div>
        <button type="submit" class="btn btn-verde btn-grande">Agregar al stock</button>
      </form>
    </div>

    <div class="tarjeta">
      <h2>✏️ Ajustar / merma</h2>
      <form id="f-ajustar">
        <div class="campo">
          <label>Producto</label>
          <select id="a-producto" required></select>
        </div>
        <div class="campo">
          <label>Stock real contado</label>
          <input type="number" step="0.001" min="0" id="a-stock" required placeholder="Ej: 7.5">
        </div>
        <div class="campo">
          <label>Tipo</label>
          <select id="a-tipo">
            <option value="ajuste">Ajuste (corrección)</option>
            <option value="merma">Merma (se descartó)</option>
          </select>
        </div>
        <div class="campo">
          <label>Motivo</label>
          <input type="text" id="a-motivo" placeholder="Ej: se echó a perder">
        </div>
        <button type="submit" class="btn btn-naranja btn-grande">Guardar ajuste</button>
      </form>
    </div>
 </div>

  <!-- Stock actual -->
 <div>
    <div class="tarjeta">
      <h2>🔴 Falta reponer</h2>
      <div id="alertas"><div class="cargando">Cargando...</div></div>
    </div>

    <div class="tarjeta">
      <h2>📋 Stock actual</h2>
      <div id="tabla-stock"><div class="cargando">Cargando...</div></div>
    </div>
 </div>

</div>

<script src="assets/js/stock.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>