<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class OrganizerRepository
{
    public function all(?string $status = null): array
    {
        $sql = "SELECT id, name, email, phone, account_status, review_reason,
                       reviewed_at, created_at
                FROM users
                WHERE role = 'organizer'";
        $parameters = [];

        if ($status !== null) {
            $sql .= ' AND account_status = :status';
            $parameters['status'] = $status;
        }

        $sql .= " ORDER BY FIELD(account_status, 'pending', 'rejected', 'approved', 'disabled'),
                          created_at DESC";
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function lockById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, name, email, role, account_status
             FROM users WHERE id = :id LIMIT 1 FOR UPDATE"
        );
        $statement->execute(['id' => $id]);
        $organizer = $statement->fetch();

        return is_array($organizer) ? $organizer : null;
    }

    public function updateReview(int $id, string $status, int $adminId, ?string $reason): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET account_status = :status, reviewed_by = :admin_id,
                 reviewed_at = UTC_TIMESTAMP(6), review_reason = :reason
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'admin_id' => $adminId,
            'reason' => $reason,
            'id' => $id,
        ]);
    }

    public function resubmit(int $id, string $name, string $phone): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE users
             SET name = :name, phone = :phone, account_status = 'pending',
                 reviewed_by = NULL, reviewed_at = NULL, review_reason = NULL
             WHERE id = :id AND role = 'organizer' AND account_status = 'rejected'"
        );
        $statement->execute(['name' => $name, 'phone' => $phone, 'id' => $id]);
    }
}

