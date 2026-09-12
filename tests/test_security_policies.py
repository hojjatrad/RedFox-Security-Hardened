#!/usr/bin/env python3
"""Release-blocking static security contracts that do not require PHP/MySQL."""
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1];e=[]
def text(p):return (R/p).read_text(errors='ignore')
def need(p,x):
 if x not in text(p):e.append(f'{p}: missing {x}')
def forbid(p,x):
 if x in text(p):e.append(f'{p}: forbidden {x}')
# Central session, CSRF, public-error and redacted logging policy.
for x in ['function redfox_secure_session_start','session.use_strict_mode','cookie_httponly','cookie_samesite','function redfox_enforce_csrf','function redfox_exception_fingerprint','function redfox_public_exception','function redfox_remote_error_summary']:
 need('lib/Security.php',x)
# Outbound requests are centrally restricted, DNS-pinned and bounded.
for x in ['function redfox_outbound_url_policy','FILTER_FLAG_NO_PRIV_RANGE','FILTER_FLAG_NO_RES_RANGE','dns_get_record','function redfox_apply_curl_url_policy','CURLOPT_RESOLVE','CURLOPT_PROTOCOLS','CURLOPT_SSL_VERIFYPEER, true','function redfox_fetch_public_https','response_too_large','redfox-policy-block.invalid']:
 need('lib/Security.php',x)
for x in ['redfox_apply_curl_url_policy($ch, $url, false, false)','CURLOPT_WRITEFUNCTION','2 * 1024 * 1024']:
 need('re/rx/function/database_helpers_2.php',x)
for p in ['panel/user_service.php','panel/users.php','cron_campaigns.php']:
 need(p,'redfox_apply_panel_curl_url_policy')
for p in ['Marzban.php','marzneshin.php','hiddify.php','alireza.php','alireza_single.php','x-ui_single.php','s_ui.php','mikrotik.php','ibsng/Modules/IBSng.php']:
 need(p,'redfox_apply_panel_curl_url_policy')
for p in ['api/product.php','api/panels.php','api/users.php','api/handlers/ServiceActionHandler.php']:
 need(p,'redfox_remote_error_summary')
for x in ['redfox_fetch_public_https($fileUrl, 5242880, 20)','imagecreatefromstring','imagejpeg','16000000']:
 need('re/rx/admin/settings.php',x)
forbid('re/rx/admin/settings.php','file_get_contents($fileUrl)')
need('re/rx/function/database_helpers_1.php','redfox_fetch_public_https($endpoint, 1048576, 5')
# Integration endpoints share one fail-closed DB secret and bounded methods/bodies.
for x in ['function rx_require_integration_auth','SELECT integration_secret_token FROM setting','hash_equals($stored, $provided)']:
 need('api/lib/IntegrationAuth.php',x)
for p in ['api/index.php','webhooks.php','payment/card.php']:
 need(p,'rx_require_integration_auth($pdo)')
for p in ['webhooks.php','payment/card.php']:
 need(p,"REQUEST_METHOD")
 need(p,"!== 'POST'")
# Telegram webhook uses secret_token and database-only deduplication.
for x in ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN','hash_equals($rxStoredSecret, $rxIncomingSecret)','function isDuplicateUpdate(','INSERT IGNORE INTO processed_updates','redfox_update_claim_unavailable']:
 need('botapi.php',x)
# Updater is signed-only and mandatory-backup.
for x in ['sodium_crypto_sign_verify_detached','update-manifest.json','update-signature.txt']:
 need('lib/SecureUpdater.php',x)
for x in ['inspectAny','inspectUnsigned',"'signed'=>false"]:forbid('lib/SecureUpdater.php',x)
for x in ['RedFoxDatabaseBackup::create','RedFoxDatabaseBackup::restore','if(!$createDatabaseBackup)throw']:
 need('lib/InPlaceUpdater.php',x)
# Root web-server policy denies internal, upload and secret-bearing paths.
ht=text('.htaccess')
for x in ['Options -Indexes','FilesMatch','RewriteRule','bin|lib|vendor|migrations|re/rx|updates|Upload|storage|logs','[F,L,NC]']:
 if x not in ht:e.append('.htaccess: missing '+x)
need('Upload/.htaccess','Require all denied')
# Global high-signal insecure transport/debug patterns are release blockers.
php_files=[p for p in R.rglob('*.php') if 'vendor' not in p.parts and p.name!='compiled.php']
patterns={
 'TLS peer verification disabled':re.compile(r'CURLOPT_SSL_VERIFYPEER\s*(?:=>|,)\s*false',re.I),
 'TLS host verification disabled':re.compile(r'CURLOPT_SSL_VERIFYHOST\s*(?:=>|,)\s*[01](?:\D|$)',re.I),
 'phpinfo exposed':re.compile(r'\bphpinfo\s*\('),
 'raw var_dump':re.compile(r'\bvar_dump\s*\('),
}
for p in php_files:
 s=p.read_text(errors='ignore')
 for label,pat in patterns.items():
  if pat.search(s):e.append(f'{p.relative_to(R)}: {label}')
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: central auth/session/CSRF, webhook dedupe, signed updater, web denial and TLS/debug contracts passed')
