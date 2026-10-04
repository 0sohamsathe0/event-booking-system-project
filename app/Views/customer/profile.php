<section class="section dashboard-page customer-profile">
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>

        <header class="page-heading profile-heading">
            <div>
                <span class="eyebrow">Attendee profile</span>
                <h1>Account details</h1>
                <p class="muted">Keep your contact details current for booking and account updates.</p>
            </div>
            <span class="role-label">Attendee</span>
        </header>

        <div class="profile-layout">
            <section class="panel profile-panel" aria-labelledby="profile-details-heading">
                <div class="panel-heading">
                    <div><span class="eyebrow">Personal information</span><h2 id="profile-details-heading">Your details</h2></div>
                </div>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-error" role="alert">Please correct the highlighted fields.</div>
                <?php endif; ?>

                <form class="form-stack" method="post" action="<?= e(url('profile')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="profile-name">Full name</label>
                        <input id="profile-name" name="name" value="<?= e($old['name']) ?>" minlength="2" maxlength="100" autocomplete="name" required aria-describedby="<?= isset($errors['name']) ? 'profile-name-error' : '' ?>">
                        <?php if (isset($errors['name'])): ?><p class="field-error" id="profile-name-error"><?= e($errors['name']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="profile-phone">Phone number</label>
                        <input id="profile-phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" maxlength="20" autocomplete="tel" required aria-describedby="<?= isset($errors['phone']) ? 'profile-phone-error profile-phone-help' : 'profile-phone-help' ?>">
                        <small class="field-help" id="profile-phone-help">Use 10 to 15 digits, with an optional country-code prefix.</small>
                        <?php if (isset($errors['phone'])): ?><p class="field-error" id="profile-phone-error"><?= e($errors['phone']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email address</label>
                        <input id="profile-email" type="email" value="<?= e($user['email'] ?? '') ?>" autocomplete="email" readonly aria-describedby="profile-email-help">
                        <small class="field-help" id="profile-email-help">Email changes are unavailable in the submission build.</small>
                    </div>
                    <div class="profile-form-actions">
                        <button class="button" type="submit">Save profile</button>
                        <a class="button button-secondary" href="<?= e(url('account')) ?>">Back to dashboard</a>
                    </div>
                </form>
            </section>

            <aside class="profile-context" aria-label="Profile information">
                <span class="eyebrow">Account status</span>
                <strong><?= e(ucfirst((string) ($user['account_status'] ?? 'approved'))) ?></strong>
                <p>Your profile details are used for your attendee account. Existing booking records retain their original transaction history.</p>
                <p>Password changes will be added after the submission-critical deployment work.</p>
            </aside>
        </div>
    </div>
</section>
