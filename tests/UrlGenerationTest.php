<?php

declare(strict_types=1);

use App\Core\Config;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Csrf.php';
require BASE_PATH . '/app/Support/helpers.php';

function assertUrl(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Config::set('app', ['base_path' => null, 'url' => '']);
$_SERVER['SCRIPT_NAME'] = '/Event Booking System/public/index.php';
assertUrl(base_url() === '/Event Booking System/public', 'Local subdirectory base path is incorrect.');
assertUrl(url('events/12') === '/Event Booking System/public/events/12', 'Local URL generation is incorrect.');

Config::set('app', ['base_path' => '', 'url' => 'https://preview.example.test']);
$_SERVER['SCRIPT_NAME'] = '/api/index.php';
assertUrl(base_url() === '', 'Production base path should be empty.');
assertUrl(url('events/12') === '/events/12', 'Production URL contains an internal entrypoint.');
assertUrl(absolute_url('webhooks/razorpay') === 'https://preview.example.test/webhooks/razorpay', 'Absolute URL is incorrect.');
assertUrl(url('https://res.cloudinary.com/example/image/upload/poster.jpg')
    === 'https://res.cloudinary.com/example/image/upload/poster.jpg', 'External URLs were rewritten.');
assertUrl(poster_url('https://res.cloudinary.com/example/image/upload/poster.jpg')
    === 'https://res.cloudinary.com/example/image/upload/poster.jpg', 'Cloudinary poster URL is incorrect.');
Config::set('app', ['base_path' => '', 'url' => 'https://preview.example.test', 'environment' => 'production']);
assertUrl(poster_url('uploads/events/local.jpg') === null, 'Production exposed an unavailable local poster path.');

fwrite(STDOUT, "URL generation tests passed.\n");
