#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]; e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p): e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p): e.append(f'{p}: forbidden {x}')
need('lib/SecureUpdater.php','sodium_crypto_sign_verify_detached')
need('lib/SecureUpdater.php','update-manifest.json')
need('lib/SecureUpdater.php','update-signature.txt')
for x in ['inspectAny','inspectUnsigned',"'signed'=>false"]: forbid('lib/SecureUpdater.php',x)
need('lib/InPlaceUpdater.php','storage/update-backups')
need('lib/InPlaceUpdater.php','RedFoxDatabaseBackup::create')
need('lib/InPlaceUpdater.php','RedFoxMigrationRunner::run')
need('lib/InPlaceUpdater.php','vpnbot/Default/')
need('lib/InPlaceUpdater.php','Unsigned updates are forbidden')
need('lib/UpdateSources.php','api.github.com/repos/')
need('lib/UpdateSources.php',"strtolower((string)($parts['scheme'] ?? '')) !== 'https'")
need('lib/UpdateSources.php','CURLOPT_SSL_VERIFYPEER => true')
need('lib/UpdateSources.php','CURLOPT_SSL_VERIFYHOST => 2')
need('lib/UpdateSources.php','CURLOPT_FOLLOWLOCATION => false')
need('lib/UpdateSources.php','RedFoxSecureUpdater::inspect($file, $current)')
for x in ['inspectAny','inspectUnsigned']: forbid('lib/UpdateSources.php',x)
need('cron/update_check.php','checkGitHub')
need('panel/update.php','مرکز بروزرسانی ساده و امن')
need('panel/update.php','class="install-form"')
need('panel/update.php',"$backupChoice!=='create'")
need('panel/header.php','نسخه <?=htmlspecialchars($__newUpdateVersion)?> از')
need('migrations/012_professional_updater.sql','update_jobs')
for p in ['panel/update.php','lib/InPlaceUpdater.php']:
 if 'extractTo(' in text(p): e.append(f'{p}: updater uses unsafe bulk extractTo')
if e: print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: signed-only multi-source updater, mandatory backup and bounded extraction passed')
