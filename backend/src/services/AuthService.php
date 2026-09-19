<?php
/**
 * Logica de negocio: autenticacion.
 *
 * Valida usuario y contrasena contra la tabla 001_usuarios. La contrasena se
 * guarda con password_hash() (bcrypt) y se verifica con password_verify():
 * nunca se guarda ni se compara texto plano.
 *
 * Importante: el mensaje de error es SIEMPRE el mismo ("Usuario o contrasena
 * incorrectos"), tanto si el usuario no existe como si la clave esta mal. Asi
 * no le damos una pista a quien intente adivinar quien existe.
 */
namespace Polleria;
class AuthService
{
    /**
     * Intenta loguear. Devuelve los datos publicos del usuario si esta bien.
     * Corta con error 401 si las credenciales no sirven.
     */
    public static function login($data)
    {
        $usuario  = trim((string)Input::requerido($data, 'usuario'));
        $password = (string)Input::requerido($data, 'password');

        $u = Db::selectOne(
            "SELECT c001_id, c001_nombre, c001_usuario, c001_password_hash,
                    c001_rol, c001_activo
               FROM 001_usuarios
              WHERE c001_usuario = ?",
            [$usuario]
        );

        // Mismo error para "no existe" y "clave mal", a proposito.
        $error = 'Usuario o contrasena incorrectos';

        if ($u === null) {
            Response::error($error, 401);
        }
        if ((int)$u['c001_activo'] !== 1) {
            Response::error('El usuario esta desactivado', 403);
        }
        if (!password_verify($password, $u['c001_password_hash'])) {
            Response::error($error, 401);
        }

        // Si el hash quedo viejo (bcrypt con otro costo), lo actualizamos aca.
        if (password_needs_rehash($u['c001_password_hash'], PASSWORD_DEFAULT)) {
            Db::execute(
                "UPDATE 001_usuarios SET c001_password_hash = ? WHERE c001_id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $u['c001_id']]
            );
        }

        // Dejamos registrado el ultimo acceso (util para saber quien entro).
        Db::execute(
            "UPDATE 001_usuarios SET c001_ultimo_acceso = NOW() WHERE c001_id = ?",
            [$u['c001_id']]
        );

        Auth::login($u);

        return self::publico($u);
    }

    /** Cierra la sesion. */
    public static function logout(): array
    {
        Auth::logout();
        return ['mensaje' => 'Sesion cerrada'];
    }

    /**
     * Crea un usuario nuevo (empleado). Pensado para darlo de alta desde la app
     * mas adelante; por ahora se usa tambien para regenerar el admin del seed.
     */
    public static function crear($data)
    {
        $nombre   = trim((string)Input::requerido($data, 'nombre'));
        $usuario  = trim((string)Input::requerido($data, 'usuario'));
        $password = (string)Input::requerido($data, 'password');
        $rol      = Input::opcional($data, 'rol', 'vendedor');

        if (!in_array($rol, ['admin', 'vendedor'], true)) {
            Response::error("El rol debe ser 'admin' o 'vendedor'", 422);
        }
        if (strlen($password) < 6) {
            Response::error('La contrasena debe tener al menos 6 caracteres', 422);
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
            Response::error('El usuario solo admite letras, numeros, punto, guion y guion bajo (3 a 50)', 422);
        }

        $existe = Db::selectOne("SELECT c001_id FROM 001_usuarios WHERE c001_usuario = ?", [$usuario]);
        if ($existe !== null) {
            Response::error('Ya existe un usuario con ese nombre de ingreso', 409);
        }

        Db::execute(
            "INSERT INTO 001_usuarios (c001_nombre, c001_usuario, c001_password_hash, c001_rol)
             VALUES (?, ?, ?)",
            [$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol]
        );

        return ['usuario_id' => (int)Db::conn()->lastInsertId()];
    }

    /** Lista de usuarios (sin el hash, obviamente). Para la pantalla de admin. */
    public static function usuarios()
    {
        return Db::select(
            "SELECT c001_id            AS usuario_id,
                    c001_nombre         AS nombre,
                    c001_usuario        AS usuario,
                    c001_rol            AS rol,
                    c001_activo         AS activo,
                    c001_ultimo_acceso  AS ultimo_acceso,
                    c001_fcreacion      AS creado
               FROM 001_usuarios
           ORDER BY c001_id"
        );
    }

    /** Solo los campos que se pueden mostrar (nunca el hash). */
    private static function publico(array $u): array
    {
        return [
            'id'      => (int)$u['c001_id'],
            'nombre'  => $u['c001_nombre'],
            'usuario' => $u['c001_usuario'],
            'rol'     => $u['c001_rol'],
        ];
    }
}