<?php

declare(strict_types=1);

namespace GestioBio;

/**
 * Acceso a la configuracion con notacion de puntos (app.brand_name).
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /**
     * @param array<string, mixed> $values
     */
    public static function boot(array $values): void
    {
        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }
}
