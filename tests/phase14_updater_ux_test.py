#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
p=text('panel/update.php');u=text('lib/InPlaceUpdater.php')
for x in ["if($backupChoice!=='create')throw new RuntimeException",'name="backup_choice" value=""','id="createBackupBtn"','id="cancelUpdateBtn"',"startInstall(pendingForm,'create')",'بکاپ رمزنگاری‌شده دیتابیس و امکان Rollback پیش‌نیاز اجباری نصب است.']:
 if x not in p:e.append('panel/update.php: missing '+x)
for x in ["startInstall(form,'skip')",'ادامه بدون بکاپ دیتابیس']: 
 if x in p:e.append('panel/update.php: unsafe optional-backup UX '+x)
for x in ['createDatabaseBackup','if(!$createDatabaseBackup)throw new RuntimeException','DATABASE ROLLBACK FAILED','RedFoxDatabaseBackup::restore']:
 if x not in u:e.append('lib/InPlaceUpdater.php: missing '+x)
need('panel/header.php','SELECT last_version,last_source FROM update_sources WHERE id=1')
for x in ['RedFoxResellerBotManager','repairAll(','checkGitHub(']:forbid('panel/header.php',x)
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: mandatory encrypted backup/Rollback UX and read-only panel header passed')
