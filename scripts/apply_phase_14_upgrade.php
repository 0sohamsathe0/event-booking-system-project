<?php

declare(strict_types=1);

use App\Core\Database;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$database = Database::connection();
$database->exec(
    "CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload MEDIUMBLOB NOT NULL,
        last_activity DATETIME(6) NOT NULL,
        expires_at DATETIME(6) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_sessions_expiry (expires_at)
    ) ENGINE=InnoDB"
);

$columnExists = $database->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);

foreach ([
    'poster_provider' => 'ALTER TABLE events ADD COLUMN poster_provider VARCHAR(20) NULL AFTER poster_path',
    'poster_public_id' => 'ALTER TABLE events ADD COLUMN poster_public_id VARCHAR(255) NULL AFTER poster_provider',
] as $column => $sql) {
    $columnExists->execute(['table_name' => 'events', 'column_name' => $column]);
    if ($columnExists->fetchColumn() === false) {
        $database->exec($sql);
    }
}

fwrite(STDOUT, "Phase 14 additive database upgrade completed.\n");
