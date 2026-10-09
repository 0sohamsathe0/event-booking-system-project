<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $values = [];

    private function __construct()
    {
    }

    public static function set(string $key, array $value): void
    {
        self::$values[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$values;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function envBool(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    public static function envInt(string $key, int $default): int
    {
        $value = getenv($key);

        return $value !== false && preg_match('/^-?[0-9]+$/', $value) ? (int) $value : $default;
    }
}
