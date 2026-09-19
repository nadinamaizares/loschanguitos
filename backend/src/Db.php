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

        $cfg = require __DIR__ . '/../config.php';
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

    /**
     * Llama a un procedimiento almacenado que termina con un SELECT.
     *
     * Estos SP (sp_registrar_venta, sp_cargar_stock, ...) devuelven su
     * resultado con un SELECT final. Hay que cerrar el cursor antes de poder
     * hacer otra consulta en la misma conexion: de ahi el closeCursor().
     */
    public static function call($proc, $params = [])
    {
        $marcas = implode(',', array_fill(0, count($params), '?'));
        $stmt   = self::conn()->prepare("CALL {$proc}({$marcas})");
        $stmt->execute($params);

        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row === false ? null : $row;
    }
}