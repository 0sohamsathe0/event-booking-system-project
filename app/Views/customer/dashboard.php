<section class="section dashboard-page customer-dashboard">
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <header class="page-heading customer-dashboard-heading">
            <div>
                <span class="eyebrow">Attendee dashboard</span>
                <h1>Welcome back, <?= e($user['name']) ?></h1>
                <p>Your next experiences, recent bookings, and account updates in one place.</p>
            </div>
            <a class="button" href="<?= e(url('events')) ?>">Browse events</a>
        </header>

        <nav class="customer-quick-actions" aria-label="Customer quick actions">
            <a href="<?= e(url('events')) ?>"><span>Discover</span><strong>Browse events</strong></a>
            <a href="<?= e(url('bookings')) ?>"><span>Bookings</span><strong>View history</strong></a>
            <a href="<?= e(url('notifications')) ?>"><span>Notifications</span><strong><?= e($unreadNotificationCount) ?> unread</strong></a>
            <a href="<?= e(url('profile')) ?>"><span>Profile</span><strong>Manage details</strong></a>
        </nav>

        <div class="customer-dashboard-grid">
            <section class="panel dashboard-panel dashboard-panel-wide" aria-labelledby="upcoming-heading">
                <div class="panel-heading">
                    <div><span class="eyebrow">On your calendar</span><h2 id="upcoming-heading">Upcoming bookings</h2></div>
                    <a href="<?= e(url('bookings')) ?>">View all bookings</a>
                </div>
                <?php if ($upcomingBookings === []): ?>
                    <div class="dashboard-empty-state">
                        <h3>No upcoming bookings</h3>
                        <p>Explore the latest approved events and reserve your next experience.</p>
                        <a class="text-link" href="<?= e(url('events')) ?>">Browse events &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-booking-list">
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <article class="dashboard-booking-card">
                                <div>
                                    <span class="event-meta"><?= e($booking['booking_reference']) ?></span>
                                    <h3><a href="<?= e(url('bookings/' . $booking['id'])) ?>"><?= e($booking['event_title']) ?></a></h3>
                                    <p><?= e(local_datetime($booking['start_datetime'])) ?> &middot; <?= e($booking['hall_name']) ?></p>
                                </div>
                                <dl class="dashboard-booking-facts">
                                    <div><dt>Tickets</dt><dd><?= e($booking['total_quantity']) ?></dd></div>
                                    <div><dt>Total</dt><dd><?= e(money($booking['total_amount'])) ?></dd></div>
                                    <div><dt>Status</dt><dd><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span></dd></div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel dashboard-panel" aria-labelledby="pending-heading">
                <div class="panel-heading"><div><span class="eyebrow">Action needed</span><h2 id="pending-heading">Pending payments</h2></div></div>
                <?php if ($pendingBookings === []): ?>
                    <div class="dashboard-empty-state compact"><h3>No pending payments</h3><p>You have no active reservations waiting for payment.</p></div>
                <?php else: ?>
                    <div class="dashboard-compact-list">
                        <?php foreach ($pendingBookings as $booking): ?>
                            <article>
                                <span class="event-meta"><?= e($booking['booking_reference']) ?></span>
                                <h3><?= e($booking['event_title']) ?></h3>
                                <p>Reserved until <?= e(local_datetime($booking['reservation_expires_at'])) ?></p>
                                <div class="dashboard-item-actions">
                                    <strong><?= e(money($booking['total_amount'])) ?></strong>
                                    <a class="button button-small" href="<?= e(url('bookings/' . $booking['id'] . '/checkout')) ?>">Complete payment</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel dashboard-panel" aria-labelledby="notifications-heading">
                <div class="panel-heading">
                    <div><span class="eyebrow">Account updates</span><h2 id="notifications-heading">Notifications</h2></div>
                    <div class="panel-heading-actions">
                        <?php if ($unreadNotificationCount > 0): ?><span class="notification-count" aria-label="<?= e($unreadNotificationCount) ?> unread notifications"><?= e($unreadNotificationCount) ?></span><?php endif; ?>
                        <a href="<?= e(url('notifications')) ?>">View all notifications</a>
                    </div>
                </div>
                <?php if ($recentNotifications === []): ?>
                    <div class="dashboard-empty-state compact"><h3>No notifications yet</h3><p>Booking and account updates will appear here.</p></div>
                <?php else: ?>
                    <div class="notification-preview-list">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <article class="<?= $notification['read_at'] === null ? 'is-unread' : '' ?>">
                                <div><h3><?php if ($notification['read_at'] === null): ?><span class="sr-only">Unread notification: </span><?php endif; ?><?= e($notification['title']) ?></h3><p><?= e($notification['message']) ?></p></div>
                                <time datetime="<?= e($notification['created_at']) ?>"><?= e(local_datetime($notification['created_at'], 'd M')) ?></time>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel dashboard-panel dashboard-panel-wide" aria-labelledby="recent-heading">
                <div class="panel-heading">
                    <div><span class="eyebrow">Latest activity</span><h2 id="recent-heading">Recent bookings</h2></div>
                    <a href="<?= e(url('bookings')) ?>">View all bookings</a>
                </div>
                <?php if ($recentBookings === []): ?>
                    <div class="dashboard-empty-state"><h3>No recent bookings</h3><p>Your reservations and confirmed tickets will appear here.</p><a class="text-link" href="<?= e(url('events')) ?>">Browse events &rarr;</a></div>
                <?php else: ?>
                    <div class="recent-booking-grid">
                        <?php foreach ($recentBookings as $booking): ?>
                            <a href="<?= e(url('bookings/' . $booking['id'])) ?>">
                                <span class="event-meta"><?= e($booking['booking_reference']) ?></span>
                                <h3><?= e($booking['event_title']) ?></h3>
                                <p>Booked <?= e(local_datetime($booking['booked_at'], 'd M Y')) ?></p>
                                <span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
