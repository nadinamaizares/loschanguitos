/* =============================================================================
   cierre.js - Cierre de caja del dia.

   Responde a la pregunta del mostrador al final de la jornada:
     - cuanto efectivo deberia haber en la caja
     - cuanto se fue a la libreta (no entro plata, pero salio mercaderia)
     - cuanto se debe en total
   ============================================================================= */

function iniciarCierre() {
  // Fecha de hoy por defecto
  const hoy = new Date();
  const iso = hoy.getFullYear() + '-' +
              String(hoy.getMonth() + 1).padStart(2, '0') + '-' +
              String(hoy.getDate()).padStart(2, '0');
  const campo = document.getElementById('fecha');
  campo.value = iso;
  campo.addEventListener('change', () => dibujar(campo.value));

  dibujar(iso);
}

async function dibujar(fechaSel) {
  const cont = document.getElementById('indicadores');
  const contVentas = document.getElementById('ventas-dia');

  try {
    const r = await apiGet('/caja/resumen?fecha=' + encodeURIComponent(fechaSel));

    cont.innerHTML = `
      <div class="indicador verde">
        <div class="et">Debería haber en caja</div>
        <div class="va">${plata(r.total_en_caja)}</div>
        <div class="de">Contado ${plata(r.contado.total)} + cobros de libreta ${plata(r.cobros_fiado.total)}</div>
      </div>
      <div class="indicador ambar">
        <div class="et">Se fue fiado hoy</div>
        <div class="va">${plata(r.total_fiado_dia)}</div>
        <div class="de">${r.fiado.cant_ventas} venta(s) · ${kg(r.fiado.kilos)}</div>
      </div>
      <div class="indicador rojo">
        <div class="et">Deuda total en la calle</div>
        <div class="va">${plata(r.libreta.deuda_total)}</div>
        <div class="de">${r.libreta.clientes_con_deuda} cliente(s) con deuda</div>
      </div>
      <div class="indicador">
        <div class="et">Ventas de contado</div>
        <div class="va">${plata(r.contado.total)}</div>
        <div class="de">${r.contado.cant_ventas} venta(s) · ${kg(r.contado.kilos)}</div>
      </div>
      <div class="indicador">
        <div class="et">Cobros de libreta</div>
        <div class="va">${plata(r.cobros_fiado.total)}</div>
        <div class="de">${r.cobros_fiado.cant} pago(s) recibido(s)</div>
      </div>
    `;

    const vs = await apiGet('/caja/ventas?fecha=' + encodeURIComponent(fechaSel));
    if (vs.length === 0) {
      contVentas.innerHTML = '<div class="vacio">No hubo ventas ese día</div>';
      return;
    }

    let html = '<table><thead><tr><th>#</th><th>Hora</th><th>Tipo</th><th>Cliente</th>' +
               '<th class="num">Kilos</th><th class="num">Total</th></tr></thead><tbody>';
    for (const v of vs) {
      html += '<tr>' +
        '<td>' + v.venta_id + '</td>' +
        '<td>' + fechaCorta(v.fecha) + '</td>' +
        '<td><span class="etiqueta ' + v.tipo + '">' + v.tipo + '</span></td>' +
        '<td>' + esc(v.cliente || '-') + '</td>' +
        '<td class="num">' + kg(v.kilos) + '</td>' +
        '<td class="num">' + plata(v.total) + '</td></tr>';
    }
    html += '</tbody></table>';
    contVentas.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

iniciarCierre();