<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Services\DiagnosticForm;
use GestioBio\Services\Pipeline;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Vista previa en tiempo real (consumida por JavaScript).
 */
final class PreviewController
{
    public static function index(): void
    {
        $form = new DiagnosticForm(Request::queryData());
        $result = $form->isValid() ? Pipeline::runGeneration($form->cleaned) : null;

        View::partial('partials/preview', [
            'result' => $result,
            'form' => $form,
        ]);
    }
}
