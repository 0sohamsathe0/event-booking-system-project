<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

class RazorpayService
{
    private array $config;
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->config = (array) Config::get('payment.razorpay', []);
        $this->transport = $transport;
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

    public function createRefund(
        string $paymentId,
        int $amountPaise,
        string $receipt,
        string $idempotencyKey
    ): array {
        if ($amountPaise < 100) {
            throw new RuntimeException('A Razorpay refund must be at least INR 1.00.');
        }
        if (!str_starts_with($this->keyId(), 'rzp_test_')) {
            throw new RuntimeException('This application only permits Razorpay Test Mode refunds.');
        }

        return $this->request(
            'POST',
            '/payments/' . rawurlencode($paymentId) . '/refund',
            [
                'amount' => $amountPaise,
                'speed' => 'normal',
                'receipt' => $receipt,
                'notes' => ['refund_reference' => $receipt],
            ],
            ['X-Refund-Idempotency: ' . $idempotencyKey]
        );
    }

    public function fetchRefundsForPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId) . '/refunds?count=100');
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

    private function request(string $method, string $path, ?array $payload = null, array $additionalHeaders = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Razorpay test keys are not configured.');
        }
        if (is_callable($this->transport)) {
            $result = ($this->transport)($method, $path, $payload, $additionalHeaders);
            if (!is_array($result)) {
                throw new RuntimeException('Razorpay transport returned an invalid response.');
            }
            return $result;
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
            CURLOPT_HTTPHEADER => array_merge(
                ['Accept: application/json', 'Content-Type: application/json'],
                $additionalHeaders
            ),
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
