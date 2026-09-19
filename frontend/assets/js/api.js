/* =============================================================================
   api.js - Cliente de la API de la polleria.

   Todo el frontend habla por aca. La API responde siempre:
     { ok: true,  data: ... }
     { ok: false, error: "..." }
   y este modulo desarma eso para que las paginas solo vean los datos.
   ============================================================================= */

// URL base de la API. Se calcula sola: si la pagina esta en
//   http://localhost/polleria/frontend/index.php
// la API esta en
//   http://localhost/polleria/backend/public/api
const API_BASE = (() => {
  const path = window.location.pathname;
  const idx  = path.indexOf('/frontend');
  const raiz = idx >= 0 ? path.substring(0, idx) : '';
  return window.location.origin + raiz + '/backend/public/api';
})();

/**
 * Pide a la API y devuelve directamente el campo `data`.
 * Si la API contesta con error, lanza una excepcion con el mensaje.
 */
async function apiGet(ruta) {
  const resp = await fetch(API_BASE + ruta, {
    headers: { 'Accept': 'application/json' }
  });
  const json = await resp.json().catch(() => ({ ok: false, error: 'Respuesta invalida del servidor' }));
  if (!json.ok) {
    throw new Error(json.error || 'Error desconocido');
  }
  return json.data;
}

/** Igual que apiGet pero para POST. */
async function apiPost(ruta, datos) {
  const resp = await fetch(API_BASE + ruta, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'Accept': 'application/json' },
    body: JSON.stringify(datos || {})
  });
  const json = await resp.json().catch(() => ({ ok: false, error: 'Respuesta invalida del servidor' }));
  if (!json.ok) {
    throw new Error(json.error || 'Error desconocido');
  }
  return json.data;
}

/* ---------- Utilidades de formato ---------- */

/** Precio con separador de miles y dos decimales: 12345.5 -> "$ 12.345,50" */
function plata(n) {
  const v = Number(n) || 0;
  return '$ ' + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Kilos con hasta 3 decimales, sin ceros de mas: 2.5 -> "2,5 kg" */
function kg(n) {
  const v = Number(n) || 0;
  return v.toLocaleString('es-AR', { maximumFractionDigits: 3 }) + ' kg';
}

/** Fecha y hora legible: "19/09 10:04" */
function fechaCorta(iso) {
  if (!iso) return '-';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d)) return iso;
  const p = (x) => String(x).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + p(d.getHours()) + ':' + p(d.getMinutes());
}

/** Solo la fecha: "19/09/2026" */
function fecha(iso) {
  if (!iso) return '-';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d)) return iso;
  const p = (x) => String(x).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear();
}

/**
 * Escapa texto para poder meterlo en innerHTML sin que un nombre con < o >
 * rompa la pagina (o peor, meta un script).
 */
function esc(t) {
  return String(t == null ? '' : t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/** Muestra un aviso arriba de la pantalla y lo borra solo a los segundos. */
function avisar(mensaje, tipo) {
  let caja = document.getElementById('aviso');
  if (!caja) return;
  caja.className = 'aviso visible ' + (tipo === 'error' ? 'error' : 'ok');
  caja.textContent = mensaje;
  clearTimeout(caja._t);
  caja._t = setTimeout(() => { caja.className = 'aviso'; }, 5000);
}

/** Fila de tabla para cuando no hay datos. */
function filaVacia(cols, texto) {
  return '<tr><td colspan="' + cols + '" class="vacio">' + esc(texto || 'No hay datos para mostrar') + '</td></tr>';
}
/* ---------- Sesion ---------- */
/**
 * Cierra la sesion en el servidor y manda al login.
 * La usa el boton "Salir" de la barra superior.
 */
async function cerrarSesion() {
  try {
    await apiPost('/logout', {});
  } catch (e) {
    /* aunque falle, igual volvemos al login */
  }
  window.location.href = 'login.php';
}

/**
 * Detecta si una respuesta vino con 401 (sesion vencida) para redirigir al
 * login en vez de dejar la pantalla colgada con un error raro. Se engancha
 * envolviendo el fetch una sola vez, sin tocar las funciones de arriba.
 */
(function vigilarSesion() {
  const fetchOriginal = window.fetch;
  if (!fetchOriginal) return;
  window.fetch = async function (...args) {
    const resp = await fetchOriginal.apply(this, args);
    if (resp.status === 401) {
      // La sesion no sirve: al login. (Evitamos loop si ya estamos en el login.)
      if (!window.location.pathname.endsWith('login.php')) {
        window.location.href = 'login.php';
      }
    }
    return resp;
  };
})();