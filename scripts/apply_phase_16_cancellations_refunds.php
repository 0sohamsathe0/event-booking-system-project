<?php

declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap/app.php';

$database = Database::connection();
$columnExists = static function (string $table, string $column) use ($database): bool {
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $statement->fetchColumn() === 1;
};
$tableExists = static function (string $table) use ($database): bool {
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    return (int) $statement->fetchColumn() === 1;
};

$hasActiveSeat = $columnExists('issued_tickets', 'active_seat_slot');
$hasRefundReference = $columnExists('refunds', 'refund_reference');
$hasCancellationTable = $tableExists('ticket_cancellations');
$hasActionTable = $tableExists('booking_cancellation_actions');
if ($hasActiveSeat && $hasRefundReference && $hasCancellationTable && $hasActionTable) {
    fwrite(STDOUT, "Phase 16 database upgrade is already present.\n");
    exit(0);
}
if ($hasActiveSeat || $hasRefundReference || $hasCancellationTable || $hasActionTable) {
    throw new RuntimeException(
        'A partial Phase 16 schema was detected. Restore the pre-migration backup or complete the SQL manually before retrying.'
    );
}

$path = dirname(__DIR__) . '/database/phase_16_cancellations_refunds.sql';
$sql = file_get_contents($path);
if (!is_string($sql)) {
    throw new RuntimeException('The Phase 16 SQL upgrade file could not be read.');
}
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
$statements = preg_split('/;\s*(?:\r?\n|$)/', trim($sql)) ?: [];
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $database->exec($statement);
    }
}

fwrite(STDOUT, "Phase 16 cancellation/refund database upgrade completed.\n");
