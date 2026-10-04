<?php $isOrganizer = $registrationRole === 'organizer'; ?>
<section class="auth-section">
    <div class="auth-card auth-card-wide">
        <div class="auth-heading">
            <span class="eyebrow"><?= $isOrganizer ? 'Organizer application' : 'Customer registration' ?></span>
            <h1><?= $isOrganizer ? 'Become an event organizer' : 'Create your account' ?></h1>
            <p><?= $isOrganizer
                ? 'Your account will require hall-manager approval before you can submit events.'
                : 'Register to book tickets and manage your booking history.' ?></p>
        </div>

        <form method="post" action="<?= e($isOrganizer ? url('organizer/register') : url('register')) ?>"
              class="form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" maxlength="100" autocomplete="name"
                           value="<?= e($old['name'] ?? '') ?>" required>
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel"
                           value="<?= e($old['phone'] ?? '') ?>" required>
                    <?php if (isset($errors['phone'])): ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email"
                       value="<?= e($old['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           minlength="8" autocomplete="new-password" required>
                    <?php if (isset($errors['password_confirmation'])): ?><p class="field-error"><?= e($errors['password_confirmation']) ?></p><?php endif; ?>
                </div>
            </div>

            <button class="button button-full" type="submit">
                <?= $isOrganizer ? 'Submit organizer application' : 'Create customer account' ?>
            </button>
        </form>

        <div class="auth-links">
            <a href="<?= e(url('login')) ?>">Already registered? Log in</a>
            <a href="<?= e($isOrganizer ? url('register') : url('organizer/register')) ?>">
                <?= $isOrganizer ? 'Register as customer' : 'Apply as organizer' ?>
            </a>
        </div>
    </div>
</section>

