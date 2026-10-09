<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use DomainException;
use Throwable;

final class RefundService
{
    public function __construct(private readonly RazorpayService $razorpay = new RazorpayService())
    {
    }

    public function process(int $refundId, bool $retryProcessing = false): string
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'SELECT r.*, p.provider_payment_id, p.currency, b.booking_reference
                 FROM refunds r
                 JOIN payments p ON p.id = r.payment_id
                 JOIN bookings b ON b.id = r.booking_id
                 WHERE r.id = :id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $refundId]);
            $refund = $statement->fetch();
            if (!is_array($refund)) {
                throw new DomainException('Refund record was not found.');
            }
            if ($refund['status'] === 'processed') {
                $database->commit();
                return 'processed';
            }
            if ($refund['status'] === 'processing' && !$retryProcessing) {
                $database->commit();
                return 'processing';
            }
            if (($refund['provider_payment_id'] ?? '') === '' || $refund['currency'] !== 'INR') {
                throw new DomainException('The captured payment is not available for refund.');
            }

            $database->prepare(
                "UPDATE refunds SET status = 'processing', attempt_count = attempt_count + 1,
                        last_attempted_at = UTC_TIMESTAMP(6), failure_description = NULL
                 WHERE id = :id"
            )->execute(['id' => $refundId]);
            $database->commit();

            $remote = $this->razorpay->createRefund(
                (string) $refund['provider_payment_id'],
                $this->toPaise((string) $refund['amount']),
                (string) $refund['refund_reference'],
                (string) $refund['idempotency_key']
            );
            return $this->applyProviderEntity($refundId, $remote);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->markFailed($refundId, $exception->getMessage());
            Logger::error('refund.processing_failed', ['refund_id' => $refundId, 'exception' => $exception]);
            return 'failed';
        }
    }

    public function processPendingBatch(int $limit = 10): array
    {
        $limit = max(1, min(25, $limit));
        $statement = Database::connection()->query(
            "SELECT id FROM refunds WHERE status = 'pending' ORDER BY requested_at, id LIMIT {$limit}"
        );
        $results = [];
        foreach ($statement->fetchAll() as $row) {
            $results[(int) $row['id']] = $this->process((int) $row['id']);
        }
        return $results;
    }

    public function applyWebhookEntity(array $entity, string $eventType): void
    {
        $providerId = trim((string) ($entity['id'] ?? ''));
        $receipt = trim((string) ($entity['receipt'] ?? ''));
        $paymentId = trim((string) ($entity['payment_id'] ?? ''));
        if ($providerId === '' || $paymentId === '' || !isset($entity['amount'])) {
            throw new DomainException('Refund webhook data is incomplete.');
        }

        $database = Database::connection();
        $statement = $database->prepare(
            'SELECT r.id, r.amount, r.provider_refund_id, p.provider_payment_id
             FROM refunds r JOIN payments p ON p.id = r.payment_id
             WHERE r.provider_refund_id = :provider_id
                OR (r.refund_reference = :receipt AND r.provider_refund_id IS NULL)
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['provider_id' => $providerId, 'receipt' => $receipt]);
        $refund = $statement->fetch();
        if (!is_array($refund)
            || !hash_equals((string) $refund['provider_payment_id'], $paymentId)
            || $this->toPaise((string) $refund['amount']) !== (int) $entity['amount']) {
            throw new DomainException('Refund webhook does not match a local refund.');
        }

        $status = match ($eventType) {
            'refund.processed' => 'processed',
            'refund.failed' => 'failed',
            default => 'processing',
        };
        $database->prepare(
            "UPDATE refunds SET provider_refund_id = :provider_id, status = :status,
                    processed_at = CASE WHEN :processed_status = 'processed' THEN UTC_TIMESTAMP(6) ELSE processed_at END,
                    failure_description = CASE WHEN :failed_status = 'failed' THEN 'Razorpay reported that the refund failed.' ELSE NULL END
             WHERE id = :id"
        )->execute([
            'provider_id' => $providerId,
            'status' => $status,
            'processed_status' => $status,
            'failed_status' => $status,
            'id' => $refund['id'],
        ]);
        if ($status === 'processed') {
            $this->refreshPaymentStatusForRefund((int) $refund['id']);
        }
    }

    private function applyProviderEntity(int $refundId, array $entity): string
    {
        $status = (string) ($entity['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'processed', 'failed'], true)) {
            $status = 'pending';
        }
        $localStatus = $status === 'pending' ? 'processing' : $status;
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'SELECT r.amount, p.provider_payment_id
                 FROM refunds r JOIN payments p ON p.id = r.payment_id
                 WHERE r.id = :id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => $refundId]);
            $local = $statement->fetch();
            if (!is_array($local)
                || (string) ($entity['payment_id'] ?? '') !== (string) $local['provider_payment_id']
                || (int) ($entity['amount'] ?? -1) !== $this->toPaise((string) $local['amount'])) {
                throw new DomainException('Razorpay returned refund details that do not match the request.');
            }
            $providerId = trim((string) ($entity['id'] ?? ''));
            if ($providerId === '') {
                throw new DomainException('Razorpay did not return a refund identifier.');
            }
            $database->prepare(
                "UPDATE refunds SET provider_refund_id = :provider_id, status = :status,
                        processed_at = CASE WHEN :processed_status = 'processed' THEN UTC_TIMESTAMP(6) ELSE NULL END,
                        failure_description = CASE WHEN :failed_status = 'failed' THEN 'Razorpay reported that the refund failed.' ELSE NULL END
                 WHERE id = :id"
            )->execute([
                'provider_id' => $providerId,
                'status' => $localStatus,
                'processed_status' => $localStatus,
                'failed_status' => $localStatus,
                'id' => $refundId,
            ]);
            if ($localStatus === 'processed') {
                $this->refreshPaymentStatusForRefund($refundId);
            }
            $database->commit();
            return $localStatus;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function refreshPaymentStatusForRefund(int $refundId): void
    {
        $database = Database::connection();
        $statement = $database->prepare('SELECT payment_id FROM refunds WHERE id = :id');
        $statement->execute(['id' => $refundId]);
        $paymentId = (int) $statement->fetchColumn();
        $statement = $database->prepare(
            "SELECT p.amount, COALESCE(SUM(CASE WHEN r.status = 'processed' THEN r.amount ELSE 0 END), 0) refunded
             FROM payments p LEFT JOIN refunds r ON r.payment_id = p.id
             WHERE p.id = :id GROUP BY p.id, p.amount"
        );
        $statement->execute(['id' => $paymentId]);
        $totals = $statement->fetch();
        if (!is_array($totals)) {
            return;
        }
        $status = (float) $totals['refunded'] >= (float) $totals['amount'] ? 'refunded' : 'partially_refunded';
        $database->prepare('UPDATE payments SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $paymentId]);
    }

    private function markFailed(int $refundId, string $message): void
    {
        try {
            Database::connection()->prepare(
                "UPDATE refunds SET status = 'failed', failure_description = :message WHERE id = :id AND status <> 'processed'"
            )->execute(['message' => mb_substr($message, 0, 500), 'id' => $refundId]);
        } catch (Throwable $ignored) {
            Logger::error('refund.failure_state_write_failed', ['refund_id' => $refundId, 'exception' => $ignored]);
        }
    }

    private function toPaise(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
