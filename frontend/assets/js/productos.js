/* =============================================================================
   productos.js - Catalogo y altas de productos y clientes.
   ============================================================================= */

async function iniciarProductos() {
  await cargarCategorias();
  await dibujarCatalogo();

  document.getElementById('f-nuevo').addEventListener('submit', crearProducto);
  document.getElementById('f-cliente').addEventListener('submit', crearCliente);
}

async function cargarCategorias() {
  try {
    const cs = await apiGet('/categorias');
    const sel = document.getElementById('p-categoria');
    let html = '<option value="">— Elegir categoría —</option>';
    for (const c of cs) {
      html += '<option value="' + c.categoria_id + '">' + esc(c.categoria) + '</option>';
    }
    sel.innerHTML = html;
  } catch (e) {
    avisar('No se pudieron cargar las categorías: ' + e.message, 'error');
  }
}

async function dibujarCatalogo() {
  const cont = document.getElementById('tabla');
  try {
    const ps = await apiGet('/productos');
    if (ps.length === 0) { cont.innerHTML = '<div class="vacio">No hay productos cargados</div>'; return; }

    let html = '<table><thead><tr><th>Producto</th><th>Categoría</th>' +
               '<th>Unidad</th><th class="num">Precio</th><th class="num">Stock</th>' +
               '</tr></thead><tbody>';
    for (const p of ps) {
      html += '<tr>' +
        '<td>' + esc(p.producto) + '</td>' +
        '<td>' + esc(p.categoria) + '</td>' +
        '<td>' + (p.unidad === 'kg' ? 'por kg' : 'por unidad') + '</td>' +
        '<td class="num">' + plata(p.precio) + '</td>' +
        '<td class="num">' + kg(p.stock) + '</td></tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

async function crearProducto(ev) {
  ev.preventDefault();
  const datos = {
    categoria_id: Number(document.getElementById('p-categoria').value),
    producto:     document.getElementById('p-nombre').value.trim(),
    unidad:       document.getElementById('p-unidad').value,
    precio:       Number(document.getElementById('p-precio').value),
    stock_minimo: Number(document.getElementById('p-minimo').value) || 0
  };
  if (!datos.categoria_id) { avisar('Elegí una categoría', 'error'); return; }

  try {
    const r = await apiPost('/productos', datos);
    avisar('Producto "' + r.producto + '" creado. Ahora cargale stock.', 'ok');
    document.getElementById('f-nuevo').reset();
    await dibujarCatalogo();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

async function crearCliente(ev) {
  ev.preventDefault();
  const datos = {
    nombre:   document.getElementById('cl-nombre').value.trim(),
    telefono: document.getElementById('cl-telefono').value.trim() || null
  };

  try {
    const r = await apiPost('/clientes', datos);
    avisar('Cliente "' + r.cliente + '" creado', 'ok');
    document.getElementById('f-cliente').reset();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

iniciarProductos();