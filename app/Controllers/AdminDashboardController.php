<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminDashboardRepository;
use App\Repositories\OrganizerRepository;
use App\Repositories\ManagementBookingRepository;
use App\Repositories\CancellationRepository;

final class AdminDashboardController
{
    public function index(): void
    {
        Authorization::requireAdmin();
        $bookings = new ManagementBookingRepository();
        View::render('admin/dashboard', [
            'pageTitle' => 'Admin dashboard',
            'counts' => (new AdminDashboardRepository())->counts(),
            'pendingOrganizers' => array_slice((new OrganizerRepository())->all('pending'), 0, 5),
            'bookingMetrics' => $bookings->adminMetrics(),
            'recentBookings' => $bookings->recentForAdmin(),
            'refundMetrics' => (new CancellationRepository())->refundMetrics(),
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }
}
