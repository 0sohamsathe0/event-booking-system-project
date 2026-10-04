<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class EventRepository
{
    public function forOrganizer(int $organizerId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.id, e.title, e.start_datetime, e.end_datetime,
                    e.event_capacity, e.status, e.poster_path, e.updated_at,
                    c.name AS category_name, h.name AS hall_name,
                    (SELECT COUNT(*) FROM bookings b WHERE b.event_id = e.id AND b.status = 'confirmed') AS confirmed_bookings,
                    (SELECT COALESCE(SUM(b.total_quantity), 0) FROM bookings b WHERE b.event_id = e.id AND b.status = 'confirmed') AS confirmed_tickets,
                    (SELECT COALESCE(SUM(CASE WHEN b.total_amount = 0 OR EXISTS (
                        SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.status = 'captured'
                    ) THEN b.total_amount ELSE 0 END), 0)
                     FROM bookings b WHERE b.event_id = e.id AND b.status = 'confirmed') AS confirmed_revenue,
                    (SELECT esh.reason FROM event_status_history esh
                     WHERE esh.event_id = e.id AND esh.new_status = 'rejected'
                     ORDER BY esh.created_at DESC, esh.id DESC LIMIT 1) AS rejection_reason
             FROM events e
             INNER JOIN event_categories c ON c.id = e.category_id
             INNER JOIN halls h ON h.id = e.hall_id
             WHERE e.organizer_id = :organizer_id
             ORDER BY e.created_at DESC"
        );
        $statement->execute(['organizer_id' => $organizerId]);
        return $statement->fetchAll();
    }

    public function findOwned(int $id, int $organizerId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM events WHERE id = :id AND organizer_id = :organizer_id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id, 'organizer_id' => $organizerId]);
        $event = $statement->fetch();
        return is_array($event) ? $event : null;
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            "INSERT INTO events
                (organizer_id, hall_id, category_id, title, description, poster_path,
                 poster_provider, poster_public_id,
                 start_datetime, end_datetime, sale_start_datetime,
                 sale_end_datetime, event_capacity, status)
             VALUES
                (:organizer_id, :hall_id, :category_id, :title, :description, :poster_path,
                 :poster_provider, :poster_public_id,
                 :start_datetime, :end_datetime, :sale_start_datetime,
                 :sale_end_datetime, :event_capacity, 'pending')"
        );
        $statement->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, int $organizerId, array $data, string $status): void
    {
        $data['id'] = $id;
        $data['organizer_id'] = $organizerId;
        $data['status'] = $status;
        $statement = Database::connection()->prepare(
            'UPDATE events SET
                hall_id = :hall_id, category_id = :category_id, title = :title,
                description = :description, poster_path = :poster_path,
                poster_provider = :poster_provider, poster_public_id = :poster_public_id,
                start_datetime = :start_datetime, end_datetime = :end_datetime,
                sale_start_datetime = :sale_start_datetime,
                sale_end_datetime = :sale_end_datetime,
                event_capacity = :event_capacity, status = :status
             WHERE id = :id AND organizer_id = :organizer_id'
        );
        $statement->execute($data);
    }

    public function addStatusHistory(
        int $eventId,
        ?string $oldStatus,
        string $newStatus,
        int $changedBy,
        ?string $reason
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO event_status_history
                (event_id, old_status, new_status, changed_by, reason)
             VALUES (:event_id, :old_status, :new_status, :changed_by, :reason)'
        );
        $statement->execute([
            'event_id' => $eventId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'reason' => $reason,
        ]);
    }
}
