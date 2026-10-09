<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static array $config = [];

    private function __construct()
    {
    }

    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::$config = $config;
        ini_set('session.lazy_write', '1');
        if (($config['driver'] ?? 'file') === 'database') {
            session_set_save_handler(new DatabaseSessionHandler((int) $config['lifetime']), true);
        } else {
            if (!is_dir($config['save_path'])) {
                throw new \RuntimeException('The session storage directory is missing.');
            }
            session_save_path($config['save_path']);
        }

        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => $config['lifetime'],
            'path' => '/',
            'secure' => $config['secure'],
            'httponly' => $config['httponly'],
            'samesite' => $config['samesite'],
        ]);

        $started = PHP_SAPI === 'cli' ? @session_start() : session_start();
        if (!$started && PHP_SAPI !== 'cli') {
            throw new \RuntimeException('The application session could not be started.');
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function consumeFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function restart(): void
    {
        self::start(self::$config);
    }
}
