<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Services\DiagnosticForm;
use GestioBio\Services\Pipeline;
use GestioBio\Support\Auth;
use GestioBio\Support\Csrf;
use GestioBio\Support\Flash;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Formulario de captura y generación del diagnóstico.
 */
final class GeneratorController
{
    public static function index(): void
    {
        $form = new DiagnosticForm();
        $result = null;
        $ticket = null;

        if (Request::isPost()) {
            Csrf::validate();
            $form = new DiagnosticForm(Request::postData());

            if ($form->isValid()) {
                $result = Pipeline::runGeneration($form->cleaned);
                $ticket = Pipeline::registerTicket($form->cleaned, $result, Auth::user());
                Flash::success(
                    'Diagnóstico del ticket #' . $ticket->ticketNumber
                    . ' generado y guardado en el historial.'
                );
            } else {
                Flash::error(
                    'Revise los datos: hay campos obligatorios pendientes o '
                    . 'inconsistentes con la familia del equipo.'
                );
            }
        }

        View::render('generator', [
            'page_title' => 'Generador · ' . cfg('app.brand_name'),
            'form' => $form,
            'result' => $result,
            'ticket' => $ticket,
            'family' => $form->familyObj,
        ]);
    }
}
