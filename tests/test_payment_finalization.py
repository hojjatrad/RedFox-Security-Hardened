#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
for p in ['payment/zarinpal.php','payment/aqayepardakht.php','payment/iranpay1.php','payment/tronado.php','payment/ZarinPay/successful.php','payment/card.php','cronbot/croncard.php','cronbot/cryptocheck.php']:
 need(p,'payment_confirm_paid')
need('re/rx/index/finalize.php',"successful_payment")
need('re/rx/index/finalize.php',"answerPreCheckoutQuery")
need('re/rx/index/panel_dispatch.php','payment_confirm_paid')
need('re/rx/admin/bootstrap_2.php','payment_confirm_paid')
need('deploy/nginx-redfox.conf','location ~ ^/(?:bin|lib|vendor|migrations|re/rx)')
need('deploy/deploy.sh','bin/preflight.php')
need('deploy/rollback.sh','does not roll back database migrations')
need('bin/integrity-check.php','duplicate_payment_order_ids')
need('bin/enforce-constraints.php','uq_payment_order_id')
need('migrations/006_integrity_indexes.sql','idx_payment_order_status')
for marker in ['SHA2(id_order, 256)', 'uq_payment_order_id', 'uq_product_code', 'uq_botsaz_token']:
 need('migrations/056_integrity_unique_constraints.sql', marker)
# DirectPayment must have exactly one caller: the central payment service.
callers=[]
for p in R.rglob('*.php'):
 if 'vendor' in p.parts:continue
 s=p.read_text(errors='ignore')
 if 'DirectPayment(' in s and 'function DirectPayment' not in s:callers.append(str(p.relative_to(R)))
if callers!=['lib/PaymentConfirm.php']:e.append('DirectPayment callers are not centralized: '+','.join(callers))
# No gateway/worker may directly mark payment paid.
rx=re.compile(r"UPDATE Payment_report SET payment_Status\s*=\s*'paid'|update\([^\n]*Payment_report[^\n]*payment_Status[^\n]*paid",re.I)
for root in [R/'payment',R/'cronbot']:
 for p in root.rglob('*.php'):
  if rx.search(p.read_text(errors='ignore')):e.append(f'direct paid transition: {p.relative_to(R)}')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: phase 6 static assertions passed')
