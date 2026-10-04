<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const SENSITIVE_KEYS = [
        'password', 'secret', 'signature', 'cookie', 'session', 'token',
        'payload', 'authorization', 'database_url', 'dsn',
    ];

    private function __construct()
    {
    }

    public static function error(string $event, array $context = []): void
    {
        $record = [
            'level' => 'error',
            'event' => preg_replace('/[^a-z0-9._-]/i', '_', $event),
            'time' => gmdate(DATE_ATOM),
            'context' => self::sanitize($context),
        ];

        try {
            error_log(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable) {
            error_log('[application.error] ' . $record['event']);
        }
    }

    private static function sanitize(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            if (self::isSensitive($normalized)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            if ($value instanceof \Throwable) {
                $safe[$key] = ['class' => $value::class, 'code' => $value->getCode()];
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
            } else {
                $safe[$key] = '[non-scalar]';
            }
        }

        return $safe;
    }

    private static function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
