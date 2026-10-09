<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CancellationRepository;
use App\Services\EventCancellationService;
use DomainException;
use Throwable;

final class AdminCancellationController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireAdmin();
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'pending';
        if (!in_array($status, ['any', 'pending', 'approved', 'rejected', 'withdrawn'], true)) {
            $status = 'pending';
        }
        $search = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
        $filters = ['status' => $status, 'search' => $search];
        $repository = new CancellationRepository();
        $total = $repository->countAdminRequests($filters);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1), $pageCount);
        View::render('admin/cancellations/index', [
            'pageTitle' => 'Event cancellation requests',
            'requests' => $repository->adminRequests($filters, $page, self::PER_PAGE),
            'selectedStatus' => $status,
            'search' => $search, 'total' => $total, 'page' => $page, 'pageCount' => $pageCount,
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function approve(int $id): never
    {
        $this->review($id, 'approved');
    }

    public function reject(int $id): never
    {
        $this->review($id, 'rejected');
    }

    private function review(int $id, string $decision): never
    {
        Authorization::requireAdmin();
        $this->verifyCsrf();
        try {
            $result = (new EventCancellationService())->review(
                $id, Auth::id() ?? 0, $decision, $_POST['review_note'] ?? null
            );
            $message = $decision === 'approved'
                ? 'Event cancelled. ' . $result['refunds_created'] . ' refund(s) queued; ' . $result['refunds_processed'] . ' attempted immediately.'
                : 'The event cancellation request was rejected.';
            Session::flash('success', $message);
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Logger::error('event_cancellation.review_failed', ['request_id' => $id, 'exception' => $exception]);
            Session::flash('error', 'The cancellation request could not be reviewed safely.');
        }
        Authorization::redirect('admin/cancellations');
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
