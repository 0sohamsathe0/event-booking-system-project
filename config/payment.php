<?php

declare(strict_types=1);

use App\Core\Config;

// Keep real credentials out of source control. Set environment variables or
// create payment.local.php with the same array keys for local XAMPP testing.
return [
    'razorpay' => [
        'key_id' => (string) Config::env('RAZORPAY_KEY_ID', ''),
        'key_secret' => (string) Config::env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => (string) Config::env('RAZORPAY_WEBHOOK_SECRET', ''),
        'currency' => 'INR',
    ],
    'reservation_minutes' => 15,
];
