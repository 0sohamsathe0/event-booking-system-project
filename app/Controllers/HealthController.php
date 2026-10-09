<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\SafeDiagnostics;

final class HealthController
{
    public function show(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $failure = null;
        try {
            $healthy = (int) Database::connection()->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable $exception) {
            Logger::error('health.database_unavailable', ['exception' => $exception]);
            $failure = $exception;
            $healthy = false;
        }

        http_response_code($healthy ? 200 : 503);
        echo $healthy
            ? 'healthy'
            : SafeDiagnostics::unavailable($failure ?? new \RuntimeException('Health query returned an invalid result.'));
    }
}
