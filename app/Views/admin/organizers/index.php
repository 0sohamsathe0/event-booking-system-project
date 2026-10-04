<section class="section dashboard-page">
    <div class="container">
        <div class="page-heading">
            <div><span class="eyebrow">Administration</span><h1>Organizer management</h1></div>
            <a class="button button-secondary" href="<?= e(url('admin/dashboard')) ?>">Dashboard</a>
        </div>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <div class="filter-tabs" aria-label="Organizer status filters">
            <a class="<?= $selectedStatus === null ? 'active' : '' ?>" href="<?= e(url('admin/organizers')) ?>">All</a>
            <?php foreach (['pending', 'approved', 'rejected', 'disabled'] as $status): ?>
                <a class="<?= $selectedStatus === $status ? 'active' : '' ?>"
                   href="<?= e(url('admin/organizers?status=' . $status)) ?>"><?= e(ucfirst($status)) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <?php if ($organizers === []): ?><p class="muted">No organizers match this filter.</p><?php else: ?>
            <div class="responsive-table"><table><thead><tr><th>Organizer</th><th>Contact</th><th>Status</th><th>Applied</th><th>Review</th></tr></thead><tbody>
            <?php foreach ($organizers as $organizer): ?><tr>
                <td><a href="<?= e(url('admin/organizers/' . $organizer['id'])) ?>"><strong><?= e($organizer['name']) ?></strong></a><small>ID #<?= e($organizer['id']) ?></small></td>
                <td><?= e($organizer['email']) ?><small><?= e($organizer['phone']) ?></small></td>
                <td><span class="status-badge status-<?= e($organizer['account_status']) ?>"><?= e(ucfirst($organizer['account_status'])) ?></span></td>
                <td><?= e(date('d M Y', strtotime($organizer['created_at'] . ' UTC'))) ?></td>
                <td>
                    <?php if ($organizer['account_status'] === 'pending'): ?>
                    <div class="review-actions">
                        <form method="post" action="<?= e(url('admin/organizers/' . $organizer['id'] . '/approve')) ?>">
                            <?= csrf_field() ?><button class="button button-compact" type="submit">Approve</button>
                        </form>
                        <form method="post" action="<?= e(url('admin/organizers/' . $organizer['id'] . '/reject')) ?>" class="reject-form">
                            <?= csrf_field() ?>
                            <input name="reason" maxlength="500" placeholder="Rejection reason" aria-label="Rejection reason" required>
                            <button class="button button-danger button-compact" type="submit">Reject</button>
                        </form>
                    </div>
                    <?php elseif ($organizer['review_reason']): ?><small><?= e($organizer['review_reason']) ?></small>
                    <?php else: ?><span class="muted">Reviewed</span><?php endif; ?>
                </td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</section>
