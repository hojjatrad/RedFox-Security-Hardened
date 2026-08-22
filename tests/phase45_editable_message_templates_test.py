#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
for key in ['report_reseller_purchase','report_reseller_extend','report_reseller_test','alert_reseller_no_credit_customer','alert_reseller_no_credit_admin','alert_reseller_low_balance']:
 need('lib/MessageTemplates.php',key);need('migrations/044_editable_message_templates.sql',key)
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:
 need(p,"rx_message_template($pdo,'report_reseller_purchase'")
 need(p,"rx_message_template($pdo,'report_reseller_extend'")
 need(p,"rx_message_template($pdo,'report_reseller_test'")
 need(p,"rx_message_template($pdo,'alert_reseller_no_credit_customer'")
need('panel/textbot.php','rx_message_template_definitions')
need('panel/textbot.php','گزارش‌های خرید و سرویس نماینده')
need('panel/textbot.php','هشدارهای اعتبار نماینده')
need('panel/textbot.php','متغیرها:')
# Old typo/hardcoded purchase report must not return.
for p in ['vpnbot/Default/index.php','vpnbot/update/index.php']:
 s=(R/p).read_text(errors='ignore')
 if 'موجودی نماینده قبل از خرید :$balanceagent_after' in s:e.append(p+': old balance-after typo returned')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: editable reseller reports, placeholders and credit alerts passed')
