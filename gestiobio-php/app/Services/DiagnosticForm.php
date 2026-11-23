<?php

declare(strict_types=1);

namespace GestioBio\Services;

use GestioBio\Models\EquipmentFamily;
use GestioBio\Models\ItemCatalog;

/**
 * Validacion del formulario de diagnostico (equivalente a DiagnosticForm de
 * Django, incluida la autodeteccion de familia y los mensajes de error).
 */
final class DiagnosticForm
{
    public const WARRANTY_CHOICES = ['Vigente', 'Vencida', 'Invalidada'];

    public bool $isBound = false;

    /** @var array<string, string> */
    public array $values = [
        'ticket_number' => '',
        'serial_number' => '',
        'family' => '',
        'warranty_status' => 'Vencida',
        'technical_report' => '',
    ];

    /** @var array<string, list<string>> */
    public array $errors = [];

    public ?EquipmentFamily $familyObj = null;

    /** @var list<EquipmentFamily> */
    public array $familyOptions = [];

    /** @var list<ItemCatalog> */
    public array $itemOptions = [];

    /** @var list<int> */
    public array $selectedItemIds = [];

    /** @var array<string, mixed> */
    public array $cleaned = [];

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(array $input = [])
    {
        $this->isBound = $input !== [];
        $this->familyOptions = EquipmentFamily::active();
        $this->values = [
            'ticket_number' => trim((string) ($input['ticket_number'] ?? '')),
            'serial_number' => strtoupper(trim((string) ($input['serial_number'] ?? ''))),
            'family' => trim((string) ($input['family'] ?? '')),
            'warranty_status' => (string) ($input['warranty_status'] ?? 'Vencida'),
            'technical_report' => (string) ($input['technical_report'] ?? ''),
        ];

        $this->familyObj = $this->resolveFamily($input);
        $this->itemOptions = $this->familyObj !== null
            ? ItemCatalog::forFamily($this->familyObj)
            : ItemCatalog::selectable();

        $selected = $input['items'] ?? [];
        if (!is_array($selected)) {
            $selected = $selected === '' || $selected === null ? [] : [$selected];
        }
        $this->selectedItemIds = array_values(array_unique(array_map('intval', $selected)));

        if ($this->isBound) {
            $this->validate();
        }
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function value(string $field): string
    {
        return $this->values[$field] ?? '';
    }

    /**
     * @return list<string>
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Mensajes de campos sin errores propios (equivalente a non_field_errors).
     *
     * @return list<string>
     */
    public function nonFieldErrors(): array
    {
        return $this->errors['__all__'] ?? [];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveFamily(array $input): ?EquipmentFamily
    {
        $requested = trim((string) ($input['family'] ?? ''));
        if ($requested !== '' && ctype_digit($requested)) {
            $family = EquipmentFamily::find((int) $requested);
            if ($family !== null && $family->isActive) {
                return $family;
            }
        }

        return EquipmentFamily::detect($this->values['serial_number']);
    }

    private function validate(): void
    {
        if ($this->values['ticket_number'] === '') {
            $this->addError('ticket_number', 'Este campo es obligatorio.');
        } elseif (mb_strlen($this->values['ticket_number'], 'UTF-8') > 60) {
            $this->addError('ticket_number', 'Asegúrese de que este valor tenga como máximo 60 caracteres.');
        }

        if ($this->values['serial_number'] === '') {
            $this->addError('serial_number', 'Este campo es obligatorio.');
        } elseif (mb_strlen($this->values['serial_number'], 'UTF-8') > 80) {
            $this->addError('serial_number', 'Asegúrese de que este valor tenga como máximo 80 caracteres.');
        }

        $requestedFamily = trim($this->values['family']);
        if ($requestedFamily !== '' && !ctype_digit($requestedFamily)) {
            $this->addError('family', 'Seleccione una opción válida. Esa opción no está entre las disponibles.');
        }

        $allowedIds = array_map(
            static fn (ItemCatalog $item): int => $item->id,
            $this->itemOptions
        );
        $selected = array_values(array_intersect($this->selectedItemIds, $allowedIds));
        if ($this->selectedItemIds === []) {
            $this->addError('items', 'Marque al menos una falla o repuesto para el diagnóstico.');
        } elseif (count($selected) !== count($this->selectedItemIds)) {
            $this->addError('items', 'Seleccione una opción válida. Esa opción no está entre las disponibles.');
        }

        if (!in_array($this->values['warranty_status'], self::WARRANTY_CHOICES, true)) {
            $this->addError('warranty_status', 'Seleccione una opción válida. Esa opción no está entre las disponibles.');
        }

        $family = $this->familyObj;
        $serial = $this->values['serial_number'];

        if ($family === null) {
            $this->addError(
                'serial_number',
                'No se reconoce el prefijo del serial. Verifique el número o seleccione manualmente la familia del equipo.'
            );
        } else {
            if ($serial !== '' && !$family->matchesSerial($serial)) {
                $this->addError(
                    'serial_number',
                    'El serial no corresponde al prefijo ' . $family->prefix
                    . ' de la familia ' . $family->name . '.'
                );
            }

            $incompatible = array_values(array_filter(
                ItemCatalog::findMany($selected),
                static fn (ItemCatalog $item): bool => !$item->isAvailableFor($family)
            ));
            if ($incompatible !== []) {
                $this->addError(
                    'items',
                    'Artículos no habilitados para ' . $family->name . ': '
                    . implode(', ', array_map(static fn (ItemCatalog $item): string => $item->name, $incompatible))
                    . '.'
                );
            }
        }

        $this->cleaned = [
            'ticket_number' => $this->values['ticket_number'],
            'serial_number' => $serial,
            'family' => $family,
            'items' => ItemCatalog::findMany($selected),
            'warranty_status' => $this->values['warranty_status'],
            'technical_report' => $this->values['technical_report'],
        ];
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}

