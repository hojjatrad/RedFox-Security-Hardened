#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('config.php','REDFOX_DB_HOST')
need('config.php','REDFOX_BOT_TOKEN')
need('botapi.php','REDFOX_TELEGRAM_API_BASE')
need('portal/login.php','ورود از دستگاه جدید')
need('lib/SecureUpdater.php','.release-manifest.json')
need('lib/SecureUpdater.php','verifyRelease')
need('bin/verify-release.php','UNSIGNED INITIAL/MANUAL RELEASE')
need('bin/preflight.php','release_integrity')
need('deploy/staging/docker-compose.yml','REDFOX_TELEGRAM_API_BASE')
need('deploy/staging/mock-server.py','ThreadingHTTPServer')
need('.github/workflows/ci.yml','composer audit')
need('.github/workflows/ci.yml','php tests/runtime_core.php')
need('tests/runtime_core.php','RUNTIME CORE OK')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 9 static assertions passed')
