<?php

declare(strict_types=1);

use App\Services\CloudinaryPosterStorage;
use App\Services\EventService;
use App\Services\LocalPosterStorage;
use App\Services\PosterStorageManager;
use App\Services\PosterValidator;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertPosterCompensation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validator = new class extends PosterValidator {
    public function validate(?array $file): ?array
    {
        return ['temporary_path' => __FILE__, 'mime' => 'image/png', 'extension' => 'png'];
    }
};
$file = ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => __FILE__, 'name' => 'poster.png'];
$requests = [];
$transport = static function (string $url, array $fields) use (&$requests): array {
    $requests[] = $url;
    if (str_ends_with($url, '/image/destroy')) {
        return ['result' => 'ok'];
    }
    return [
        'secure_url' => 'https://res.cloudinary.com/example/image/upload/v1/compensation.png',
        'public_id' => $fields['folder'] . '/' . $fields['public_id'],
    ];
};
$cloudinary = new CloudinaryPosterStorage([
    'cloud_name' => 'example', 'api_key' => 'fake', 'api_secret' => 'fake', 'folder' => 'events',
], $validator, $transport);
$manager = new PosterStorageManager('cloudinary', new LocalPosterStorage(), $cloudinary);
$service = new EventService(posters: $manager);

try {
    $failed = false;
    try {
        $service->create(0, [
            'hall_id' => 0,
            'category_id' => 0,
            'title' => 'Compensation test event',
            'description' => 'This event intentionally fails its database insert.',
            'start_datetime' => '2099-01-01 10:00:00.000000',
            'end_datetime' => '2099-01-01 12:00:00.000000',
            'sale_start_datetime' => '2098-12-01 10:00:00.000000',
            'sale_end_datetime' => '2099-01-01 09:00:00.000000',
            'event_capacity' => 10,
        ], $file);
    } catch (Throwable) {
        $failed = true;
    }

    assertPosterCompensation($failed, 'The intentional database failure did not occur.');
    assertPosterCompensation(count($requests) === 2, 'The newly uploaded asset was not deleted after DB failure.');
    assertPosterCompensation(str_ends_with($requests[1], '/image/destroy'), 'DB failure did not call Cloudinary destroy.');
    fwrite(STDOUT, "Poster database-failure compensation tests passed.\n");
} finally {
}
