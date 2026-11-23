<?php

declare(strict_types=1);

namespace GestioBio;

use GestioBio\Support\Seeder;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexion PDO unica a SQLite + aplicacion del esquema y datos iniciales.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static ?string $path = null;

    public static function connect(?string $path = null): PDO
    {
        $path ??= self::$path ?? Config::string('database.path', base_path('storage/gestiobio.sqlite'));

        if (self::$pdo instanceof PDO && self::$path === $path) {
            return self::$pdo;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de la base de datos: ' . $directory);
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo abrir la base SQLite: ' . $exception->getMessage(), 0, $exception);
        }

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::$pdo = $pdo;
        self::$path = $path;

        self::migrate($pdo);
        Seeder::ensure($pdo);

        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    public static function migrate(PDO $pdo): void
    {
        $schema = base_path('database/schema.sql');
        $sql = file_get_contents($schema);
        if ($sql === false) {
            throw new RuntimeException('No se encontro el esquema: ' . $schema);
        }
        $pdo->exec($sql);
    }

    /**
     * Cierra la conexion actual (usado por los scripts de prueba con base temporal).
     */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$path = null;
    }
}
