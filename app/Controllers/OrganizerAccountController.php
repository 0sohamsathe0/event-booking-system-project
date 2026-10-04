<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\OrganizerManagementService;
use DomainException;

final class OrganizerAccountController
{
    public function resubmit(): never
    {
        Authorization::requireRole('organizer');

        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }

        try {
            (new OrganizerManagementService())->resubmit(
                Auth::id() ?? 0,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['phone'] ?? '')
            );
            Auth::refresh();
            Session::flash('success', 'Your organizer application was resubmitted for review.');
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        Authorization::redirect('account');
    }
}

