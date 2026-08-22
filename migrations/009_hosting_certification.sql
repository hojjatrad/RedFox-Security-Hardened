-- Phase 10: shared-hosting connector tests and certification history
CREATE TABLE IF NOT EXISTS `panel_test_accounts` (
 `panel_code` VARCHAR(191) NOT NULL,
 `test_username` VARCHAR(191) NOT NULL,
 `updated_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`panel_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `certification_runs` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `status` VARCHAR(20) NOT NULL,
 `score` INT UNSIGNED NOT NULL,
 `report_json` MEDIUMTEXT NOT NULL,
 `created_by` VARCHAR(191) NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), KEY `idx_cert_time` (`created_at`), KEY `idx_cert_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
