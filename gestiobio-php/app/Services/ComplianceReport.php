<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Hallazgos de la validacion del formulario.
 */
final class ComplianceReport
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param list<string> $infos
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
        public array $infos = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function label(): string
    {
        if ($this->errors !== []) {
            return 'Con errores';
        }
        if ($this->warnings !== []) {
            return 'Con advertencias';
        }

        return 'Validado';
    }

    public function tone(): string
    {
        if ($this->errors !== []) {
            return 'danger';
        }
        if ($this->warnings !== []) {
            return 'warning';
        }

        return 'success';
    }
}
