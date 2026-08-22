#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
for x in ['CURLOPT_SSL_VERIFYPEER => true','CURLOPT_SSL_VERIFYHOST => 2','CURLOPT_FOLLOWLOCATION => false','CURLOPT_PROTOCOLS => CURLPROTO_HTTPS',"($parts['scheme'] ?? '')) !== 'https'",'Untrusted update download URL']:
 need('lib/UpdateSources.php',x)
need('lib/UpdateSources.php',"if ($unsigned) throw new RuntimeException('Unsigned update packages are forbidden')")
need('lib/UpdateSources.php','RedFoxSecureUpdater::inspect($file, $current)')
for x in ['inspectAny','inspectUnsigned','CURLOPT_SSL_VERIFYPEER => false','CURLOPT_SSL_VERIFYHOST => 0']:forbid('lib/UpdateSources.php',x)
need('lib/SecureUpdater.php','sodium_crypto_sign_verify_detached')
need('lib/SecureUpdater.php','RedFoxUnsignedUpdatePackage')
for x in ['inspectAny','inspectUnsigned',"'signed'=>false"]:forbid('lib/SecureUpdater.php',x)
need('panel/update.php',"RedFoxUpdateSources::save($pdo,$githubInput,'*.zip',false,true)")
need('panel/update.php','RedFoxSecureUpdater::inspect($target,$current)')
need('panel/update.php','فقط بسته دارای manifest و امضای معتبر Ed25519 پذیرفته می‌شود.')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: update sources enforce TLS, per-hop host allowlist and Ed25519 signed-only packages')
