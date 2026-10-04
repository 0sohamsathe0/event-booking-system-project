<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ParsesManagementFilters;
use App\Core\Authorization;
use App\Core\View;
use App\Repositories\ManagementBookingRepository;

final class AdminCustomerController
{
    use ParsesManagementFilters;

    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireAdmin();
        [$filters, $filterErrors] = $this->customerFilters();
        $repository = new ManagementBookingRepository();
        $total = $repository->countCustomers($filters);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->pageFromQuery(), $pageCount);
        View::render('admin/customers/index', [
            'pageTitle' => 'Customer management',
            'customers' => $repository->customers($filters, $page, self::PER_PAGE),
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'total' => $total,
            'page' => $page,
            'pageCount' => $pageCount,
        ]);
    }

    public function show(int $id): void
    {
        Authorization::requireAdmin();
        $repository = new ManagementBookingRepository();
        $customer = $repository->customer($id);
        if ($customer === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Customer not found']);
            return;
        }
        $filters = ['customer_id' => $id, 'sort' => 'newest'];
        View::render('admin/customers/show', [
            'pageTitle' => $customer['name'],
            'customer' => $customer,
            'bookings' => $repository->forAdmin($filters, 1, 10),
        ]);
    }
}
