#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('bin/InstallerDatabaseRepair.php','MAX(CHAR_LENGTH')
need('bin/InstallerDatabaseRepair.php','CHARACTER_MAXIMUM_LENGTH')
need('installer/index.php','RedFoxInstallerDatabaseRepair::repair')
need('installer/index.php',"$installStage = 'ساخت و تکمیل جداول پایه'")
need('table.php','id_text varchar(191) PRIMARY KEY')
need('table.php','Namevalue varchar(191) PRIMARY KEY')
need('table.php','idx_pr_source_user (source, id_user(100))')
need('table.php','uq_nm_stock_product_map (source_codepanel(63), stock_codepanel(63), codeproduct(63))')
need('migrations/003_operations_and_migrations.sql','`invoice_id`(100)')
need('migrations/010_final_features_no_runtime_ddl.sql','CHARACTER SET ascii COLLATE ascii_bin')
need('migrations/010_final_features_no_runtime_ddl.sql','`codepanel`(63)')
need('lib/MigrationRunner.php','ee47b4b466af6e683c9c00f0f7e6abf9712adb393fc853399df9b6aa8b08b951')
if e: print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: full primary/unique/index compatibility audit assertions passed')
