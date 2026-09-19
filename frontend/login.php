<?php
/**
 * Pantalla de login.
 *
 * Es la unica pagina que NO incluye el layout normal (no tiene barra de
 * navegacion: todavia no hay nada que navegar). Al entrar bien, el JS redirige
 * a index.php (la pantalla de venta).
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>Ingresar - Avicola Los Changuitos</title>
 <link rel="stylesheet" href="assets/css/estilos.css">
 <script src="assets/js/api.js"></script>
</head>
<body class="pantalla-login">
 <div class="caja-login">
    <div class="logo-login">🐔</div>
    <h1>Avicola Los Changuitos</h1>
    <p class="subtitulo">Ingresa para operar el sistema</p>

    <form id="form-login" autocomplete="on">
      <div class="campo">
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" autocomplete="username"
               placeholder="ej: admin" required autofocus>
      </div>
      <div class="campo">
        <label for="password">Contrasena</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" placeholder="••" required>
      </div>
      <button type="submit" class="btn btn-primario btn-grande" id="btn-entrar" style="width:100%">
        Ingresar
      </button>
      <div id="error-login" class="error-login"></div>
    </form>
 </div>
 <script src="assets/js/login.js"></script>
</body>
</html>