<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\BookingCancellationService;
use App\Services\CancellationPolicy;
use App\Services\RazorpayService;
use App\Services\RefundService;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertBookingCancellation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$suffix = bin2hex(random_bytes(5));
$created = [];
$gateway = new class extends RazorpayService {
    public array $calls = [];

    public function createRefund(string $paymentId, int $amountPaise, string $receipt, string $idempotencyKey): array
    {
        $this->calls[] = compact('paymentId', 'amountPaise', 'receipt', 'idempotencyKey');
        return [
            'id' => 'rfnd_' . substr(hash('sha256', $idempotencyKey), 0, 18),
            'payment_id' => $paymentId,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'status' => 'processed',
        ];
    }
};

try {
    $database->prepare("INSERT INTO users (name, email, phone, password_hash, role, account_status) VALUES (?, ?, ?, ?, 'organizer', 'approved')")
        ->execute(['Cancel Organizer', "cancel-organizer-{$suffix}@example.test", '9000000001', password_hash('Example123!', PASSWORD_DEFAULT)]);
    $created['organizer'] = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO users (name, email, phone, password_hash, role, account_status) VALUES (?, ?, ?, ?, 'customer', 'approved')")
        ->execute(['Cancel Customer', "cancel-customer-{$suffix}@example.test", '9000000002', password_hash('Example123!', PASSWORD_DEFAULT)]);
    $created['customer'] = (int) $database->lastInsertId();
    $database->prepare('INSERT INTO halls (name, address, city, maximum_capacity) VALUES (?, ?, ?, ?)')
        ->execute(["Cancellation Hall {$suffix}", 'Test address', 'Test city', 20]);
    $created['hall'] = (int) $database->lastInsertId();
    $category = (int) $database->query('SELECT id FROM event_categories ORDER BY id LIMIT 1')->fetchColumn();
    $database->prepare(
        "INSERT INTO events
            (organizer_id, hall_id, category_id, title, description, start_datetime, end_datetime,
             sale_start_datetime, sale_end_datetime, event_capacity, status)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 10 DAY),
                 DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 10 DAY) + INTERVAL 2 HOUR,
                 DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 DAY), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 9 DAY), 20, 'approved')"
    )->execute([$created['organizer'], $created['hall'], $category, "Cancellation Event {$suffix}", 'Cancellation test event']);
    $created['event'] = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO ticket_types (event_id, name, price, capacity, sold_quantity, is_active) VALUES (?, 'Regular', 100.00, 10, 3, 1)")
        ->execute([$created['event']]);
    $created['ticket_type'] = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO bookings (booking_reference, customer_id, event_id, status, total_quantity, total_amount, confirmed_at) VALUES (?, ?, ?, 'confirmed', 3, 300.00, UTC_TIMESTAMP(6))")
        ->execute(['EBS-CANCEL-' . strtoupper($suffix), $created['customer'], $created['event']]);
    $created['booking'] = (int) $database->lastInsertId();
    $database->prepare('INSERT INTO booking_items (booking_id, event_id, ticket_type_id, quantity, unit_price) VALUES (?, ?, ?, 3, 100.00)')
        ->execute([$created['booking'], $created['event'], $created['ticket_type']]);
    $created['item'] = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO payments (booking_id, provider_order_id, provider_payment_id, amount, currency, status, captured_at) VALUES (?, ?, ?, 300.00, 'INR', 'captured', UTC_TIMESTAMP(6))")
        ->execute([$created['booking'], 'order_' . $suffix, 'pay_' . $suffix]);
    $created['payment'] = (int) $database->lastInsertId();
    $ticketInsert = $database->prepare("INSERT INTO issued_tickets (ticket_code, booking_id, booking_item_id, event_id, ticket_type_id, seat_number, status, active_seat_slot) VALUES (?, ?, ?, ?, ?, ?, 'valid', 1)");
    for ($seat = 1; $seat <= 3; $seat++) {
        $ticketInsert->execute(['TKT-' . strtoupper($suffix) . '-' . $seat, $created['booking'], $created['item'], $created['event'], $created['ticket_type'], $seat]);
    }

    $service = new BookingCancellationService(new CancellationPolicy(), new RefundService($gateway));
    $firstToken = bin2hex(random_bytes(32));
    $result = $service->cancel($created['booking'], $created['customer'], [$created['ticket_type'] => 2], null, $firstToken);
    assertBookingCancellation($result['refund_amount'] === '200.00', 'The 100% partial refund amount is incorrect.');
    assertBookingCancellation($result['refund_status'] === 'processed', 'The fake refund was not processed.');
    assertBookingCancellation(count($gateway->calls) === 1, 'The provider should receive exactly one refund call.');
    assertBookingCancellation($gateway->calls[0]['amountPaise'] === 20000, 'The provider refund amount is incorrect.');

    $bookingStatus = $database->query('SELECT status FROM bookings WHERE id = ' . $created['booking'])->fetchColumn();
    assertBookingCancellation($bookingStatus === 'partially_cancelled', 'The booking did not become partially cancelled.');
    $sold = (int) $database->query('SELECT sold_quantity FROM ticket_types WHERE id = ' . $created['ticket_type'])->fetchColumn();
    assertBookingCancellation($sold === 1, 'Sold inventory was not restored.');
    $cancelledSeats = $database->query("SELECT seat_number FROM issued_tickets WHERE booking_id = {$created['booking']} AND status = 'cancelled' ORDER BY seat_number")->fetchAll(PDO::FETCH_COLUMN);
    assertBookingCancellation(array_map('intval', $cancelledSeats) === [2, 3], 'The highest seats were not selected deterministically.');
    $paymentStatus = $database->query('SELECT status FROM payments WHERE id = ' . $created['payment'])->fetchColumn();
    assertBookingCancellation($paymentStatus === 'partially_refunded', 'Payment did not become partially refunded.');

    $duplicate = $service->cancel($created['booking'], $created['customer'], [$created['ticket_type'] => 2], null, $firstToken);
    assertBookingCancellation($duplicate['duplicate'] === true, 'A repeated cancellation token was not treated idempotently.');
    assertBookingCancellation(count($gateway->calls) === 1, 'A repeated cancellation created another provider refund.');

    $result = $service->cancel($created['booking'], $created['customer'], [$created['ticket_type'] => 1], 'Cannot attend', bin2hex(random_bytes(32)));
    assertBookingCancellation($result['refund_amount'] === '100.00', 'Final refund amount is incorrect.');
    assertBookingCancellation($database->query('SELECT status FROM bookings WHERE id = ' . $created['booking'])->fetchColumn() === 'customer_cancelled', 'Booking did not become fully cancelled.');
    assertBookingCancellation($database->query('SELECT status FROM payments WHERE id = ' . $created['payment'])->fetchColumn() === 'refunded', 'Payment did not become fully refunded.');
    assertBookingCancellation((int) $database->query('SELECT sold_quantity FROM ticket_types WHERE id = ' . $created['ticket_type'])->fetchColumn() === 0, 'Final inventory was not restored.');

    fwrite(STDOUT, "Booking cancellation service tests passed.\n");
} finally {
    if (isset($created['booking'])) {
        $database->prepare('DELETE FROM notifications WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM booking_cancellation_actions WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM ticket_cancellations WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM refunds WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM issued_tickets WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM payments WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM booking_items WHERE booking_id = ?')->execute([$created['booking']]);
        $database->prepare('DELETE FROM bookings WHERE id = ?')->execute([$created['booking']]);
    }
    if (isset($created['ticket_type'])) {
        $database->prepare('DELETE FROM ticket_types WHERE id = ?')->execute([$created['ticket_type']]);
    }
    if (isset($created['event'])) {
        $database->prepare('DELETE FROM event_status_history WHERE event_id = ?')->execute([$created['event']]);
        $database->prepare('DELETE FROM events WHERE id = ?')->execute([$created['event']]);
    }
    if (isset($created['hall'])) {
        $database->prepare('DELETE FROM halls WHERE id = ?')->execute([$created['hall']]);
    }
    foreach (['customer', 'organizer'] as $key) {
        if (isset($created[$key])) {
            $database->prepare('DELETE FROM users WHERE id = ?')->execute([$created[$key]]);
        }
    }
}
