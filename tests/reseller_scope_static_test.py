#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1]
errors=[]
def need(path,text):
 s=(R/path).read_text(errors='ignore')
 if text not in s:errors.append(f'{path}: missing {text}')
need('portal/lib/Portal.php','rxp_invoice_scope')
need('portal/lib/Portal.php','rxp_customer_scope')
need('portal/lib/Portal.php','reseller_parent_id = ?')
need('portal/lib/Portal.php','in_array($target, $ctx[\'scope_ids\'], true)')
need('portal/users.php','rxp_assert_customer')
need('portal/services.php','rxp_assert_invoice')
need('portal/products.php','rxp_target_reseller')
need('portal/cards.php','reseller_id=?')
need('portal/subresellers.php','reseller_parent_id=?')
need('portal/payments.php','ix.id_invoice=p.id_invoice')
need('migrations/002_reseller_portal.sql','reseller_wallet_ledger')
need('panel/agents.php','sethierarchy')
# Every protected portal page must load Portal.php directly or through Layout (login/logout excluded).
for p in (R/'portal').glob('*.php'):
 if p.name in {'login.php','logout.php'}:continue
 s=p.read_text(errors='ignore')
 if "lib/Portal.php" not in s:errors.append(f'{p.name}: Portal scope bootstrap missing')
# No portal mutation may use a request-provided owner without target validation.
for p in (R/'portal').glob('*.php'):
 s=p.read_text(errors='ignore')
 if "$_POST['owner']" in s and 'rxp_target_reseller' not in s:errors.append(f'{p.name}: raw owner mutation')
if errors:
 print('\n'.join('FAIL '+x for x in errors));sys.exit(1)
print('OK: reseller scope static assertions passed')
