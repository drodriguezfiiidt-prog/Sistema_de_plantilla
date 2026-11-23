<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Models\DiagnosticTicket;
use GestioBio\Models\EquipmentFamily;
use GestioBio\Services\DocumentFactory;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Historial de evaluaciones guardadas y detalle de cada ticket.
 */
final class HistoryController
{
    public static function index(): void
    {
        $filters = [
            'q' => trim((string) Request::query('q', '')),
            'garantia' => trim((string) Request::query('garantia', '')),
            'familia' => trim((string) Request::query('familia', '')),
        ];

        $search = DiagnosticTicket::search($filters);

        View::render('history', [
            'page_title' => 'Historial · ' . cfg('app.brand_name'),
            'tickets' => $search['tickets'],
            'total_count' => $search['total'],
            'query' => $filters['q'],
            'warranty' => $filters['garantia'],
            'family_id' => $filters['familia'],
            'families' => EquipmentFamily::all(),
            'warranty_choices' => DiagnosticTicket::warrantyChoices(),
        ]);
    }

    public static function show(array $params): void
    {
        $ticket = DiagnosticTicket::find((int) ($params['id'] ?? 0));
        if ($ticket === null) {
            View::error(404, 'Ticket no encontrado', 'El registro solicitado no existe en el historial.');

            return;
        }

        View::render('ticket_detail', [
            'page_title' => 'Ticket #' . $ticket->ticketNumber . ' · ' . cfg('app.brand_name'),
            'ticket' => $ticket,
            'document' => DocumentFactory::forTicket($ticket),
        ]);
    }
}
