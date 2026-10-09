<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CancellationRepository;
use App\Services\EventCancellationService;
use DomainException;

final class OrganizerCancellationController
{
    public function index(): void
    {
        Authorization::requireApprovedOrganizer();
        $organizerId = Auth::id() ?? 0;
        $repository = new CancellationRepository();
        View::render('organizer/cancellations/index', [
            'pageTitle' => 'Event cancellations',
            'requests' => $repository->forOrganizer($organizerId),
            'events' => $repository->eligibleOrganizerEvents($organizerId),
            'success' => Session::consumeFlash('success'),
            'error' => Session::consumeFlash('error'),
        ]);
    }

    public function store(int $eventId): never
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        try {
            (new EventCancellationService())->request($eventId, Auth::id() ?? 0, $_POST['reason'] ?? null);
            Session::flash('success', 'The event cancellation request was sent for admin review.');
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        Authorization::redirect('organizer/cancellations');
    }

    public function withdraw(int $id): never
    {
        Authorization::requireApprovedOrganizer();
        $this->verifyCsrf();
        try {
            (new EventCancellationService())->withdraw($id, Auth::id() ?? 0);
            Session::flash('success', 'The cancellation request was withdrawn.');
        } catch (DomainException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        Authorization::redirect('organizer/cancellations');
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
