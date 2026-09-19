/* =============================================================================
   login.js - Pantalla de ingreso.
   Manda usuario y contrasena a /api/login. Si esta bien, redirige a la venta.
   ============================================================================= */
const form  = document.getElementById('form-login');
const error = document.getElementById('error-login');
const btn   = document.getElementById('btn-entrar');

/** Muestra un mensaje de error debajo del formulario. */
function mostrarError(msg) {
  error.textContent = msg;
  error.classList.add('visible');
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  error.classList.remove('visible');
  btn.disabled = true;
  btn.textContent = 'Ingresando...';

  const usuario  = document.getElementById('usuario').value.trim();
  const password = document.getElementById('password').value;

  try {
    await apiPost('/login', { usuario, password });
    // Login OK: vamos a la pantalla principal.
    window.location.href = 'index.php';
  } catch (err) {
    mostrarError(err.message || 'No se pudo ingresar');
    btn.disabled = false;
    btn.textContent = 'Ingresar';
    document.getElementById('password').value = '';
    document.getElementById('password').focus();
  }
});

/* Si ya habia una sesion activa, no tiene sentido mostrar el login. */
(async () => {
  try {
    const u = await apiGet('/sesion');
    if (u) {
      window.location.href = 'index.php';
    }
  } catch (e) {
    /* sin sesion: nos quedamos en el login */
  }
})();