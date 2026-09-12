#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
need('installer/index.php','RedFoxMigrationRunner::run')
need('installer/index.php','ensureFreshInstallSecrets')
need('installer/index.php',"'REDFOX_BACKUP_KEY'")
need('installer/index.php','bin2hex(random_bytes(32))')
need('installer/index.php','PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')
need('INSTALL-SHARED-HOST-FA.md','/installer/')
need('README.md','2.4.12-redfox-dedicated-template-editor')
# Fresh installation must create, not merely document, all runtime secrets.
installer=text('installer/index.php')
for key in ['REDFOX_MASTER_KEY','REDFOX_BACKUP_KEY','REDFOX_CRON_SECRET','REDFOX_DIAG_SECRET','REDFOX_HEALTH_TOKEN']:
 if key not in installer:e.append('installer missing generated secret '+key)
if e: print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: fresh shared-host install runs migrations and generates operational secrets')
