<section class="section account-section">
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <div class="account-header">
            <div>
                <span class="eyebrow"><?= e(ucfirst($user['role'])) ?> account</span>
                <h1>Hello, <?= e($user['name']) ?></h1>
                <p><?= e($user['email']) ?></p>
            </div>
            <span class="status-badge status-<?= e($user['account_status']) ?>">
                <?= e(ucfirst($user['account_status'])) ?>
            </span>
        </div>

        <?php if ($user['role'] === 'organizer' && $user['account_status'] !== 'approved'): ?>
            <div class="notice-card">
                <h2><?= $user['account_status'] === 'pending' ? 'Application under review' : 'Application needs attention' ?></h2>
                <p><?= $user['account_status'] === 'pending'
                    ? 'The hall manager will review your organizer application. Event creation remains locked until approval.'
                    : 'Your organizer application was rejected. You may correct the details below and resubmit it.' ?></p>
                <?php if ($user['account_status'] === 'rejected'): ?>
                    <?php if (!empty($user['review_reason'])): ?><div class="alert alert-error">Reason: <?= e($user['review_reason']) ?></div><?php endif; ?>
                    <form class="form-stack" method="post" action="<?= e(url('organizer/resubmit')) ?>">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="form-group"><label for="name">Full name</label><input id="name" name="name" maxlength="100" value="<?= e($user['name']) ?>" required></div>
                            <div class="form-group"><label for="phone">Phone number</label><input id="phone" name="phone" maxlength="20" value="<?= e($user['phone'] ?? '') ?>" required></div>
                        </div>
                        <button class="button" type="submit">Resubmit application</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="notice-card">
                <h2>Your dashboard is ready for the next module</h2>
                <p>Role-specific booking, organizer, and admin features will be connected phase by phase.</p>
            </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'admin'): ?>
            <p><a class="button" href="<?= e(url('admin/dashboard')) ?>">Open admin dashboard</a></p>
        <?php endif; ?>
        <?php if ($user['role'] === 'organizer' && $user['account_status'] === 'approved'): ?>
            <p><a class="button" href="<?= e(url('organizer/events')) ?>">Manage my events</a></p>
        <?php endif; ?>
        <?php if ($user['role'] === 'customer'): ?>
            <p><a class="button" href="<?= e(url('bookings')) ?>">View my bookings</a></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button class="button button-secondary" type="submit">Log out</button>
        </form>
    </div>
</section>
