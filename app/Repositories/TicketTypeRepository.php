<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class TicketTypeRepository
{
    public function forEvent(int $eventId, bool $forUpdate = false): array
    {
        $sql = 'SELECT id, event_id, name, price, capacity, reserved_quantity,
                       sold_quantity, is_active, display_order, created_at, updated_at
                FROM ticket_types WHERE event_id = :event_id
                ORDER BY display_order, id' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['event_id' => $eventId]);
        return $statement->fetchAll();
    }

    public function findOwned(int $id, int $eventId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT tt.* FROM ticket_types tt
                WHERE tt.id = :id AND tt.event_id = :event_id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id, 'event_id' => $eventId]);
        $ticket = $statement->fetch();
        return is_array($ticket) ? $ticket : null;
    }

    public function nameExists(int $eventId, string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM ticket_types WHERE event_id = :event_id AND name = :name';
        $parameters = ['event_id' => $eventId, 'name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO ticket_types (event_id, name, price, capacity, display_order)
             VALUES (:event_id, :name, :price, :capacity, :display_order)'
        );
        $statement->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, int $eventId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE ticket_types SET name = :name, price = :price,
                    capacity = :capacity, display_order = :display_order
             WHERE id = :id AND event_id = :event_id'
        );
        $statement->execute($data + ['id' => $id, 'event_id' => $eventId]);
    }

    public function setActive(int $id, int $eventId, bool $active): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE ticket_types SET is_active = :is_active WHERE id = :id AND event_id = :event_id'
        );
        $statement->execute(['is_active' => $active ? 1 : 0, 'id' => $id, 'event_id' => $eventId]);
    }
}

