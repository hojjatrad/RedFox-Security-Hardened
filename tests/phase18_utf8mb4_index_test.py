#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('migrations/006_integrity_indexes.sql','`id_order`(100)')
need('migrations/006_integrity_indexes.sql','`payment_Status`(20)')
need('migrations/006_integrity_indexes.sql','`id_user`(75)')
need('migrations/002_reseller_portal.sql','`agent`(100)')
need('lib/MigrationRunner.php','f2e41935306f8424c80639cdd5ac7af65e36ab9160ff8d4cae3e250389e5a342')
need('lib/MigrationRunner.php','25ba3c3c340a192c35bc36258cd5e43ce4ac4c2900215da7ff80bfb5a351b264')
need('lib/MigrationRunner.php','خطا در ')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: utf8mb4 safe index prefixes and legacy checksums passed')
