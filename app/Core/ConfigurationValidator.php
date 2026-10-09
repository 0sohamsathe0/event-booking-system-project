<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ConfigurationValidator
{
    private function __construct()
    {
    }

    public static function validate(array $app, array $database, array $payment, array $storage): void
    {
        $sessionDriver = (string) ($app['session']['driver'] ?? '');
        $posterDriver = (string) ($storage['driver'] ?? '');
        $logChannel = (string) ($app['log_channel'] ?? '');

        if (!in_array($sessionDriver, ['file', 'database'], true)) {
            throw new RuntimeException('SESSION_DRIVER must be file or database.');
        }
        if (!in_array($posterDriver, ['local', 'cloudinary'], true)) {
            throw new RuntimeException('POSTER_STORAGE_DRIVER must be local or cloudinary.');
        }
        if (!in_array($logChannel, ['file', 'stderr'], true)) {
            throw new RuntimeException('LOG_CHANNEL must be file or stderr.');
        }
        if (!in_array(strtolower((string) ($database['ssl_mode'] ?? 'disabled')), [
            'disabled', 'preferred', 'required', 'verify_ca', 'verify_identity',
        ], true)) {
            throw new RuntimeException('DB_SSL_MODE is invalid.');
        }

        if (($app['environment'] ?? 'local') !== 'production') {
            return;
        }

        self::requireValues($database, ['host', 'database', 'username', 'password'], 'database');
        self::requireValues($payment['razorpay'] ?? [], ['key_id', 'key_secret', 'webhook_secret'], 'Razorpay');
        $appUrl = (string) ($app['url'] ?? '');
        if (!str_starts_with($appUrl, 'https://') || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('APP_URL must be a valid HTTPS production URL.');
        }
        if (!str_starts_with((string) ($payment['razorpay']['key_id'] ?? ''), 'rzp_test_')) {
            throw new RuntimeException('Phase 14 requires a Razorpay Test Mode key ID.');
        }

        $sslMode = strtolower((string) ($database['ssl_mode'] ?? 'disabled'));
        if (in_array($sslMode, ['verify_ca', 'verify_identity'], true)
            && trim((string) ($database['ssl_ca'] ?? '')) === '') {
            throw new RuntimeException('Verified database TLS requires DB_SSL_CA.');
        }

        if ($sessionDriver !== 'database') {
            throw new RuntimeException('Production requires SESSION_DRIVER=database.');
        }
        if ($posterDriver !== 'cloudinary') {
            throw new RuntimeException('Production requires POSTER_STORAGE_DRIVER=cloudinary.');
        }

        self::requireValues(
            $storage['cloudinary'] ?? [],
            ['cloud_name', 'api_key', 'api_secret', 'folder'],
            'Cloudinary'
        );
    }

    private static function requireValues(array $values, array $keys, string $group): void
    {
        $missing = [];
        foreach ($keys as $key) {
            if (trim((string) ($values[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException($group . ' production configuration is incomplete: ' . implode(', ', $missing) . '.');
        }
    }
}
