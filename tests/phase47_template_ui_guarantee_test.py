#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('panel/textbot.php','restore_templates')
need('panel/textbot.php','بازسازی قالب‌های مفقود در دیتابیس')
need('panel/textbot.php',"'_virtual'=>1")
need('panel/textbot.php','ON DUPLICATE KEY UPDATE text=VALUES(text)')
need('panel/textbot.php','قالب‌های گزارش و اعلان قابل ویرایش')
need('panel/textbot.php','گزارش‌های خرید و سرویس نماینده')
need('lib/MessageTemplates.php','report_reseller_purchase')
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:need(p,"rx_message_template($pdo,'report_reseller_purchase'")
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: operational templates are always visible, restorable and upsertable')
