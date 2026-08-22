-- Enforce full uniqueness for security-sensitive business identifiers.
-- Payment_report.id_order is VARCHAR(2000), so a direct utf8mb4 index is not
-- portable. Its complete SHA-256 digest is indexed instead of an unsafe prefix.
-- Existing installations with duplicates are left unchanged so operators can
-- reconcile records with bin/integrity-check.php before running
-- bin/enforce-constraints.php --apply.
SET @db:=DATABASE();

SET @duplicates:=(SELECT COUNT(*) FROM (SELECT id_order FROM Payment_report WHERE id_order IS NOT NULL AND id_order<>'' GROUP BY id_order HAVING COUNT(*)>1) duplicate_orders);
SET @q:=IF(@duplicates=0 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='Payment_report' AND COLUMN_NAME='id_order_hash')=0,"ALTER TABLE Payment_report ADD COLUMN id_order_hash BINARY(32) GENERATED ALWAYS AS (UNHEX(SHA2(id_order, 256))) STORED AFTER id_order",'SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @q:=IF(@duplicates=0 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='Payment_report' AND COLUMN_NAME='id_order_hash')=1 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='Payment_report' AND INDEX_NAME='uq_payment_order_id')=0,"ALTER TABLE Payment_report ADD UNIQUE KEY uq_payment_order_id (id_order_hash)",'SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;

SET @duplicates:=(SELECT COUNT(*) FROM (SELECT code_product FROM product WHERE code_product IS NOT NULL AND code_product<>'' GROUP BY code_product HAVING COUNT(*)>1) duplicate_products);
SET @q:=IF(@duplicates=0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='product' AND INDEX_NAME='uq_product_code')=0,"ALTER TABLE product ADD UNIQUE KEY uq_product_code (code_product)",'SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;

SET @duplicates:=(SELECT COUNT(*) FROM (SELECT bot_token FROM botsaz WHERE bot_token IS NOT NULL AND bot_token<>'' GROUP BY bot_token HAVING COUNT(*)>1) duplicate_bot_tokens);
SET @q:=IF(@duplicates=0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='botsaz' AND INDEX_NAME='uq_botsaz_token')=0,"ALTER TABLE botsaz ADD UNIQUE KEY uq_botsaz_token (bot_token)",'SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
