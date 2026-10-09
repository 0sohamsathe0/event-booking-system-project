<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CancellationRepository;
use App\Services\RefundService;

final class AdminRefundController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireAdmin();
        $filters = $this->filters();
        $repository = new CancellationRepository();
        $total = $repository->countAdminRefunds($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1), $pages);
        View::render('admin/refunds/index', [
            'pageTitle' => 'Refund management',
            'refunds' => $repository->adminRefunds($filters, $page, self::PER_PAGE),
            'filters' => $filters, 'total' => $total, 'page' => $page, 'pageCount' => $pages,
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
        ]);
    }

    public function show(int $id): void
    {
        Authorization::requireAdmin();
        $repository = new CancellationRepository();
        $refund = $repository->adminRefund($id);
        if ($refund === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Refund not found']);
            return;
        }
        View::render('admin/refunds/show', [
            'pageTitle' => 'Refund ' . $refund['refund_reference'],
            'refund' => $refund,
            'tickets' => $repository->cancellationTicketsForRefund($id),
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
        ]);
    }

    public function process(): never
    {
        Authorization::requireAdmin();
        $this->verifyCsrf();
        $results = (new RefundService())->processPendingBatch(10);
        Session::flash('success', count($results) . ' queued refund(s) were attempted.');
        Authorization::redirect('admin/refunds');
    }

    public function retry(int $id): never
    {
        Authorization::requireAdmin();
        $this->verifyCsrf();
        $status = (new RefundService())->process($id, true);
        Session::flash($status === 'failed' ? 'error' : 'success', 'Refund status: ' . str_replace('_', ' ', $status) . '.');
        Authorization::redirect('admin/refunds/' . $id);
    }

    private function filters(): array
    {
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'any';
        $source = is_string($_GET['source'] ?? null) ? $_GET['source'] : 'any';
        return [
            'search' => mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100),
            'status' => in_array($status, ['any', 'pending', 'processing', 'processed', 'failed'], true) ? $status : 'any',
            'source' => in_array($source, ['any', 'customer', 'event'], true) ? $source : 'any',
        ];
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
