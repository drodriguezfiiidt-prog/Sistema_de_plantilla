<?php

declare(strict_types=1);

use GestioBio\Database;
use GestioBio\Support\View;

require dirname(__DIR__) . '/bootstrap.php';

$debug = (bool) cfg('app.debug', false);

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log('[GestioBio] ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());

    $message = $debug
        ? $exception->getMessage() . ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')'
        : 'Ocurrió un error inesperado. Intente nuevamente en unos minutos.';

    try {
        View::error(500, 'Error del servidor', $message);
    } catch (Throwable) {
        http_response_code(500);
        echo 'Error del servidor';
    }
});

$router = require base_path('routes.php');

Database::connect();

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);
