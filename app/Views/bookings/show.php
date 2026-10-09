<section class="section dashboard-page"><div class="container form-page">
    <div class="page-heading"><div><span class="eyebrow">Booking reference</span><h1><?= e($booking['booking_reference']) ?></h1><p class="muted"><?= e($booking['event_title']) ?></p></div><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel booking-summary">
        <dl class="details-list"><div><dt>Event</dt><dd><?= e($booking['event_title']) ?></dd></div><div><dt>Schedule</dt><dd><?= e(local_datetime($booking['start_datetime'])) ?></dd></div><div><dt>Venue</dt><dd><?= e($booking['hall_name']) ?></dd></div><div><dt>Booked</dt><dd><?= e(local_datetime($booking['booked_at'])) ?></dd></div></dl>
        <div class="booking-items"><?php foreach ($items as $item): ?><div><span><?= e($item['quantity']) ?> × <?= e($item['ticket_name']) ?></span><strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong></div><?php endforeach; ?><div class="booking-total"><span>Total</span><strong><?= e(money($booking['total_amount'])) ?></strong></div></div>
        <?php if ($booking['status'] === 'pending_payment'): ?><a class="button" href="<?= e(url('bookings/' . $booking['id'] . '/checkout')) ?>">Complete payment</a><?php endif; ?>
    </div>
    <?php require BASE_PATH . '/app/Views/partials/issued_tickets.php'; ?>

    <?php if ($canCancel): ?>
        <section class="panel cancellation-panel" aria-labelledby="cancel-ticket-heading">
            <div class="panel-heading"><div><span class="eyebrow">Cancellation policy</span><h2 id="cancel-ticket-heading">Cancel tickets</h2></div><strong><?= e($refundPercentage) ?>% refund</strong></div>
            <p class="muted">Select quantities by ticket type. The highest-numbered valid seats in each type will be cancelled. This action is final.</p>
            <form class="form-stack" method="post" action="<?= e(url('bookings/' . $booking['id'] . '/cancellations')) ?>" data-cancellation-form data-refund-percentage="<?= e($refundPercentage) ?>" data-confirm="Cancel the selected tickets and start the Test Mode refund?">
                <?= csrf_field() ?>
                <input type="hidden" name="request_token" value="<?= e($cancellationRequestToken) ?>">
                <div class="ticket-options cancellation-options">
                    <?php foreach ($cancellationOptions as $option): ?>
                        <label class="ticket-option" for="cancel-ticket-<?= e($option['ticket_type_id']) ?>" data-cancel-price-paise="<?= e((int) round((float) $option['unit_price'] * 100)) ?>">
                            <span><strong><?= e($option['ticket_type_name']) ?></strong><small><?= e(money($option['unit_price'])) ?> each &middot; <?= e($option['available_quantity']) ?> valid</small></span>
                            <select id="cancel-ticket-<?= e($option['ticket_type_id']) ?>" name="quantities[<?= e($option['ticket_type_id']) ?>]">
                                <?php for ($quantity = 0; $quantity <= (int) $option['available_quantity']; $quantity++): ?><option value="<?= e($quantity) ?>"><?= e($quantity) ?></option><?php endfor; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="cancellation-estimate" data-cancellation-estimate>No tickets selected.</p>
                <div class="form-group"><label for="cancellation-reason">Reason <span class="muted">(optional)</span></label><textarea id="cancellation-reason" name="reason" maxlength="1000" rows="3"></textarea></div>
                <button class="button button-danger" type="submit">Cancel selected tickets</button>
            </form>
        </section>
    <?php elseif ($cancellationUnavailableReason): ?>
        <div class="alert alert-warning"><?= e($cancellationUnavailableReason) ?></div>
    <?php endif; ?>

    <?php if ($refunds !== []): ?>
        <section class="panel refund-history" aria-labelledby="refund-history-heading">
            <div class="panel-heading"><div><span class="eyebrow">Payment reversals</span><h2 id="refund-history-heading">Refund history</h2></div></div>
            <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Tickets</th><th>Policy</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead><tbody>
                <?php foreach ($refunds as $refund): ?><tr><td><strong><?= e($refund['refund_reference']) ?></strong></td><td><?= e($refund['ticket_count']) ?></td><td><?= e($refund['refund_percentage']) ?>%</td><td><?= e(money($refund['amount'])) ?></td><td><span class="status-badge status-<?= e($refund['status']) ?>"><?= e($refund['status']) ?></span><?php if ($refund['status'] === 'failed'): ?><small>Queued for admin retry</small><?php endif; ?></td><td><?= e(local_datetime($refund['requested_at'])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>
</div></section>
