<?php
/**
 * Conexion a la base de datos (PDO).
 *
 * Singleton simple: la primera vez que alguien pide la conexion se crea, y
 * despues se reutiliza. Con ERRMODE_EXCEPTION cualquier error de SQL se
 * convierte en excepcion, asi no hay que chequear resultados a mano.
 */

namespace Polleria;

use PDO;
use PDOException;

class Db
{
    private static $pdo = null;

    public static function conn()
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Igual que bootstrap: preferimos config.local.php si existe.
        $configFile = is_file(__DIR__ . '/../config.local.php')
            ? __DIR__ . '/../config.local.php'
            : __DIR__ . '/../config.php';
        $cfg = require $configFile;
        $c   = $cfg['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['nombre'],
            $c['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $c['usuario'], $c['password'], [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Que los numeros vuelvan como numeros y no como texto.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Fijamos la zona horaria de la CONEXION a Argentina (-03:00).
            // Asi NOW() y CURRENT_TIMESTAMP (las fechas de ventas, fiados,
            // movimientos y pagos) guardan SIEMPRE hora argentina, sin
            // importar en que zona este el servidor (XAMPP local o un
            // hosting como InfinityFree que usa UTC).
            //
            // Va en try/catch: algunos hostings compartidos no dejan cambiar
            // la zona horaria y tirarian error. Si eso pasa, seguimos igual
            // (queda la hora del servidor, pero el sistema no se rompe).
            try {
                self::$pdo->exec("SET time_zone = '-03:00'");
            } catch (PDOException $e) {
                // sin permiso para cambiar la zona: seguimos
            }
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'No se pudo conectar a la base de datos "polleria". ' .
                'Verifica que MySQL este encendido en XAMPP y que hayas corrido ' .
                'los scripts de /database. Detalle: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /** SELECT que devuelve muchas filas. */
    public static function select($sql, $params = [])
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** SELECT que devuelve una sola fila (o null). */
    public static function selectOne($sql, $params = [])
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** INSERT / UPDATE / DELETE: devuelve filas afectadas. */
    public static function execute($sql, $params = [])
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Id de la ultima fila insertada (reemplaza LAST_INSERT_ID() de los SP). */
    public static function lastInsertId()
    {
        return (int)self::conn()->lastInsertId();
    }

    /**
     * Envuelve una funcion en una transaccion.
     *
     * Antes esto lo hacian los procedimientos almacenados (START TRANSACTION
     * ... COMMIT/ROLLBACK). Pero los hostings gratis como InfinityFree NO
     * dejan crear procedimientos, asi que esa logica la hacemos desde PHP.
     * Si la funcion tira una excepcion, se hace ROLLBACK y no queda nada a
     * medias (por ejemplo, una venta sin descontar el stock).
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::conn();
        $pdo->beginTransaction();
        try {
            $resultado = $fn();
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}