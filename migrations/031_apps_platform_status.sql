-- Manageable software platform and active status.
SET @db:=DATABASE();
SET @q:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='app' AND COLUMN_NAME='platform')=0,'ALTER TABLE app ADD COLUMN platform VARCHAR(50) NOT NULL DEFAULT ''عمومی'',ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
UPDATE app SET platform='اندروید' WHERE name LIKE '%اندروید%';UPDATE app SET platform='iOS' WHERE name LIKE '%آیفون%' OR name LIKE '%آیپد%';UPDATE app SET platform='ویندوز، macOS و لینوکس' WHERE name LIKE '%ویندوز%' OR name LIKE '%Linux%' OR name LIKE '%macOS%';
