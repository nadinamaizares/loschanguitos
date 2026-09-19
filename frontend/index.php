<?php
/**
 * Pantalla principal: registrar una venta.
 *
 * A la izquierda, los productos por categoria (se toca uno y se elige el peso).
 * A la derecha, el carrito con el total y los botones de cobro.
 *
 * Toda la logica de esta pantalla vive en assets/js/venta.js.
 */
$titulo = 'Vender';
require __DIR__ . '/paginas/layout.php';
?>

<h1>Nueva venta</h1>
<p class="subtitulo">Toca un producto para agregarlo, poné el peso y cobrá.</p>

<div class="grilla grilla-venta">

  <!-- ============ PRODUCTOS ============ -->
 <div>
    <div class="tarjeta">
      <div class="pestanas" id="pestanas"></div>
      <div class="productos" id="productos">
        <div class="cargando">Cargando productos...</div>
      </div>
    </div>
 </div>

  <!-- ============ CARRITO ============ -->
 <div>
    <div class="tarjeta carrito">
      <h2>🧾 Venta en curso</h2>

      <div class="items" id="items">
        <div class="vacio">Todavía no agregaste nada</div>
      </div>

      <div class="total-caja">
        <div class="linea"><span>Kilos totales</span><span id="total-kilos">0 kg</span></div>
        <div class="linea"><span>Artículos</span><span id="total-articulos">0</span></div>
        <div class="grande"><span id="total-monto">$ 0,00</span></div>
      </div>

      <!-- Datos del cobro -->
      <div class="campo">
        <label>Tipo de venta</label>
        <select id="tipo">
          <option value="contado">Contado (paga ahora)</option>
          <option value="fiado">Fiado (va a la libreta)</option>
        </select>
      </div>

      <div class="campo" id="campo-cliente" style="display:none">
        <label>Cliente</label>
        <div class="fila">
          <select id="cliente"></select>
          <button type="button" class="btn btn-gris btn-chico" onclick="nuevoCliente()">+ Nuevo</button>
        </div>
      </div>

      <div class="campo">
        <label>Observaciones (opcional)</label>
        <input type="text" id="observaciones" placeholder="Ej: entregar a las 20hs">
      </div>

      <button class="btn btn-primario btn-grande" id="btn-cobrar" disabled>
        Cobrar
      </button>
      <button class="btn btn-gris btn-chico" id="btn-limpiar" style="margin-top:8px;width:100%">
        Vaciar venta
      </button>
    </div>

    <!-- Ultimas ventas, como referencia rapida del mostrador -->
    <div class="tarjeta">
      <h2>🕐 Últimas ventas</h2>
      <div id="ultimas"><div class="cargando">Cargando...</div></div>
    </div>
 </div>

</div>

<script src="assets/js/venta.js"></script>
<?php require __DIR__ . '/paginas/pie.php'; ?>