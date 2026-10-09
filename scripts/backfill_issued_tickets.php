<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\TicketIssuanceService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap/app.php';

$database = Database::connection();
$bookingIds = $database->query(
    "SELECT b.id
     FROM bookings b
     LEFT JOIN issued_tickets it ON it.booking_id = b.id
     WHERE b.status = 'confirmed'
     GROUP BY b.id, b.event_id, b.confirmed_at
     HAVING COUNT(it.id) = 0
     ORDER BY b.event_id, b.confirmed_at, b.id"
)->fetchAll(PDO::FETCH_COLUMN);

$issuedBookings = 0;
foreach ($bookingIds as $bookingId) {
    $database->beginTransaction();
    try {
        (new TicketIssuanceService())->issueForConfirmedBooking((int) $bookingId);
        $database->commit();
        $issuedBookings++;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw new RuntimeException('Existing ticket backfill failed.', 0, $exception);
    }
}

fwrite(STDOUT, "Ticket backfill completed for {$issuedBookings} confirmed booking(s).\n");
