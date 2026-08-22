-- Phase 5: payment effect idempotency and operational telemetry
CREATE TABLE IF NOT EXISTS `payment_effects` (
 `order_id` VARCHAR(191) NOT NULL,
 `status` VARCHAR(30) NOT NULL,
 `source` VARCHAR(100) NOT NULL DEFAULT '',
 `started_at` BIGINT UNSIGNED NOT NULL,
 `updated_at` BIGINT UNSIGNED NOT NULL,
 `last_error` VARCHAR(2000) NULL,
 PRIMARY KEY (`order_id`), KEY `idx_pe_status_time` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_job_runs` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `job_name` VARCHAR(100) NOT NULL,
 `run_id` CHAR(32) NOT NULL,
 `status` VARCHAR(20) NOT NULL,
 `started_at` BIGINT UNSIGNED NOT NULL,
 `finished_at` BIGINT UNSIGNED NULL,
 `processed_count` INT UNSIGNED NOT NULL DEFAULT 0,
 `error_summary` VARCHAR(2000) NULL,
 `host_name` VARCHAR(191) NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_cjr_run` (`run_id`), KEY `idx_cjr_job_time` (`job_name`,`started_at`), KEY `idx_cjr_status` (`status`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
