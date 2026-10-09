<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">One hall. Memorable events.</span>
            <h1>Discover<br><span>unforgettable</span><br>events.</h1>
            <p class="hero-copy">
                Discover approved events, choose the right ticket, and complete
                your booking securely online.
            </p>
            <div class="hero-actions">
                <a class="button" href="#events">Browse events</a>
                <a class="button button-secondary" href="#hall">Explore the hall</a>
            </div>
        </div>

        <aside class="hero-panel" aria-label="Booking highlights">
            <span class="hero-index">EBS / 01</span>
            <div class="highlight">
                <strong>Secure checkout</strong>
                <span>Online payments powered by Razorpay</span>
            </div>
            <div class="highlight">
                <strong>Live availability</strong>
                <span>Inventory protected during every booking</span>
            </div>
            <div class="highlight">
                <strong>Simple access</strong>
                <span>Manage bookings from one customer account</span>
            </div>
        </aside>
    </div>
</section>

<section class="section" id="events">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Upcoming</span>
                <h2>Events worth looking forward to</h2>
            </div>
            <a class="section-index" href="<?= e(url('events')) ?>">01 / View all</a>
        </div>

        <?php if ($events === []): ?><div class="empty-state">
            <div class="empty-icon" aria-hidden="true">+</div>
            <h3>No published events yet</h3>
            <p>Approved events will appear here with ticket prices and live availability.</p>
        </div><?php else: ?><div class="public-event-grid public-event-grid-featured">
            <?php foreach (array_slice($events, 0, 3) as $event): ?><article class="public-event-card">
                <?php $posterUrl = poster_url($event['poster_path']); ?>
                <a class="event-image" href="<?= e(url('events/' . $event['id'])) ?>"><?php if ($posterUrl): ?><img src="<?= e($posterUrl) ?>" alt="<?= e($event['title']) ?> poster"><?php else: ?><span>No poster</span><?php endif; ?></a>
                <div class="public-event-copy"><span class="event-meta"><?= e(local_datetime($event['start_datetime'], 'd M Y')) ?> / <?= e($event['category_name']) ?></span><h3><a href="<?= e(url('events/' . $event['id'])) ?>"><?= e($event['title']) ?></a></h3><div class="event-card-footer"><strong>From <?= e(money($event['starting_price'])) ?></strong><span><?= e($event['available_quantity']) ?> available</span></div></div>
            </article><?php endforeach; ?>
        </div><?php endif; ?>
    </div>
</section>

<section class="section section-muted" id="hall">
    <div class="container hall-card">
        <div>
            <span class="eyebrow">Our venue</span>
            <h2>A single venue, carefully scheduled</h2>
        </div>
        <p class="editorial-copy">
            Every approved event is checked against the hall schedule. This prevents
            overlapping events and keeps capacity within the venue's safe limit.
        </p>
    </div>
</section>
