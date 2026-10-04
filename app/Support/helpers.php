<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Config;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $configured = Config::get('app.base_path');
    if (is_string($configured)) {
        return $configured === '' ? '' : '/' . trim($configured, '/');
    }

    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    return $directory === '/' ? '' : rtrim($directory, '/');
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return base_url() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $appUrl = rtrim((string) Config::get('app.url', ''), '/');

    return $appUrl !== '' ? $appUrl . url($path) : url($path);
}

function poster_url(mixed $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https://#i', $path)) {
        return $path;
    }
    if (Config::get('app.environment') === 'production' && str_starts_with($path, 'uploads/events/')) {
        return null;
    }

    return url($path);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function local_datetime(string $utcValue, string $format = 'd M Y, h:i A'): string
{
    return (new DateTimeImmutable($utcValue, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Kolkata'))->format($format);
}

function money(mixed $amount): string
{
    return (float) $amount === 0.0 ? 'Free' : '₹' . number_format((float) $amount, 2);
}
