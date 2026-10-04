<?php

declare(strict_types=1);

use App\Core\Router;

// When this file is used as PHP's built-in development-server router,
// allow real assets and uploads to be served directly by the server.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicFile = __DIR__ . '/' . ltrim((string) $requestPath, '/');

    if ($requestPath !== '/' && is_file($publicFile)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$router = new Router();
require BASE_PATH . '/routes/web.php';

try {
    $router->dispatch(
        strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        $_SERVER['REQUEST_URI'] ?? '/'
    );
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(500);

    if ($app['debug']) {
        echo '<h1>Application error</h1><pre>'
            . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    } else {
        echo '<h1>Something went wrong.</h1>';
    }
}
