<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BookingRepository;
use App\Repositories\NotificationRepository;
use App\Services\BookingService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class BookingController
{
    private const HISTORY_STATUSES = [
        'any',
        'pending_payment',
        'confirmed',
        'payment_failed',
        'expired',
        'customer_cancelled',
        'event_cancelled',
    ];
    private const HISTORY_SORTS = ['newest', 'oldest', 'event_soonest', 'amount_high', 'amount_low'];

    public function store(int $eventId): void
    {
        Authorization::requireRole('customer');
        $this->verifyCsrf();
        try {
            $result = (new BookingService())->reserve(Auth::id() ?? 0, $eventId, $_POST['tickets'] ?? []);
            if ($result['free']) {
                Session::flash('success', 'Your free booking is confirmed.');
                Authorization::redirect('bookings/' . $result['booking_id']);
            }
            Authorization::redirect('bookings/' . $result['booking_id'] . '/checkout');
        } catch (Throwable $exception) {
            $this->handleError($exception, 'The booking could not be started.');
            Authorization::redirect('events/' . $eventId);
        }
    }

    public function checkout(int $id): void
    {
        Authorization::requireRole('customer');
        $repository = new BookingRepository();
        $booking = $repository->findForCustomer($id, Auth::id() ?? 0);
        if ($booking === null) {
            $this->notFound();
            return;
        }
        if ($booking['status'] !== 'pending_payment') {
            Authorization::redirect('bookings/' . $id);
        }
        $gateway = (new BookingService())->razorpay();
        View::render('bookings/checkout', [
            'pageTitle' => 'Complete payment', 'booking' => $booking,
            'items' => $repository->items($id), 'razorpayKey' => $gateway->keyId(),
            'customer' => Auth::user(), 'error' => Session::consumeFlash('error'),
            'unreadNotificationCount' => $this->unreadNotificationCount(),
        ]);
    }

    public function confirm(int $id): void
    {
        Authorization::requireRole('customer');
        $this->verifyCsrf();
        try {
            (new BookingService())->confirm(
                $id, Auth::id() ?? 0,
                trim((string) ($_POST['razorpay_order_id'] ?? '')),
                trim((string) ($_POST['razorpay_payment_id'] ?? '')),
                trim((string) ($_POST['razorpay_signature'] ?? ''))
            );
            Session::flash('success', 'Payment verified. Your booking is confirmed.');
            Authorization::redirect('bookings/' . $id);
        } catch (Throwable $exception) {
            $this->handleError($exception, 'Payment could not be verified.');
            Authorization::redirect('bookings/' . $id . '/checkout');
        }
    }

    public function paymentFailed(int $id): void
    {
        Authorization::requireRole('customer');
        $this->verifyCsrf();
        try {
            (new BookingService())->failAndRelease(
                $id, Auth::id() ?? 0, trim((string) ($_POST['reason'] ?? 'Payment was not completed.'))
            );
            Session::flash('error', 'Payment was not completed. Reserved tickets were released.');
        } catch (Throwable $exception) {
            $this->handleError($exception, 'The payment status could not be updated.');
        }
        Authorization::redirect('bookings/' . $id);
    }

    public function index(): void
    {
        Authorization::requireRole('customer');
        [$filters, $filterErrors] = $this->historyFilters();
        $repository = new BookingRepository();
        $bookings = $repository->forCustomer(Auth::id() ?? 0, $filters);

        View::render('bookings/index', [
            'pageTitle' => 'My bookings',
            'bookings' => $bookings,
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'hasActiveFilters' => $this->hasActiveHistoryFilters($filters),
            'filteredCount' => count($bookings),
            'totalCount' => $repository->countForCustomer(Auth::id() ?? 0),
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
            'unreadNotificationCount' => $this->unreadNotificationCount(),
        ]);
    }

    public function show(int $id): void
    {
        Authorization::requireRole('customer');
        $repository = new BookingRepository();
        $booking = $repository->findForCustomer($id, Auth::id() ?? 0);
        if ($booking === null) {
            $this->notFound();
            return;
        }
        View::render('bookings/show', [
            'pageTitle' => 'Booking ' . $booking['booking_reference'],
            'booking' => $booking, 'items' => $repository->items($id),
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
            'unreadNotificationCount' => $this->unreadNotificationCount(),
        ]);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }
    }

    private function handleError(Throwable $exception, string $fallback): void
    {
        if ($exception instanceof DomainException) {
            Session::flash('error', $exception->getMessage());
        } else {
            error_log($exception->__toString());
            Session::flash('error', $fallback);
        }
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Booking not found']);
    }

    private function unreadNotificationCount(): int
    {
        return (new NotificationRepository())->unreadCountForUser(Auth::id() ?? 0);
    }

    private function historyFilters(): array
    {
        $errors = [];
        $search = trim($this->queryString('q'));
        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
            $errors[] = 'Search text was limited to 100 characters.';
        }

        $status = $this->queryString('status', 'any');
        if (!in_array($status, self::HISTORY_STATUSES, true)) {
            $status = 'any';
            $errors[] = 'The booking status filter was reset.';
        }

        $dateFrom = trim($this->queryString('from'));
        $dateTo = trim($this->queryString('to'));
        $fromDate = $this->parseLocalDate($dateFrom);
        $toDate = $this->parseLocalDate($dateTo);

        if ($dateFrom !== '' && $fromDate === null) {
            $dateFrom = '';
            $errors[] = 'Enter a valid booking start date.';
        }
        if ($dateTo !== '' && $toDate === null) {
            $dateTo = '';
            $errors[] = 'Enter a valid booking end date.';
        }
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $dateFrom = '';
            $dateTo = '';
            $fromDate = null;
            $toDate = null;
            $errors[] = 'The booking start date must be on or before the end date.';
        }

        $sort = $this->queryString('sort', 'newest');
        if (!in_array($sort, self::HISTORY_SORTS, true)) {
            $sort = 'newest';
            $errors[] = 'The booking sort order was reset.';
        }

        $utc = new DateTimeZone('UTC');
        return [[
            'search' => $search,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_utc' => $fromDate?->setTimezone($utc)->format('Y-m-d H:i:s'),
            'date_to_utc' => $toDate?->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
            'sort' => $sort,
        ], $errors];
    }

    private function parseLocalDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function hasActiveHistoryFilters(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['status'] !== 'any'
            || $filters['date_from'] !== ''
            || $filters['date_to'] !== '';
    }

    private function queryString(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}
