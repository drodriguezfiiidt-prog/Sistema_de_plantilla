<?php

declare(strict_types=1);

namespace GestioBio\Support;

/**
 * Acceso a la peticion HTTP (query string, cuerpo POST y cabeceras).
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    /**
     * Parametro de query string.
     */
    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Campo enviado por POST.
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Campo de POST o, en su defecto, de la query string.
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function postData(): array
    {
        return $_POST;
    }

    /**
     * @return array<string, mixed>
     */
    public static function queryData(): array
    {
        return $_GET;
    }

    public static function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }
}
