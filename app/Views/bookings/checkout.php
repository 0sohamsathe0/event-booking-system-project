<section class="section dashboard-page"><div class="container form-page">
    <div class="page-heading"><div><span class="eyebrow">Secure checkout</span><h1>Complete payment</h1><p class="muted">Booking <?= e($booking['booking_reference']) ?></p></div><strong class="checkout-total"><?= e(money($booking['total_amount'])) ?></strong></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="checkout-grid"><div class="panel"><h2><?= e($booking['event_title']) ?></h2><div class="booking-items"><?php foreach ($items as $item): ?><div><span><?= e($item['quantity']) ?> × <?= e($item['ticket_name']) ?></span><strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong></div><?php endforeach; ?></div><p class="reservation-note">Reserved until <?= e(local_datetime($booking['reservation_expires_at'])) ?></p></div>
    <aside class="panel decision-panel"><h2>Razorpay</h2><p class="muted">Payment is confirmed only after secure server verification.</p><?php if ($razorpayKey === '' || !$booking['provider_order_id']): ?><div class="alert alert-warning">Razorpay test keys are not configured. Add them to <code>config/payment.local.php</code>.</div><?php else: ?><button class="button button-full" id="razorpay-pay" type="button">Pay <?= e(money($booking['total_amount'])) ?></button><?php endif; ?><form method="post" action="<?= e(url('bookings/' . $booking['id'] . '/payment-failed')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Customer cancelled checkout."><button class="button button-secondary button-full" type="submit">Cancel payment</button></form></aside></div>
    <form id="payment-confirmation" method="post" action="<?= e(url('bookings/' . $booking['id'] . '/confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_signature"></form>
</div></section>
<?php if ($razorpayKey !== '' && $booking['provider_order_id']): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('razorpay-pay').addEventListener('click', function () {
    const checkout = new Razorpay({
        key: <?= json_encode($razorpayKey) ?>,
        amount: <?= (int) round((float) $booking['total_amount'] * 100) ?>,
        currency: 'INR',
        name: 'Event Booking System',
        description: <?= json_encode($booking['event_title']) ?>,
        order_id: <?= json_encode($booking['provider_order_id']) ?>,
        prefill: {name: <?= json_encode($customer['name']) ?>, email: <?= json_encode($customer['email']) ?>},
        theme: {color: '#D9783D'},
        timeout: 840,
        handler: function (response) {
            const form = document.getElementById('payment-confirmation');
            form.razorpay_payment_id.value = response.razorpay_payment_id;
            form.razorpay_order_id.value = response.razorpay_order_id;
            form.razorpay_signature.value = response.razorpay_signature;
            form.submit();
        }
    });
    checkout.open();
});
</script>
<?php endif; ?>
