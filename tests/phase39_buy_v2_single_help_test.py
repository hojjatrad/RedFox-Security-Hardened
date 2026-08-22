#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:
 need(p,'BUY_V2_ENTER');need(p,'$activePanels' if False else '$visible')
 need(p,'هنوز محصولی برای این ربات و پنل تعریف نشده است')
 need(p,'selectproductbuy_')
 s=(R/p).read_text(errors='ignore')
 if 'limitedpanelfirst' in s or "managepanel']['limitedpanel" in s:e.append(p+': capacity block returned')
need('re/rx/keyboard/layouts_1.php',"$bt==='text_help'")
need('re/rx/keyboard/layouts_1.php',"trim($bt)==='آموزش'")
need('re/rx/keyboard/layouts_1.php','📚 آموزش اتصال')
need('panel/apps.php','ذخیره ویرایش')
need('panel/help.php','ذخیره تغییرات')
if '<tr><form' in (R/'panel/apps.php').read_text(errors='ignore'):e.append('apps invalid tr/form')
if '<tr><form' in (R/'panel/help.php').read_text(errors='ignore'):e.append('help invalid tr/form')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: BUY_V2 independent handler, single help button and compact valid forms passed')
