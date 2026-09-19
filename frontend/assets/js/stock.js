/* =============================================================================
   stock.js - Carga de mercaderia, ajustes y vista del stock.
   ============================================================================= */

let PRODS_STOCK = [];

async function iniciarStock() {
  await cargarTodo();

  document.getElementById('f-cargar').addEventListener('submit', cargarMercaderia);
  document.getElementById('f-ajustar').addEventListener('submit', ajustarStock);
}

async function cargarTodo() {
  try {
    PRODS_STOCK = await apiGet('/productos');
    llenarSelects();
    await dibujarStock();
    await dibujarAlertas();
  } catch (e) {
    avisar('Error al cargar stock: ' + e.message, 'error');
  }
}

function llenarSelects() {
  for (const id of ['c-producto', 'a-producto']) {
    const sel = document.getElementById(id);
    const actual = sel.value;
    let html = '<option value="">— Elegir producto —</option>';
    for (const p of PRODS_STOCK) {
      html += '<option value="' + p.producto_id + '">' +
              esc(p.producto) + ' (' + kg(p.stock) + ')</option>';
    }
    sel.innerHTML = html;
    if (actual) sel.value = actual;
  }
}

async function dibujarStock() {
  const cont = document.getElementById('tabla-stock');
  try {
    const filas = await apiGet('/stock');
    if (filas.length === 0) { cont.innerHTML = '<div class="vacio">No hay productos</div>'; return; }

    let html = '<table><thead><tr><th>Producto</th><th>Categoría</th><th class="num">Stock</th>' +
               '<th class="num">Mínimo</th><th class="num">Precio</th><th>Estado</th></tr></thead><tbody>';
    for (const f of filas) {
      html += '<tr>' +
        '<td>' + esc(f.producto) + '</td>' +
        '<td>' + esc(f.categoria) + '</td>' +
        '<td class="num">' + kg(f.stock) + '</td>' +
        '<td class="num">' + kg(f.stock_minimo) + '</td>' +
        '<td class="num">' + plata(f.precio) + '</td>' +
        '<td><span class="etiqueta ' + f.estado.replace(' ', '-') + '">' + esc(f.estado) + '</span></td>' +
       '</tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

async function dibujarAlertas() {
  const cont = document.getElementById('alertas');
  try {
    const filas = await apiGet('/stock/alertas');
    if (filas.length === 0) {
      cont.innerHTML = '<div class="vacio">Todo en orden, no falta nada 👍</div>';
      return;
    }
    let html = '<table><thead><tr><th>Producto</th><th class="num">Stock</th><th>Estado</th></tr></thead><tbody>';
    for (const f of filas) {
      html += '<tr><td>' + esc(f.producto) + '</td>' +
              '<td class="num">' + kg(f.stock) + '</td>' +
              '<td><span class="etiqueta ' + f.estado.replace(' ', '-') + '">' + esc(f.estado) + '</span></td></tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

async function cargarMercaderia(ev) {
  ev.preventDefault();
  const producto = document.getElementById('c-producto').value;
  const cantidad = document.getElementById('c-cantidad').value;
  const motivo   = document.getElementById('c-motivo').value.trim();

  if (!producto) { avisar('Elegí un producto', 'error'); return; }

  try {
    const r = await apiPost('/stock/cargar', {
      producto_id: Number(producto),
      cantidad: Number(cantidad),
      motivo: motivo || null
    });
    avisar('Stock actualizado: ' + kg(r.stock_anterior) + ' → ' + kg(r.stock_nuevo), 'ok');
    document.getElementById('f-cargar').reset();
    await cargarTodo();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

async function ajustarStock(ev) {
  ev.preventDefault();
  const producto = document.getElementById('a-producto').value;
  const stock    = document.getElementById('a-stock').value;
  const tipo     = document.getElementById('a-tipo').value;
  const motivo   = document.getElementById('a-motivo').value.trim();

  if (!producto) { avisar('Elegí un producto', 'error'); return; }
  if (!confirm('¿Confirmás el ajuste del stock?')) return;

  try {
    const r = await apiPost('/stock/ajustar', {
      producto_id: Number(producto),
      stock_nuevo: Number(stock),
      tipo: tipo,
      motivo: motivo || null
    });
    avisar('Ajustado: ' + kg(r.stock_anterior) + ' → ' + kg(r.stock_nuevo), 'ok');
    document.getElementById('f-ajustar').reset();
    await cargarTodo();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

iniciarStock();