<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PosterUploader
{
    private const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The poster upload failed. Please try again.');
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > self::MAX_SIZE) {
            throw new RuntimeException('Poster size must not exceed 5 MB.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime]) || @getimagesize($temporaryPath) === false) {
            throw new RuntimeException('Poster must be a valid JPEG, PNG, or WebP image.');
        }

        $filename = bin2hex(random_bytes(20)) . '.' . self::ALLOWED_TYPES[$mime];
        $directory = BASE_PATH . '/public/uploads/events';
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The poster could not be stored.');
        }

        return 'uploads/events/' . $filename;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || !preg_match('#^uploads/events/[a-f0-9]{40}\.(jpg|png|webp)$#', $relativePath)) {
            return;
        }
        $path = BASE_PATH . '/public/' . $relativePath;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

