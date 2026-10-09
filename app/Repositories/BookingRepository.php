<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class BookingRepository
{
    public function publicEvents(array $filters = []): array
    {
        $where = [
            "e.status = 'approved'",
            'e.start_datetime > UTC_TIMESTAMP(6)',
        ];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(
                LOCATE(:search_title, LOWER(e.title)) > 0
                OR LOCATE(:search_description, LOWER(e.description)) > 0
                OR LOCATE(:search_category, LOWER(c.name)) > 0
            )';
            $search = strtolower((string) $filters['search']);
            $parameters['search_title'] = $search;
            $parameters['search_description'] = $search;
            $parameters['search_category'] = $search;
        }

        if (($filters['category_id'] ?? null) !== null) {
            $where[] = 'e.category_id = :category_id';
            $parameters['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['date_from_utc'] ?? null) !== null) {
            $where[] = 'e.start_datetime >= :date_from_utc';
            $parameters['date_from_utc'] = $filters['date_from_utc'];
        }

        if (($filters['date_to_utc'] ?? null) !== null) {
            $where[] = 'e.start_datetime < :date_to_utc';
            $parameters['date_to_utc'] = $filters['date_to_utc'];
        }

        $having = match ($filters['availability'] ?? 'any') {
            'available' => 'HAVING available_quantity > 0',
            'sold_out' => 'HAVING available_quantity <= 0',
            default => '',
        };

        $orderBy = match ($filters['sort'] ?? 'soonest') {
            'latest' => 'e.start_datetime DESC, e.id DESC',
            'price_low' => 'starting_price ASC, e.start_datetime ASC',
            'price_high' => 'starting_price DESC, e.start_datetime ASC',
            'availability' => 'available_quantity DESC, e.start_datetime ASC',
            default => 'e.start_datetime ASC, e.id ASC',
        };

        $statement = Database::connection()->prepare(
            "SELECT e.id, e.title, e.description, e.poster_path, e.start_datetime,
                    e.end_datetime, c.name AS category_name, h.name AS hall_name, h.city,
                    MIN(tt.price) AS starting_price,
                    SUM(tt.capacity - tt.reserved_quantity - tt.sold_quantity) AS available_quantity
             FROM events e
             JOIN event_categories c ON c.id = e.category_id
             JOIN halls h ON h.id = e.hall_id
             JOIN ticket_types tt ON tt.event_id = e.id AND tt.is_active = 1
             WHERE " . implode(' AND ', $where) . "
             GROUP BY e.id, e.title, e.description, e.poster_path, e.start_datetime,
                      e.end_datetime, c.name, h.name, h.city
             {$having}
             ORDER BY {$orderBy}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function publicEvent(int $eventId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.*, c.name AS category_name, h.name AS hall_name, h.address, h.city
             FROM events e JOIN event_categories c ON c.id = e.category_id
             JOIN halls h ON h.id = e.hall_id
             WHERE e.id = :id AND e.status = 'approved' AND e.start_datetime > UTC_TIMESTAMP(6)
             LIMIT 1"
        );
        $statement->execute(['id' => $eventId]);
        $event = $statement->fetch();
        return is_array($event) ? $event : null;
    }

    public function publicTickets(int $eventId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, event_id, name, price, capacity, reserved_quantity, sold_quantity,
                    capacity - reserved_quantity - sold_quantity AS available_quantity
             FROM ticket_types WHERE event_id = :event_id AND is_active = 1
             ORDER BY display_order, id'
        );
        $statement->execute(['event_id' => $eventId]);
        return $statement->fetchAll();
    }

    public function findForCustomer(int $bookingId, int $customerId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT b.*, e.title AS event_title, e.start_datetime, e.status AS event_status,
                       h.name AS hall_name, p.id AS payment_id, p.provider_order_id,
                       p.provider_payment_id, p.status AS payment_status
                FROM bookings b JOIN events e ON e.id = b.event_id
                JOIN halls h ON h.id = e.hall_id
                LEFT JOIN payments p ON p.booking_id = b.id
                WHERE b.id = :id AND b.customer_id = :customer_id LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $bookingId, 'customer_id' => $customerId]);
        $booking = $statement->fetch();
        return is_array($booking) ? $booking : null;
    }

    public function items(int $bookingId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT bi.*, tt.name AS ticket_name FROM booking_items bi
             JOIN ticket_types tt ON tt.id = bi.ticket_type_id
             WHERE bi.booking_id = :booking_id ORDER BY bi.id'
        );
        $statement->execute(['booking_id' => $bookingId]);
        return $statement->fetchAll();
    }

    public function forCustomer(int $customerId, array $filters = []): array
    {
        [$where, $parameters] = $this->customerHistoryWhere($customerId, $filters);
        $orderBy = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'b.booked_at ASC, b.id ASC',
            'event_soonest' => 'e.start_datetime ASC, b.id DESC',
            'amount_high' => 'b.total_amount DESC, b.booked_at DESC, b.id DESC',
            'amount_low' => 'b.total_amount ASC, b.booked_at DESC, b.id DESC',
            default => 'b.booked_at DESC, b.id DESC',
        };

        $statement = Database::connection()->prepare(
            "SELECT b.*, e.title AS event_title, e.start_datetime, e.poster_path,
                    h.name AS hall_name
             FROM bookings b JOIN events e ON e.id = b.event_id
             JOIN halls h ON h.id = e.hall_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$orderBy}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function countForCustomer(int $customerId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM bookings WHERE customer_id = :customer_id'
        );
        $statement->execute(['customer_id' => $customerId]);
        return (int) $statement->fetchColumn();
    }

    public function upcomingConfirmedForCustomer(int $customerId, int $limit = 3): array
    {
        $statement = Database::connection()->prepare(
            "SELECT b.id, b.booking_reference, b.status, b.total_quantity, b.total_amount,
                    b.booked_at, e.title AS event_title, e.start_datetime, h.name AS hall_name
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN halls h ON h.id = e.hall_id
             WHERE b.customer_id = :customer_id
               AND b.status IN ('confirmed', 'partially_cancelled')
               AND e.start_datetime >= UTC_TIMESTAMP(6)
             ORDER BY e.start_datetime, b.id
             LIMIT " . $this->safeLimit($limit)
        );
        $statement->execute(['customer_id' => $customerId]);
        return $statement->fetchAll();
    }

    public function recentForCustomer(int $customerId, int $limit = 4): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id, b.booking_reference, b.status, b.total_quantity, b.total_amount,
                    b.booked_at, e.title AS event_title, e.start_datetime, h.name AS hall_name
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN halls h ON h.id = e.hall_id
             WHERE b.customer_id = :customer_id
             ORDER BY b.booked_at DESC, b.id DESC
             LIMIT ' . $this->safeLimit($limit)
        );
        $statement->execute(['customer_id' => $customerId]);
        return $statement->fetchAll();
    }

    public function payablePendingForCustomer(int $customerId, int $limit = 3): array
    {
        $statement = Database::connection()->prepare(
            "SELECT b.id, b.booking_reference, b.status, b.total_quantity, b.total_amount,
                    b.booked_at, b.reservation_expires_at, e.title AS event_title,
                    e.start_datetime, h.name AS hall_name
             FROM bookings b
             JOIN events e ON e.id = b.event_id
             JOIN halls h ON h.id = e.hall_id
             WHERE b.customer_id = :customer_id
               AND b.status = 'pending_payment'
               AND b.reservation_expires_at IS NOT NULL
               AND b.reservation_expires_at > UTC_TIMESTAMP(6)
               AND e.status = 'approved'
               AND EXISTS (
                   SELECT 1
                   FROM payments p
                   WHERE p.booking_id = b.id
                     AND p.provider_order_id IS NOT NULL
                     AND p.status IN ('created', 'authorized')
               )
             ORDER BY b.reservation_expires_at, b.id
             LIMIT " . $this->safeLimit($limit)
        );
        $statement->execute(['customer_id' => $customerId]);
        return $statement->fetchAll();
    }

    private function safeLimit(int $limit): int
    {
        return max(1, min(10, $limit));
    }

    private function customerHistoryWhere(int $customerId, array $filters): array
    {
        $where = ['b.customer_id = :customer_id'];
        $parameters = ['customer_id' => $customerId];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(
                LOCATE(:search_reference, LOWER(b.booking_reference)) > 0
                OR LOCATE(:search_event, LOWER(e.title)) > 0
                OR LOCATE(:search_hall, LOWER(h.name)) > 0
            )';
            $search = strtolower((string) $filters['search']);
            $parameters['search_reference'] = $search;
            $parameters['search_event'] = $search;
            $parameters['search_hall'] = $search;
        }

        if (($filters['status'] ?? 'any') !== 'any') {
            $where[] = 'b.status = :status';
            $parameters['status'] = (string) $filters['status'];
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
}
