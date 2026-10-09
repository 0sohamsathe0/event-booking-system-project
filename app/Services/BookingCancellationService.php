<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\NotificationRepository;
use DomainException;
use PDOException;
use Throwable;

final class BookingCancellationService
{
    public function __construct(
        private readonly CancellationPolicy $policy = new CancellationPolicy(),
        private readonly RefundService $refunds = new RefundService()
    ) {
    }

    public function cancel(
        int $bookingId,
        int $customerId,
        array $submittedQuantities,
        ?string $reason,
        string $requestToken
    ): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $requestToken)) {
            throw new DomainException('The cancellation request is invalid. Refresh the booking and try again.');
        }
        $quantities = $this->normalizeQuantities($submittedQuantities);
        if (array_sum($quantities) < 1) {
            throw new DomainException('Choose at least one ticket to cancel.');
        }
        $reason = $this->normalizeReason($reason);
        $database = Database::connection();
        $database->beginTransaction();
        $refundId = null;
        $actionId = null;
        try {
            try {
                $database->prepare(
                    "INSERT INTO booking_cancellation_actions
                        (request_token, booking_id, customer_id, status)
                     VALUES (:token, :booking_id, :customer_id, 'processing')"
                )->execute(['token' => $requestToken, 'booking_id' => $bookingId, 'customer_id' => $customerId]);
                $actionId = (int) $database->lastInsertId();
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $database->rollBack();
                    return $this->completedAction($requestToken, $bookingId, $customerId);
                }
                throw $exception;
            }
            $statement = $database->prepare(
                'SELECT b.*, e.start_datetime, e.title AS event_title,
                        p.id AS payment_record_id, p.provider_payment_id, p.status AS payment_status,
                        p.amount AS payment_amount
                 FROM bookings b
                 JOIN events e ON e.id = b.event_id
                 LEFT JOIN payments p ON p.booking_id = b.id
                 WHERE b.id = :id AND b.customer_id = :customer_id
                 LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $bookingId, 'customer_id' => $customerId]);
            $booking = $statement->fetch();
            if (!is_array($booking)) {
                throw new DomainException('Booking was not found.');
            }
            if (!in_array($booking['status'], ['confirmed', 'partially_cancelled'], true)) {
                throw new DomainException('Only confirmed tickets can be cancelled.');
            }
            $percentage = $this->policy->customerRefundPercentage((string) $booking['start_datetime']);

            $ticketStatement = $database->prepare(
                "SELECT it.id, it.ticket_type_id, it.seat_number, bi.id AS booking_item_id, bi.unit_price
                 FROM issued_tickets it
                 JOIN booking_items bi ON bi.id = it.booking_item_id
                 JOIN ticket_types tt ON tt.id = it.ticket_type_id
                 WHERE it.booking_id = :booking_id AND it.status = 'valid'
                 ORDER BY it.ticket_type_id, it.seat_number DESC
                 FOR UPDATE"
            );
            $ticketStatement->execute(['booking_id' => $bookingId]);
            $availableByType = [];
            foreach ($ticketStatement->fetchAll() as $ticket) {
                $availableByType[(int) $ticket['ticket_type_id']][] = $ticket;
            }

            $selected = [];
            foreach ($quantities as $ticketTypeId => $quantity) {
                if (!isset($availableByType[$ticketTypeId]) || count($availableByType[$ticketTypeId]) < $quantity) {
                    throw new DomainException('One or more ticket quantities are no longer available for cancellation.');
                }
                $selected = array_merge($selected, array_slice($availableByType[$ticketTypeId], 0, $quantity));
            }

            $grossPaise = 0;
            $refundPaise = 0;
            $values = [];
            foreach ($selected as $ticket) {
                $ticketGross = $this->toPaise((string) $ticket['unit_price']);
                $ticketRefund = $this->policy->refundPaise($ticketGross, $percentage);
                $grossPaise += $ticketGross;
                $refundPaise += $ticketRefund;
                $values[(int) $ticket['id']] = [$ticketGross, $ticketRefund];
            }

            $isPaid = $grossPaise > 0;
            if ($isPaid) {
                if ($refundPaise < 100) {
                    throw new DomainException('The selected refund is below Razorpay’s INR 1.00 minimum. Select more tickets or contact support.');
                }
                if (!in_array($booking['payment_status'], ['captured', 'partially_refunded'], true)
                    || trim((string) ($booking['provider_payment_id'] ?? '')) === '') {
                    throw new DomainException('The captured payment is not available for an automatic refund.');
                }
                $reservedStatement = $database->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM refunds
                     WHERE payment_id = :payment_id AND status IN ('pending', 'processing', 'processed', 'failed')"
                );
                $reservedStatement->execute(['payment_id' => $booking['payment_record_id']]);
                $reservedPaise = $this->toPaise((string) $reservedStatement->fetchColumn());
                if ($reservedPaise + $refundPaise > $this->toPaise((string) $booking['payment_amount'])) {
                    throw new DomainException('The refund would exceed the captured payment amount.');
                }

                $reference = $this->refundReference();
                $insertRefund = $database->prepare(
                    "INSERT INTO refunds
                        (booking_id, payment_id, refund_reference, idempotency_key, amount,
                         refund_percentage, source, status, reason, initiated_by)
                     VALUES
                        (:booking_id, :payment_id, :reference, :idempotency_key, :amount,
                         :percentage, 'customer', 'pending', :reason, :initiated_by)"
                );
                $insertRefund->execute([
                    'booking_id' => $bookingId,
                    'payment_id' => $booking['payment_record_id'],
                    'reference' => $reference,
                    'idempotency_key' => 'ebs-refund-' . bin2hex(random_bytes(16)),
                    'amount' => $this->fromPaise($refundPaise),
                    'percentage' => $percentage,
                    'reason' => $reason ?? 'Customer ticket cancellation',
                    'initiated_by' => $customerId,
                ]);
                $refundId = (int) $database->lastInsertId();
            }

            $cancelTicket = $database->prepare(
                "UPDATE issued_tickets SET status = 'cancelled', active_seat_slot = NULL
                 WHERE id = :id AND status = 'valid' AND active_seat_slot = 1"
            );
            $insertCancellation = $database->prepare(
                "INSERT INTO ticket_cancellations
                    (booking_id, issued_ticket_id, refund_id, source, cancelled_by, reason,
                     gross_amount, refund_percentage, refund_amount)
                 VALUES
                    (:booking_id, :ticket_id, :refund_id, 'customer', :cancelled_by, :reason,
                     :gross_amount, :percentage, :refund_amount)"
            );
            $inventoryByType = [];
            foreach ($selected as $ticket) {
                $cancelTicket->execute(['id' => $ticket['id']]);
                if ($cancelTicket->rowCount() !== 1) {
                    throw new DomainException('A selected ticket was already cancelled or used.');
                }
                [$ticketGross, $ticketRefund] = $values[(int) $ticket['id']];
                $insertCancellation->execute([
                    'booking_id' => $bookingId,
                    'ticket_id' => $ticket['id'],
                    'refund_id' => $refundId,
                    'cancelled_by' => $customerId,
                    'reason' => $reason,
                    'gross_amount' => $this->fromPaise($ticketGross),
                    'percentage' => $percentage,
                    'refund_amount' => $this->fromPaise($ticketRefund),
                ]);
                $typeId = (int) $ticket['ticket_type_id'];
                $inventoryByType[$typeId] = ($inventoryByType[$typeId] ?? 0) + 1;
            }

            $release = $database->prepare(
                'UPDATE ticket_types SET sold_quantity = sold_quantity - :quantity
                 WHERE id = :id AND sold_quantity >= :minimum'
            );
            foreach ($inventoryByType as $typeId => $quantity) {
                $release->execute(['quantity' => $quantity, 'minimum' => $quantity, 'id' => $typeId]);
                if ($release->rowCount() !== 1) {
                    throw new DomainException('Cancelled ticket inventory could not be restored safely.');
                }
            }

            $remainingStatement = $database->prepare(
                "SELECT COUNT(*) FROM issued_tickets WHERE booking_id = :booking_id AND status = 'valid'"
            );
            $remainingStatement->execute(['booking_id' => $bookingId]);
            $remaining = (int) $remainingStatement->fetchColumn();
            $status = $remaining === 0 ? 'customer_cancelled' : 'partially_cancelled';
            $database->prepare(
                'UPDATE bookings SET status = :status,
                        cancelled_at = CASE WHEN :cancelled_status = \'customer_cancelled\' THEN UTC_TIMESTAMP(6) ELSE cancelled_at END,
                        cancellation_reason = CASE WHEN :reason_status = \'customer_cancelled\' THEN :reason ELSE cancellation_reason END
                 WHERE id = :id'
            )->execute([
                'status' => $status,
                'cancelled_status' => $status,
                'reason_status' => $status,
                'reason' => $reason ?? 'Customer ticket cancellation',
                'id' => $bookingId,
            ]);
            (new NotificationRepository())->createForUser(
                $customerId,
                'tickets_cancelled',
                'Tickets cancelled',
                count($selected) . ' ticket(s) for ' . $booking['event_title'] . ' were cancelled.',
                (int) $booking['event_id'],
                $bookingId
            );
            $database->prepare(
                "UPDATE booking_cancellation_actions
                 SET refund_id = :refund_id, cancelled_quantity = :quantity,
                     refund_amount = :refund_amount, refund_percentage = :percentage,
                     refund_status = :refund_status, status = 'completed'
                 WHERE id = :id"
            )->execute([
                'refund_id' => $refundId,
                'quantity' => count($selected),
                'refund_amount' => $this->fromPaise($refundPaise),
                'percentage' => $percentage,
                'refund_status' => $refundId === null ? 'not_required' : 'pending',
                'id' => $actionId,
            ]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $refundStatus = $refundId === null ? 'not_required' : $this->refunds->process($refundId);
        try {
            Database::connection()->prepare(
                'UPDATE booking_cancellation_actions SET refund_status = :refund_status WHERE id = :id'
            )->execute(['refund_status' => $refundStatus, 'id' => $actionId]);
        } catch (Throwable $exception) {
            Logger::error('booking_cancellation.action_status_write_failed', [
                'booking_id' => $bookingId, 'action_id' => $actionId, 'exception' => $exception,
            ]);
        }
        return [
            'cancelled_quantity' => count($selected),
            'refund_amount' => $this->fromPaise($refundPaise),
            'refund_percentage' => $percentage,
            'refund_status' => $refundStatus,
            'duplicate' => false,
        ];
    }

    private function completedAction(string $token, int $bookingId, int $customerId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT bca.cancelled_quantity, bca.refund_amount, bca.refund_percentage,
                    COALESCE(r.status, bca.refund_status) AS refund_status, bca.status
             FROM booking_cancellation_actions bca
             LEFT JOIN refunds r ON r.id = bca.refund_id
             WHERE bca.request_token = :token AND bca.booking_id = :booking_id AND bca.customer_id = :customer_id
             LIMIT 1"
        );
        $statement->execute(['token' => $token, 'booking_id' => $bookingId, 'customer_id' => $customerId]);
        $action = $statement->fetch();
        if (!is_array($action) || $action['status'] !== 'completed') {
            throw new DomainException('This cancellation request is already being processed.');
        }
        return [
            'cancelled_quantity' => (int) $action['cancelled_quantity'],
            'refund_amount' => (string) $action['refund_amount'],
            'refund_percentage' => (int) $action['refund_percentage'],
            'refund_status' => (string) $action['refund_status'],
            'duplicate' => true,
        ];
    }

    private function normalizeQuantities(array $submitted): array
    {
        $normalized = [];
        foreach ($submitted as $id => $quantity) {
            if (!ctype_digit((string) $id) || !is_scalar($quantity)) {
                throw new DomainException('Invalid cancellation selection.');
            }
            $value = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($value === false || $value < 0 || $value > 10) {
                throw new DomainException('Cancellation quantities must be whole numbers between 0 and 10.');
            }
            if ($value > 0) {
                $normalized[(int) $id] = $value;
            }
        }
        ksort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    private function normalizeReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return null;
        }
        if (mb_strlen($reason) > (int) Config::get('cancellation.reason_max_length', 1000)) {
            throw new DomainException('The cancellation reason must be 1000 characters or fewer.');
        }
        return $reason;
    }

    private function refundReference(): string
    {
        return 'RFD-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function toPaise(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromPaise(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
