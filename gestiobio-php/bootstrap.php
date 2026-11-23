<?php

declare(strict_types=1);

use GestioBio\Config;

/**
 * Arranque comun de la aplicacion: autoload, configuracion, zona horaria y
 * sesion. Se usa tanto desde el front controller web como desde los scripts CLI.
 */

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'GestioBio\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Config::boot(require BASE_PATH . '/config.php');

error_reporting(E_ALL);
ini_set('display_errors', Config::bool('app.debug', false) ? '1' : '0');

date_default_timezone_set(Config::string('app.timezone', 'America/Caracas'));

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name(Config::string('session.name', 'gestiobio_session'));
    session_set_cookie_params([
        'lifetime' => (int) Config::get('session.lifetime', 7200),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Valor de configuracion con notacion de puntos.
 */
function cfg(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/**
 * Ruta absoluta dentro del proyecto.
 */
function base_path(string $path = ''): string
{
    return BASE_PATH . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

/**
 * Escapa texto para HTML (equivalente al autoescape de Django).
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL raiz-relativa de una ruta interna.
 */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');

    return $path === '/' ? '/' : rtrim($path, '/') . '/';
}

/**
 * URL de un recurso estatico servido desde public/.
 */
function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/**
 * Exige un usuario staff y devuelve su registro.
 */
function require_staff_guard(): \GestioBio\Models\User
{
    return \GestioBio\Support\Auth::requireStaff();
}

/**
 * Renderiza un parcial y devuelve su HTML (para includes dentro de plantillas).
 *
 * @param array<string, mixed> $data
 */
function render_partial(string $template, array $data = []): string
{
    return \GestioBio\Support\View::capture($template, $data);
}


