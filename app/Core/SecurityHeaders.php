<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    private function __construct()
    {
    }

    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');

        if (Config::get('app.environment') === 'production' && self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function isHttps(): bool
    {
        $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        return $forwarded === 'https'
            || strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
