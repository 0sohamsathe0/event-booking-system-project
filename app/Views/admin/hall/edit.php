<section class="section dashboard-page">
    <div class="container form-page">
        <div class="page-heading">
            <div><span class="eyebrow">Administration</span><h1>Hall settings</h1><p class="muted">Configure the single venue used by every event.</p></div>
            <a class="button button-secondary" href="<?= e(url('admin/dashboard')) ?>">Dashboard</a>
        </div>

        <form class="panel form-stack" method="post" action="<?= e(url('admin/hall')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="form-group"><label for="name">Hall name</label><input id="name" name="name" maxlength="150" value="<?= e($hall['name'] ?? '') ?>" required><?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?></div>
            <div class="form-group"><label for="address">Address</label><textarea id="address" name="address" maxlength="255" rows="3" required><?= e($hall['address'] ?? '') ?></textarea><?php if (isset($errors['address'])): ?><p class="field-error"><?= e($errors['address']) ?></p><?php endif; ?></div>
            <div class="form-grid">
                <div class="form-group"><label for="city">City</label><input id="city" name="city" maxlength="100" value="<?= e($hall['city'] ?? '') ?>" required><?php if (isset($errors['city'])): ?><p class="field-error"><?= e($errors['city']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="maximum_capacity">Maximum capacity</label><input id="maximum_capacity" name="maximum_capacity" type="number" min="1" value="<?= e($hall['maximum_capacity'] ?? '') ?>" required><?php if (isset($errors['maximum_capacity'])): ?><p class="field-error"><?= e($errors['maximum_capacity']) ?></p><?php endif; ?></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label for="contact_phone">Contact phone <span class="muted">(optional)</span></label><input id="contact_phone" name="contact_phone" maxlength="20" value="<?= e($hall['contact_phone'] ?? '') ?>"><?php if (isset($errors['contact_phone'])): ?><p class="field-error"><?= e($errors['contact_phone']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="contact_email">Contact email <span class="muted">(optional)</span></label><input id="contact_email" name="contact_email" type="email" maxlength="254" value="<?= e($hall['contact_email'] ?? '') ?>"><?php if (isset($errors['contact_email'])): ?><p class="field-error"><?= e($errors['contact_email']) ?></p><?php endif; ?></div>
            </div>
            <div class="alert alert-warning">Maximum capacity cannot be reduced below an existing pending or approved event's capacity.</div>
            <button class="button button-full" type="submit">Save hall settings</button>
        </form>
    </div>
</section>
