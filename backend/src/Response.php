<?php
/**
 * Helpers para responder en JSON y cortar la ejecucion.
 *
 * Todas las respuestas de la API tienen esta forma:
 *   { "ok": true,  "data": ... }
 *   { "ok": false, "error": "mensaje" }
 */

namespace Polleria;

class Response
{
    /** Respuesta exitosa. */
    public static function ok($data = null, $httpCode = 200)
    {
        self::send(['ok' => true, 'data' => $data], $httpCode);
    }

    /** Respuesta de error. */
    public static function error($mensaje, $httpCode = 400)
    {
        self::send(['ok' => false, 'error' => $mensaje], $httpCode);
    }

    private static function send($payload, $httpCode)
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
            // Permite que el frontend (mismo host, otro puerto) pueda consumirlo.
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}