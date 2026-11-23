<?php

declare(strict_types=1);

namespace GestioBio\Support;

use PDO;

/**
 * Datos iniciales (equivalente a fixtures/initial_catalog.json del proyecto
 * Django) y usuarios de demostracion. Solo se cargan en bases vacias.
 */
final class Seeder
{
    /**
     * @var list<array{name: string, prefix: string, allows_quotes: bool, support_channel: string}>
     */
    public const FAMILIES = [
        ['name' => 'Bio500/V10', 'prefix' => 'V10', 'allows_quotes' => true, 'support_channel' => ''],
        ['name' => 'Futronic', 'prefix' => 'FP-', 'allows_quotes' => true, 'support_channel' => ''],
        ['name' => 'SZZT', 'prefix' => 'G62', 'allows_quotes' => true, 'support_channel' => ''],
    ];

    /**
     * Precios en centavos de USD; "families" son indices 1-based del arreglo anterior.
     *
     * @var list<array{name: string, price_cents: int, is_mandatory_base: bool, families: list<int>}>
     */
    public const ITEMS = [
        ['name' => 'Lector biométrico', 'price_cents' => 8000, 'is_mandatory_base' => false, 'families' => [1, 2, 3]],
        ['name' => 'Pantalla', 'price_cents' => 8000, 'is_mandatory_base' => false, 'families' => [1, 2, 3]],
        ['name' => 'Pin', 'price_cents' => 2000, 'is_mandatory_base' => false, 'families' => [1, 2, 3]],
        ['name' => 'Botón', 'price_cents' => 1000, 'is_mandatory_base' => false, 'families' => [1, 2, 3]],
        ['name' => 'Mantenimiento menor', 'price_cents' => 1000, 'is_mandatory_base' => false, 'families' => [1, 2, 3]],
        [
            'name' => 'Reemplazo de partes y piezas de dispositivos biométricos',
            'price_cents' => 1000,
            'is_mandatory_base' => true,
            'families' => [1, 2, 3],
        ],
    ];

    /**
     * @var list<array{username: string, password: string, full_name: string, is_staff: bool}>
     */
    public const USERS = [
        [
            'username' => 'admin',
            'password' => 'GestioBio2026*',
            'full_name' => 'Administrador GestioBioV3',
            'is_staff' => true,
        ],
        [
            'username' => 'soporte',
            'password' => 'Soporte2026*',
            'full_name' => 'Analista de Soporte',
            'is_staff' => false,
        ],
    ];

    public static function ensure(PDO $pdo): void
    {
        $now = Clock::now();

        $familyCount = (int) $pdo->query('SELECT COUNT(*) FROM equipment_families')->fetchColumn();
        if ($familyCount === 0) {
            self::seedCatalog($pdo, $now);
        }

        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($userCount === 0) {
            self::seedUsers($pdo, $now);
        }
    }

    private static function seedCatalog(PDO $pdo, string $now): void
    {
        $pdo->beginTransaction();
        try {
            $insertFamily = $pdo->prepare(
                'INSERT INTO equipment_families (name, prefix, allows_quotes, support_channel, is_active, created_at, updated_at)
                 VALUES (:name, :prefix, :allows_quotes, :support_channel, 1, :created_at, :updated_at)'
            );
            $familyIds = [];
            foreach (self::FAMILIES as $index => $family) {
                $insertFamily->execute([
                    ':name' => $family['name'],
                    ':prefix' => $family['prefix'],
                    ':allows_quotes' => $family['allows_quotes'] ? 1 : 0,
                    ':support_channel' => $family['support_channel'],
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $familyIds[$index + 1] = (int) $pdo->lastInsertId();
            }

            $insertItem = $pdo->prepare(
                'INSERT INTO items (name, price_cents, is_mandatory_base, is_active, created_at, updated_at)
                 VALUES (:name, :price_cents, :is_mandatory_base, 1, :created_at, :updated_at)'
            );
            $insertLink = $pdo->prepare(
                'INSERT OR IGNORE INTO item_family (item_id, family_id) VALUES (:item_id, :family_id)'
            );

            foreach (self::ITEMS as $item) {
                $insertItem->execute([
                    ':name' => $item['name'],
                    ':price_cents' => $item['price_cents'],
                    ':is_mandatory_base' => $item['is_mandatory_base'] ? 1 : 0,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $itemId = (int) $pdo->lastInsertId();
                foreach ($item['families'] as $familyIndex) {
                    if (!isset($familyIds[$familyIndex])) {
                        continue;
                    }
                    $insertLink->execute([
                        ':item_id' => $itemId,
                        ':family_id' => $familyIds[$familyIndex],
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function seedUsers(PDO $pdo, string $now): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, is_staff, created_at)
             VALUES (:username, :password_hash, :full_name, :is_staff, :created_at)'
        );

        foreach (self::USERS as $user) {
            $insert->execute([
                ':username' => $user['username'],
                ':password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                ':full_name' => $user['full_name'],
                ':is_staff' => $user['is_staff'] ? 1 : 0,
                ':created_at' => $now,
            ]);
        }
    }
}
