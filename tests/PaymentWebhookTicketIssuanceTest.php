<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Services\PaymentWebhookService;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertWebhookTicket(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$created = ['users' => [], 'category' => null, 'hall' => null, 'event' => null, 'booking' => null];
$eventIds = [];

try {
    $suffix = bin2hex(random_bytes(5));
    $secret = 'webhook-test-' . $suffix;
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $user = $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, :role, 'approved')"
    );
    foreach ([['Webhook Organizer', 'organizer'], ['Webhook Customer', 'customer']] as $index => [$name, $role]) {
        $user->execute([
            'name' => $name,
            'email' => "webhook-{$suffix}-{$index}@example.test",
            'phone' => '+9192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);
        $created['users'][] = (int) $database->lastInsertId();
    }
    [$organizerId, $customerId] = $created['users'];

    $database->prepare('INSERT INTO event_categories (name) VALUES (:name)')
        ->execute(['name' => 'Webhook Test ' . $suffix]);
    $created['category'] = (int) $database->lastInsertId();
    $database->prepare(
        "INSERT INTO halls (name, address, city, maximum_capacity)
         VALUES (:name, '1 Webhook Street', 'Test City', 10)"
    )->execute(['name' => 'Webhook Hall ' . $suffix]);
    $created['hall'] = (int) $database->lastInsertId();

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $database->prepare(
        "INSERT INTO events
            (organizer_id, hall_id, category_id, title, description, start_datetime,
             end_datetime, sale_start_datetime, sale_end_datetime, event_capacity, status)
         VALUES (:organizer_id, :hall_id, :category_id, :title, 'Webhook ticket test',
                 :starts, :ends, :sale_starts, :sale_ends, 10, 'approved')"
    )->execute([
        'organizer_id' => $organizerId,
        'hall_id' => $created['hall'],
        'category_id' => $created['category'],
        'title' => 'Webhook Event ' . $suffix,
        'starts' => $now->modify('+2 days')->format('Y-m-d H:i:s.u'),
        'ends' => $now->modify('+2 days +2 hours')->format('Y-m-d H:i:s.u'),
        'sale_starts' => $now->modify('-1 day')->format('Y-m-d H:i:s.u'),
        'sale_ends' => $now->modify('+1 day')->format('Y-m-d H:i:s.u'),
    ]);
    $created['event'] = (int) $database->lastInsertId();

    $database->prepare(
        "INSERT INTO ticket_types
            (event_id, name, price, capacity, reserved_quantity, display_order)
         VALUES (:event_id, 'Regular', 300.00, 5, 2, 1)"
    )->execute(['event_id' => $created['event']]);
    $ticketTypeId = (int) $database->lastInsertId();

    $database->prepare(
        "INSERT INTO bookings
            (booking_reference, customer_id, event_id, status, total_quantity,
             total_amount, reservation_expires_at)
         VALUES (:reference, :customer_id, :event_id, 'pending_payment', 2, 600.00, :expires)"
    )->execute([
        'reference' => 'EBS-WEB-' . strtoupper(substr($suffix, 0, 8)),
        'customer_id' => $customerId,
        'event_id' => $created['event'],
        'expires' => $now->modify('+15 minutes')->format('Y-m-d H:i:s.u'),
    ]);
    $created['booking'] = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO booking_items (booking_id, event_id, ticket_type_id, quantity, unit_price)
         VALUES (:booking_id, :event_id, :ticket_type_id, 2, 300.00)'
    )->execute([
        'booking_id' => $created['booking'],
        'event_id' => $created['event'],
        'ticket_type_id' => $ticketTypeId,
    ]);

    $orderId = 'order_webhook_' . $suffix;
    $paymentId = 'pay_webhook_' . $suffix;
    $database->prepare(
        "INSERT INTO payments (booking_id, provider_order_id, amount, currency, status)
         VALUES (:booking_id, :order_id, 600.00, 'INR', 'created')"
    )->execute(['booking_id' => $created['booking'], 'order_id' => $orderId]);

    Config::set('payment', ['razorpay' => ['webhook_secret' => $secret]]);
    $payload = json_encode([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => $paymentId,
            'order_id' => $orderId,
            'amount' => 60000,
            'currency' => 'INR',
            'status' => 'captured',
        ]]],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $payload, $secret);
    $eventIds = ['evt_' . $suffix . '_1', 'evt_' . $suffix . '_2'];
    $service = new PaymentWebhookService();

    assertWebhookTicket(
        $service->process($payload, $signature, $eventIds[0]) === 'processed',
        'Captured webhook was not processed.'
    );
    $ticketQuery = $database->prepare(
        'SELECT ticket_code, seat_number FROM issued_tickets WHERE booking_id = :booking_id ORDER BY seat_number'
    );
    $ticketQuery->execute(['booking_id' => $created['booking']]);
    $tickets = $ticketQuery->fetchAll();
    assertWebhookTicket(count($tickets) === 2, 'Webhook did not issue two tickets.');
    assertWebhookTicket(array_column($tickets, 'seat_number') === [1, 2], 'Webhook seats are incorrect.');
    assertWebhookTicket(
        count(array_unique(array_column($tickets, 'ticket_code'))) === 2,
        'Webhook ticket codes are not unique.'
    );
    assertWebhookTicket(
        $service->process($payload, $signature, $eventIds[0]) === 'duplicate',
        'Duplicate webhook event was not ignored.'
    );
    assertWebhookTicket(
        $service->process($payload, $signature, $eventIds[1]) === 'processed',
        'Repeated captured payment event was not handled idempotently.'
    );
    $ticketQuery->execute(['booking_id' => $created['booking']]);
    assertWebhookTicket(count($ticketQuery->fetchAll()) === 2, 'Repeated webhook created duplicate tickets.');

    fwrite(STDOUT, "Payment webhook ticket issuance tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($eventIds !== []) {
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $database->prepare("DELETE FROM payment_webhook_events WHERE provider_event_id IN ($placeholders)")
            ->execute($eventIds);
    }
    if ($created['booking'] !== null) {
        $database->prepare('DELETE FROM notifications WHERE booking_id = :id')->execute(['id' => $created['booking']]);
        $database->prepare('DELETE FROM issued_tickets WHERE booking_id = :id')->execute(['id' => $created['booking']]);
        $database->prepare('DELETE FROM payments WHERE booking_id = :id')->execute(['id' => $created['booking']]);
        $database->prepare('DELETE FROM booking_items WHERE booking_id = :id')->execute(['id' => $created['booking']]);
        $database->prepare('DELETE FROM bookings WHERE id = :id')->execute(['id' => $created['booking']]);
    }
    if ($created['event'] !== null) {
        $database->prepare('DELETE FROM ticket_types WHERE event_id = :id')->execute(['id' => $created['event']]);
        $database->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $created['event']]);
    }
    if ($created['hall'] !== null) {
        $database->prepare('DELETE FROM halls WHERE id = :id')->execute(['id' => $created['hall']]);
    }
    if ($created['category'] !== null) {
        $database->prepare('DELETE FROM event_categories WHERE id = :id')->execute(['id' => $created['category']]);
    }
    if ($created['users'] !== []) {
        $placeholders = implode(',', array_fill(0, count($created['users']), '?'));
        $database->prepare("DELETE FROM users WHERE id IN ($placeholders)")->execute($created['users']);
    }
}

exit($exitCode ?? 0);
