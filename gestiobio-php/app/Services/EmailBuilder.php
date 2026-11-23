<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;

/**
 * Redaccion del documento institucional de soporte tecnico.
 *
 * El documento se modela como bloques (parrafos, titulos, vinetas y tabla
 * tarifaria) para renderizarlo igual en HTML, portapapeles y archivo Word.
 */
final class EmailBuilder
{
    /**
     * Tabla en texto plano alineado (para el correo copiado).
     */
    public static function renderPlainTable(Block $block): string
    {
        $rows = $block->rows;
        array_unshift($rows, $block->columns);
        if ($rows === []) {
            return '';
        }

        $width = count($block->columns);
        $widths = [];
        for ($index = 0; $index < $width; $index++) {
            $max = 0;
            foreach ($rows as $row) {
                if (!isset($row[$index])) {
                    continue;
                }
                $max = max($max, mb_strlen((string) $row[$index], 'UTF-8'));
            }
            $widths[$index] = min($max, 60);
        }

        $format = static function (array $row) use ($width, $widths): string {
            $cells = [];
            for ($index = 0; $index < $width; $index++) {
                $value = $row[$index] ?? '';
                $cells[] = mb_substr((string) $value, 0, $widths[$index], 'UTF-8');
            }
            $padded = [];
            foreach ($cells as $index => $cell) {
                $padded[] = $cell . str_repeat(' ', max(0, $widths[$index] - mb_strlen($cell, 'UTF-8')));
            }

            return rtrim(implode(' | ', $padded));
        };

        $separator = implode('-+-', array_map(static fn (int $size): string => str_repeat('-', $size), $widths));
        $body = [$format($block->columns)];
        foreach ($block->rows as $row) {
            $body[] = $format($row);
        }

        return implode("\n", array_merge([$body[0], $separator], array_slice($body, 1)));
    }

    /**
     * @param list<Block> $blocks
     */
    public static function renderPlainText(array $blocks): string
    {
        $chunks = [];
        foreach ($blocks as $block) {
            if ($block->kind === Block::TABLE) {
                $chunks[] = self::renderPlainTable($block);
            } elseif ($block->kind === Block::BULLETS) {
                $chunks[] = implode("\n", array_map(
                    static fn (string $item): string => '• ' . $item,
                    $block->items
                ));
            } else {
                $chunks[] = $block->text;
            }
        }

        $chunks = array_values(array_filter($chunks, static fn (string $chunk): bool => trim($chunk) !== ''));

        return trim(implode("\n\n", $chunks));
    }

    /**
     * Compone el documento institucional migrado de generador.txt.
     *
     * @param list<ItemCatalog> $items
     */
    public static function build(
        ?EquipmentFamily $family,
        string $ticketNumber,
        string $serialNumber,
        string $warrantyStatus,
        array $items = [],
        ?QuoteResult $quote = null,
        string $technicalReport = '',
    ): DiagnosticDocument {
        $quote ??= new QuoteResult();
        $company = (string) cfg('app.company', 'Biopago C.A.');
        $platformLine = (string) cfg('app.platform_line', 'Se envía adjunto código de oficinas.');
        $signature = (string) cfg('app.signature', 'Equipo de Soporte técnico Biopago C.A.');

        $ticket = trim($ticketNumber);
        $serial = strtoupper(trim($serialNumber));
        $model = $family !== null ? $family->shortName() : '';
        $selected = array_values(array_filter(
            $items,
            static fn (?ItemCatalog $item): bool => $item instanceof ItemCatalog
        ));
        $issue = Naming::normalize(implode(', ', array_map(
            static fn (ItemCatalog $item): string => $item->name,
            $selected
        )));
        if ($issue === '') {
            $issue = 'las fallas reportadas';
        }
        $report = Naming::normalize($technicalReport);

        $subject = 'Soporte técnico ' . $company . ' - Ticket #' . $ticket;
        if ($serial !== '') {
            $subject .= ' - Serial ' . $serial;
        }

        $blocks = [
            Block::paragraph('Estimado/a cliente:'),
            Block::paragraph(
                'En respuesta a su solicitud de servicio (ticket #' . $ticket . '), hemos '
                . 'realizado una evaluación exhaustiva de su equipo con serial ' . $serial . '.'
            ),
            Block::paragraph('Hemos determinado que presenta falla con: ' . $issue . '.'),
        ];

        $clause = Pricing::warrantyClause($warrantyStatus);
        if ($clause !== '') {
            $blocks[] = Block::paragraph($clause);
        }

        $blocks[] = Block::heading('Propuesta de solución:');
        $blocks[] = Block::paragraph(
            'Para restablecer el funcionamiento óptimo de su equipo, recomendamos '
            . 'el reemplazo de: ' . $issue . '.',
            true
        );

        if ($quote->quoteAllowed && $quote->hasLines()) {
            $blocks[] = Block::paragraph(
                'Contamos con la pieza original para su modelo ' . $model
                . ' disponible en nuestro inventario. Para ejecutar este '
                . 'procedimiento, a continuación, dejamos un precio aproximado de '
                . 'esta reparación:'
            );
            $blocks[] = Block::table(
                ['Modelo', 'Artículo', 'Costo referencial ($)'],
                array_map(
                    static fn (QuoteLine $line): array => [
                        $line->model,
                        $line->article,
                        Formatting::priceCell($line->priceCents),
                    ],
                    $quote->lines
                )
            );
        } elseif ($quote->quoteAllowed) {
            $blocks[] = Block::paragraph(
                'No se registraron repuestos ni servicios para incluir en la '
                . 'tabla tarifaria referencial.'
            );
        } else {
            $channel = $quote->channel !== '' ? $quote->channel : (string) cfg('app.administrative_channel', '');
            $message = 'Para esta familia de equipos (' . $model . ') la cotización formal se '
                . 'gestiona directamente con el departamento de administración';
            $message .= $channel !== '' ? ' a través del canal ' . $channel . '.' : '.';
            $blocks[] = Block::paragraph($message);
        }

        $blocks = array_merge($blocks, [
            Block::paragraph('Con base en la información hasta este punto suministrada:'),
            Block::paragraph(
                'Nuestro departamento de administración se pondrá en contacto con '
                . 'usted a la brevedad, para enviarle una cotización formal '
                . 'detallada, la cual incluirá:'
            ),
            Block::bullets([
                'Costo del repuesto original: ' . $issue . ' compatible con su modelo ' . $model . '.',
                'Gastos adicionales (si aplican): Cualquier otro costo asociado a la reparación, '
                . 'como componentes adicionales o servicios especializados.',
            ]),
            Block::paragraph(
                'Si decide no continuar con la reparación, le agradeceríamos que '
                . 'nos lo informara por este medio para proporcionarle el '
                . 'procedimiento de retiro de su equipo.'
            ),
            Block::paragraph('Le recordamos que para la facturación es indispensable que nos facilite su RIF.'),
            Block::paragraph($platformLine),
            Block::heading('Diagnóstico:'),
            Block::paragraph('Falla diagnosticada: ' . $issue),
            Block::heading('Informe técnico:'),
            Block::paragraph($report !== '' ? $report : 'Sin observaciones adicionales registradas.'),
            Block::paragraph('Agradecemos su comprensión.'),
            Block::paragraph("Reciba nuestros saludos.\n" . $signature),
        ]);

        return new DiagnosticDocument(
            subject: mb_substr(trim($subject), 0, 255, 'UTF-8'),
            issue: $issue,
            blocks: $blocks,
            quote: $quote,
            familyName: $family !== null ? $family->name : '',
            ticketNumber: $ticket,
            serialNumber: $serial,
            warrantyStatus: $warrantyStatus,
        );
    }
}

