<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\DiagnosticTicket;

/**
 * Documento listo para previsualizar, copiar o exportar a Word.
 */
final class DiagnosticDocument
{
    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $issue,
        public readonly array $blocks,
        public readonly QuoteResult $quote,
        public readonly string $familyName = '',
        public readonly string $ticketNumber = '',
        public readonly string $serialNumber = '',
        public readonly string $warrantyStatus = '',
    ) {
    }

    /**
     * @return list<QuoteLine>
     */
    public function lines(): array
    {
        return $this->quote->lines;
    }

    public function totalCents(): int
    {
        return $this->quote->totalCents;
    }

    public function hasQuoteTable(): bool
    {
        foreach ($this->blocks as $block) {
            if ($block->kind === Block::TABLE) {
                return true;
            }
        }

        return false;
    }

    public function plainText(): string
    {
        return EmailBuilder::renderPlainText($this->blocks);
    }

    public function filename(): string
    {
        return 'Diagnostico_' . DiagnosticTicket::safeTicket($this->ticketNumber) . '.docx';
    }
}
