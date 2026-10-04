<section class="section dashboard-page"><div class="container form-page">
    <div class="page-heading"><div><span class="eyebrow">Booking reference</span><h1><?= e($booking['booking_reference']) ?></h1><p class="muted"><?= e($booking['event_title']) ?></p></div><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel booking-summary">
        <dl class="details-list"><div><dt>Event</dt><dd><?= e($booking['event_title']) ?></dd></div><div><dt>Schedule</dt><dd><?= e(local_datetime($booking['start_datetime'])) ?></dd></div><div><dt>Venue</dt><dd><?= e($booking['hall_name']) ?></dd></div><div><dt>Booked</dt><dd><?= e(local_datetime($booking['booked_at'])) ?></dd></div></dl>
        <div class="booking-items"><?php foreach ($items as $item): ?><div><span><?= e($item['quantity']) ?> × <?= e($item['ticket_name']) ?></span><strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong></div><?php endforeach; ?><div class="booking-total"><span>Total</span><strong><?= e(money($booking['total_amount'])) ?></strong></div></div>
        <?php if ($booking['status'] === 'pending_payment'): ?><a class="button" href="<?= e(url('bookings/' . $booking['id'] . '/checkout')) ?>">Complete payment</a><?php endif; ?>
    </div>
</div></section>
