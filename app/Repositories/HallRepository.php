<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class HallRepository
{
    public function first(): ?array
    {
        $hall = Database::connection()->query('SELECT * FROM halls ORDER BY id LIMIT 1')->fetch();
        return is_array($hall) ? $hall : null;
    }

    public function save(array $data): int
    {
        $database = Database::connection();
        $database->beginTransaction();

        try {
            $hall = $database->query('SELECT id FROM halls ORDER BY id LIMIT 1 FOR UPDATE')->fetch();

            if (is_array($hall)) {
                $statement = $database->prepare(
                    'UPDATE halls SET name = :name, address = :address, city = :city,
                        maximum_capacity = :maximum_capacity, contact_phone = :contact_phone,
                        contact_email = :contact_email, is_active = 1
                     WHERE id = :id'
                );
                $data['id'] = (int) $hall['id'];
                $statement->execute($data);
                $hallId = (int) $hall['id'];
            } else {
                $statement = $database->prepare(
                    'INSERT INTO halls
                        (name, address, city, maximum_capacity, contact_phone, contact_email, is_active)
                     VALUES
                        (:name, :address, :city, :maximum_capacity, :contact_phone, :contact_email, 1)'
                );
                $statement->execute($data);
                $hallId = (int) $database->lastInsertId();
            }

            $database->commit();
            return $hallId;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function highestScheduledEventCapacity(): int
    {
        return (int) Database::connection()->query(
            "SELECT COALESCE(MAX(event_capacity), 0)
             FROM events
             WHERE status IN ('pending', 'approved')"
        )->fetchColumn();
    }
}
