<section class="section dashboard-page"><div class="container">
    <div class="page-heading"><div><span class="eyebrow">Organizer controls</span><h1>Event cancellations</h1><p class="muted">Requests require platform-admin approval before an event or customer tickets are changed.</p></div></div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <section class="panel"><div class="panel-heading"><div><span class="eyebrow">Future approved events</span><h2>Request cancellation</h2></div></div>
        <?php if ($events === []): ?><p class="muted">No event is currently eligible for a new cancellation request.</p><?php else: ?>
            <div class="cancellation-request-grid">
                <?php foreach ($events as $event): ?><form class="form-stack cancellation-request" method="post" action="<?= e(url('organizer/events/' . $event['id'] . '/cancellation-requests')) ?>" data-confirm="Submit this event cancellation request for admin review?">
                    <?= csrf_field() ?><div><strong><?= e($event['title']) ?></strong><small><?= e(local_datetime($event['start_datetime'])) ?></small></div>
                    <label for="reason-<?= e($event['id']) ?>">Reason <span class="muted">(optional)</span></label><textarea id="reason-<?= e($event['id']) ?>" name="reason" maxlength="1000" rows="3"></textarea>
                    <button class="button button-danger button-compact" type="submit">Request cancellation</button>
                </form><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel"><div class="panel-heading"><div><span class="eyebrow">Audit trail</span><h2>Previous requests</h2></div></div>
        <?php if ($requests === []): ?><p class="muted">No cancellation requests have been submitted.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Event</th><th>Reason</th><th>Status</th><th>Submitted</th><th>Review</th><th></th></tr></thead><tbody>
            <?php foreach ($requests as $request): ?><tr><td><strong><?= e($request['title']) ?></strong><small><?= e(local_datetime($request['start_datetime'])) ?></small></td><td><?= e($request['reason']) ?></td><td><span class="status-badge status-<?= e($request['status']) ?>"><?= e($request['status']) ?></span></td><td><?= e(local_datetime($request['created_at'])) ?></td><td><?= e($request['review_note'] ?: '—') ?></td><td><?php if ($request['status'] === 'pending'): ?><form method="post" action="<?= e(url('organizer/cancellation-requests/' . $request['id'] . '/withdraw')) ?>" data-confirm="Withdraw this pending request?"><?= csrf_field() ?><button class="button button-secondary button-compact" type="submit">Withdraw</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div></section>
