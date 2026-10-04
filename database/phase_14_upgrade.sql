-- Event Booking System: additive Phase 13 -> Phase 14 upgrade
-- Safe for repeat execution on MariaDB 10.4+ and MySQL 8.x.
-- Select the application database before running this script.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `payload` MEDIUMBLOB NOT NULL,
    `last_activity` DATETIME(6) NOT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_expiry` (`expires_at`)
) ENGINE=InnoDB;

DELIMITER //
DROP PROCEDURE IF EXISTS `phase_14_add_event_poster_metadata`//
CREATE PROCEDURE `phase_14_add_event_poster_metadata`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'poster_provider'
    ) THEN
        ALTER TABLE `events`
            ADD COLUMN `poster_provider` VARCHAR(20) NULL AFTER `poster_path`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'poster_public_id'
    ) THEN
        ALTER TABLE `events`
            ADD COLUMN `poster_public_id` VARCHAR(255) NULL AFTER `poster_provider`;
    END IF;
END//
CALL `phase_14_add_event_poster_metadata`()//
DROP PROCEDURE `phase_14_add_event_poster_metadata`//
DELIMITER ;
