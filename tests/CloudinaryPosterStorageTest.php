<?php

declare(strict_types=1);

use App\Services\CloudinaryPosterStorage;
use App\Services\PosterAsset;
use App\Services\PosterValidator;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertCloudinary(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validator = new class extends PosterValidator {
    public function validate(?array $file): ?array
    {
        return $file === null ? null : [
            'temporary_path' => __FILE__, 'mime' => 'image/png', 'extension' => 'png',
        ];
    }
};
$file = [
    'error' => UPLOAD_ERR_OK,
    'size' => 100,
    'tmp_name' => __FILE__,
    'name' => 'poster.png',
];
$config = [
    'cloud_name' => 'example-cloud',
    'api_key' => 'example-key',
    'api_secret' => 'example-secret',
    'folder' => 'event-booking-system/events',
];
$calls = [];
$transport = static function (string $url, array $fields) use (&$calls): array {
    $calls[] = ['url' => $url, 'fields' => $fields];
    if (str_ends_with($url, '/image/destroy')) {
        return ['result' => 'ok'];
    }

    return [
        'secure_url' => 'https://res.cloudinary.com/example-cloud/image/upload/v1/poster.png',
        'public_id' => $fields['folder'] . '/' . $fields['public_id'],
    ];
};

try {
    $storage = new CloudinaryPosterStorage($config, $validator, $transport);
    $expected = sha1('folder=events&public_id=poster&timestamp=123' . $config['api_secret']);
    assertCloudinary($storage->signature([
        'timestamp' => 123, 'public_id' => 'poster', 'folder' => 'events',
    ]) === $expected, 'Cloudinary signature is incorrect.');

    $asset = $storage->store($file);
    assertCloudinary($asset instanceof PosterAsset, 'Cloudinary upload did not return an asset.');
    assertCloudinary($asset->provider === 'cloudinary', 'Cloudinary provider metadata is incorrect.');
    assertCloudinary(str_starts_with($asset->path, 'https://res.cloudinary.com/'), 'Secure delivery URL is invalid.');
    assertCloudinary($asset->publicId !== null, 'Cloudinary public ID was not stored.');

    $storage->delete($asset);
    assertCloudinary(count($calls) === 2, 'Cloudinary replacement/deletion request was not made.');
    assertCloudinary(str_ends_with($calls[1]['url'], '/image/destroy'), 'Cloudinary destroy endpoint is incorrect.');

    $storage->delete(new PosterAsset('https://example.test/not-managed.jpg', 'external'));
    assertCloudinary(count($calls) === 2, 'An unmanaged external poster was deleted.');

    $failed = false;
    $invalidStorage = new CloudinaryPosterStorage(
        $config,
        $validator,
        static fn (): array => ['secure_url' => 'http://invalid.example/poster.png', 'public_id' => 'bad']
    );
    try {
        $invalidStorage->store($file);
    } catch (RuntimeException) {
        $failed = true;
    }
    assertCloudinary($failed, 'Invalid Cloudinary response was accepted.');

    fwrite(STDOUT, "Cloudinary poster storage tests passed.\n");
} finally {
}
