#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('migrations/033_reseller_deposit_settlement.sql','reseller_deposit_requests')
need('portal/settlements.php','شماره شبای نماینده لازم نیست')
need('portal/settlements.php','شماره پیگیری بانکی')
need('panel/reseller_settlements.php','تأیید و افزایش اعتبار')
need('panel/reseller_settlements.php','COLLATE utf8mb4_unicode_ci')
if 'reseller_payout_accounts' in (R/'panel/reseller_settlements.php').read_text(errors='ignore'):e.append('old payout/IBAN model remains in admin settlement')
need('migrations/034_v2ray_windows_defaults.sql','v2rayN — ویندوز')
need('migrations/034_v2ray_windows_defaults.sql','اتصال با v2rayN در ویندوز')
need('re/rx/index/panel_dispatch.php','appplatform_')
need('re/rx/index/panel_dispatch.php','سیستم‌عامل خود را انتخاب کنید')
need('panel/js/persian-date.js','rx-jalali-picker')
need('panel/js/persian-date.js','toG(')
need('panel/header.php','persian-date.js')
need('portal/lib/Layout.php','persian-date.js')
need('panel/css/theme.css','unified Persian typography and spacing')
need('panel/css/theme.css','--rx-title-size')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: deposit settlement, OS grouping, Jalali picker and unified typography passed')
