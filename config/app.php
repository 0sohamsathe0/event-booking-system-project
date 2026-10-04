<?php

declare(strict_types=1);

return [
    'name' => 'Event Booking System',
    'environment' => 'local',
    'debug' => true,
    'timezone' => 'Asia/Kolkata',
    'session' => [
        'name' => 'event_booking_session',
        'save_path' => BASE_PATH . '/storage/sessions',
        'lifetime' => 7200,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
