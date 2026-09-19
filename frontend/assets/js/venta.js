/* =============================================================================
   venta.js - Pantalla de venta.

   Flujo: toca producto -> se agrega al carrito con un peso por defecto de 1 ->
   ajustas los kilos -> elegis contado o fiado -> cobrar.
   ============================================================================= */

// Estado de la pantalla
let PRODUCTOS   = [];   // catalogo completo
let CATEGORIAS  = [];   // para las pestanas
let CARRITO     = [];   // [{producto_id, producto, precio, kilos}]
let CAT_ACTIVA  = 'todas';

/* ---------------------------------------------------------------- carga */
async function iniciar() {
  try {
    [PRODUCTOS, CATEGORIAS] = await Promise.all([
      apiGet('/productos'),
      apiGet('/categorias')
    ]);
    dibujarPestanas();
    dibujarProductos();
  } catch (e) {
    document.getElementById('productos').innerHTML =
      '<div class="vacio">No se pudo cargar el catálogo: ' + esc(e.message) + '</div>';
    avisar('Error al cargar productos: ' + e.message, 'error');
  }

  cargarClientes();
  cargarUltimas();

  // Listeners
  document.getElementById('tipo').addEventListener('change', alCambiarTipo);
  document.getElementById('btn-cobrar').addEventListener('click', cobrar);
  document.getElementById('btn-limpiar').addEventListener('click', vaciar);
}

/* ------------------------------------------------------------ pestanas */
function dibujarPestanas() {
  const cont = document.getElementById('pestanas');
  let html = '<button class="activa" data-cat="todas">Todas</button>';
  for (const c of CATEGORIAS) {
    html += '<button data-cat="' + c.categoria_id + '">' + esc(c.categoria) + '</button>';
  }
  cont.innerHTML = html;

  cont.querySelectorAll('button').forEach(b => {
    b.addEventListener('click', () => {
      CAT_ACTIVA = b.dataset.cat;
      cont.querySelectorAll('button').forEach(x => x.classList.remove('activa'));
      b.classList.add('activa');
      dibujarProductos();
    });
  });
}

/* ----------------------------------------------------------- productos */
function dibujarProductos() {
  const cont = document.getElementById('productos');
  const lista = PRODUCTOS.filter(p => CAT_ACTIVA === 'todas' || String(p.categoria_id) === CAT_ACTIVA);

  if (lista.length === 0) {
    cont.innerHTML = '<div class="vacio">No hay productos en esta categoría</div>';
    return;
  }

  let html = '';
  for (const p of lista) {
    const stock = Number(p.stock) || 0;
    const sinStock = stock <= 0;
    html += `
      <button class="producto ${sinStock ? 'sin-stock' : ''}" data-id="${p.producto_id}">
        <span class="nom">${esc(p.producto)}</span>
        <span class="pre">${plata(p.precio)}</span>
        <span class="stk">${sinStock ? 'SIN STOCK' : 'Stock: ' + kg(stock)}</span>
      </button>`;
  }
  cont.innerHTML = html;

  cont.querySelectorAll('.producto').forEach(b => {
    b.addEventListener('click', () => agregar(Number(b.dataset.id)));
  });
}

/* ------------------------------------------------------------- carrito */
function agregar(productoId) {
  const p = PRODUCTOS.find(x => Number(x.producto_id) === productoId);
  if (!p) return;

  if (Number(p.stock) <= 0) {
    avisar('No hay stock de ' + p.producto, 'error');
    return;
  }

  const ya = CARRITO.find(x => x.producto_id === productoId);
  if (ya) {
    ya.kilos = Number(ya.kilos) + 1;
  } else {
    CARRITO.push({
      producto_id: productoId,
      producto:    p.producto,
      precio:      Number(p.precio),
      stock:       Number(p.stock),
      kilos:       1
    });
  }
  dibujarCarrito();
}

function quitar(productoId) {
  CARRITO = CARRITO.filter(x => x.producto_id !== productoId);
  dibujarCarrito();
}

function cambiarKilos(productoId, valor) {
  const it = CARRITO.find(x => x.producto_id === productoId);
  if (!it) return;
  // Acepta coma o punto, como lo escribe cualquiera.
  it.kilos = parseFloat(String(valor).replace(',', '.')) || 0;
  dibujarCarrito();
}

function dibujarCarrito() {
  const cont = document.getElementById('items');

  if (CARRITO.length === 0) {
    cont.innerHTML = '<div class="vacio">Todavía no agregaste nada</div>';
  } else {
    let html = '';
    for (const it of CARRITO) {
      const subtotal = it.kilos * it.precio;
      const excede = it.kilos > it.stock;
      html += `
        <div class="item">
          <span class="nom">${esc(it.producto)}${excede ? ' <small style="color:#b3261e">(más que el stock)</small>' : ''}</span>
          <input type="number" step="0.1" min="0" value="${it.kilos}"
                 onchange="cambiarKilos(${it.producto_id}, this.value)"
                 oninput="cambiarKilos(${it.producto_id}, this.value)">
          <span class="sub">${plata(subtotal)}</span>
          <button class="quitar" onclick="quitar(${it.producto_id})" title="Quitar">&times;</button>
        </div>`;
    }
    cont.innerHTML = html;
  }

  // Totales
  const kilos  = CARRITO.reduce((s, x) => s + x.kilos, 0);
  const monto  = CARRITO.reduce((s, x) => s + x.kilos * x.precio, 0);

  document.getElementById('total-kilos').textContent     = kg(kilos);
  document.getElementById('total-articulos').textContent = CARRITO.length;
  document.getElementById('total-monto').textContent     = plata(monto);
  document.getElementById('btn-cobrar').disabled         = CARRITO.length === 0;
}

function vaciar() {
  if (CARRITO.length && !confirm('¿Vaciar la venta?')) return;
  CARRITO = [];
  dibujarCarrito();
  document.getElementById('observaciones').value = '';
}

/* -------------------------------------------------------------- cobro */
function alCambiarTipo() {
  const esFiado = document.getElementById('tipo').value === 'fiado';
  document.getElementById('campo-cliente').style.display = esFiado ? 'block' : 'none';
}

async function cobrar() {
  if (CARRITO.length === 0) return;

  const tipo     = document.getElementById('tipo').value;
  const cliente  = document.getElementById('cliente').value;
  const obs      = document.getElementById('observaciones').value.trim();

  if (tipo === 'fiado' && !cliente) {
    avisar('Elegí un cliente para la venta fiada (o creá uno nuevo)', 'error');
    return;
  }

  // Validacion local: no mandamos kilos de mas al servidor.
  for (const it of CARRITO) {
    if (it.kilos <= 0) {
      avisar('Los kilos de "' + it.producto + '" deben ser mayores a 0', 'error');
      return;
    }
    if (it.kilos > it.stock) {
      avisar('No alcanza el stock de "' + it.producto + '" (hay ' + kg(it.stock) + ')', 'error');
      return;
    }
  }

  const datos = {
    tipo: tipo,
    cliente_id: cliente ? Number(cliente) : null,
    observaciones: obs || null,
    items: CARRITO.map(x => ({ producto_id: x.producto_id, kilos: x.kilos }))
  };

  const btn = document.getElementById('btn-cobrar');
  btn.disabled = true;
  btn.textContent = 'Cobrando...';

  try {
    const r = await apiPost('/ventas', datos);
    avisar('Venta #' + r.venta_id + ' registrada por ' + plata(r.total) +
           (tipo === 'fiado' ? ' (quedó a la libreta)' : ''), 'ok');

    CARRITO = [];
    dibujarCarrito();
    document.getElementById('observaciones').value = '';

    // Refrescamos catalogo (cambiaron los stocks) y las ultimas ventas.
    PRODUCTOS = await apiGet('/productos');
    dibujarProductos();
    cargarUltimas();
    cargarClientes();
  } catch (e) {
    avisar(e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Cobrar';
  }
}

/* ------------------------------------------------------------ clientes */
async function cargarClientes() {
  try {
    const cs = await apiGet('/clientes');
    const sel = document.getElementById('cliente');
    const actual = sel.value;
    let html = '<option value="">— Elegir cliente —</option>';
    for (const c of cs) {
      const debe = Number(c.saldo_deudor) || 0;
      html += '<option value="' + c.cliente_id + '">' +
              esc(c.cliente) + (debe > 0 ? ' (debe ' + plata(debe) + ')' : '') +
              '</option>';
    }
    sel.innerHTML = html;
    if (actual) sel.value = actual;
  } catch (e) {
    // No es critico: sin clientes se puede vender de contado igual.
    console.warn('No se pudieron cargar clientes:', e.message);
  }
}

async function nuevoCliente() {
  const nombre = prompt('Nombre del cliente:');
  if (!nombre || !nombre.trim()) return;
  const tel = prompt('Teléfono (opcional):') || null;

  try {
    const c = await apiPost('/clientes', { nombre: nombre.trim(), telefono: tel });
    avisar('Cliente "' + c.cliente + '" creado', 'ok');
    await cargarClientes();
    document.getElementById('cliente').value = c.cliente_id;
  } catch (e) {
    avisar(e.message, 'error');
  }
}

/* ------------------------------------------------------- ultimas ventas */
async function cargarUltimas() {
  const cont = document.getElementById('ultimas');
  try {
    const vs = await apiGet('/ventas?limite=6');
    if (vs.length === 0) {
      cont.innerHTML = '<div class="vacio">Todavía no hay ventas</div>';
      return;
    }
    let html = '<table><thead><tr><th>#</th><th>Tipo</th><th class="num">Total</th><th>Hora</th></tr></thead><tbody>';
    for (const v of vs) {
      html += '<tr><td>' + v.venta_id + '</td>' +
              '<td><span class="etiqueta ' + v.tipo + '">' + v.tipo + '</span></td>' +
              '<td class="num">' + plata(v.total) + '</td>' +
              '<td>' + fechaCorta(v.fecha) + '</td></tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">No se pudieron cargar las ventas</div>';
  }
}

iniciar();