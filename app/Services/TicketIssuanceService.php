<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;

final class TicketIssuanceService
{
    public function issueForConfirmedBooking(int $bookingId): void
    {
        $database = Database::connection();
        if (!$database->inTransaction()) {
            throw new DomainException('Tickets must be issued inside the booking transaction.');
        }

        $bookingStatement = $database->prepare(
            'SELECT id, booking_reference, event_id, status, total_quantity
             FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $bookingStatement->execute(['id' => $bookingId]);
        $booking = $bookingStatement->fetch();
        if (!is_array($booking) || $booking['status'] !== 'confirmed') {
            throw new DomainException('Tickets can only be issued for a confirmed booking.');
        }

        $existingStatement = $database->prepare(
            'SELECT COUNT(*) FROM issued_tickets WHERE booking_id = :booking_id'
        );
        $existingStatement->execute(['booking_id' => $bookingId]);
        $existingCount = (int) $existingStatement->fetchColumn();
        $expectedCount = (int) $booking['total_quantity'];
        if ($existingCount === $expectedCount) {
            return;
        }
        if ($existingCount !== 0) {
            throw new DomainException('Ticket issuance is incomplete for this booking.');
        }

        // Locking the event serializes event-wide seat allocation across
        // simultaneous browser callbacks and webhook confirmations.
        $eventStatement = $database->prepare(
            'SELECT event_capacity FROM events WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $eventStatement->execute(['id' => $booking['event_id']]);
        $eventCapacity = $eventStatement->fetchColumn();
        if ($eventCapacity === false) {
            throw new DomainException('The ticket event could not be found.');
        }

        $seatStatement = $database->prepare(
            'SELECT seat_number FROM issued_tickets
             WHERE event_id = :event_id AND active_seat_slot = 1
             ORDER BY seat_number FOR UPDATE'
        );
        $seatStatement->execute(['event_id' => $booking['event_id']]);
        $occupied = array_fill_keys(array_map('intval', $seatStatement->fetchAll(\PDO::FETCH_COLUMN)), true);
        $availableSeats = [];
        for ($seat = 1; $seat <= (int) $eventCapacity && count($availableSeats) < $expectedCount; $seat++) {
            if (!isset($occupied[$seat])) {
                $availableSeats[] = $seat;
            }
        }
        if (count($availableSeats) !== $expectedCount) {
            throw new DomainException('The event does not have enough assignable seats.');
        }

        $itemsStatement = $database->prepare(
            'SELECT id, ticket_type_id, quantity
             FROM booking_items WHERE booking_id = :booking_id ORDER BY id FOR UPDATE'
        );
        $itemsStatement->execute(['booking_id' => $bookingId]);
        $items = $itemsStatement->fetchAll();
        $itemQuantity = array_sum(array_map(
            static fn (array $item): int => (int) $item['quantity'],
            $items
        ));
        if ($itemQuantity !== $expectedCount) {
            throw new DomainException('The booking quantity does not match its ticket items.');
        }

        $insert = $database->prepare(
            "INSERT INTO issued_tickets
                (ticket_code, booking_id, booking_item_id, event_id, ticket_type_id,
                 seat_number, status, active_seat_slot)
             VALUES
                (:ticket_code, :booking_id, :booking_item_id, :event_id, :ticket_type_id,
                 :seat_number, 'valid', 1)"
        );
        $seatIndex = 0;
        foreach ($items as $item) {
            for ($quantity = 0; $quantity < (int) $item['quantity']; $quantity++) {
                $insert->execute([
                    'ticket_code' => $this->ticketCode(),
                    'booking_id' => $bookingId,
                    'booking_item_id' => $item['id'],
                    'event_id' => $booking['event_id'],
                    'ticket_type_id' => $item['ticket_type_id'],
                    'seat_number' => $availableSeats[$seatIndex++],
                ]);
            }
        }
    }

    private function ticketCode(): string
    {
        return 'TKT-' . strtoupper(bin2hex(random_bytes(8)));
    }
}
