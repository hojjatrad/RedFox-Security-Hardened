#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
need('migrations/042_admin_notification_center.sql','admin_notifications')
for x in ['Payment_report','Requestagent','support_message','cancel_service','reseller_deposit_requests','payment_effects','reseller_service_operations','reseller_message_queue']:need('lib/AdminNotifications.php',x)
need('lib/AdminNotifications.php','ON DUPLICATE KEY UPDATE')
need('panel/header.php','rxNoticeCount')
need('panel/header.php','اعلان جدید برای مدیریت دریافت شد')
need('panel/header.php','rx-nav-count')
notifications=text('panel/notifications.php')
if not re.search(r"REQUEST_METHOD.*GET.*ajax.*count",notifications,re.S):e.append('panel/notifications.php: read-only GET count endpoint missing')
need('panel/notifications.php','خواندن همه اعلان‌ها')
need('panel/notifications.php',"redfox_enforce_csrf()")
need('panel/support_inbox.php','صندوق پیام‌های پشتیبانی')
need('panel/support_inbox.php',"status='Answered'")
need('panel/header.php','support_inbox.php')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: persistent global admin alerts, read-only polling, CSRF writes and support inbox passed')
