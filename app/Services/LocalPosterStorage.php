<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class LocalPosterStorage implements PosterStorageInterface
{
    public function __construct(private readonly PosterValidator $validator = new PosterValidator())
    {
    }

    public function store(?array $file): ?PosterAsset
    {
        $validated = $this->validator->validate($file);
        if ($validated === null) {
            return null;
        }

        $filename = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
        $directory = BASE_PATH . '/public/uploads/events';
        $destination = $directory . '/' . $filename;
        if (!is_dir($directory) || !move_uploaded_file($validated['temporary_path'], $destination)) {
            throw new RuntimeException('The poster could not be stored.');
        }

        return new PosterAsset('uploads/events/' . $filename, 'local');
    }

    public function delete(?PosterAsset $asset): void
    {
        if ($asset === null || $asset->provider !== 'local'
            || !preg_match('#^uploads/events/[a-f0-9]{40}\.(jpg|png|webp)$#', $asset->path)) {
            return;
        }

        $path = BASE_PATH . '/public/' . $asset->path;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
