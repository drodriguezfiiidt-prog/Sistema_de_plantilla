<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Resultado del motor tarifario.
 */
final class QuoteResult
{
    /** @var list<QuoteLine> */
    public array $lines = [];

    public int $totalCents = 0;

    public bool $quoteAllowed = false;

    public string $channel = '';

    /** @var list<string> */
    public array $notes = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $errors = [];

    public function hasLines(): bool
    {
        return $this->lines !== [];
    }

    /**
     * @return list<QuoteLine>
     */
    public function baseLines(): array
    {
        return array_values(array_filter($this->lines, static fn (QuoteLine $line): bool => $line->isBase));
    }

    /**
     * @return list<QuoteLine>
     */
    public function specificLines(): array
    {
        return array_values(array_filter($this->lines, static fn (QuoteLine $line): bool => !$line->isBase));
    }
}
