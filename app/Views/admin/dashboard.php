<section class="section dashboard-page">
    <div class="container">
        <div class="page-heading">
            <div><span class="eyebrow">Hall management</span><h1>Admin dashboard</h1></div>
            <div class="hero-actions"><a class="button button-secondary" href="<?= e(url('admin/hall')) ?>">Hall settings</a><a class="button button-secondary" href="<?= e(url('admin/organizers')) ?>">Organizers</a><a class="button" href="<?= e(url('admin/events?status=pending')) ?>">Review events</a></div>
        </div>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <div class="stat-grid">
            <article class="stat-card"><span>Customers</span><strong><?= e($counts['customers']) ?></strong></article>
            <article class="stat-card"><span>Organizers</span><strong><?= e($counts['organizers']) ?></strong></article>
            <article class="stat-card"><span>Pending approvals</span><strong><?= e($counts['pending_organizers']) ?></strong></article>
            <article class="stat-card"><span>Pending events</span><strong><?= e($counts['pending_events']) ?></strong></article>
        </div>

        <div class="panel-heading"><div><span class="eyebrow">Cancellation operations</span><h2>Refund status</h2></div><div class="hero-actions"><a href="<?= e(url('admin/cancellations')) ?>">Review cancellations</a><a href="<?= e(url('admin/refunds')) ?>">Manage refunds</a></div></div>
        <div class="stat-grid">
            <article class="stat-card"><span>All refunds</span><strong><?= e($refundMetrics['total_refunds']) ?></strong></article>
            <article class="stat-card"><span>Pending</span><strong><?= e($refundMetrics['pending_refunds']) ?></strong></article>
            <article class="stat-card"><span>Failed / retry</span><strong><?= e($refundMetrics['failed_refunds']) ?></strong></article>
            <article class="stat-card"><span>Refunded</span><strong><?= e(money($refundMetrics['refunded_amount'])) ?></strong></article>
            <article class="stat-card"><span>Cancelled tickets</span><strong><?= e($refundMetrics['cancelled_tickets']) ?></strong></article>
            <article class="stat-card"><span>Cancellation requests</span><strong><?= e($refundMetrics['pending_cancellation_requests']) ?></strong></article>
        </div>

        <div class="panel-heading"><div><span class="eyebrow">Commercial overview</span><h2>Bookings and revenue</h2></div><div class="hero-actions"><a href="<?= e(url('admin/customers')) ?>">Customers</a><a href="<?= e(url('admin/bookings')) ?>">All bookings</a></div></div>
        <div class="stat-grid">
            <article class="stat-card"><span>All / confirmed bookings</span><strong><?= e($bookingMetrics['total_bookings']) ?> / <?= e($bookingMetrics['confirmed_bookings']) ?></strong></article>
            <article class="stat-card"><span>Confirmed tickets</span><strong><?= e($bookingMetrics['confirmed_tickets']) ?></strong></article>
            <article class="stat-card"><span>Confirmed revenue</span><strong><?= e(money($bookingMetrics['confirmed_revenue'])) ?></strong></article>
            <article class="stat-card"><span>Pending / failed</span><strong><?= e($bookingMetrics['pending_bookings']) ?> / <?= e($bookingMetrics['failed_bookings']) ?></strong></article>
        </div>

        <div class="panel">
            <div class="panel-heading"><h2>Recent bookings</h2><a href="<?= e(url('admin/bookings')) ?>">View all</a></div>
            <?php if ($recentBookings === []): ?><p class="muted">No booking activity yet.</p><?php else: ?><div class="responsive-table"><table><thead><tr><th>Reference</th><th>Customer</th><th>Event</th><th>Status</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($recentBookings as $booking): ?><tr><td><a href="<?= e(url('admin/bookings/' . $booking['id'])) ?>"><strong><?= e($booking['booking_reference']) ?></strong></a><small><?= e(local_datetime($booking['booked_at'])) ?></small></td><td><?= e($booking['customer_name']) ?></td><td><?= e($booking['event_title']) ?></td><td><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></td><td><?= e(money($booking['total_amount'])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-heading"><h2>Pending organizer applications</h2><a href="<?= e(url('admin/organizers?status=pending')) ?>">View all</a></div>
            <?php if ($pendingOrganizers === []): ?>
                <p class="muted">No organizer applications are waiting for review.</p>
            <?php else: ?>
                <div class="responsive-table"><table><thead><tr><th>Name</th><th>Email</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($pendingOrganizers as $organizer): ?><tr>
                    <td><?= e($organizer['name']) ?></td><td><?= e($organizer['email']) ?></td>
                    <td><?= e(date('d M Y, h:i A', strtotime($organizer['created_at'] . ' UTC'))) ?></td>
                    <td><a href="<?= e(url('admin/organizers?status=pending')) ?>">Review</a></td>
                </tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</section>
