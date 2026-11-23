<?php

declare(strict_types=1);

/**
 * Inicializa la base SQLite y carga el catálogo y usuarios iniciales.
 *
 * Uso: php bin/init-db.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use GestioBio\Database;
use GestioBio\Models\DiagnosticTicket;
use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;
use GestioBio\Models\User;

Database::connect();

$lines = [
    'Base de datos: ' . (string) cfg('database.path'),
    'Familias: ' . count(EquipmentFamily::all()),
    'Artículos: ' . count(ItemCatalog::all()),
    'Usuarios: ' . count(User::all()),
    'Diagnósticos: ' . DiagnosticTicket::count(),
    '',
    'Usuarios iniciales:',
];

foreach (User::all() as $user) {
    $lines[] = '  - ' . $user->username . ($user->isStaff ? ' (staff)' : ' (consulta)');
}

echo implode(PHP_EOL, $lines) . PHP_EOL;
