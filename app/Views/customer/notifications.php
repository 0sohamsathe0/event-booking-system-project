<section class="section dashboard-page notifications-page">
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <header class="page-heading notifications-heading">
            <div>
                <span class="eyebrow">Account updates</span>
                <h1>Notifications</h1>
                <p><?= e($totalNotificationCount) ?> total<?= $unreadNotificationCount > 0 ? ' / ' . e($unreadNotificationCount) . ' unread' : '' ?></p>
            </div>
            <?php if ($unreadNotificationCount > 0): ?>
                <form method="post" action="<?= e(url('notifications/read-all')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="page" value="<?= e($page) ?>">
                    <button class="button button-secondary" type="submit">Mark all as read</button>
                </form>
            <?php endif; ?>
        </header>

        <?php if ($notifications === []): ?>
            <div class="empty-state notification-empty-state">
                <span class="eyebrow">All caught up</span>
                <h2>No notifications yet</h2>
                <p>Booking confirmations and account updates will appear here.</p>
                <a class="button button-secondary" href="<?= e(url('events')) ?>">Browse events</a>
            </div>
        <?php else: ?>
            <div class="notification-history" aria-label="Notification history">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $isUnread = $notification['read_at'] === null;
                    $relatedPath = null;
                    $relatedLabel = null;
                    if ($notification['owned_booking_id'] !== null) {
                        $relatedPath = 'bookings/' . $notification['owned_booking_id'];
                        $relatedLabel = 'View booking';
                    } elseif ($notification['public_event_id'] !== null) {
                        $relatedPath = 'events/' . $notification['public_event_id'];
                        $relatedLabel = 'View event';
                    }
                    $machineTime = str_replace(' ', 'T', (string) $notification['created_at']) . 'Z';
                    ?>
                    <article class="notification-history-item<?= $isUnread ? ' is-unread' : '' ?>">
                        <div class="notification-history-marker" aria-hidden="true"></div>
                        <div class="notification-history-copy">
                            <div class="notification-history-meta">
                                <span><?= e(ucwords(str_replace('_', ' ', $notification['type']))) ?></span>
                                <time datetime="<?= e($machineTime) ?>"><?= e(local_datetime($notification['created_at'], 'd M Y, h:i A')) ?></time>
                            </div>
                            <h2>
                                <?php if ($isUnread): ?><span class="sr-only">Unread notification: </span><?php endif; ?>
                                <?php if ($relatedPath !== null): ?>
                                    <a href="<?= e(url($relatedPath)) ?>"><?= e($notification['title']) ?></a>
                                <?php else: ?>
                                    <?= e($notification['title']) ?>
                                <?php endif; ?>
                            </h2>
                            <p><?= e($notification['message']) ?></p>
                        </div>
                        <div class="notification-history-actions">
                            <?php if ($relatedPath !== null): ?>
                                <a class="text-link" href="<?= e(url($relatedPath)) ?>"><?= e($relatedLabel) ?> &rarr;</a>
                            <?php endif; ?>
                            <?php if ($isUnread): ?>
                                <form method="post" action="<?= e(url('notifications/' . $notification['id'] . '/read')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="page" value="<?= e($page) ?>">
                                    <button class="text-button" type="submit">Mark as read</button>
                                </form>
                            <?php else: ?>
                                <span class="notification-read-state">Read</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pageCount > 1): ?>
                <nav class="pagination" aria-label="Notification pages">
                    <?php if ($page > 1): ?><a href="<?= e(url('notifications?page=' . ($page - 1))) ?>">&larr; Newer</a><?php else: ?><span></span><?php endif; ?>
                    <span>Page <?= e($page) ?> of <?= e($pageCount) ?></span>
                    <?php if ($page < $pageCount): ?><a href="<?= e(url('notifications?page=' . ($page + 1))) ?>">Older &rarr;</a><?php else: ?><span></span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
