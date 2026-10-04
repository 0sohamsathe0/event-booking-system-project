<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(private readonly int $lifetime)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $statement = Database::connection()->prepare(
            'SELECT payload FROM sessions WHERE id = :id AND expires_at > UTC_TIMESTAMP(6) LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $payload = $statement->fetchColumn();

        return is_string($payload) ? $payload : '';
    }

    public function write(string $id, string $data): bool
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO sessions (id, payload, last_activity, expires_at)
             VALUES (:id, :payload, UTC_TIMESTAMP(6), :expires_at)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload),
                last_activity = VALUES(last_activity), expires_at = VALUES(expires_at)'
        );

        return $statement->execute([
            'id' => $id,
            'payload' => $data,
            'expires_at' => gmdate('Y-m-d H:i:s.u', time() + $this->lifetime),
        ]);
    }

    public function destroy(string $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE id = :id');

        return $statement->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE expires_at <= UTC_TIMESTAMP(6)');
        $statement->execute();

        return $statement->rowCount();
    }
}
