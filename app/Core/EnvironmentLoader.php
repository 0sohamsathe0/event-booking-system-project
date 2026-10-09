<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class EnvironmentLoader
{
    private function __construct()
    {
    }

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        self::loadLines($lines);
    }

    public static function loadLines(array $lines): void
    {
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $line = trim((string) $lines[$index]);
            if ($index === 0) {
                $line = ltrim($line, "\xEF\xBB\xBF");
            }
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match)) {
                throw new RuntimeException('Invalid environment entry on line ' . ($index + 1) . '.');
            }

            $key = $match[1];
            $value = trim($match[2]);
            if ($key === 'DB_SSL_CA' && str_starts_with($value, '-----BEGIN CERTIFICATE-----')) {
                $pem = [$value];
                while (!str_contains(end($pem), '-----END CERTIFICATE-----')) {
                    $index++;
                    if ($index >= $count) {
                        throw new RuntimeException('The DB_SSL_CA certificate is incomplete.');
                    }
                    $pem[] = trim((string) $lines[$index]);
                }
                $value = implode("\n", $pem) . "\n";
            } else {
                $value = self::unquote($value);
            }

            if (getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
            if ($first === '"') {
                $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
            }
        }

        return $value;
    }
}
