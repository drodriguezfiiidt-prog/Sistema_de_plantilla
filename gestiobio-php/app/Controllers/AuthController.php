<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Models\User;
use GestioBio\Support\Auth;
use GestioBio\Support\Csrf;
use GestioBio\Support\Flash;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Inicio y cierre de sesión del panel de gestión.
 */
final class AuthController
{
    public static function login(): void
    {
        $next = self::safeNext((string) Request::input('next', (string) cfg('auth.redirect_after_login', '/gestion/')));
        $error = null;
        $username = '';

        if (Request::isPost()) {
            Csrf::validate();
            $username = trim((string) Request::post('username', ''));
            $password = (string) Request::post('password', '');

            $user = User::attempt($username, $password);
            if ($user === null) {
                $error = 'Usuario o contraseña incorrectos.';
            } else {
                Auth::login($user);
                Flash::success('Bienvenido/a, ' . $user->displayName() . '.');
                Request::redirect($next);
            }
        }

        View::render('login', [
            'page_title' => 'Iniciar sesión · ' . cfg('app.brand_name'),
            'error' => $error,
            'username' => $username,
            'next' => $next,
        ]);
    }

    public static function logout(): void
    {
        if (Request::isPost()) {
            Csrf::validate();
        }

        Auth::logout();
        Flash::info('La sesión se cerró correctamente.');
        Request::redirect((string) cfg('auth.redirect_after_logout', '/'));
    }

    private static function safeNext(string $next): string
    {
        $next = trim($next);
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return (string) cfg('auth.redirect_after_login', '/gestion/');
        }

        return $next;
    }
}
