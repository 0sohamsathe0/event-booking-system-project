<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\CloudinaryPosterStorage;
use App\Services\EventService;
use App\Services\LocalPosterStorage;
use App\Services\PosterStorageManager;
use App\Services\PosterValidator;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertPosterReplacement(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = Database::connection();
$suffix = bin2hex(random_bytes(5));
$validator = new class extends PosterValidator {
    public function validate(?array $file): ?array
    {
        return ['temporary_path' => __FILE__, 'mime' => 'image/png', 'extension' => 'png'];
    }
};
$file = ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => __FILE__, 'name' => 'poster.png'];
$requests = [];
$uploadNumber = 0;
$transport = static function (string $url, array $fields) use (&$requests, &$uploadNumber): array {
    $requests[] = ['url' => $url, 'public_id' => $fields['public_id'] ?? null];
    if (str_ends_with($url, '/image/destroy')) {
        return ['result' => 'ok'];
    }
    $uploadNumber++;
    return [
        'secure_url' => 'https://res.cloudinary.com/example/image/upload/v1/poster-' . $uploadNumber . '.png',
        'public_id' => $fields['folder'] . '/' . $fields['public_id'],
    ];
};
$cloudinary = new CloudinaryPosterStorage([
    'cloud_name' => 'example', 'api_key' => 'fake', 'api_secret' => 'fake', 'folder' => 'events',
], $validator, $transport);
$service = new EventService(posters: new PosterStorageManager('cloudinary', new LocalPosterStorage(), $cloudinary));
$created = ['user' => null, 'hall' => null, 'category' => null, 'event' => null];

try {
    $database->prepare(
        "INSERT INTO users (name, email, phone, password_hash, role, account_status)
         VALUES ('Poster Organizer', :email, '+919100000001', :password, 'organizer', 'approved')"
    )->execute(['email' => 'poster-' . $suffix . '@example.test', 'password' => password_hash('fake', PASSWORD_DEFAULT)]);
    $created['user'] = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO halls (name, address, city, maximum_capacity) VALUES (:name, 'Test', 'Test', 50)")
        ->execute(['name' => 'Poster Hall ' . $suffix]);
    $created['hall'] = (int) $database->lastInsertId();
    $database->prepare('INSERT INTO event_categories (name) VALUES (:name)')->execute(['name' => 'Poster ' . $suffix]);
    $created['category'] = (int) $database->lastInsertId();

    $data = [
        'hall_id' => $created['hall'], 'category_id' => $created['category'],
        'title' => 'Poster replacement event', 'description' => 'Poster replacement integration test event.',
        'start_datetime' => '2099-01-01 10:00:00.000000', 'end_datetime' => '2099-01-01 12:00:00.000000',
        'sale_start_datetime' => '2098-12-01 10:00:00.000000', 'sale_end_datetime' => '2099-01-01 09:00:00.000000',
        'event_capacity' => 10,
    ];
    $created['event'] = $service->create($created['user'], $data, $file);
    $first = $database->query('SELECT poster_path, poster_provider, poster_public_id FROM events WHERE id = ' . $created['event'])->fetch();
    $service->update($created['event'], $created['user'], $data, $file, false);
    $second = $database->query('SELECT poster_path, poster_provider, poster_public_id FROM events WHERE id = ' . $created['event'])->fetch();

    assertPosterReplacement($first['poster_provider'] === 'cloudinary', 'Initial poster provider was not stored.');
    assertPosterReplacement($second['poster_provider'] === 'cloudinary', 'Replacement poster provider was not stored.');
    assertPosterReplacement($first['poster_public_id'] !== $second['poster_public_id'], 'Poster public ID was not replaced.');
    assertPosterReplacement($first['poster_path'] !== $second['poster_path'], 'Poster URL was not replaced.');
    assertPosterReplacement(count($requests) === 3, 'Expected two uploads followed by one deletion.');
    assertPosterReplacement(str_ends_with($requests[2]['url'], '/image/destroy'), 'Old poster was not deleted after update.');
    assertPosterReplacement($requests[2]['public_id'] === $first['poster_public_id'], 'Wrong Cloudinary asset was deleted.');

    fwrite(STDOUT, "Event poster replacement tests passed.\n");
} finally {
    if ($created['event'] !== null) {
        $database->prepare('DELETE FROM notifications WHERE event_id = :id')->execute(['id' => $created['event']]);
        $database->prepare('DELETE FROM event_status_history WHERE event_id = :id')->execute(['id' => $created['event']]);
        $database->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $created['event']]);
    }
    if ($created['category'] !== null) {
        $database->prepare('DELETE FROM event_categories WHERE id = :id')->execute(['id' => $created['category']]);
    }
    if ($created['hall'] !== null) {
        $database->prepare('DELETE FROM halls WHERE id = :id')->execute(['id' => $created['hall']]);
    }
    if ($created['user'] !== null) {
        $database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $created['user']]);
    }
}
