<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\EventCatalogRepository;
use App\Repositories\EventRepository;
use App\Services\EventService;
use DomainException;
use RuntimeException;

final class OrganizerEventController
{
    public function index(): void
    {
        Authorization::requireApprovedOrganizer();
        View::render('organizer/events/index', [
            'pageTitle' => 'My events',
            'events' => (new EventRepository())->forOrganizer(Auth::id() ?? 0),
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function create(): void
    {
        Authorization::requireApprovedOrganizer();
        $this->renderForm('create', $this->emptyForm(), []);
    }

    public function store(): void
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        $service = new EventService();
        [$errors, $data] = $service->validate($_POST);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('create', $_POST, $errors);
            return;
        }

        try {
            $service->create(Auth::id() ?? 0, $data, $_FILES['poster'] ?? null);
            Session::flash('success', 'Event submitted for admin review. Ticket types can be added in Phase 10.');
            Authorization::redirect('organizer/events');
        } catch (RuntimeException $exception) {
            http_response_code(422);
            $this->renderForm('create', $_POST, ['poster' => $exception->getMessage()]);
        }
    }

    public function edit(int $id): void
    {
        Authorization::requireApprovedOrganizer();
        $event = (new EventRepository())->findOwned($id, Auth::id() ?? 0);
        if ($event === null) {
            $this->notFound();
        }
        if ($event['status'] === 'cancelled') {
            Session::flash('error', 'Cancelled events cannot be edited.');
            Authorization::redirect('organizer/events');
        }
        $this->renderForm('edit', (new EventService())->localFormData($event), [], $event);
    }

    public function update(int $id): void
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        $service = new EventService();
        [$errors, $data] = $service->validate($_POST);
        $existing = (new EventRepository())->findOwned($id, Auth::id() ?? 0);
        if ($existing === null) {
            $this->notFound();
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('edit', $_POST, $errors, $existing);
            return;
        }

        try {
            $service->update(
                $id,
                Auth::id() ?? 0,
                $data,
                $_FILES['poster'] ?? null,
                isset($_POST['remove_poster'])
            );
            Session::flash('success', 'Event updated successfully. Reapproval is required after edits to approved events.');
            Authorization::redirect('organizer/events');
        } catch (DomainException|RuntimeException $exception) {
            http_response_code(422);
            $this->renderForm('edit', $_POST, ['general' => $exception->getMessage()], $existing);
        }
    }

    private function renderForm(string $mode, array $old, array $errors, ?array $event = null): void
    {
        $catalog = new EventCatalogRepository();
        View::render('organizer/events/form', [
            'pageTitle' => $mode === 'create' ? 'Create event' : 'Edit event',
            'mode' => $mode,
            'event' => $event,
            'old' => $old,
            'errors' => $errors,
            'categories' => $catalog->activeCategories(),
            'hall' => $catalog->activeHall(),
        ]);
    }

    private function emptyForm(): array
    {
        return ['title' => '', 'description' => '', 'category_id' => '', 'event_capacity' => '',
            'start_datetime' => '', 'end_datetime' => '', 'sale_start_datetime' => '', 'sale_end_datetime' => ''];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            View::render('errors/419', ['pageTitle' => 'Session expired']);
            exit;
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Event not found']);
        exit;
    }
}

