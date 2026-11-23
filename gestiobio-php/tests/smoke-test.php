<?php

declare(strict_types=1);

/**
 * Pruebas de humo del port PHP de GestioBioV3 (sin PHPUnit).
 *
 * Uso: php tests/smoke-test.php
 * Codigo de salida 0 = todas las comprobaciones pasaron.
 */

require dirname(__DIR__) . '/bootstrap.php';

use GestioBio\Database;
use GestioBio\Models\DiagnosticTicket;
use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;
use GestioBio\Services\DiagnosticForm;
use GestioBio\Services\DocxExport;
use GestioBio\Services\DocumentFactory;
use GestioBio\Services\EmailBuilder;
use GestioBio\Services\Formatting;
use GestioBio\Services\Naming;
use GestioBio\Services\Pipeline;
use GestioBio\Services\Pricing;

$failures = 0;
$checks = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo '  OK   ' . $label . PHP_EOL;

        return;
    }

    $failures++;
    echo '  FAIL ' . $label . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
}

function checkSame(string $label, mixed $expected, mixed $actual): void
{
    check(
        $label,
        $expected === $actual,
        'esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true)
    );
}

function hasTable(array $blocks): bool
{
    foreach ($blocks as $block) {
        if ($block->kind === 'table') {
            return true;
        }
    }

    return false;
}

$database = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gb_smoke_' . bin2hex(random_bytes(6)) . '.sqlite';
Database::connect($database);

echo 'Base temporal: ' . $database . PHP_EOL . PHP_EOL;

echo '1. Formato numerico' . PHP_EOL;
checkSame('toCents(80)', 8000, Formatting::toCents('80'));
checkSame('toCents(12,50)', 1250, Formatting::toCents('12,50'));
checkSame('toCents(1.234,56)', 123456, Formatting::toCents('1.234,56'));
checkSame('toCents(0.1+0.2)', 30, Formatting::toCents(0.1 + 0.2));
checkSame('money(123456)', '1.234,56', Formatting::money(123456));
checkSame('money(8000)', '80,00', Formatting::money(8000));
checkSame('priceCell(8000)', '80', Formatting::priceCell(8000));
checkSame('priceCell(1250)', '12,50', Formatting::priceCell(1250));
checkSame('decimal(21000)', '210.00', Formatting::decimal(21000));
checkSame('moneyUsd(1000)', '10,00 USD', Formatting::moneyUsd(1000));

echo PHP_EOL . '2. Autodeteccion de familia' . PHP_EOL;
$bio = EquipmentFamily::detect('v10-000123');
check('detect(v10-000123) = Bio500/V10', $bio !== null && $bio->name === 'Bio500/V10');
check('shortName = Bio500', $bio !== null && $bio->shortName() === 'Bio500');
$futronic = EquipmentFamily::detect('FP-99001');
check('detect(FP-99001) = Futronic', $futronic !== null && $futronic->name === 'Futronic');
$szzt = EquipmentFamily::detect('G62-ABS');
check('detect(G62-ABS) = SZZT', $szzt !== null && $szzt->name === 'SZZT');
check('detect(ZZZ-1) = null', EquipmentFamily::detect('ZZZ-1') === null);
check('detect("") = null', EquipmentFamily::detect('') === null);

$prefixFamily = EquipmentFamily::create([
    'name' => 'Prefijo corto',
    'prefix' => 'V1',
    'allows_quotes' => true,
    'support_channel' => '',
    'is_active' => true,
]);
$longest = EquipmentFamily::detect('V10-000123');
check(
    'Prefijo mas largo gana (V10 sobre V1)',
    $longest !== null && $longest->name === 'Bio500/V10'
);
$shortest = EquipmentFamily::detect('V1-777');
check('Familia con prefijo V1 detectada', $shortest !== null && $shortest->name === 'Prefijo corto');

echo PHP_EOL . '3. Tarifario' . PHP_EOL;
$byName = [];
foreach (ItemCatalog::all() as $item) {
    $byName[$item->name] = $item;
}
$reader = $byName['Lector biométrico'];
$pin = $byName['Pin'];
$baseItem = $byName['Reemplazo de partes y piezas de dispositivos biométricos'];

$selectable = ItemCatalog::forFamily($bio);
checkSame('Articulos seleccionables para Bio500', 5, count($selectable));
check(
    'Partida base fuera del formulario',
    !in_array($baseItem->id, array_map(static fn (ItemCatalog $item): int => $item->id, $selectable), true)
);

$quote = Pricing::buildQuote($bio, [$reader, $pin]);
checkSame('Lineas de cotizacion (base + 2)', 3, count($quote->lines));
checkSame('Total referencial en centavos', 11000, $quote->totalCents);
check('Primera linea es la partida base', $quote->lines[0]->isBase);
checkSame('Nombre con modelo agregado', 'Lector biométrico Bio500', $quote->lines[1]->article);
check('Familia permite cotizacion', $quote->quoteAllowed);

$noQuoteFamily = EquipmentFamily::create([
    'name' => 'Sin cotizacion',
    'prefix' => 'NQ',
    'allows_quotes' => false,
    'support_channel' => 'admin@test',
    'is_active' => true,
]);
$noQuote = Pricing::buildQuote($noQuoteFamily, [$reader]);
checkSame('Familia sin cotizacion no genera lineas', 0, count($noQuote->lines));
check(
    'Advertencia de tabla deshabilitada',
    str_contains($noQuote->warnings[0] ?? '', 'no permite tabla de cotizacion')
);
checkSame('Canal administrativo de la familia', 'admin@test', $noQuote->channel);

$noFamily = Pricing::buildQuote(null, []);
check('Sin familia reporta error', str_contains($noFamily->errors[0] ?? '', 'Debe indicar la familia'));

echo PHP_EOL . '4. Redaccion y glosario institucional' . PHP_EOL;
checkSame(
    'Glosario aplicado',
    'reemplazo de Case biométrico USB artículos',
    Naming::normalize('remplazo de carcasas biometrico  usb articulos')
);
checkSame('Tuteo detectado', ['puedes', 'tú'], Naming::findInformalTerms('Tú puedes revisar el equipo'));
checkSame(
    'Terminos del glosario detectados',
    ['carcasa', 'rif', 'usb', 'articulo', 'reemplazo'],
    Naming::findGlossaryIssues('carcasa rif usb articulo remplazo')
);
checkSame('Tratamiento formal sin hallazgos', [], Naming::findInformalTerms('Usted puede revisar el equipo'));

echo PHP_EOL . '5. Generacion del documento' . PHP_EOL;
$cleaned = [
    'ticket_number' => '35212348',
    'serial_number' => 'v10-000123',
    'family' => $bio,
    'items' => [$reader, $pin],
    'warranty_status' => 'Vencida',
    'technical_report' => 'Se revisó el equipo y se detectó falla en el lector. Puedes retirarlo.',
];
$result = Pipeline::runGeneration($cleaned);
check('Documento con tabla tarifaria', hasTable($result->document->blocks));
checkSame(
    'Asunto institucional',
    'Soporte técnico Biopago C.A. - Ticket #35212348 - Serial V10-000123',
    $result->document->subject
);
check('Clausula de garantia vencida', str_contains($result->document->plainText(), 'lapsos de tiempo'));
check('Informe tecnico incluido', str_contains($result->document->plainText(), 'Se revisó el equipo'));
check('Aviso de tuteo', str_contains(implode(' ', $result->report->warnings), 'tuteo'));
checkSame('Nombre del archivo', 'Diagnostico_35212348.docx', $result->document->filename());
checkSame('Total del resultado', 11000, $result->totalCents());
check('Texto plano con tabla', str_contains($result->document->plainText(), 'Costo referencial'));

echo PHP_EOL . '6. Exportacion .docx' . PHP_EOL;
$bytes = DocxExport::build($result->document);
check('Firma ZIP del paquete', str_starts_with($bytes, 'PK'));

$docxPath = tempnam(sys_get_temp_dir(), 'gb_docx') . '.docx';
file_put_contents($docxPath, $bytes);
$zip = new ZipArchive();
check('Paquete .docx abierto', $zip->open($docxPath) === true);

foreach (
    [
        '[Content_Types].xml',
        '_rels/.rels',
        'word/document.xml',
        'word/styles.xml',
        'word/numbering.xml',
        'docProps/core.xml',
        'word/_rels/document.xml.rels',
    ] as $part
) {
    check('Parte ' . $part, $zip->locateName($part) !== false);
}

$documentXml = (string) $zip->getFromName('word/document.xml');
check('Tabla Word presente', str_contains($documentXml, '<w:tbl>'));
check('Estilo Calibri', str_contains((string) $zip->getFromName('word/styles.xml'), 'Calibri'));
check('Vineta definida', str_contains((string) $zip->getFromName('word/numbering.xml'), 'bullet'));

$dom = new DOMDocument();
$wellFormed = true;
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = (string) $zip->getNameIndex($index);
    if (!str_ends_with($name, '.xml') && !str_ends_with($name, '.rels')) {
        continue;
    }
    $contents = (string) $zip->getFromName($name);
    $previous = libxml_use_internal_errors(true);
    if (!$dom->loadXML($contents)) {
        $wellFormed = false;
        echo '       XML invalido: ' . $name . PHP_EOL;
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}
check('Todas las partes son XML valido', $wellFormed);
$zip->close();
@unlink($docxPath);

echo PHP_EOL . '7. Persistencia e historial' . PHP_EOL;
$ticket = Pipeline::registerTicket($cleaned, $result);
checkSame('Ticket registrado', 1, DiagnosticTicket::count());

$stored = DiagnosticTicket::find($ticket->id);
check('Ticket recuperado', $stored !== null);
checkSame('Serial normalizado', 'V10-000123', $stored->serialNumber ?? '');
checkSame('Total guardado', 11000, $stored->totalCents ?? 0);
checkSame('Articulos guardados', 2, count($stored->items ?? []));
check('Resumen de fallas', str_contains($stored->itemsSummary(), 'Lector biométrico'));
checkSame('Nombre .docx del ticket', 'Diagnostico_35212348.docx', $stored->documentFilename());

$search = DiagnosticTicket::search(['q' => '3521']);
checkSame('Busqueda por ticket', 1, $search['total']);
checkSame('Filtro por garantia sin resultados', 0, DiagnosticTicket::search(['garantia' => 'Vigente'])['total']);
checkSame('Filtro por familia', 1, DiagnosticTicket::search(['familia' => (string) $bio->id])['total']);

$rebuilt = DocumentFactory::forTicket($stored);
checkSame('Documento reconstruido con el mismo total', 11000, $rebuilt->totalCents());
check('Documento reconstruido con tabla', $rebuilt->hasQuoteTable());

echo PHP_EOL . '8. Validacion del formulario' . PHP_EOL;
$validForm = new DiagnosticForm([
    'ticket_number' => ' 9001 ',
    'serial_number' => 'v10-777',
    'family' => '',
    'items' => [(string) $reader->id, (string) $pin->id],
    'warranty_status' => 'Vigente',
    'technical_report' => '',
]);
check('Formulario valido', $validForm->isValid());
checkSame('Ticket limpio', '9001', $validForm->cleaned['ticket_number'] ?? '');
check('Familia autodetectada', ($validForm->familyObj->name ?? '') === 'Bio500/V10');
checkSame('Garantia aceptada', 'Vigente', $validForm->cleaned['warranty_status'] ?? '');

$badSerial = new DiagnosticForm([
    'ticket_number' => '9002',
    'serial_number' => 'ZZZ-1',
    'items' => [(string) $reader->id],
    'warranty_status' => 'Vencida',
]);
check('Serial desconocido invalida el formulario', !$badSerial->isValid());
check(
    'Mensaje de prefijo no reconocido',
    str_contains(implode(' ', $badSerial->errorsFor('serial_number')), 'No se reconoce el prefijo')
);

$missingItems = new DiagnosticForm([
    'ticket_number' => '9003',
    'serial_number' => 'V10-1',
    'warranty_status' => 'Vencida',
]);
check('Sin fallas invalida el formulario', !$missingItems->isValid());
check(
    'Mensaje de fallas obligatorias',
    str_contains(implode(' ', $missingItems->errorsFor('items')), 'Marque al menos una falla')
);

$wrongFamily = new DiagnosticForm([
    'ticket_number' => '9004',
    'serial_number' => 'FP-55',
    'family' => (string) $bio->id,
    'items' => [(string) $reader->id],
    'warranty_status' => 'Vencida',
]);
check('Serial de otra familia invalida', !$wrongFamily->isValid());
check(
    'Mensaje de prefijo inconsistente',
    str_contains(implode(' ', $wrongFamily->errorsFor('serial_number')), 'El serial no corresponde al prefijo')
);

echo PHP_EOL . '9. Usuarios y acceso' . PHP_EOL;
$admin = \GestioBio\Models\User::attempt('admin', 'GestioBio2026*');
check('Login admin', $admin !== null && $admin->isStaff);
$soporte = \GestioBio\Models\User::attempt('soporte', 'Soporte2026*');
check('Login soporte sin staff', $soporte !== null && !$soporte->isStaff);
check('Clave incorrecta rechazada', \GestioBio\Models\User::attempt('admin', 'otra') === null);

Database::reset();
@unlink($database);
@unlink($database . '-wal');
@unlink($database . '-shm');

echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo sprintf('%d comprobaciones, %d fallo(s).', $checks, $failures) . PHP_EOL;
exit($failures === 0 ? 0 : 1);


