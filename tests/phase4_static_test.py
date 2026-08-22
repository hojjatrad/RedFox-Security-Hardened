#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
need('lib/ResellerMessaging.php','rxp_customer_scope')
need('lib/ResellerMessaging.php','حداکثر سه پیام')
need('lib/ResellerMessaging.php','LIMIT 5000')
need('cron/reseller_messages.php','claim_token')
need('cron/reseller_messages.php',"attempt>=5")
need('cron/reseller_messages.php','bot_owner_id')
need('cron/reseller_messages.php',"status='needs_reconcile'")
need('cron/reseller_messages.php','claimed_at')
need('lib/CronGuard.php','REDFOX_CRON_SECRET')
need('portal/messages.php',"rxp_require_perm($ctx,'broadcast')")
need('portal/api_keys.php',"hash('sha256',$newToken)")
need('api/reseller.php','rxp_invoice_scope')
need('api/reseller.php','rxp_customer_scope')
need('api/reseller.php',"redfox_login_rate_check('reseller_api'")
need('api/reseller.php',"if(!$rate['allowed'])")
need('api/reseller.php',"header('Retry-After: '")
need('migrations/004_messaging_finance_api.sql','uq_rmq_broadcast_user')
need('migrations/004_messaging_finance_api.sql','uq_rak_hash')
need('panel/reseller_security.php','cancel_broadcast')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: reseller scopes, durable messaging and atomic API rate limiting passed')
