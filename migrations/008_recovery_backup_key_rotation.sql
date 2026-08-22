-- Phase 8: reseller recovery codes and update key rotation/backup metadata
CREATE TABLE IF NOT EXISTS `reseller_recovery_codes` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `reseller_id` VARCHAR(64) NOT NULL,
 `code_hash` VARCHAR(255) NOT NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 `used_at` BIGINT UNSIGNED NULL,
 PRIMARY KEY (`id`), KEY `idx_rrc_owner_unused` (`reseller_id`,`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='update_history' AND COLUMN_NAME='key_id')=0,'ALTER TABLE `update_history` ADD COLUMN `key_id` VARCHAR(100) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='update_history' AND COLUMN_NAME='channel')=0,'ALTER TABLE `update_history` ADD COLUMN `channel` VARCHAR(30) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='update_history' AND COLUMN_NAME='backup_path')=0,'ALTER TABLE `update_history` ADD COLUMN `backup_path` VARCHAR(1000) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
