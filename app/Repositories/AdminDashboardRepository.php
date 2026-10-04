<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AdminDashboardRepository
{
    public function counts(): array
    {
        $sql = "SELECT
            SUM(role = 'customer') AS customers,
            SUM(role = 'organizer') AS organizers,
            SUM(role = 'organizer' AND account_status = 'pending') AS pending_organizers
            FROM users";
        $counts = Database::connection()->query($sql)->fetch();

        return [
            'customers' => (int) ($counts['customers'] ?? 0),
            'organizers' => (int) ($counts['organizers'] ?? 0),
            'pending_organizers' => (int) ($counts['pending_organizers'] ?? 0),
            'pending_events' => (int) Database::connection()->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn(),
        ];
    }
}
