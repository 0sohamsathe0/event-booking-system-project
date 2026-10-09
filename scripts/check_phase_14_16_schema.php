<?php

declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('APP_LOAD_ENV_FILE=true');
require dirname(__DIR__) . '/bootstrap/app.php';

$database = Database::connection();
$scalar = static function (string $sql, array $parameters = []) use ($database): mixed {
    $statement = $database->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchColumn();
};
$tableExists = static fn (string $table): bool => (int) $scalar(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    [$table]
) === 1;
$columnExists = static fn (string $table, string $column): bool => (int) $scalar(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$table, $column]
) === 1;
$indexExists = static fn (string $table, string $index): bool => (int) $scalar(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
    [$table, $index]
) >= 1;
$columnType = static fn (string $table, string $column): string => (string) $scalar(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$table, $column]
);

$checks = [
    'Phase 14: sessions table' => $tableExists('sessions'),
    'Phase 14: sessions expiry index' => $indexExists('sessions', 'idx_sessions_expiry'),
    'Phase 14: events.poster_provider' => $columnExists('events', 'poster_provider'),
    'Phase 14: events.poster_public_id' => $columnExists('events', 'poster_public_id'),
    'Phase 15: issued_tickets table' => $tableExists('issued_tickets'),
    'Phase 16: issued_tickets.active_seat_slot' => $columnExists('issued_tickets', 'active_seat_slot'),
    'Phase 16: active-seat unique index' => $indexExists('issued_tickets', 'uq_issued_tickets_active_event_seat'),
    'Phase 16: booking_cancellation_actions table' => $tableExists('booking_cancellation_actions'),
    'Phase 16: ticket_cancellations table' => $tableExists('ticket_cancellations'),
    'Phase 16: refunds.refund_reference' => $columnExists('refunds', 'refund_reference'),
    'Phase 16: refunds.idempotency_key' => $columnExists('refunds', 'idempotency_key'),
    'Phase 16: refunds.refund_percentage' => $columnExists('refunds', 'refund_percentage'),
    'Phase 16: refunds.source' => $columnExists('refunds', 'source'),
    'Phase 16: refunds.event_cancellation_request_id' => $columnExists('refunds', 'event_cancellation_request_id'),
    'Phase 16: refunds.attempt_count' => $columnExists('refunds', 'attempt_count'),
    'Phase 16: refunds.last_attempted_at' => $columnExists('refunds', 'last_attempted_at'),
    'Phase 16: refund idempotency unique index' => $indexExists('refunds', 'uq_refunds_idempotency'),
    'Phase 16: partially_cancelled booking status' => str_contains($columnType('bookings', 'status'), 'partially_cancelled'),
    'Phase 16: partially_refunded payment status' => str_contains($columnType('payments', 'status'), 'partially_refunded'),
];

echo "Connected to the configured database.\n";
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS  ' : 'FAIL  ') . $label . PHP_EOL;
}

echo 'Schema tables: ' . (int) $scalar(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
) . PHP_EOL;

foreach (['users', 'events', 'ticket_types', 'bookings', 'payments', 'issued_tickets', 'refunds'] as $table) {
    if ($tableExists($table)) {
        echo 'Rows ' . $table . ': ' . (int) $scalar('SELECT COUNT(*) FROM `' . $table . '`') . PHP_EOL;
    }
}

exit(in_array(false, $checks, true) ? 1 : 0);
