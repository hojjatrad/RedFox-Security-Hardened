-- Phase 6: non-breaking indexes used by payment/integrity/reconciliation paths
-- Prefix lengths are mandatory because legacy Red Fox columns can be VARCHAR(2000)
-- and utf8mb4 may consume four bytes per character (MySQL index limit: 3072 bytes).
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='Payment_report' AND INDEX_NAME='idx_payment_order_status')=0,
 'ALTER TABLE `Payment_report` ADD INDEX `idx_payment_order_status` (`id_order`(100),`payment_Status`(20))', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='invoice' AND INDEX_NAME='idx_invoice_username_location')=0,
 'ALTER TABLE `invoice` ADD INDEX `idx_invoice_username_location` (`username`(75),`Service_location`(75))', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='botsaz' AND INDEX_NAME='idx_botsaz_token_owner')=0,
 'ALTER TABLE `botsaz` ADD INDEX `idx_botsaz_token_owner` (`bot_token`(75),`id_user`(75))', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
