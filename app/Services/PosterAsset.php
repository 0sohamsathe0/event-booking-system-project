<?php

declare(strict_types=1);

namespace App\Services;

final class PosterAsset
{
    public function __construct(
        public readonly string $path,
        public readonly string $provider,
        public readonly ?string $publicId = null
    ) {
    }

    public static function fromEvent(array $event): ?self
    {
        $path = trim((string) ($event['poster_path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $provider = trim((string) ($event['poster_provider'] ?? ''));
        if ($provider === '') {
            $provider = str_starts_with($path, 'uploads/events/') ? 'local' : 'external';
        }

        $publicId = trim((string) ($event['poster_public_id'] ?? ''));

        return new self($path, $provider, $publicId !== '' ? $publicId : null);
    }
}
