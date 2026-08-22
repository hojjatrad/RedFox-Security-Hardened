#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('lib/MigrationRunner.php','$statement->fetchAll(PDO::FETCH_NUM)')
need('lib/MigrationRunner.php','$statement->nextRowset()')
need('lib/MigrationRunner.php','$statement->closeCursor()')
need('installer/index.php','PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true')
need('migrations/015_installer_database_repair.sql','SELECT 1')
need('version','2.4.12-redfox-dedicated-template-editor')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: installer existing-database cursor repair assertions passed')
