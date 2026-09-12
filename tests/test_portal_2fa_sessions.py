#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('portal/login.php','portal_2fa_hash')
need('portal/login.php',"reseller_role']??'agent')==='super'")
need('portal/login.php','reseller_sessions')
need('portal/lib/Portal.php','session_hash=? AND reseller_id=?')
need('portal/profile.php','revoke_others')
need('portal/tickets.php','rxp_customer_scope')
need('portal/tickets.php',"rxp_require_perm($ctx,'support')")
need('lib/SecureUpdater.php','sodium_crypto_sign_verify_detached')
need('lib/SecureUpdater.php','Hash mismatch')
need('lib/SecureUpdater.php','Symlinks are forbidden')
need('lib/SecureUpdater.php','Unsigned extra file')
need('lib/SecureUpdater.php','Duplicate ZIP entry')
need('lib/SecureUpdater.php','Update version is not newer')
need('bin/build-update.php','sodium_crypto_sign_detached')
need('bin/update.php','RedFoxSecureUpdater::stage')
need('panel/update.php','مرکز بروزرسانی ساده و امن')
need('lib/InPlaceUpdater.php','RedFoxDatabaseBackup::create')
need('migrations/007_reseller_2fa_secure_updates.sql','reseller_sessions')
need('migrations/007_reseller_2fa_secure_updates.sql','update_history')
up=(R/'panel/update.php').read_text(errors='ignore')
for bad in ['zipball_url','extractTo(','redfox_do_update(']:
 if bad in up:e.append('panel/update.php retains unsafe updater primitive: '+bad)
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 7 static assertions passed')
