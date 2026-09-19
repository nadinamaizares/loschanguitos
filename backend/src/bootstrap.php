<?php
/**
 * Arranque comun de la API.
 *
 * Registra un autoloader simple (sin Composer) para las clases del namespace
 * Polleria, y deja configurado el manejo de errores.
 *
 * Regla general de la API: SIEMPRE responde JSON. Nunca HTML de error de PHP,
 * porque el frontend no sabria que hacer con eso.
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);

// --- Autoloader: Polleria\Xxx  ->  src/Xxx.php  o  src/services/Xxx.php ---
spl_autoload_register(function ($clase) use ($raiz) {
    $prefijo = 'Polleria\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }

    $relativo = substr($clase, strlen($prefijo));          // ej: "services\VentaService"
    $relativo = str_replace('\\', '/', $relativo);

    foreach (["$raiz/src/$relativo.php", "$raiz/src/services/$relativo.php"] as $archivo) {
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});

$config = require "$raiz/config.php";

// --- Errores ---
if ($config['debug']) {
    ini_set('display_errors', '0');   // no queremos HTML metido en el JSON
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// --- Zona horaria (para DATE() y las fechas de las ventas) ---
date_default_timezone_set('America/Argentina/Buenos_Aires');

/**
 * Convierte cualquier error/excepcion no atrapada en una respuesta JSON.
 */
set_exception_handler(function ($e) use ($config) {
    $detalle = $config['debug'] ? $e->getMessage() : 'Error interno del servidor';

    // Los SIGNAL de los procedimientos llegan como PDOException.
    // Su mensaje es util y lo queremos mostrar siempre (ej: "Stock insuficiente...").
    $esDeNegocio = ($e instanceof PDOException);

    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => $esDeNegocio ? $e->getMessage() : $detalle,
    ], JSON_UNESCAPED_UNICODE);
    exit;
});