<?php

declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP:
 *   php -S 127.0.0.1:8001 -t public router.php
 *
 * Devuelve los archivos estaticos tal cual y enruta el resto a public/index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

require __DIR__ . '/public/index.php';
