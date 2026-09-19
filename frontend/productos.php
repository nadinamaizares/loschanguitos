<?php
/**
 * Productos: ver catalogo, dar de alta y editar (precio) productos, clientes y usuarios.
 *
 * Solo el admin ve las altas. El vendedor que entre a esta URL igual no ve los
 * formularios (y aunque los viera, la API le responde 403: el permiso se valida
 * en el backend, no aca).
 */
$titulo = 'Productos';
require __DIR__ . '/paginas/layout.php';
$esAdmin = ($usuarioActual['rol'] === 'admin');
?>
<h1>Productos</h1>
<p class="subtitulo">El catalogo de lo que vendes y a que precio. Toca <strong>Editar</strong> para cambiar el precio cuando sube.</p>
<div class="grilla grilla-2">
 <div>
    <div class="tarjeta">
      <h2>Catalogo</h2>
      <div id="tabla"><div class="cargando">Cargando...</div></div>
    </div>
 </div>
 <div>
<?php if ($esAdmin): ?>
    <div class="tarjeta" id="tarjeta-producto">
      <h2 id="h-producto">Producto nuevo</h2>
      <form id="f-nuevo">
        <input type="hidden" id="p-id" value="">
        <div class="campo">
          <label>Categoria</label>
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
          <label>Stock minimo (para que te avise)</label>
          <input type="number" step="0.001" min="0" id="p-minimo" value="0">
        </div>
        <button type="submit" class="btn btn-primario btn-grande" id="btn-guardar-prod">Crear producto</button>
        <button type="button" class="btn btn-gris btn-chico" id="btn-cancelar-prod" style="margin-top:8px;width:100%;display:none">
          Cancelar edicion
        </button>
      </form>
    </div>
    <div class="tarjeta">
      <h2>Cliente nuevo</h2>
      <form id="f-cliente">
        <div class="campo">
          <label>Nombre</label>
          <input type="text" id="cl-nombre" required placeholder="Ej: Don Ramirez">
        </div>
        <div class="campo">
          <label>Telefono (opcional)</label>
          <input type="text" id="cl-telefono" placeholder="11-5555-1234">
        </div>
        <button type="submit" class="btn btn-naranja btn-grande">Crear cliente</button>
      </form>
    </div>
    <div class="tarjeta">
      <h2>Usuario nuevo</h2>
      <form id="f-usuario">
        <div class="campo">
          <label>Nombre y apellido</label>
          <input type="text" id="u-nombre" required placeholder="Ej: Juan Perez">
        </div>
        <div class="fila">
          <div class="campo">
            <label>Usuario (para entrar)</label>
            <input type="text" id="u-usuario" required placeholder="Ej: juan">
          </div>
          <div class="campo">
            <label>Rol</label>
            <select id="u-rol">
              <option value="vendedor">Vendedor</option>
              <option value="admin">Administrador</option>
            </select>
          </div>
        </div>
        <div class="campo">
          <label>Contrasena (minimo 6 caracteres)</label>
          <input type="password" id="u-password" required minlength="6" placeholder="••">
        </div>
        <button type="submit" class="btn btn-primario btn-grande">Crear usuario</button>
      </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
      <h2>Solo consulta</h2>
      <p class="subtitulo">Tu usuario es <strong>vendedor</strong>: podes ver el
      catalogo, pero dar de alta productos, clientes y usuarios es tarea del
      administrador.</p>
    </div>
<?php endif; ?>
 </div>
</div>
<script>const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;</script>
<script src="assets/js/productos.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>