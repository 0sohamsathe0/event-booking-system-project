<?php if (($refunds ?? []) !== []): ?>
    <section class="panel refund-history" aria-labelledby="management-refund-history">
        <div class="panel-heading"><div><span class="eyebrow">Financial audit</span><h2 id="management-refund-history">Refund history</h2></div></div>
        <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Source</th><th>Tickets</th><th>Policy</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead><tbody>
            <?php foreach ($refunds as $refund): ?><tr><td><strong><?= e($refund['refund_reference']) ?></strong></td><td><?= e($refund['source']) ?></td><td><?= e($refund['ticket_count']) ?></td><td><?= e($refund['refund_percentage']) ?>%</td><td><?= e(money($refund['amount'])) ?></td><td><span class="status-badge status-<?= e($refund['status']) ?>"><?= e($refund['status']) ?></span></td><td><?= e(local_datetime($refund['requested_at'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
<?php endif; ?>
