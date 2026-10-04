<?php

declare(strict_types=1);

use App\Core\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    return $directory === '/' ? '' : rtrim($directory, '/');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
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
