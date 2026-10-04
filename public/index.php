<?php

declare(strict_types=1);

use App\Core\Router;
use App\Core\Logger;
use App\Core\SecurityHeaders;

// When this file is used as PHP's built-in development-server router,
// allow real assets and uploads to be served directly by the server.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicFile = __DIR__ . '/' . ltrim((string) $requestPath, '/');

    if ($requestPath !== '/' && is_file($publicFile)) {
        return false;
    }
}

try {
    $app = require dirname(__DIR__) . '/bootstrap/app.php';
    SecurityHeaders::apply();

    $router = new Router();
    require BASE_PATH . '/routes/web.php';

    $router->dispatch(
        strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        $_SERVER['REQUEST_URI'] ?? '/'
    );
} catch (Throwable $exception) {
    if (class_exists(Logger::class)) {
        Logger::error('application.request_failed', ['exception' => $exception]);
    } else {
        error_log('[application.request_failed] ' . $exception::class);
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (rtrim($requestPath, '/') === '/health') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        echo 'unavailable';
        return;
    }

    http_response_code(500);

    if (($app['debug'] ?? false) === true) {
        echo '<h1>Application error</h1><pre>'
            . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    } else {
        echo '<h1>Something went wrong.</h1>';
    }
}
