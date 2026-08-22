#!/usr/bin/env python3
"""Regression checks for installer Telegram transport and secure local-font UI."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
installer = (ROOT / 'installer/index.php').read_text(encoding='utf-8')
security = (ROOT / 'lib/Security.php').read_text(encoding='utf-8')
errors: list[str] = []

def need(source: str, needle: str, label: str) -> None:
    if needle not in source:
        errors.append(f'missing {label}: {needle}')

def forbid(source: str, needle: str, label: str) -> None:
    if needle in source:
        errors.append(f'forbidden {label}: {needle}')

# Root-cause regression: multiple CURLOPT_RESOLVE rows for one host:port cause
# libcurl to discard A when AAAA is added. New curl receives one address list;
# old curl receives an IPv4-first single target.
need(security, "implode(',', $targets)", 'single multi-address resolve record')
need(security, "$versionNumber >= 0x073B00", 'curl 7.59 capability gate')
need(security, "$targets[0]", 'legacy curl IPv4-first fallback')
need(security, "$targets = array_merge($ipv4, $ipv6)", 'IPv4-first ordering')
forbid(security, "$resolve[] = $policy['host']", 'duplicate host:port resolve entries')
resolve_call = "curl_setopt($curl, CURLOPT_RESOLVE, [\n                $policy['host'] . ':' . $policy['port'] . ':' . $targetList,\n            ]);"
need(security, resolve_call, 'one CURLOPT_RESOLVE cache row')

# Installer transport must be TLS-verifying, bounded, retry only transient
# network failures, and return safe semantic diagnostics rather than secrets.
start = installer.find('function telegramApiRequest(')
end = installer.find('function isValidTelegramToken(', start)
helper = installer[start:end]
if start < 0 or end < 0:
    errors.append('Telegram helper block not found')
else:
    for needle, label in [
        ('CURLOPT_SSL_VERIFYPEER => true', 'TLS peer verification'),
        ('CURLOPT_SSL_VERIFYHOST => 2', 'TLS hostname verification'),
        ('CURLOPT_WRITEFUNCTION', 'bounded cURL writer'),
        ('$maxResponseBytes = 1048576', 'one MiB response cap'),
        ('$maxAttempts = max(1, min(3, $attemptLimit))', 'bounded retry'),
        ('redfox_apply_curl_url_policy', 'central outbound policy'),
        ('redfox_outbound_url_policy', 'stream outbound policy'),
        ("'follow_location' => 0", 'stream redirect denial'),
        ("'allow_self_signed' => false", 'stream self-signed denial'),
        ('JSON_BIGINT_AS_STRING', 'safe Telegram numeric decoding'),
        ("'failure' => 'telegram_api'", 'semantic API diagnostics'),
        ('REDFOX_TELEGRAM_API_BASE', 'validated alternate Telegram API base'),
    ]:
        need(helper, needle, label)
    forbid(helper, 'CURLOPT_RETURNTRANSFER => true', 'unbounded in-memory cURL response')
    forbid(helper, 'curl_error(', 'raw cURL error reflection')
    need(helper, "'upstream_description' => $upstreamDescription", 'internal-only Telegram description classification input')
    forbid(helper, "escapeHtml($decoded['description']", 'raw Telegram description HTML reflection')
    forbid(helper, "error_log($decoded['description']", 'raw Telegram description logging')

for needle, label in [
    ('function telegramInstallerCurlFailure', 'transport error classifier'),
    ('function telegramInstallerErrorMessage', 'Persian actionable error mapping'),
    ('TG-DNS', 'DNS diagnostic code'),
    ('TG-CONNECT', 'connection diagnostic code'),
    ('TG-TIMEOUT', 'timeout diagnostic code'),
    ('TG-TLS', 'TLS diagnostic code'),
    ('TG-AUTH', 'invalid-token diagnostic code'),
]:
    need(installer, needle, label)
forbid(installer, 'توکن ربات را بررسی کنید. <i>عدم توانایی دریافت جزئیات ربات.</i>', 'old ambiguous Telegram error')
need(installer, "$welcomeResult = telegramApiRequest($tgBotToken, 'sendMessage'", 'verified administrator welcome delivery')
need(installer, "if (empty($welcomeResult['ok']))", 'fail-closed administrator credential delivery')

# Token and admin validators are future-compatible but bounded.
need(installer, r"/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/", 'bounded Telegram token validator')
need(installer, r"/^[1-9]\d{4,19}$/", 'bounded positive Telegram user id')
clientlog = (ROOT / 'api/clientlog.php').read_text(encoding='utf-8')
need(clientlog, r"\d{6,20}:[A-Za-z0-9_-]{20,100}", 'future-compatible bot-token redaction')
need(clientlog, r"(?![A-Za-z0-9_-])", 'redaction boundary covering trailing dash/underscore')
logger = (ROOT / 'api/lib/Logger.php').read_text(encoding='utf-8')
need(logger, r"\d{6,20}:[A-Za-z0-9_-]{20,100}", 'logger bot-token redaction parity')
token_re = re.compile(r'^\d{6,20}:[A-Za-z0-9_-]{20,100}$')
for sample in [
    '123456:' + 'A' * 20,
    '123456789:' + 'Ab_c-' * 7,
    '12345678901234567890:' + 'z' * 100,
]:
    if not token_re.fullmatch(sample):
        errors.append('valid bounded Telegram token rejected by test contract')
for sample in [
    '12345:' + 'A' * 35,
    '123456:' + 'A' * 19,
    '123456:' + 'A' * 101,
    '123456:' + 'A' * 34 + '/',
]:
    if token_re.fullmatch(sample):
        errors.append('invalid Telegram token accepted by test contract')

# UI is self-contained and does not leak posted bot/database credentials back
# into the response DOM.
forbidden_external = ['fonts.googleapis.com', 'fonts.gstatic.com', 'AradMediumDots', 'JetBrains Mono']
for item in forbidden_external:
    forbid(installer, item, 'external/legacy installer typography')
need(installer, "font-family:Vazirmatn", 'Vazirmatn authorization UI')
need(installer, "font-family: 'Vazirmatn'", 'Vazirmatn wizard UI')
need(installer, 'class="auth-wrap"', 'professional authorization layout')
need(installer, 'class="reveal"', 'accessible token visibility control')
need(installer, 'prefers-reduced-motion', 'reduced-motion accessibility')
need(installer, "dirname($documentRootReal) . '/.redfox-install-token-'", 'subdirectory token stored above web root')
need(installer, 'htmlspecialchars(basename($tokenFile)', 'safe token filename guidance')
need(installer, 'type="password" id="tg_bot_token"', 'masked Telegram token field')
need(installer, 'type="password" id="database_password"', 'masked database password field')
forbid(installer, "escapeHtml($uPOST['tg_bot_token']", 'posted bot token reflection')
forbid(installer, "escapeHtml($uPOST['database_password']", 'posted database password reflection')
need(installer, "$uPOST['database_password'] = $_POST['database_password']", 'exact database password preservation')
need(installer, r"preg_match('/[\x00\r\n]/', $databasePassword)", 'database credential control-character rejection')

font_dir = ROOT / 'installer/fonts/vazirmatn'
for weight in (400, 500, 600, 700):
    for subset in ('arabic', 'latin'):
        font = font_dir / f'vazirmatn-{subset}-{weight}-normal.woff2'
        if not font.is_file() or font.stat().st_size < 1000:
            errors.append(f'missing/empty local font: {font.relative_to(ROOT)}')
        elif font.read_bytes()[:4] != b'wOF2':
            errors.append(f'invalid WOFF2 signature: {font.relative_to(ROOT)}')
license_file = font_dir / 'LICENSE.txt'
if not license_file.is_file() or 'SIL OPEN FONT LICENSE Version 1.1' not in license_file.read_text(encoding='utf-8'):
    errors.append('Vazirmatn OFL license missing')
manifest = (ROOT / 'release-required-files.json').read_text(encoding='utf-8')
for required in ['migrations/053_integration_api_idempotency.sql', 'migrations/054_processed_update_lifecycle.sql',
                 'migrations/055_webhook_recovery_status.sql', 'migrations/056_integrity_unique_constraints.sql',
                 'lib/HostingSecrets.php',
                 'lib/TelegramWebhook.php', 'panel/bot_settings.php',
                 're/rx/admin/compiled.php', 're/rx/keyboard/compiled.php',
                 'composer.json', 'composer.lock', 'vendor/autoload.php',
                 'vendor/composer/installed.json', 'vendor/paragonie/sodium_compat/autoload.php',
                 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
                 'vendor/endroid/qr-code/src/QrCode.php',
                 'installer/fonts/vazirmatn/LICENSE.txt']:
    need(manifest, required, 'release required-file gate')
for font in font_dir.glob('*.woff2'):
    need(manifest, font.relative_to(ROOT).as_posix(), 'required local font gate')
for dependency in ['composer.json', 'composer.lock', 'vendor/autoload.php',
                   'vendor/composer/installed.json', 'vendor/paragonie/sodium_compat/autoload.php',
                   'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
                   'vendor/endroid/qr-code/src/QrCode.php']:
    need(installer, dependency, 'installer dependency fallback gate')

# Admin provisioning must reuse the already authenticated PDO connection; no
# second, hard-coded localhost mysqli connection is allowed.
need(installer, 'function ensureAdminRecord(PDO $pdo, string $adminNumber): string', 'PDO admin provisioning')
need(installer, 'ensureAdminRecord($pdo, $tgAdminId)', 'PDO admin provisioning call')
forbid(installer, "new mysqli('localhost'", 'hard-coded admin database host')

if errors:
    print('\n'.join('FAIL: ' + error for error in errors))
    raise SystemExit(1)
print('OK: installer IPv4/IPv6 fallback, Telegram diagnostics, local Vazirmatn UI and secret handling passed')
