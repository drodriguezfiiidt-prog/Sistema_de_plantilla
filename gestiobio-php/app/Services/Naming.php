<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Redaccion corporativa: glosario obligatorio y control del tratamiento (usted).
 */
final class Naming
{
    /**
     * Terminos de tuteo que deben corregirse en el informe tecnico.
     *
     * @var list<string>
     */
    public const INFORMAL_TERMS = [
        'tú', 'tu', 'tus', 'tuyo', 'tuya', 'tienes', 'puedes', 'debes',
        'necesitas', 'quieres', 'sabes', 'haz', 'envíame', 'dime', 'mira', 'recuerda',
    ];

    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = str_replace("\t", ' ', $text);

        $normalized = (string) preg_replace('/\b(carcasas?|carcazas?)\b/iu', 'Case', $normalized);
        $normalized = (string) preg_replace('/\brif\b/iu', 'RIF', $normalized);
        $normalized = (string) preg_replace('/\busb\b/iu', 'USB', $normalized);
        $normalized = (string) preg_replace_callback(
            '/\b(articulos?|artículos?)\b/iu',
            static fn (array $match): string => self::replaceArticulo($match[0]),
            $normalized
        );
        $normalized = (string) preg_replace_callback(
            '/\b(remplazos?|reemplasos?|remplasos?)\b/iu',
            static fn (array $match): string => self::replaceReemplazo($match[0]),
            $normalized
        );
        $normalized = (string) preg_replace_callback(
            '/\b(biometrico|biometricos|biometria)\b/iu',
            static fn (array $match): string => self::replaceBiometrico($match[0]),
            $normalized
        );

        $normalized = (string) preg_replace('/ {2,}/u', ' ', $normalized);
        $lines = preg_split('/\n/', $normalized) ?: [];
        $normalized = implode("\n", array_map(static fn (string $line): string => rtrim($line), $lines));
        $normalized = (string) preg_replace('/\n{3,}/', "\n\n", $normalized);

        return trim($normalized);
    }

    /**
     * @return list<string>
     */
    public static function findInformalTerms(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $lowered = mb_strtolower($text, 'UTF-8');
        $found = [];
        foreach (self::INFORMAL_TERMS as $term) {
            $pattern = '/(?<![a-záéíóúñ])' . preg_quote($term, '/') . '(?![a-záéíóúñ])/u';
            if (preg_match($pattern, $lowered) === 1) {
                $found[] = $term;
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    public static function findGlossaryIssues(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $issues = [];
        if (preg_match('/\bcarcasas?\b/iu', $text) === 1) {
            $issues[] = 'carcasa';
        }
        if (preg_match('/\brif\b/', $text) === 1 && !str_contains($text, 'RIF')) {
            $issues[] = 'rif';
        }
        if (preg_match('/\busb\b/', $text) === 1 && !str_contains($text, 'USB')) {
            $issues[] = 'usb';
        }
        if (preg_match('/\barticulos?\b/iu', $text) === 1) {
            $issues[] = 'articulo';
        }
        if (preg_match('/\b(remplazo|reemplaso)\b/iu', $text) === 1) {
            $issues[] = 'reemplazo';
        }

        return $issues;
    }

    private static function replaceArticulo(string $word): string
    {
        $plural = str_ends_with(mb_strtolower($word), 's');
        $upper = self::startsUpper($word);
        if ($plural) {
            return $upper ? 'Artículos' : 'artículos';
        }

        return $upper ? 'Artículo' : 'artículo';
    }

    private static function replaceReemplazo(string $word): string
    {
        $plural = str_ends_with(mb_strtolower($word), 's');
        $upper = self::startsUpper($word);
        if ($plural) {
            return $upper ? 'Reemplazos' : 'reemplazos';
        }

        return $upper ? 'Reemplazo' : 'reemplazo';
    }

    private static function replaceBiometrico(string $word): string
    {
        $map = [
            'biometrico' => 'biométrico',
            'biometricos' => 'biométricos',
            'biometria' => 'biometría',
        ];
        $replacement = $map[mb_strtolower($word)] ?? $word;
        if ($replacement === $word) {
            return $word;
        }

        return self::startsUpper($word)
            ? mb_strtoupper(mb_substr($replacement, 0, 1)) . mb_substr($replacement, 1)
            : $replacement;
    }

    private static function startsUpper(string $word): bool
    {
        return preg_match('/^\p{Lu}/u', $word) === 1;
    }
}
