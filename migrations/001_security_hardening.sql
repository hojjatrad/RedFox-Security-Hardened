-- Red Fox security hardening migration (non-destructive)
-- Run once against the existing database before deploying this release.
SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='user' AND COLUMN_NAME='token_expires_at')=0,
 'ALTER TABLE `user` ADD COLUMN `token_expires_at` BIGINT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX `idx_user_token_expiry` (`token`(64),`token_expires_at`)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='setting' AND COLUMN_NAME='integration_secret_token')=0,
 'ALTER TABLE `setting` ADD COLUMN `integration_secret_token` VARCHAR(64) NOT NULL DEFAULT \'\'', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- Existing Mini App bearer tokens are intentionally invalidated and must be reissued.
UPDATE `user` SET `token_expires_at` = 0 WHERE `token_expires_at` IS NULL OR `token_expires_at` = 0;
