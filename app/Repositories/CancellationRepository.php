<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CancellationRepository
{
    public function refundMetrics(?int $organizerId = null): array
    {
        $join = $organizerId === null ? '' : ' JOIN bookings b ON b.id = r.booking_id JOIN events e ON e.id = b.event_id';
        $where = $organizerId === null ? '' : ' WHERE e.organizer_id = :organizer_id';
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_refunds,
                    SUM(r.status = 'pending') AS pending_refunds,
                    SUM(r.status = 'failed') AS failed_refunds,
                    SUM(r.status = 'processed') AS processed_refunds,
                    COALESCE(SUM(CASE WHEN r.status = 'processed' THEN r.amount ELSE 0 END), 0) AS refunded_amount
             FROM refunds r{$join}{$where}"
        );
        $statement->execute($organizerId === null ? [] : ['organizer_id' => $organizerId]);
        $row = $statement->fetch() ?: [];
        if ($organizerId === null) {
            $cancelledTickets = (int) Database::connection()->query('SELECT COUNT(*) FROM ticket_cancellations')->fetchColumn();
            $pendingRequests = (int) Database::connection()->query(
                "SELECT COUNT(*) FROM event_cancellation_requests WHERE status = 'pending'"
            )->fetchColumn();
        } else {
            $ticketStatement = Database::connection()->prepare(
                'SELECT COUNT(*) FROM ticket_cancellations tc
                 JOIN bookings b ON b.id = tc.booking_id
                 JOIN events e ON e.id = b.event_id WHERE e.organizer_id = :organizer_id'
            );
            $ticketStatement->execute(['organizer_id' => $organizerId]);
            $cancelledTickets = (int) $ticketStatement->fetchColumn();
            $requestStatement = Database::connection()->prepare(
                "SELECT COUNT(*) FROM event_cancellation_requests ecr
                 JOIN events e ON e.id = ecr.event_id
                 WHERE e.organizer_id = :organizer_id AND ecr.status = 'pending'"
            );
            $requestStatement->execute(['organizer_id' => $organizerId]);
            $pendingRequests = (int) $requestStatement->fetchColumn();
        }
        return [
            'total_refunds' => (int) ($row['total_refunds'] ?? 0),
            'pending_refunds' => (int) ($row['pending_refunds'] ?? 0),
            'failed_refunds' => (int) ($row['failed_refunds'] ?? 0),
            'processed_refunds' => (int) ($row['processed_refunds'] ?? 0),
            'refunded_amount' => (string) ($row['refunded_amount'] ?? '0.00'),
            'cancelled_tickets' => $cancelledTickets,
            'pending_cancellation_requests' => $pendingRequests,
        ];
    }

    public function customerOptions(int $bookingId, int $customerId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT it.ticket_type_id, tt.name AS ticket_type_name, bi.unit_price,
                    COUNT(*) AS available_quantity
             FROM issued_tickets it
             JOIN bookings b ON b.id = it.booking_id
             JOIN booking_items bi ON bi.id = it.booking_item_id
             JOIN ticket_types tt ON tt.id = it.ticket_type_id
             WHERE it.booking_id = :booking_id AND b.customer_id = :customer_id
               AND it.status = 'valid'
             GROUP BY it.ticket_type_id, tt.name, bi.unit_price
             ORDER BY MIN(bi.id)"
        );
        $statement->execute(['booking_id' => $bookingId, 'customer_id' => $customerId]);
        return $statement->fetchAll();
    }

    public function refundsForBooking(int $bookingId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.refund_reference, r.amount, r.refund_percentage, r.source,
                    r.status, r.failure_description, r.requested_at, r.processed_at,
                    COUNT(tc.id) AS ticket_count
             FROM refunds r LEFT JOIN ticket_cancellations tc ON tc.refund_id = r.id
             WHERE r.booking_id = :booking_id
             GROUP BY r.id, r.refund_reference, r.amount, r.refund_percentage, r.source,
                      r.status, r.failure_description, r.requested_at, r.processed_at
             ORDER BY r.requested_at DESC, r.id DESC'
        );
        $statement->execute(['booking_id' => $bookingId]);
        return $statement->fetchAll();
    }

    public function forOrganizer(int $organizerId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT ecr.*, e.title, e.start_datetime, e.status AS event_status,
                    u.name AS reviewer_name
             FROM event_cancellation_requests ecr
             JOIN events e ON e.id = ecr.event_id
             LEFT JOIN users u ON u.id = ecr.reviewed_by
             WHERE e.organizer_id = :organizer_id AND ecr.requested_by = :requester_id
             ORDER BY ecr.created_at DESC, ecr.id DESC"
        );
        $statement->execute(['organizer_id' => $organizerId, 'requester_id' => $organizerId]);
        return $statement->fetchAll();
    }

    public function eligibleOrganizerEvents(int $organizerId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.id, e.title, e.start_datetime
             FROM events e
             WHERE e.organizer_id = :organizer_id AND e.status = 'approved'
               AND e.start_datetime > UTC_TIMESTAMP(6)
               AND NOT EXISTS (
                   SELECT 1 FROM event_cancellation_requests ecr
                   WHERE ecr.event_id = e.id AND ecr.status = 'pending'
               )
             ORDER BY e.start_datetime, e.id"
        );
        $statement->execute(['organizer_id' => $organizerId]);
        return $statement->fetchAll();
    }

    public function adminRequests(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = $this->requestWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $statement = Database::connection()->prepare(
            "SELECT ecr.*, e.title, e.start_datetime, e.status AS event_status,
                    organizer.name AS organizer_name, organizer.email AS organizer_email,
                    reviewer.name AS reviewer_name,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.event_id = e.id AND b.status IN ('confirmed', 'partially_cancelled')) AS affected_bookings,
                    (SELECT COALESCE(SUM(b.total_amount), 0) FROM bookings b
                     WHERE b.event_id = e.id AND b.status IN ('confirmed', 'partially_cancelled')) AS booked_value
             FROM event_cancellation_requests ecr
             JOIN events e ON e.id = ecr.event_id
             JOIN users organizer ON organizer.id = e.organizer_id
             LEFT JOIN users reviewer ON reviewer.id = ecr.reviewed_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE WHEN ecr.status = 'pending' THEN 0 ELSE 1 END, ecr.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function countAdminRequests(array $filters): int
    {
        [$where, $parameters] = $this->requestWhere($filters);
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM event_cancellation_requests ecr
             JOIN events e ON e.id = ecr.event_id
             JOIN users organizer ON organizer.id = e.organizer_id
             WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function adminRefunds(array $filters, int $page, int $perPage): array
    {
        [$where, $parameters] = $this->refundWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $statement = Database::connection()->prepare(
            "SELECT r.*, b.booking_reference, e.title AS event_title,
                    u.name AS customer_name, u.email AS customer_email,
                    p.provider_payment_id,
                    COUNT(tc.id) AS ticket_count
             FROM refunds r
             JOIN bookings b ON b.id = r.booking_id
             JOIN events e ON e.id = b.event_id
             JOIN users u ON u.id = b.customer_id
             JOIN payments p ON p.id = r.payment_id
             LEFT JOIN ticket_cancellations tc ON tc.refund_id = r.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY r.id, b.booking_reference, e.title, u.name, u.email, p.provider_payment_id
             ORDER BY r.requested_at DESC, r.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function countAdminRefunds(array $filters): int
    {
        [$where, $parameters] = $this->refundWhere($filters);
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM refunds r
             JOIN bookings b ON b.id = r.booking_id
             JOIN events e ON e.id = b.event_id
             JOIN users u ON u.id = b.customer_id
             WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function adminRefund(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.*, b.booking_reference, b.status AS booking_status,
                    e.title AS event_title, u.name AS customer_name, u.email AS customer_email,
                    p.provider_payment_id, p.status AS payment_status, p.amount AS payment_amount
             FROM refunds r
             JOIN bookings b ON b.id = r.booking_id
             JOIN events e ON e.id = b.event_id
             JOIN users u ON u.id = b.customer_id
             JOIN payments p ON p.id = r.payment_id
             WHERE r.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $refund = $statement->fetch();
        return is_array($refund) ? $refund : null;
    }

    public function cancellationTicketsForRefund(int $refundId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT tc.*, it.ticket_code, it.seat_number, tt.name AS ticket_type_name
             FROM ticket_cancellations tc
             JOIN issued_tickets it ON it.id = tc.issued_ticket_id
             JOIN ticket_types tt ON tt.id = it.ticket_type_id
             WHERE tc.refund_id = :refund_id ORDER BY it.seat_number'
        );
        $statement->execute(['refund_id' => $refundId]);
        return $statement->fetchAll();
    }

    private function refundWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        if (($filters['status'] ?? 'any') !== 'any') {
            $where[] = 'r.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['source'] ?? 'any') !== 'any') {
            $where[] = 'r.source = :source';
            $parameters['source'] = $filters['source'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(LOWER(b.booking_reference) LIKE :reference OR LOWER(e.title) LIKE :event OR LOWER(u.email) LIKE :email)';
            $term = '%' . strtolower((string) $filters['search']) . '%';
            $parameters['reference'] = $term;
            $parameters['event'] = $term;
            $parameters['email'] = $term;
        }
        return [$where, $parameters];
    }

    private function requestWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        if (($filters['status'] ?? 'any') !== 'any') {
            $where[] = 'ecr.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $term = '%' . strtolower((string) $filters['search']) . '%';
            $where[] = '(LOWER(e.title) LIKE :event OR LOWER(organizer.name) LIKE :organizer OR LOWER(organizer.email) LIKE :email)';
            $parameters['event'] = $term;
            $parameters['organizer'] = $term;
            $parameters['email'] = $term;
        }
        return [$where, $parameters];
    }
}
