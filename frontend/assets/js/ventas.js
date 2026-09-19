/* =============================================================================
   ventas.js - Historial de ventas y su detalle.
   ============================================================================= */

async function iniciarVentas() {
  const cont = document.getElementById('tabla');
  try {
    const vs = await apiGet('/ventas?limite=50');
    if (vs.length === 0) {
      cont.innerHTML = '<div class="vacio">Todavía no hay ventas registradas</div>';
      return;
    }

    let html = '<table><thead><tr><th>#</th><th>Fecha</th><th>Tipo</th><th>Cliente</th>' +
               '<th class="num">Kilos</th><th class="num">Total</th><th>Vendedor</th><th></th>' +
               '</tr></thead><tbody>';
    for (const v of vs) {
      html += '<tr>' +
        '<td>' + v.venta_id + '</td>' +
        '<td>' + fechaCorta(v.fecha) + '</td>' +
        '<td><span class="etiqueta ' + v.tipo + '">' + v.tipo + '</span></td>' +
        '<td>' + esc(v.cliente || '-') + '</td>' +
        '<td class="num">' + kg(v.kilos) + '</td>' +
        '<td class="num">' + plata(v.total) + '</td>' +
        '<td>' + esc(v.vendedor || '-') + '</td>' +
        '<td><button class="btn btn-gris btn-chico" onclick="verVenta(' + v.venta_id + ')">Detalle</button></td>' +
       '</tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

async function verVenta(id) {
  const caja = document.getElementById('detalle');
  const tit = document.getElementById('detalle-titulo');
  const cuerpo = document.getElementById('detalle-cuerpo');

  caja.style.display = 'block';
  tit.textContent = 'Cargando...';
  caja.scrollIntoView({ behavior: 'smooth' });

  try {
    const v = await apiGet('/ventas/' + id);
    tit.textContent = '🧾 Venta #' + v.venta_id + ' — ' + plata(v.total);

    let html = '<p class="subtitulo">' +
      fecha(v.fecha) + ' · <span class="etiqueta ' + v.tipo + '">' + v.tipo + '</span>' +
      (v.cliente ? ' · Cliente: ' + esc(v.cliente) : '') +
      '</p>';

    html += '<table><thead><tr><th>Producto</th><th class="num">Kilos</th>' +
            '<th class="num">Precio</th><th class="num">Subtotal</th></tr></thead><tbody>';
    for (const it of v.items) {
      html += '<tr><td>' + esc(it.producto) + '</td>' +
              '<td class="num">' + kg(it.kilos) + '</td>' +
              '<td class="num">' + plata(it.precio) + '</td>' +
              '<td class="num">' + plata(it.subtotal) + '</td></tr>';
    }
    html += '</tbody></table>';

    if (v.observaciones) {
      html += '<p><strong>Observaciones:</strong> ' + esc(v.observaciones) + '</p>';
    }

    cuerpo.innerHTML = html;
  } catch (e) {
    cuerpo.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

iniciarVentas();