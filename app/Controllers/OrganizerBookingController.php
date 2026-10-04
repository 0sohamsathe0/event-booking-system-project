<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ParsesManagementFilters;
use App\Core\Auth;
use App\Core\Authorization;
use App\Core\View;
use App\Repositories\ManagementBookingRepository;
use App\Repositories\EventRepository;

final class OrganizerBookingController
{
    use ParsesManagementFilters;

    private const PER_PAGE = 20;

    public function index(): void
    {
        Authorization::requireApprovedOrganizer();
        $organizerId = Auth::id() ?? 0;
        [$filters, $filterErrors] = $this->managementFilters(false);
        $repository = new ManagementBookingRepository();
        $total = $repository->countForOrganizer($organizerId, $filters);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->pageFromQuery(), $pageCount);
        View::render('organizer/bookings/index', [
            'pageTitle' => 'Event bookings',
            'bookings' => $repository->forOrganizer($organizerId, $filters, $page, self::PER_PAGE),
            'events' => $repository->organizerEvents($organizerId),
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'total' => $total,
            'page' => $page,
            'pageCount' => $pageCount,
        ]);
    }

    public function forEvent(int $eventId): void
    {
        Authorization::requireApprovedOrganizer();
        if ((new EventRepository())->findOwned($eventId, Auth::id() ?? 0) === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Event not found']);
            return;
        }
        $_GET['event'] = (string) $eventId;
        $this->index();
    }

    public function show(int $id): void
    {
        Authorization::requireApprovedOrganizer();
        $repository = new ManagementBookingRepository();
        $booking = $repository->findForOrganizer($id, Auth::id() ?? 0);
        if ($booking === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Booking not found']);
            return;
        }
        View::render('organizer/bookings/show', [
            'pageTitle' => 'Booking ' . $booking['booking_reference'],
            'booking' => $booking,
            'items' => $repository->items($id),
        ]);
    }
}
