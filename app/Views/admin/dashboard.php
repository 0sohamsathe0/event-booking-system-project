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
