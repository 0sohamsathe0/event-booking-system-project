<?php
$usedCapacity = 0;
foreach ($tickets as $ticket) {
    if ((int)$ticket['is_active'] === 1 || (int)$ticket['sold_quantity'] > 0 || (int)$ticket['reserved_quantity'] > 0) {
        $usedCapacity += (int)$ticket['capacity'];
    }
}
?>
<section class="section dashboard-page"><div class="container">
    <div class="page-heading"><div><span class="eyebrow">Ticket inventory</span><h1><?= e($event['title']) ?></h1><p class="muted">Configured capacity: <?= e($usedCapacity) ?> / <?= e($event['event_capacity']) ?></p></div><a class="button button-secondary" href="<?= e(url('organizer/events')) ?>">My events</a></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><strong>Ticket type was not added.</strong> Please correct the highlighted fields below.</div><?php endif; ?>
    <?php if ($event['status'] === 'approved'): ?><div class="alert alert-warning">Changing tickets will return this approved event to Pending for admin review.</div><?php endif; ?>

    <div class="ticket-layout">
        <div class="panel">
            <div class="panel-heading"><h2>Ticket types</h2><span class="status-badge status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?> event</span></div>
            <?php if ($tickets === []): ?><p class="muted">No ticket types yet. Add at least one before admin approval.</p><?php endif; ?>
            <div class="ticket-stack">
            <?php foreach ($tickets as $ticket): $available = (int)$ticket['capacity'] - (int)$ticket['reserved_quantity'] - (int)$ticket['sold_quantity']; ?>
                <article class="ticket-editor <?= (int)$ticket['is_active'] ? '' : 'ticket-inactive' ?>">
                    <form method="post" action="<?= e(url('organizer/events/' . $event['id'] . '/tickets/' . $ticket['id'])) ?>" class="ticket-edit-grid">
                        <?= csrf_field() ?>
                        <div class="form-group"><label>Name</label><input name="name" maxlength="80" value="<?= e($ticket['name']) ?>" required></div>
                        <div class="form-group"><label>Price (₹)</label><input name="price" inputmode="decimal" value="<?= e($ticket['price']) ?>" required></div>
                        <div class="form-group"><label>Capacity</label><input name="capacity" type="number" min="1" value="<?= e($ticket['capacity']) ?>" required></div>
                        <div class="form-group"><label>Order</label><input name="display_order" type="number" min="0" value="<?= e($ticket['display_order']) ?>"></div>
                        <button class="button button-compact" type="submit">Save</button>
                    </form>
                    <div class="inventory-row"><span>Reserved <strong><?= e($ticket['reserved_quantity']) ?></strong></span><span>Sold <strong><?= e($ticket['sold_quantity']) ?></strong></span><span>Available <strong><?= e($available) ?></strong></span>
                        <form method="post" action="<?= e(url('organizer/events/' . $event['id'] . '/tickets/' . $ticket['id'] . '/' . ((int)$ticket['is_active'] ? 'deactivate' : 'activate'))) ?>"><?= csrf_field() ?><button class="text-button" type="submit"><?= (int)$ticket['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div>

        <aside class="panel decision-panel"><h2>Add ticket type</h2>
            <form method="post" action="<?= e(url('organizer/events/' . $event['id'] . '/tickets')) ?>" class="form-stack" novalidate><?= csrf_field() ?>
                <div class="form-group"><label for="name">Name</label><input id="name" name="name" maxlength="80" placeholder="Standard" value="<?= e($old['name'] ?? '') ?>" required><?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="price">Price (₹)</label><input id="price" name="price" inputmode="decimal" placeholder="300.00" value="<?= e($old['price'] ?? '') ?>" required><?php if (isset($errors['price'])): ?><p class="field-error"><?= e($errors['price']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="capacity">Capacity</label><input id="capacity" name="capacity" type="number" min="1" max="<?= e($event['event_capacity']) ?>" value="<?= e($old['capacity'] ?? '') ?>" required><?php if (isset($errors['capacity'])): ?><p class="field-error"><?= e($errors['capacity']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="display_order">Display order</label><input id="display_order" name="display_order" type="number" min="0" value="<?= e($old['display_order'] ?? 0) ?>"></div>
                <button class="button button-full" type="submit">Add ticket type</button>
            </form>
        </aside>
    </div>
</div></section>
