#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
for x in ['function redfox_login_rate_check','flock($fh, LOCK_EX)','function redfox_secure_session_start','function redfox_enforce_csrf']:
 need('lib/Security.php',x)
for x in ["redfox_login_rate_check('admin'",'password_verify($password, $storedPassword)','password_needs_rehash','redfox_secure_session_start()']:
 need('panel/login.php',x)
need('api/index.php',"require_once __DIR__ . '/lib/IntegrationAuth.php';")
need('api/index.php','rx_require_integration_auth($pdo)')
need('api/index.php',"redfox_login_rate_check('rest-integration-api'")
for x in ['repairAll(','RedFoxResellerBotManager','checkGitHub(']:forbid('panel/header.php',x)
need('panel/header.php','SELECT last_version,last_source FROM update_sources WHERE id=1')
need('panel/notifications.php','redfox_enforce_csrf()')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: atomic rate limiting, password/session/CSRF, integration auth and read-only header passed')
