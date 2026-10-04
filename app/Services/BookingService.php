<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\NotificationRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use Throwable;

final class BookingService
{
    private const MAX_QUANTITY = 10;

    public function __construct(private readonly RazorpayService $razorpay = new RazorpayService())
    {
    }

    public function reserve(int $customerId, int $eventId, array $submittedTickets): array
    {
        $quantities = $this->normalizeQuantities($submittedTickets);
        $totalQuantity = array_sum($quantities);
        if ($totalQuantity < 1 || $totalQuantity > self::MAX_QUANTITY) {
            throw new DomainException('Choose between 1 and 10 tickets in total.');
        }

        $this->expireStaleReservations();
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $eventStatement = $database->prepare('SELECT * FROM events WHERE id = :id LIMIT 1 FOR UPDATE');
            $eventStatement->execute(['id' => $eventId]);
            $event = $eventStatement->fetch();
            $now = $this->utcNow();
            if (!is_array($event) || $event['status'] !== 'approved') {
                throw new DomainException('This event is not available for booking.');
            }
            if ($now < $event['sale_start_datetime'] || $now > $event['sale_end_datetime'] || $now >= $event['start_datetime']) {
                throw new DomainException('Ticket sales are not currently open for this event.');
            }

            $tickets = $this->lockTickets($eventId, array_keys($quantities));
            if (count($tickets) !== count($quantities)) {
                throw new DomainException('One or more selected ticket types are invalid.');
            }

            $totalPaise = 0;
            foreach ($tickets as $ticket) {
                $quantity = $quantities[(int) $ticket['id']];
                $available = (int) $ticket['capacity'] - (int) $ticket['reserved_quantity'] - (int) $ticket['sold_quantity'];
                if (!(int) $ticket['is_active'] || $quantity > $available) {
                    throw new DomainException($ticket['name'] . ' tickets are no longer available in the requested quantity.');
                }
                $totalPaise += $this->toPaise((string) $ticket['price']) * $quantity;
            }

            $reference = $this->uniqueReference();
            $minutes = $this->reservationMinutes();
            $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s.u');
            $bookingStatement = $database->prepare(
                "INSERT INTO bookings
                    (booking_reference, customer_id, event_id, status, total_quantity,
                     total_amount, reservation_expires_at)
                 VALUES (:reference, :customer_id, :event_id, 'pending_payment',
                         :total_quantity, :total_amount, :expires)"
            );
            $bookingStatement->execute([
                'reference' => $reference, 'customer_id' => $customerId, 'event_id' => $eventId,
                'total_quantity' => $totalQuantity, 'total_amount' => $this->fromPaise($totalPaise),
                'expires' => $expires,
            ]);
            $bookingId = (int) $database->lastInsertId();

            $itemStatement = $database->prepare(
                'INSERT INTO booking_items (booking_id, event_id, ticket_type_id, quantity, unit_price)
                 VALUES (:booking_id, :event_id, :ticket_id, :quantity, :unit_price)'
            );
            $reserveStatement = $database->prepare(
                'UPDATE ticket_types SET reserved_quantity = reserved_quantity + :quantity WHERE id = :id'
            );
            foreach ($tickets as $ticket) {
                $quantity = $quantities[(int) $ticket['id']];
                $itemStatement->execute([
                    'booking_id' => $bookingId, 'event_id' => $eventId, 'ticket_id' => $ticket['id'],
                    'quantity' => $quantity, 'unit_price' => $ticket['price'],
                ]);
                $reserveStatement->execute(['quantity' => $quantity, 'id' => $ticket['id']]);
            }

            if ($totalPaise === 0) {
                $this->convertReservationToSale($bookingId, $customerId);
                $database->commit();
                return ['booking_id' => $bookingId, 'free' => true];
            }

            $paymentStatement = $database->prepare(
                "INSERT INTO payments (booking_id, amount, currency, status)
                 VALUES (:booking_id, :amount, 'INR', 'created')"
            );
            $paymentStatement->execute(['booking_id' => $bookingId, 'amount' => $this->fromPaise($totalPaise)]);
            $paymentId = (int) $database->lastInsertId();
            $database->commit();

            try {
                $order = $this->razorpay->createOrder($totalPaise, $reference);
                $statement = $database->prepare(
                    'UPDATE payments SET provider_order_id = :order_id WHERE id = :id AND status = \'created\''
                );
                $statement->execute(['order_id' => $order['id'], 'id' => $paymentId]);
            } catch (Throwable $exception) {
                error_log(sprintf(
                    '[booking.payment_order_failed] booking_id=%d customer_id=%d error=%s',
                    $bookingId,
                    $customerId,
                    $exception->getMessage()
                ));

                try {
                    $this->failAndRelease($bookingId, $customerId, 'Payment order could not be created.');
                } catch (Throwable $releaseException) {
                    error_log(sprintf(
                        '[booking.reservation_release_failed] booking_id=%d customer_id=%d error=%s',
                        $bookingId,
                        $customerId,
                        $releaseException->getMessage()
                    ));
                    throw $releaseException;
                }

                throw new DomainException(
                    'Payment checkout is temporarily unavailable. Your tickets were not held or charged. Please try again later.',
                    0,
                    $exception
                );
            }

            return ['booking_id' => $bookingId, 'free' => false];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function confirm(int $bookingId, int $customerId, string $orderId, string $paymentId, string $signature): void
    {
        $database = Database::connection();
        $statement = $database->prepare(
            'SELECT b.total_amount, b.status, p.provider_order_id
             FROM bookings b JOIN payments p ON p.booking_id = b.id
             WHERE b.id = :id AND b.customer_id = :customer_id LIMIT 1'
        );
        $statement->execute(['id' => $bookingId, 'customer_id' => $customerId]);
        $record = $statement->fetch();
        if (!is_array($record) || !hash_equals((string) $record['provider_order_id'], $orderId)) {
            throw new DomainException('The payment order does not match this booking.');
        }
        if (!$this->razorpay->verifyPaymentSignature((string) $record['provider_order_id'], $paymentId, $signature)) {
            throw new DomainException('Payment verification failed.');
        }

        $remote = $this->razorpay->fetchPayment($paymentId);
        $expectedPaise = $this->toPaise((string) $record['total_amount']);
        if (($remote['order_id'] ?? '') !== $orderId
            || (int) ($remote['amount'] ?? -1) !== $expectedPaise
            || ($remote['currency'] ?? '') !== 'INR'
            || ($remote['status'] ?? '') !== 'captured') {
            throw new DomainException('Payment has not been captured correctly. Please contact support if money was deducted.');
        }

        $database->beginTransaction();
        try {
            $booking = $this->lockBooking($bookingId, $customerId);
            if ($booking['status'] === 'confirmed') {
                $database->commit();
                return;
            }
            if ($booking['status'] !== 'pending_payment' || $booking['reservation_expires_at'] < $this->utcNow()) {
                throw new DomainException('The ticket reservation has expired. Please contact support if money was deducted.');
            }
            $event = $database->prepare('SELECT status FROM events WHERE id = :id FOR UPDATE');
            $event->execute(['id' => $booking['event_id']]);
            if ($event->fetchColumn() !== 'approved') {
                throw new DomainException('The event is no longer available. Please contact support if money was deducted.');
            }
            $this->lockBookingTickets($bookingId);
            $this->convertReservationToSale($bookingId, $customerId);
            $payment = $database->prepare(
                "UPDATE payments SET provider_payment_id = :payment_id, status = 'captured',
                        signature_verified_at = UTC_TIMESTAMP(6), captured_at = UTC_TIMESTAMP(6)
                 WHERE booking_id = :booking_id AND provider_order_id = :order_id"
            );
            $payment->execute(['payment_id' => $paymentId, 'booking_id' => $bookingId, 'order_id' => $orderId]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function failAndRelease(int $bookingId, int $customerId, string $reason): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $booking = $this->lockBooking($bookingId, $customerId);
            if ($booking['status'] !== 'pending_payment') {
                $database->commit();
                return;
            }
            $items = $this->lockBookingTickets($bookingId);
            $release = $database->prepare(
                'UPDATE ticket_types SET reserved_quantity = reserved_quantity - :release_quantity
                 WHERE id = :id AND reserved_quantity >= :minimum_quantity'
            );
            foreach ($items as $item) {
                $release->execute([
                    'release_quantity' => $item['quantity'],
                    'minimum_quantity' => $item['quantity'],
                    'id' => $item['ticket_type_id'],
                ]);
                if ($release->rowCount() !== 1) {
                    throw new DomainException('Reserved ticket inventory could not be released safely.');
                }
            }
            $database->prepare(
                "UPDATE bookings SET status = 'payment_failed', reservation_expires_at = NULL WHERE id = :id"
            )->execute(['id' => $bookingId]);
            $database->prepare(
                "UPDATE payments SET status = 'failed', failure_description = :reason
                 WHERE booking_id = :booking_id AND status = 'created'"
            )->execute(['reason' => mb_substr($reason, 0, 500), 'booking_id' => $bookingId]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function razorpay(): RazorpayService
    {
        return $this->razorpay;
    }

    private function normalizeQuantities(array $submitted): array
    {
        $normalized = [];
        foreach ($submitted as $id => $quantity) {
            if (!ctype_digit((string) $id) || !is_scalar($quantity)) {
                throw new DomainException('Invalid ticket selection.');
            }
            $value = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($value === false || $value < 0 || $value > self::MAX_QUANTITY) {
                throw new DomainException('Ticket quantities must be whole numbers between 0 and 10.');
            }
            if ($value > 0) {
                $normalized[(int) $id] = $value;
            }
        }
        ksort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    private function lockTickets(int $eventId, array $ticketIds): array
    {
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT * FROM ticket_types WHERE event_id = ? AND id IN ($placeholders) ORDER BY id FOR UPDATE"
        );
        $statement->execute(array_merge([$eventId], $ticketIds));
        return $statement->fetchAll();
    }

    private function lockBooking(int $bookingId, int $customerId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM bookings WHERE id = :id AND customer_id = :customer_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $bookingId, 'customer_id' => $customerId]);
        $booking = $statement->fetch();
        if (!is_array($booking)) {
            throw new DomainException('Booking was not found.');
        }
        return $booking;
    }

    private function lockBookingTickets(int $bookingId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT bi.ticket_type_id, bi.quantity FROM booking_items bi
             JOIN ticket_types tt ON tt.id = bi.ticket_type_id
             WHERE bi.booking_id = :booking_id ORDER BY bi.ticket_type_id FOR UPDATE'
        );
        $statement->execute(['booking_id' => $bookingId]);
        return $statement->fetchAll();
    }

    private function convertReservationToSale(int $bookingId, int $customerId): void
    {
        $database = Database::connection();
        $items = $this->lockBookingTickets($bookingId);
        $update = $database->prepare(
            'UPDATE ticket_types SET reserved_quantity = reserved_quantity - :release_quantity,
                    sold_quantity = sold_quantity + :sell_quantity
             WHERE id = :id AND reserved_quantity >= :minimum_quantity'
        );
        foreach ($items as $item) {
            $update->execute([
                'release_quantity' => $item['quantity'],
                'sell_quantity' => $item['quantity'],
                'minimum_quantity' => $item['quantity'],
                'id' => $item['ticket_type_id'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('Ticket inventory could not be finalized.');
            }
        }
        $database->prepare(
            "UPDATE bookings SET status = 'confirmed', confirmed_at = UTC_TIMESTAMP(6),
                    reservation_expires_at = NULL WHERE id = :id AND status = 'pending_payment'"
        )->execute(['id' => $bookingId]);
        (new NotificationRepository())->createForUser(
            $customerId, 'booking_confirmed', 'Booking confirmed',
            'Your booking has been confirmed successfully.',
            null,
            $bookingId
        );
    }

    private function expireStaleReservations(): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->query(
                "SELECT id, customer_id FROM bookings
                 WHERE status = 'pending_payment' AND reservation_expires_at < UTC_TIMESTAMP(6)
                 ORDER BY id LIMIT 25 FOR UPDATE"
            );
            foreach ($statement->fetchAll() as $booking) {
                $items = $this->lockBookingTickets((int) $booking['id']);
                $release = $database->prepare(
                    'UPDATE ticket_types SET reserved_quantity = reserved_quantity - :release_quantity
                     WHERE id = :id AND reserved_quantity >= :minimum_quantity'
                );
                foreach ($items as $item) {
                    $release->execute([
                        'release_quantity' => $item['quantity'],
                        'minimum_quantity' => $item['quantity'],
                        'id' => $item['ticket_type_id'],
                    ]);
                    if ($release->rowCount() !== 1) {
                        throw new DomainException('Expired ticket inventory could not be released safely.');
                    }
                }
                $database->prepare(
                    "UPDATE bookings SET status = 'expired', reservation_expires_at = NULL WHERE id = :id"
                )
                    ->execute(['id' => $booking['id']]);
                $database->prepare(
                    "UPDATE payments SET status = 'failed',
                            failure_description = COALESCE(failure_description, 'Reservation expired before payment completed.')
                     WHERE booking_id = :booking_id AND status IN ('created', 'authorized')"
                )->execute(['booking_id' => $booking['id']]);
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function uniqueReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'EBS-' . gmdate('Y') . '-' . $suffix;
            $statement = Database::connection()->prepare('SELECT 1 FROM bookings WHERE booking_reference = :reference');
            $statement->execute(['reference' => $reference]);
        } while ($statement->fetchColumn() !== false);
        return $reference;
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

    private function reservationMinutes(): int
    {
        $file = is_file(BASE_PATH . '/config/payment.local.php')
            ? BASE_PATH . '/config/payment.local.php' : BASE_PATH . '/config/payment.php';
        return max(5, min(30, (int) ((require $file)['reservation_minutes'] ?? 15)));
    }

    private function utcNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
