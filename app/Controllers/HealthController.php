<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class HealthController
{
    public function show(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        try {
            $healthy = Database::connection()->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable) {
            $healthy = false;
        }

        http_response_code($healthy ? 200 : 503);
        echo $healthy ? 'healthy' : 'unavailable';
    }
}
