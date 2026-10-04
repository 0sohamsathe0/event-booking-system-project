<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\NotificationRepository;
use App\Services\CustomerProfileService;

final class CustomerProfileController
{
    public function show(): void
    {
        Authorization::requireRole('customer');
        $user = Auth::user() ?? [];

        View::render('customer/profile', [
            'pageTitle' => 'My profile',
            'user' => $user,
            'old' => Session::consumeFlash('profile_old', [
                'name' => $user['name'] ?? '',
                'phone' => $user['phone'] ?? '',
            ]),
            'errors' => Session::consumeFlash('profile_errors', []),
            'success' => Session::consumeFlash('success'),
            'unreadNotificationCount' => (new NotificationRepository())->unreadCountForUser(Auth::id() ?? 0),
        ]);
    }

    public function update(): never
    {
        Authorization::requireRole('customer');

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }

        $profile = new CustomerProfileService();
        [$errors, $data] = $profile->validate($_POST);
        if ($errors !== []) {
            Session::flash('profile_errors', $errors);
            Session::flash('profile_old', $data);
            Authorization::redirect('profile');
        }

        $profile->update(Auth::id() ?? 0, $data);
        Auth::refresh();
        Session::flash('success', 'Your profile was updated.');
        Authorization::redirect('profile');
    }
}
