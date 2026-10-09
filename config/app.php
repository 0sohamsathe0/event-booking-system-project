<?php

declare(strict_types=1);

use App\Core\Config;

$environment = trim((string) Config::env('APP_ENV', 'local')) ?: 'local';
$production = $environment === 'production';

return [
    'name' => 'Event Booking System',
    'environment' => $environment,
    'debug' => Config::envBool('APP_DEBUG', !$production),
    'url' => rtrim((string) Config::env('APP_URL', ''), '/'),
    'base_path' => Config::env('APP_BASE_PATH', $production ? '' : null),
    'timezone' => (string) Config::env('APP_TIMEZONE', 'Asia/Kolkata'),
    'log_channel' => (string) Config::env('LOG_CHANNEL', $production ? 'stderr' : 'file'),
    'session' => [
        'driver' => (string) Config::env('SESSION_DRIVER', $production ? 'database' : 'file'),
        'name' => (string) Config::env('SESSION_NAME', 'event_booking_session'),
        'save_path' => BASE_PATH . '/storage/sessions',
        'lifetime' => max(300, Config::envInt('SESSION_LIFETIME', 7200)),
        'secure' => $production,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
