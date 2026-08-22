#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
# Legacy server-rendered Mini App is a redirect only.
need('panel/miniapp.php',"header('Location: ../app/', true, 302)")
# Telegram initData is signature-checked, fresh, and never accepted from URLs.
for x in ["hash_hmac('sha256', $botToken, 'WebAppData', true)",'hash_equals($calcHash, $receivedHash)',"($now - $authDate) > 600",'bin2hex(random_bytes(32))','token_expires_at']:
 need('api/lib/Auth.php',x)
for x in ["$_GET['initData']","$_GET['init_data']",'initDataUnsafe']:
 forbid('api/lib/Auth.php',x)
for endpoint in ['api/verify.php','api/phone.php']:
 need(endpoint,"REQUEST_METHOD")
 need(endpoint,"!== 'POST'")
 need(endpoint,"contentType !== 'application/json'")
 need(endpoint,'redfox_request_origin_is_same_site()')
 need(endpoint,'redfox_login_rate_check(')
for x in ['RedFoxAuth::extractBearerToken()','RedFoxAuth::userFromToken($token)','redfox_configured_origin()',"if ($method === 'GET' && $action !== 'brand_info')"]:
 need('api/miniapp.php',x)
# Removed main-bot privileged Mini App dispatcher must not return.
k=text('re/rx/keyboard/layouts_1.php'); p=text('re/rx/index/panel_dispatch.php')
for unsafe in ['function swquery(','function switchInlineQuery(','function requestMiniApp(','function createInvoiceIntent(']:
 if unsafe in k:e.append('legacy Mini App dispatcher remains: '+unsafe)
for unsafe in ['switchInlineQuery($text','requestMiniApp($text','createInvoiceIntent($text']:
 if unsafe in p:e.append('privileged Mini App call remains: '+unsafe)
need('re/rx/index/panel_dispatch.php','$rxPrivilegedRequests')
need('re/rx/index/panel_dispatch.php','$rxAssertLockedPermission')
need('re/rx/index/panel_dispatch.php','FOR UPDATE')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: Mini App initData signature/freshness, POST/origin gates, bearer auth and legacy-surface removal passed')
