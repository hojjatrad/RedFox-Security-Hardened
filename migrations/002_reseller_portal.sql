-- Red Fox phase 2: scoped reseller/super-reseller portal
SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND COLUMN_NAME='reseller_role')=0,
 'ALTER TABLE `user` ADD COLUMN `reseller_role` VARCHAR(20) NOT NULL DEFAULT \'agent\'', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND COLUMN_NAME='reseller_parent_id')=0,
 'ALTER TABLE `user` ADD COLUMN `reseller_parent_id` VARCHAR(64) NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND COLUMN_NAME='reseller_portal_status')=0,
 'ALTER TABLE `user` ADD COLUMN `reseller_portal_status` VARCHAR(20) NOT NULL DEFAULT \'active\'', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND INDEX_NAME='idx_reseller_parent')=0,
 'ALTER TABLE `user` ADD INDEX `idx_reseller_parent` (`reseller_parent_id`(64),`agent`(100))', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `reseller_audit_log` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `reseller_id` VARCHAR(64) NOT NULL,
 `actor_role` VARCHAR(20) NOT NULL,
 `action` VARCHAR(100) NOT NULL,
 `entity` VARCHAR(100) NULL,
 `details` TEXT NULL,
 `ip` VARCHAR(100) NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), KEY `idx_ral_owner_time` (`reseller_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_wallet_ledger` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `from_user_id` VARCHAR(64) NOT NULL,
 `to_user_id` VARCHAR(64) NOT NULL,
 `amount` BIGINT UNSIGNED NOT NULL,
 `reason` VARCHAR(100) NOT NULL,
 `actor_reseller_id` VARCHAR(64) NOT NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), KEY `idx_rwl_actor_time` (`actor_reseller_id`,`created_at`), KEY `idx_rwl_users` (`from_user_id`,`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_categories` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `reseller_id` VARCHAR(64) NOT NULL,
 `name` VARCHAR(191) NOT NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_reseller_category` (`reseller_id`,`name`(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve legacy behaviour: accounts with an existing non-empty permission map become super resellers.
UPDATE `user` SET `reseller_role`='super'
WHERE `agent` IN ('n','n2') AND `reseller_perms` IS NOT NULL
AND TRIM(`reseller_perms`) NOT IN ('','{}','[]','null');
