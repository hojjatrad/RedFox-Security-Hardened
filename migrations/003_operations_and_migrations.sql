-- Phase 3: idempotent reseller service operations and migration tracking
CREATE TABLE IF NOT EXISTS `schema_migrations` (
 `version` VARCHAR(191) NOT NULL,
 `checksum` CHAR(64) NOT NULL,
 `executed_at` BIGINT UNSIGNED NOT NULL,
 `execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_service_operations` (
 `operation_id` CHAR(32) NOT NULL,
 `idempotency_key` VARCHAR(64) NOT NULL,
 `actor_reseller_id` VARCHAR(64) NOT NULL,
 `owner_reseller_id` VARCHAR(64) NOT NULL,
 `invoice_id` VARCHAR(200) NOT NULL,
 `action` VARCHAR(40) NOT NULL,
 `amount` INT UNSIGNED NOT NULL DEFAULT 0,
 `price` BIGINT UNSIGNED NOT NULL DEFAULT 0,
 `status` VARCHAR(30) NOT NULL,
 `result` MEDIUMTEXT NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 `updated_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`operation_id`),
 UNIQUE KEY `uq_rso_idempotency` (`idempotency_key`),
 KEY `idx_rso_owner_time` (`owner_reseller_id`,`created_at`),
 KEY `idx_rso_invoice` (`invoice_id`(100),`created_at`),
 KEY `idx_rso_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
