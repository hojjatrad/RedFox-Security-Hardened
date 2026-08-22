#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];errors=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:errors.append(f'{p}: missing {x}')
need('lib/ResellerServiceOperations.php','idempotency_key')
need('lib/ResellerServiceOperations.php','funds_reserved')
need('lib/ResellerServiceOperations.php','remoteApplied')
need('lib/ResellerServiceOperations.php','needs_reconcile')
need('lib/ResellerServiceOperations.php','refund_service_operation')
need('portal/services.php','rxp_assert_invoice')
need('portal/services.php','ResellerServiceOperations')
need('panel/service_operations.php','mark_succeeded')
need('migrations/003_operations_and_migrations.sql','uq_rso_idempotency')
need('lib/MigrationRunner.php',"GET_LOCK('redfox_schema_migrations',30)")
need('lib/MigrationRunner.php','checksum mismatch')
need('lib/Secrets.php','rxenc:v1:')
need('lib/Secrets.php','REDFOX_MASTER_KEY')
need('panel/panels.php','rx_secret_encrypt($passPanel)')
need('re/rx/function/bootstrap.php','rx_secret_decrypt_db_result')
need('portal/products.php',"'product.create'")
need('vpnbot/Default/keyboard.php','rxVpnbotProductAllowed')
need('vpnbot/Default/index.php',"agent = '{$dataBase['id_user']}'")
if errors:print('\n'.join('FAIL '+e for e in errors));sys.exit(1)
print('OK: phase 3 static assertions passed')
