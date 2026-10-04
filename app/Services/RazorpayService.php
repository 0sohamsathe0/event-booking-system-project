<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class RazorpayService
{
    private array $config;

    public function __construct()
    {
        $file = is_file(BASE_PATH . '/config/payment.local.php')
            ? BASE_PATH . '/config/payment.local.php'
            : BASE_PATH . '/config/payment.php';
        $this->config = (require $file)['razorpay'];
    }

    public function isConfigured(): bool
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    public function keyId(): string
    {
        return trim((string) ($this->config['key_id'] ?? ''));
    }

    public function createOrder(int $amountPaise, string $receipt): array
    {
        if ($amountPaise < 1) {
            throw new RuntimeException('A paid Razorpay order must have a positive amount.');
        }

        return $this->request('POST', '/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => ['booking_reference' => $receipt],
        ]);
    }

    public function fetchPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret());
        return $signature !== '' && hash_equals($expected, $signature);
    }

    private function keySecret(): string
    {
        return trim((string) ($this->config['key_secret'] ?? ''));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Razorpay test keys are not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for Razorpay.');
        }

        $handle = curl_init('https://api.razorpay.com/v1' . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->keyId() . ':' . $this->keySecret(),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        $data = is_string($body) ? json_decode($body, true) : null;
        if ($error !== '' || $status < 200 || $status >= 300 || !is_array($data)) {
            $message = $data['error']['description'] ?? $error ?: 'Razorpay could not process the request.';
            throw new RuntimeException((string) $message);
        }
        return $data;
    }
}
