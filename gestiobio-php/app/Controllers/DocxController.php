<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Models\DiagnosticTicket;
use GestioBio\Services\DiagnosticForm;
use GestioBio\Services\DocxExport;
use GestioBio\Services\DocumentFactory;
use GestioBio\Services\Pipeline;
use GestioBio\Support\Csrf;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Exportación a Word (.docx) del diagnóstico generado o de un ticket guardado.
 */
final class DocxController
{
    public static function download(): void
    {
        if (!Request::isPost()) {
            View::error(405, 'Método no permitido', 'La descarga del Word requiere POST.');
        }

        Csrf::validate();
        $form = new DiagnosticForm(Request::postData());
        if (!$form->isValid()) {
            View::json(['ok' => false, 'errors' => $form->errors], 400);
        }

        $result = Pipeline::runGeneration($form->cleaned);
        self::send(DocxExport::build($result->document), $result->document->filename());
    }

    public static function ticket(array $params): void
    {
        $ticket = DiagnosticTicket::find((int) ($params['id'] ?? 0));
        if ($ticket === null) {
            View::error(404, 'Ticket no encontrado', 'El registro solicitado no existe en el historial.');

            return;
        }

        $document = DocumentFactory::forTicket($ticket);
        self::send(DocxExport::build($document), $document->filename());
    }

    private static function send(string $bytes, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }
}
