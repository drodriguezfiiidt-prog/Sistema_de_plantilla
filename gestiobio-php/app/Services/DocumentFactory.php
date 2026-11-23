<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\DiagnosticTicket;

/**
 * Reconstruye el documento institucional de un ticket ya guardado.
 */
final class DocumentFactory
{
    public static function forTicket(DiagnosticTicket $ticket): DiagnosticDocument
    {
        $quote = Pricing::buildQuote($ticket->family, $ticket->items);

        return EmailBuilder::build(
            family: $ticket->family,
            ticketNumber: $ticket->ticketNumber,
            serialNumber: $ticket->serialNumber,
            warrantyStatus: $ticket->warrantyStatus,
            items: $ticket->items,
            quote: $quote,
            technicalReport: $ticket->technicalReport,
        );
    }
}
