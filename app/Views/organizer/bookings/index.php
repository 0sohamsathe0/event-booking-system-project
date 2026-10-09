<?php
$query = ['q' => $filters['search'], 'status' => $filters['status'], 'payment_status' => $filters['payment_status'], 'event' => $filters['event_id'], 'from' => $filters['date_from'], 'to' => $filters['date_to'], 'sort' => $filters['sort']];
$pageLink = static fn (int $target): string => url('organizer/bookings?' . http_build_query(array_filter($query + ['page' => $target], static fn ($value) => $value !== null && $value !== '')));
?>
<section class="section dashboard-page"><div class="container">
    <div class="page-heading"><div><span class="eyebrow">Organizer operations</span><h1>Event bookings</h1><p class="muted"><?= e($total) ?> booking<?= $total === 1 ? '' : 's' ?> across your events.</p></div><a class="button button-secondary" href="<?= e(url('account')) ?>">Overview</a></div>
    <?php foreach ($filterErrors as $message): ?><div class="alert alert-warning"><?= e($message) ?></div><?php endforeach; ?>
    <form class="catalog-filters management-booking-filters" method="get" action="<?= e(url('organizer/bookings')) ?>">
        <div class="catalog-search-field"><label for="q">Search</label><input id="q" name="q" maxlength="100" value="<?= e($filters['search']) ?>" placeholder="Reference, event, customer"></div>
        <div><label for="status">Booking</label><select id="status" name="status"><?php foreach (['any','pending_payment','confirmed','partially_cancelled','payment_failed','expired','customer_cancelled','event_cancelled'] as $value): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$value))) ?></option><?php endforeach; ?></select></div>
        <div><label for="payment_status">Payment</label><select id="payment_status" name="payment_status"><?php foreach (['any','not_required','not_started','created','authorized','captured','partially_refunded','failed','refunded'] as $value): ?><option value="<?= e($value) ?>" <?= $filters['payment_status'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$value))) ?></option><?php endforeach; ?></select></div>
        <div><label for="event">Event</label><select id="event" name="event"><option value="">All events</option><?php foreach ($events as $event): ?><option value="<?= e($event['id']) ?>" <?= $filters['event_id'] === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['title']) ?></option><?php endforeach; ?></select></div>
        <div><label for="from">Booked from</label><input id="from" name="from" type="date" value="<?= e($filters['date_from']) ?>"></div>
        <div><label for="to">Booked to</label><input id="to" name="to" type="date" value="<?= e($filters['date_to']) ?>"></div>
        <div><label for="sort">Sort</label><select id="sort" name="sort"><?php foreach (['newest'=>'Newest','oldest'=>'Oldest','event_soonest'=>'Event soonest','amount_high'=>'Amount high','amount_low'=>'Amount low'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="catalog-filter-actions"><button class="button button-compact" type="submit">Apply filters</button><a class="button button-secondary button-compact" href="<?= e(url('organizer/bookings')) ?>">Reset</a></div>
    </form>
    <div class="panel">
    <?php if ($bookings === []): ?><div class="empty-state"><h2>No bookings found</h2><p>Adjust the filters or wait for customers to reserve your events.</p></div><?php else: ?>
        <div class="responsive-table"><table><thead><tr><th>Booking</th><th>Customer</th><th>Event</th><th>Tickets</th><th>Booking / payment</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($bookings as $booking): ?><tr>
            <td><a href="<?= e(url('organizer/bookings/' . $booking['id'])) ?>"><strong><?= e($booking['booking_reference']) ?></strong></a><small><?= e(local_datetime($booking['booked_at'])) ?></small></td>
            <td><?= e($booking['customer_name']) ?><small><?= e($booking['customer_email']) ?></small></td>
            <td><?= e($booking['event_title']) ?><small><?= e(local_datetime($booking['start_datetime'])) ?></small></td>
            <td><?= e($booking['total_quantity']) ?></td>
            <td><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(str_replace('_',' ',$booking['status'])) ?></span><small><?= e(ucwords(str_replace('_',' ',$booking['payment_status']))) ?></small></td>
            <td><?= e(money($booking['total_amount'])) ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    </div>
    <?php if ($pageCount > 1): ?><nav class="pagination" aria-label="Booking pages"><?= $page > 1 ? '<a href="' . e($pageLink($page - 1)) . '">&larr; Previous</a>' : '<span></span>' ?><span>Page <?= e($page) ?> of <?= e($pageCount) ?></span><?= $page < $pageCount ? '<a href="' . e($pageLink($page + 1)) . '">Next &rarr;</a>' : '<span></span>' ?></nav><?php endif; ?>
</div></section>
