<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Core\Logger;
use App\Repositories\EventRepository;
use App\Repositories\TicketTypeRepository;
use App\Services\TicketTypeService;
use DomainException;
use Throwable;

final class OrganizerTicketController
{
    public function index(int $eventId): void
    {
        Authorization::requireApprovedOrganizer();
        $event = $this->ownedEvent($eventId);
        View::render('organizer/tickets/index', [
            'pageTitle' => 'Ticket types', 'event' => $event,
            'tickets' => (new TicketTypeRepository())->forEvent($eventId),
            'errors' => [], 'old' => ['name' => '', 'price' => '', 'capacity' => '', 'display_order' => 0],
            'success' => Session::consumeFlash('success'), 'error' => Session::consumeFlash('error'),
        ]);
    }

    public function store(int $eventId): void
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        $event = $this->ownedEvent($eventId);
        $service = new TicketTypeService();
        [$errors, $data] = $service->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderWithErrors($event, $errors, $_POST);
            return;
        }
        try {
            $service->create($eventId, Auth::id() ?? 0, $data);
            Session::flash('success', 'Ticket type added.');
        } catch (Throwable $exception) {
            $this->flashMutationError($exception);
        }
        Authorization::redirect('organizer/events/' . $eventId . '/tickets');
    }

    public function update(int $eventId, int $ticketId): void
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        $this->ownedEvent($eventId);
        $service = new TicketTypeService();
        [$errors, $data] = $service->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            Authorization::redirect('organizer/events/' . $eventId . '/tickets');
        }
        try {
            $service->update($eventId, $ticketId, Auth::id() ?? 0, $data);
            Session::flash('success', 'Ticket type updated.');
        } catch (Throwable $exception) {
            $this->flashMutationError($exception);
        }
        Authorization::redirect('organizer/events/' . $eventId . '/tickets');
    }

    public function activate(int $eventId, int $ticketId): void
    {
        $this->changeActive($eventId, $ticketId, true);
    }

    public function deactivate(int $eventId, int $ticketId): void
    {
        $this->changeActive($eventId, $ticketId, false);
    }

    private function changeActive(int $eventId, int $ticketId, bool $active): never
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        $this->ownedEvent($eventId);
        try {
            (new TicketTypeService())->setActive($eventId, $ticketId, Auth::id() ?? 0, $active);
            Session::flash('success', 'Ticket type ' . ($active ? 'activated.' : 'deactivated.'));
        } catch (Throwable $exception) {
            $this->flashMutationError($exception);
        }
        Authorization::redirect('organizer/events/' . $eventId . '/tickets');
    }

    private function ownedEvent(int $eventId): array
    {
        $event = (new EventRepository())->findOwned($eventId, Auth::id() ?? 0);
        if ($event === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Event not found']);
            exit;
        }
        return $event;
    }

    private function renderWithErrors(array $event, array $errors, array $old): void
    {
        View::render('organizer/tickets/index', [
            'pageTitle' => 'Ticket types', 'event' => $event,
            'tickets' => (new TicketTypeRepository())->forEvent((int) $event['id']),
            'errors' => $errors, 'old' => $old, 'success' => null, 'error' => null,
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

    private function flashMutationError(Throwable $exception): void
    {
        if ($exception instanceof DomainException) {
            Session::flash('error', $exception->getMessage());
            return;
        }

        Logger::error('ticket.request_failed', [
            'exception' => $exception,
            'organizer_id' => Auth::id(),
        ]);
        Session::flash('error', 'The ticket change could not be saved. Please check the values and try again.');
    }
}
