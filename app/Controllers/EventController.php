<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BookingRepository;
use App\Repositories\EventCatalogRepository;
use DateTimeImmutable;
use DateTimeZone;

final class EventController
{
    private const AVAILABILITY_FILTERS = ['any', 'available', 'sold_out'];
    private const SORT_OPTIONS = ['soonest', 'latest', 'price_low', 'price_high', 'availability'];

    public function index(): void
    {
        $categories = (new EventCatalogRepository())->activeCategories();
        [$filters, $filterErrors] = $this->catalogFilters($categories);
        $events = (new BookingRepository())->publicEvents($filters);

        View::render('events/index', [
            'pageTitle' => 'Upcoming events',
            'events' => $events,
            'categories' => $categories,
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'hasActiveFilters' => $this->hasActiveFilters($filters),
            'resultCount' => count($events),
        ]);
    }

    public function show(int $id): void
    {
        $repository = new BookingRepository();
        $event = $repository->publicEvent($id);
        if ($event === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Event not found']);
            return;
        }
        View::render('events/show', [
            'pageTitle' => $event['title'],
            'event' => $event,
            'tickets' => $repository->publicTickets($id),
        ]);
    }

    private function catalogFilters(array $categories): array
    {
        $errors = [];
        $search = trim((string) ($_GET['q'] ?? ''));
        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
            $errors[] = 'Search text was limited to 100 characters.';
        }

        $categoryId = null;
        $categoryInput = trim((string) ($_GET['category'] ?? ''));
        if ($categoryInput !== '') {
            $activeCategoryIds = array_map(static fn (array $category): int => (int) $category['id'], $categories);
            if (ctype_digit($categoryInput) && in_array((int) $categoryInput, $activeCategoryIds, true)) {
                $categoryId = (int) $categoryInput;
            } else {
                $errors[] = 'The selected category is not available.';
            }
        }

        $dateFrom = trim((string) ($_GET['from'] ?? ''));
        $dateTo = trim((string) ($_GET['to'] ?? ''));
        $fromDate = $this->parseLocalDate($dateFrom);
        $toDate = $this->parseLocalDate($dateTo);

        if ($dateFrom !== '' && $fromDate === null) {
            $errors[] = 'Enter a valid start date.';
            $dateFrom = '';
        }
        if ($dateTo !== '' && $toDate === null) {
            $errors[] = 'Enter a valid end date.';
            $dateTo = '';
        }
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $errors[] = 'The start date must be on or before the end date.';
            $dateFrom = '';
            $dateTo = '';
            $fromDate = null;
            $toDate = null;
        }

        $availability = (string) ($_GET['availability'] ?? 'any');
        if (!in_array($availability, self::AVAILABILITY_FILTERS, true)) {
            $availability = 'any';
            $errors[] = 'The availability filter was reset.';
        }

        $sort = (string) ($_GET['sort'] ?? 'soonest');
        if (!in_array($sort, self::SORT_OPTIONS, true)) {
            $sort = 'soonest';
            $errors[] = 'The sort order was reset.';
        }

        $utc = new DateTimeZone('UTC');
        return [[
            'search' => $search,
            'category_id' => $categoryId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_utc' => $fromDate?->setTimezone($utc)->format('Y-m-d H:i:s'),
            'date_to_utc' => $toDate?->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
            'availability' => $availability,
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

    private function hasActiveFilters(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['category_id'] !== null
            || $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['availability'] !== 'any';
    }
}
