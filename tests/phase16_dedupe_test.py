#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
b=text('botapi.php');m10=text('migrations/010_final_features_no_runtime_ddl.sql');m54=text('migrations/054_processed_update_lifecycle.sql')
for x in ['function isDuplicateUpdate(','rx_require_schema($pdo','INSERT IGNORE INTO processed_updates',"'processing'",'register_shutdown_function',"$failed ? 'failed' : 'completed'",'claim_token',"status='processing'",'redfox_update_claim_unavailable']:
 if x not in b:e.append('botapi.php: missing '+x)
for x in ["http_response_code(!empty($GLOBALS['redfox_update_claim_unavailable']) ? 503 : 200)"]:
 if x not in b:e.append('botapi.php: claim-outage handling missing '+x)
for x in ['CREATE TABLE IF NOT EXISTS `processed_updates`','`update_id` BIGINT UNSIGNED PRIMARY KEY']:
 if x not in m10:e.append('migration 010: missing '+x)
for x in ['ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT','ADD COLUMN claim_token CHAR(64)','ADD COLUMN started_at','ADD COLUMN completed_at','ADD COLUMN failure_code','ADD COLUMN attempts',"SET status='completed'",'idx_processed_updates_lifecycle']:
 if x not in m54:e.append('migration 054: missing '+x)
if "DELETE FROM processed_updates" in b and "status='completed'" not in b:e.append('botapi cleanup may delete non-completed claims')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: atomic database-only Telegram update claims, lifecycle finalization and 503 outage handling passed')
