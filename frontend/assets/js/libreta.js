/* =============================================================================
   libreta.js - La libreta de fiado.

   Lista de clientes con su saldo; al elegir uno se abre el detalle con los
   fiados que tiene, que se llevo en cada uno, y los pagos que fue haciendo.
   Desde ahi tambien se registra un pago.
   ============================================================================= */

async function iniciarLibreta() {
  await dibujarLibreta();
}

async function dibujarLibreta() {
  const cont = document.getElementById('tabla');
  try {
    const cs = await apiGet('/libreta');
    if (cs.length === 0) {
      cont.innerHTML = '<div class="vacio">Todavía no hay clientes en la libreta</div>';
      return;
    }

    let html = '<table><thead><tr>' +
      '<th>Cliente</th><th>Teléfono</th>' +
      '<th class="num">Total fiado</th><th class="num">Pagado</th>' +
      '<th class="num">Debe</th><th>Deuda más antigua</th><th></th>' +
      '</tr></thead><tbody>';

    let deudaTotal = 0;
    for (const c of cs) {
      const debe = Number(c.saldo_deudor) || 0;
      deudaTotal += debe;
      const enRojo = debe > 0 ? ' style="color:#b3261e;font-weight:700"' : '';
      html += '<tr>' +
        '<td>' + esc(c.cliente) + '</td>' +
        '<td>' + esc(c.telefono || '-') + '</td>' +
        '<td class="num">' + plata(c.total_fiado) + '</td>' +
        '<td class="num">' + plata(c.total_pagado) + '</td>' +
        '<td class="num"' + enRojo + '>' + plata(debe) + '</td>' +
        '<td>' + (c.deuda_mas_antigua ? fecha(c.deuda_mas_antigua) : '-') + '</td>' +
        '<td><button class="btn btn-gris btn-chico" onclick="verCliente(' + c.cliente_id + ')">Ver</button></td>' +
       '</tr>';
    }
    html += '</tbody></table>';

    html += '<div class="total-caja" style="margin-top:16px"><div class="linea">' +
            '<span><strong>Deuda total en la calle</strong></span>' +
            '<span class="grande" style="font-size:24px">' + plata(deudaTotal) + '</span></div></div>';

    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

async function verCliente(clienteId) {
  const caja = document.getElementById('detalle');
  const tit = document.getElementById('detalle-titulo');
  const cuerpo = document.getElementById('detalle-cuerpo');

  caja.style.display = 'block';
  tit.textContent = 'Cargando...';
  cuerpo.innerHTML = '<div class="cargando">Cargando...</div>';
  caja.scrollIntoView({ behavior: 'smooth' });

  try {
    const c = await apiGet('/libreta/' + clienteId);
    tit.textContent = '📒 ' + c.cliente + ' — debe ' + plata(c.saldo_deudor);

    let html = '';

    // --- Fiados ---
    html += '<h3>Fiados</h3>';
    if (!c.fiados.length) {
      html += '<div class="vacio">Sin fiados registrados</div>';
    } else {
      html += '<table><thead><tr><th>#</th><th>Fecha</th><th>Qué se llevó</th>' +
              '<th class="num">Total</th><th class="num">Saldo</th><th>Estado</th><th></th></tr></thead><tbody>';
      for (const f of c.fiados) {
        const boton = (f.estado !== 'pagado')
          ? '<button class="btn btn-verde btn-chico" onclick="pagar(' + f.fiado_id + ', \''
            + esc(c.cliente) + '\', ' + (Number(f.saldo)) + ')">Cobrar</button>'
          : '';
        html += '<tr>' +
          '<td>' + f.fiado_id + '</td>' +
          '<td>' + fechaCorta(f.fecha) + '</td>' +
          '<td>' + esc(f.detalle || '-') + '</td>' +
          '<td class="num">' + plata(f.monto_total) + '</td>' +
          '<td class="num">' + plata(f.saldo) + '</td>' +
          '<td><span class="etiqueta ' + f.estado + '">' + f.estado + '</span></td>' +
          '<td>' + boton + '</td></tr>';
      }
      html += '</tbody></table>';
    }

    // --- Pagos ---
    html += '<h3 style="margin-top:22px">Pagos recibidos</h3>';
    if (!c.pagos.length) {
      html += '<div class="vacio">Sin pagos registrados</div>';
    } else {
      html += '<table><thead><tr><th>Fecha</th><th class="num">Monto</th><th>Medio</th><th>Nota</th></tr></thead><tbody>';
      for (const p of c.pagos) {
        html += '<tr><td>' + fechaCorta(p.fecha) + '</td>' +
                '<td class="num">' + plata(p.monto) + '</td>' +
                '<td>' + esc(p.medio) + '</td>' +
                '<td>' + esc(p.observaciones || '-') + '</td></tr>';
      }
      html += '</tbody></table>';
    }

    cuerpo.innerHTML = html;
  } catch (e) {
    cuerpo.innerHTML = '<div class="vacio">Error: ' + esc(e.message) + '</div>';
  }
}

/**
 * Registra un pago. Si deja el monto vacio, se asume que paga todo el saldo.
 */
async function pagar(fiadoId, cliente, saldo) {
  const entrada = prompt(
    cliente + ' debe ' + plata(saldo) + '.\n¿Cuánto deja pagando? (Enter = paga todo)',
    saldo
  );
  if (entrada === null) return;

  const monto = parseFloat(String(entrada).replace(',', '.')) || saldo;
  if (monto <= 0) { avisar('El monto debe ser mayor a 0', 'error'); return; }
  if (monto > saldo) { avisar('El pago no puede superar la deuda (' + plata(saldo) + ')', 'error'); return; }

  const medio = confirm('¿El pago fue en efectivo?\n\nAceptar = efectivo, Cancelar = transferencia')
    ? 'efectivo' : 'transferencia';

  try {
    const r = await apiPost('/fiado/pagar', {
      fiado_id: fiadoId,
      monto: monto,
      medio_pago: medio
    });
    avisar('Pago registrado. Saldo restante: ' + plata(r.saldo_restante) +
           (r.estado === 'pagado' ? ' — deuda saldada ✅' : ''), 'ok');
    await dibujarLibreta();
    // Recargamos el detalle del mismo cliente si su id esta en el DOM.
    const m = document.getElementById('detalle-titulo').textContent;
    if (m) {
      // No tenemos el id guardado; volvemos a pedir la lista y buscamos por nombre.
      const cs = await apiGet('/libreta');
      const enc = cs.find(x => m.includes(x.cliente));
      if (enc) verCliente(enc.cliente_id);
    }
  } catch (e) {
    avisar(e.message, 'error');
  }
}

iniciarLibreta();