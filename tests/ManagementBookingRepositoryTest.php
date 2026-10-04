<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\ManagementBookingRepository;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertManagementBooking(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$database->beginTransaction();

try {
    $baselineMetrics = (new ManagementBookingRepository())->adminMetrics();
    $suffix = bin2hex(random_bytes(4));
    $hash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $createUser = $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, :role, 'approved')"
    );
    $userIds = [];
    foreach ([
        ['Report Organizer A', 'organizer', '+919111111111'],
        ['Report Organizer B', 'organizer', '+919222222222'],
        ['Report Customer A', 'customer', '+919333333333'],
        ['Report Customer B', 'customer', '+919444444444'],
    ] as $index => [$name, $role, $phone]) {
        $createUser->execute([
            'name' => $name,
            'email' => "report-{$suffix}-{$index}@example.test",
            'phone' => $phone,
            'password_hash' => $hash,
            'role' => $role,
        ]);
        $userIds[] = (int) $database->lastInsertId();
    }
    [$organizerA, $organizerB, $customerA, $customerB] = $userIds;

    $database->prepare('INSERT INTO event_categories (name) VALUES (:name)')->execute(['name' => 'Reports ' . $suffix]);
    $categoryId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO halls (name, address, city, maximum_capacity) VALUES (:name, '1 Report Road', 'Test City', 100)")
        ->execute(['name' => 'Report Hall ' . $suffix]);
    $hallId = (int) $database->lastInsertId();

    $createEvent = $database->prepare(
        "INSERT INTO events (organizer_id, hall_id, category_id, title, description,
            start_datetime, end_datetime, sale_start_datetime, sale_end_datetime,
            event_capacity, status)
         VALUES (:organizer_id, :hall_id, :category_id, :title, 'Report event',
            '2035-06-15 12:00:00', '2035-06-15 14:00:00', '2035-01-01 00:00:00',
            '2035-06-15 11:00:00', 50, 'approved')"
    );
    $eventIds = [];
    foreach ([[$organizerA, 'Alpha Event'], [$organizerB, 'Beta Event']] as [$organizerId, $title]) {
        $createEvent->execute(['organizer_id' => $organizerId, 'hall_id' => $hallId, 'category_id' => $categoryId, 'title' => $title . ' ' . $suffix]);
        $eventIds[] = (int) $database->lastInsertId();
    }
    [$eventA, $eventB] = $eventIds;

    $createTicket = $database->prepare("INSERT INTO ticket_types (event_id, name, price, capacity) VALUES (:event_id, :name, :price, 50)");
    $createTicket->execute(['event_id' => $eventA, 'name' => 'Regular', 'price' => '300.00']);
    $ticketA = (int) $database->lastInsertId();
    $createTicket->execute(['event_id' => $eventA, 'name' => 'Free', 'price' => '0.00']);
    $ticketFree = (int) $database->lastInsertId();
    $createTicket->execute(['event_id' => $eventB, 'name' => 'Standard', 'price' => '400.00']);
    $ticketB = (int) $database->lastInsertId();

    $createBooking = $database->prepare(
        "INSERT INTO bookings (booking_reference, customer_id, event_id, status,
            total_quantity, total_amount, booked_at, confirmed_at)
         VALUES (:reference, :customer_id, :event_id, 'confirmed', :quantity, :amount, :booked_at, :confirmed_at)"
    );
    $bookingData = [
        ['REP-A-' . $suffix, $customerA, $eventA, 2, '600.00', '2035-02-01 10:00:00', $ticketA, '300.00'],
        ['REP-F-' . $suffix, $customerB, $eventA, 1, '0.00', '2035-02-02 10:00:00', $ticketFree, '0.00'],
        ['REP-B-' . $suffix, $customerA, $eventB, 1, '400.00', '2035-02-03 10:00:00', $ticketB, '400.00'],
    ];
    $bookingIds = [];
    foreach ($bookingData as [$reference, $customerId, $eventId, $quantity, $amount, $bookedAt, $ticketId, $unitPrice]) {
        $createBooking->execute([
            'reference' => $reference,
            'customer_id' => $customerId,
            'event_id' => $eventId,
            'quantity' => $quantity,
            'amount' => $amount,
            'booked_at' => $bookedAt,
            'confirmed_at' => $bookedAt,
        ]);
        $bookingId = (int) $database->lastInsertId();
        $bookingIds[] = $bookingId;
        $database->prepare(
            'INSERT INTO booking_items (booking_id, event_id, ticket_type_id, quantity, unit_price)
             VALUES (:booking_id, :event_id, :ticket_id, :quantity, :unit_price)'
        )->execute(['booking_id' => $bookingId, 'event_id' => $eventId, 'ticket_id' => $ticketId, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
    }
    [$bookingA, $bookingFree, $bookingB] = $bookingIds;

    $payment = $database->prepare(
        "INSERT INTO payments (booking_id, provider_order_id, provider_payment_id, amount, status, created_at)
         VALUES (:booking_id, :order_id, :payment_id, :amount, :status, :created_at)"
    );
    $payment->execute(['booking_id' => $bookingA, 'order_id' => 'order_old_' . $suffix, 'payment_id' => null, 'amount' => '600.00', 'status' => 'failed', 'created_at' => '2035-02-01 10:01:00']);
    $payment->execute(['booking_id' => $bookingA, 'order_id' => 'order_new_' . $suffix, 'payment_id' => 'pay_a_' . $suffix, 'amount' => '600.00', 'status' => 'captured', 'created_at' => '2035-02-01 10:02:00']);
    $payment->execute(['booking_id' => $bookingB, 'order_id' => 'order_b_' . $suffix, 'payment_id' => 'pay_b_' . $suffix, 'amount' => '400.00', 'status' => 'captured', 'created_at' => '2035-02-03 10:02:00']);

    $repository = new ManagementBookingRepository();
    $organizerRows = $repository->forOrganizer($organizerA, ['sort' => 'newest'], 1, 20);
    assertManagementBooking(count($organizerRows) === 2, 'Organizer booking scope is incomplete or leaked another organizer.');
    assertManagementBooking(!array_key_exists('customer_phone', $organizerRows[0]), 'Organizer result exposed customer phone.');
    assertManagementBooking($repository->findForOrganizer($bookingB, $organizerA) === null, 'Organizer accessed another organizer booking.');
    assertManagementBooking($repository->findForOrganizer($bookingA, $organizerA) !== null, 'Organizer could not access an owned booking.');

    $adminRows = $repository->forAdmin(['search' => strtolower($suffix), 'sort' => 'newest'], 1, 20);
    assertManagementBooking(count($adminRows) === 3, 'Admin did not receive all bookings exactly once; received ' . count($adminRows) . '.');
    assertManagementBooking(array_key_exists('customer_phone', $adminRows[0]), 'Admin result omitted customer phone.');
    $paidRows = $repository->forAdmin(['search' => strtolower($suffix), 'payment_status' => 'captured', 'sort' => 'newest'], 1, 20);
    assertManagementBooking(count($paidRows) === 2, 'Latest-payment filtering is incorrect.');
    $freeRows = $repository->forAdmin(['search' => strtolower($suffix), 'payment_status' => 'not_required', 'sort' => 'newest'], 1, 20);
    assertManagementBooking(count($freeRows) === 1 && (int) $freeRows[0]['id'] === $bookingFree, 'Free payment labeling is incorrect.');
    assertManagementBooking(count($repository->forAdmin(['search' => strtolower('Alpha Event'), 'sort' => 'newest'], 1, 20)) === 2, 'Event search failed.');
    assertManagementBooking(count($repository->forAdmin(['organizer_id' => $organizerB, 'sort' => 'newest'], 1, 20)) === 1, 'Organizer filter failed.');
    assertManagementBooking(count($repository->forAdmin(['search' => strtolower($suffix), 'sort' => 'newest'], 2, 1)) === 1, 'Pagination failed.');

    $metricsA = $repository->organizerMetrics($organizerA);
    assertManagementBooking($metricsA['confirmed_bookings'] === 2, 'Organizer confirmed booking metric is incorrect.');
    assertManagementBooking($metricsA['confirmed_tickets'] === 3, 'Organizer ticket metric is incorrect.');
    assertManagementBooking($metricsA['confirmed_customers'] === 2, 'Organizer attendee metric is incorrect.');
    assertManagementBooking((string) $metricsA['confirmed_revenue'] === '600.00', 'Organizer revenue metric is incorrect.');

    $adminMetrics = $repository->adminMetrics();
    assertManagementBooking($adminMetrics['total_bookings'] === $baselineMetrics['total_bookings'] + 3, 'Admin booking metric is incorrect.');
    assertManagementBooking(abs((float) $adminMetrics['confirmed_revenue'] - ((float) $baselineMetrics['confirmed_revenue'] + 1000.0)) < 0.001, 'Admin revenue metric is incorrect.');
    $customer = $repository->customer($customerA);
    assertManagementBooking($customer !== null && (int) $customer['booking_count'] === 2, 'Customer profile booking metric is incorrect.');
    assertManagementBooking(count($repository->customers(['search' => strtolower('Report Customer A'), 'account_status' => 'any'], 1, 20)) === 1, 'Customer search failed.');

    $database->rollBack();
    fwrite(STDOUT, "Management booking repository tests passed.\n");
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    fwrite(STDERR, $exception->__toString() . PHP_EOL);
    exit(1);
}
