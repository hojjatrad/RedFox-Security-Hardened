#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('panel/message_templates.php','rx_message_template_definitions')
need('panel/message_templates.php','INSERT INTO textbot(id_text,text) VALUES(?,?) ON DUPLICATE KEY UPDATE')
need('panel/message_templates.php','ذخیره همه قالب‌ها')
need('panel/message_templates.php','بازسازی فقط قالب‌های مفقود')
need('panel/header.php','قالب گزارش‌های نماینده')
need('panel/textbot.php','بازکردن ویرایشگر اختصاصی قالب‌های نماینده')
need('lib/MessageTemplates.php','report_reseller_purchase')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: dedicated template editor is independent from generic text rows and migration seed')
