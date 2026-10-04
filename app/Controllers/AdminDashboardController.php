<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminDashboardRepository;
use App\Repositories\OrganizerRepository;

final class AdminDashboardController
{
    public function index(): void
    {
        Authorization::requireAdmin();
        View::render('admin/dashboard', [
            'pageTitle' => 'Admin dashboard',
            'counts' => (new AdminDashboardRepository())->counts(),
            'pendingOrganizers' => array_slice((new OrganizerRepository())->all('pending'), 0, 5),
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }
}

