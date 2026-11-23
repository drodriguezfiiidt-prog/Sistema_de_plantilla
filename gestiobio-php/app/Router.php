<?php

declare(strict_types=1);

namespace GestioBio;

use GestioBio\Support\View;

/**
 * Router minimo con patrones tipo Django (``/historial/{id}/``).
 */
final class Router
{
    /**
     * @var list<array{method: string, pattern: string, handler: callable}>
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalize($path);
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed[$route['method']] = true;
                continue;
            }

            ($route['handler'])($params);

            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_keys($allowed)));
            View::error(405, 'Método no permitido', 'La ruta ' . $path . ' no admite el método ' . $method . '.');

            return;
        }

        View::error(404, 'Página no encontrada', 'La ruta ' . $path . ' no existe en GestioBioV3.');
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $match): string => '(?P<' . $match[1] . '>[0-9]+)',
            $pattern
        );
        $regex = '#^' . $regex . '$#u';

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        return array_filter(
            $matches,
            static fn (int|string $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function normalize(string $path): string
    {
        $path = rawurldecode(parse_url($path, PHP_URL_PATH) ?: '/');

        return $path === '' ? '/' : $path;
    }
}
