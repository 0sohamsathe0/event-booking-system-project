<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AdminEventRepository
{
    public function all(?string $status = null): array
    {
        $sql = "SELECT e.id, e.title, e.description, e.poster_path,
                       e.start_datetime, e.end_datetime, e.sale_start_datetime,
                       e.sale_end_datetime, e.event_capacity, e.status,
                       e.created_at, u.name AS organizer_name, u.email AS organizer_email,
                       u.account_status AS organizer_status, h.name AS hall_name,
                       h.maximum_capacity AS hall_capacity, c.name AS category_name,
                       COALESCE(SUM(tt.is_active = 1), 0) AS ticket_type_count,
                       COALESCE(SUM(CASE WHEN tt.is_active = 1 OR tt.sold_quantity > 0
                           THEN tt.capacity ELSE 0 END), 0) AS ticket_capacity
                FROM events e
                JOIN users u ON u.id = e.organizer_id
                JOIN halls h ON h.id = e.hall_id
                JOIN event_categories c ON c.id = e.category_id
                LEFT JOIN ticket_types tt ON tt.event_id = e.id
                WHERE 1 = 1";
        $parameters = [];
        if ($status !== null) {
            $sql .= ' AND e.status = :status';
            $parameters['status'] = $status;
        }
        $sql .= " GROUP BY e.id ORDER BY FIELD(e.status, 'pending', 'approved', 'rejected', 'cancelled'), e.created_at DESC";
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $event) {
            if ((int) $event['id'] === $id) {
                return $event;
            }
        }
        return null;
    }

    public function basic(int $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM events WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $event = $statement->fetch();
        return is_array($event) ? $event : null;
    }

    public function overlapExists(array $event): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM events
             WHERE hall_id = :hall_id AND status = 'approved' AND id <> :id
               AND start_datetime < :end_datetime
               AND end_datetime > :start_datetime
             LIMIT 1 FOR UPDATE"
        );
        $statement->execute([
            'hall_id' => $event['hall_id'], 'id' => $event['id'],
            'end_datetime' => $event['end_datetime'], 'start_datetime' => $event['start_datetime'],
        ]);
        return $statement->fetchColumn() !== false;
    }

    public function ticketSummary(int $eventId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT COALESCE(SUM(is_active = 1), 0) AS type_count,
                    COALESCE(SUM(CASE WHEN is_active = 1 OR sold_quantity > 0 THEN capacity ELSE 0 END), 0) AS total_capacity
             FROM ticket_types WHERE event_id = :event_id"
        );
        $statement->execute(['event_id' => $eventId]);
        return $statement->fetch();
    }

    public function setStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare('UPDATE events SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);
    }
}
