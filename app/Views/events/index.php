<section class="section event-catalog"><div class="container">
    <div class="page-heading"><div><span class="eyebrow">Live programme</span><h1>Upcoming events</h1><p class="muted">Search the programme and find your next experience.</p></div></div>

    <form class="catalog-filters" method="get" action="<?= e(url('events')) ?>" role="search">
        <div class="catalog-search-field">
            <label for="event-search">Search events</label>
            <input id="event-search" type="search" name="q" value="<?= e($filters['search']) ?>" maxlength="100" placeholder="Title, description, or category">
        </div>
        <div>
            <label for="event-category">Category</label>
            <select id="event-category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['id']) ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="event-from">From</label>
            <input id="event-from" type="date" name="from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div>
            <label for="event-to">To</label>
            <input id="event-to" type="date" name="to" value="<?= e($filters['date_to']) ?>">
        </div>
        <div>
            <label for="event-availability">Availability</label>
            <select id="event-availability" name="availability">
                <option value="any" <?= $filters['availability'] === 'any' ? 'selected' : '' ?>>Any availability</option>
                <option value="available" <?= $filters['availability'] === 'available' ? 'selected' : '' ?>>Tickets available</option>
                <option value="sold_out" <?= $filters['availability'] === 'sold_out' ? 'selected' : '' ?>>Sold out</option>
            </select>
        </div>
        <div>
            <label for="event-sort">Sort by</label>
            <select id="event-sort" name="sort">
                <option value="soonest" <?= $filters['sort'] === 'soonest' ? 'selected' : '' ?>>Soonest first</option>
                <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>Latest first</option>
                <option value="price_low" <?= $filters['sort'] === 'price_low' ? 'selected' : '' ?>>Lowest price</option>
                <option value="price_high" <?= $filters['sort'] === 'price_high' ? 'selected' : '' ?>>Highest price</option>
                <option value="availability" <?= $filters['sort'] === 'availability' ? 'selected' : '' ?>>Most availability</option>
            </select>
        </div>
        <div class="catalog-filter-actions">
            <button class="button" type="submit">Show events</button>
            <?php if ($hasActiveFilters || $filters['sort'] !== 'soonest'): ?><a class="button button-secondary" href="<?= e(url('events')) ?>">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if ($filterErrors !== []): ?>
        <div class="alert alert-warning" role="status"><?= e(implode(' ', $filterErrors)) ?></div>
    <?php endif; ?>

    <div class="catalog-result-summary" aria-live="polite">
        <span><?= e($resultCount) ?> <?= $resultCount === 1 ? 'event' : 'events' ?></span>
        <?php if ($hasActiveFilters): ?><span>Matching your filters</span><?php else: ?><span>Approved and upcoming</span><?php endif; ?>
    </div>

    <?php if ($events === []): ?>
        <?php if ($hasActiveFilters): ?>
            <div class="empty-state"><div class="empty-icon" aria-hidden="true">&times;</div><h2>No matching events</h2><p>Try a broader date range, another category, or clear the filters.</p><a class="button button-secondary" href="<?= e(url('events')) ?>">Clear filters</a></div>
        <?php else: ?>
            <div class="empty-state"><div class="empty-icon" aria-hidden="true">+</div><h2>No upcoming events</h2><p>New approved events will appear here.</p></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="public-event-grid">
        <?php foreach ($events as $event): ?>
            <article class="public-event-card">
                <a class="event-image" href="<?= e(url('events/' . $event['id'])) ?>">
                    <?php $posterUrl = poster_url($event['poster_path']); if ($posterUrl): ?><img src="<?= e($posterUrl) ?>" alt="<?= e($event['title']) ?> poster"><?php else: ?><span>No poster</span><?php endif; ?>
                </a>
                <div class="public-event-copy">
                    <span class="event-meta"><?= e(local_datetime($event['start_datetime'], 'd M Y')) ?> / <?= e($event['category_name']) ?></span>
                    <h2><a href="<?= e(url('events/' . $event['id'])) ?>"><?= e($event['title']) ?></a></h2>
                    <p><?= e($event['hall_name']) ?>, <?= e($event['city']) ?></p>
                    <div class="event-card-footer"><strong>From <?= e(money($event['starting_price'])) ?></strong><span><?= (int) $event['available_quantity'] > 0 ? e($event['available_quantity']) . ' available' : 'Sold out' ?></span></div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></section>
