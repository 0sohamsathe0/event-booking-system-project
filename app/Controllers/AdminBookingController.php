<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ParsesManagementFilters;
use App\Core\Authorization;
use App\Core\View;
use App\Repositories\ManagementBookingRepository;
use App\Repositories\IssuedTicketRepository;
use App\Repositories\CancellationRepository;

final class AdminBookingController
{
    use ParsesManagementFilters;

    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireAdmin();
        [$filters, $filterErrors] = $this->managementFilters(true);
        $repository = new ManagementBookingRepository();
        $total = $repository->countForAdmin($filters);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->pageFromQuery(), $pageCount);
        View::render('admin/bookings/index', [
            'pageTitle' => 'Booking management',
            'bookings' => $repository->forAdmin($filters, $page, self::PER_PAGE),
            'events' => $repository->adminEvents(),
            'organizers' => $repository->adminOrganizers(),
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
        $booking = $repository->findForAdmin($id);
        if ($booking === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Booking not found']);
            return;
        }
        View::render('admin/bookings/show', [
            'pageTitle' => 'Booking ' . $booking['booking_reference'],
            'booking' => $booking,
            'items' => $repository->items($id),
            'issuedTickets' => (new IssuedTicketRepository())->forBooking($id),
            'refunds' => (new CancellationRepository())->refundsForBooking($id),
        ]);
    }
}
