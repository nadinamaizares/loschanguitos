/* =============================================================================
   proveedores.js - Proveedores: compras y cuenta corriente.

   Lista de proveedores con lo que se les debe; al elegir uno se abre el
   detalle con las compras, los pagos y los ultimos precios de compra.
   A la derecha: cargar una compra (varios productos) y alta de proveedor.
   ============================================================================= */

let PRODUCTOS = [];
let PRECIOS = {};          // producto_id -> ultimo precio de compra del proveedor elegido
let proveedorAbierto = null;

async function iniciarProveedores() {
  try {
    PRODUCTOS = await apiGet('/productos');
  } catch (e) {
    avisar('No se pudieron cargar los productos: ' + e.message, 'error');
  }
  agregarItem();
  await dibujarProveedores();

  document.getElementById('co-agregar').addEventListener('click', () => agregarItem());
  document.getElementById('co-proveedor').addEventListener('change', cargarPreciosProveedor);
  document.getElementById('f-compra').addEventListener('submit', guardarCompra);
  const fProv = document.getElementById('f-proveedor');
  if (fProv) fProv.addEventListener('submit', crearProveedor);
}

/* ---------- Lista de proveedores ---------- */

async function dibujarProveedores() {
  const cont = document.getElementById('tabla');
  try {
    const ps = await apiGet('/proveedores');
    llenarSelectProveedores(ps);

    if (ps.length === 0) {
      cont.innerHTML = '<div class="vacio">Todavía no hay proveedores cargados' +
        (window.USUARIO.esAdmin ? ' — dalos de alta en "Proveedor nuevo"' : '') + '</div>';
      return;
    }

    let html = '<table><thead><tr>' +
      '<th>Proveedor</th><th>Rubro</th>' +
      '<th class="num">Comprado</th><th class="num">Pagado</th>' +
      '<th class="num">Le debo</th><th>Última compra</th><th></th>' +
      '</tr></thead><tbody>';

    let deudaTotal = 0;
    for (const p of ps) {
      const debe = Number(p.saldo) || 0;
      deudaTotal += debe;
      const enRojo = debe > 0 ? ' style="color:#b3261e;font-weight:700"' : '';
      html += '<tr>' +
        '<td>' + esc(p.proveedor) + '</td>' +
        '<td>' + esc(p.rubro || '-') + '</td>' +
        '<td class="num">' + plata(p.total_comprado) + '</td>' +
        '<td class="num">' + plata(p.total_pagado) + '</td>' +
        '<td class="num"' + enRojo + '>' + plata(debe) + '</td>' +
        '<td>' + (p.ultima_compra ? fecha(p.ultima_compra) : '-') + '</td>' +
        '<td><button class="btn btn-gris btn-chico" onclick="verProveedor(' + p.proveedor_id + ')">Ver</button></td>' +
        '</tr>';
    }
    html += '</tbody></table>';

    html += '<div class="total-caja" style="margin-top:16px"><div class="linea">' +
            '<span><strong>Total que les debo</strong></span>' +
            '<span class="grande" style="font-size:24px">' + plata(deudaTotal) + '</span></div></div>';

    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

function llenarSelectProveedores(ps) {
  const sel = document.getElementById('co-proveedor');
  const elegido = sel.value;
  sel.innerHTML = '<option value="">Elegí el proveedor...</option>' +
    ps.map(p => '<option value="' + p.proveedor_id + '">' + esc(p.proveedor) + '</option>').join('');
  sel.value = elegido;
}

/* ---------- Detalle ---------- */

async function verProveedor(proveedorId) {
  proveedorAbierto = proveedorId;
  const caja = document.getElementById('detalle');
  const tit = document.getElementById('detalle-titulo');
  const cuerpo = document.getElementById('detalle-cuerpo');

  caja.style.display = 'block';
  tit.textContent = 'Cargando...';
  cuerpo.innerHTML = '<div class="cargando">Cargando...</div>';
  caja.scrollIntoView({ behavior: 'smooth' });

  try {
    const p = await apiGet('/proveedores/' + proveedorId);
    const saldo = Number(p.saldo) || 0;
    tit.textContent = '🚚 ' + p.proveedor + ' — le debo ' + plata(saldo);

    let html = '';
    if (p.telefono || p.direccion) {
      html += '<p class="subtitulo">' + esc([p.telefono, p.direccion].filter(Boolean).join(' · ')) + '</p>';
    }
    if (saldo > 0) {
      html += '<button class="btn btn-verde" onclick="pagarProveedor(' + p.proveedor_id + ', ' + saldo + ')">Registrar pago</button>';
    }

    // --- Compras ---
    html += '<h3 style="margin-top:18px">Compras</h3>';
    if (!p.compras.length) {
      html += '<div class="vacio">Sin compras registradas</div>';
    } else {
      html += '<table><thead><tr><th>#</th><th>Fecha</th><th>Qué dejó</th><th class="num">Total</th><th>Nota</th></tr></thead><tbody>';
      for (const c of p.compras) {
        html += '<tr>' +
          '<td>' + c.compra_id + '</td>' +
          '<td>' + fechaCorta(c.fecha) + '</td>' +
          '<td>' + esc(c.detalle || '-') + '</td>' +
          '<td class="num">' + plata(c.total) + '</td>' +
          '<td>' + esc(c.observaciones || '-') + '</td></tr>';
      }
      html += '</tbody></table>';
    }

    // --- Pagos ---
    html += '<h3 style="margin-top:22px">Pagos hechos</h3>';
    if (!p.pagos.length) {
      html += '<div class="vacio">Sin pagos registrados</div>';
    } else {
      html += '<table><thead><tr><th>Fecha</th><th class="num">Monto</th><th>Medio</th><th>Nota</th></tr></thead><tbody>';
      for (const pa of p.pagos) {
        html += '<tr><td>' + fechaCorta(pa.fecha) + '</td>' +
                '<td class="num">' + plata(pa.monto) + '</td>' +
                '<td>' + esc(pa.medio) + '</td>' +
                '<td>' + esc(pa.observaciones || '-') + '</td></tr>';
      }
      html += '</tbody></table>';
    }

    // --- Ultimos precios de compra ---
    if (p.precios.length) {
      html += '<h3 style="margin-top:22px">Últimos precios de compra</h3>';
      html += '<table><thead><tr><th>Producto</th><th class="num">Precio</th></tr></thead><tbody>';
      for (const pr of p.precios) {
        html += '<tr><td>' + esc(pr.producto) + '</td><td class="num">' + plata(pr.precio_compra) + '</td></tr>';
      }
      html += '</tbody></table>';
    }

    cuerpo.innerHTML = html;
  } catch (e) {
    cuerpo.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

/** Pago a cuenta. Si deja el monto vacio, se asume que paga todo el saldo. */
async function pagarProveedor(proveedorId, saldo) {
  const entrada = prompt('Le debés ' + plata(saldo) + '.\n¿Cuánto le pagás? (Enter = todo)', saldo);
  if (entrada === null) return;

  const monto = parseFloat(String(entrada).replace(',', '.')) || saldo;
  if (monto <= 0) { avisar('El monto debe ser mayor a 0', 'error'); return; }
  if (monto > saldo + 0.001) { avisar('El pago no puede superar la deuda (' + plata(saldo) + ')', 'error'); return; }

  const medio = confirm('¿El pago fue en efectivo?\n\nAceptar = efectivo, Cancelar = transferencia')
    ? 'efectivo' : 'transferencia';

  try {
    const r = await apiPost('/proveedores/pagar', { proveedor_id: proveedorId, monto: monto, medio_pago: medio });
    avisar('Pago registrado. Saldo restante: ' + plata(r.saldo_restante) +
           (r.saldo_restante <= 0 ? ' — cuenta saldada ✅' : ''), 'ok');
    await dibujarProveedores();
    verProveedor(proveedorId);
  } catch (e) {
    avisar(e.message, 'error');
  }
}

/* ---------- Cargar compra ---------- */

function agregarItem() {
  const cont = document.getElementById('co-items');
  const fila = document.createElement('div');
  fila.className = 'fila co-item';
  fila.style.alignItems = 'flex-end';
  fila.innerHTML =
    '<div class="campo" style="flex:2"><label>Producto</label>' +
      '<select class="it-producto" required><option value="">Elegí...</option>' +
      PRODUCTOS.map(p => '<option value="' + p.producto_id + '" data-unidad="' + esc(p.unidad) + '">' + esc(p.producto) + '</option>').join('') +
      '</select></div>' +
    '<div class="campo"><label class="it-lbl-cant">Cantidad</label>' +
      '<input type="number" class="it-cantidad" step="0.001" min="0.001" required></div>' +
    '<div class="campo"><label>Precio compra</label>' +
      '<input type="number" class="it-precio" step="0.01" min="0" required></div>' +
    '<div class="campo" style="flex:0 0 auto;text-align:center"><label title="Si lo destildás, no suma al stock (ej: pollo entero que después se trocea)">Suma stock</label>' +
      '<input type="checkbox" class="it-stock" checked></div>' +
    '<div class="campo" style="flex:0 0 auto"><button type="button" class="btn btn-gris btn-chico it-quitar" title="Quitar">✕</button></div>';
  cont.appendChild(fila);

  const selProd = fila.querySelector('.it-producto');
  selProd.addEventListener('change', () => {
    const op = selProd.selectedOptions[0];
    fila.querySelector('.it-lbl-cant').textContent = 'Cantidad' + (op && op.dataset.unidad ? ' (' + op.dataset.unidad + ')' : '');
    sugerirPrecio(fila);
    recalcularTotal();
  });
  fila.querySelector('.it-cantidad').addEventListener('input', recalcularTotal);
  fila.querySelector('.it-precio').addEventListener('input', recalcularTotal);
  fila.querySelector('.it-quitar').addEventListener('click', () => {
    if (document.querySelectorAll('.co-item').length > 1) fila.remove();
    recalcularTotal();
  });
}

/** Al elegir proveedor: traemos sus ultimos precios para sugerirlos. */
async function cargarPreciosProveedor() {
  PRECIOS = {};
  const id = document.getElementById('co-proveedor').value;
  if (!id) return;
  try {
    const p = await apiGet('/proveedores/' + id);
    for (const pr of p.precios) PRECIOS[pr.producto_id] = Number(pr.precio_compra);
    document.querySelectorAll('.co-item').forEach(sugerirPrecio);
    recalcularTotal();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

/** Completa el precio con el ultimo que se le pago, solo si esta vacio. */
function sugerirPrecio(fila) {
  const prod = fila.querySelector('.it-producto').value;
  const inp = fila.querySelector('.it-precio');
  if (prod && inp.value === '' && PRECIOS[prod] !== undefined) {
    inp.value = PRECIOS[prod];
  }
}

function leerItems() {
  return [...document.querySelectorAll('.co-item')].map(f => ({
    producto_id:   f.querySelector('.it-producto').value,
    cantidad:      f.querySelector('.it-cantidad').value,
    precio_compra: f.querySelector('.it-precio').value,
    suma_stock:    f.querySelector('.it-stock').checked ? 1 : 0
  }));
}

function recalcularTotal() {
  const total = leerItems().reduce((s, it) => s + (Number(it.cantidad) || 0) * (Number(it.precio_compra) || 0), 0);
  document.getElementById('co-total').textContent = plata(total);
  return total;
}

async function guardarCompra(ev) {
  ev.preventDefault();
  const proveedorId = document.getElementById('co-proveedor').value;
  const items = leerItems().filter(it => it.producto_id);
  if (!proveedorId) { avisar('Elegí el proveedor', 'error'); return; }
  if (!items.length) { avisar('Agregá al menos un producto', 'error'); return; }

  const total = recalcularTotal();
  const pago = parseFloat(String(document.getElementById('co-pago').value).replace(',', '.')) || 0;
  if (pago > total + 0.001) { avisar('El pago no puede superar el total (' + plata(total) + ')', 'error'); return; }

  try {
    const r = await apiPost('/compras', {
      proveedor_id: proveedorId,
      items: items,
      pago_monto: pago,
      pago_medio: document.getElementById('co-medio').value,
      observaciones: document.getElementById('co-obs').value
    });
    avisar('Compra #' + r.compra_id + ' guardada por ' + plata(r.total) +
           (r.quedo_debiendo > 0 ? '. Quedás debiendo ' + plata(r.quedo_debiendo) : '. Pagada completa ✅'), 'ok');

    // Limpiamos el formulario (dejamos el proveedor elegido por si carga otra).
    document.getElementById('co-items').innerHTML = '';
    agregarItem();
    document.getElementById('co-pago').value = '';
    document.getElementById('co-obs').value = '';
    recalcularTotal();
    await cargarPreciosProveedor();
    await dibujarProveedores();
    verProveedor(Number(proveedorId));
  } catch (e) {
    avisar(e.message, 'error');
  }
}

/* ---------- Alta de proveedor (admin) ---------- */

async function crearProveedor(ev) {
  ev.preventDefault();
  try {
    const r = await apiPost('/proveedores', {
      nombre:    document.getElementById('pr-nombre').value,
      rubro:     document.getElementById('pr-rubro').value,
      telefono:  document.getElementById('pr-telefono').value,
      direccion: document.getElementById('pr-direccion').value
    });
    avisar('Proveedor "' + r.proveedor + '" creado', 'ok');
    ev.target.reset();
    await dibujarProveedores();
    document.getElementById('co-proveedor').value = r.proveedor_id;
    cargarPreciosProveedor();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

iniciarProveedores();
