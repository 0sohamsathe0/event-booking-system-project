<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Services\CloudinaryPosterStorage;
use App\Services\PosterAsset;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('APP_LOAD_ENV_FILE=true');
require dirname(__DIR__) . '/bootstrap/app.php';

function verificationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

verificationAssert(Config::get('app.environment') === 'production', 'APP_ENV must be production.');
verificationAssert(Config::get('app.session.driver') === 'database', 'SESSION_DRIVER must be database.');
verificationAssert(Config::get('storage.driver') === 'cloudinary', 'POSTER_STORAGE_DRIVER must be cloudinary.');

$database = Database::connection();
verificationAssert((int) $database->query('SELECT 1')->fetchColumn() === 1, 'Database connectivity failed.');

$requiredTables = [
    'users', 'halls', 'event_categories', 'events', 'sessions', 'event_status_history',
    'ticket_types', 'bookings', 'booking_items', 'issued_tickets', 'payments', 'refunds',
    'payment_webhook_events', 'event_cancellation_requests', 'booking_cancellation_actions',
    'ticket_cancellations', 'notifications',
];
$tableQuery = $database->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
);
$tables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
verificationAssert(array_diff($requiredTables, $tables) === [], 'The external database schema is incomplete.');

$columnQuery = $database->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'"
);
$columnQuery->execute();
$eventColumns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);
verificationAssert(in_array('poster_provider', $eventColumns, true), 'poster_provider is missing.');
verificationAssert(in_array('poster_public_id', $eventColumns, true), 'poster_public_id is missing.');

$ticketColumnQuery = $database->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'issued_tickets'"
);
$ticketColumnQuery->execute();
verificationAssert(
    in_array('active_seat_slot', $ticketColumnQuery->fetchAll(PDO::FETCH_COLUMN), true),
    'Phase 16 active seat metadata is missing.'
);

$ticketMismatchCount = (int) $database->query(
    "SELECT COUNT(*)
     FROM bookings b
     LEFT JOIN (
        SELECT booking_id, COUNT(*) AS issued_quantity
        FROM issued_tickets GROUP BY booking_id
     ) issued ON issued.booking_id = b.id
     WHERE b.status = 'confirmed'
       AND COALESCE(issued.issued_quantity, 0) <> b.total_quantity"
)->fetchColumn();
verificationAssert($ticketMismatchCount === 0, 'Confirmed booking ticket issuance is incomplete.');

$sessionId = session_id();
Session::put('external_verification', 'ok');
session_write_close();
$sessionQuery = $database->prepare('SELECT payload FROM sessions WHERE id = :id');
$sessionQuery->execute(['id' => $sessionId]);
verificationAssert($sessionQuery->fetchColumn() !== false, 'Database session persistence failed.');
Session::start((array) Config::get('app.session'));
verificationAssert(Session::get('external_verification') === 'ok', 'Database session read failed.');
Session::destroy();

$posterFiles = array_merge(
    glob(BASE_PATH . '/public/uploads/events/*.jpg') ?: [],
    glob(BASE_PATH . '/public/uploads/events/*.jpeg') ?: [],
    glob(BASE_PATH . '/public/uploads/events/*.png') ?: [],
    glob(BASE_PATH . '/public/uploads/events/*.webp') ?: []
);
verificationAssert($posterFiles !== [], 'A local JPEG, PNG, or WebP fixture is required for Cloudinary verification.');
$posterPath = $posterFiles[0];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($posterPath);
verificationAssert(is_string($mime), 'The Cloudinary fixture MIME type could not be read.');
$file = [
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($posterPath),
    'tmp_name' => $posterPath,
    'name' => basename($posterPath),
];

$cloudinary = new CloudinaryPosterStorage((array) Config::get('storage.cloudinary', []));
$asset = null;
try {
    $asset = $cloudinary->store($file);
    verificationAssert($asset instanceof PosterAsset, 'Cloudinary upload verification failed.');
    verificationAssert($asset->provider === 'cloudinary', 'Cloudinary provider metadata is incorrect.');
    $cloudinary->delete($asset);
    $asset = null;
} finally {
    if ($asset instanceof PosterAsset) {
        try {
            $cloudinary->delete($asset);
        } catch (Throwable) {
        }
    }
}

$razorpayKey = (string) Config::get('payment.razorpay.key_id', '');
verificationAssert(str_starts_with($razorpayKey, 'rzp_test_'), 'Razorpay is not configured for Test Mode.');

fwrite(STDOUT, "External database connectivity: passed\n");
fwrite(STDOUT, "External database schema: 17/17 tables passed\n");
fwrite(STDOUT, "Confirmed booking ticket quantities: passed\n");
fwrite(STDOUT, "Database session persistence and logout: passed\n");
fwrite(STDOUT, "Cloudinary signed upload and deletion: passed\n");
fwrite(STDOUT, "Razorpay Test Mode configuration: passed (no provider request made)\n");
