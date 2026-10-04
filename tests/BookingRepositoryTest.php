<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\BookingRepository;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertBookingTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$database->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $createUser = $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, :role, 'approved')"
    );

    $createUser->execute([
        'name' => 'Booking Test Organizer',
        'email' => 'booking-organizer-' . $suffix . '@example.test',
        'phone' => '+91880000' . $suffix,
        'password_hash' => $passwordHash,
        'role' => 'organizer',
    ]);
    $organizerId = (int) $database->lastInsertId();

    $customerIds = [];
    foreach ([1, 2] as $number) {
        $createUser->execute([
            'name' => 'Booking Test Customer ' . $number,
            'email' => 'booking-customer-' . $suffix . '-' . $number . '@example.test',
            'phone' => '+91770000' . $suffix . $number,
            'password_hash' => $passwordHash,
            'role' => 'customer',
        ]);
        $customerIds[] = (int) $database->lastInsertId();
    }
    [$ownerId, $otherCustomerId] = $customerIds;

    $database->prepare('INSERT INTO event_categories (name) VALUES (:name)')
        ->execute(['name' => 'Booking Test ' . $suffix]);
    $categoryId = (int) $database->lastInsertId();

    $database->prepare(
        'INSERT INTO halls (name, address, city, maximum_capacity)
         VALUES (:name, :address, :city, 100)'
    )->execute([
        'name' => 'Searchable Hall ' . $suffix,
        'address' => '1 Test Street',
        'city' => 'Test City',
    ]);
    $hallId = (int) $database->lastInsertId();

    $database->prepare(
        "INSERT INTO events
            (organizer_id, hall_id, category_id, title, description, start_datetime,
             end_datetime, sale_start_datetime, sale_end_datetime, event_capacity, status)
         VALUES
            (:organizer_id, :hall_id, :category_id, :title, 'Test event', '2035-06-15 12:00:00',
             '2035-06-15 14:00:00', '2035-01-01 00:00:00', '2035-06-15 11:00:00', 100, 'approved')"
    )->execute([
        'organizer_id' => $organizerId,
        'hall_id' => $hallId,
        'category_id' => $categoryId,
        'title' => 'Searchable Concert ' . $suffix,
    ]);
    $eventId = (int) $database->lastInsertId();

    $createBooking = $database->prepare(
        'INSERT INTO bookings
            (booking_reference, customer_id, event_id, status, total_quantity, total_amount, booked_at)
         VALUES
            (:reference, :customer_id, :event_id, :status, 1, :amount, :booked_at)'
    );
    $ownerBookings = [
        ['reference' => 'BH-A-' . $suffix, 'status' => 'confirmed', 'amount' => '500.00', 'booked_at' => '2035-02-01 10:00:00'],
        ['reference' => 'BH-B-' . $suffix, 'status' => 'pending_payment', 'amount' => '1200.00', 'booked_at' => '2035-03-01 10:00:00'],
        ['reference' => 'BH-C-' . $suffix, 'status' => 'payment_failed', 'amount' => '250.00', 'booked_at' => '2035-04-01 10:00:00'],
    ];
    foreach ($ownerBookings as $booking) {
        $createBooking->execute($booking + ['customer_id' => $ownerId, 'event_id' => $eventId]);
    }
    $createBooking->execute([
        'reference' => 'BH-OTHER-' . $suffix,
        'customer_id' => $otherCustomerId,
        'event_id' => $eventId,
        'status' => 'confirmed',
        'amount' => '900.00',
        'booked_at' => '2035-03-15 10:00:00',
    ]);

    $repository = new BookingRepository();
    assertBookingTest($repository->countForCustomer($ownerId) === 3, 'Owner total booking count is incorrect.');
    assertBookingTest(count($repository->forCustomer($ownerId)) === 3, 'Owner booking history is incomplete.');
    assertBookingTest(count($repository->forCustomer($otherCustomerId)) === 1, 'Customer isolation failed.');

    $referenceResults = $repository->forCustomer($ownerId, ['search' => strtolower('BH-B-' . $suffix)]);
    assertBookingTest(count($referenceResults) === 1, 'Booking reference search did not return one result.');
    assertBookingTest($referenceResults[0]['booking_reference'] === 'BH-B-' . $suffix, 'Reference search returned the wrong booking.');

    $eventResults = $repository->forCustomer($ownerId, ['search' => 'searchable concert']);
    assertBookingTest(count($eventResults) === 3, 'Event title search did not return the owner bookings.');
    $hallResults = $repository->forCustomer($ownerId, ['search' => 'searchable hall']);
    assertBookingTest(count($hallResults) === 3, 'Venue search did not return the owner bookings.');

    $statusResults = $repository->forCustomer($ownerId, ['status' => 'confirmed']);
    assertBookingTest(count($statusResults) === 1 && $statusResults[0]['status'] === 'confirmed', 'Status filtering failed.');

    $dateResults = $repository->forCustomer($ownerId, [
        'date_from_utc' => '2035-03-01 00:00:00',
        'date_to_utc' => '2035-04-01 00:00:00',
    ]);
    assertBookingTest(count($dateResults) === 1 && $dateResults[0]['booking_reference'] === 'BH-B-' . $suffix, 'Booking date filtering failed.');

    $oldestResults = $repository->forCustomer($ownerId, ['sort' => 'oldest']);
    assertBookingTest($oldestResults[0]['booking_reference'] === 'BH-A-' . $suffix, 'Oldest-first sorting failed.');
    $amountResults = $repository->forCustomer($ownerId, ['sort' => 'amount_high']);
    assertBookingTest($amountResults[0]['booking_reference'] === 'BH-B-' . $suffix, 'Amount sorting failed.');

    $database->rollBack();
    fwrite(STDOUT, "Booking repository tests passed.\n");
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
