<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\NotificationRepository;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertNotificationTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$database->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $createUser = $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES (:name, :email, :phone, :password_hash, 'customer', 'approved')"
    );
    $userIds = [];

    foreach ([1, 2] as $number) {
        $createUser->execute([
            'name' => 'Notification Test ' . $number,
            'email' => 'notification-test-' . $suffix . '-' . $number . '@example.test',
            'phone' => '+91999999' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
        ]);
        $userIds[] = (int) $database->lastInsertId();
    }

    [$ownerId, $otherUserId] = $userIds;
    $repository = new NotificationRepository();
    $repository->createForUser($ownerId, 'test_notice', 'Owned notice', 'Ownership test message.');
    $notificationId = (int) $database->lastInsertId();

    assertNotificationTest($repository->countForUser($ownerId) === 1, 'Owner history count is incorrect.');
    assertNotificationTest($repository->unreadCountForUser($ownerId) === 1, 'Owner unread count is incorrect.');
    assertNotificationTest($repository->countForUser($otherUserId) === 0, 'Notification leaked into another history.');

    $repository->markReadForUser($notificationId, $otherUserId);
    $readAt = $database->query('SELECT read_at FROM notifications WHERE id = ' . $notificationId)->fetchColumn();
    assertNotificationTest($readAt === null, 'Another customer changed the notification.');

    $repository->markReadForUser($notificationId, $ownerId);
    $readAt = $database->query('SELECT read_at FROM notifications WHERE id = ' . $notificationId)->fetchColumn();
    assertNotificationTest(is_string($readAt) && $readAt !== '', 'Owner could not mark the notification read.');

    $repository->markReadForUser($notificationId, $ownerId);
    $readAtAfterRepeat = $database->query('SELECT read_at FROM notifications WHERE id = ' . $notificationId)->fetchColumn();
    assertNotificationTest($readAtAfterRepeat === $readAt, 'Repeated mark-read changed the original timestamp.');

    $repository->createForUser($ownerId, 'test_notice', 'Second notice', 'Mark-all test message.');
    assertNotificationTest($repository->unreadCountForUser($ownerId) === 1, 'Second unread notice was not counted.');
    $repository->markAllReadForUser($otherUserId);
    assertNotificationTest($repository->unreadCountForUser($ownerId) === 1, 'Another customer changed owner notifications.');
    $repository->markAllReadForUser($ownerId);
    assertNotificationTest($repository->unreadCountForUser($ownerId) === 0, 'Mark-all did not clear the owner unread count.');

    $history = $repository->historyForUser($ownerId, 1, 20);
    assertNotificationTest(count($history) === 2, 'History did not return the owner notifications.');
    assertNotificationTest(
        $history[0]['owned_booking_id'] === null && $history[0]['public_event_id'] === null,
        'Text-only notification did not remain text-only.'
    );

    $database->rollBack();
    fwrite(STDOUT, "Notification repository tests passed.\n");
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
