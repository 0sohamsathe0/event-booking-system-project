<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

final class PosterStorageManager implements PosterStorageInterface
{
    private LocalPosterStorage $local;
    private CloudinaryPosterStorage $cloudinary;
    private string $driver;

    public function __construct(
        ?string $driver = null,
        ?LocalPosterStorage $local = null,
        ?CloudinaryPosterStorage $cloudinary = null
    ) {
        $this->driver = $driver ?? (string) Config::get('storage.driver', 'local');
        $this->local = $local ?? new LocalPosterStorage();
        $this->cloudinary = $cloudinary ?? new CloudinaryPosterStorage(
            (array) Config::get('storage.cloudinary', [])
        );
    }

    public function store(?array $file): ?PosterAsset
    {
        return $this->driver === 'cloudinary'
            ? $this->cloudinary->store($file)
            : $this->local->store($file);
    }

    public function delete(?PosterAsset $asset): void
    {
        if ($asset?->provider === 'cloudinary') {
            $this->cloudinary->delete($asset);
            return;
        }
        if ($asset?->provider === 'local') {
            $this->local->delete($asset);
        }
    }

    public function deleteSafely(?PosterAsset $asset): void
    {
        try {
            $this->delete($asset);
        } catch (\Throwable $exception) {
            Logger::error('poster.delete_failed', [
                'provider' => $asset?->provider,
                'exception' => $exception,
            ]);
        }
    }
}
