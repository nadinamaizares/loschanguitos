<?php
/**
 * Router minimalista.
 *
 * No usamos un framework: con dos metodos alcanza.
 *   $r->get('/api/stock',  fn() => ...);
 *   $r->post('/api/ventas', fn() => ...);
 *
 * Soporta parametros en la ruta, ej: '/api/ventas/{id}/detalle'.
 */

namespace Polleria;

class Router
{
    private array $rutas = [];

    public function get(string $ruta, callable $accion): void
    {
        $this->agregar('GET', $ruta, $accion);
    }

    public function post(string $ruta, callable $accion): void
    {
        $this->agregar('POST', $ruta, $accion);
    }

    public function put(string $ruta, callable $accion): void
    {
        $this->agregar('PUT', $ruta, $accion);
    }

    public function delete(string $ruta, callable $accion): void
    {
        $this->agregar('DELETE', $ruta, $accion);
    }

    private function agregar(string $metodo, string $ruta, callable $accion): void
    {
        $this->rutas[] = [
            'metodo' => $metodo,
            'ruta'   => $ruta,
            'accion' => $accion,
        ];
    }

    /**
     * Busca la ruta que corresponde y la ejecuta.
     */
    public function despachar(string $metodo, string $uri): void
    {
        // Nos quedamos solo con el path (sin query string) y sin barra final.
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // En XAMPP la API puede colgar de /polleria/backend/public, asi que
        // dejamos solo lo que empieza en /api.
        $posApi = strpos($path, '/api');
        if ($posApi !== false) {
            $path = substr($path, $posApi);
        }

        foreach ($this->rutas as $r) {
            if ($r['metodo'] !== $metodo) {
                continue;
            }

            $params = $this->coincide($r['ruta'], $path);
            if ($params === null) {
                continue;
            }

            call_user_func_array($r['accion'], $params);
            return;
        }

        Response::error("Ruta no encontrada: {$metodo} {$path}", 404);
    }

    /**
     * Compara una ruta definida con la pedida.
     * Devuelve los parametros capturados, o null si no coincide.
     */
    private function coincide(string $definida, string $pedida): ?array
    {
        $patron = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $definida);
        $patron = '#^' . $patron . '$#';

        if (preg_match($patron, $pedida, $m) !== 1) {
            return null;
        }

        array_shift($m);   // sacamos el match completo
        return $m;
    }
}