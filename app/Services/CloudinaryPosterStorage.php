<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use RuntimeException;

final class CloudinaryPosterStorage implements PosterStorageInterface
{
    private readonly Closure $transport;
    private readonly bool $usesCurlTransport;

    public function __construct(
        private readonly array $config,
        private readonly PosterValidator $validator = new PosterValidator(),
        ?Closure $transport = null
    ) {
        $this->usesCurlTransport = $transport === null;
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    public function store(?array $file): ?PosterAsset
    {
        $validated = $this->validator->validate($file);
        if ($validated === null) {
            return null;
        }
        $this->assertConfigured();

        $timestamp = time();
        $publicId = 'poster_' . bin2hex(random_bytes(16));
        $signed = [
            'folder' => $this->folder(),
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        $fields = $signed + [
            'api_key' => $this->config['api_key'],
            'signature' => $this->signature($signed),
            'file' => new \CURLFile(
                $validated['temporary_path'],
                $validated['mime'],
                $publicId . '.' . $validated['extension']
            ),
        ];

        $response = ($this->transport)($this->endpoint('image/upload'), $fields);
        $secureUrl = trim((string) ($response['secure_url'] ?? ''));
        $storedPublicId = trim((string) ($response['public_id'] ?? ''));
        if (!$this->isCloudinaryUrl($secureUrl) || $storedPublicId === '') {
            throw new RuntimeException('Cloudinary returned an invalid poster response.');
        }

        return new PosterAsset($secureUrl, 'cloudinary', $storedPublicId);
    }

    public function delete(?PosterAsset $asset): void
    {
        if ($asset === null || $asset->provider !== 'cloudinary' || $asset->publicId === null
            || !preg_match('#^[A-Za-z0-9_./-]{1,255}$#', $asset->publicId)) {
            return;
        }
        $this->assertConfigured();

        $signed = [
            'invalidate' => 'true',
            'public_id' => $asset->publicId,
            'timestamp' => time(),
        ];
        $response = ($this->transport)($this->endpoint('image/destroy'), $signed + [
            'api_key' => $this->config['api_key'],
            'signature' => $this->signature($signed),
        ]);
        if (!in_array((string) ($response['result'] ?? ''), ['ok', 'not found'], true)) {
            throw new RuntimeException('Cloudinary could not remove the previous poster.');
        }
    }

    public function signature(array $parameters): string
    {
        unset($parameters['file'], $parameters['api_key'], $parameters['signature']);
        ksort($parameters, SORT_STRING);
        $parts = [];
        foreach ($parameters as $key => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $key . '=' . $value;
            }
        }

        return hash('sha1', implode('&', $parts) . (string) ($this->config['api_secret'] ?? ''));
    }

    private function endpoint(string $action): string
    {
        return 'https://api.cloudinary.com/v1_1/'
            . rawurlencode((string) $this->config['cloud_name']) . '/' . $action;
    }

    private function folder(): string
    {
        $folder = trim((string) ($this->config['folder'] ?? ''), '/');
        if ($folder === '' || !preg_match('#^[A-Za-z0-9_/-]{1,150}$#', $folder)) {
            throw new RuntimeException('Cloudinary poster folder configuration is invalid.');
        }

        return $folder;
    }

    private function assertConfigured(): void
    {
        foreach (['cloud_name', 'api_key', 'api_secret', 'folder'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') {
                throw new RuntimeException('Cloudinary poster storage is not configured.');
            }
        }
        if (!function_exists('curl_init') && $this->usesCurlTransport) {
            throw new RuntimeException('The PHP cURL extension is required for Cloudinary.');
        }
    }

    private function isCloudinaryUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($url, 'https://')
            && ($host === 'res.cloudinary.com' || str_ends_with($host, '.cloudinary.com'));
    }

    private function curlTransport(string $url, array $fields): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for Cloudinary.');
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        $data = is_string($body) ? json_decode($body, true) : null;
        if ($error !== '' || $status < 200 || $status >= 300 || !is_array($data)) {
            throw new RuntimeException('Cloudinary could not process the poster request.');
        }

        return $data;
    }
}
