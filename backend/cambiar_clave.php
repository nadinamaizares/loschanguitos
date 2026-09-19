<?php
/**
 * Utilidad de linea de comandos: cambiar la contrasena de un usuario.
 *
 * La columna c001_password_hash NO guarda la contrasena en texto: guarda un
 * hash bcrypt generado con password_hash(). Este script hace eso por vos, asi
 * nunca queda una clave en texto plano (que haria fallar el login).
 *
 * Uso:
 *   C:\xampp\php\php.exe backend\cambiar_clave.php admin MiClaveNueva
 *
 * Si no pasas la clave como segundo argumento, te la pide de forma oculta.
 */
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

use Polleria\Db;

$usuario = $argv[1] ?? null;
if ($usuario === null || $usuario === '') {
    fwrite(STDERR, "Falta el nombre de usuario.`n");
    fwrite(STDERR, "Uso: php backend/cambiar_clave.php <usuario> [clave-nueva]`n");
    exit(1);
}

$clave = $argv[2] ?? null;
if ($clave === null) {
    fwrite(STDOUT, "Contrasena nueva para '{$usuario}': ");
    // Lectura oculta: en Windows y Linux el flag del sistema la oculta.
    if (PHP_OS_FAMILY === 'Windows') {
        system('powershell -Command "$ = Read-Host -AsSecureString; $p"');
        $clave = trim((string)fgets(STDIN));
    } else {
        system('stty -echo');
        $clave = trim((string)fgets(STDIN));
        system('stty echo');
        fwrite(STDOUT, "`n");
    }
}

if (strlen($clave) < 6) {
    fwrite(STDERR, "La contrasena debe tener al menos 6 caracteres.`n");
    exit(1);
}

$existe = Db::selectOne("SELECT c001_id, c001_nombre FROM 001_usuarios WHERE c001_usuario = ?", [$usuario]);
if ($existe === null) {
    fwrite(STDERR, "No existe el usuario '{$usuario}'.`n");
    exit(1);
}

Db::execute(
    "UPDATE 001_usuarios SET c001_password_hash = ? WHERE c001_usuario = ?",
    [password_hash($clave, PASSWORD_DEFAULT), $usuario]
);

fwrite(STDOUT, "Listo: la contrasena de '{$usuario}' ({$existe['c001_nombre']}) fue actualizada.`n");