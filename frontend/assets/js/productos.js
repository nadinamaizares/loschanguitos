/* =============================================================================
   productos.js - Catalogo, alta y edicion de productos (sobre todo el precio),
   clientes y usuarios.
   Los formularios solo existen en la pagina si el usuario es admin; por eso
   enganchamos los eventos solo cuando el elemento esta presente.
   ============================================================================= */
let CATALOGO = [];

async function iniciarProductos() {
  await dibujarCatalogo();

  const fNuevo = document.getElementById('f-nuevo');
  if (fNuevo) {
    await cargarCategorias();
    fNuevo.addEventListener('submit', guardarProducto);
    const btnCancelar = document.getElementById('btn-cancelar-prod');
    if (btnCancelar) btnCancelar.addEventListener('click', cancelarEdicion);
  }

  const fCliente = document.getElementById('f-cliente');
  if (fCliente) fCliente.addEventListener('submit', crearCliente);

  const fUsuario = document.getElementById('f-usuario');
  if (fUsuario) fUsuario.addEventListener('submit', crearUsuario);
}

async function cargarCategorias() {
  try {
    const cs = await apiGet('/categorias');
    const sel = document.getElementById('p-categoria');
    if (!sel) return;
    let html = '<option value="">- Elegir categoria -</option>';
    for (const c of cs) {
      html += '<option value="' + c.categoria_id + '">' + esc(c.categoria) + '</option>';
    }
    sel.innerHTML = html;
  } catch (e) {
    avisar('No se pudieron cargar las categorias: ' + e.message, 'error');
  }
}

function esAdmin() {
  return typeof ES_ADMIN !== 'undefined' && ES_ADMIN;
}

async function dibujarCatalogo() {
  const cont = document.getElementById('tabla');
  try {
    CATALOGO = await apiGet('/productos');
    renderTabla();
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

function renderTabla() {
  const cont = document.getElementById('tabla');
  const ps = CATALOGO;
  if (ps.length === 0) {
    cont.innerHTML = '<div class="vacio">No hay productos cargados</div>';
    return;
  }
  const idEditando = document.getElementById('p-id') ? document.getElementById('p-id').value : '';
  let html = '<table><thead><tr><th>Producto</th><th>Categoria</th>' +
             '<th>Unidad</th><th class="num">Precio</th><th class="num">Stock</th>' +
             (esAdmin() ? '<th></th>' : '') +
             '</tr></thead><tbody>';
  for (const p of ps) {
    const editando = String(p.producto_id) === String(idEditando);
    html += '<tr' + (editando ? ' class="editando"' : '') + '>' +
      '<td>' + esc(p.producto) + '</td>' +
      '<td>' + esc(p.categoria) + '</td>' +
      '<td>' + (p.unidad === 'kg' ? 'por kg' : 'por unidad') + '</td>' +
      '<td class="num">' + plata(p.precio) + '</td>' +
      '<td class="num">' + kg(p.stock) + '</td>';
    if (esAdmin()) {
      html += '<td class="acciones"><button type="button" class="btn btn-gris btn-chico" ' +
              'onclick="editarProducto(' + p.producto_id + ')">Editar</button></td>';
    }
    html += '</tr>';
  }
  html += '</tbody></table>';
  cont.innerHTML = html;
}

function datosFormProducto() {
  return {
    categoria_id: Number(document.getElementById('p-categoria').value),
    producto:     document.getElementById('p-nombre').value.trim(),
    unidad:       document.getElementById('p-unidad').value,
    precio:       Number(document.getElementById('p-precio').value),
    stock_minimo: Number(document.getElementById('p-minimo').value) || 0
  };
}

async function guardarProducto(ev) {
  ev.preventDefault();
  const datos = datosFormProducto();
  if (!datos.categoria_id) { avisar('Elegi una categoria', 'error'); return; }
  const id = document.getElementById('p-id').value;
  try {
    if (id) {
      const r = await apiPost('/productos/' + id, datos);
      avisar('"' + r.producto + '" quedó a ' + plata(r.precio), 'ok');
      cancelarEdicion();
    } else {
      const r = await apiPost('/productos', datos);
      avisar('Producto "' + r.producto + '" creado. Ahora cargale stock.', 'ok');
      document.getElementById('f-nuevo').reset();
    }
    await dibujarCatalogo();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

function editarProducto(productoId) {
  const p = CATALOGO.find(x => Number(x.producto_id) === Number(productoId));
  if (!p) return;
  const form = document.getElementById('f-nuevo');
  if (!form) {
    avisar('Solo el administrador puede cambiar el precio', 'error');
    return;
  }

  document.getElementById('p-id').value = p.producto_id;
  document.getElementById('p-categoria').value = p.categoria_id;
  document.getElementById('p-nombre').value = p.producto;
  document.getElementById('p-unidad').value = p.unidad;
  document.getElementById('p-precio').value = Number(p.precio);
  document.getElementById('p-minimo').value = Number(p.stock_minimo) || 0;

  document.getElementById('h-producto').textContent = 'Editar: ' + p.producto;
  document.getElementById('btn-guardar-prod').textContent = 'Guardar cambios';
  document.getElementById('btn-cancelar-prod').style.display = '';
  const tarjeta = document.getElementById('tarjeta-producto');
  if (tarjeta) tarjeta.classList.add('form-editando');

  renderTabla();

  const precio = document.getElementById('p-precio');
  precio.focus();
  precio.select();
  if (tarjeta && tarjeta.scrollIntoView) {
    tarjeta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function cancelarEdicion() {
  const form = document.getElementById('f-nuevo');
  if (!form) return;
  form.reset();
  document.getElementById('p-id').value = '';
  document.getElementById('h-producto').textContent = 'Producto nuevo';
  document.getElementById('btn-guardar-prod').textContent = 'Crear producto';
  document.getElementById('btn-cancelar-prod').style.display = 'none';
  const tarjeta = document.getElementById('tarjeta-producto');
  if (tarjeta) tarjeta.classList.remove('form-editando');
  if (CATALOGO.length) renderTabla();
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

async function crearUsuario(ev) {
  ev.preventDefault();
  const datos = {
    nombre:   document.getElementById('u-nombre').value.trim(),
    usuario:  document.getElementById('u-usuario').value.trim(),
    password: document.getElementById('u-password').value,
    rol:      document.getElementById('u-rol').value
  };
  try {
    const r = await apiPost('/usuarios', datos);
    avisar('Usuario "' + datos.usuario + '" creado', 'ok');
    document.getElementById('f-usuario').reset();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

iniciarProductos();