<?php
/**
 * Configuracion general del backend de la polleria.
 *
 * Todo lo que cambia entre una maquina y otra (credenciales, puertos) vive
 * aca. El resto del codigo nunca debe tener datos de conexion hardcodeados.
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'nombre'   => 'polleria',
        'usuario'  => 'root',
        'password' => '',          // XAMPP por defecto no tiene password
        'charset'  => 'utf8mb4',
    ],

    // La app no tiene login propio todavia: mientras tanto usamos este usuario
    // como "el que esta operando". Es el admin que crea 02_seed_y_vistas.sql.
    'usuario_default_id' => 1,

    // Mostrar errores detallados en pantalla (util en desarrollo).
    // Poner en false cuando esto se use de verdad en el mostrador.
    'debug' => true,
];