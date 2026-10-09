<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Repositories\NotificationRepository;
use DomainException;
use PDOException;
use RuntimeException;
use Throwable;

final class PaymentWebhookService
{
    private array $config;

    public function __construct()
    {
        $this->config = (array) Config::get('payment.razorpay', []);
    }

    public function process(string $rawBody, string $signature, string $providerEventId): string
    {
        $secret = trim((string) ($this->config['webhook_secret'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('Razorpay webhook secret is not configured.');
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw new DomainException('Invalid webhook signature.');
        }
        if ($providerEventId === '' || strlen($providerEventId) > 100) {
            throw new DomainException('Invalid webhook event identifier.');
        }
        $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        $type = (string) ($payload['event'] ?? 'unknown');
        $database = Database::connection();
        $database->beginTransaction();
        try {
            try {
                $insert = $database->prepare(
                    "INSERT INTO payment_webhook_events
                        (provider_event_id, event_type, payload_hash, payload_json, processing_status)
                     VALUES (:event_id, :event_type, :payload_hash, :payload, 'received')"
                );
                $insert->execute([
                    'event_id' => $providerEventId, 'event_type' => mb_substr($type, 0, 100),
                    'payload_hash' => hash('sha256', $rawBody), 'payload' => $rawBody,
                ]);
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $database->rollBack();
                    return 'duplicate';
                }
                throw $exception;
            }
            $webhookId = (int) $database->lastInsertId();

            if (in_array($type, ['refund.created', 'refund.processed', 'refund.failed'], true)) {
                $refundEntity = $payload['payload']['refund']['entity'] ?? null;
                if (!is_array($refundEntity)) {
                    throw new DomainException('Refund webhook data is missing.');
                }
                (new RefundService())->applyWebhookEntity($refundEntity, $type);
                $this->mark($webhookId, 'processed');
                $database->commit();
                return 'processed';
            }

            if ($type !== 'payment.captured') {
                $this->mark($webhookId, 'ignored');
                $database->commit();
                return 'ignored';
            }

            $paymentEntity = $payload['payload']['payment']['entity'] ?? null;
            if (!is_array($paymentEntity)) {
                throw new DomainException('Captured payment data is missing.');
            }
            $orderId = (string) ($paymentEntity['order_id'] ?? '');
            $paymentId = (string) ($paymentEntity['id'] ?? '');
            $statement = $database->prepare(
                'SELECT p.id AS payment_record_id, p.booking_id, p.amount, p.currency, p.status
                 FROM payments p WHERE p.provider_order_id = :order_id LIMIT 1'
            );
            $statement->execute(['order_id' => $orderId]);
            $paymentRecord = $statement->fetch();
            if (!is_array($paymentRecord)) {
                throw new DomainException('Webhook order does not match a booking.');
            }
            $bookingStatement = $database->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
            $bookingStatement->execute(['id' => $paymentRecord['booking_id']]);
            $booking = $bookingStatement->fetch();
            if (!is_array($booking)) {
                throw new DomainException('Webhook booking was not found.');
            }
            $expectedPaise = (int) round((float) $paymentRecord['amount'] * 100);
            if ((int) ($paymentEntity['amount'] ?? -1) !== $expectedPaise
                || ($paymentEntity['currency'] ?? '') !== 'INR'
                || ($paymentEntity['status'] ?? '') !== 'captured') {
                throw new DomainException('Webhook payment details do not match the booking.');
            }
            if ($booking['status'] === 'confirmed') {
                (new TicketIssuanceService())->issueForConfirmedBooking((int) $booking['id']);
                $this->mark($webhookId, 'processed');
                $database->commit();
                return 'processed';
            }
            if (in_array($booking['status'], ['partially_cancelled', 'customer_cancelled', 'event_cancelled'], true)
                && in_array($paymentRecord['status'], ['captured', 'partially_refunded', 'refunded'], true)) {
                $this->mark($webhookId, 'processed');
                $database->commit();
                return 'processed';
            }
            if ($booking['status'] !== 'pending_payment'
                || $booking['reservation_expires_at'] < gmdate('Y-m-d H:i:s')) {
                throw new DomainException('Captured payment arrived after the reservation became unavailable.');
            }
            $event = $database->prepare('SELECT status FROM events WHERE id = :id FOR UPDATE');
            $event->execute(['id' => $booking['event_id']]);
            if ($event->fetchColumn() !== 'approved') {
                throw new DomainException('The event is unavailable for this captured payment.');
            }

            $itemsStatement = $database->prepare(
                'SELECT bi.ticket_type_id, bi.quantity FROM booking_items bi
                 JOIN ticket_types tt ON tt.id = bi.ticket_type_id
                 WHERE bi.booking_id = :booking_id ORDER BY bi.ticket_type_id FOR UPDATE'
            );
            $itemsStatement->execute(['booking_id' => $booking['id']]);
            $updateInventory = $database->prepare(
                'UPDATE ticket_types SET reserved_quantity = reserved_quantity - :release_quantity,
                        sold_quantity = sold_quantity + :sell_quantity
                 WHERE id = :id AND reserved_quantity >= :minimum_quantity'
            );
            foreach ($itemsStatement->fetchAll() as $item) {
                $updateInventory->execute([
                    'release_quantity' => $item['quantity'],
                    'sell_quantity' => $item['quantity'],
                    'minimum_quantity' => $item['quantity'],
                    'id' => $item['ticket_type_id'],
                ]);
                if ($updateInventory->rowCount() !== 1) {
                    throw new DomainException('Reserved ticket inventory is unavailable.');
                }
            }
            $confirm = $database->prepare(
                "UPDATE bookings SET status = 'confirmed', confirmed_at = UTC_TIMESTAMP(6),
                        reservation_expires_at = NULL WHERE id = :id AND status = 'pending_payment'"
            );
            $confirm->execute(['id' => $booking['id']]);
            if ($confirm->rowCount() !== 1) {
                throw new DomainException('The booking could not be confirmed safely.');
            }
            (new TicketIssuanceService())->issueForConfirmedBooking((int) $booking['id']);
            $database->prepare(
                "UPDATE payments SET provider_payment_id = :provider_payment_id, status = 'captured',
                        signature_verified_at = UTC_TIMESTAMP(6), captured_at = UTC_TIMESTAMP(6)
                 WHERE id = :id"
            )->execute(['provider_payment_id' => $paymentId, 'id' => $paymentRecord['payment_record_id']]);
            (new NotificationRepository())->createForUser(
                (int) $booking['customer_id'], 'booking_confirmed', 'Booking confirmed',
                'Your booking has been confirmed successfully.',
                null,
                (int) $booking['id']
            );
            $this->mark($webhookId, 'processed');
            $database->commit();
            return 'processed';
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function mark(int $id, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE payment_webhook_events SET processing_status = :status,
                    processed_at = UTC_TIMESTAMP(6) WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }
}
