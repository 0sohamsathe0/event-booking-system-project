<?php

declare(strict_types=1);

use App\Core\Config;
use App\Services\CancellationPolicy;
use App\Services\RazorpayService;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Services/CancellationPolicy.php';
require BASE_PATH . '/app/Services/RazorpayService.php';

function assertCancellation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Config::set('cancellation', require BASE_PATH . '/config/cancellation.php');
Config::set('payment', ['razorpay' => [
    'key_id' => 'rzp_test_example',
    'key_secret' => 'fake-secret',
    'webhook_secret' => 'fake-webhook-secret',
]]);

$policy = new CancellationPolicy();
$now = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));
assertCancellation($policy->customerRefundPercentage('2026-01-08 00:00:00', $now) === 100, 'Seven-day boundary must refund 100%.');
assertCancellation($policy->customerRefundPercentage('2026-01-07 23:59:59', $now) === 50, 'Inside seven days must refund 50%.');
assertCancellation($policy->customerRefundPercentage('2026-01-03 00:00:00', $now) === 50, 'The 48-hour boundary must remain cancellable.');
$blocked = false;
try {
    $policy->customerRefundPercentage('2026-01-02 23:59:59', $now);
} catch (DomainException) {
    $blocked = true;
}
assertCancellation($blocked, 'Cancellation inside 48 hours was not blocked.');
assertCancellation($policy->refundPaise(101, 50) === 51, 'Refund paise must use half-up rounding.');

$calls = [];
$gateway = new RazorpayService(static function (string $method, string $path, ?array $payload, array $headers) use (&$calls): array {
    $calls[] = compact('method', 'path', 'payload', 'headers');
    return [
        'id' => 'rfnd_test_1', 'payment_id' => 'pay_test_1', 'amount' => 5000,
        'currency' => 'INR', 'status' => 'processed',
    ];
});
$response = $gateway->createRefund('pay_test_1', 5000, 'RFD-TEST-1', 'ebs-refund-test-key');
assertCancellation($response['status'] === 'processed', 'The fake refund response was not returned.');
assertCancellation($calls[0]['method'] === 'POST', 'Refunds must use POST.');
assertCancellation($calls[0]['path'] === '/payments/pay_test_1/refund', 'Refund endpoint is incorrect.');
assertCancellation($calls[0]['payload']['amount'] === 5000, 'Refund amount was not sent in paise.');
assertCancellation(in_array('X-Refund-Idempotency: ebs-refund-test-key', $calls[0]['headers'], true), 'Idempotency header is missing.');
assertCancellation($calls[0]['payload']['receipt'] === 'RFD-TEST-1', 'Refund receipt is missing.');

$liveBlocked = false;
Config::set('payment', ['razorpay' => ['key_id' => 'rzp_live_example', 'key_secret' => 'fake']]);
try {
    (new RazorpayService(static fn (): array => []))->createRefund('pay_test_1', 5000, 'RFD-TEST-2', 'ebs-refund-test-live');
} catch (RuntimeException) {
    $liveBlocked = true;
}
assertCancellation($liveBlocked, 'Live Mode refund credentials were not rejected.');

fwrite(STDOUT, "Cancellation policy and Razorpay refund tests passed.\n");
