-- Phase 7: reseller 2FA/sessions and signed update history
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND COLUMN_NAME='portal_2fa_enabled')=0,
 'ALTER TABLE `user` ADD COLUMN `portal_2fa_enabled` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `reseller_sessions` (
 `session_hash` CHAR(64) NOT NULL,
 `reseller_id` VARCHAR(64) NOT NULL,
 `ip` VARCHAR(100) NULL,
 `ua_hash` CHAR(64) NOT NULL,
 `ua_label` VARCHAR(255) NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 `last_seen_at` BIGINT UNSIGNED NOT NULL,
 `expires_at` BIGINT UNSIGNED NOT NULL,
 `revoked_at` BIGINT UNSIGNED NULL,
 PRIMARY KEY (`session_hash`), KEY `idx_rs_owner_active` (`reseller_id`,`revoked_at`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `update_history` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `version` VARCHAR(100) NOT NULL,
 `previous_version` VARCHAR(100) NULL,
 `package_sha256` CHAR(64) NOT NULL,
 `status` VARCHAR(30) NOT NULL,
 `actor` VARCHAR(191) NULL,
 `details` TEXT NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), KEY `idx_uh_version_time` (`version`,`created_at`), KEY `idx_uh_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
