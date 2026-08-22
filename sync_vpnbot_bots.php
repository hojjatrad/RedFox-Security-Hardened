<?php
declare(strict_types=1);

/**
 * Compatibility entry point for synchronising reseller bot runtimes.
 *
 * Security invariants:
 *  - an administrator session is mandatory;
 *  - GET is read-only and never accepts a credential;
 *  - synchronisation is POST-only and protected by the central CSRF guard;
 *  - all filesystem/Webhook work is delegated to RedFoxResellerBotManager.
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}
require_once __DIR__ . '/lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ResellerBotManager.php';

$stmt = $pdo->prepare('SELECT id_admin,username,rule FROM admin WHERE username=:username LIMIT 1');
$stmt->execute([':username' => (string)($_SESSION['user'] ?? '')]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($admin)) {
    header('Location: panel/login.php', true, 303);
    exit;
}
if ((string)($admin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit('Forbidden');
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed');
}

$result = null;
$error = '';
if ($method === 'POST') {
    if ((string)($_POST['action'] ?? '') !== 'repair_all') {
        http_response_code(400);
        $error = 'درخواست نامعتبر است.';
    } else {
        try {
            $result = (new RedFoxResellerBotManager($pdo, __DIR__))->repairAll();
        } catch (Throwable $e) {
            error_log('[reseller-bot-sync] repair failed: ' . get_class($e) . ': ' . redfox_exception_fingerprint($e));
            http_response_code(500);
            $error = 'همگام‌سازی ناموفق بود؛ جزئیات در لاگ امن سرور ثبت شد.';
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$csrf = htmlspecialchars(redfox_csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>همگام‌سازی امن ربات‌های نماینده</title>
    <style>
        body{font-family:sans-serif;background:#0a0a0f;color:#e4e4e7;padding:20px;max-width:760px;margin:6vh auto}
        main{background:#171721;border:1px solid #30303c;border-radius:16px;padding:24px}.ok{color:#86efac}.err{color:#fca5a5}
        button,a{display:inline-block;border:0;border-radius:9px;padding:11px 16px;text-decoration:none;cursor:pointer}
        button{background:#6d5dfc;color:#fff}a{background:#282834;color:#fff}code{direction:ltr}
    </style>
</head>
<body><main>
    <h1>همگام‌سازی ربات‌های نماینده</h1>
    <p>این عملیات فایل‌های runtime را از قالب رسمی بازسازی و Webhook هر ربات را با secret مستقل بازبینی می‌کند. GET فقط همین صفحه را نمایش می‌دهد.</p>
    <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (is_array($result)): ?>
        <p class="ok">عملیات پایان یافت: <?= (int)($result['ok'] ?? 0) ?> موفق، <?= (int)($result['failed'] ?? 0) ?> ناموفق.</p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="rx_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="repair_all">
        <button type="submit">اجرای همگام‌سازی امن</button>
        <a href="panel/agents.php">بازگشت به مدیریت نمایندگان</a>
    </form>
</main></body></html>
