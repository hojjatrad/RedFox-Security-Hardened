-- Replace enumerable invoice IDs in public subscription URLs with 256-bit capability tokens.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=@db AND TABLE_NAME='invoice' AND COLUMN_NAME='subscription_token')=0,
 'ALTER TABLE `invoice` ADD COLUMN `subscription_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=@db AND TABLE_NAME='invoice' AND INDEX_NAME='uq_invoice_subscription_token')=0,
 'ALTER TABLE `invoice` ADD UNIQUE KEY `uq_invoice_subscription_token` (`subscription_token`)',
 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
