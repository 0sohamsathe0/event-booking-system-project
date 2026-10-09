-- Event Booking System: additive Phase 14 -> ticket issuance upgrade
-- Safe for repeat execution on MariaDB 10.4+ and MySQL 8.x.
-- Select the application database before running this script.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `issued_tickets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_code` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `booking_item_id` BIGINT UNSIGNED NOT NULL,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `ticket_type_id` BIGINT UNSIGNED NOT NULL,
    `seat_number` INT UNSIGNED NOT NULL,
    `status` ENUM('valid', 'used', 'cancelled') NOT NULL DEFAULT 'valid',
    `issued_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_issued_tickets_code` (`ticket_code`),
    UNIQUE KEY `uq_issued_tickets_event_seat` (`event_id`, `seat_number`),
    KEY `idx_issued_tickets_booking` (`booking_id`, `seat_number`),
    KEY `idx_issued_tickets_booking_item` (`booking_item_id`),
    KEY `idx_issued_tickets_ticket_type` (`ticket_type_id`),
    CONSTRAINT `fk_issued_tickets_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_issued_tickets_booking_item`
        FOREIGN KEY (`booking_item_id`) REFERENCES `booking_items` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_issued_tickets_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_issued_tickets_ticket_type`
        FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_issued_tickets_seat_positive`
        CHECK (`seat_number` > 0)
) ENGINE=InnoDB;
