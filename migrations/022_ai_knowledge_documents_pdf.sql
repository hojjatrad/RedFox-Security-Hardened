-- Large editable knowledge documents and PDF imports.
SET @db:=DATABASE();
SET @q:=IF(COALESCE((SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ai_knowledge' AND COLUMN_NAME='answer'),'')<>'mediumtext','ALTER TABLE ai_knowledge MODIFY answer MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @q:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ai_knowledge' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE ai_knowledge ADD COLUMN updated_at VARCHAR(30) NULL AFTER created_at','SELECT 1');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
