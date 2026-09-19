<?php
/**
 * Lectura y validacion de lo que manda el frontend.
 */

namespace Polleria;

class Input
{
    /**
     * Cuerpo de la peticion como array.
     *
     * Acepta dos formatos para que sea comodo de probar desde el navegador
     * o desde la consola:
     *   - JSON:   {"tipo":"contado", "items":[...]}
     *   - Form:   tipo=contado&items=...
     */
    public static function json()
    {
        $raw = file_get_contents('php://input');

        if ($raw === '' || $raw === false) {
            return $_POST ?: [];
        }

        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }

        // No era JSON: probamos como formulario clasico.
        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    /** Devuelve el valor o un error si falta. */
    public static function requerido($data, $campo)
    {
        if (!isset($data[$campo]) || $data[$campo] === '' || $data[$campo] === null) {
            Response::error("Falta el campo obligatorio: {$campo}", 422);
        }
        return $data[$campo];
    }

    /** Devuelve el valor o un default si no vino. */
    public static function opcional($data, $campo, $default = null)
    {
        return (isset($data[$campo]) && $data[$campo] !== '') ? $data[$campo] : $default;
    }

    /** Numero entero, o error si no es valido. */
    public static function entero($valor, $campo)
    {
        if (!is_numeric($valor) || (int)$valor != $valor) {
            Response::error("El campo {$campo} debe ser un numero entero", 422);
        }
        return (int)$valor;
    }

    /** Numero decimal (acepta coma o punto), o error si no es valido. */
    public static function decimal($valor, $campo)
    {
        $normalizado = str_replace(',', '.', (string)$valor);
        if (!is_numeric($normalizado)) {
            Response::error("El campo {$campo} debe ser un numero", 422);
        }
        return (float)$normalizado;
    }
}