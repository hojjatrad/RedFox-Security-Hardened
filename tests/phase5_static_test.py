#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p): return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
# Current implementation embeds the durable payment-effect state machine directly.
for x in ["SELECT * FROM payment_effects", "status='claimed'", "status='fulfillment_started'", "status='fulfillment_done'", "status='completed'", "status='needs_reconcile'", 'FOR UPDATE']:
 need('lib/PaymentConfirm.php',x)
need('migrations/005_production_operations.sql','payment_effects')
need('migrations/005_production_operations.sql','cron_job_runs')
need('lib/CronGuard.php','REDFOX_CRON_SECRET')
need('lib/CronGuard.php','rx_cron_telemetry_start')
need('cron/cron.php','X-Cron-Secret: ')
need('cron/diag.php','REDFOX_DIAG_SECRET')
need('ready.php','013_update_progress_ui.sql')
need('bin/preflight.php','migration_013')
need('bin/maintenance.php','gzencode')
ht=text('.htaccess')
for x in ['RewriteRule','bin|lib|vendor|migrations|re/rx|updates|Upload','[F,L,NC]']:
 if x not in ht:e.append('.htaccess: protected internal paths rule missing '+x)
for p in (R/'cronbot').glob('*.php'):
 if p.name=='_init.php':continue
 s=p.read_text(errors='ignore')[:1500]
 if '_init.php' not in s and 'rx_cron_authorize()' not in s:e.append(f'{p.relative_to(R)}: unguarded cron entry')
for name in ['cron.php','migrate_indexes.php','reseller_messages.php']:
 s=(R/'cron'/name).read_text(errors='ignore')[:1000]
 if 'rx_cron_authorize()' not in s:e.append(f'cron/{name}: unguarded')
for name in ['cron_campaigns.php','cron_renewal.php']:
 if 'rx_cron_authorize()' not in (R/name).read_text(errors='ignore')[:1000]:e.append(f'{name}: unguarded')
if (R/'error_log').exists():e.append('release contains root error_log')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: durable payment effects, protected cron/diagnostics and internal path denial passed')
