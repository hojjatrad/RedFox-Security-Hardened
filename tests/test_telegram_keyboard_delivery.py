#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
for p in ['vpnbot/Default/func.php','vpnbot/update/func.php']:
 need(p,'REDFOX_TELEGRAM_BUTTON_STYLES');need(p,'rxVpnbotCallbackData');need(p,'strlen($candidate)<=64')
for p in ['vpnbot/Default/botapi.php','vpnbot/update/botapi.php']:
 need(p,'rxVpnbotCleanMarkup');need(p,"unset($x['style'])");need(p,"error_log('[vpnbot telegram]")
for p in ['vpnbot/Default/keyboard.php','vpnbot/update/keyboard.php']:
 need(p,'rxVpnbotCallbackData');need(p,'محصول بدون نام');need(p,'mb_strimwidth')
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:
 need(p,'rxVpnbotResolveCallback');need(p,'LIMIT 80');need(p,'BUY_V2 send failed')
need('migrations/040_bot_callback_map.sql','bot_callback_map')
if (R/'vpnbot/Default/index.php').read_bytes()!=(R/'vpnbot/update/index.php').read_bytes():e.append('index mismatch')
if (R/'vpnbot/Default/botapi.php').read_bytes()!=(R/'vpnbot/update/botapi.php').read_bytes():e.append('botapi mismatch')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: Telegram markup cleanup, retry and long callback mapping passed')
