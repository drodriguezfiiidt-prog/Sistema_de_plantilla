<?php

declare(strict_types=1);

namespace GestioBio\Services;

/**
 * Bloque de contenido del documento institucional.
 */
final class Block
{
    public const PARAGRAPH = 'paragraph';
    public const HEADING = 'heading';
    public const BULLETS = 'bullets';
    public const TABLE = 'table';

    /**
     * @param list<string>       $items
     * @param list<string>       $columns
     * @param list<list<string>> $rows
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $text = '',
        public readonly bool $bold = false,
        public readonly int $level = 2,
        public readonly array $items = [],
        public readonly array $columns = [],
        public readonly array $rows = [],
    ) {
    }

    public static function paragraph(string $text, bool $bold = false): self
    {
        return new self(self::PARAGRAPH, $text, $bold);
    }

    public static function heading(string $text, int $level = 2): self
    {
        return new self(self::HEADING, $text, false, $level);
    }

    /**
     * @param list<string> $items
     */
    public static function bullets(array $items): self
    {
        return new self(self::BULLETS, items: $items);
    }

    /**
     * @param list<string>       $columns
     * @param list<list<string>> $rows
     */
    public static function table(array $columns, array $rows): self
    {
        return new self(self::TABLE, columns: $columns, rows: $rows);
    }
}
