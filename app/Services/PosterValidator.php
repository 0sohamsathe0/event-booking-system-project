<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class PosterValidator
{
    private const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function validate(?array $file): ?array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The poster upload failed. Please try again.');
        }
        if (($file['size'] ?? 0) < 1 || (int) $file['size'] > self::MAX_SIZE) {
            throw new RuntimeException('Poster size must not exceed 5 MB.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if (!is_file($temporaryPath)) {
            throw new RuntimeException('The uploaded poster could not be read.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime]) || @getimagesize($temporaryPath) === false) {
            throw new RuntimeException('Poster must be a valid JPEG, PNG, or WebP image.');
        }

        return [
            'temporary_path' => $temporaryPath,
            'mime' => $mime,
            'extension' => self::ALLOWED_TYPES[$mime],
        ];
    }
}
