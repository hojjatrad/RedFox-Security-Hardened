#!/usr/bin/env python3
"""Fail-closed Red Fox release builder: validates dependencies before ZIP creation."""
from pathlib import Path
import hashlib, json, os, re, shutil, subprocess, sys, zipfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'version').read_text().strip()
OUT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else ROOT.parent / f'RedFox-{VERSION}-Full.zip'
errors=[]

def fail(msg): errors.append(msg)

def required_files():
    try: data=json.loads((ROOT/'release-required-files.json').read_text())
    except Exception as e: fail(f'required manifest invalid: {e}'); return []
    if not isinstance(data,list) or not data: fail('required manifest empty'); return []
    return data

required=required_files()
for rel in required:
    p=ROOT/rel
    if not p.is_file() or p.stat().st_size==0: fail(f'missing/empty required file: {rel}')

# A release must be reproducible and its bundled production dependencies must
# exactly match composer.lock. Shared hosts may not have Composer available.
try:
    lock_data=json.loads((ROOT/'composer.lock').read_text())
    installed_data=json.loads((ROOT/'vendor/composer/installed.json').read_text())
    locked={p['name']:p['version'] for p in lock_data.get('packages',[]) if isinstance(p,dict) and 'name' in p and 'version' in p}
    installed={p['name']:p['version'] for p in installed_data.get('packages',[]) if isinstance(p,dict) and 'name' in p and 'version' in p}
    if not locked: fail('composer.lock has no production packages')
    for name,version in locked.items():
        if installed.get(name)!=version: fail(f'bundled dependency mismatch: {name} locked={version} installed={installed.get(name)}')
    unexpected=sorted(set(installed)-set(locked))
    if unexpected: fail('bundled dependencies absent from lock: '+','.join(unexpected))
except Exception as e:
    fail(f'Composer lock/vendor metadata invalid: {e}')

# No reference may remain to the removed optional helper that caused production failure.
for p in ROOT.rglob('*.php'):
    if 'vendor' in p.parts: continue
    text=p.read_text(errors='ignore')
    if 'ResellerBotSynchronizer.php' in text: fail(f'forbidden external installer dependency: {p.relative_to(ROOT)}')

# Compiled files must exactly correspond to their source manifests.
def compile_expected(folder,parts):
    out=['<?php\n/** Generated from manifest.php at release build time. Do not edit directly. */\n']
    for name in parts:
        s=(folder/name).read_text()
        if s.startswith('<?php'): s=s[5:]
        out += [f'\n/* ---- {name} ---- */\n',s.lstrip('\n')]
    return ''.join(out)
compiled_sets=[
 ('re/rx/index',['bootstrap.php','user_flow.php','panel_dispatch.php','finalize.php']),
 ('re/rx/function',['bootstrap.php','database_helpers_1.php','database_helpers_2.php','bot_api_helpers.php','business_logic_1.php','business_logic_2.php','business_logic_3.php','business_logic_4.php','business_logic_5.php','crypto_helpers.php']),
 ('re/rx/admin',['bootstrap_1.php','bootstrap_2.php','finance.php','settings.php','maintenance.php']),
 ('re/rx/keyboard',['layouts_1.php','layouts_2.php','admin_panels_1.php','admin_panels_2.php'])]
for rel,parts in compiled_sets:
    folder=ROOT/rel; expected=compile_expected(folder,parts); target=folder/'compiled.php'
    if not target.exists() or target.read_text()!=expected: fail(f'compiled/source mismatch: {target.relative_to(ROOT)}')

for name in ['index.php','func.php','keyboard.php','admin.php','botapi.php']:
    a=ROOT/'vpnbot/Default'/name; b=ROOT/'vpnbot/update'/name
    if not a.is_file() or not b.is_file() or a.read_bytes()!=b.read_bytes(): fail(f'reseller template mismatch: {name}')

# Run all static suites. If PHP exists, lint every project PHP file too.
for test in sorted((ROOT/'tests').glob('*_test.py')):
    r=subprocess.run([sys.executable,str(test)],cwd=ROOT,text=True,capture_output=True)
    if r.returncode: fail(f'{test.name}: {r.stdout}{r.stderr}')
php=shutil.which('php')
if php:
    for p in ROOT.rglob('*.php'):
        if 'vendor' in p.parts: continue
        r=subprocess.run([php,'-l',str(p)],text=True,capture_output=True)
        if r.returncode: fail(f'PHP lint {p.relative_to(ROOT)}: {r.stdout}{r.stderr}')

if errors:
    print('\n'.join('FAIL '+e for e in errors)); sys.exit(1)

exclude_parts={'.git','__pycache__','logs'}
exclude_prefixes=('storage/update-backups/','storage/migration-backups/','updates/archive/')
exclude_exact={'storage/maintenance.flag','storage/migration-progress.json','storage/secure.env.php'}
def include(rel):
    s=rel.as_posix()
    if any(x in rel.parts for x in exclude_parts) or s in exclude_exact or s.startswith(exclude_prefixes): return False
    if rel.suffix in {'.pyc','.log','.rxb'}: return False
    if (s.startswith('Upload/') or s.startswith('updates/')) and rel.suffix.lower()=='.zip': return False
    return True

OUT.parent.mkdir(parents=True,exist_ok=True)
if OUT.exists(): OUT.unlink()
with zipfile.ZipFile(OUT,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
    for p in sorted(ROOT.rglob('*')):
        if p.is_file():
            rel=p.relative_to(ROOT)
            if include(rel): z.write(p,rel.as_posix())
with zipfile.ZipFile(OUT) as z:
    names=set(z.namelist()); bad=z.testzip()
    if bad: fail(f'corrupt ZIP entry: {bad}')
    for rel in required:
        if rel not in names: fail(f'required file absent from ZIP: {rel}')
    if len(names)<1000: fail(f'suspiciously small release: only {len(names)} files')
if errors:
    OUT.unlink(missing_ok=True); print('\n'.join('FAIL '+e for e in errors)); sys.exit(1)
sha=hashlib.sha256(OUT.read_bytes()).hexdigest()
sha_file=OUT.with_suffix(OUT.suffix+'.sha256')
sha_file.write_text(f'{sha}  {OUT.name}\n')
print(f'OK {OUT}\nFILES {len(names)}\nSHA256 {sha}')
