<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Formato numerico venezolano: miles con punto y decimales con coma.
 */
final class Formatting
{
    /**
     * Convierte un monto (int de centavos, float o texto "1.234,56") en centavos
     * exactos, redondeando a la mitad hacia arriba como Decimal de Python.
     */
    public static function toCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value * 100, 0, PHP_ROUND_HALF_UP);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 0;
        }

        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '+-');

        // Formato venezolano: 1.234,56 (punto de miles, coma decimal).
        if (str_contains($text, ',')) {
            $normalized = str_replace(['.', ' '], '', $text);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace([' ', ','], '', $text);
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            return 0;
        }

        $cents = (int) round(((float) $normalized) * 100, 0, PHP_ROUND_HALF_UP);

        return $negative ? -$cents : $cents;
    }

    /**
     * Monto con separadores: 1.234,56.
     */
    public static function money(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $units = intdiv($cents, 100);
        $decimals = $cents % 100;

        return ($negative ? '-' : '')
            . number_format($units, 0, ',', '.')
            . ',' . str_pad((string) $decimals, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Precio tal como aparece en la tabla: 80 para 80,00; 12,50 para 12,50.
     */
    public static function priceCell(int $cents): string
    {
        if ($cents % 100 === 0) {
            return (string) intdiv($cents, 100);
        }

        return self::money($cents);
    }

    /**
     * Monto con moneda: 10,00 USD.
     */
    public static function moneyUsd(int $cents): string
    {
        return self::money($cents) . ' ' . (string) cfg('app.currency', 'USD');
    }

    /**
     * Representacion decimal con punto (para mensajes internos y JSON).
     */
    public static function decimal(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '')
            . intdiv($cents, 100)
            . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
