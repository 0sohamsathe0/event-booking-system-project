<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\EventRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\TicketTypeRepository;
use DomainException;
use Throwable;

final class TicketTypeService
{
    public function validate(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $priceInput = trim((string) ($input['price'] ?? ''));
        $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        $displayOrder = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT);
        $errors = [];

        if (mb_strlen($name) < 1 || mb_strlen($name) > 80) {
            $errors['name'] = 'Ticket name must contain between 1 and 80 characters.';
        }
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $priceInput)) {
            $errors['price'] = 'Enter a valid non-negative price with up to 2 decimal places.';
        }
        if ($capacity === false || $capacity < 1) {
            $errors['capacity'] = 'Capacity must be a positive whole number.';
        }
        if ($displayOrder === false || $displayOrder < 0 || $displayOrder > 65535) {
            $errors['display_order'] = 'Display order must be between 0 and 65,535.';
        }

        return [$errors, [
            'name' => $name,
            'price' => number_format((float) $priceInput, 2, '.', ''),
            'capacity' => (int) $capacity,
            'display_order' => (int) $displayOrder,
        ]];
    }

    public function create(int $eventId, int $organizerId, array $data): void
    {
        $this->mutate($eventId, $organizerId, function (array $event, TicketTypeRepository $tickets) use ($data, $eventId): void {
            if ($tickets->nameExists($eventId, $data['name'])) {
                throw new DomainException('A ticket type with this name already exists for the event.');
            }
            $existing = $tickets->forEvent($eventId, true);
            $applicableCapacity = array_sum(array_map(
                static fn(array $ticket): int => ((int) $ticket['is_active'] === 1 || (int) $ticket['sold_quantity'] > 0 || (int) $ticket['reserved_quantity'] > 0)
                    ? (int) $ticket['capacity'] : 0,
                $existing
            ));
            if ($applicableCapacity + $data['capacity'] > (int) $event['event_capacity']) {
                throw new DomainException('Combined ticket capacity cannot exceed the event capacity of ' . $event['event_capacity'] . '.');
            }
            $tickets->create($data + ['event_id' => $eventId]);
        }, 'Ticket type added by organizer.');
    }

    public function update(int $eventId, int $ticketId, int $organizerId, array $data): void
    {
        $this->mutate($eventId, $organizerId, function (array $event, TicketTypeRepository $tickets) use ($data, $eventId, $ticketId): void {
            $ticket = $tickets->findOwned($ticketId, $eventId, true);
            if ($ticket === null) {
                throw new DomainException('Ticket type was not found.');
            }
            if ($data['capacity'] < (int) $ticket['sold_quantity'] + (int) $ticket['reserved_quantity']) {
                throw new DomainException('Capacity cannot be below the reserved and sold quantity.');
            }
            if ($tickets->nameExists($eventId, $data['name'], $ticketId)) {
                throw new DomainException('A ticket type with this name already exists for the event.');
            }
            $all = $tickets->forEvent($eventId, true);
            $total = 0;
            foreach ($all as $item) {
                if ((int) $item['id'] === $ticketId) {
                    $total += $data['capacity'];
                } elseif ((int) $item['is_active'] === 1 || (int) $item['sold_quantity'] > 0 || (int) $item['reserved_quantity'] > 0) {
                    $total += (int) $item['capacity'];
                }
            }
            if ($total > (int) $event['event_capacity']) {
                throw new DomainException('Combined ticket capacity cannot exceed the event capacity of ' . $event['event_capacity'] . '.');
            }
            $tickets->update($ticketId, $eventId, $data);
        }, 'Ticket type updated by organizer.');
    }

    public function setActive(int $eventId, int $ticketId, int $organizerId, bool $active): void
    {
        $this->mutate($eventId, $organizerId, function (array $event, TicketTypeRepository $tickets) use ($eventId, $ticketId, $active): void {
            $ticket = $tickets->findOwned($ticketId, $eventId, true);
            if ($ticket === null) {
                throw new DomainException('Ticket type was not found.');
            }
            if ($active && !(int) $ticket['is_active']) {
                $all = $tickets->forEvent($eventId, true);
                $total = array_sum(array_map(
                    static fn(array $item): int => ((int) $item['is_active'] === 1 || (int) $item['sold_quantity'] > 0 || (int) $item['reserved_quantity'] > 0 || (int) $item['id'] === $ticketId)
                        ? (int) $item['capacity'] : 0,
                    $all
                ));
                if ($total > (int) $event['event_capacity']) {
                    throw new DomainException('Activating this type would exceed event capacity.');
                }
            }
            $tickets->setActive($ticketId, $eventId, $active);
        }, $active ? 'Ticket type activated by organizer.' : 'Ticket type deactivated by organizer.');
    }

    private function mutate(int $eventId, int $organizerId, callable $operation, string $reason): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $eventRepository = new EventRepository();
            $event = $eventRepository->findOwned($eventId, $organizerId, true);
            if ($event === null) {
                throw new DomainException('Event was not found.');
            }
            if ($event['status'] === 'cancelled') {
                throw new DomainException('Ticket types for a cancelled event cannot be changed.');
            }

            $operation($event, new TicketTypeRepository());
            if (in_array($event['status'], ['approved', 'rejected'], true)) {
                $statement = $database->prepare("UPDATE events SET status = 'pending' WHERE id = :id");
                $statement->execute(['id' => $eventId]);
                $eventRepository->addStatusHistory($eventId, $event['status'], 'pending', $organizerId, $reason . ' Reapproval required.');
                (new NotificationRepository())->createForApprovedAdmins(
                    'event_submitted', 'Event ticket configuration changed',
                    $event['title'] . ' requires review after a ticket change.',
                    $eventId
                );
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }
}
