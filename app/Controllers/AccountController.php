<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BookingRepository;
use App\Repositories\NotificationRepository;

final class AccountController
{
    public function index(): void
    {
        Authorization::requireAuthentication();

        $user = Auth::user();
        if (($user['role'] ?? null) === 'customer') {
            $customerId = Auth::id() ?? 0;
            $bookings = new BookingRepository();
            $notifications = new NotificationRepository();

            View::render('customer/dashboard', [
                'pageTitle' => 'Dashboard',
                'user' => Auth::user(),
                'upcomingBookings' => $bookings->upcomingConfirmedForCustomer($customerId),
                'recentBookings' => $bookings->recentForCustomer($customerId),
                'pendingBookings' => $bookings->payablePendingForCustomer($customerId),
                'unreadNotificationCount' => $notifications->unreadCountForUser($customerId),
                'recentNotifications' => $notifications->recentForUser($customerId),
                'success' => Session::consumeFlash('success'),
                'error' => Session::consumeFlash('error'),
            ]);
            return;
        }

        View::render('account/index', [
            'pageTitle' => 'My account',
            'user' => $user,
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }
}
