<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizerRepository;
use App\Services\OrganizerManagementService;
use App\Repositories\ManagementBookingRepository;
use DomainException;

final class AdminOrganizerController
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'disabled'];

    public function index(): void
    {
        Authorization::requireAdmin();
        $status = isset($_GET['status']) && in_array($_GET['status'], self::STATUSES, true)
            ? $_GET['status']
            : null;

        View::render('admin/organizers/index', [
            'pageTitle' => 'Organizer management',
            'organizers' => (new OrganizerRepository())->all($status),
            'selectedStatus' => $status,
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function approve(int $id): void
    {
        $this->review($id, 'approved');
    }

    public function show(int $id): void
    {
        Authorization::requireAdmin();
        $repository = new ManagementBookingRepository();
        $organizer = $repository->organizer($id);
        if ($organizer === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Organizer not found']);
            return;
        }
        View::render('admin/organizers/show', [
            'pageTitle' => $organizer['name'],
            'organizer' => $organizer,
            'metrics' => $repository->organizerMetrics($id),
            'events' => $repository->organizerEvents($id),
            'recentBookings' => $repository->forAdmin(['organizer_id' => $id, 'sort' => 'newest'], 1, 10),
        ]);
    }

    public function reject(int $id): void
    {
        $this->review($id, 'rejected');
    }

    private function review(int $id, string $decision): never
    {
        Authorization::requireAdmin();
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }

        try {
            $name = (new OrganizerManagementService())->review(
                $id,
                Auth::id() ?? 0,
                $decision,
                $_POST['reason'] ?? null
            );
            Session::flash('success', $name . ' was ' . $decision . '.');
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        Authorization::redirect('admin/organizers');
    }
}
