<section class="section dashboard-page"><div class="container">
    <div class="page-heading"><div><span class="eyebrow">Hall schedule</span><h1>Event approvals</h1></div><a class="button button-secondary" href="<?= e(url('admin/dashboard')) ?>">Dashboard</a></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="filter-tabs"><a class="<?= $selectedStatus === null ? 'active' : '' ?>" href="<?= e(url('admin/events')) ?>">All</a><?php foreach (['pending','approved','rejected','cancelled'] as $status): ?><a class="<?= $selectedStatus === $status ? 'active' : '' ?>" href="<?= e(url('admin/events?status=' . $status)) ?>"><?= e(ucfirst($status)) ?></a><?php endforeach; ?></div>
    <div class="panel">
    <?php if ($events === []): ?><p class="muted">No events match this filter.</p><?php else: ?><div class="responsive-table"><table><thead><tr><th>Event</th><th>Organizer</th><th>Schedule</th><th>Tickets</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($events as $event): ?><tr>
        <td><strong><?= e($event['title']) ?></strong><small><?= e($event['category_name']) ?> · Capacity <?= e($event['event_capacity']) ?></small></td>
        <td><?= e($event['organizer_name']) ?><small><?= e($event['organizer_email']) ?></small></td>
        <td><?= e(date('d M Y, h:i A', strtotime($event['start_datetime'] . ' UTC'))) ?><small>to <?= e(date('d M, h:i A', strtotime($event['end_datetime'] . ' UTC'))) ?></small></td>
        <td><?= e($event['ticket_type_count']) ?> type(s)<small><?= e($event['ticket_capacity']) ?> capacity</small></td>
        <td><span class="status-badge status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></td>
        <td><a href="<?= e(url('admin/events/' . $event['id'])) ?>">Review</a></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
</div></section>

