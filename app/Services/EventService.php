<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\EventCatalogRepository;
use App\Repositories\EventRepository;
use App\Repositories\NotificationRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class EventService
{
    private const LOCAL_TIMEZONE = 'Asia/Kolkata';

    public function __construct(
        private readonly EventRepository $events = new EventRepository(),
        private readonly EventCatalogRepository $catalog = new EventCatalogRepository(),
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly PosterUploader $posters = new PosterUploader()
    ) {
    }

    public function validate(array $input): array
    {
        $errors = [];
        $data = [
            'title' => trim((string) ($input['title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'category_id' => filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'hall_id' => filter_var($input['hall_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'event_capacity' => filter_var($input['event_capacity'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'start_local' => trim((string) ($input['start_datetime'] ?? '')),
            'end_local' => trim((string) ($input['end_datetime'] ?? '')),
            'sale_start_local' => trim((string) ($input['sale_start_datetime'] ?? '')),
            'sale_end_local' => trim((string) ($input['sale_end_datetime'] ?? '')),
        ];

        if (mb_strlen($data['title']) < 3 || mb_strlen($data['title']) > 180) {
            $errors['title'] = 'Title must contain between 3 and 180 characters.';
        }
        if (mb_strlen($data['description']) < 20 || mb_strlen($data['description']) > 5000) {
            $errors['description'] = 'Description must contain between 20 and 5,000 characters.';
        }
        if (!$this->catalog->categoryExists($data['category_id'])) {
            $errors['category_id'] = 'Select a valid active category.';
        }

        $hall = $this->catalog->hallById($data['hall_id']);
        if ($hall === null) {
            $errors['hall_id'] = 'The active hall is not configured.';
        } elseif ($data['event_capacity'] < 1 || $data['event_capacity'] > (int) $hall['maximum_capacity']) {
            $errors['event_capacity'] = 'Capacity must be between 1 and ' . $hall['maximum_capacity'] . '.';
        }

        $start = $this->parseLocal($data['start_local'], 'start_datetime', $errors);
        $end = $this->parseLocal($data['end_local'], 'end_datetime', $errors);
        $saleStart = $this->parseLocal($data['sale_start_local'], 'sale_start_datetime', $errors);
        $saleEnd = $this->parseLocal($data['sale_end_local'], 'sale_end_datetime', $errors);

        $now = new DateTimeImmutable('now', new DateTimeZone(self::LOCAL_TIMEZONE));
        if ($start && $start <= $now) {
            $errors['start_datetime'] = 'Event start must be in the future.';
        }
        if ($start && $end && $end <= $start) {
            $errors['end_datetime'] = 'Event end must be after its start.';
        }
        if ($saleStart && $saleEnd && $saleEnd <= $saleStart) {
            $errors['sale_end_datetime'] = 'Ticket sales must end after they begin.';
        }
        if ($saleEnd && $start && $saleEnd > $start) {
            $errors['sale_end_datetime'] = 'Ticket sales must end no later than the event start.';
        }

        if ($errors === []) {
            $utc = new DateTimeZone('UTC');
            $data += [
                'start_datetime' => $start->setTimezone($utc)->format('Y-m-d H:i:s.u'),
                'end_datetime' => $end->setTimezone($utc)->format('Y-m-d H:i:s.u'),
                'sale_start_datetime' => $saleStart->setTimezone($utc)->format('Y-m-d H:i:s.u'),
                'sale_end_datetime' => $saleEnd->setTimezone($utc)->format('Y-m-d H:i:s.u'),
            ];
        }

        return [$errors, $data];
    }

    public function create(int $organizerId, array $data, ?array $poster): int
    {
        $newPoster = $this->posters->store($poster);
        $database = Database::connection();
        $database->beginTransaction();

        try {
            $eventId = $this->events->create($this->persistenceData($organizerId, $data, $newPoster));
            $this->events->addStatusHistory($eventId, null, 'pending', $organizerId, 'Event submitted for review.');
            $this->notifications->createForApprovedAdmins(
                'event_submitted',
                'New event awaiting review',
                $data['title'] . ' was submitted for approval.',
                $eventId
            );
            $database->commit();
            return $eventId;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->posters->delete($newPoster);
            throw $exception;
        }
    }

    public function update(int $eventId, int $organizerId, array $data, ?array $poster, bool $removePoster): void
    {
        $newPoster = $this->posters->store($poster);
        $oldPosterToDelete = null;
        $database = Database::connection();
        $database->beginTransaction();

        try {
            $event = $this->events->findOwned($eventId, $organizerId, true);
            if ($event === null) {
                throw new DomainException('Event was not found.');
            }
            if ($event['status'] === 'cancelled') {
                throw new DomainException('A cancelled event cannot be edited.');
            }

            $posterPath = $event['poster_path'];
            if ($newPoster !== null) {
                $oldPosterToDelete = $posterPath;
                $posterPath = $newPoster;
            } elseif ($removePoster) {
                $oldPosterToDelete = $posterPath;
                $posterPath = null;
            }

            $oldStatus = $event['status'];
            $newStatus = in_array($oldStatus, ['approved', 'rejected'], true) ? 'pending' : $oldStatus;
            $this->events->update(
                $eventId,
                $organizerId,
                $this->persistenceData($organizerId, $data, $posterPath),
                $newStatus
            );

            if ($newStatus !== $oldStatus) {
                $reason = $oldStatus === 'approved'
                    ? 'Approved event edited by organizer; reapproval required.'
                    : 'Rejected event edited and resubmitted.';
                $this->events->addStatusHistory($eventId, $oldStatus, $newStatus, $organizerId, $reason);
                $this->notifications->createForApprovedAdmins(
                    'event_submitted',
                    'Event awaiting reapproval',
                    $data['title'] . ' was edited and submitted again.',
                    $eventId
                );
            }

            $database->commit();
            $this->posters->delete($oldPosterToDelete);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->posters->delete($newPoster);
            throw $exception;
        }
    }

    public function localFormData(array $event): array
    {
        foreach (['start_datetime', 'end_datetime', 'sale_start_datetime', 'sale_end_datetime'] as $field) {
            $event[$field] = (new DateTimeImmutable($event[$field], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(self::LOCAL_TIMEZONE))->format('Y-m-d\TH:i');
        }
        return $event;
    }

    private function parseLocal(string $value, string $field, array &$errors): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone(self::LOCAL_TIMEZONE));
        $parseErrors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))) {
            $errors[$field] = 'Enter a valid date and time.';
            return null;
        }
        return $date;
    }

    private function persistenceData(int $organizerId, array $data, ?string $posterPath): array
    {
        return [
            'organizer_id' => $organizerId,
            'hall_id' => $data['hall_id'],
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'poster_path' => $posterPath,
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'sale_start_datetime' => $data['sale_start_datetime'],
            'sale_end_datetime' => $data['sale_end_datetime'],
            'event_capacity' => $data['event_capacity'],
        ];
    }
}
