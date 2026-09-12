#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('lib/ResellerBotManager.php','redfox_apply_telegram_webhook')
need('lib/TelegramWebhook.php',"$safeCall('setWebhook'")
need('lib/ResellerBotManager.php','findByToken')
need('lib/ResellerBotManager.php','sync_status')
need('lib/InPlaceUpdater.php','RedFoxResellerBotManager')
need('panel/agents.php','repair_all_bots')
need('migrations/036_content_category_deduplicate.sql','DELETE c1')
need('migrations/036_content_category_deduplicate.sql','uq_cc_section_name')
need('panel/apps.php','form="<?=$fid?>"')
need('panel/apps.php','ذخیره ویرایش')
need('panel/help.php','form="<?=$fid?>"')
need('panel/help.php','sendVideo')
need('re/rx/keyboard/layouts_2.php','content_categories')
need('re/rx/index/user_flow.php','content_categories')
need('panel/update.php','مسیر دریافت بروزرسانی از GitHub')
need('panel/reseller_report.php','liveCanBuy')
need('panel/reseller_security.php','فهرست IP مجاز')
need('panel/service_operations.php','نیازمند بررسی')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: reseller bot repair, category dedupe, valid edit forms and live report passed')
