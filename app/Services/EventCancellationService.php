<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\NotificationRepository;
use DomainException;
use Throwable;

final class EventCancellationService
{
    public function __construct(private readonly RefundService $refunds = new RefundService())
    {
    }

    public function request(int $eventId, int $organizerId, ?string $reason): int
    {
        $reason = $this->reason($reason) ?? 'No reason provided.';
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'SELECT id, status, start_datetime FROM events
                 WHERE id = :id AND organizer_id = :organizer_id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $eventId, 'organizer_id' => $organizerId]);
            $event = $statement->fetch();
            if (!is_array($event)) {
                throw new DomainException('Event was not found.');
            }
            if ($event['status'] !== 'approved' || $event['start_datetime'] <= gmdate('Y-m-d H:i:s')) {
                throw new DomainException('Only an approved future event can be submitted for cancellation.');
            }
            $pending = $database->prepare(
                "SELECT 1 FROM event_cancellation_requests WHERE event_id = :event_id AND status = 'pending' LIMIT 1"
            );
            $pending->execute(['event_id' => $eventId]);
            if ($pending->fetchColumn() !== false) {
                throw new DomainException('A cancellation request is already awaiting admin review.');
            }
            $database->prepare(
                "INSERT INTO event_cancellation_requests (event_id, requested_by, reason, status)
                 VALUES (:event_id, :requested_by, :reason, 'pending')"
            )->execute(['event_id' => $eventId, 'requested_by' => $organizerId, 'reason' => $reason]);
            $id = (int) $database->lastInsertId();
            $database->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function withdraw(int $requestId, int $organizerId): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE event_cancellation_requests ecr
             JOIN events e ON e.id = ecr.event_id
             SET ecr.status = 'withdrawn'
             WHERE ecr.id = :id AND ecr.requested_by = :organizer_id
               AND e.organizer_id = :owner_id AND ecr.status = 'pending'"
        );
        $statement->execute(['id' => $requestId, 'organizer_id' => $organizerId, 'owner_id' => $organizerId]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('The pending cancellation request could not be withdrawn.');
        }
    }

    public function review(int $requestId, int $adminId, string $decision, ?string $reviewNote): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Invalid cancellation review decision.');
        }
        $reviewNote = $this->reason($reviewNote);
        $database = Database::connection();
        $database->beginTransaction();
        $createdRefunds = [];
        try {
            $statement = $database->prepare(
                'SELECT ecr.*, e.status AS event_status, e.start_datetime, e.title,
                        e.organizer_id
                 FROM event_cancellation_requests ecr
                 JOIN events e ON e.id = ecr.event_id
                 WHERE ecr.id = :id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $requestId]);
            $request = $statement->fetch();
            if (!is_array($request) || $request['status'] !== 'pending') {
                throw new DomainException('This cancellation request is no longer pending.');
            }
            if ($decision === 'rejected') {
                $database->prepare(
                    "UPDATE event_cancellation_requests
                     SET status = 'rejected', reviewed_by = :admin_id,
                         reviewed_at = UTC_TIMESTAMP(6), review_note = :note
                     WHERE id = :id"
                )->execute(['admin_id' => $adminId, 'note' => $reviewNote, 'id' => $requestId]);
                (new NotificationRepository())->createForUser(
                    (int) $request['organizer_id'], 'event_cancellation_rejected',
                    'Event cancellation rejected',
                    'The cancellation request for ' . $request['title'] . ' was rejected.',
                    (int) $request['event_id'], null
                );
                $database->commit();
                return ['decision' => 'rejected', 'refunds_created' => 0, 'refunds_processed' => 0];
            }
            if ($request['event_status'] !== 'approved' || $request['start_datetime'] <= gmdate('Y-m-d H:i:s')) {
                throw new DomainException('The event is no longer eligible for cancellation approval.');
            }

            $database->prepare('SELECT id FROM ticket_types WHERE event_id = :event_id ORDER BY id FOR UPDATE')
                ->execute(['event_id' => $request['event_id']]);
            $bookingStatement = $database->prepare(
                'SELECT b.*, p.id AS payment_record_id, p.provider_payment_id,
                        p.status AS payment_status, p.amount AS payment_amount
                 FROM bookings b LEFT JOIN payments p ON p.booking_id = b.id
                 WHERE b.event_id = :event_id ORDER BY b.id FOR UPDATE'
            );
            $bookingStatement->execute(['event_id' => $request['event_id']]);
            $bookings = $bookingStatement->fetchAll();
            foreach ($bookings as $booking) {
                if ($booking['status'] === 'pending_payment') {
                    $this->cancelPendingBooking($booking);
                    (new NotificationRepository())->createForUser(
                        (int) $booking['customer_id'], 'event_cancelled', 'Event cancelled',
                        $request['title'] . ' was cancelled. Your pending reservation was released.',
                        (int) $request['event_id'], (int) $booking['id']
                    );
                    continue;
                }
                if (!in_array($booking['status'], ['confirmed', 'partially_cancelled'], true)) {
                    continue;
                }
                $refundId = $this->cancelConfirmedBooking(
                    $booking,
                    $requestId,
                    $adminId,
                    (string) $request['reason']
                );
                if ($refundId !== null) {
                    $createdRefunds[] = $refundId;
                }
                (new NotificationRepository())->createForUser(
                    (int) $booking['customer_id'], 'event_cancelled', 'Event cancelled',
                    $request['title'] . ' was cancelled. Any eligible paid tickets have been queued for a full refund.',
                    (int) $request['event_id'], (int) $booking['id']
                );
            }

            $database->prepare(
                "UPDATE events SET status = 'cancelled', cancelled_by = :admin_id,
                        cancelled_at = UTC_TIMESTAMP(6), cancellation_reason = :reason
                 WHERE id = :event_id"
            )->execute(['admin_id' => $adminId, 'reason' => $request['reason'], 'event_id' => $request['event_id']]);
            $database->prepare('UPDATE ticket_types SET is_active = 0 WHERE event_id = :event_id')
                ->execute(['event_id' => $request['event_id']]);
            $database->prepare(
                "INSERT INTO event_status_history (event_id, old_status, new_status, changed_by, reason)
                 VALUES (:event_id, 'approved', 'cancelled', :admin_id, :reason)"
            )->execute(['event_id' => $request['event_id'], 'admin_id' => $adminId, 'reason' => $request['reason']]);
            $database->prepare(
                "UPDATE event_cancellation_requests
                 SET status = 'approved', reviewed_by = :admin_id,
                     reviewed_at = UTC_TIMESTAMP(6), review_note = :note
                 WHERE id = :id"
            )->execute(['admin_id' => $adminId, 'note' => $reviewNote, 'id' => $requestId]);
            (new NotificationRepository())->createForUser(
                (int) $request['organizer_id'], 'event_cancellation_approved',
                'Event cancellation approved',
                $request['title'] . ' was cancelled and customer refunds were queued.',
                (int) $request['event_id'], null
            );
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $processed = 0;
        foreach (array_slice($createdRefunds, 0, (int) Config::get('cancellation.refund_batch_size', 10)) as $refundId) {
            $this->refunds->process($refundId);
            $processed++;
        }
        return ['decision' => 'approved', 'refunds_created' => count($createdRefunds), 'refunds_processed' => $processed];
    }

    private function cancelPendingBooking(array $booking): void
    {
        $database = Database::connection();
        $items = $database->prepare(
            'SELECT bi.ticket_type_id, bi.quantity FROM booking_items bi
             JOIN ticket_types tt ON tt.id = bi.ticket_type_id
             WHERE bi.booking_id = :booking_id ORDER BY bi.ticket_type_id FOR UPDATE'
        );
        $items->execute(['booking_id' => $booking['id']]);
        $release = $database->prepare(
            'UPDATE ticket_types SET reserved_quantity = reserved_quantity - :quantity
             WHERE id = :id AND reserved_quantity >= :minimum'
        );
        foreach ($items->fetchAll() as $item) {
            $release->execute(['quantity' => $item['quantity'], 'minimum' => $item['quantity'], 'id' => $item['ticket_type_id']]);
            if ($release->rowCount() !== 1) {
                throw new DomainException('Pending event inventory could not be released safely.');
            }
        }
        $database->prepare(
            "UPDATE bookings SET status = 'event_cancelled', reservation_expires_at = NULL,
                    cancelled_at = UTC_TIMESTAMP(6), cancellation_reason = 'Event cancelled'
             WHERE id = :id"
        )->execute(['id' => $booking['id']]);
        $database->prepare(
            "UPDATE payments SET status = 'failed', failure_description = 'Event cancelled before payment completion.'
             WHERE booking_id = :booking_id AND status IN ('created', 'authorized')"
        )->execute(['booking_id' => $booking['id']]);
    }

    private function cancelConfirmedBooking(array $booking, int $requestId, int $adminId, string $reason): ?int
    {
        $database = Database::connection();
        $statement = $database->prepare(
            "SELECT it.id, it.ticket_type_id, bi.unit_price
             FROM issued_tickets it
             JOIN booking_items bi ON bi.id = it.booking_item_id
             WHERE it.booking_id = :booking_id AND it.status = 'valid'
             ORDER BY it.id FOR UPDATE"
        );
        $statement->execute(['booking_id' => $booking['id']]);
        $tickets = $statement->fetchAll();
        if ($tickets === []) {
            return null;
        }
        $refundPaise = array_sum(array_map(fn (array $ticket): int => $this->toPaise((string) $ticket['unit_price']), $tickets));
        $refundId = null;
        if ($refundPaise > 0) {
            if (!in_array($booking['payment_status'], ['captured', 'partially_refunded'], true)
                || trim((string) ($booking['provider_payment_id'] ?? '')) === '') {
                throw new DomainException('A paid booking does not have a captured payment available for refund.');
            }
            $reference = $this->refundReference();
            $database->prepare(
                "INSERT INTO refunds
                    (booking_id, payment_id, refund_reference, provider_refund_id, idempotency_key,
                     amount, refund_percentage, source, status, reason, initiated_by,
                     event_cancellation_request_id)
                 VALUES
                    (:booking_id, :payment_id, :reference, NULL, :idempotency_key,
                     :amount, 100, 'event', 'pending', :reason, :admin_id, :request_id)"
            )->execute([
                'booking_id' => $booking['id'], 'payment_id' => $booking['payment_record_id'],
                'reference' => $reference, 'idempotency_key' => 'ebs-refund-' . bin2hex(random_bytes(16)),
                'amount' => $this->fromPaise($refundPaise), 'reason' => $reason,
                'admin_id' => $adminId, 'request_id' => $requestId,
            ]);
            $refundId = (int) $database->lastInsertId();
        }
        $cancel = $database->prepare(
            "UPDATE issued_tickets SET status = 'cancelled', active_seat_slot = NULL
             WHERE id = :id AND status = 'valid'"
        );
        $audit = $database->prepare(
            "INSERT INTO ticket_cancellations
                (booking_id, issued_ticket_id, refund_id, event_cancellation_request_id,
                 source, cancelled_by, reason, gross_amount, refund_percentage, refund_amount)
             VALUES
                (:booking_id, :ticket_id, :refund_id, :request_id,
                 'event', :admin_id, :reason, :gross, 100, :refund)"
        );
        $inventory = [];
        foreach ($tickets as $ticket) {
            $cancel->execute(['id' => $ticket['id']]);
            if ($cancel->rowCount() !== 1) {
                throw new DomainException('Event ticket cancellation conflicted with another update.');
            }
            $audit->execute([
                'booking_id' => $booking['id'], 'ticket_id' => $ticket['id'], 'refund_id' => $refundId,
                'request_id' => $requestId, 'admin_id' => $adminId, 'reason' => $reason,
                'gross' => $ticket['unit_price'], 'refund' => $ticket['unit_price'],
            ]);
            $typeId = (int) $ticket['ticket_type_id'];
            $inventory[$typeId] = ($inventory[$typeId] ?? 0) + 1;
        }
        $release = $database->prepare(
            'UPDATE ticket_types SET sold_quantity = sold_quantity - :quantity
             WHERE id = :id AND sold_quantity >= :minimum'
        );
        foreach ($inventory as $typeId => $quantity) {
            $release->execute(['quantity' => $quantity, 'minimum' => $quantity, 'id' => $typeId]);
            if ($release->rowCount() !== 1) {
                throw new DomainException('Event ticket inventory could not be restored safely.');
            }
        }
        $database->prepare(
            "UPDATE bookings SET status = 'event_cancelled', cancelled_at = UTC_TIMESTAMP(6),
                    cancellation_reason = :reason WHERE id = :id"
        )->execute(['reason' => $reason, 'id' => $booking['id']]);
        return $refundId;
    }

    private function reason(?string $reason): ?string
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return null;
        }
        if (mb_strlen($reason) > (int) Config::get('cancellation.reason_max_length', 1000)) {
            throw new DomainException('The reason must be 1000 characters or fewer.');
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
