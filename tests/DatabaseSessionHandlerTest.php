<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\DatabaseSessionHandler;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertDatabaseSession(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$handler = new DatabaseSessionHandler(3600);
$firstId = 'phase14_' . bin2hex(random_bytes(12));
$secondId = 'phase14_' . bin2hex(random_bytes(12));

try {
    assertDatabaseSession($handler->open('', 'test'), 'Session handler did not open.');
    assertDatabaseSession($handler->write($firstId, 'value|s:3:"one";'), 'Session was not created.');
    assertDatabaseSession($handler->read($firstId) === 'value|s:3:"one";', 'Session could not be read.');
    assertDatabaseSession($handler->write($firstId, 'value|s:3:"two";'), 'Session was not updated.');
    assertDatabaseSession($handler->read($firstId) === 'value|s:3:"two";', 'Updated session payload is incorrect.');

    assertDatabaseSession($handler->write($secondId, 'auth|b:1;'), 'Regenerated session was not written.');
    assertDatabaseSession($handler->destroy($firstId), 'Old regenerated session was not destroyed.');
    assertDatabaseSession($handler->read($firstId) === '', 'Destroyed session remained readable.');
    assertDatabaseSession($handler->read($secondId) === 'auth|b:1;', 'Regenerated session is unavailable.');

    $database->prepare('UPDATE sessions SET expires_at = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND WHERE id = :id')
        ->execute(['id' => $secondId]);
    assertDatabaseSession($handler->read($secondId) === '', 'Expired session remained readable.');
    assertDatabaseSession($handler->gc(3600) !== false, 'Session garbage collection failed.');
    $remaining = $database->prepare('SELECT COUNT(*) FROM sessions WHERE id = :id');
    $remaining->execute(['id' => $secondId]);
    assertDatabaseSession((int) $remaining->fetchColumn() === 0, 'Expired session was not collected.');
    assertDatabaseSession($handler->close(), 'Session handler did not close.');

    fwrite(STDOUT, "Database session handler tests passed.\n");
} finally {
    $database->prepare('DELETE FROM sessions WHERE id IN (:first_id, :second_id)')->execute([
        'first_id' => $firstId,
        'second_id' => $secondId,
    ]);
}
