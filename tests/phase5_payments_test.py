#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
confirm=text('lib/PaymentConfirm.php'); migration=text('migrations/049_payment_state_machine.sql')
for x in ['expected_method','in_array($actual, $expected, true)','FOR UPDATE',"status='fulfillment_started'", "status='fulfillment_done'", "status='completed'", "status='needs_reconcile'"]:
 if x not in confirm:e.append('lib/PaymentConfirm.php: missing '+x)
for x in ['provider_invoice_id','provider_payment_id','UNIQUE INDEX `uq_payment_provider_invoice`','UNIQUE INDEX `uq_payment_provider_payment`','payment_cashback_ledger']:
 if x not in migration:e.append('migration 049: missing '+x)
callbacks=['payment/zarinpal.php','payment/ZarinPay/successful.php','payment/aqayepardakht.php','payment/iranpay1.php','payment/nowpayment.php','payment/plisio.php','payment/tronado.php']
for rel in callbacks:
 s=text(rel)
 for x in ['payment_confirm_paid','expected_method']:
  if x not in s:e.append(f'{rel}: missing {x}')
# Providers that issue an immutable invoice identifier must bind and compare it.
for rel in ['payment/zarinpal.php','payment/ZarinPay/successful.php','payment/aqayepardakht.php','payment/iranpay1.php','payment/nowpayment.php','payment/plisio.php']:
 need(rel,'provider_invoice_id')
# Every production caller must supply the expected payment method.
for p in R.rglob('*.php'):
 if any(part in {'vendor','tests'} for part in p.parts) or p.name=='compiled.php' or p.name=='PaymentConfirm.php':continue
 s=p.read_text(errors='ignore')
 for m in re.finditer(r'payment_confirm_paid\s*\(',s):
  segment=s[max(0,m.start()-1600):m.start()+1200]
  if 'expected_method' not in segment:e.append(f'{p.relative_to(R)}: payment_confirm_paid call lacks expected_method')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: provider binding, replay-resistant identifiers and durable exactly-once payment effects passed')
