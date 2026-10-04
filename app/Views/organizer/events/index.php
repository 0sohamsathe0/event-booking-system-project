<section class="section dashboard-page">
    <div class="container">
        <div class="page-heading">
            <div><span class="eyebrow">Organizer workspace</span><h1>My events</h1><p class="muted">Create events and track their approval status.</p></div>
            <a class="button" href="<?= e(url('organizer/events/create')) ?>">Create event</a>
        </div>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($events === []): ?>
            <div class="empty-state"><div class="empty-icon">+</div><h2>No events created yet</h2><p>Start with the event schedule and details. Ticket types are configured separately.</p><a class="button" href="<?= e(url('organizer/events/create')) ?>">Create your first event</a></div>
        <?php else: ?>
            <div class="event-management-grid">
            <?php foreach ($events as $event): ?>
                <article class="management-card">
                    <div class="poster-thumb">
                        <?php if ($event['poster_path']): ?><img src="<?= e(url($event['poster_path'])) ?>" alt="<?= e($event['title']) ?> poster"><?php else: ?><span>No poster</span><?php endif; ?>
                    </div>
                    <div class="management-card-body">
                        <div class="card-meta"><span><?= e($event['category_name']) ?></span><span class="status-badge status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></div>
                        <h2><?= e($event['title']) ?></h2>
                        <p class="muted"><?= e(date('d M Y, h:i A', strtotime($event['start_datetime'] . ' UTC'))) ?> · <?= e($event['hall_name']) ?></p>
                        <p>Capacity: <?= e($event['event_capacity']) ?></p>
                        <dl class="event-booking-metrics"><div><dt>Bookings</dt><dd><?= e($event['confirmed_bookings']) ?></dd></div><div><dt>Tickets</dt><dd><?= e($event['confirmed_tickets']) ?></dd></div><div><dt>Revenue</dt><dd><?= e(money($event['confirmed_revenue'])) ?></dd></div></dl>
                        <?php if ($event['status'] === 'rejected' && $event['rejection_reason']): ?><div class="alert alert-error">Reason: <?= e($event['rejection_reason']) ?></div><?php endif; ?>
                        <div class="hero-actions"><a class="button button-secondary button-compact" href="<?= e(url('organizer/events/' . $event['id'] . '/bookings')) ?>">Bookings</a><?php if ($event['status'] !== 'cancelled'): ?><a class="button button-secondary button-compact" href="<?= e(url('organizer/events/' . $event['id'] . '/edit')) ?>">Edit</a><a class="button button-compact" href="<?= e(url('organizer/events/' . $event['id'] . '/tickets')) ?>">Tickets</a><?php endif; ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
