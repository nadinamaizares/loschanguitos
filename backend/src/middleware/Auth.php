<?php
/**
 * Sesion y control de acceso.
 *
 * El sistema es para el mostrador de una polleria, pero ahora se accede por
 * internet (un socio en Jujuy, otro en Buenos Aires), asi que hace falta saber
 * QUIEN esta operando: no puede quedar abierto a cualquiera que tenga la URL.
 *
 * Usamos las sesiones nativas de PHP ($_SESSION), que ya manejan una cookie de
 * sesion sola. No hace falta tabla de sesiones ni tokens: con la cookie HttpOnly
 * alcanza para este caso.
 *
 * La sesion guarda unico array 'usuario' con lo minimo para trabajar:
 *   ['id' => 1, 'nombre' => '...', 'usuario' => 'admin', 'rol' => 'admin']
 */
namespace Polleria;
class Auth
{
    /** Arranca la sesion una sola vez (las llamadas siguientes no hacen nada). */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // La cookie de sesion no es accesible desde JavaScript (HttpOnly),
            // y no se manda como Referer a otros sitios (SameSite=Strict).
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Strict',
                'path'     => '/',
            ]);
            session_start();
        }
    }

    /** Guarda al usuario logueado en la sesion. */
    public static function login(array $usuario): void
    {
        self::iniciar();
        // Regeneramos el ID de sesion al loguear (evita "session fixation").
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'      => (int)$usuario['c001_id'],
            'nombre'  => $usuario['c001_nombre'],
            'usuario' => $usuario['c001_usuario'],
            'rol'     => $usuario['c001_rol'],
        ];
    }

    /** Borra la sesion por completo. */
    public static function logout(): void
    {
        self::iniciar();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Devuelve el usuario de la sesion, o null si no hay nadie logueado. */
    public static function usuario(): ?array
    {
        self::iniciar();
        return $_SESSION['usuario'] ?? null;
    }

    /** Atajo: el id del usuario actual (lo usan las ventas y la libreta). */
    public static function id(): ?int
    {
        $u = self::usuario();
        return $u ? (int)$u['id'] : null;
    }

    /**
     * Corta la ejecucion si no hay sesion.
     *
     * Las rutas de la API la llaman al principio. Responde 401 en JSON (no
     * redirige: quien consume la API es el fetch del frontend, que maneja el
     * error de forma generica).
     */
    public static function exigir(): array
    {
        $u = self::usuario();
        if ($u === null) {
            Response::error('No hay sesion activa. Inicia sesion.', 401);
        }
        return $u;
    }

    /** Atajo: el rol del usuario actual ('admin' o 'vendedor'), o null. */
    public static function rol(): ?string
    {
        $u = self::usuario();
        return $u ? $u['rol'] : null;
    }

    /** True si el usuario actual es admin. */
    public static function esAdmin(): bool
    {
        return self::rol() === 'admin';
    }

    /**
     * Exige que el usuario sea admin. Se usa en las acciones que un vendedor
     * NO puede hacer (dar de alta productos, clientes, usuarios).
     *
     * Da 403 (prohibido), distinto del 401 (no logueado): el usuario existe,
     * pero su rol no alcanza.
     */
    public static function exigirAdmin(): array
    {
        $u = self::exigir();
        if ($u['rol'] !== 'admin') {
            Response::error('Solo un administrador puede hacer esta operacion.', 403);
        }
        return $u;
    }
}