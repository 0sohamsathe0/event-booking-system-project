<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;
use Throwable;

final class SafeDiagnostics
{
    private function __construct()
    {
    }

    public static function enabled(): bool
    {
        return filter_var(getenv('APP_SAFE_DIAGNOSTICS') ?: false, FILTER_VALIDATE_BOOL);
    }

    public static function unavailable(Throwable $exception): string
    {
        return self::enabled()
            ? 'unavailable: ' . self::category($exception)
            : 'unavailable';
    }

    public static function category(Throwable $exception): string
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof PDOException) {
                return self::databaseCategory($current);
            }
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'database') && str_contains($message, 'configuration')) {
            return 'configuration_incomplete';
        }
        if (str_contains($message, 'app_url')
            || str_contains($message, 'session_driver')
            || str_contains($message, 'poster_storage_driver')
            || str_contains($message, 'log_channel')
            || str_contains($message, 'db_ssl_mode')) {
            return 'configuration_invalid';
        }
        if (str_contains($message, 'razorpay') || str_contains($message, 'cloudinary')) {
            return 'integration_configuration_incomplete';
        }
        if (str_contains($message, 'session')) {
            return 'session_initialization_failed';
        }
        if (str_contains($message, 'connect to the database')) {
            return 'database_connection_failed';
        }

        return 'application_initialization_failed';
    }

    private static function databaseCategory(PDOException $exception): string
    {
        $driverCode = is_array($exception->errorInfo ?? null)
            ? (int) ($exception->errorInfo[1] ?? 0)
            : 0;

        return match ($driverCode) {
            1044, 1045 => 'database_authentication_failed',
            1054, 1146 => 'database_schema_missing',
            1205, 1213 => 'database_temporarily_busy',
            2002, 2003, 2005, 2006, 2013 => 'database_connection_failed',
            2026 => 'database_tls_failed',
            default => 'database_query_failed',
        };
    }
}
