<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Services\CustomerProfileService;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertCustomerProfileTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$profile = new CustomerProfileService();
[$validErrors, $validData] = $profile->validate([
    'name' => '  Updated Customer  ',
    'phone' => '+91 (98765) 43210',
]);
assertCustomerProfileTest($validErrors === [], 'Valid profile details were rejected.');
assertCustomerProfileTest($validData['name'] === 'Updated Customer', 'Profile name was not normalized.');
assertCustomerProfileTest($validData['phone'] === '+919876543210', 'Profile phone was not normalized.');

[$invalidErrors] = $profile->validate(['name' => ['invalid'], 'phone' => '123']);
assertCustomerProfileTest(isset($invalidErrors['name']), 'Invalid profile name was accepted.');
assertCustomerProfileTest(isset($invalidErrors['phone']), 'Invalid profile phone was accepted.');

$database = Database::connection();
$database->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(5));
    $createUser = $database->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, :role, :account_status)'
    );
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $users = [
        ['name' => 'Profile Owner', 'role' => 'customer', 'account_status' => 'approved'],
        ['name' => 'Other Customer', 'role' => 'customer', 'account_status' => 'approved'],
        ['name' => 'Profile Organizer', 'role' => 'organizer', 'account_status' => 'approved'],
    ];
    $userIds = [];

    foreach ($users as $index => $user) {
        $createUser->execute([
            'name' => $user['name'],
            'email' => 'profile-test-' . $suffix . '-' . $index . '@example.test',
            'phone' => '+91910000' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'password_hash' => $passwordHash,
            'role' => $user['role'],
            'account_status' => $user['account_status'],
        ]);
        $userIds[] = (int) $database->lastInsertId();
    }

    [$ownerId, $otherCustomerId, $organizerId] = $userIds;
    $repository = new UserRepository();
    $repository->updateCustomerProfile($ownerId, $validData['name'], $validData['phone']);

    $selectUser = $database->prepare('SELECT name, phone FROM users WHERE id = :id');
    $selectUser->execute(['id' => $ownerId]);
    $updatedOwner = $selectUser->fetch();
    assertCustomerProfileTest($updatedOwner['name'] === 'Updated Customer', 'Owner profile name was not updated.');
    assertCustomerProfileTest($updatedOwner['phone'] === '+919876543210', 'Owner profile phone was not updated.');

    $selectUser->execute(['id' => $otherCustomerId]);
    $otherCustomer = $selectUser->fetch();
    assertCustomerProfileTest($otherCustomer['name'] === 'Other Customer', 'Another customer profile was changed.');

    $repository->updateCustomerProfile($organizerId, 'Changed Organizer', '+919999999999');
    $selectUser->execute(['id' => $organizerId]);
    $organizer = $selectUser->fetch();
    assertCustomerProfileTest($organizer['name'] === 'Profile Organizer', 'Customer profile update changed an organizer.');

    $database->rollBack();
    fwrite(STDOUT, "Customer profile tests passed.\n");
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
