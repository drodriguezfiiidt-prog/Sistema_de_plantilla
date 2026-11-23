<?php

declare(strict_types=1);

namespace GestioBio\Support;

/**
 * Proteccion CSRF con token de sesion (equivalente al CsrfViewMiddleware).
 */
final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (!isset($_SESSION['_token']) || !is_string($_SESSION['_token']) || $_SESSION['_token'] === '') {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function validate(): void
    {
        $sent = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = self::token();

        if ($expected === '' || !hash_equals($expected, $sent)) {
            View::error(
                419,
                'Sesión expirada',
                'El formulario perdió la protección CSRF. Recargue la página e intente nuevamente.'
            );
        }
    }
}
