<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ConfigurationValidator;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/ConfigurationValidator.php';

function assertProductionConfig(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$original = [];
foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_BASE_PATH', 'SESSION_DRIVER', 'LOG_CHANNEL'] as $key) {
    $original[$key] = getenv($key);
}

try {
    putenv('APP_ENV=production');
    putenv('APP_DEBUG=false');
    putenv('APP_URL=https://preview.example.test');
    putenv('APP_BASE_PATH=');
    putenv('SESSION_DRIVER=database');
    putenv('LOG_CHANNEL=stderr');

    $app = require BASE_PATH . '/config/app.php';
    assertProductionConfig($app['environment'] === 'production', 'APP_ENV was not applied.');
    assertProductionConfig($app['debug'] === false, 'Production debug mode was not disabled.');
    assertProductionConfig($app['session']['driver'] === 'database', 'SESSION_DRIVER was not applied.');
    assertProductionConfig($app['log_channel'] === 'stderr', 'LOG_CHANNEL was not applied.');

    $failedClosed = false;
    try {
        ConfigurationValidator::validate(
            $app,
            ['host' => '', 'database' => '', 'username' => '', 'password' => ''],
            ['razorpay' => []],
            ['driver' => 'cloudinary', 'cloudinary' => []]
        );
    } catch (RuntimeException $exception) {
        $failedClosed = !str_contains($exception->getMessage(), 'replace-with');
    }
    assertProductionConfig($failedClosed, 'Incomplete production configuration did not fail closed.');

    ConfigurationValidator::validate(
        $app,
        ['host' => 'db.example.test', 'database' => 'events', 'username' => 'app', 'password' => 'fake',
            'ssl_mode' => 'required', 'ssl_ca' => ''],
        ['razorpay' => ['key_id' => 'rzp_test_example', 'key_secret' => 'fake', 'webhook_secret' => 'fake']],
        ['driver' => 'cloudinary', 'cloudinary' => [
            'cloud_name' => 'example', 'api_key' => 'fake', 'api_secret' => 'fake', 'folder' => 'events',
        ]]
    );

    fwrite(STDOUT, "Production configuration tests passed.\n");
} finally {
    foreach ($original as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
}
