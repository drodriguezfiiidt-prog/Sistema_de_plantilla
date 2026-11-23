<?php

declare(strict_types=1);

namespace GestioBio\Models;

use GestioBio\Database;
use GestioBio\Support\Clock;
use PDO;

/**
 * Familia o modelo de equipo atendido por el soporte tecnico.
 */
final class EquipmentFamily
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $prefix,
        public readonly bool $allowsQuotes,
        public readonly string $supportChannel,
        public readonly bool $isActive,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            prefix: (string) $row['prefix'],
            allowsQuotes: (bool) $row['allows_quotes'],
            supportChannel: (string) ($row['support_channel'] ?? ''),
            isActive: (bool) $row['is_active'],
        );
    }

    public static function find(int $id): ?self
    {
        $statement = Database::pdo()->prepare('SELECT * FROM equipment_families WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::hydrate('SELECT * FROM equipment_families ORDER BY name ASC');
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return self::hydrate('SELECT * FROM equipment_families WHERE is_active = 1 ORDER BY name ASC');
    }

    /**
     * Autodetecta la familia a partir del prefijo mas largo que coincida.
     */
    public static function detect(?string $serial): ?self
    {
        $serial = strtoupper(trim((string) $serial));
        if ($serial === '') {
            return null;
        }

        $best = null;
        $bestLength = -1;
        foreach (self::active() as $family) {
            $prefix = trim($family->prefix);
            if ($prefix === '' || !str_starts_with($serial, strtoupper($prefix))) {
                continue;
            }
            $length = mb_strlen($prefix, 'UTF-8');
            if ($length > $bestLength) {
                $best = $family;
                $bestLength = $length;
            }
        }

        return $best;
    }

    public function matchesSerial(?string $serial): bool
    {
        $prefix = strtoupper(trim($this->prefix));
        $serial = strtoupper(trim((string) $serial));

        return $prefix !== '' && str_starts_with($serial, $prefix);
    }

    /**
     * Nombre corto usado en la columna Modelo de la tabla tarifaria.
     */
    public function shortName(): string
    {
        $parts = explode('/', $this->name);
        $base = trim($parts[0]);

        return $base !== '' ? $base : $this->name;
    }

    /**
     * @param array{name: string, prefix: string, allows_quotes: bool, support_channel: string, is_active: bool} $data
     */
    public static function create(array $data): self
    {
        $now = Clock::now();
        $statement = Database::pdo()->prepare(
            'INSERT INTO equipment_families (name, prefix, allows_quotes, support_channel, is_active, created_at, updated_at)
             VALUES (:name, :prefix, :allows_quotes, :support_channel, :is_active, :created_at, :updated_at)'
        );
        $statement->execute([
            ':name' => $data['name'],
            ':prefix' => $data['prefix'],
            ':allows_quotes' => $data['allows_quotes'] ? 1 : 0,
            ':support_channel' => $data['support_channel'],
            ':is_active' => $data['is_active'] ? 1 : 0,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return self::find((int) Database::pdo()->lastInsertId()) ?? throw new \RuntimeException('No se pudo crear la familia.');
    }

    /**
     * @param array{name: string, prefix: string, allows_quotes: bool, support_channel: string, is_active: bool} $data
     */
    public function update(array $data): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE equipment_families
                SET name = :name, prefix = :prefix, allows_quotes = :allows_quotes,
                    support_channel = :support_channel, is_active = :is_active, updated_at = :updated_at
              WHERE id = :id'
        );
        $statement->execute([
            ':name' => $data['name'],
            ':prefix' => $data['prefix'],
            ':allows_quotes' => $data['allows_quotes'] ? 1 : 0,
            ':support_channel' => $data['support_channel'],
            ':is_active' => $data['is_active'] ? 1 : 0,
            ':updated_at' => Clock::now(),
            ':id' => $this->id,
        ]);
    }

    public static function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM equipment_families WHERE name = :name';
        $params = [':name' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @return list<self>
     */
    private static function hydrate(string $sql, array $params = []): array
    {
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn (array $row): self => self::fromRow($row),
            $statement->fetchAll()
        );
    }
}
