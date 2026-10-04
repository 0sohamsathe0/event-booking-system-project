<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class EventCatalogRepository
{
    public function activeCategories(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM event_categories WHERE is_active = 1 ORDER BY name')
            ->fetchAll();
    }

    public function activeHall(): ?array
    {
        $hall = Database::connection()
            ->query('SELECT id, name, city, maximum_capacity FROM halls WHERE is_active = 1 ORDER BY id LIMIT 1')
            ->fetch();

        return is_array($hall) ? $hall : null;
    }

    public function categoryExists(int $id): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM event_categories WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function hallById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, maximum_capacity FROM halls WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $hall = $statement->fetch();
        return is_array($hall) ? $hall : null;
    }
}

