<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Config;
use App\Core\ConfigurationValidator;
use App\Core\EnvironmentLoader;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$loadEnvironmentFile = PHP_SAPI !== 'cli'
    || filter_var(getenv('APP_LOAD_ENV_FILE') ?: false, FILTER_VALIDATE_BOOL);
if ($loadEnvironmentFile) {
    EnvironmentLoader::load(BASE_PATH . '/.env');
}

require BASE_PATH . '/app/Support/helpers.php';

$appConfig = require BASE_PATH . '/config/app.php';
$databaseConfig = require BASE_PATH . '/config/database.php';
$paymentConfig = require BASE_PATH . '/config/payment.php';
$storageConfig = require BASE_PATH . '/config/storage.php';
$cancellationConfig = require BASE_PATH . '/config/cancellation.php';

if ($appConfig['environment'] !== 'production' && is_file(BASE_PATH . '/config/database.local.php')) {
    $databaseConfig = array_replace($databaseConfig, require BASE_PATH . '/config/database.local.php');
}
if ($appConfig['environment'] !== 'production' && is_file(BASE_PATH . '/config/payment.local.php')) {
    $paymentConfig = array_replace_recursive($paymentConfig, require BASE_PATH . '/config/payment.local.php');
}

$databaseEnvironment = [
    'host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE',
    'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD', 'charset' => 'DB_CHARSET',
    'ssl_mode' => 'DB_SSL_MODE', 'ssl_ca' => 'DB_SSL_CA',
];
foreach ($databaseEnvironment as $key => $environmentKey) {
    $value = getenv($environmentKey);
    if ($value !== false) {
        $databaseConfig[$key] = $key === 'port' ? (int) $value : $value;
    }
}

$sslCa = trim((string) ($databaseConfig['ssl_ca'] ?? ''));
if (str_starts_with($sslCa, '-----BEGIN CERTIFICATE-----')) {
    $temporaryCa = rtrim(sys_get_temp_dir(), '/\\')
        . DIRECTORY_SEPARATOR . 'event-booking-db-ca-' . hash('sha256', $sslCa) . '.pem';
    if (!is_file($temporaryCa) && file_put_contents($temporaryCa, $sslCa, LOCK_EX) === false) {
        throw new RuntimeException('The database CA certificate could not be prepared.');
    }
    $databaseConfig['ssl_ca'] = $temporaryCa;
} elseif ($sslCa !== '' && !preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $sslCa)) {
    $databaseConfig['ssl_ca'] = BASE_PATH . '/' . ltrim(str_replace('\\', '/', $sslCa), '/');
}

foreach (['RAZORPAY_KEY_ID' => 'key_id', 'RAZORPAY_KEY_SECRET' => 'key_secret',
    'RAZORPAY_WEBHOOK_SECRET' => 'webhook_secret'] as $environmentKey => $key) {
    $value = getenv($environmentKey);
    if ($value !== false) {
        $paymentConfig['razorpay'][$key] = $value;
    }
}

ConfigurationValidator::validate($appConfig, $databaseConfig, $paymentConfig, $storageConfig);
Config::set('app', $appConfig);
Config::set('database', $databaseConfig);
Config::set('payment', $paymentConfig);
Config::set('storage', $storageConfig);
Config::set('cancellation', $cancellationConfig);

ini_set('log_errors', '1');
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');
if ($appConfig['log_channel'] === 'file') {
    ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
} else {
    ini_set('error_log', 'php://stderr');
}

date_default_timezone_set($appConfig['timezone']);

Database::configure($databaseConfig);
Session::start($appConfig['session']);

return $appConfig;
