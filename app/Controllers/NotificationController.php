<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\NotificationRepository;

final class NotificationController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireRole('customer');

        $userId = Auth::id() ?? 0;
        $repository = new NotificationRepository();
        $total = $repository->countForUser($userId);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->pageFromQuery(), $pageCount);

        View::render('customer/notifications', [
            'pageTitle' => 'Notifications',
            'notifications' => $repository->historyForUser($userId, $page, self::PER_PAGE),
            'unreadNotificationCount' => $repository->unreadCountForUser($userId),
            'page' => $page,
            'pageCount' => $pageCount,
            'totalNotificationCount' => $total,
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function markRead(int $id): void
    {
        Authorization::requireRole('customer');
        $this->verifyCsrf();

        (new NotificationRepository())->markReadForUser($id, Auth::id() ?? 0);
        Session::flash('success', 'Notification status updated.');
        Authorization::redirect($this->returnPath());
    }

    public function markAllRead(): void
    {
        Authorization::requireRole('customer');
        $this->verifyCsrf();

        (new NotificationRepository())->markAllReadForUser(Auth::id() ?? 0);
        Session::flash('success', 'All notifications marked as read.');
        Authorization::redirect($this->returnPath());
    }

    private function pageFromQuery(): int
    {
        $value = $_GET['page'] ?? '1';
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            return 1;
        }

        return min((int) $value, 100000);
    }

    private function returnPath(): string
    {
        $value = $_POST['page'] ?? '1';
        if (!is_string($value) || !ctype_digit($value) || (int) $value <= 1) {
            return 'notifications';
        }

        return 'notifications?page=' . min((int) $value, 100000);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }
    }
}
