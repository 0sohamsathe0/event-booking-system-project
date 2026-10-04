<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class NotificationRepository
{
    public function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $eventId = null,
        ?int $bookingId = null
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO notifications
                (recipient_user_id, type, title, message, event_id, booking_id)
             VALUES
                (:user_id, :type, :title, :message, :event_id, :booking_id)'
        );
        $statement->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'event_id' => $eventId,
            'booking_id' => $bookingId,
        ]);
    }

    public function createForApprovedAdmins(
        string $type,
        string $title,
        string $message,
        ?int $eventId = null,
        ?int $bookingId = null
    ): void {
        $statement = Database::connection()->prepare(
            "INSERT INTO notifications
                (recipient_user_id, type, title, message, event_id, booking_id)
             SELECT id, :type, :title, :message, :event_id, :booking_id
             FROM users
             WHERE role = 'admin' AND account_status = 'approved'"
        );
        $statement->execute([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'event_id' => $eventId,
            'booking_id' => $bookingId,
        ]);
    }

    public function unreadCountForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE recipient_user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function recentForUser(int $userId, int $limit = 3): array
    {
        $safeLimit = max(1, min(10, $limit));
        $statement = Database::connection()->prepare(
            'SELECT id, type, title, message, event_id, booking_id, read_at, created_at
             FROM notifications
             WHERE recipient_user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safeLimit
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function countForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function historyForUser(int $userId, int $page, int $perPage = 20): array
    {
        $safePerPage = max(1, min(50, $perPage));
        $safePage = max(1, $page);
        $offset = ($safePage - 1) * $safePerPage;
        $statement = Database::connection()->prepare(
            'SELECT n.id, n.type, n.title, n.message, n.event_id, n.booking_id,
                    n.read_at, n.created_at,
                    b.id AS owned_booking_id,
                    public_event.id AS public_event_id
             FROM notifications n
             LEFT JOIN bookings b
                ON b.id = n.booking_id AND b.customer_id = n.recipient_user_id
             LEFT JOIN events public_event
                ON public_event.id = n.event_id
               AND public_event.status = \'approved\'
               AND public_event.start_datetime > UTC_TIMESTAMP(6)
             WHERE n.recipient_user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . $safePerPage . ' OFFSET ' . $offset
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function markReadForUser(int $notificationId, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications
             SET read_at = COALESCE(read_at, UTC_TIMESTAMP(6))
             WHERE id = :id AND recipient_user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markAllReadForUser(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications
             SET read_at = UTC_TIMESTAMP(6)
             WHERE recipient_user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
    }
}
