<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminEventRepository;
use App\Services\EventApprovalService;
use DomainException;

final class AdminEventController
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public function index(): void
    {
        Authorization::requireAdmin();
        $status = isset($_GET['status']) && in_array($_GET['status'], self::STATUSES, true) ? $_GET['status'] : null;
        View::render('admin/events/index', [
            'pageTitle' => 'Event approvals', 'events' => (new AdminEventRepository())->all($status),
            'selectedStatus' => $status, 'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function show(int $id): void
    {
        Authorization::requireAdmin();
        $event = (new AdminEventRepository())->find($id);
        if ($event === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Event not found']);
            return;
        }
        View::render('admin/events/show', [
            'pageTitle' => 'Review event', 'event' => $event,
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
        ]);
    }

    public function approve(int $id): void
    {
        $this->review($id, 'approved');
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
            $title = (new EventApprovalService())->review($id, Auth::id() ?? 0, $decision, $_POST['reason'] ?? null);
            Session::flash('success', $title . ' was ' . $decision . '.');
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        Authorization::redirect('admin/events/' . $id);
    }
}

