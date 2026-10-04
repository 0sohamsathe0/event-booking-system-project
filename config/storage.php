<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'driver' => (string) Config::env('POSTER_STORAGE_DRIVER', 'local'),
    'cloudinary' => [
        'cloud_name' => (string) Config::env('CLOUDINARY_CLOUD_NAME', ''),
        'api_key' => (string) Config::env('CLOUDINARY_API_KEY', ''),
        'api_secret' => (string) Config::env('CLOUDINARY_API_SECRET', ''),
        'folder' => trim((string) Config::env('CLOUDINARY_FOLDER', 'event-booking-system/events'), '/'),
    ],
];
