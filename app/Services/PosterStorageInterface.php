<?php

declare(strict_types=1);

namespace App\Services;

interface PosterStorageInterface
{
    public function store(?array $file): ?PosterAsset;

    public function delete(?PosterAsset $asset): void;
}
