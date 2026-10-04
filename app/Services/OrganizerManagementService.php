<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\NotificationRepository;
use App\Repositories\OrganizerRepository;
use DomainException;
use Throwable;

final class OrganizerManagementService
{
    public function __construct(
        private readonly OrganizerRepository $organizers = new OrganizerRepository(),
        private readonly NotificationRepository $notifications = new NotificationRepository()
    ) {
    }

    public function review(int $organizerId, int $adminId, string $decision, ?string $reason): string
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Invalid organizer decision.');
        }

        $reason = trim((string) $reason);
        if ($decision === 'rejected' && $reason === '') {
            throw new DomainException('A rejection reason is required.');
        }

        $database = Database::connection();
        $database->beginTransaction();

        try {
            $organizer = $this->organizers->lockById($organizerId);

            if ($organizer === null || $organizer['role'] !== 'organizer') {
                throw new DomainException('Organizer account was not found.');
            }

            if ($organizer['account_status'] !== 'pending') {
                throw new DomainException('Only pending organizer applications can be reviewed.');
            }

            $this->organizers->updateReview(
                $organizerId,
                $decision,
                $adminId,
                $decision === 'rejected' ? $reason : null
            );

            $this->notifications->createForUser(
                $organizerId,
                'organizer_' . $decision,
                $decision === 'approved' ? 'Organizer account approved' : 'Organizer application rejected',
                $decision === 'approved'
                    ? 'Your organizer account is approved. You can now create events.'
                    : 'Your organizer application was rejected. Reason: ' . $reason
            );

            $database->commit();
            return $organizer['name'];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function resubmit(int $organizerId, string $name, string $phone): void
    {
        $name = trim($name);
        $phone = preg_replace('/[\s()-]+/', '', trim($phone)) ?? '';

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new DomainException('Name must contain between 2 and 100 characters.');
        }

        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            throw new DomainException('Enter a valid phone number containing 10 to 15 digits.');
        }

        $database = Database::connection();
        $database->beginTransaction();

        try {
            $organizer = $this->organizers->lockById($organizerId);
            if ($organizer === null || $organizer['role'] !== 'organizer'
                || $organizer['account_status'] !== 'rejected') {
                throw new DomainException('Only a rejected organizer application can be resubmitted.');
            }

            $this->organizers->resubmit($organizerId, $name, $phone);
            $this->notifications->createForApprovedAdmins(
                'organizer_registration_submitted',
                'Organizer application resubmitted',
                $name . ' resubmitted an organizer application for review.'
            );
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }
}

