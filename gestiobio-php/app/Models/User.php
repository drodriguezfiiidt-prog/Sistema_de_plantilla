<?php

declare(strict_types=1);

namespace GestioBio\Models;

use GestioBio\Database;
use GestioBio\Support\Clock;

/**
 * Usuario del sistema (staff para el panel de gestion).
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $fullName,
        public readonly bool $isStaff,
        public readonly string $passwordHash,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            username: (string) $row['username'],
            fullName: (string) ($row['full_name'] ?? ''),
            isStaff: (bool) $row['is_staff'],
            passwordHash: (string) $row['password_hash'],
        );
    }

    public static function findByUsername(string $username): ?self
    {
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE username = :username');
        $statement->execute([':username' => trim($username)]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public static function find(int $id): ?self
    {
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public static function attempt(string $username, string $password): ?self
    {
        $user = self::findByUsername($username);
        if ($user === null || !$user->verifyPassword($password)) {
            return null;
        }

        return $user;
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $statement = Database::pdo()->query('SELECT * FROM users ORDER BY username ASC');

        return array_map(
            static fn (array $row): self => self::fromRow($row),
            $statement->fetchAll()
        );
    }

    public static function create(string $username, string $password, string $fullName, bool $isStaff): self
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO users (username, password_hash, full_name, is_staff, created_at)
             VALUES (:username, :password_hash, :full_name, :is_staff, :created_at)'
        );
        $statement->execute([
            ':username' => trim($username),
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':full_name' => $fullName,
            ':is_staff' => $isStaff ? 1 : 0,
            ':created_at' => Clock::now(),
        ]);

        return self::find((int) Database::pdo()->lastInsertId())
            ?? throw new \RuntimeException('No se pudo crear el usuario.');
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function updatePassword(string $password): void
    {
        $statement = Database::pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $statement->execute([
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $this->id,
        ]);
    }

    public function displayName(): string
    {
        return $this->fullName !== '' ? $this->fullName : $this->username;
    }
}
