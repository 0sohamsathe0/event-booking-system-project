-- Phase 16: partial ticket cancellation, safe seat reuse, and Razorpay refunds.
-- Apply once after phase_15_ticket_issuance.sql.

ALTER TABLE `bookings`
    MODIFY `status` ENUM(
        'pending_payment',
        'confirmed',
        'partially_cancelled',
        'payment_failed',
        'expired',
        'customer_cancelled',
        'event_cancelled'
    ) NOT NULL DEFAULT 'pending_payment';

ALTER TABLE `payments`
    MODIFY `status` ENUM(
        'created',
        'authorized',
        'captured',
        'partially_refunded',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'created';

ALTER TABLE `issued_tickets`
    ADD COLUMN `active_seat_slot` TINYINT UNSIGNED NULL DEFAULT 1 AFTER `status`;

UPDATE `issued_tickets`
SET `active_seat_slot` = CASE WHEN `status` = 'cancelled' THEN NULL ELSE 1 END;

ALTER TABLE `issued_tickets`
    DROP INDEX `uq_issued_tickets_event_seat`,
    ADD UNIQUE KEY `uq_issued_tickets_active_event_seat`
        (`event_id`, `seat_number`, `active_seat_slot`),
    ADD CONSTRAINT `chk_issued_tickets_active_seat_slot`
        CHECK (`active_seat_slot` IS NULL OR `active_seat_slot` = 1);

ALTER TABLE `refunds`
    ADD COLUMN `refund_reference` VARCHAR(40) NULL AFTER `payment_id`,
    ADD COLUMN `idempotency_key` VARCHAR(64) NULL AFTER `provider_refund_id`,
    ADD COLUMN `refund_percentage` TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER `amount`,
    ADD COLUMN `source` ENUM('customer', 'event') NOT NULL DEFAULT 'customer' AFTER `refund_percentage`,
    ADD COLUMN `event_cancellation_request_id` BIGINT UNSIGNED NULL AFTER `initiated_by`,
    ADD COLUMN `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `failure_description`,
    ADD COLUMN `last_attempted_at` DATETIME(6) NULL AFTER `attempt_count`,
    ADD UNIQUE KEY `uq_refunds_reference` (`refund_reference`),
    ADD UNIQUE KEY `uq_refunds_idempotency` (`idempotency_key`),
    ADD KEY `idx_refunds_event_cancellation` (`event_cancellation_request_id`),
    ADD CONSTRAINT `fk_refunds_event_cancellation`
        FOREIGN KEY (`event_cancellation_request_id`) REFERENCES `event_cancellation_requests` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT `chk_refunds_percentage`
        CHECK (`refund_percentage` BETWEEN 1 AND 100);

CREATE TABLE IF NOT EXISTS `booking_cancellation_actions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `refund_id` BIGINT UNSIGNED NULL,
    `cancelled_quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refund_percentage` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `refund_status` VARCHAR(20) NOT NULL DEFAULT 'not_required',
    `status` ENUM('processing', 'completed') NOT NULL DEFAULT 'processing',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking_cancellation_actions_token` (`request_token`),
    KEY `idx_booking_cancellation_actions_booking` (`booking_id`, `created_at`),
    KEY `idx_booking_cancellation_actions_customer` (`customer_id`, `created_at`),
    KEY `idx_booking_cancellation_actions_refund` (`refund_id`),
    CONSTRAINT `fk_booking_cancellation_actions_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_booking_cancellation_actions_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_booking_cancellation_actions_refund`
        FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ticket_cancellations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `issued_ticket_id` BIGINT UNSIGNED NOT NULL,
    `refund_id` BIGINT UNSIGNED NULL,
    `event_cancellation_request_id` BIGINT UNSIGNED NULL,
    `source` ENUM('customer', 'event') NOT NULL,
    `cancelled_by` BIGINT UNSIGNED NULL,
    `reason` VARCHAR(1000) NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL,
    `refund_percentage` TINYINT UNSIGNED NOT NULL,
    `refund_amount` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ticket_cancellations_ticket` (`issued_ticket_id`),
    KEY `idx_ticket_cancellations_booking_time` (`booking_id`, `created_at`),
    KEY `idx_ticket_cancellations_refund` (`refund_id`),
    KEY `idx_ticket_cancellations_event_request` (`event_cancellation_request_id`),
    KEY `idx_ticket_cancellations_actor` (`cancelled_by`),
    CONSTRAINT `fk_ticket_cancellations_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ticket_cancellations_ticket`
        FOREIGN KEY (`issued_ticket_id`) REFERENCES `issued_tickets` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ticket_cancellations_refund`
        FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_ticket_cancellations_event_request`
        FOREIGN KEY (`event_cancellation_request_id`) REFERENCES `event_cancellation_requests` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_ticket_cancellations_actor`
        FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_ticket_cancellations_percentage`
        CHECK (`refund_percentage` BETWEEN 0 AND 100),
    CONSTRAINT `chk_ticket_cancellations_amounts`
        CHECK (`gross_amount` >= 0 AND `refund_amount` >= 0 AND `refund_amount` <= `gross_amount`)
) ENGINE=InnoDB;
