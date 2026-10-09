<section class="section dashboard-page booking-history"><div class="container">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Your experiences</span>
            <h1>My bookings</h1>
            <p class="muted">Find a booking by reference, event, venue, status, or booking date.</p>
        </div>
        <a class="button" href="<?= e(url('events')) ?>">Discover events</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="catalog-filters booking-history-filters" method="get" action="<?= e(url('bookings')) ?>" role="search">
        <div class="catalog-search-field">
            <label for="booking-search">Search bookings</label>
            <input id="booking-search" type="search" name="q" value="<?= e($filters['search']) ?>" maxlength="100" placeholder="Reference, event, or venue">
        </div>
        <div>
            <label for="booking-status">Status</label>
            <select id="booking-status" name="status">
                <option value="any" <?= $filters['status'] === 'any' ? 'selected' : '' ?>>Any status</option>
                <option value="pending_payment" <?= $filters['status'] === 'pending_payment' ? 'selected' : '' ?>>Pending payment</option>
                <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="partially_cancelled" <?= $filters['status'] === 'partially_cancelled' ? 'selected' : '' ?>>Partially cancelled</option>
                <option value="payment_failed" <?= $filters['status'] === 'payment_failed' ? 'selected' : '' ?>>Payment failed</option>
                <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                <option value="customer_cancelled" <?= $filters['status'] === 'customer_cancelled' ? 'selected' : '' ?>>Customer cancelled</option>
                <option value="event_cancelled" <?= $filters['status'] === 'event_cancelled' ? 'selected' : '' ?>>Event cancelled</option>
            </select>
        </div>
        <div>
            <label for="booking-from">Booked from</label>
            <input id="booking-from" type="date" name="from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div>
            <label for="booking-to">Booked to</label>
            <input id="booking-to" type="date" name="to" value="<?= e($filters['date_to']) ?>">
        </div>
        <div>
            <label for="booking-sort">Sort by</label>
            <select id="booking-sort" name="sort">
                <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest booking</option>
                <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest booking</option>
                <option value="event_soonest" <?= $filters['sort'] === 'event_soonest' ? 'selected' : '' ?>>Event date</option>
                <option value="amount_high" <?= $filters['sort'] === 'amount_high' ? 'selected' : '' ?>>Highest amount</option>
                <option value="amount_low" <?= $filters['sort'] === 'amount_low' ? 'selected' : '' ?>>Lowest amount</option>
            </select>
        </div>
        <div class="catalog-filter-actions">
            <button class="button" type="submit">Show bookings</button>
            <?php if ($hasActiveFilters || $filters['sort'] !== 'newest'): ?><a class="button button-secondary" href="<?= e(url('bookings')) ?>">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if ($filterErrors !== []): ?>
        <div class="alert alert-warning" role="status"><?= e(implode(' ', $filterErrors)) ?></div>
    <?php endif; ?>

    <div class="catalog-result-summary" aria-live="polite">
        <?php if ($hasActiveFilters): ?>
            <span><?= e($filteredCount) ?> of <?= e($totalCount) ?> <?= $totalCount === 1 ? 'booking' : 'bookings' ?></span>
            <span>Matching your filters</span>
        <?php else: ?>
            <span><?= e($totalCount) ?> <?= $totalCount === 1 ? 'booking' : 'bookings' ?></span>
            <span>Your complete history</span>
        <?php endif; ?>
    </div>

    <?php if ($bookings === [] && $hasActiveFilters): ?>
        <div class="empty-state">
            <div class="empty-icon" aria-hidden="true">&times;</div>
            <h2>No matching bookings</h2>
            <p>Try another reference, status, or booking date range.</p>
            <a class="button button-secondary" href="<?= e(url('bookings')) ?>">Clear filters</a>
        </div>
    <?php elseif ($bookings === []): ?>
        <div class="empty-state">
            <div class="empty-icon" aria-hidden="true">+</div>
            <h2>No bookings yet</h2>
            <p>Your confirmed tickets and payment attempts will appear here.</p>
            <a class="button button-secondary" href="<?= e(url('events')) ?>">Discover events</a>
        </div>
    <?php else: ?>
        <div class="booking-list">
            <?php foreach ($bookings as $booking): ?>
                <a class="booking-row" href="<?= e(url('bookings/' . $booking['id'])) ?>">
                    <div>
                        <span class="event-meta"><?= e($booking['booking_reference']) ?> / Booked <?= e(local_datetime($booking['booked_at'], 'd M Y')) ?></span>
                        <h2><?= e($booking['event_title']) ?></h2>
                        <p><?= e(local_datetime($booking['start_datetime'])) ?> &middot; <?= e($booking['hall_name']) ?> &middot; <?= e($booking['total_quantity']) ?> <?= (int) $booking['total_quantity'] === 1 ? 'ticket' : 'tickets' ?></p>
                    </div>
                    <div>
                        <span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_', ' ', $booking['status'])) ?></span>
                        <strong><?= e(money($booking['total_amount'])) ?></strong>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></section>
