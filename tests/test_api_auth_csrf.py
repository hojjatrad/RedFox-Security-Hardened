#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
a=text('api/lib/IntegrationAuth.php')
for x in ['function rx_integration_request_token','function rx_require_integration_auth','SELECT integration_secret_token FROM setting','hash_equals($stored, $provided)',"preg_match('/^Bearer",'Cache-Control: no-store']:
 if x not in a:e.append('api/lib/IntegrationAuth.php: missing '+x)
for x in ['$_GET','$_POST','APIKEY','bottoken','bot_token']:
 if x in a:e.append('api/lib/IntegrationAuth.php: forbidden credential source '+x)
for p in ['api/index.php','webhooks.php','payment/card.php']:
 need(p,'rx_require_integration_auth($pdo)')
need('api/index.php',"redfox_login_rate_check('rest-integration-api'")
for x in ['password_verify($password, $storedPassword)','password_needs_rehash','redfox_secure_session_start()']:
 need('panel/login.php',x)
need('lib/Security.php','function redfox_enforce_csrf')
need('migrations/001_security_hardening.sql','integration_secret_token')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: fail-closed DB integration secret, bearer/header-only credentials and panel password/session checks passed')
