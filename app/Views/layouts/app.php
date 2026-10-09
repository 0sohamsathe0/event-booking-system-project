<?php

use App\Core\Csrf;
use App\Core\Auth;

$appName = $appName ?? 'Event Booking System';
$pageTitle = $pageTitle ?? $appName;
$currentUser = Auth::user();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$role = $currentUser['role'] ?? 'guest';
$roleLabel = match ($role) {
    'admin' => 'Platform Admin',
    'organizer' => 'Organizer',
    'customer' => 'Attendee',
    default => 'Guest',
};
$isAdminArea = $currentUser && str_contains($requestPath, '/admin');
$isOrganizerArea = $currentUser && str_contains($requestPath, '/organizer');
$isAccountArea = $currentUser && str_contains($requestPath, '/account');
$isCustomerArea = $currentUser && $role === 'customer' && (
    $isAccountArea
    || str_contains($requestPath, '/bookings')
    || str_contains($requestPath, '/notifications')
    || str_contains($requestPath, '/profile')
);
$isDashboard = $isAdminArea || $isOrganizerArea || $isAccountArea || $isCustomerArea;
$bodyClasses = ['role-' . $role];
if ($isDashboard) {
    $bodyClasses[] = 'has-dashboard';
    $bodyClasses[] = 'dashboard--' . ($role === 'customer' ? 'user' : $role);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>">
    <div class="global-loader" data-global-loader hidden aria-hidden="true">
        <div class="global-loader-panel" role="status" aria-live="polite" aria-atomic="true">
            <span class="global-loader-mark" aria-hidden="true">E</span>
            <span class="global-loader-content">
                <span class="global-loader-kicker">Event Booking</span>
                <strong class="global-loader-label" data-global-loader-label>Loading</strong>
                <span class="global-loader-track" aria-hidden="true"><span></span></span>
                <small>Securely processing your request</small>
            </span>
        </div>
    </div>

    <header class="site-header">
        <div class="container navigation">
            <a class="brand" href="<?= e(url('')) ?>">
                <span class="brand-mark" aria-hidden="true">E</span>
                <span>Event Booking</span>
            </a>

            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
                <span class="sr-only">Toggle navigation</span><span></span><span></span>
            </button>
            <nav id="primary-navigation" aria-label="Primary navigation">
                <a href="<?= e(url('')) ?>">Home</a>
                <a href="<?= e(url('events')) ?>">Events</a>
                <a href="<?= e(url('')) ?>#hall">About Hall</a>
                <?php if ($currentUser): ?>
                    <?php if ($currentUser['role'] === 'admin'): ?><a href="<?= e(url('admin/dashboard')) ?>">Admin</a><a href="<?= e(url('admin/events')) ?>">Events</a><a href="<?= e(url('admin/hall')) ?>">Hall</a><?php endif; ?>
                    <?php if ($currentUser['role'] === 'organizer' && $currentUser['account_status'] === 'approved'): ?><a href="<?= e(url('organizer/events')) ?>">My events</a><?php endif; ?>
                    <?php if ($currentUser['role'] === 'customer'): ?><a href="<?= e(url('bookings')) ?>">My bookings</a><?php endif; ?>
                    <a class="button button-small" href="<?= e(url('account')) ?>">My account</a>
                <?php else: ?>
                    <a href="<?= e(url('login')) ?>">Login</a>
                    <a class="button button-small" href="<?= e(url('register')) ?>">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <?php if ($isDashboard): ?>
        <div class="dashboard-frame">
            <button class="dashboard-backdrop" type="button" aria-label="Close dashboard navigation"></button>
            <aside class="dashboard-sidebar" id="dashboard-navigation">
                <div class="sidebar-identity">
                    <span class="sidebar-kicker">Signed in as</span>
                    <strong><?= e($currentUser['name']) ?></strong>
                    <span class="role-label"><?= e($roleLabel) ?></span>
                </div>
                <nav class="sidebar-nav" aria-label="Dashboard navigation">
                    <?php if ($role === 'admin'): ?>
                        <a class="<?= str_contains($requestPath, '/admin/dashboard') ? 'active' : '' ?>" href="<?= e(url('admin/dashboard')) ?>">Overview</a>
                        <a class="<?= str_contains($requestPath, '/admin/bookings') ? 'active' : '' ?>" href="<?= e(url('admin/bookings')) ?>">Bookings</a>
                        <a class="<?= str_contains($requestPath, '/admin/refunds') ? 'active' : '' ?>" href="<?= e(url('admin/refunds')) ?>">Refunds</a>
                        <a class="<?= str_contains($requestPath, '/admin/cancellations') ? 'active' : '' ?>" href="<?= e(url('admin/cancellations')) ?>">Cancellations</a>
                        <a class="<?= str_contains($requestPath, '/admin/customers') ? 'active' : '' ?>" href="<?= e(url('admin/customers')) ?>">Customers</a>
                        <a class="<?= str_contains($requestPath, '/admin/organizers') ? 'active' : '' ?>" href="<?= e(url('admin/organizers')) ?>">Organizers</a>
                        <a class="<?= str_contains($requestPath, '/admin/events') ? 'active' : '' ?>" href="<?= e(url('admin/events')) ?>">Events</a>
                        <a class="<?= str_contains($requestPath, '/admin/hall') ? 'active' : '' ?>" href="<?= e(url('admin/hall')) ?>">Hall settings</a>
                        <form class="sidebar-logout" method="post" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit">Log out</button>
                        </form>
                    <?php elseif ($role === 'organizer' && $currentUser['account_status'] === 'approved'): ?>
                        <a class="<?= $isAccountArea ? 'active' : '' ?>" href="<?= e(url('account')) ?>">Overview</a>
                        <a class="<?= str_contains($requestPath, '/organizer/events') ? 'active' : '' ?>" href="<?= e(url('organizer/events')) ?>">My events</a>
                        <a class="<?= str_contains($requestPath, '/organizer/bookings') ? 'active' : '' ?>" href="<?= e(url('organizer/bookings')) ?>">Bookings</a>
                        <a class="<?= str_contains($requestPath, '/organizer/cancellations') ? 'active' : '' ?>" href="<?= e(url('organizer/cancellations')) ?>">Cancellations</a>
                        <form class="sidebar-logout" method="post" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit">Log out</button>
                        </form>
                    <?php elseif ($role === 'customer'): ?>
                        <a class="<?= $isAccountArea ? 'active' : '' ?>" href="<?= e(url('account')) ?>">Dashboard</a>
                        <a href="<?= e(url('events')) ?>">Browse events</a>
                        <a class="<?= str_contains($requestPath, '/bookings') ? 'active' : '' ?>" href="<?= e(url('bookings')) ?>">My bookings</a>
                        <a class="<?= str_contains($requestPath, '/notifications') ? 'active' : '' ?>" href="<?= e(url('notifications')) ?>">
                            Notifications
                            <?php if (($unreadNotificationCount ?? 0) > 0): ?>
                                <span class="sidebar-count" aria-label="<?= e($unreadNotificationCount) ?> unread notifications"><?= e($unreadNotificationCount) ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="<?= str_contains($requestPath, '/profile') ? 'active' : '' ?>" href="<?= e(url('profile')) ?>">Profile</a>
                        <form class="sidebar-logout" method="post" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit">Log out</button>
                        </form>
                    <?php else: ?>
                        <a class="<?= $isAccountArea ? 'active' : '' ?>" href="<?= e(url('account')) ?>">My account</a>
                        <a class="<?= str_contains($requestPath, '/bookings') ? 'active' : '' ?>" href="<?= e(url('bookings')) ?>">My bookings</a>
                    <?php endif; ?>
                </nav>
                <a class="sidebar-public-link" href="<?= e(url('')) ?>">View public website &rarr;</a>
            </aside>
            <div class="dashboard-content">
                <button class="dashboard-menu-button" type="button" aria-expanded="false" aria-controls="dashboard-navigation">Menu</button>
                <main><?= $content ?></main>
            </div>
        </div>
    <?php else: ?>
        <main><?= $content ?></main>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container footer-content">
            <p>&copy; <?= date('Y') ?> Event Booking System</p>
            <p>Secure event discovery and ticket booking for one trusted venue.</p>
        </div>
    </footer>

    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
