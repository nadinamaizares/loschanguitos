<?php
/** Productos: ver catalogo y dar de alta productos nuevos. */
$titulo = 'Productos';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Productos</h1>
<p class="subtitulo">El catálogo de lo que vendés y a qué precio.</p>

<div class="grilla grilla-2">

 <div>
    <div class="tarjeta">
      <h2>📋 Catálogo</h2>
      <div id="tabla"><div class="cargando">Cargando...</div></div>
    </div>
 </div>

 <div>
    <div class="tarjeta">
      <h2>➕ Producto nuevo</h2>
      <form id="f-nuevo">
        <div class="campo">
          <label>Categoría</label>
          <select id="p-categoria" required></select>
        </div>
        <div class="campo">
          <label>Nombre del producto</label>
          <input type="text" id="p-nombre" required placeholder="Ej: Milanesa de pollo">
        </div>
        <div class="fila">
          <div class="campo">
            <label>Se vende por</label>
            <select id="p-unidad">
              <option value="kg">Kilo (se pesa)</option>
              <option value="unidad">Unidad (se cuenta)</option>
            </select>
          </div>
          <div class="campo">
            <label>Precio</label>
            <input type="number" step="0.01" min="0" id="p-precio" required placeholder="Ej: 8900">
          </div>
        </div>
        <div class="campo">
          <label>Stock mínimo (para que te avise)</label>
          <input type="number" step="0.001" min="0" id="p-minimo" value="0">
        </div>
        <button type="submit" class="btn btn-primario btn-grande">Crear producto</button>
      </form>
    </div>

    <div class="tarjeta">
      <h2>👤 Cliente nuevo</h2>
      <form id="f-cliente">
        <div class="campo">
          <label>Nombre</label>
          <input type="text" id="cl-nombre" required placeholder="Ej: Don Ramirez">
        </div>
        <div class="campo">
          <label>Teléfono (opcional)</label>
          <input type="text" id="cl-telefono" placeholder="11-5555-1234">
        </div>
        <button type="submit" class="btn btn-naranja btn-grande">Crear cliente</button>
      </form>
    </div>
 </div>

</div>

<script src="assets/js/productos.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>