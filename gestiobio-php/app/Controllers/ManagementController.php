<?php

declare(strict_types=1);

namespace GestioBio\Controllers;

use GestioBio\Models\DiagnosticTicket;
use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;
use GestioBio\Services\Formatting;
use GestioBio\Support\Flash;
use GestioBio\Support\Request;
use GestioBio\Support\View;

/**
 * Panel de gestión de repuestos, precios, familias y compatibilidades.
 */
final class ManagementController
{
    public static function index(): void
    {
        require_staff_guard();

        $items = ItemCatalog::all();
        $families = EquipmentFamily::all();

        View::render('management/dashboard', [
            'page_title' => 'Gestión · ' . cfg('app.brand_name'),
            'items' => $items,
            'families' => $families,
            'stats' => [
                'items' => count($items),
                'active_items' => count(array_filter($items, static fn (ItemCatalog $item): bool => $item->isActive)),
                'families' => count($families),
                'tickets' => DiagnosticTicket::count(),
            ],
        ]);
    }

    public static function itemCreate(): void
    {
        require_staff_guard();

        $errors = [];
        $values = [
            'name' => '',
            'price' => '',
            'is_mandatory_base' => false,
            'is_active' => true,
            'family_ids' => [],
        ];

        if (Request::isPost()) {
            [$values, $errors] = self::validateItem(Request::postData());
            if ($errors === []) {
                $item = ItemCatalog::create([
                    'name' => $values['name'],
                    'price_cents' => Formatting::toCents($values['price']),
                    'is_mandatory_base' => $values['is_mandatory_base'],
                    'is_active' => $values['is_active'],
                    'family_ids' => $values['family_ids'],
                ]);
                Flash::success('Artículo «' . $item->name . '» creado con precio $' . Formatting::money($item->priceCents) . '.');
                Request::redirect(url('/gestion'));
            }
        }

        View::render('management/item_form', [
            'page_title' => 'Nuevo artículo · ' . cfg('app.brand_name'),
            'item' => null,
            'families' => EquipmentFamily::all(),
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    public static function itemEdit(array $params): void
    {
        require_staff_guard();

        $item = ItemCatalog::find((int) ($params['id'] ?? 0));
        if ($item === null) {
            View::error(404, 'Artículo no encontrado', 'El artículo solicitado no existe en el catálogo.');

            return;
        }

        $errors = [];
        $values = [
            'name' => $item->name,
            'price' => Formatting::decimal($item->priceCents),
            'is_mandatory_base' => $item->isMandatoryBase,
            'is_active' => $item->isActive,
            'family_ids' => $item->familyIds,
        ];

        if (Request::isPost()) {
            [$values, $errors] = self::validateItem(Request::postData(), $item->id);
            if ($errors === []) {
                $item->update([
                    'name' => $values['name'],
                    'price_cents' => Formatting::toCents($values['price']),
                    'is_mandatory_base' => $values['is_mandatory_base'],
                    'is_active' => $values['is_active'],
                    'family_ids' => $values['family_ids'],
                ]);
                Flash::success('Artículo «' . $values['name'] . '» actualizado.');
                Request::redirect(url('/gestion'));
            }
        }

        View::render('management/item_form', [
            'page_title' => 'Editar artículo · ' . cfg('app.brand_name'),
            'item' => $item,
            'families' => EquipmentFamily::all(),
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    public static function priceUpdate(array $params): void
    {
        require_staff_guard();

        $item = ItemCatalog::find((int) ($params['id'] ?? 0));
        if ($item === null) {
            View::error(404, 'Artículo no encontrado', 'El artículo solicitado no existe en el catálogo.');

            return;
        }

        $price = trim((string) Request::post('price', ''));
        if (!self::validPrice($price)) {
            Flash::error('El precio indicado no es válido.');
            Request::redirect(url('/gestion'));

            return;
        }

        $item->setPrice(Formatting::toCents($price));
        Flash::success('Precio de «' . $item->name . '» actualizado a $' . Formatting::money(Formatting::toCents($price)) . '.');
        Request::redirect(url('/gestion'));
    }

    public static function toggle(array $params): void
    {
        require_staff_guard();

        $item = ItemCatalog::find((int) ($params['id'] ?? 0));
        if ($item === null) {
            View::error(404, 'Artículo no encontrado', 'El artículo solicitado no existe en el catálogo.');

            return;
        }

        $active = $item->toggleActive();
        Flash::success('Artículo «' . $item->name . '» ' . ($active ? 'habilitado' : 'deshabilitado') . '.');
        Request::redirect(url('/gestion'));
    }

    public static function familyCreate(): void
    {
        require_staff_guard();

        $errors = [];
        $values = [
            'name' => '',
            'prefix' => '',
            'allows_quotes' => true,
            'support_channel' => '',
            'is_active' => true,
        ];

        if (Request::isPost()) {
            [$values, $errors] = self::validateFamily(Request::postData());
            if ($errors === []) {
                $family = EquipmentFamily::create([
                    'name' => $values['name'],
                    'prefix' => $values['prefix'],
                    'allows_quotes' => $values['allows_quotes'],
                    'support_channel' => $values['support_channel'],
                    'is_active' => $values['is_active'],
                ]);
                Flash::success('Familia «' . $family->name . '» creada.');
                Request::redirect(url('/gestion'));
            }
        }

        View::render('management/family_form', [
            'page_title' => 'Nueva familia · ' . cfg('app.brand_name'),
            'family' => null,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    public static function familyEdit(array $params): void
    {
        require_staff_guard();

        $family = EquipmentFamily::find((int) ($params['id'] ?? 0));
        if ($family === null) {
            View::error(404, 'Familia no encontrada', 'La familia solicitada no existe en el catálogo.');

            return;
        }

        $errors = [];
        $values = [
            'name' => $family->name,
            'prefix' => $family->prefix,
            'allows_quotes' => $family->allowsQuotes,
            'support_channel' => $family->supportChannel,
            'is_active' => $family->isActive,
        ];

        if (Request::isPost()) {
            [$values, $errors] = self::validateFamily(Request::postData(), $family->id);
            if ($errors === []) {
                $family->update([
                    'name' => $values['name'],
                    'prefix' => $values['prefix'],
                    'allows_quotes' => $values['allows_quotes'],
                    'support_channel' => $values['support_channel'],
                    'is_active' => $values['is_active'],
                ]);
                Flash::success('Familia «' . $values['name'] . '» actualizada.');
                Request::redirect(url('/gestion'));
            }
        }

        View::render('management/family_form', [
            'page_title' => 'Editar familia · ' . cfg('app.brand_name'),
            'family' => $family,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private static function validateItem(array $input, ?int $ignoreId = null): array
    {
        $values = [
            'name' => trim((string) ($input['name'] ?? '')),
            'price' => trim((string) ($input['price'] ?? '')),
            'is_mandatory_base' => self::checkbox($input, 'is_mandatory_base'),
            'is_active' => self::checkbox($input, 'is_active'),
            'family_ids' => self::ids($input['family_ids'] ?? []),
        ];
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'][] = 'Indique el nombre del artículo.';
        } elseif (mb_strlen($values['name'], 'UTF-8') > 160) {
            $errors['name'][] = 'El nombre no puede superar los 160 caracteres.';
        } elseif (ItemCatalog::nameExists($values['name'], $ignoreId)) {
            $errors['name'][] = 'Ya existe un artículo con ese nombre en el catálogo.';
        }

        if ($values['price'] === '') {
            $errors['price'][] = 'Indique el precio referencial en USD.';
        } elseif (!self::validPrice($values['price'])) {
            $errors['price'][] = 'Indique un precio válido (por ejemplo 80 o 12,50).';
        }

        return [$values, $errors];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private static function validateFamily(array $input, ?int $ignoreId = null): array
    {
        $values = [
            'name' => trim((string) ($input['name'] ?? '')),
            'prefix' => trim((string) ($input['prefix'] ?? '')),
            'allows_quotes' => self::checkbox($input, 'allows_quotes'),
            'support_channel' => trim((string) ($input['support_channel'] ?? '')),
            'is_active' => self::checkbox($input, 'is_active'),
        ];
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'][] = 'Indique el nombre de la familia.';
        } elseif (mb_strlen($values['name'], 'UTF-8') > 80) {
            $errors['name'][] = 'El nombre no puede superar los 80 caracteres.';
        } elseif (EquipmentFamily::nameExists($values['name'], $ignoreId)) {
            $errors['name'][] = 'Ya existe una familia con ese nombre.';
        }

        if ($values['prefix'] === '') {
            $errors['prefix'][] = 'Indique el prefijo que identifica al equipo (por ejemplo V10).';
        } elseif (mb_strlen($values['prefix'], 'UTF-8') > 20) {
            $errors['prefix'][] = 'El prefijo no puede superar los 20 caracteres.';
        }

        if (mb_strlen($values['support_channel'], 'UTF-8') > 160) {
            $errors['support_channel'][] = 'El canal no puede superar los 160 caracteres.';
        }

        return [$values, $errors];
    }

    private static function validPrice(string $price): bool
    {
        if ($price === '') {
            return false;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $price) === 1) {
            return true;
        }

        return preg_match('/^\d+(?:[.,]\d{1,2})?$/', $price) === 1;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function checkbox(array $input, string $key): bool
    {
        $value = $input[$key] ?? null;
        if ($value === null) {
            return false;
        }

        return !in_array(strtolower((string) $value), ['0', 'false', 'off', ''], true);
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        if (!is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn (int $id): bool => $id > 0
        )));
    }
}


