<?php

declare(strict_types=1);

namespace GestioBio\Support;

/**
 * Mensajes flash de una sola lectura (equivalente a django.contrib.messages).
 */
final class Flash
{
    public static function add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public static function consume(): array
    {
        $messages = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);

        return is_array($messages) ? array_values($messages) : [];
    }
}
