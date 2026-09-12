#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
def forbidden(p,x):
 s=(R/p).read_text(errors='ignore')
 if x in s:e.append(f'{p}: forbidden {x}')
# Capacity blocking must never return to executable bot flows.
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php','re/rx/index/user_flow.php','re/rx/index/compiled.php']:
 s=(R/p).read_text(errors='ignore')
 if 'limitedpanelfirst' in s or "managepanel']['limitedpanel" in s:e.append(p+': forbidden local capacity block returned')
# Unified buttons and handlers in every bot family.
for p in ['vpnbot/Default/keyboard.php','vpnbot/update/keyboard.php','re/rx/keyboard/layouts_1.php','re/rx/keyboard/compiled.php']:
 need(p,'📚 آموزش اتصال');need(p,'📱 نرم‌افزارهای اتصال')
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php','re/rx/index/user_flow.php','re/rx/index/compiled.php']:
 need(p,'rxhelpcat_');need(p,'rxappcat_')
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:
 need(p,'BUY_V2_ENTER');need(p,'هنوز محصولی برای این ربات و پنل تعریف نشده است')
need('lib/BotContentMenu.php','rx_content_categories')
need('lib/ResellerBotManager.php','redfox_apply_telegram_webhook')
need('lib/TelegramWebhook.php',"$safeCall('setWebhook'")
need('lib/InPlaceUpdater.php','RedFoxResellerBotManager')
forbidden('panel/header.php','ResellerBotManager')
need('panel/header.php','SELECT last_version,last_source FROM update_sources')
need('installer/index.php','ResellerBotManager')
need('migrations/036_content_category_deduplicate.sql','uq_cc_section_name')
# GitHub UI contract: one visible URL input, no asset-pattern field.
u=(R/'panel/update.php').read_text(errors='ignore')
if 'name="github_url"' not in u:e.append('update: github_url input missing')
if 'name="asset_pattern"' in u or 'name="github_repo"' in u:e.append('update: obsolete GitHub fields returned')
# HTML edit forms must use form association instead of invalid tr>form.
for p in ['panel/apps.php','panel/help.php']:
 s=(R/p).read_text(errors='ignore')
 if '<tr><form' in s:e.append(p+': invalid table form returned')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: permanent regression contract passed')
