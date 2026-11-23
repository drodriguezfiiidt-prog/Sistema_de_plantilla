<?php

declare(strict_types=1);

namespace GestioBio\Models;

use GestioBio\Database;
use GestioBio\Support\Clock;

/**
 * Registro de evaluaciones tecnicas generadas desde el asistente.
 */
final class DiagnosticTicket
{
    /**
     * @param list<ItemCatalog> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $ticketNumber,
        public readonly string $serialNumber,
        public readonly ?EquipmentFamily $family,
        public readonly string $warrantyStatus,
        public readonly string $technicalReport,
        public readonly int $totalCents,
        public readonly string $documentBody,
        public readonly ?string $createdBy,
        public readonly string $createdAt,
        public readonly array $items = [],
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function warrantyChoices(): array
    {
        return [
            'Vigente' => 'Vigente',
            'Vencida' => 'Vencida',
            'Invalidada' => 'Invalidada',
        ];
    }

    public function warrantyLabel(): string
    {
        return self::warrantyChoices()[$this->warrantyStatus] ?? $this->warrantyStatus;
    }

    /**
     * @param list<ItemCatalog> $items
     */
    public static function create(
        string $ticketNumber,
        string $serialNumber,
        ?EquipmentFamily $family,
        string $warrantyStatus,
        string $technicalReport,
        int $totalCents,
        string $documentBody,
        ?int $createdBy,
        array $items,
    ): self {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO diagnostic_tickets
                    (ticket_number, serial_number, family_id, warranty_status, technical_report,
                     total_cents, document_body, created_by, created_at)
                 VALUES (:ticket_number, :serial_number, :family_id, :warranty_status, :technical_report,
                     :total_cents, :document_body, :created_by, :created_at)'
            );
            $statement->execute([
                ':ticket_number' => $ticketNumber,
                ':serial_number' => $serialNumber,
                ':family_id' => $family?->id,
                ':warranty_status' => $warrantyStatus,
                ':technical_report' => $technicalReport,
                ':total_cents' => max(0, $totalCents),
                ':document_body' => $documentBody,
                ':created_by' => $createdBy,
                ':created_at' => Clock::now(),
            ]);
            $ticketId = (int) $pdo->lastInsertId();

            $link = $pdo->prepare(
                'INSERT OR IGNORE INTO ticket_items (ticket_id, item_id) VALUES (:ticket_id, :item_id)'
            );
            foreach ($items as $item) {
                $link->execute([':ticket_id' => $ticketId, ':item_id' => $item->id]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return self::find($ticketId) ?? throw new \RuntimeException('No se pudo registrar el ticket.');
    }

    public static function find(int $id): ?self
    {
        $statement = Database::pdo()->prepare(self::baseQuery() . ' WHERE t.id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $items = self::itemsForTickets([(int) $row['id']])[(int) $row['id']] ?? [];

        return self::fromRow($row, $items);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM diagnostic_tickets')->fetchColumn();
    }

    /**
     * Busqueda del historial con los mismos filtros del proyecto Django.
     *
     * @param array{q?: string, garantia?: string, familia?: string} $filters
     *
     * @return array{tickets: list<self>, total: int}
     */
    public static function search(array $filters = [], int $limit = 100): array
    {
        [$where, $params] = self::buildFilters($filters);

        $countStatement = Database::pdo()->prepare('SELECT COUNT(*) FROM diagnostic_tickets t' . $where);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = Database::pdo()->prepare(
            self::baseQuery() . $where . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . max(1, $limit)
        );
        $statement->execute($params);
        $rows = $statement->fetchAll();

        $items = self::itemsForTickets(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $tickets = array_map(
            static fn (array $row): self => self::fromRow($row, $items[(int) $row['id']] ?? []),
            $rows
        );

        return ['tickets' => $tickets, 'total' => $total];
    }

    public function itemsSummary(): string
    {
        return implode(', ', array_map(static fn (ItemCatalog $item): string => $item->name, $this->items));
    }

    public function documentFilename(): string
    {
        return 'Diagnostico_' . self::safeTicket($this->ticketNumber) . '.docx';
    }

    public static function safeTicket(string $ticketNumber): string
    {
        $safe = '';
        foreach (preg_split('//u', $ticketNumber, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (preg_match('/^[\p{L}\p{N}]$/u', $char) === 1 || in_array($char, ['-', '_', '.'], true)) {
                $safe .= $char;
            }
        }

        return $safe !== '' ? $safe : 'sin_ticket';
    }

    /**
     * @param array<string, mixed> $row
     * @param list<ItemCatalog>    $items
     */
    private static function fromRow(array $row, array $items = []): self
    {
        $familyId = $row['family_id'] ?? null;

        return new self(
            id: (int) $row['id'],
            ticketNumber: (string) $row['ticket_number'],
            serialNumber: (string) $row['serial_number'],
            family: $familyId !== null ? EquipmentFamily::find((int) $familyId) : null,
            warrantyStatus: (string) $row['warranty_status'],
            technicalReport: (string) $row['technical_report'],
            totalCents: (int) $row['total_cents'],
            documentBody: (string) $row['document_body'],
            createdBy: isset($row['created_by_username']) ? (string) $row['created_by_username'] : null,
            createdAt: (string) $row['created_at'],
            items: $items,
        );
    }

    private static function baseQuery(): string
    {
        return 'SELECT t.*, u.username AS created_by_username
                  FROM diagnostic_tickets t
             LEFT JOIN users u ON u.id = t.created_by';
    }

    /**
     * @param array{q?: string, garantia?: string, familia?: string} $filters
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function buildFilters(array $filters): array
    {
        $conditions = [];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $conditions[] = '(t.ticket_number LIKE :query OR t.serial_number LIKE :query)';
            $params[':query'] = '%' . $query . '%';
        }

        $warranty = trim((string) ($filters['garantia'] ?? ''));
        if ($warranty !== '') {
            $conditions[] = 't.warranty_status = :warranty';
            $params[':warranty'] = $warranty;
        }

        $family = trim((string) ($filters['familia'] ?? ''));
        if ($family !== '') {
            $conditions[] = 't.family_id = :family';
            $params[':family'] = (int) $family;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param list<int> $ticketIds
     *
     * @return array<int, list<ItemCatalog>>
     */
    private static function itemsForTickets(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ticketIds), '?'));
        $statement = Database::pdo()->prepare(
            'SELECT ticket_items.ticket_id AS ticket_id, items.*
               FROM ticket_items
               JOIN items ON items.id = ticket_items.item_id
              WHERE ticket_items.ticket_id IN (' . $placeholders . ')
              ORDER BY items.name ASC'
        );
        $statement->execute($ticketIds);

        $itemRows = [];
        foreach ($statement->fetchAll() as $row) {
            $ticketId = (int) $row['ticket_id'];
            unset($row['ticket_id']);
            $itemRows[$ticketId][] = $row;
        }

        $allItemIds = [];
        foreach ($itemRows as $rows) {
            foreach ($rows as $row) {
                $allItemIds[] = (int) $row['id'];
            }
        }

        $families = self::familyMapForItems($allItemIds);
        $grouped = [];
        foreach ($itemRows as $ticketId => $rows) {
            foreach ($rows as $row) {
                $grouped[$ticketId][] = ItemCatalog::fromRow($row, $families[(int) $row['id']] ?? []);
            }
        }

        return $grouped;
    }

    /**
     * @param list<int> $itemIds
     *
     * @return array<int, list<int>>
     */
    private static function familyMapForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique($itemIds));
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
        $statement = Database::pdo()->prepare(
            'SELECT item_id, family_id FROM item_family WHERE item_id IN (' . $placeholders . ')'
        );
        $statement->execute($itemIds);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['item_id']][] = (int) $row['family_id'];
        }

        return $map;
    }
}

