<section class="section dashboard-page">
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <header class="page-heading">
            <div><span class="eyebrow">Organizer overview</span><h1>Welcome, <?= e($user['name']) ?></h1><p class="muted">Confirmed attendance and revenue across your events.</p></div>
            <div class="hero-actions"><a class="button button-secondary" href="<?= e(url('organizer/events')) ?>">My events</a><a class="button" href="<?= e(url('organizer/bookings')) ?>">View bookings</a></div>
        </header>
        <div class="stat-grid">
            <article class="stat-card"><span>Confirmed revenue</span><strong><?= e(money($metrics['confirmed_revenue'])) ?></strong></article>
            <article class="stat-card"><span>Tickets sold</span><strong><?= e($metrics['confirmed_tickets']) ?></strong></article>
            <article class="stat-card"><span>Confirmed bookings</span><strong><?= e($metrics['confirmed_bookings']) ?></strong></article>
            <article class="stat-card"><span>Unique attendees</span><strong><?= e($metrics['confirmed_customers']) ?></strong></article>
        </div>
        <div class="panel-heading"><div><span class="eyebrow">Cancellation impact</span><h2>Refund visibility</h2></div><a href="<?= e(url('organizer/cancellations')) ?>">Cancellation requests</a></div>
        <div class="stat-grid">
            <article class="stat-card"><span>Refund records</span><strong><?= e($refundMetrics['total_refunds']) ?></strong></article>
            <article class="stat-card"><span>Pending</span><strong><?= e($refundMetrics['pending_refunds']) ?></strong></article>
            <article class="stat-card"><span>Failed</span><strong><?= e($refundMetrics['failed_refunds']) ?></strong></article>
            <article class="stat-card"><span>Processed amount</span><strong><?= e(money($refundMetrics['refunded_amount'])) ?></strong></article>
            <article class="stat-card"><span>Cancelled tickets</span><strong><?= e($refundMetrics['cancelled_tickets']) ?></strong></article>
            <article class="stat-card"><span>Pending requests</span><strong><?= e($refundMetrics['pending_cancellation_requests']) ?></strong></article>
        </div>
        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">Latest activity</span><h2>Recent bookings</h2></div><a href="<?= e(url('organizer/bookings')) ?>">View all</a></div>
            <?php if ($recentBookings === []): ?><p class="muted">Bookings for your events will appear here.</p><?php else: ?>
                <div class="responsive-table"><table><thead><tr><th>Booking</th><th>Customer</th><th>Event</th><th>Status</th><th>Total</th></tr></thead><tbody>
                <?php foreach ($recentBookings as $booking): ?><tr>
                    <td><a href="<?= e(url('organizer/bookings/' . $booking['id'])) ?>"><strong><?= e($booking['booking_reference']) ?></strong></a><small><?= e(local_datetime($booking['booked_at'])) ?></small></td>
                    <td><?= e($booking['customer_name']) ?><small><?= e($booking['customer_email']) ?></small></td>
                    <td><?= e($booking['event_title']) ?></td>
                    <td><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></td>
                    <td><?= e(money($booking['total_amount'])) ?></td>
                </tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </div>
</section>
