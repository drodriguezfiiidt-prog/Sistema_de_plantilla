<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;

/**
 * Orquestacion de la evaluacion: validaciones, tarifario y redaccion.
 */
final class Pipeline
{
    /**
     * @param array<string, mixed> $cleaned
     */
    public static function runGeneration(array $cleaned): GenerationResult
    {
        $family = $cleaned['family'] ?? null;
        $family = $family instanceof EquipmentFamily ? $family : null;
        $items = array_values(array_filter(
            (array) ($cleaned['items'] ?? []),
            static fn (?ItemCatalog $item): bool => $item instanceof ItemCatalog
        ));
        $ticket = trim((string) ($cleaned['ticket_number'] ?? ''));
        $serial = strtoupper(trim((string) ($cleaned['serial_number'] ?? '')));
        $warranty = trim((string) ($cleaned['warranty_status'] ?? ''));
        $reportText = (string) ($cleaned['technical_report'] ?? '');

        $quote = Pricing::buildQuote($family, $items);

        $report = new ComplianceReport(
            errors: $quote->errors,
            warnings: $quote->warnings,
            infos: $quote->notes,
        );

        if ($ticket === '') {
            $report->errors[] = 'Debe indicar el número de ticket.';
        }
        if ($serial === '') {
            $report->errors[] = 'Debe indicar el serial del equipo.';
        }
        if ($family === null) {
            $report->errors[] = 'No se pudo determinar la familia del equipo: verifique el prefijo del serial.';
        } elseif ($serial !== '' && !$family->matchesSerial($serial)) {
            $report->warnings[] = sprintf(
                'El serial %s no coincide con el prefijo %s registrado para la familia %s.',
                $serial,
                $family->prefix,
                $family->name
            );
        }
        if ($items === []) {
            $report->errors[] = 'Seleccione al menos una falla o repuesto.';
        }

        if (trim($reportText) === '') {
            $report->infos[] = 'No se registró informe técnico: el documento indica «Sin observaciones adicionales registradas».';
        } else {
            $informal = Naming::findInformalTerms($reportText);
            if ($informal !== []) {
                $report->warnings[] = 'Se detectó tratamiento informal (tuteo) en el informe técnico: '
                    . implode(', ', $informal)
                    . '. El texto se publica con tratamiento de usted; corrija la redacción.';
            }

            $glossaryIssues = Naming::findGlossaryIssues($reportText);
            if ($glossaryIssues !== []) {
                $report->infos[] = 'Se aplicó el glosario institucional sobre: '
                    . implode(', ', $glossaryIssues) . '.';
            }
        }

        if ($quote->channel !== '') {
            $report->infos[] = 'Canal institucional: ' . $quote->channel . '.';
        }
        if ($quote->hasLines()) {
            $report->infos[] = sprintf(
                'Tabla tarifaria con %d partida(s) para un total referencial de %s USD.',
                count($quote->lines),
                Formatting::decimal($quote->totalCents)
            );
        }

        $document = EmailBuilder::build(
            family: $family,
            ticketNumber: $ticket,
            serialNumber: $serial,
            warrantyStatus: $warranty,
            items: $items,
            quote: $quote,
            technicalReport: Naming::normalize($reportText),
        );

        return new GenerationResult(document: $document, quote: $quote, report: $report);
    }

    /**
     * Persiste la evaluacion en el historial.
     *
     * @param array<string, mixed> $cleaned
     */
    public static function registerTicket(array $cleaned, GenerationResult $result, ?\GestioBio\Models\User $user = null): \GestioBio\Models\DiagnosticTicket
    {
        $family = $cleaned['family'] ?? null;
        $family = $family instanceof EquipmentFamily ? $family : null;
        $items = array_values(array_filter(
            (array) ($cleaned['items'] ?? []),
            static fn (?ItemCatalog $item): bool => $item instanceof ItemCatalog
        ));
        $warranty = trim((string) ($cleaned['warranty_status'] ?? ''));

        return \GestioBio\Models\DiagnosticTicket::create(
            ticketNumber: trim((string) ($cleaned['ticket_number'] ?? '')),
            serialNumber: strtoupper(trim((string) ($cleaned['serial_number'] ?? ''))),
            family: $family,
            warrantyStatus: $warranty !== '' ? $warranty : 'Vencida',
            technicalReport: (string) ($cleaned['technical_report'] ?? ''),
            totalCents: $result->quote->totalCents,
            documentBody: $result->document->plainText(),
            createdBy: $user?->id,
            items: $items,
        );
    }

    /**
     * Instantanea serializable del diagnostico (auditoria / JSON).
     *
     * @param array<string, mixed> $cleaned
     *
     * @return array<string, mixed>
     */
    public static function buildSnapshot(array $cleaned, QuoteResult $quote, ComplianceReport $report): array
    {
        $family = $cleaned['family'] ?? null;
        $family = $family instanceof EquipmentFamily ? $family : null;
        $items = array_values(array_filter(
            (array) ($cleaned['items'] ?? []),
            static fn (?ItemCatalog $item): bool => $item instanceof ItemCatalog
        ));

        return [
            'ticket_number' => trim((string) ($cleaned['ticket_number'] ?? '')),
            'serial_number' => strtoupper(trim((string) ($cleaned['serial_number'] ?? ''))),
            'family_id' => $family?->id,
            'family' => $family?->name ?? '',
            'warranty_status' => (string) ($cleaned['warranty_status'] ?? ''),
            'technical_report' => (string) ($cleaned['technical_report'] ?? ''),
            'items' => array_map(
                static fn (ItemCatalog $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => Formatting::decimal($item->priceCents),
                ],
                $items
            ),
            'quote_allowed' => $quote->quoteAllowed,
            'channel' => $quote->channel,
            'total' => Formatting::decimal($quote->totalCents),
            'lines' => array_map(
                static fn (QuoteLine $line): array => [
                    'model' => $line->model,
                    'article' => $line->article,
                    'price' => Formatting::decimal($line->priceCents),
                    'is_base' => $line->isBase,
                ],
                $quote->lines
            ),
            'warnings' => $report->warnings,
            'infos' => $report->infos,
        ];
    }
}

