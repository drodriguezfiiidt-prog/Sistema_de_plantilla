<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Agrupa el documento, el tarifario y el informe de cumplimiento.
 */
final class GenerationResult
{
    public function __construct(
        public readonly DiagnosticDocument $document,
        public readonly QuoteResult $quote,
        public readonly ComplianceReport $report,
    ) {
    }

    public function totalCents(): int
    {
        return $this->quote->totalCents;
    }

    public function plainText(): string
    {
        return $this->document->plainText();
    }
}
