<?php
/**
 * Punto de entrada de la API.
 *
 * Toda peticion HTTP entra por aca. Se puede usar de dos maneras:
 *
 *   A) Con el Apache de XAMPP (recomendado, ya lo tenes corriendo):
 *      http://localhost/polleria/backend/public/index.php/api/...
 *      o con el .htaccess de esta carpeta, directamente:
 *      http://localhost/polleria/backend/public/api/...
 *
 *   B) Con el servidor embebido de PHP (no necesita Apache):
 *      php -S localhost:8080 -t backend/public
 *      http://localhost:8080/api/...
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

// Preflight de CORS (el navegador lo manda antes de un POST cross-origin).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** @var Polleria\Router $router */
$router = require dirname(__DIR__) . '/src/routes/api.php';

$router->despachar(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);