#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('portal/login.php','reseller_recovery_codes')
need('portal/login.php','used_at IS NULL')
need('portal/profile.php','recovery_codes')
need('portal/profile.php','فقط همین بار')
need('lib/SecureUpdater.php','REDFOX_UPDATE_PUBLIC_KEYS')
need('lib/SecureUpdater.php','REDFOX_UPDATE_CHANNEL')
need('bin/build-update.php','REDFOX_UPDATE_KEY_ID')
need('bin/update.php','backup-database.php')
need('bin/update.php','maintenance.flag')
need('lib/DatabaseBackup.php','sodium_crypto_secretstream_xchacha20poly1305')
need('bin/restore-database.php','--confirm=RESTORE')
need('config.php','maintenance.flag')
need('migrations/008_recovery_backup_key_rotation.sql','reseller_recovery_codes')
need('migrations/008_recovery_backup_key_rotation.sql','backup_path')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 8 static assertions passed')
