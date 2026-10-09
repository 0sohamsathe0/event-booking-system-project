<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private array $activityAgeById = [];

    public function __construct(
        private readonly int $lifetime,
        private readonly int $touchInterval = 60
    )
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
            'SELECT payload,
                    TIMESTAMPDIFF(SECOND, last_activity, UTC_TIMESTAMP(6)) AS activity_age_seconds
             FROM sessions
             WHERE id = :id AND expires_at > UTC_TIMESTAMP(6)
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $session = $statement->fetch();

        if (!is_array($session)) {
            unset($this->activityAgeById[$id]);
            return '';
        }

        $this->activityAgeById[$id] = max(0, (int) $session['activity_age_seconds']);
        return is_string($session['payload']) ? $session['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO sessions (id, payload, last_activity, expires_at)
             VALUES (:id, :payload, UTC_TIMESTAMP(6), :expires_at)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload),
                last_activity = VALUES(last_activity), expires_at = VALUES(expires_at)'
        );

        $written = $statement->execute([
            'id' => $id,
            'payload' => $data,
            'expires_at' => gmdate('Y-m-d H:i:s.u', time() + $this->lifetime),
        ]);
        if ($written) {
            $this->activityAgeById[$id] = 0;
        }

        return $written;
    }

    public function destroy(string $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE id = :id');
        unset($this->activityAgeById[$id]);

        return $statement->execute(['id' => $id]);
    }

    public function validateId(string $id): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM sessions WHERE id = :id AND expires_at > UTC_TIMESTAMP(6) LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $age = $this->activityAgeById[$id] ?? $this->touchInterval;
        if ($age < max(1, min($this->touchInterval, intdiv($this->lifetime, 4)))) {
            return true;
        }

        $statement = Database::connection()->prepare(
            'UPDATE sessions
             SET last_activity = UTC_TIMESTAMP(6), expires_at = :expires_at
             WHERE id = :id AND expires_at > UTC_TIMESTAMP(6)'
        );
        $updated = $statement->execute([
            'id' => $id,
            'expires_at' => gmdate('Y-m-d H:i:s.u', time() + $this->lifetime),
        ]);
        if ($updated && $statement->rowCount() === 1) {
            $this->activityAgeById[$id] = 0;
            return true;
        }

        return false;
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE expires_at <= UTC_TIMESTAMP(6)');
        $statement->execute();

        return $statement->rowCount();
    }
}
