-- Event Booking System
-- Phase 4: Database and table implementation
-- Target: XAMPP MariaDB 10.4+ / MySQL 8-compatible SQL
-- Application timezone: Asia/Kolkata; database timestamps are stored as UTC.
-- Import this file using phpMyAdmin. No default admin password is included.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `event_booking_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `event_booking_system`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'organizer', 'admin') NOT NULL,
    `account_status` ENUM('pending', 'approved', 'rejected', 'disabled') NOT NULL,
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME(6) NULL,
    `review_reason` VARCHAR(500) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `account_status`),
    KEY `idx_users_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_users_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_users_name_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `chk_users_email_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`email`)) > 0),
    CONSTRAINT `chk_users_phone_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`phone`)) > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `halls` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `maximum_capacity` INT UNSIGNED NOT NULL,
    `contact_phone` VARCHAR(20) NULL,
    `contact_email` VARCHAR(254) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    CONSTRAINT `chk_halls_capacity_positive`
        CHECK (`maximum_capacity` > 0),
    CONSTRAINT `chk_halls_active_boolean`
        CHECK (`is_active` IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `event_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_categories_name` (`name`),
    CONSTRAINT `chk_event_categories_name_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `chk_event_categories_active_boolean`
        CHECK (`is_active` IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organizer_id` BIGINT UNSIGNED NOT NULL,
    `hall_id` BIGINT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `description` TEXT NOT NULL,
    `poster_path` VARCHAR(255) NULL,
    `poster_provider` VARCHAR(20) NULL,
    `poster_public_id` VARCHAR(255) NULL,
    `start_datetime` DATETIME(6) NOT NULL,
    `end_datetime` DATETIME(6) NOT NULL,
    `sale_start_datetime` DATETIME(6) NOT NULL,
    `sale_end_datetime` DATETIME(6) NOT NULL,
    `event_capacity` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled')
        NOT NULL DEFAULT 'pending',
    `cancelled_by` BIGINT UNSIGNED NULL,
    `cancelled_at` DATETIME(6) NULL,
    `cancellation_reason` VARCHAR(1000) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_events_public_upcoming` (`status`, `start_datetime`),
    KEY `idx_events_hall_schedule`
        (`hall_id`, `status`, `start_datetime`, `end_datetime`),
    KEY `idx_events_organizer_dashboard`
        (`organizer_id`, `status`, `start_datetime`),
    KEY `idx_events_category_public`
        (`category_id`, `status`, `start_datetime`),
    KEY `idx_events_cancelled_by` (`cancelled_by`),
    CONSTRAINT `fk_events_organizer`
        FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_events_hall`
        FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_events_category`
        FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_events_cancelled_by`
        FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_events_title_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`title`)) > 0),
    CONSTRAINT `chk_events_datetime_order`
        CHECK (`end_datetime` > `start_datetime`),
    CONSTRAINT `chk_events_sale_datetime_order`
        CHECK (`sale_end_datetime` > `sale_start_datetime`),
    CONSTRAINT `chk_events_sale_before_event`
        CHECK (`sale_end_datetime` <= `start_datetime`),
    CONSTRAINT `chk_events_capacity_positive`
        CHECK (`event_capacity` > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `payload` MEDIUMBLOB NOT NULL,
    `last_activity` DATETIME(6) NOT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_expiry` (`expires_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `event_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `old_status` ENUM('pending', 'approved', 'rejected', 'cancelled') NULL,
    `new_status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL,
    `changed_by` BIGINT UNSIGNED NULL,
    `reason` VARCHAR(1000) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_event_status_history_event_time` (`event_id`, `created_at`),
    KEY `idx_event_status_history_status_time` (`new_status`, `created_at`),
    KEY `idx_event_status_history_actor` (`changed_by`),
    CONSTRAINT `fk_event_status_history_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_event_status_history_actor`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ticket_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(80) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `capacity` INT UNSIGNED NOT NULL,
    `reserved_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `sold_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ticket_types_event_name` (`event_id`, `name`),
    UNIQUE KEY `uq_ticket_types_id_event` (`id`, `event_id`),
    KEY `idx_ticket_types_event_listing`
        (`event_id`, `is_active`, `display_order`),
    CONSTRAINT `fk_ticket_types_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_ticket_types_name_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `chk_ticket_types_price_nonnegative`
        CHECK (`price` >= 0),
    CONSTRAINT `chk_ticket_types_capacity_positive`
        CHECK (`capacity` > 0),
    CONSTRAINT `chk_ticket_types_inventory_within_capacity`
        CHECK (`reserved_quantity` + `sold_quantity` <= `capacity`),
    CONSTRAINT `chk_ticket_types_active_boolean`
        CHECK (`is_active` IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(24) NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM(
        'pending_payment',
        'confirmed',
        'payment_failed',
        'expired',
        'customer_cancelled',
        'event_cancelled'
    ) NOT NULL DEFAULT 'pending_payment',
    `total_quantity` SMALLINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `reservation_expires_at` DATETIME(6) NULL,
    `booked_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `confirmed_at` DATETIME(6) NULL,
    `cancelled_at` DATETIME(6) NULL,
    `cancellation_reason` VARCHAR(1000) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookings_reference` (`booking_reference`),
    UNIQUE KEY `uq_bookings_id_event` (`id`, `event_id`),
    KEY `idx_bookings_customer_history` (`customer_id`, `booked_at`),
    KEY `idx_bookings_event_status` (`event_id`, `status`, `booked_at`),
    KEY `idx_bookings_expiring_reservations`
        (`status`, `reservation_expires_at`),
    CONSTRAINT `fk_bookings_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_bookings_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_bookings_total_quantity`
        CHECK (`total_quantity` BETWEEN 1 AND 10),
    CONSTRAINT `chk_bookings_total_amount_nonnegative`
        CHECK (`total_amount` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `booking_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `ticket_type_id` BIGINT UNSIGNED NOT NULL,
    `quantity` SMALLINT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking_items_booking_ticket`
        (`booking_id`, `ticket_type_id`),
    KEY `idx_booking_items_booking_event` (`booking_id`, `event_id`),
    KEY `idx_booking_items_ticket_event` (`ticket_type_id`, `event_id`),
    CONSTRAINT `fk_booking_items_booking_event`
        FOREIGN KEY (`booking_id`, `event_id`)
        REFERENCES `bookings` (`id`, `event_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_booking_items_ticket_event`
        FOREIGN KEY (`ticket_type_id`, `event_id`)
        REFERENCES `ticket_types` (`id`, `event_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_booking_items_quantity`
        CHECK (`quantity` BETWEEN 1 AND 10),
    CONSTRAINT `chk_booking_items_unit_price_nonnegative`
        CHECK (`unit_price` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `provider` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    `provider_order_id` VARCHAR(100) NULL,
    `provider_payment_id` VARCHAR(100) NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'INR',
    `status` ENUM(
        'created',
        'authorized',
        'captured',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'created',
    `signature_verified_at` DATETIME(6) NULL,
    `captured_at` DATETIME(6) NULL,
    `failure_code` VARCHAR(100) NULL,
    `failure_description` VARCHAR(500) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_provider_order` (`provider_order_id`),
    UNIQUE KEY `uq_payments_provider_payment` (`provider_payment_id`),
    KEY `idx_payments_booking_time` (`booking_id`, `created_at`),
    KEY `idx_payments_status_time` (`status`, `created_at`),
    CONSTRAINT `fk_payments_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_payments_provider_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`provider`)) > 0),
    CONSTRAINT `chk_payments_amount_nonnegative`
        CHECK (`amount` >= 0),
    CONSTRAINT `chk_payments_currency_inr`
        CHECK (`currency` = 'INR')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `provider_refund_id` VARCHAR(100) NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `status` ENUM('pending', 'processing', 'processed', 'failed')
        NOT NULL DEFAULT 'pending',
    `reason` VARCHAR(1000) NOT NULL,
    `initiated_by` BIGINT UNSIGNED NULL,
    `failure_description` VARCHAR(500) NULL,
    `requested_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `processed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refunds_provider_refund` (`provider_refund_id`),
    KEY `idx_refunds_booking_time` (`booking_id`, `requested_at`),
    KEY `idx_refunds_payment_status` (`payment_id`, `status`),
    KEY `idx_refunds_status_time` (`status`, `requested_at`),
    KEY `idx_refunds_initiated_by` (`initiated_by`),
    CONSTRAINT `fk_refunds_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_refunds_payment`
        FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_refunds_initiated_by`
        FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_refunds_amount_positive`
        CHECK (`amount` > 0),
    CONSTRAINT `chk_refunds_reason_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`reason`)) > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_event_id` VARCHAR(100) NOT NULL,
    `provider` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    `event_type` VARCHAR(100) NOT NULL,
    `payload_hash` CHAR(64) NOT NULL,
    `payload_json` LONGTEXT NULL,
    `processing_status` ENUM('received', 'processed', 'failed', 'ignored')
        NOT NULL DEFAULT 'received',
    `received_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `processed_at` DATETIME(6) NULL,
    `error_message` VARCHAR(1000) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_webhook_provider_event` (`provider_event_id`),
    KEY `idx_payment_webhook_status_time` (`processing_status`, `received_at`),
    KEY `idx_payment_webhook_type_time` (`event_type`, `received_at`),
    CONSTRAINT `chk_payment_webhook_event_id_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`provider_event_id`)) > 0),
    CONSTRAINT `chk_payment_webhook_event_type_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`event_type`)) > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `event_cancellation_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `requested_by` BIGINT UNSIGNED NOT NULL,
    `reason` VARCHAR(1000) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'withdrawn')
        NOT NULL DEFAULT 'pending',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME(6) NULL,
    `review_note` VARCHAR(1000) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_cancellation_requests_admin_queue` (`status`, `created_at`),
    KEY `idx_cancellation_requests_event_status` (`event_id`, `status`),
    KEY `idx_cancellation_requests_organizer_time`
        (`requested_by`, `created_at`),
    KEY `idx_cancellation_requests_reviewer` (`reviewed_by`),
    CONSTRAINT `fk_cancellation_requests_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cancellation_requests_requester`
        FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cancellation_requests_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_cancellation_requests_reason_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`reason`)) > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `event_id` BIGINT UNSIGNED NULL,
    `booking_id` BIGINT UNSIGNED NULL,
    `read_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_notifications_recipient_unread`
        (`recipient_user_id`, `read_at`, `created_at`),
    KEY `idx_notifications_event` (`event_id`),
    KEY `idx_notifications_booking` (`booking_id`),
    CONSTRAINT `fk_notifications_recipient`
        FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_notifications_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_notifications_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_notifications_type_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`type`)) > 0),
    CONSTRAINT `chk_notifications_title_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`title`)) > 0),
    CONSTRAINT `chk_notifications_message_not_blank`
        CHECK (CHAR_LENGTH(TRIM(`message`)) > 0)
) ENGINE=InnoDB;

-- Standard V1 categories. INSERT IGNORE makes repeat imports safe.
INSERT IGNORE INTO `event_categories` (`name`) VALUES
    ('Music'),
    ('Comedy'),
    ('Theatre'),
    ('Workshop'),
    ('Conference'),
    ('Cultural'),
    ('Sports'),
    ('Other');

-- Configure the real hall after import. Example only (intentionally commented):
-- INSERT INTO `halls`
--     (`name`, `address`, `city`, `maximum_capacity`, `contact_phone`, `contact_email`)
-- VALUES
--     ('Your Hall Name', 'Your Hall Address', 'Your City', 500, '9876543210', 'hall@example.com');

-- Create the first admin later with a password hash produced by PHP password_hash().
-- Never place or store a plaintext admin password in this SQL file.
