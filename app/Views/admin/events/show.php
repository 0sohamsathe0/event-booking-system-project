<section class="section dashboard-page"><div class="container form-page">
    <div class="page-heading"><div><span class="eyebrow">Event review</span><h1><?= e($event['title']) ?></h1></div><a class="button button-secondary" href="<?= e(url('admin/events')) ?>">All events</a></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="stat-grid">
        <article class="stat-card"><span>Confirmed bookings</span><strong><?= e($bookingMetrics['confirmed_bookings']) ?></strong></article>
        <article class="stat-card"><span>Tickets sold</span><strong><?= e($bookingMetrics['confirmed_tickets']) ?></strong></article>
        <article class="stat-card"><span>Unique attendees</span><strong><?= e($bookingMetrics['confirmed_customers']) ?></strong></article>
        <article class="stat-card"><span>Confirmed revenue</span><strong><?= e(money($bookingMetrics['confirmed_revenue'])) ?></strong><a href="<?= e(url('admin/bookings?event=' . $event['id'])) ?>">View bookings</a></article>
    </div>
    <div class="review-layout">
        <article class="panel">
            <?php if ($event['poster_path']): ?><img class="review-poster" src="<?= e(url($event['poster_path'])) ?>" alt="<?= e($event['title']) ?> poster"><?php endif; ?>
            <div class="card-meta"><span><?= e($event['category_name']) ?></span><span class="status-badge status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></div>
            <p><?= nl2br(e($event['description'])) ?></p>
            <dl class="details-list">
                <div><dt>Organizer</dt><dd><?= e($event['organizer_name']) ?> · <?= e($event['organizer_email']) ?></dd></div>
                <div><dt>Hall</dt><dd><?= e($event['hall_name']) ?></dd></div>
                <div><dt>Event time</dt><dd><?= e(date('d M Y, h:i A', strtotime($event['start_datetime'] . ' UTC'))) ?> – <?= e(date('d M Y, h:i A', strtotime($event['end_datetime'] . ' UTC'))) ?></dd></div>
                <div><dt>Sales window</dt><dd><?= e(date('d M Y, h:i A', strtotime($event['sale_start_datetime'] . ' UTC'))) ?> – <?= e(date('d M Y, h:i A', strtotime($event['sale_end_datetime'] . ' UTC'))) ?></dd></div>
                <div><dt>Capacity</dt><dd>Event <?= e($event['event_capacity']) ?> / Hall <?= e($event['hall_capacity']) ?></dd></div>
                <div><dt>Ticket configuration</dt><dd><?= e($event['ticket_type_count']) ?> type(s), combined capacity <?= e($event['ticket_capacity']) ?></dd></div>
            </dl>
        </article>
        <?php if ($event['status'] === 'pending'): ?><aside class="panel decision-panel">
            <h2>Admin decision</h2>
            <?php if ((int)$event['ticket_type_count'] < 1): ?><div class="alert alert-warning">This event cannot be approved until the organizer adds at least one ticket type in Phase 10.</div><?php endif; ?>
            <form method="post" action="<?= e(url('admin/events/' . $event['id'] . '/approve')) ?>"><?= csrf_field() ?><button class="button button-full" type="submit" <?= (int)$event['ticket_type_count'] < 1 ? 'disabled' : '' ?>>Approve event</button></form>
            <form class="form-stack" method="post" action="<?= e(url('admin/events/' . $event['id'] . '/reject')) ?>"><?= csrf_field() ?><div class="form-group"><label for="reason">Rejection reason</label><textarea id="reason" name="reason" maxlength="1000" rows="4" required></textarea></div><button class="button button-danger button-full" type="submit">Reject event</button></form>
        </aside><?php endif; ?>
    </div>
</div></section>
