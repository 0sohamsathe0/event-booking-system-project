<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AdminEventRepository;
use App\Repositories\EventRepository;
use App\Repositories\NotificationRepository;
use DomainException;
use Throwable;

final class EventApprovalService
{
    public function review(int $eventId, int $adminId, string $decision, ?string $reason): string
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Invalid event decision.');
        }
        $reason = trim((string) $reason);
        if ($decision === 'rejected' && $reason === '') {
            throw new DomainException('A rejection reason is required.');
        }

        $database = Database::connection();
        $preview = (new AdminEventRepository())->basic($eventId);
        if ($preview === null) {
            throw new DomainException('Event was not found.');
        }
        $database->beginTransaction();

        try {
            $hallLock = $database->prepare('SELECT id, maximum_capacity, is_active FROM halls WHERE id = :id FOR UPDATE');
            $hallLock->execute(['id' => $preview['hall_id']]);
            $hall = $hallLock->fetch();
            $events = new AdminEventRepository();
            $event = $events->basic($eventId, true);

            if (!is_array($hall) || !(int) $hall['is_active'] || $event === null) {
                throw new DomainException('The event hall is unavailable.');
            }
            if ($event['status'] !== 'pending') {
                throw new DomainException('Only pending events can be reviewed.');
            }

            $organizerQuery = $database->prepare("SELECT account_status FROM users WHERE id = :id AND role = 'organizer' FOR UPDATE");
            $organizerQuery->execute(['id' => $event['organizer_id']]);
            if ($organizerQuery->fetchColumn() !== 'approved') {
                throw new DomainException('The organizer account is not approved.');
            }

            if ($decision === 'approved') {
                $summary = $events->ticketSummary($eventId);
                if ((int) $summary['type_count'] < 1) {
                    throw new DomainException('Add at least one ticket type before approving this event.');
                }
                if ((int) $event['event_capacity'] > (int) $hall['maximum_capacity']) {
                    throw new DomainException('Event capacity exceeds the hall maximum.');
                }
                if ((int) $summary['total_capacity'] > (int) $event['event_capacity']) {
                    throw new DomainException('Ticket capacity exceeds the event capacity.');
                }
                if ($events->overlapExists($event)) {
                    throw new DomainException('This event overlaps another approved event in the hall.');
                }
            }

            $events->setStatus($eventId, $decision);
            (new EventRepository())->addStatusHistory($eventId, 'pending', $decision, $adminId, $reason ?: 'Event approved after review.');
            (new NotificationRepository())->createForUser(
                (int) $event['organizer_id'], 'event_' . $decision,
                $decision === 'approved' ? 'Event approved' : 'Event rejected',
                $decision === 'approved' ? $event['title'] . ' is approved.' : $event['title'] . ' was rejected. Reason: ' . $reason,
                $eventId
            );
            $database->commit();
            return $event['title'];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }
}
