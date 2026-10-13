<?php
/**
 * Modelo de configuracion del backend de la polleria.
 *
 * Copiar este archivo como:
 *   - config.php        -> el que usa el sistema publicado (InfinityFree)
 *   - config.local.php  -> para probar en tu PC con XAMPP (si existe, tiene prioridad)
 *
 * Ninguno de esos dos se sube a GitHub (ver .gitignore): tienen contrasenas.
 */
return [
    'db' => [
        'host'     => '127.0.0.1',     // InfinityFree: sqlXXX.infinityfree.com
        'port'     => 3306,
        'nombre'   => 'polleria',
        'usuario'  => 'root',
        'password' => '',              // XAMPP por defecto no tiene password
        'charset'  => 'utf8mb4',
    ],
    // La app ya tiene login propio; este valor quedo como respaldo.
    'usuario_default_id' => 1,
    // Mostrar errores detallados. En produccion conviene false.
    'debug' => true,
];
