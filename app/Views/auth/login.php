<?php $success = \App\Core\Session::consumeFlash('success'); ?>
<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <span class="eyebrow">Welcome back</span>
            <h1>Log in to your account</h1>
            <p>Manage your bookings, events, or hall approvals securely.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if (isset($errors['credentials'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['credentials']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login')) ?>" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email"
                       value="<?= e($old['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
            </div>

            <button class="button button-full" type="submit">Log in</button>
        </form>

        <div class="auth-links">
            <a href="<?= e(url('register')) ?>">Create customer account</a>
            <a href="<?= e(url('organizer/register')) ?>">Apply as organizer</a>
        </div>
    </div>
</section>

