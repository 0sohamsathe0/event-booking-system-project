<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('organizer/events/' . $event['id']) : url('organizer/events');
$value = static fn(string $key, mixed $default = ''): string => e($old[$key] ?? $default);
?>
<section class="section dashboard-page">
    <div class="container form-page">
        <div class="page-heading">
            <div><span class="eyebrow">Organizer workspace</span><h1><?= $isEdit ? 'Edit event' : 'Create an event' ?></h1></div>
            <a class="button button-secondary" href="<?= e(url('organizer/events')) ?>">Back to events</a>
        </div>
        <?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php endif; ?>
        <?php if ($hall === null): ?><div class="alert alert-error">No active hall is configured. Ask the administrator to add the hall before creating events.</div><?php endif; ?>
        <?php if ($isEdit && $event['status'] === 'approved'): ?><div class="alert alert-warning">Editing any approved event returns it to Pending and requires admin approval again.</div><?php endif; ?>

        <form class="panel form-stack" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <?php if ($hall): ?><input type="hidden" name="hall_id" value="<?= e($hall['id']) ?>"><?php endif; ?>

            <div class="form-group"><label for="title">Event title</label><input id="title" name="title" maxlength="180" value="<?= $value('title') ?>" required><?php if (isset($errors['title'])): ?><p class="field-error"><?= e($errors['title']) ?></p><?php endif; ?></div>
            <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" maxlength="5000" rows="7" required><?= $value('description') ?></textarea><small class="muted">20–5,000 characters</small><?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?></div>

            <div class="form-grid">
                <div class="form-group"><label for="category_id">Category</label><select id="category_id" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>" <?= (string)($old['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="event_capacity">Event capacity</label><input id="event_capacity" name="event_capacity" type="number" min="1" max="<?= e($hall['maximum_capacity'] ?? '') ?>" value="<?= $value('event_capacity') ?>" required><small class="muted"><?= $hall ? 'Hall maximum: ' . e($hall['maximum_capacity']) : 'Hall not configured' ?></small><?php if (isset($errors['event_capacity'])): ?><p class="field-error"><?= e($errors['event_capacity']) ?></p><?php endif; ?></div>
            </div>

            <fieldset><legend>Event schedule</legend><div class="form-grid">
                <div class="form-group"><label for="start_datetime">Starts</label><input id="start_datetime" name="start_datetime" type="datetime-local" value="<?= $value('start_datetime') ?>" required><?php if (isset($errors['start_datetime'])): ?><p class="field-error"><?= e($errors['start_datetime']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="end_datetime">Ends</label><input id="end_datetime" name="end_datetime" type="datetime-local" value="<?= $value('end_datetime') ?>" required><?php if (isset($errors['end_datetime'])): ?><p class="field-error"><?= e($errors['end_datetime']) ?></p><?php endif; ?></div>
            </div></fieldset>

            <fieldset><legend>Ticket sale window</legend><div class="form-grid">
                <div class="form-group"><label for="sale_start_datetime">Sales start</label><input id="sale_start_datetime" name="sale_start_datetime" type="datetime-local" value="<?= $value('sale_start_datetime') ?>" required><?php if (isset($errors['sale_start_datetime'])): ?><p class="field-error"><?= e($errors['sale_start_datetime']) ?></p><?php endif; ?></div>
                <div class="form-group"><label for="sale_end_datetime">Sales end</label><input id="sale_end_datetime" name="sale_end_datetime" type="datetime-local" value="<?= $value('sale_end_datetime') ?>" required><?php if (isset($errors['sale_end_datetime'])): ?><p class="field-error"><?= e($errors['sale_end_datetime']) ?></p><?php endif; ?></div>
            </div></fieldset>

            <div class="form-group"><label for="poster">Event poster</label><input id="poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp"><small class="muted">Optional JPEG, PNG, or WebP; maximum 5 MB.</small><?php if (isset($errors['poster'])): ?><p class="field-error"><?= e($errors['poster']) ?></p><?php endif; ?></div>
            <?php if ($isEdit && $event['poster_path']): ?><div class="current-poster"><img src="<?= e(url($event['poster_path'])) ?>" alt="Current poster"><label><input type="checkbox" name="remove_poster" value="1"> Remove current poster</label></div><?php endif; ?>

            <button class="button button-full" type="submit" <?= $hall === null ? 'disabled' : '' ?>><?= $isEdit ? 'Save and submit for review' : 'Submit event for review' ?></button>
        </form>
    </div>
</section>

