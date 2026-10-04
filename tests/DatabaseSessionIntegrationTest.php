<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

putenv('SESSION_DRIVER=database');
putenv('SESSION_NAME=phase14_integration_session');
putenv('SESSION_LIFETIME=3600');

require dirname(__DIR__) . '/bootstrap/app.php';

function assertSessionIntegration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$ids = [];

try {
    $ids[] = session_id();
    Session::put('phase14_value', 'present');
    session_write_close();

    Session::start((array) \App\Core\Config::get('app.session'));
    assertSessionIntegration(Session::get('phase14_value') === 'present', 'Database session did not persist.');

    $oldId = session_id();
    Session::regenerate();
    $newId = session_id();
    $ids[] = $newId;
    assertSessionIntegration($newId !== $oldId, 'Session ID did not regenerate.');

    Auth::login([
        'id' => 999999,
        'name' => 'Session Test',
        'email' => 'session@example.test',
        'role' => 'customer',
        'account_status' => 'approved',
    ]);
    $authenticatedId = session_id();
    $ids[] = $authenticatedId;
    assertSessionIntegration(Auth::check(), 'Authentication state was not stored in the database session.');

    Auth::logout();
    $statement = $database->prepare('SELECT COUNT(*) FROM sessions WHERE id = :id');
    $statement->execute(['id' => $authenticatedId]);
    assertSessionIntegration((int) $statement->fetchColumn() === 0, 'Logout did not destroy the database session.');

    fwrite(STDOUT, "Database session integration tests passed.\n");
} finally {
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $database->prepare("DELETE FROM sessions WHERE id IN ($placeholders)")->execute($ids);
    }
    putenv('SESSION_DRIVER');
    putenv('SESSION_NAME');
    putenv('SESSION_LIFETIME');
}
