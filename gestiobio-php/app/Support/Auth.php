<?php

declare(strict_types=1);

namespace GestioBio\Support;

use GestioBio\Models\User;

/**
 * Autenticacion por sesion con proteccion de areas exclusivas de staff.
 */
final class Auth
{
    public static function user(): ?User
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }

        $user = User::find((int) $id);
        if ($user === null) {
            unset($_SESSION['user_id']);

            return null;
        }

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $user = self::user();

        return $user !== null && $user->isStaff;
    }

    public static function login(User $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user->id;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Exige usuario staff; en caso contrario redirige al login con ``next``.
     */
    public static function requireStaff(): User
    {
        $user = self::user();
        if ($user !== null && $user->isStaff) {
            return $user;
        }

        Flash::error('Inicie sesión con un usuario autorizado para acceder a la gestión del catálogo.');
        $next = Request::path();
        $login = (string) cfg('auth.login_path', '/login/');

        Request::redirect($login . '?next=' . urlencode($next));
    }
}
