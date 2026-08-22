#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('lib/HostingSecrets.php','storage/secure.env.php')
need('lib/HostingSecrets.php','REDFOX_BACKUP_KEY')
need('lib/CronGuard.php','HostingSecrets.php')
need('storage/.htaccess','Require all denied')
need('panel/hosting_setup.php','ساخت Secretهای مفقود')
need('panel/hosting_setup.php','چرخش Cron Secret')
need('lib/Certification.php','migration_013')
need('lib/Certification.php','cron_orchestrator')
need('panel/certification.php','DataUser Read-only')
need('panel/certification.php','certification_runs')
need('deploy/nginx-redfox.conf','location ^~ /storage/')
need('migrations/009_hosting_certification.sql','panel_test_accounts')
need('migrations/009_hosting_certification.sql','certification_runs')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 10 static assertions passed')
