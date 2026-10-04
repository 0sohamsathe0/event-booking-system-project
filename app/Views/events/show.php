<?php $salesOpen = gmdate('Y-m-d H:i:s') >= substr($event['sale_start_datetime'], 0, 19) && gmdate('Y-m-d H:i:s') <= substr($event['sale_end_datetime'], 0, 19); ?>
<section class="section event-detail"><div class="container">
    <a class="back-link" href="<?= e(url('events')) ?>">&larr; All events</a>
    <div class="event-detail-grid">
        <div class="event-detail-poster"><?php if ($event['poster_path']): ?><img src="<?= e(url($event['poster_path'])) ?>" alt="<?= e($event['title']) ?> poster"><?php else: ?><span>No poster available</span><?php endif; ?></div>
        <div class="event-detail-copy">
            <span class="eyebrow"><?= e($event['category_name']) ?></span>
            <h1><?= e($event['title']) ?></h1>
            <p class="event-lead"><?= nl2br(e($event['description'])) ?></p>
            <dl class="details-list">
                <div><dt>Date & time</dt><dd><?= e(local_datetime($event['start_datetime'])) ?> – <?= e(local_datetime($event['end_datetime'], 'h:i A')) ?></dd></div>
                <div><dt>Venue</dt><dd><?= e($event['hall_name']) ?>, <?= e($event['address']) ?>, <?= e($event['city']) ?></dd></div>
                <div><dt>Sales close</dt><dd><?= e(local_datetime($event['sale_end_datetime'])) ?></dd></div>
            </dl>
        </div>
    </div>

    <section class="ticket-booking-panel" aria-labelledby="ticket-heading">
        <div><span class="eyebrow">Select admission</span><h2 id="ticket-heading">Tickets</h2><p class="muted">Choose up to 10 tickets across all types. Availability is rechecked securely.</p></div>
        <?php if (!\App\Core\Auth::check()): ?><div class="alert alert-warning">Please <a href="<?= e(url('login')) ?>">log in as a customer</a> to book tickets.</div><?php endif; ?>
        <?php if ($error = \App\Core\Session::consumeFlash('error')): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('events/' . $event['id'] . '/book')) ?>" class="ticket-selection-form">
            <?= csrf_field() ?>
            <div class="ticket-options">
            <?php foreach ($tickets as $ticket): $available = (int) $ticket['available_quantity']; ?>
                <div class="ticket-option" data-ticket-price-paise="<?= (int) round((float) $ticket['price'] * 100) ?>">
                    <div><strong><?= e($ticket['name']) ?></strong><span><?= e(money($ticket['price'])) ?> · <?= $available > 0 ? e($available) . ' remaining' : 'Sold out' ?></span></div>
                    <label><span class="sr-only"><?= e($ticket['name']) ?> quantity</span><select name="tickets[<?= e($ticket['id']) ?>]" <?= $available < 1 ? 'disabled' : '' ?>><?php for ($i = 0; $i <= min(10, $available); $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
                </div>
            <?php endforeach; ?>
            </div>
            <p class="ticket-selection-summary" data-ticket-summary aria-live="polite">No tickets selected.</p>
            <p class="field-error" data-ticket-error role="alert" hidden>Choose at least one ticket before continuing.</p>
            <button class="button" type="submit" <?= !$salesOpen || !\App\Core\Auth::hasRole('customer') ? 'disabled' : '' ?>><?= $salesOpen ? 'Reserve & continue' : 'Ticket sales are closed' ?></button>
        </form>
    </section>
</div></section>
