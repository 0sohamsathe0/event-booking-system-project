<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class IssuedTicketRepository
{
    public function forCustomerBooking(int $bookingId, int $customerId): array
    {
        return $this->tickets(
            'it.booking_id = :booking_id AND b.customer_id = :customer_id',
            ['booking_id' => $bookingId, 'customer_id' => $customerId]
        );
    }

    public function forBooking(int $bookingId): array
    {
        return $this->tickets('it.booking_id = :booking_id', ['booking_id' => $bookingId]);
    }

    private function tickets(string $where, array $parameters): array
    {
        $statement = Database::connection()->prepare(
            "SELECT it.id, it.ticket_code, it.seat_number, it.status, it.issued_at,
                    tt.name AS ticket_type_name
             FROM issued_tickets it
             JOIN bookings b ON b.id = it.booking_id
             JOIN ticket_types tt ON tt.id = it.ticket_type_id
             WHERE {$where}
             ORDER BY it.seat_number, it.id"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }
}
