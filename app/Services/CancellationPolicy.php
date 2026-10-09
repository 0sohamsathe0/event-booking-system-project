<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class CancellationPolicy
{
    public function customerRefundPercentage(string $eventStartUtc, ?DateTimeImmutable $now = null): int
    {
        $utc = new DateTimeZone('UTC');
        $now ??= new DateTimeImmutable('now', $utc);
        $start = new DateTimeImmutable($eventStartUtc, $utc);
        $seconds = $start->getTimestamp() - $now->getTimestamp();
        $cutoff = (int) Config::get('cancellation.customer_cutoff_hours', 48) * 3600;
        if ($seconds < $cutoff) {
            throw new DomainException('Tickets can only be cancelled at least 48 hours before the event starts.');
        }

        $fullRefund = (int) Config::get('cancellation.full_refund_hours', 168) * 3600;
        return $seconds >= $fullRefund
            ? (int) Config::get('cancellation.full_refund_percentage', 100)
            : (int) Config::get('cancellation.late_refund_percentage', 50);
    }

    public function refundPaise(int $grossPaise, int $percentage): int
    {
        if ($grossPaise < 0 || $percentage < 0 || $percentage > 100) {
            throw new DomainException('The cancellation refund could not be calculated.');
        }

        return intdiv(($grossPaise * $percentage) + 50, 100);
    }
}
