<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BookingRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\ManagementBookingRepository;
use App\Repositories\CancellationRepository;

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

        if (($user['role'] ?? null) === 'organizer' && ($user['account_status'] ?? null) === 'approved') {
            $organizerId = Auth::id() ?? 0;
            $bookings = new ManagementBookingRepository();
            View::render('organizer/dashboard', [
                'pageTitle' => 'Organizer dashboard',
                'user' => $user,
                'metrics' => $bookings->organizerMetrics($organizerId),
                'recentBookings' => $bookings->recentForOrganizer($organizerId),
                'refundMetrics' => (new CancellationRepository())->refundMetrics($organizerId),
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
