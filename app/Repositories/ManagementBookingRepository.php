<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
final class ManagementBookingRepository
{
    public function forOrganizer(int $organizerId, array $filters, int $page, int $perPage = 20): array
    {
        return $this->bookingPage('organizer', $organizerId, $filters, $page, $perPage);
    }

    public function forAdmin(array $filters, int $page, int $perPage = 20): array
    {
        return $this->bookingPage('admin', null, $filters, $page, $perPage);
    }

    public function countForOrganizer(int $organizerId, array $filters): int
    {
        return $this->bookingCount('organizer', $organizerId, $filters);
    }

    public function countForAdmin(array $filters): int
    {
        return $this->bookingCount('admin', null, $filters);
    }

    public function findForOrganizer(int $bookingId, int $organizerId): ?array
    {
        return $this->findBooking($bookingId, 'organizer', $organizerId);
    }

    public function findForAdmin(int $bookingId): ?array
    {
        return $this->findBooking($bookingId, 'admin', null);
    }

    public function items(int $bookingId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT bi.quantity, bi.unit_price, tt.name AS ticket_name
             FROM booking_items bi
             JOIN ticket_types tt ON tt.id = bi.ticket_type_id
             WHERE bi.booking_id = :booking_id
             ORDER BY bi.id'
        );
        $statement->execute(['booking_id' => $bookingId]);
        return $statement->fetchAll();
    }

    public function organizerMetrics(int $organizerId, ?int $eventId = null): array
    {
        $eventClause = $eventId === null ? '' : ' AND e.id = :event_id';
        $parameters = ['organizer_id' => $organizerId];
        if ($eventId !== null) {
            $parameters['event_id'] = $eventId;
        }
        $statement = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN b.id END) AS confirmed_bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN
                        (SELECT COUNT(*) FROM issued_tickets it WHERE it.booking_id = b.id AND it.status = 'valid') ELSE 0 END), 0) AS confirmed_tickets,
                    COUNT(DISTINCT CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN b.customer_id END) AS confirmed_customers,
                    COALESCE(SUM(CASE WHEN p.status IN ('captured', 'partially_refunded', 'refunded')
                        THEN p.amount - COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.payment_id = p.id AND r.status = 'processed'), 0)
                        ELSE 0 END), 0) AS confirmed_revenue
             FROM events e
             LEFT JOIN bookings b ON b.event_id = e.id
             LEFT JOIN payments p ON p.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE e.organizer_id = :organizer_id{$eventClause}"
        );
        $statement->execute($parameters);
        return $this->normalizeMetrics($statement->fetch() ?: []);
    }

    public function adminMetrics(): array
    {
        $row = Database::connection()->query(
            "SELECT COUNT(*) AS total_bookings,
                    SUM(b.status IN ('confirmed', 'partially_cancelled')) AS confirmed_bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN
                        (SELECT COUNT(*) FROM issued_tickets it WHERE it.booking_id = b.id AND it.status = 'valid') ELSE 0 END), 0) AS confirmed_tickets,
                    SUM(b.status = 'pending_payment') AS pending_bookings,
                    SUM(b.status IN ('payment_failed', 'expired')) AS failed_bookings,
                    COALESCE(SUM(CASE WHEN p.status IN ('captured', 'partially_refunded', 'refunded')
                        THEN p.amount - COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.payment_id = p.id AND r.status = 'processed'), 0)
                        ELSE 0 END), 0) AS confirmed_revenue
             FROM bookings b
             LEFT JOIN payments p ON p.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )"
        )->fetch() ?: [];

        return [
            'total_bookings' => (int) ($row['total_bookings'] ?? 0),
            'confirmed_bookings' => (int) ($row['confirmed_bookings'] ?? 0),
            'confirmed_tickets' => (int) ($row['confirmed_tickets'] ?? 0),
            'pending_bookings' => (int) ($row['pending_bookings'] ?? 0),
            'failed_bookings' => (int) ($row['failed_bookings'] ?? 0),
            'confirmed_revenue' => (string) ($row['confirmed_revenue'] ?? '0.00'),
        ];
    }

    public function recentForOrganizer(int $organizerId, int $limit = 5): array
    {
        return $this->recent('organizer', $organizerId, $limit);
    }

    public function recentForAdmin(int $limit = 5): array
    {
        return $this->recent('admin', null, $limit);
    }

    public function organizerEvents(int $organizerId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, title FROM events WHERE organizer_id = :organizer_id ORDER BY start_datetime DESC, id DESC'
        );
        $statement->execute(['organizer_id' => $organizerId]);
        return $statement->fetchAll();
    }

    public function adminEvents(): array
    {
        return Database::connection()->query('SELECT id, title FROM events ORDER BY start_datetime DESC, id DESC')->fetchAll();
    }

    public function adminOrganizers(): array
    {
        return Database::connection()->query(
            "SELECT id, name FROM users WHERE role = 'organizer' ORDER BY name, id"
        )->fetchAll();
    }

    public function customers(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = $this->customerWhere($filters);
        $safePerPage = max(1, min(100, $perPage));
        $offset = (max(1, $page) - 1) * $safePerPage;
        $statement = Database::connection()->prepare(
            "SELECT u.id, u.name, u.email, u.phone, u.account_status, u.created_at,
                    COUNT(b.id) AS booking_count,
                    COALESCE(SUM(b.status IN ('confirmed', 'partially_cancelled')), 0) AS confirmed_bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN
                        (SELECT COUNT(*) FROM issued_tickets it WHERE it.booking_id = b.id AND it.status = 'valid') ELSE 0 END), 0) AS confirmed_tickets,
                    COALESCE(SUM(CASE WHEN cp.status IN ('captured', 'partially_refunded', 'refunded')
                        THEN cp.amount - COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.payment_id = cp.id AND r.status = 'processed'), 0)
                        ELSE 0 END), 0) AS confirmed_spend
             FROM users u
             LEFT JOIN bookings b ON b.customer_id = u.id
             LEFT JOIN payments cp ON cp.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.id, u.name, u.email, u.phone, u.account_status, u.created_at
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT {$safePerPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function countCustomers(array $filters): int
    {
        [$where, $parameters] = $this->customerWhere($filters);
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM users u WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function customer(int $customerId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT u.id, u.name, u.email, u.phone, u.account_status, u.created_at,
                    COUNT(b.id) AS booking_count,
                    COALESCE(SUM(b.status IN ('confirmed', 'partially_cancelled')), 0) AS confirmed_bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'partially_cancelled') THEN
                        (SELECT COUNT(*) FROM issued_tickets it WHERE it.booking_id = b.id AND it.status = 'valid') ELSE 0 END), 0) AS confirmed_tickets,
                    COALESCE(SUM(CASE WHEN cp.status IN ('captured', 'partially_refunded', 'refunded')
                        THEN cp.amount - COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.payment_id = cp.id AND r.status = 'processed'), 0)
                        ELSE 0 END), 0) AS confirmed_spend
             FROM users u
             LEFT JOIN bookings b ON b.customer_id = u.id
             LEFT JOIN payments cp ON cp.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE u.id = :id AND u.role = 'customer'
             GROUP BY u.id, u.name, u.email, u.phone, u.account_status, u.created_at
             LIMIT 1"
        );
        $statement->execute(['id' => $customerId]);
        $customer = $statement->fetch();
        return is_array($customer) ? $customer : null;
    }

    public function organizer(int $organizerId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, name, email, phone, account_status, review_reason, reviewed_at, created_at
             FROM users WHERE id = :id AND role = 'organizer' LIMIT 1"
        );
        $statement->execute(['id' => $organizerId]);
        $organizer = $statement->fetch();
        return is_array($organizer) ? $organizer : null;
    }

    private function bookingPage(string $scope, ?int $scopeId, array $filters, int $page, int $perPage): array
    {
        [$where, $parameters] = $this->bookingWhere($scope, $scopeId, $filters);
        $safePerPage = max(1, min(100, $perPage));
        $offset = (max(1, $page) - 1) * $safePerPage;
        $order = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'b.booked_at ASC, b.id ASC',
            'amount_high' => 'b.total_amount DESC, b.booked_at DESC, b.id DESC',
            'amount_low' => 'b.total_amount ASC, b.booked_at DESC, b.id DESC',
            'event_soonest' => 'e.start_datetime ASC, b.id DESC',
            default => 'b.booked_at DESC, b.id DESC',
        };
        $customerPhone = $scope === 'admin' ? ', cu.phone AS customer_phone' : '';
        $statement = Database::connection()->prepare(
            "SELECT b.id, b.booking_reference, b.status, b.total_quantity, b.total_amount,
                    b.booked_at, b.confirmed_at, b.reservation_expires_at,
                    e.id AS event_id, e.title AS event_title, e.start_datetime,
                    o.id AS organizer_id, o.name AS organizer_name,
                    cu.id AS customer_id, cu.name AS customer_name, cu.email AS customer_email{$customerPhone},
                    CASE WHEN b.total_amount = 0 THEN 'not_required'
                         ELSE COALESCE(p.status, 'not_started') END AS payment_status
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN users o ON o.id = e.organizer_id
             JOIN users cu ON cu.id = b.customer_id
             LEFT JOIN payments p ON p.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$order}
             LIMIT {$safePerPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function bookingCount(string $scope, ?int $scopeId, array $filters): int
    {
        [$where, $parameters] = $this->bookingWhere($scope, $scopeId, $filters);
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN users o ON o.id = e.organizer_id
             JOIN users cu ON cu.id = b.customer_id
             LEFT JOIN payments p ON p.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE " . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    private function findBooking(int $bookingId, string $scope, ?int $scopeId): ?array
    {
        $scopeClause = $scope === 'organizer' ? ' AND e.organizer_id = :scope_id' : '';
        $customerPhone = $scope === 'admin' ? ', cu.phone AS customer_phone' : '';
        $statement = Database::connection()->prepare(
            "SELECT b.*, e.title AS event_title, e.start_datetime, e.end_datetime,
                    o.id AS organizer_id, o.name AS organizer_name, o.email AS organizer_email,
                    cu.id AS customer_id, cu.name AS customer_name, cu.email AS customer_email{$customerPhone},
                    CASE WHEN b.total_amount = 0 THEN 'not_required'
                         ELSE COALESCE(p.status, 'not_started') END AS payment_status,
                    p.captured_at
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN users o ON o.id = e.organizer_id
             JOIN users cu ON cu.id = b.customer_id
             LEFT JOIN payments p ON p.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id
                 ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1
             )
             WHERE b.id = :id{$scopeClause} LIMIT 1"
        );
        $parameters = ['id' => $bookingId];
        if ($scope === 'organizer') {
            $parameters['scope_id'] = $scopeId;
        }
        $statement->execute($parameters);
        $booking = $statement->fetch();
        return is_array($booking) ? $booking : null;
    }

    private function recent(string $scope, ?int $scopeId, int $limit): array
    {
        $filters = ['sort' => 'newest'];
        return $this->bookingPage($scope, $scopeId, $filters, 1, max(1, min(10, $limit)));
    }

    private function bookingWhere(string $scope, ?int $scopeId, array $filters): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        if ($scope === 'organizer') {
            $where[] = 'e.organizer_id = :scope_id';
            $parameters['scope_id'] = $scopeId;
        } elseif (($filters['organizer_id'] ?? null) !== null) {
            $where[] = 'e.organizer_id = :organizer_id';
            $parameters['organizer_id'] = $filters['organizer_id'];
        }
        if (($filters['customer_id'] ?? null) !== null) {
            $where[] = 'b.customer_id = :customer_id';
            $parameters['customer_id'] = $filters['customer_id'];
        }
        if (($filters['event_id'] ?? null) !== null) {
            $where[] = 'e.id = :event_id';
            $parameters['event_id'] = $filters['event_id'];
        }
        if (($filters['status'] ?? 'any') !== 'any') {
            $where[] = 'b.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['payment_status'] ?? 'any') !== 'any') {
            if ($filters['payment_status'] === 'not_required') {
                $where[] = 'b.total_amount = 0';
            } elseif ($filters['payment_status'] === 'not_started') {
                $where[] = 'p.id IS NULL';
            } else {
                $where[] = 'p.status = :payment_status';
                $parameters['payment_status'] = $filters['payment_status'];
            }
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(LOCATE(:search_reference, LOWER(b.booking_reference)) > 0
                OR LOCATE(:search_event, LOWER(e.title)) > 0
                OR LOCATE(:search_customer_name, LOWER(cu.name)) > 0
                OR LOCATE(:search_customer_email, LOWER(cu.email)) > 0'
                . ($scope === 'admin'
                    ? ' OR LOCATE(:search_customer_phone, LOWER(cu.phone)) > 0
                       OR LOCATE(:search_organizer_name, LOWER(o.name)) > 0
                       OR LOCATE(:search_organizer_email, LOWER(o.email)) > 0)'
                    : ')');
            $search = strtolower($filters['search']);
            $parameters += [
                'search_reference' => $search,
                'search_event' => $search,
                'search_customer_name' => $search,
                'search_customer_email' => $search,
            ];
            if ($scope === 'admin') {
                $parameters += [
                    'search_customer_phone' => $search,
                    'search_organizer_name' => $search,
                    'search_organizer_email' => $search,
                ];
            }
        }
        if (($filters['date_from_utc'] ?? null) !== null) {
            $where[] = 'b.booked_at >= :date_from_utc';
            $parameters['date_from_utc'] = $filters['date_from_utc'];
        }
        if (($filters['date_to_utc'] ?? null) !== null) {
            $where[] = 'b.booked_at < :date_to_utc';
            $parameters['date_to_utc'] = $filters['date_to_utc'];
        }
        return [$where, $parameters];
    }

    private function customerWhere(array $filters): array
    {
        $where = ["u.role = 'customer'"];
        $parameters = [];
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(LOCATE(:name, LOWER(u.name)) > 0 OR LOCATE(:email, LOWER(u.email)) > 0 OR LOCATE(:phone, LOWER(u.phone)) > 0)';
            $search = strtolower($filters['search']);
            $parameters = ['name' => $search, 'email' => $search, 'phone' => $search];
        }
        if (($filters['account_status'] ?? 'any') !== 'any') {
            $where[] = 'u.account_status = :account_status';
            $parameters['account_status'] = $filters['account_status'];
        }
        return [$where, $parameters];
    }

    private function normalizeMetrics(array $row): array
    {
        return [
            'confirmed_bookings' => (int) ($row['confirmed_bookings'] ?? 0),
            'confirmed_tickets' => (int) ($row['confirmed_tickets'] ?? 0),
            'confirmed_customers' => (int) ($row['confirmed_customers'] ?? 0),
            'confirmed_revenue' => (string) ($row['confirmed_revenue'] ?? '0.00'),
        ];
    }
}
