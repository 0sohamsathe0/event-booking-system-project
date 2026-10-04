<?php

declare(strict_types=1);

// Copy to payment.local.php and paste TEST keys from the Razorpay dashboard.
// Never commit or share the local file containing real secrets.
return [
    'razorpay' => [
        'key_id' => 'rzp_test_replace_me',
        'key_secret' => 'replace_me',
        'webhook_secret' => '',
        'currency' => 'INR',
    ],
    'reservation_minutes' => 15,
];
