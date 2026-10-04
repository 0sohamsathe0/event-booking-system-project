<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

// Keep development diagnostics outside the public document root. Controllers
// still return safe messages while full exceptions remain available locally.
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

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

require BASE_PATH . '/app/Support/helpers.php';

$appConfig = require BASE_PATH . '/config/app.php';
$databaseConfigFile = is_file(BASE_PATH . '/config/database.local.php')
    ? BASE_PATH . '/config/database.local.php'
    : BASE_PATH . '/config/database.php';
$databaseConfig = require $databaseConfigFile;

date_default_timezone_set($appConfig['timezone']);

Session::start($appConfig['session']);
Database::configure($databaseConfig);

return $appConfig;
