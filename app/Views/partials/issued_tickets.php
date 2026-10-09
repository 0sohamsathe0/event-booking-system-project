<?php if (in_array(($booking['status'] ?? ''), ['confirmed', 'partially_cancelled', 'customer_cancelled', 'event_cancelled'], true)): ?>
    <section class="issued-ticket-section" aria-labelledby="issued-ticket-heading">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">Entry credentials</span>
                <h2 id="issued-ticket-heading">Issued tickets</h2>
            </div>
            <span class="muted"><?= e(count($issuedTickets)) ?> issued &middot; <?= e(count(array_filter($issuedTickets, static fn (array $ticket): bool => $ticket['status'] === 'valid'))) ?> valid</span>
        </div>

        <?php if ($issuedTickets === []): ?>
            <div class="alert alert-warning">Tickets have not been issued for this older booking yet.</div>
        <?php else: ?>
            <div class="issued-ticket-grid">
                <?php foreach ($issuedTickets as $ticket): ?>
                    <article class="issued-ticket">
                        <div class="issued-ticket-topline">
                            <span>Admits one</span>
                            <span class="status-badge status-<?= e($ticket['status']) ?>"><?= e($ticket['status']) ?></span>
                        </div>
                        <div class="issued-ticket-main">
                            <div>
                                <small>Event</small>
                                <h3><?= e($booking['event_title']) ?></h3>
                            </div>
                            <div class="issued-ticket-seat">
                                <small>Seat</small>
                                <strong>S-<?= e(str_pad((string) $ticket['seat_number'], 3, '0', STR_PAD_LEFT)) ?></strong>
                            </div>
                        </div>
                        <dl class="issued-ticket-meta">
                            <div><dt>Type</dt><dd><?= e($ticket['ticket_type_name']) ?></dd></div>
                            <div><dt>Ticket code</dt><dd><code><?= e($ticket['ticket_code']) ?></code></dd></div>
                            <div><dt>Issued</dt><dd><?= e(local_datetime($ticket['issued_at'])) ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
