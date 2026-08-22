#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]; errors=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p): errors.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p): errors.append(f'{p}: forbidden {x}')
need('lib/DatabaseBackup.php','$rows->closeCursor()')
need('lib/DatabaseBackup.php','$createStmt->closeCursor()')
need('lib/MigrationRunner.php','$lockStmt->closeCursor()')
need('bin/UpdaterSchema.php','final class RedFoxUpdaterSchema')
need('panel/update.php','RedFoxUpdaterSchema::ensure($pdo)')
need('panel/update.php','name="backup_choice"')
need('panel/update.php',"$backupChoice!=='create'")
need('panel/update.php','بکاپ دیتابیس و Rollback برای بروزرسانی اجباری است')
need('lib/InPlaceUpdater.php','bool$createDatabaseBackup=true')
need('lib/InPlaceUpdater.php',"if(!$createDatabaseBackup)throw new RuntimeException")
need('lib/InPlaceUpdater.php','REDFOX_BACKUP_KEY')
need('lib/InPlaceUpdater.php','RedFoxDatabaseBackup::assertRestoreAvailable()')
forbid('panel/update.php','ادامه بدون بکاپ دیتابیس')
if "header('Location:migrations.php?required=updater')" in text('panel/update.php'): errors.append('update center still redirects to migration page')
if errors: print('\n'.join('FAIL '+x for x in errors));sys.exit(1)
print('OK: direct updater, mandatory encrypted backup/Rollback and PDO cursor hardening passed')
