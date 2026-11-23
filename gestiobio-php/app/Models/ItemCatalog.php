<?php

declare(strict_types=1);

namespace GestioBio\Models;

use GestioBio\Database;
use GestioBio\Support\Clock;

/**
 * Falla, repuesto o servicio con precio referencial en USD (centavos) y
 * compatibilidad por familia de equipos.
 */
final class ItemCatalog
{
    /**
     * @param list<int> $familyIds
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $priceCents,
        public readonly bool $isMandatoryBase,
        public readonly bool $isActive,
        public readonly array $familyIds = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int>            $familyIds
     */
    public static function fromRow(array $row, array $familyIds = []): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            priceCents: (int) $row['price_cents'],
            isMandatoryBase: (bool) $row['is_mandatory_base'],
            isActive: (bool) $row['is_active'],
            familyIds: $familyIds,
        );
    }

    public static function find(int $id): ?self
    {
        $statement = Database::pdo()->prepare('SELECT * FROM items WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return self::fromRow($row, self::loadFamilyMap([(int) $row['id']])[(int) $row['id']] ?? []);
    }

    /**
     * Catalogo completo ordenado por nombre (panel de gestion).
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::hydrate('SELECT * FROM items ORDER BY name ASC');
    }

    /**
     * Articulos que el operador puede marcar en el formulario.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return self::hydrate(
            'SELECT * FROM items WHERE is_active = 1 AND is_mandatory_base = 0 ORDER BY price_cents ASC, name ASC'
        );
    }

    /**
     * Articulos activos y compatibles con la familia indicada.
     *
     * @return list<self>
     */
    public static function forFamily(?EquipmentFamily $family): array
    {
        if ($family === null) {
            return [];
        }

        return self::hydrate(
            'SELECT items.*
               FROM items
               JOIN item_family ON item_family.item_id = items.id
              WHERE items.is_active = 1
                AND items.is_mandatory_base = 0
                AND item_family.family_id = :family_id
              ORDER BY items.price_cents ASC, items.name ASC',
            [':family_id' => $family->id]
        );
    }

    /**
     * Partidas base obligatorias activas y compatibles con la familia.
     *
     * @return list<self>
     */
    public static function baseLinesFor(?EquipmentFamily $family): array
    {
        if ($family === null) {
            return [];
        }

        return self::hydrate(
            'SELECT items.*
               FROM items
               JOIN item_family ON item_family.item_id = items.id
              WHERE items.is_active = 1
                AND items.is_mandatory_base = 1
                AND item_family.family_id = :family_id
              ORDER BY items.price_cents ASC, items.name ASC',
            [':family_id' => $family->id]
        );
    }

    /**
     * Recupera varios articulos respetando el orden de los identificadores.
     *
     * @param list<int> $ids
     *
     * @return list<self>
     */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = Database::pdo()->prepare('SELECT * FROM items WHERE id IN (' . $placeholders . ')');
        $statement->execute($ids);
        $rows = $statement->fetchAll();
        $familyMap = self::loadFamilyMap(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = self::fromRow($row, $familyMap[(int) $row['id']] ?? []);
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }

        return $ordered;
    }

    /**
     * Nombre oficial usado en la tabla: "{articulo} {modelo}" sin duplicar modelo.
     */
    public function displayName(?EquipmentFamily $family = null): string
    {
        $name = trim($this->name);
        if ($family === null) {
            return $name;
        }

        $model = $family->shortName();
        if ($model === '' || mb_stripos($name, $model) !== false) {
            return $name;
        }

        return $name . ' ' . $model;
    }

    /**
     * Disponibilidad efectiva: activo y compatible con la familia.
     */
    public function isAvailableFor(?EquipmentFamily $family): bool
    {
        if ($family === null || !$this->isActive) {
            return false;
        }

        return in_array($family->id, $this->familyIds, true);
    }

    /**
     * Familias compatibles (panel de gestion).
     *
     * @return list<EquipmentFamily>
     */
    public function families(): array
    {
        if ($this->familyIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($this->familyIds), '?'));
        $statement = Database::pdo()->prepare(
            'SELECT * FROM equipment_families WHERE id IN (' . $placeholders . ') ORDER BY name ASC'
        );
        $statement->execute($this->familyIds);

        return array_map(
            static fn (array $row): EquipmentFamily => EquipmentFamily::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * @param array{name: string, price_cents: int, is_mandatory_base: bool, is_active: bool, family_ids: list<int>} $data
     */
    public static function create(array $data): self
    {
        $pdo = Database::pdo();
        $now = Clock::now();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO items (name, price_cents, is_mandatory_base, is_active, created_at, updated_at)
                 VALUES (:name, :price_cents, :is_mandatory_base, :is_active, :created_at, :updated_at)'
            );
            $statement->execute([
                ':name' => $data['name'],
                ':price_cents' => $data['price_cents'],
                ':is_mandatory_base' => $data['is_mandatory_base'] ? 1 : 0,
                ':is_active' => $data['is_active'] ? 1 : 0,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::syncFamiliesFor($id, $data['family_ids']);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return self::find($id) ?? throw new \RuntimeException('No se pudo crear el articulo.');
    }

    /**
     * @param array{name: string, price_cents: int, is_mandatory_base: bool, is_active: bool, family_ids: list<int>} $data
     */
    public function update(array $data): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'UPDATE items
                    SET name = :name, price_cents = :price_cents, is_mandatory_base = :is_mandatory_base,
                        is_active = :is_active, updated_at = :updated_at
                  WHERE id = :id'
            );
            $statement->execute([
                ':name' => $data['name'],
                ':price_cents' => $data['price_cents'],
                ':is_mandatory_base' => $data['is_mandatory_base'] ? 1 : 0,
                ':is_active' => $data['is_active'] ? 1 : 0,
                ':updated_at' => Clock::now(),
                ':id' => $this->id,
            ]);
            self::syncFamiliesFor($this->id, $data['family_ids']);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function setPrice(int $cents): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE items SET price_cents = :price_cents, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            ':price_cents' => max(0, $cents),
            ':updated_at' => Clock::now(),
            ':id' => $this->id,
        ]);
    }

    public function toggleActive(): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE items SET is_active = :is_active, updated_at = :updated_at WHERE id = :id'
        );
        $newValue = !$this->isActive;
        $statement->execute([
            ':is_active' => $newValue ? 1 : 0,
            ':updated_at' => Clock::now(),
            ':id' => $this->id,
        ]);

        return $newValue;
    }

    /**
     * @param list<int> $familyIds
     */
    public function syncFamilies(array $familyIds): void
    {
        self::syncFamiliesFor($this->id, $familyIds);
    }

    public static function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM items WHERE name = :name';
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
     * @param list<int> $familyIds
     */
    private static function syncFamiliesFor(int $itemId, array $familyIds): void
    {
        $pdo = Database::pdo();
        $delete = $pdo->prepare('DELETE FROM item_family WHERE item_id = :item_id');
        $delete->execute([':item_id' => $itemId]);

        $insert = $pdo->prepare('INSERT OR IGNORE INTO item_family (item_id, family_id) VALUES (:item_id, :family_id)');
        foreach (array_unique(array_map('intval', $familyIds)) as $familyId) {
            if ($familyId <= 0) {
                continue;
            }
            $insert->execute([':item_id' => $itemId, ':family_id' => $familyId]);
        }
    }

    /**
     * @return list<self>
     */
    private static function hydrate(string $sql, array $params = []): array
    {
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            return [];
        }

        $familyMap = self::loadFamilyMap(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return array_map(
            static fn (array $row): self => self::fromRow($row, $familyMap[(int) $row['id']] ?? []),
            $rows
        );
    }

    /**
     * @param list<int> $itemIds
     *
     * @return array<int, list<int>>
     */
    private static function loadFamilyMap(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
        $statement = Database::pdo()->prepare(
            'SELECT item_id, family_id FROM item_family WHERE item_id IN (' . $placeholders . ') ORDER BY family_id ASC'
        );
        $statement->execute($itemIds);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['item_id']][] = (int) $row['family_id'];
        }

        return $map;
    }
}

