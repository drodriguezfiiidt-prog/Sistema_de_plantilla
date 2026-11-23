<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;

/**
 * Reglas comerciales y tarifarias (misma logica que generador.txt / Django).
 *
 * La tabla inicia siempre con la partida base obligatoria y luego agrega cada
 * falla seleccionada con su precio referencial en USD.
 */
final class Pricing
{
    /**
     * @var array<string, string>
     */
    public const WARRANTY_CLAUSES = [
        'Vencida' => 'Se le recuerda que su equipo ya no cumple con los lapsos de tiempo establecidos para el cumplimiento de garantía.',
        'Invalidada' => 'Se le informa que su equipo no cumple con las cláusulas establecidas para la ejecución de la garantía.',
        'Vigente' => 'Su equipo se encuentra dentro del periodo de garantía vigente.',
    ];

    public static function warrantyClause(?string $status): string
    {
        return self::WARRANTY_CLAUSES[trim((string) $status)] ?? '';
    }

    public static function administrativeChannel(?EquipmentFamily $family = null): string
    {
        $channel = $family !== null ? trim($family->supportChannel ?? '') : '';
        if ($channel !== '') {
            return $channel;
        }

        return trim((string) cfg('app.administrative_channel', ''));
    }

    /**
     * Construye la tabla tarifaria de la evaluacion.
     *
     * Reglas:
     *   - La partida base obligatoria se inserta automaticamente.
     *   - Solo se incluyen articulos activos y compatibles con la familia.
     *   - Las familias con allows_quotes = false no reciben tabla y se remiten
     *     al canal administrativo.
     *
     * @param list<ItemCatalog|null> $items
     */
    public static function buildQuote(?EquipmentFamily $family, array $items = []): QuoteResult
    {
        $result = new QuoteResult();

        if ($family === null) {
            $result->errors[] = 'Debe indicar la familia del equipo para calcular los costos referenciales.';

            return $result;
        }

        $result->quoteAllowed = $family->allowsQuotes;
        $result->channel = self::administrativeChannel($family);

        $selected = array_values(array_filter(
            $items,
            static fn (?ItemCatalog $item): bool => $item instanceof ItemCatalog
        ));

        if (!$result->quoteAllowed) {
            $result->warnings[] = sprintf(
                'La familia %s no permite tabla de cotizacion en este documento.',
                $family->name
            );
            if ($result->channel !== '') {
                $result->notes[] = 'La cotizacion formal se gestiona a traves del canal ' . $result->channel . '.';
            }

            return $result;
        }

        $seen = [];
        foreach (ItemCatalog::baseLinesFor($family) as $base) {
            if (isset($seen[$base->id])) {
                continue;
            }
            $seen[$base->id] = true;
            $result->lines[] = new QuoteLine(
                model: $family->shortName(),
                article: $base->name,
                priceCents: $base->priceCents,
                isBase: true,
                itemId: $base->id,
            );
        }

        foreach ($selected as $item) {
            if (isset($seen[$item->id])) {
                continue;
            }
            if (!$item->isActive) {
                $result->errors[] = sprintf('El articulo %s esta deshabilitado en el catalogo.', $item->name);
                continue;
            }
            if (!$item->isAvailableFor($family)) {
                $result->errors[] = sprintf(
                    'El articulo %s no esta habilitado para la familia %s.',
                    $item->name,
                    $family->name
                );
                continue;
            }
            $seen[$item->id] = true;
            $result->lines[] = new QuoteLine(
                model: $family->shortName(),
                article: $item->displayName($family),
                priceCents: $item->priceCents,
                isBase: false,
                itemId: $item->id,
            );
        }

        $result->totalCents = array_sum(array_map(
            static fn (QuoteLine $line): int => $line->priceCents,
            $result->lines
        ));

        if ($selected === []) {
            $result->warnings[] = 'No se seleccionaron fallas ni repuestos para cotizar.';
        }
        if ($result->baseLines() === []) {
            $result->warnings[] = 'La familia no tiene partida base activa asignada: la tabla se emite solo con los repuestos seleccionados.';
        }

        return $result;
    }
}
