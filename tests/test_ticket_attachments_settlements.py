#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('lib/TicketAttachments.php','FILEINFO_MIME_TYPE')
need('lib/TicketAttachments.php','storage/tickets')
need('portal/ticket_file.php','rxp_customer_scope')
need('lib/ResellerSettlements.php','settlement_escrow')
need('lib/ResellerSettlements.php','FOR UPDATE')
need('panel/reseller_settlements.php','settlement_reject_refund')
need('portal/categories.php','reseller_product_categories')
need('vpnbot/Default/index.php','resellercategory_')
need('vpnbot/Default/keyboard.php','reseller_categories')
need('api/reseller.php','X_RF_SIGNATURE')
need('api/reseller.php','reseller_api_nonces')
need('api/reseller.php','reseller_api_idempotency')
need('api/reseller.php','service_write')
need('migrations/010_final_features_no_runtime_ddl.sql','reseller_settlements')
need('migrations/010_final_features_no_runtime_ddl.sql','reseller_api_idempotency')
need('lib/SchemaGuard.php','forbidden during web/cron runtime')
allowed=('table.php','installer/','bin/','lib/MigrationRunner.php','lib/DatabaseBackup.php','panel/restore.php','panel/backup.php','cronbot/backupbot.php','re/rx/function/bot_api_helpers.php')
rx=re.compile(r'\b(?:CREATE TABLE|ALTER TABLE|DROP TABLE)\b',re.I)
for p in R.rglob('*.php'):
 rel=str(p.relative_to(R)).replace('\\','/')
 if 'vendor/' in rel or '/compiled.php' in rel or rel.startswith(allowed):continue
 if rx.search(p.read_text(errors='ignore')):e.append('runtime DDL remains: '+rel)
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 11 final feature assertions passed')
