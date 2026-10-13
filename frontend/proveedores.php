<?php
/**
 * Proveedores: a quien le compro, que me dejo y cuanto le debo.
 *
 * Cargar una compra y registrar un pago lo puede hacer el vendedor (es de
 * mostrador). Dar de alta un proveedor es solo del admin (la API lo valida).
 */
$titulo = 'Proveedores';
require __DIR__ . '/paginas/layout.php';
$esAdmin = ($usuarioActual['rol'] === 'admin');
?>

<h1>Proveedores</h1>
<p class="subtitulo">Lo que te dejan, cuánto les debés y lo que les vas pagando. Tocá un proveedor para ver el detalle.</p>

<div class="grilla grilla-2">

 <div>
    <div class="tarjeta">
      <h2>🚚 Proveedores</h2>
      <div id="tabla"><div class="cargando">Cargando...</div></div>
    </div>

    <!-- Detalle del proveedor (se muestra al tocar "Ver") -->
    <div class="tarjeta" id="detalle" style="display:none">
      <h2 id="detalle-titulo"></h2>
      <div id="detalle-cuerpo"></div>
    </div>
 </div>

 <div>
    <div class="tarjeta">
      <h2>📦 Cargar compra</h2>
      <form id="f-compra">
        <div class="campo">
          <label>Proveedor</label>
          <select id="co-proveedor" required></select>
        </div>

        <div id="co-items"></div>
        <button type="button" class="btn btn-gris btn-chico" id="co-agregar" style="margin-bottom:12px">+ Agregar producto</button>

        <div class="total-caja" style="margin-bottom:12px"><div class="linea">
          <span><strong>Total de la compra</strong></span>
          <span class="grande" id="co-total" style="font-size:22px">$ 0,00</span>
        </div></div>

        <div class="fila">
          <div class="campo">
            <label>Le pagué ahora (opcional)</label>
            <input type="number" step="0.01" min="0" id="co-pago" placeholder="0 = queda debiendo">
          </div>
          <div class="campo">
            <label>Medio</label>
            <select id="co-medio">
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        </div>
        <div class="campo">
          <label>Nota (opcional)</label>
          <input type="text" id="co-obs" placeholder="Ej: 10 cajones, uno vino golpeado">
        </div>
        <button type="submit" class="btn btn-verde btn-grande">Guardar compra</button>
      </form>
    </div>

<?php if ($esAdmin): ?>
    <div class="tarjeta">
      <h2>Proveedor nuevo</h2>
      <form id="f-proveedor">
        <div class="campo">
          <label>Nombre</label>
          <input type="text" id="pr-nombre" required placeholder="Ej: Granja Don Pedro">
        </div>
        <div class="fila">
          <div class="campo">
            <label>Rubro (opcional)</label>
            <input type="text" id="pr-rubro" placeholder="Ej: Pollo, Huevos">
          </div>
          <div class="campo">
            <label>Teléfono (opcional)</label>
            <input type="text" id="pr-telefono" placeholder="11-5555-1234">
          </div>
        </div>
        <div class="campo">
          <label>Dirección (opcional)</label>
          <input type="text" id="pr-direccion">
        </div>
        <button type="submit" class="btn btn-naranja btn-grande">Crear proveedor</button>
      </form>
    </div>
<?php endif; ?>
 </div>

</div>

<script src="assets/js/proveedores.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>
