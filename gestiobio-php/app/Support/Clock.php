<?php

declare(strict_types=1);

namespace GestioBio\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Marcas de tiempo: se almacenan en UTC y se muestran en la zona horaria local.
 */
final class Clock
{
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function local(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

            return $date
                ->setTimezone(new DateTimeZone((string) cfg('app.timezone', 'America/Caracas')))
                ->format('d/m/Y H:i');
        } catch (\Exception) {
            return $utc;
        }
    }
}
