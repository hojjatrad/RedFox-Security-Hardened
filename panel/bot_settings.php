<?php
declare(strict_types=1);
/** Main bot credentials and recoverable Telegram webhook management. */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/HostingSecrets.php';
require_once __DIR__ . '/../lib/TelegramWebhook.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$adminRow = null;
if (!empty($_SESSION['user']) && $pdo instanceof PDO) {
    $query = $pdo->prepare('SELECT * FROM admin WHERE username = :username LIMIT 1');
    $query->execute([':username' => (string)$_SESSION['user']]);
    $adminRow = $query->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (($adminRow['rule'] ?? '') === 'administrator');
$flash = '';
$flashType = 'info';
$currentToken = (string)($GLOBALS['APIKEY'] ?? '');
$currentAdmin = (string)($GLOBALS['adminnumber'] ?? '');
$currentDomain = (string)($GLOBALS['domainhosts'] ?? '');
$currentBotUser = ltrim((string)($GLOBALS['usernamebot'] ?? ''), '@');

function redfox_panel_webhook_apply(string $token, string $base, string $secret): array
{
    $url = 'https://' . rtrim($base, '/') . '/index.php';
    $result = redfox_apply_telegram_webhook(
        static function (string $method, array $parameters) use ($token): array {
            $response = telegram($method, $parameters, $token);
            return is_array($response) ? $response : ['ok' => false];
        },
        $url,
        $secret,
        true
    );
    if (!empty($result['ok'])) {
        return ['ok' => true, 'url' => $url, 'verified' => !empty($result['verified'])];
    }
    return ['ok' => false, 'url' => $url, 'error' => $result];
}

function redfox_panel_webhook_secret(PDO $pdo): string
{
    $secret = '';
    try {
        $secret = (string)$pdo->query('SELECT webhook_secret_token FROM setting LIMIT 1')->fetchColumn();
    } catch (Throwable $error) {
        redfox_log_exception($error, 'panel.bot_settings.secret.read');
    }
    if (preg_match('/^[A-Za-z0-9_-]{32,64}$/', $secret)) return $secret;
    $secret = bin2hex(random_bytes(32));
    $statement = $pdo->prepare('UPDATE setting SET webhook_secret_token=?');
    $statement->execute([$secret]);
    return $secret;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_bot') {
            $submittedToken = trim((string)($_POST['bot_token'] ?? ''));
            $newToken = $submittedToken !== '' ? $submittedToken : $currentToken;
            $newAdmin = trim((string)($_POST['admin_id'] ?? '')) ?: $currentAdmin;
            $submittedDomain = trim((string)($_POST['domain'] ?? ''));
            $newDomain = redfox_normalize_domain($submittedDomain !== '' ? $submittedDomain : $currentDomain);
            $newBotUser = ltrim(trim((string)($_POST['bot_username'] ?? '')), '@') ?: $currentBotUser;

            if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/', $newToken)) {
                throw new InvalidArgumentException('فرمت توکن نامعتبر است.');
            }
            if (!preg_match('/^-?\d{3,20}$/', $newAdmin)) {
                throw new InvalidArgumentException('شناسهٔ ادمین نامعتبر است.');
            }
            if ($newDomain === '') {
                throw new InvalidArgumentException('آدرس پایهٔ HTTPS نامعتبر است.');
            }
            if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $newBotUser)) {
                throw new InvalidArgumentException('یوزرنیم ربات نامعتبر است.');
            }

            $tokenChanged = !hash_equals($currentToken, $newToken);
            $domainChanged = !hash_equals($currentDomain, $newDomain);
            $adminChanged = !hash_equals($currentAdmin, $newAdmin);
            $usernameChanged = !hash_equals($currentBotUser, $newBotUser);
            if (!$tokenChanged && !$domainChanged && !$adminChanged && !$usernameChanged) {
                $flash = 'تغییری اعمال نشد؛ برای ثبت دوباره Webhook از دکمهٔ «تعمیر Webhook» استفاده کنید.';
            } else {
                $me = telegram('getMe', [], $newToken);
                $remoteUsername = is_array($me) ? (string)($me['result']['username'] ?? '') : '';
                if (empty($me['ok']) || !preg_match('/^[A-Za-z0-9_]{5,32}$/', $remoteUsername)) {
                    throw new RuntimeException('توکن توسط Telegram تأیید نشد.');
                }
                if (strcasecmp($remoteUsername, $newBotUser) !== 0) {
                    throw new RuntimeException('یوزرنیم واردشده با ربات متعلق به این توکن مطابقت ندارد.');
                }

                rx_update_hosting_secrets([
                    'REDFOX_BOT_TOKEN' => $newToken,
                    'REDFOX_ADMIN_ID' => $newAdmin,
                    'REDFOX_DOMAIN' => $newDomain,
                    'REDFOX_BOT_USERNAME' => $remoteUsername,
                ], dirname(__DIR__));
                $currentToken = $newToken;
                $currentAdmin = $newAdmin;
                $currentDomain = $newDomain;
                $currentBotUser = $remoteUsername;
                $GLOBALS['APIKEY'] = $newToken;
                $GLOBALS['adminnumber'] = $newAdmin;
                $GLOBALS['domainhosts'] = $newDomain;
                $GLOBALS['usernamebot'] = $remoteUsername;

                $secret = redfox_panel_webhook_secret($pdo);
                $setup = redfox_panel_webhook_apply($newToken, $newDomain, $secret);
                if (!empty($setup['ok'])) {
                    redfox_webhook_status_write($pdo, 'active', '');
                    $flash = '✅ تنظیمات در storage/secure.env.php ذخیره و Webhook امن تأیید شد.';
                    $flashType = 'success';
                } else {
                    $error = (array)($setup['error'] ?? []);
                    $code = preg_match('/^TG-WH-[A-Z0-9_-]+$/', (string)($error['code'] ?? ''))
                        ? (string)$error['code'] : 'TG-WH-UNKNOWN';
                    redfox_webhook_status_write($pdo, 'pending', $code);
                    $flash = "⚠️ تنظیمات امن ذخیره شد، اما Webhook هنوز فعال نیست.\n" . redfox_telegram_webhook_error_text($error);
                    $flashType = 'error';
                }
            }
        } elseif ($action === 'repair_webhook') {
            if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/', $currentToken) || $currentDomain === '') {
                throw new RuntimeException('توکن یا آدرس پایه تنظیم نشده است.');
            }
            $secret = redfox_panel_webhook_secret($pdo);
            $setup = redfox_panel_webhook_apply($currentToken, $currentDomain, $secret);
            if (!empty($setup['ok'])) {
                redfox_webhook_status_write($pdo, 'active', '');
                $flash = "✅ Webhook امن دوباره ثبت و با getWebhookInfo تأیید شد.\nURL: " . (string)$setup['url'];
                $flashType = 'success';
            } else {
                $error = (array)($setup['error'] ?? []);
                $code = preg_match('/^TG-WH-[A-Z0-9_-]+$/', (string)($error['code'] ?? ''))
                    ? (string)$error['code'] : 'TG-WH-UNKNOWN';
                redfox_webhook_status_write($pdo, 'pending', $code);
                $flash = "❌ تعمیر Webhook کامل نشد.\n" . redfox_telegram_webhook_error_text($error);
                $flashType = 'error';
            }
        } elseif ($action === 'test_webhook') {
            if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/', $currentToken)) {
                throw new RuntimeException('توکن تنظیم‌شده معتبر نیست.');
            }
            $info = telegram('getWebhookInfo', [], $currentToken);
            if (!is_array($info) || empty($info['ok']) || !is_array($info['result'] ?? null)) {
                $error = redfox_telegram_webhook_error($info, is_array($info) ? (string)($info['failure'] ?? '') : 'transport');
                throw new RuntimeException(redfox_telegram_webhook_error_text($error));
            }
            $result = $info['result'];
            $reportedUrl = substr((string)($result['url'] ?? ''), 0, 500);
            $expectedUrl = 'https://' . rtrim($currentDomain, '/') . '/index.php';
            $lastError = trim((string)($result['last_error_message'] ?? ''));
            $urlMatches = $reportedUrl !== '' && hash_equals($expectedUrl, $reportedUrl);
            if ($reportedUrl === '') {
                redfox_webhook_status_write($pdo, 'disabled', 'TG-WH-NOT-SET');
            } elseif (!$urlMatches) {
                redfox_webhook_status_write($pdo, 'pending', 'TG-WH-MISMATCH');
            } elseif ($lastError !== '') {
                $deliveryError = redfox_telegram_webhook_error(['description' => $lastError]);
                redfox_webhook_status_write($pdo, 'pending', (string)$deliveryError['code']);
            } else {
                redfox_webhook_status_write($pdo, 'active', '');
            }
            $flash = "📡 وضعیت Webhook:\nURL ثبت‌شده: " . ($reportedUrl !== '' ? $reportedUrl : 'تنظیم نشده') .
                "\nتطبیق با نصب جاری: " . ($urlMatches ? 'بله ✅' : 'خیر ❌') .
                "\nپیام در صف: " . (int)($result['pending_update_count'] ?? 0) .
                "\nحداکثر اتصال: " . (int)($result['max_connections'] ?? 40);
            if ($lastError !== '') {
                $deliveryError = redfox_telegram_webhook_error(['description' => $lastError]);
                $flash .= "\nخطای تحویل اخیر: " . redfox_telegram_webhook_error_text($deliveryError);
            }
            $flashType = $urlMatches && $lastError === '' ? 'success' : 'error';
        } elseif ($action === 'delete_webhook') {
            if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/', $currentToken)) {
                throw new RuntimeException('توکن تنظیم‌شده معتبر نیست.');
            }
            $deleted = telegram('deleteWebhook', [], $currentToken);
            if (!is_array($deleted) || empty($deleted['ok'])) {
                $error = redfox_telegram_webhook_error($deleted, is_array($deleted) ? (string)($deleted['failure'] ?? '') : 'transport');
                throw new RuntimeException(redfox_telegram_webhook_error_text($error));
            }
            redfox_webhook_status_write($pdo, 'disabled', 'TG-WH-NOT-SET');
            $flash = '✅ Webhook حذف شد. هر زمان آماده بودید «تعمیر Webhook» را اجرا کنید.';
            $flashType = 'success';
        }
    } catch (Throwable $error) {
        redfox_log_exception($error, 'panel.bot_settings.' . preg_replace('/[^a-z_]/', '', $action));
        if ($error instanceof RedFoxHostingSecretException) {
            $diagnostic = rx_hosting_secret_diagnostic($error);
            $public = 'کد تشخیص امن: ' . (string)$diagnostic['code'] . ' — ' . (string)$diagnostic['message'];
        } else {
            $public = $error instanceof InvalidArgumentException || str_contains($error->getMessage(), 'TG-WH-')
                ? $error->getMessage()
                : redfox_public_exception($error, 'panel/bot_settings.php');
        }
        $flash = '❌ ' . $public;
        $flashType = 'error';
    }
}

$status = redfox_webhook_status_read($pdo);
$statusCode = (string)($status['webhook_last_error_code'] ?? '');
$statusName = (string)($status['webhook_setup_status'] ?? 'unknown');
$statusLabels = ['active' => 'فعال ✅', 'pending' => 'نیازمند تعمیر ⚠️', 'disabled' => 'حذف‌شده', 'unknown' => 'نامشخص'];
$lastAttempt = (int)($status['webhook_last_attempt_at'] ?? 0);
$tokenConfigured = $currentToken !== '';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تنظیمات ربات | رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:14px 18px;border-radius:12px;margin:0 0 16px;font-size:13px;line-height:1.9;white-space:pre-wrap}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-field{margin-bottom:16px}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin-bottom:5px;font-weight:600}.rx-field input{width:100%;max-width:650px;padding:11px 14px;border-radius:10px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;box-sizing:border-box;font-family:inherit}.rx-field input[type=text]{direction:ltr}.rx-field .hint{font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.7}.rx-warn,.rx-info{border-radius:12px;padding:16px;font-size:13px;color:var(--text-main);line-height:2;margin-bottom:16px}.rx-warn{background:var(--color-warning-soft)}.rx-info{background:var(--accent-soft)}.rx-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.rx-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:12px 0}.rx-status>div{background:var(--surface-2);border:1px solid var(--border-mid);border-radius:10px;padding:12px}.rx-status small{display:block;color:var(--text-muted);margin-bottom:4px}.rx-status code{direction:ltr;display:inline-block}
</style>
</head>
<body><section id="container"><?php include 'header.php'; ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🤖 تنظیمات ربات اصلی</h1><div class="page-head__sub">مدیریت امن Secretها و تشخیص/تعمیر مستقل Webhook</div></div>
<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="rx-warn">🔐 توکن و تنظیمات عملیاتی در <code>storage/secure.env.php</code> با Permission محدود ذخیره می‌شوند؛ هیچ Secretی در HTML یا <code>config.php</code> بازنویسی نمی‌شود.</div>
<?php if ($isMainAdmin): ?>
<div class="card"><div class="card__head"><h2 class="card__title">🔑 تنظیمات اصلی</h2></div>
<form method="post"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="save_bot">
<div class="rx-field"><label>توکن ربات</label><input type="password" name="bot_token" autocomplete="new-password" placeholder="برای حفظ مقدار فعلی خالی بگذارید" style="direction:ltr"><div class="hint">وضعیت: <?= $tokenConfigured ? 'تنظیم شده ✅' : 'تنظیم نشده' ?></div></div>
<div class="rx-field"><label>آیدی عددی ادمین اصلی</label><input type="text" name="admin_id" value="<?= htmlspecialchars($currentAdmin, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="rx-field"><label>آدرس پایهٔ نصب</label><input type="text" name="domain" value="<?= htmlspecialchars($currentDomain, ENT_QUOTES, 'UTF-8') ?>" placeholder="bot.example.com یا bot.example.com/redfox"><div class="hint">بدون <code>https://</code>؛ مسیر زیرپوشه مجاز است. سیستم خودکار <code>/index.php</code> را اضافه می‌کند.</div></div>
<div class="rx-field"><label>یوزرنیم ربات (بدون @)</label><input type="text" name="bot_username" value="<?= htmlspecialchars($currentBotUser, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="rx-actions"><button class="btn btn-sm btn-primary" type="submit">💾 ذخیره امن و اعمال</button></div>
</form></div>
<div class="card"><div class="card__head"><h2 class="card__title">📡 وضعیت و بازیابی Webhook</h2></div>
<div class="rx-status"><div><small>وضعیت ثبت‌شده</small><strong><?= htmlspecialchars($statusLabels[$statusName] ?? $statusLabels['unknown'], ENT_QUOTES, 'UTF-8') ?></strong></div><div><small>آخرین کد امن</small><code><?= htmlspecialchars($statusCode !== '' ? $statusCode : '—', ENT_QUOTES, 'UTF-8') ?></code></div><div><small>آخرین تلاش</small><span><?= $lastAttempt > 0 ? htmlspecialchars(date('Y-m-d H:i:s', $lastAttempt), ENT_QUOTES, 'UTF-8') : '—' ?></span></div></div>
<p style="font-size:13px;color:var(--text-muted);line-height:1.9">«تعمیر Webhook» بدون نیاز به تغییر توکن یا دامنه، preflight عمومی، ثبت امن و تأیید مستقل با <code>getWebhookInfo</code> را اجرا می‌کند.</p>
<div class="rx-actions">
<form method="post" style="display:inline"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="repair_webhook"><button class="btn btn-sm btn-primary" type="submit">🛠 تعمیر Webhook</button></form>
<form method="post" style="display:inline"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="test_webhook"><button class="btn btn-sm btn-soft-purple" type="submit">🔍 بررسی وضعیت</button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Webhook حذف شود؟')"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="delete_webhook"><button class="btn btn-sm btn-danger" type="submit">🗑 حذف Webhook</button></form>
</div></div>
<?php else: ?><div class="card"><p class="text-muted">فقط مدیر اصلی می‌تواند این تنظیمات را تغییر دهد.</p></div><?php endif; ?>
<div class="rx-info">ترتیب پیشنهادی رفع خطا: کد امن را بخوانید، DNS/TLS/پورت یا WAF را اصلاح کنید، سپس «تعمیر Webhook» و بعد «بررسی وضعیت» را اجرا کنید. خامِ پاسخ Telegram، توکن و secret_token هرگز در صفحه نمایش داده نمی‌شود.</div>
</div></section></section></body></html>
