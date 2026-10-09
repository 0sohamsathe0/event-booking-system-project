<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\BookingRepository;
use App\Repositories\IssuedTicketRepository;
use App\Services\BookingService;
use App\Services\RazorpayService;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertBookingService(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectBookingDomainException(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException) {
        return;
    }

    throw new RuntimeException($message);
}

$database = Database::connection();
$created = [
    'users' => [],
    'hall' => null,
    'category' => null,
    'event' => null,
];

try {
    $suffix = bin2hex(random_bytes(5));
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $createUser = $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, :role, 'approved')"
    );
    foreach ([
        ['Booking Service Organizer', 'organizer'],
        ['Booking Service Customer One', 'customer'],
        ['Booking Service Customer Two', 'customer'],
    ] as $index => [$name, $role]) {
        $createUser->execute([
            'name' => $name,
            'email' => "booking-service-{$suffix}-{$index}@example.test",
            'phone' => '+9191000' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);
        $created['users'][] = (int) $database->lastInsertId();
    }
    [$organizerId, $customerOneId, $customerTwoId] = $created['users'];

    $database->prepare('INSERT INTO event_categories (name) VALUES (:name)')
        ->execute(['name' => 'Booking Service Test ' . $suffix]);
    $created['category'] = (int) $database->lastInsertId();

    $database->prepare(
        'INSERT INTO halls (name, address, city, maximum_capacity)
         VALUES (:name, :address, :city, 50)'
    )->execute([
        'name' => 'Booking Service Hall ' . $suffix,
        'address' => '1 Transaction Street',
        'city' => 'Test City',
    ]);
    $created['hall'] = (int) $database->lastInsertId();

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $database->prepare(
        "INSERT INTO events
            (organizer_id, hall_id, category_id, title, description, start_datetime,
             end_datetime, sale_start_datetime, sale_end_datetime, event_capacity, status)
         VALUES
            (:organizer_id, :hall_id, :category_id, :title, 'Booking service regression event',
             :starts, :ends, :sale_starts, :sale_ends, 50, 'approved')"
    )->execute([
        'organizer_id' => $organizerId,
        'hall_id' => $created['hall'],
        'category_id' => $created['category'],
        'title' => 'Booking Service Event ' . $suffix,
        'starts' => $now->modify('+2 days')->format('Y-m-d H:i:s.u'),
        'ends' => $now->modify('+2 days +2 hours')->format('Y-m-d H:i:s.u'),
        'sale_starts' => $now->modify('-1 day')->format('Y-m-d H:i:s.u'),
        'sale_ends' => $now->modify('+1 day')->format('Y-m-d H:i:s.u'),
    ]);
    $created['event'] = (int) $database->lastInsertId();

    $createTicket = $database->prepare(
        'INSERT INTO ticket_types (event_id, name, price, capacity, display_order)
         VALUES (:event_id, :name, :price, :capacity, :display_order)'
    );
    $ticketIds = [];
    foreach ([
        ['Regular', '300.00', 5, 1],
        ['Community', '0.00', 5, 2],
        ['Last Seat', '0.00', 1, 3],
    ] as [$name, $price, $capacity, $displayOrder]) {
        $createTicket->execute([
            'event_id' => $created['event'],
            'name' => $name,
            'price' => $price,
            'capacity' => $capacity,
            'display_order' => $displayOrder,
        ]);
        $ticketIds[$name] = (int) $database->lastInsertId();
    }

    $gateway = new class extends RazorpayService {
        public function isConfigured(): bool { return true; }
        public function keyId(): string { return 'rzp_test_regression'; }
        public function createOrder(int $amountPaise, string $receipt): array
        {
            return ['id' => 'order_regression_' . substr(hash('sha256', $receipt), 0, 12)];
        }
        public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
        {
            return $signature === 'valid-signature';
        }
        public function fetchPayment(string $paymentId): array
        {
            $payment = Database::connection()->prepare(
                'SELECT provider_order_id, amount FROM payments
                 WHERE provider_payment_id = :payment_id OR provider_payment_id IS NULL
                 ORDER BY provider_payment_id IS NOT NULL DESC, id DESC LIMIT 1'
            );
            $payment->execute(['payment_id' => $paymentId]);
            $record = $payment->fetch();
            return [
                'order_id' => $record['provider_order_id'],
                'amount' => (int) round((float) $record['amount'] * 100),
                'currency' => 'INR',
                'status' => 'captured',
            ];
        }
    };
    $service = new BookingService($gateway);

    $database->prepare(
        "INSERT INTO bookings
            (booking_reference, customer_id, event_id, status, total_quantity,
             total_amount, reservation_expires_at)
         VALUES (:reference, :customer_id, :event_id, 'pending_payment', 1, 0.00, :expires)"
    )->execute([
        'reference' => 'EBS-TEST-' . strtoupper(substr($suffix, 0, 8)),
        'customer_id' => $customerTwoId,
        'event_id' => $created['event'],
        'expires' => $now->modify('-1 minute')->format('Y-m-d H:i:s.u'),
    ]);
    $staleBookingId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO booking_items (booking_id, event_id, ticket_type_id, quantity, unit_price)
         VALUES (:booking_id, :event_id, :ticket_type_id, 1, 0.00)'
    )->execute([
        'booking_id' => $staleBookingId,
        'event_id' => $created['event'],
        'ticket_type_id' => $ticketIds['Community'],
    ]);
    $database->prepare(
        'UPDATE ticket_types SET reserved_quantity = reserved_quantity + 1 WHERE id = :id'
    )->execute(['id' => $ticketIds['Community']]);
    $database->prepare(
        "INSERT INTO payments (booking_id, amount, currency, status)
         VALUES (:booking_id, 0.00, 'INR', 'created')"
    )->execute(['booking_id' => $staleBookingId]);

    expectBookingDomainException(
        static fn () => $service->reserve($customerOneId, $created['event'], []),
        'A zero-ticket booking was accepted.'
    );
    expectBookingDomainException(
        static fn () => $service->reserve($customerOneId, $created['event'], [$ticketIds['Regular'] => 11]),
        'A booking above the ten-ticket maximum was accepted.'
    );

    $paid = $service->reserve($customerOneId, $created['event'], [$ticketIds['Regular'] => 2]);
    $staleCheck = $database->prepare(
        'SELECT b.status, b.reservation_expires_at, p.status AS payment_status
         FROM bookings b JOIN payments p ON p.booking_id = b.id WHERE b.id = :id'
    );
    $staleCheck->execute(['id' => $staleBookingId]);
    $stale = $staleCheck->fetch();
    assertBookingService($stale['status'] === 'expired', 'Stale reservation was not expired.');
    assertBookingService($stale['reservation_expires_at'] === null, 'Stale reservation expiry was not cleared.');
    assertBookingService($stale['payment_status'] === 'failed', 'Stale reservation payment was not failed.');
    $paidBooking = (new BookingRepository())->findForCustomer($paid['booking_id'], $customerOneId);
    assertBookingService($paid['free'] === false, 'A paid booking was marked free.');
    assertBookingService((int) $paidBooking['total_quantity'] === 2, 'Paid booking quantity is incorrect.');
    assertBookingService((string) $paidBooking['total_amount'] === '600.00', 'Server-calculated paid total is incorrect.');
    assertBookingService($paidBooking['status'] === 'pending_payment', 'Paid reservation was not kept pending.');
    assertBookingService($paidBooking['provider_order_id'] !== null, 'Razorpay order ID was not stored.');

    $service->confirm(
        $paid['booking_id'],
        $customerOneId,
        (string) $paidBooking['provider_order_id'],
        'pay_regression_success',
        'valid-signature'
    );
    $confirmedPaid = (new BookingRepository())->findForCustomer($paid['booking_id'], $customerOneId);
    assertBookingService($confirmedPaid['status'] === 'confirmed', 'Captured paid booking was not confirmed.');
    assertBookingService($confirmedPaid['payment_status'] === 'captured', 'Captured payment status was not stored.');
    $issuedRepository = new IssuedTicketRepository();
    $paidTickets = $issuedRepository->forCustomerBooking($paid['booking_id'], $customerOneId);
    assertBookingService(count($paidTickets) === 2, 'Paid booking did not issue one ticket per quantity.');
    assertBookingService(
        array_column($paidTickets, 'seat_number') === [1, 2],
        'Paid booking seats were not allocated sequentially.'
    );
    assertBookingService(
        count(array_unique(array_column($paidTickets, 'ticket_code'))) === 2,
        'Paid tickets did not receive unique ticket codes.'
    );
    $service->confirm(
        $paid['booking_id'],
        $customerOneId,
        (string) $paidBooking['provider_order_id'],
        'pay_regression_success',
        'valid-signature'
    );
    assertBookingService(
        count($issuedRepository->forCustomerBooking($paid['booking_id'], $customerOneId)) === 2,
        'Repeated payment confirmation created duplicate tickets.'
    );

    $free = $service->reserve($customerOneId, $created['event'], [$ticketIds['Community'] => 2]);
    $freeBooking = (new BookingRepository())->findForCustomer($free['booking_id'], $customerOneId);
    assertBookingService($free['free'] === true && $freeBooking['status'] === 'confirmed', 'Free booking was not confirmed.');
    $freeTickets = $issuedRepository->forCustomerBooking($free['booking_id'], $customerOneId);
    assertBookingService(count($freeTickets) === 2, 'Free booking did not issue tickets.');
    assertBookingService(
        array_column($freeTickets, 'seat_number') === [3, 4],
        'Free booking did not continue event-wide seat allocation.'
    );

    expectBookingDomainException(
        static fn () => $service->reserve($customerTwoId, $created['event'], [$ticketIds['Community'] => 4]),
        'A quantity above remaining inventory was accepted.'
    );

    $lastSeat = $service->reserve($customerOneId, $created['event'], [$ticketIds['Last Seat'] => 1]);
    assertBookingService($lastSeat['free'] === true, 'The final free seat was not booked.');
    expectBookingDomainException(
        static fn () => $service->reserve($customerTwoId, $created['event'], [$ticketIds['Last Seat'] => 1]),
        'A competing booking oversold the final ticket.'
    );

    $failingGateway = new class extends RazorpayService {
        public function createOrder(int $amountPaise, string $receipt): array
        {
            throw new RuntimeException('Simulated provider outage.');
        }
    };
    $failingService = new BookingService($failingGateway);
    expectBookingDomainException(
        static fn () => $failingService->reserve($customerTwoId, $created['event'], [$ticketIds['Regular'] => 1]),
        'A provider failure was not converted to a safe booking error.'
    );
    $failedStatement = $database->prepare(
        "SELECT b.status, b.reservation_expires_at, p.status AS payment_status
         FROM bookings b JOIN payments p ON p.booking_id = b.id
         WHERE b.customer_id = :customer_id AND b.event_id = :event_id
         ORDER BY b.id DESC LIMIT 1"
    );
    $failedStatement->execute(['customer_id' => $customerTwoId, 'event_id' => $created['event']]);
    $failed = $failedStatement->fetch();
    assertBookingService($failed['status'] === 'payment_failed', 'Provider failure left an active booking.');
    assertBookingService($failed['payment_status'] === 'failed', 'Provider failure left an active payment.');
    assertBookingService($failed['reservation_expires_at'] === null, 'Provider failure left a reservation expiry.');

    $inventory = $database->prepare(
        'SELECT name, reserved_quantity, sold_quantity FROM ticket_types WHERE event_id = :event_id'
    );
    $inventory->execute(['event_id' => $created['event']]);
    $inventoryByName = [];
    foreach ($inventory->fetchAll() as $row) {
        $inventoryByName[$row['name']] = $row;
    }
    assertBookingService((int) $inventoryByName['Regular']['reserved_quantity'] === 0, 'Regular inventory retained a leaked hold.');
    assertBookingService((int) $inventoryByName['Regular']['sold_quantity'] === 2, 'Paid sold inventory is incorrect.');
    assertBookingService((int) $inventoryByName['Community']['sold_quantity'] === 2, 'Free sold inventory is incorrect.');
    assertBookingService((int) $inventoryByName['Last Seat']['sold_quantity'] === 1, 'Competing inventory protection failed.');

    fwrite(STDOUT, "Booking service tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($created['event'] !== null) {
        $bookingIds = $database->prepare('SELECT id FROM bookings WHERE event_id = :event_id');
        $bookingIds->execute(['event_id' => $created['event']]);
        $ids = array_map('intval', $bookingIds->fetchAll(PDO::FETCH_COLUMN));
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $database->prepare("DELETE FROM notifications WHERE booking_id IN ($placeholders)")->execute($ids);
            $database->prepare("DELETE FROM payments WHERE booking_id IN ($placeholders)")->execute($ids);
            $database->prepare("DELETE FROM issued_tickets WHERE booking_id IN ($placeholders)")->execute($ids);
            $database->prepare("DELETE FROM booking_items WHERE booking_id IN ($placeholders)")->execute($ids);
            $database->prepare("DELETE FROM bookings WHERE id IN ($placeholders)")->execute($ids);
        }
        $database->prepare('DELETE FROM ticket_types WHERE event_id = :event_id')->execute(['event_id' => $created['event']]);
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
