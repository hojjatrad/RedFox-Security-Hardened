#!/usr/bin/env python3
"""Regression contract for recoverable and diagnosable Telegram webhook setup."""
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parents[1]
installer = (ROOT / 'installer/index.php').read_text(encoding='utf-8')
helper = (ROOT / 'lib/TelegramWebhook.php').read_text(encoding='utf-8')
panel = (ROOT / 'panel/bot_settings.php').read_text(encoding='utf-8')
security = (ROOT / 'lib/Security.php').read_text(encoding='utf-8')
hosting = (ROOT / 'lib/HostingSecrets.php').read_text(encoding='utf-8')
endpoint = (ROOT / 'index.php').read_text(encoding='utf-8')
migration = (ROOT / 'migrations/055_webhook_recovery_status.sql').read_text(encoding='utf-8')
manifest = json.loads((ROOT / 'release-required-files.json').read_text(encoding='utf-8'))
errors: list[str] = []

def need(source: str, needle: str, label: str) -> None:
    if needle not in source:
        errors.append(f'missing {label}: {needle}')

def forbid(source: str, needle: str, label: str) -> None:
    if needle in source:
        errors.append(f'forbidden {label}: {needle}')

# Strict allow-list classifier covers the actionable setWebhook failure classes.
for code in [
    'TG-WH-DNS', 'TG-WH-TLS', 'TG-WH-PORT', 'TG-WH-IP',
    'TG-WH-CONNECT', 'TG-WH-URL', 'TG-WH-SECRET', 'TG-WH-RATE',
    'TG-WH-UPSTREAM', 'TG-WH-AUTH', 'TG-WH-TRANSPORT', 'TG-WH-HTTP',
    'TG-WH-REDIRECT', 'TG-WH-UNKNOWN',
]:
    need(helper, code, f'webhook diagnostic {code}')
for pattern in [
    'failed to resolve host', 'certificate', 'connection refused',
    'only ports', 'secret[_ -]?token', 'too many requests',
]:
    need(helper, pattern, f'upstream classifier pattern {pattern}')
need(helper, "substr(hash('sha256'", 'description fingerprinting')
need(helper, 'redfox_telegram_webhook_error_text', 'safe actionable rendering')
forbid(helper, "'description' => $description", 'raw upstream description in public result')
forbid(helper, 'return $description;', 'raw upstream description return')

# URL preflight is public-only, Telegram-port aware, TLS-verifying and bounded.
need(helper, 'function redfox_validate_telegram_webhook_url', 'Telegram URL validator')
need(helper, '[80, 88, 443, 8443]', 'Telegram webhook port allow-list')
need(helper, "str_ends_with($path, '/index.php')", 'endpoint path contract')
need(helper, 'redfox_outbound_url_policy($url, false, false)', 'public DNS/IP policy')
need(helper, 'function redfox_probe_telegram_webhook_endpoint', 'endpoint TLS probe')
need(helper, 'function redfox_apply_telegram_webhook', 'shared bounded webhook coordinator')
need(helper, "$safeCall('getWebhookInfo', [])", 'shared independent verification')
need(helper, "usleep(250000)", 'bounded transient retry delay')
need(helper, "[200, 204, 400, 401, 405, 415, 422]", 'strict healthy probe status allow-list')
need(helper, 'CURLOPT_NOBODY => true', 'non-mutating endpoint probe')
need(helper, 'CURLOPT_SSL_VERIFYPEER => true', 'probe peer verification')
need(helper, 'CURLOPT_SSL_VERIFYHOST => 2', 'probe hostname verification')
forbid(helper, 'CURLOPT_FOLLOWLOCATION => true', 'probe redirects')

# Installer retries transient transport in a bounded way, sends one minimal
# fallback, verifies independently, and defers environmental failures.
need(installer, 'function telegramInstallerConfigureWebhook', 'installer webhook coordinator')
need(installer, "'drop_pending_updates' => 'false'", 'primary setWebhook request')
need(installer, "telegramApiRequest($token, 'setWebhook'", 'setWebhook operation')
need(installer, "telegramApiRequest($token, 'getWebhookInfo', [], 1)", 'independent confirmation')
need(installer, '$accepted || $verified', 'lost-response recovery')
need(installer, "$webhookPending = true", 'recoverable pending state')
need(installer, "redfox_webhook_status_write($pdo, 'pending'", 'durable pending state')
need(installer, "redfox_webhook_status_write($pdo, 'active'", 'durable active state')
need(installer, 'هستهٔ نصب کامل شد؛ Webhook در وضعیت نیازمند تعمیر است', 'deferred installer message')
need(installer, 'panel/bot_settings.php', 'installer repair route')
forbid(installer, "throw new RuntimeException('Telegram setWebhook failed", 'fatal environmental setWebhook')
need(installer, "'webhook_status' => $webhookPending ? 'pending' : 'active'", 'install lock webhook state')
need(installer, 'scheduleInstallerSelfDelete(__DIR__)', 'installer cleanup after deferred completion')
need(installer, 'rx_load_hosting_secrets($root)', 'symlink-safe installer secret loader')
need(installer, 'rx_update_hosting_secrets($secrets, $root)', 'central atomic installer secret writer')
forbid(installer, 'file_put_contents($temporary, $contents', 'duplicate installer secret writer')
need(installer, "preg_match('#^(.*?)/installer(?:/index\\.php)?/?$#i", 'robust subdirectory derivation')
forbid(installer, "?>\n/' . $path;", 'duplicate emitted PHP tail')

# Main endpoint remains POST-only and authenticates Telegram's secret header.
need(endpoint, "REQUEST_METHOD", 'webhook method gate')
need(endpoint, "header('Allow: POST')", 'POST-only response')
need(endpoint, 'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN', 'Telegram secret header')
need(endpoint, 'hash_equals($storedSecret, $incomingSecret)', 'constant-time secret match')
need(endpoint, '> 1048576', 'one MiB webhook payload limit')

# Repair UI must be independent from changing token/domain, CSRF protected,
# and use the private environment writer rather than source-code mutation.
need(panel, "value=\"repair_webhook\"", 'independent repair button')
need(panel, "elseif ($action === 'repair_webhook')", 'repair action')
need(panel, 'redfox_panel_webhook_apply', 'shared panel setup sequence')
need(panel, 'redfox_apply_telegram_webhook', 'panel uses central coordinator')
need(helper, 'redfox_validate_telegram_webhook_url', 'central panel preflight')
need(helper, "$safeCall('getWebhookInfo', [])", 'central panel verification')
need(panel, 'rx_update_hosting_secrets', 'secure operational settings writer')
need(panel, 'storage/secure.env.php', 'secure storage disclosure')
if panel.count('<form method="post"') != panel.count('redfox_csrf_field()'):
    errors.append('every bot settings POST form must carry a CSRF token')
forbid(panel, 'preg_replace_callback(', 'config.php source rewriting')
forbid(panel, 'file_put_contents($configFile', 'config.php credential storage')
forbid(panel, '$flash .= $lastError', 'raw getWebhookInfo error reflection')

# Subfolder deployment preserves the application base path, while browser
# same-origin checks intentionally strip it to scheme+authority.
need(security, 'host[:port][/install/path]', 'subfolder base-address contract')
need(security, "$path = '/' . implode('/',", 'canonical subfolder path')
need(security, 'function redfox_configured_base_url', 'path-preserving application URL helper')
need(security, "$baseUrl = redfox_configured_base_url()", 'origin derives from canonical base')
need(security, "return 'https://' . $host . $port", 'path-free configured origin')

# Secret file updates are allow-listed, symlink-safe, atomic, commonly locked
# and 0600. Correctness may not depend on putenv or optional filesystem calls
# that shared hosting providers can disable.
need(hosting, 'function rx_update_hosting_secrets', 'central secret writer')
need(hosting, 'is_link($storage) || !is_dir($storage)', 'storage symlink/type rejection')
need(hosting, 'is_link($file) || !is_file($file)', 'secret file symlink/type rejection')
need(hosting, "fopen($candidate, 'x+b')", 'exclusive temp creation')
need(hosting, 'flock($lock, LOCK_EX)', 'common secret writer lock')
need(hosting, 'fchmod($temp, 0600)', 'secret temp permissions')
need(hosting, 'rename($tempPath, $file)', 'atomic secret replacement')
need(hosting, 'umask(0077)', 'secure creation mode independent of chmod')
need(hosting, 'function rx_hosting_secret_diagnostic', 'safe actionable filesystem diagnostics')
need(hosting, 'RXSEC-TEMP-RENAME', 'stable atomic replacement diagnostic')
forbid(hosting, "putenv($key", 'process-environment mutation dependency')
forbid(hosting, 'REDFOX_BOT_TOKEN' + "' => $", 'hard-coded secret value')
hosting_panel = (ROOT / 'panel/hosting_setup.php').read_text(encoding='utf-8')
need(hosting_panel, 'redfox_enforce_csrf()', 'hosting settings CSRF enforcement')
need(hosting_panel, 'rx_load_hosting_secrets($root)', 'symlink-safe hosting settings loader')
need(hosting_panel, 'rx_update_hosting_secrets($data,$root)', 'central hosting settings writer')
if hosting_panel.count('<form method="post"') != hosting_panel.count('redfox_csrf_field()'):
    errors.append('every hosting settings POST form must carry a CSRF token')
forbid(hosting_panel, '(require$file)', 'direct potentially-linked secret include')

# Upgrade/fresh-install schema records only allow-listed status and error codes.
for column in ['webhook_setup_status', 'webhook_last_error_code', 'webhook_last_attempt_at']:
    need(migration, column, f'migration column {column}')
    need((ROOT / 'table.php').read_text(encoding='utf-8'), column, f'fresh schema field {column}')
need(migration, 'information_schema.COLUMNS', 'idempotent migration guards')
table_source = (ROOT / 'table.php').read_text(encoding='utf-8')
forbid(table_source, "telegram('setwebhook'", 'schema-time webhook side effect')
forbid(table_source, 'setwebhook FAILED:', 'raw schema-time Telegram response logging')
for related in ['api/users.php', 'lib/ResellerBotManager.php', 'panel/agents.php']:
    related_source = (ROOT / related).read_text(encoding='utf-8')
    need(related_source, 'redfox_apply_telegram_webhook', f'shared webhook coordinator in {related}')
    forbid(related_source, "telegram('setWebhook'", f'uncoordinated setWebhook in {related}')
need((ROOT / 'cron/cron.php').read_text(encoding='utf-8'), "$basePath = rtrim((string)($parts['path'] ?? ''), '/')", 'subfolder-aware cron orchestrator')
need((ROOT / 'cron/diag.php').read_text(encoding='utf-8'), "$basePart = rtrim((string)($domainParts['path'] ?? ''), '/')", 'subfolder-aware cron diagnostic')
for required in [
    'lib/HostingSecrets.php', 'lib/TelegramWebhook.php', 'panel/bot_settings.php',
    'migrations/055_webhook_recovery_status.sql',
]:
    if required not in manifest:
        errors.append(f'missing release manifest entry: {required}')
    elif not (ROOT / required).is_file():
        errors.append(f'manifest file does not exist: {required}')

if errors:
    print('\n'.join('FAIL: ' + error for error in errors))
    raise SystemExit(1)
print('OK: recoverable webhook setup, safe diagnostics, subfolder support and panel repair contract passed')
