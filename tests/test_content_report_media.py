#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('migrations/035_content_categories_media.sql','content_categories')
need('panel/help.php',"$a==='cat_add'")
need('panel/help.php','sendPhoto')
need('panel/help.php','sendVideo')
need('panel/apps.php',"$a==='cat_add'")
need('panel/apps.php',"$a==='toggle'")
need('re/rx/keyboard/layouts_2.php','content_categories')
need('re/rx/index/user_flow.php','content_categories')
need('panel/reseller_security.php','تلاش مجدد')
need('panel/reseller_security.php','فهرست IP مجاز')
need('panel/update.php','مسیر دریافت بروزرسانی از GitHub')
need('panel/operations.php','مرکز عملیات سیستم')
need('panel/reseller_report.php','liveBalance')
need('panel/reseller_report.php','نیازمند شارژ')
need('panel/reseller_report.php',"($_GET['ajax']??'')==='balance'")
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: category/media CRUD, Persian operations and live reseller credit report passed')
