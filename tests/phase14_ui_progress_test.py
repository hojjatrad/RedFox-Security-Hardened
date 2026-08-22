#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]; e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p): e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p): e.append(f'{p}: forbidden {x}')
need('migrations/013_update_progress_ui.sql','progress_percent')
need('lib/InPlaceUpdater.php',"$setProgress(10,'تهیه بکاپ")
need('lib/InPlaceUpdater.php','total_action_files')
need('panel/update.php','update-progress-bar')
need('panel/update.php','ajax=progress')
need('panel/update.php','⬆️ نصب بروزرسانی')
need('panel/update.php','در حال آماده‌سازی بکاپ اجباری دیتابیس')
need('panel/migrations.php','migration-bar')
need('panel/migrations.php','ajax=progress')
need('panel/migrations.php','بکاپ و بروزرسانی ساختار دیتابیس')
need('panel/update.php',"glob($uploadDir.'/*.zip')")
need('lib/UpdateSources.php','checkLocalFolders')
need('lib/UpdateSources.php','RedFoxSecureUpdater::inspect($file, $current)')
need('cron/update_check.php',"source'=>$best['source']")
need('panel/header.php','__newUpdateSource')
need('panel/css/theme.css','unified professional Persian UI layer')
# The legacy Mini App page must not render or trust query authentication.
need('panel/miniapp.php',"header('Location: ../app/', true, 302)")
need('panel/miniapp.php','exit;')
for x in ['$_GET[\'uid\']','$_GET["uid"]']: forbid('panel/miniapp.php',x)
need('Upload/.htaccess','Require all denied')
ui=text('panel/update.php')
for removed in ['name="confirm_version"','name="unsigned_confirm"','>Diff<','placeholder="MIGRATE"','ادامه بدون بکاپ دیتابیس']:
 if removed in ui:e.append('obsolete/unsafe update control remains: '+removed)
if e: print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: progress UI, signed-only install and Mini App compatibility redirect passed')
