<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;
use GestioBio\Services\Formatting;
use GestioBio\Services\Pricing;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Autodetección de familia y fallas compatibles (JSON para el JavaScript).
 */
final class ApiController
{
    public static function context(): void
    {
        $serial = trim((string) Request::query('serial', ''));
        $familyId = trim((string) Request::query('family', ''));

        $family = null;
        if ($familyId !== '' && ctype_digit($familyId)) {
            $candidate = EquipmentFamily::find((int) $familyId);
            if ($candidate !== null && $candidate->isActive) {
                $family = $candidate;
            }
        }
        $family ??= EquipmentFamily::detect($serial);

        $items = [];
        if ($family !== null) {
            $items = array_map(
                static fn (ItemCatalog $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => Formatting::money($item->priceCents),
                    'url' => url('/gestion/articulos/' . $item->id),
                ],
                ItemCatalog::forFamily($family)
            );
        }

        View::json([
            'family' => $family !== null ? [
                'id' => $family->id,
                'name' => $family->name,
                'prefix' => $family->prefix,
                'allows_quotes' => $family->allowsQuotes,
                'channel' => Pricing::administrativeChannel($family),
            ] : null,
            'items' => $items,
        ]);
    }
}
