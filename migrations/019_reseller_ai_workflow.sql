-- Reliable reseller AI subscription request/approval workflow.
SET @db:=DATABASE();
SET @q:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='reseller_ai_feature' AND COLUMN_NAME='request_status')=0,
'ALTER TABLE reseller_ai_feature ADD COLUMN request_status VARCHAR(20) NULL,ADD COLUMN requested_price BIGINT UNSIGNED NULL,ADD COLUMN requested_days INT UNSIGNED NULL,ADD COLUMN requested_at VARCHAR(50) NULL,ADD COLUMN approved_by VARCHAR(191) NULL,ADD COLUMN approved_at VARCHAR(50) NULL',
'SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
UPDATE reseller_ai_feature SET request_status='pending' WHERE status='pending' AND (request_status IS NULL OR request_status='');
