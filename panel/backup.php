<?php
declare(strict_types=1);

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
redfox_security_headers();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/DatabaseBackup.php';
require_once __DIR__ . '/lib/icons.php';

$stmt = $pdo->prepare('SELECT * FROM admin WHERE username=? LIMIT 1');
$stmt->execute([(string)($_SESSION['user'] ?? '')]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) { header('Location: login.php'); exit; }
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit; }

$flash = '';
$flashType = 'info';
$channelReport = '';
try { $channelReport = (string)$pdo->query('SELECT Channel_Report FROM setting LIMIT 1')->fetchColumn(); } catch (Throwable $e) {}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    redfox_enforce_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!in_array($action, ['download', 'channel'], true)) throw new RuntimeException('عملیات نامعتبر است.');
        $key = rx_key_from_env('REDFOX_BACKUP_KEY');
        if ($key === null) throw new RuntimeException('REDFOX_BACKUP_KEY تنظیم نشده است.');
        $outDir = dirname(__DIR__) . '/storage/backups/' . date('Y/m');
        $backup = RedFoxDatabaseBackup::create($pdo, $outDir, $key);
        if (function_exists('rx_log_event_structured')) {
            rx_log_event_structured('warning', 'database.backup.created', [
                'actor' => (string)$admin['username'],
                'file' => basename($backup),
                'sha256' => hash_file('sha256', $backup),
                'destination' => $action,
            ]);
        }
        if ($action === 'download') {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup) . '"');
            header('Content-Length: ' . filesize($backup));
            header('X-Content-Type-Options: nosniff');
            readfile($backup);
            exit;
        }
        if ($channelReport === '') throw new RuntimeException('کانال گزارش تنظیم نشده است.');
        if (!class_exists('CURLFile')) throw new RuntimeException('افزونه cURL در دسترس نیست.');
        require_once __DIR__ . '/../botapi.php';
        telegram('sendDocument', [
            'chat_id' => $channelReport,
            'document' => new CURLFile($backup, 'application/octet-stream', basename($backup)),
            'caption' => "🔐 بکاپ رمزنگاری‌شده RedFox\n" . date('Y-m-d H:i') . "\nکلید بکاپ جداگانه و خارج از کانال نگهداری شود.",
        ]);
        $flash = '✅ بکاپ رمزنگاری‌شده به کانال گزارش ارسال و نسخه داخلی آن نگهداری شد.';
        $flashType = 'success';
    } catch (Throwable $e) {
        error_log('[secure-backup] ' . redfox_exception_fingerprint($e));
        $flash = redfox_public_exception($e, 'panel/backup.php');
        $flashType = 'error';
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مرکز بکاپ امن</title><link rel="stylesheet" href="css/theme.css"><style>.alert{padding:14px;border-radius:12px;margin-bottom:15px}.success{background:var(--color-success-soft);color:var(--color-success)}.error{background:var(--color-danger-soft);color:var(--color-danger)}.actions{display:flex;gap:10px;flex-wrap:wrap}.note{color:var(--text-muted);line-height:2}</style></head><body>
<section id="container"><?php include 'header.php'; ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🔐 مرکز بکاپ امن</h1><div class="page-head__sub">بکاپ کامل دیتابیس با XChaCha20-Poly1305 و فرمت داخلی RXB</div></div>
<?php if ($flash !== ''): ?><div class="alert <?=htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($flash, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<div class="card"><h2>ساخت بکاپ رمزنگاری‌شده</h2><div class="actions">
<form method="post"><?=redfox_csrf_field()?><input type="hidden" name="action" value="download"><button class="btn btn-success">⬇️ ساخت و دانلود .rxb</button></form>
<form method="post" onsubmit="return confirm('بکاپ رمزنگاری‌شده به کانال گزارش ارسال شود؟')"><?=redfox_csrf_field()?><input type="hidden" name="action" value="channel"><button class="btn btn-primary">📨 ساخت و ارسال به کانال</button></form>
</div><p class="note">فایل خام SQL عمداً تولید یا ارسال نمی‌شود. فایل RXB فقط با <code>REDFOX_BACKUP_KEY</code> همان استقرار قابل بازیابی است. کلید را خارج از web root و جدا از فایل بکاپ نگهداری کنید.<br>کانال گزارش: <code><?=htmlspecialchars($channelReport !== '' ? $channelReport : 'تنظیم‌نشده', ENT_QUOTES, 'UTF-8')?></code></p></div>
<div class="card"><h2>ابزارها</h2><p><a class="btn btn-warning" href="restore.php">♻️ بازیابی فایل RXB</a></p></div>
</div></section></section></body></html>
