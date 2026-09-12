#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];s=(R/'panel/header.php').read_text(errors='ignore');e=[]
if s.count('href="update.php"')<3:e.append('update links missing from top/header/system navigation')
if '<span>مرکز بروزرسانی</span>' not in s:e.append('always-visible update label missing')
for p in ['panel/update.php','lib/InPlaceUpdater.php','lib/SecureUpdater.php','bin/UpdaterSchema.php']:
 if not (R/p).is_file():e.append('missing '+p)
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: update center is always visible and runtime files exist')
