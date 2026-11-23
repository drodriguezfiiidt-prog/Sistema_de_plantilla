<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Fila de la tabla tarifaria: Modelo | Articulo | Costo referencial ($).
 */
final class QuoteLine
{
    public function __construct(
        public readonly string $model,
        public readonly string $article,
        public readonly int $priceCents,
        public readonly bool $isBase = false,
        public readonly ?int $itemId = null,
    ) {
    }
}
