<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use DateTimeImmutable;
use DateTimeZone;

trait ParsesManagementFilters
{
    private function managementFilters(bool $admin): array
    {
        $errors = [];
        $search = trim($this->queryValue('q'));
        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
            $errors[] = 'Search text was limited to 100 characters.';
        }

        $statuses = ['any', 'pending_payment', 'confirmed', 'payment_failed', 'expired', 'customer_cancelled', 'event_cancelled'];
        $paymentStatuses = ['any', 'not_required', 'not_started', 'created', 'authorized', 'captured', 'failed', 'refunded'];
        $sorts = ['newest', 'oldest', 'event_soonest', 'amount_high', 'amount_low'];
        $status = $this->allowedQuery('status', $statuses, 'any', $errors, 'The booking status filter was reset.');
        $paymentStatus = $this->allowedQuery('payment_status', $paymentStatuses, 'any', $errors, 'The payment status filter was reset.');
        $sort = $this->allowedQuery('sort', $sorts, 'newest', $errors, 'The sort order was reset.');

        $dateFrom = trim($this->queryValue('from'));
        $dateTo = trim($this->queryValue('to'));
        $from = $this->localDate($dateFrom);
        $to = $this->localDate($dateTo);
        if ($dateFrom !== '' && $from === null) {
            $dateFrom = '';
            $errors[] = 'Enter a valid booking start date.';
        }
        if ($dateTo !== '' && $to === null) {
            $dateTo = '';
            $errors[] = 'Enter a valid booking end date.';
        }
        if ($from !== null && $to !== null && $from > $to) {
            $dateFrom = $dateTo = '';
            $from = $to = null;
            $errors[] = 'The booking start date must be on or before the end date.';
        }

        $utc = new DateTimeZone('UTC');
        return [[
            'search' => $search,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'event_id' => $this->positiveQueryId('event', $errors, 'The event filter was reset.'),
            'organizer_id' => $admin ? $this->positiveQueryId('organizer', $errors, 'The organizer filter was reset.') : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_utc' => $from?->setTimezone($utc)->format('Y-m-d H:i:s'),
            'date_to_utc' => $to?->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
            'sort' => $sort,
        ], $errors];
    }

    private function customerFilters(): array
    {
        $errors = [];
        $search = trim($this->queryValue('q'));
        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
            $errors[] = 'Search text was limited to 100 characters.';
        }
        $status = $this->allowedQuery(
            'status',
            ['any', 'approved', 'pending', 'rejected', 'disabled'],
            'any',
            $errors,
            'The account status filter was reset.'
        );
        return [['search' => $search, 'account_status' => $status], $errors];
    }

    private function pageFromQuery(): int
    {
        $page = filter_var($this->queryValue('page', '1'), FILTER_VALIDATE_INT);
        return $page === false ? 1 : max(1, min(100000, $page));
    }

    private function allowedQuery(string $key, array $allowed, string $default, array &$errors, string $message): string
    {
        $value = $this->queryValue($key, $default);
        if (!in_array($value, $allowed, true)) {
            $errors[] = $message;
            return $default;
        }
        return $value;
    }

    private function positiveQueryId(string $key, array &$errors, string $message): ?int
    {
        $value = $this->queryValue($key);
        if ($value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $errors[] = $message;
            return null;
        }
        return $id;
    }

    private function localDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }
        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function queryValue(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}
