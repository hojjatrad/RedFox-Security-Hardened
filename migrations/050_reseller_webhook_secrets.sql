-- Dedicated Telegram webhook secrets for reseller bots.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='botsaz' AND COLUMN_NAME='webhook_secret_token')=0,
 'ALTER TABLE `botsaz` ADD COLUMN `webhook_secret_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE `botsaz`
SET `webhook_secret_token` = LOWER(SHA2(CONCAT(UUID(), ':', RAND(), ':', `id`, ':', NOW(6)), 256))
WHERE `webhook_secret_token` IS NULL OR `webhook_secret_token` = '';
