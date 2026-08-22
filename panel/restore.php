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
$detail = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    redfox_enforce_csrf();
    $confirm = trim((string)($_POST['confirm'] ?? ''));
    if ($confirm !== 'RESTORE ENCRYPTED BACKUP') {
        $flash = 'عبارت تأیید دقیق نیست.';
        $flashType = 'error';
    } elseif (!isset($_FILES['backupfile']) || (int)($_FILES['backupfile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $flash = 'آپلود فایل بکاپ ناموفق بود.';
        $flashType = 'error';
    } else {
        $upload = $_FILES['backupfile'];
        $tmp = (string)$upload['tmp_name'];
        $name = (string)$upload['name'];
        $size = (int)$upload['size'];
        $maxBytes = 268435456;
        if (!is_uploaded_file($tmp) || $size < 64 || $size > $maxBytes || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'rxb') {
            $flash = 'فقط فایل داخلی .rxb با حداکثر حجم ۲۵۶ مگابایت پذیرفته می‌شود.';
            $flashType = 'error';
        } else {
            $job = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
            $workDir = dirname(__DIR__) . '/storage/restore-jobs/' . $job;
            $backupDir = dirname(__DIR__) . '/storage/restore-backups/' . $job;
            $uploadedFile = $workDir . '/uploaded.rxb';
            $lock = null;
            $maintenance = dirname(__DIR__) . '/storage/maintenance.flag';
            try {
                $key = rx_key_from_env('REDFOX_BACKUP_KEY');
                if ($key === null) throw new RuntimeException('REDFOX_BACKUP_KEY تنظیم نشده است.');
                RedFoxDatabaseBackup::assertRestoreAvailable();
                if (!is_dir($workDir) && !mkdir($workDir, 0700, true)) throw new RuntimeException('پوشه کاری Restore ساخته نشد.');
                if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('پوشه بکاپ پیش از Restore ساخته نشد.');
                if (!move_uploaded_file($tmp, $uploadedFile)) throw new RuntimeException('انتقال امن فایل آپلودی ناموفق بود.');
                chmod($uploadedFile, 0600);

                $lock = fopen(dirname(__DIR__) . '/storage/restore.lock', 'c');
                if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('عملیات Restore دیگری در حال اجراست.');
                file_put_contents($maintenance, json_encode(['operation'=>'restore','job'=>$job,'started_at'=>time()], JSON_UNESCAPED_UNICODE), LOCK_EX);

                // A current-state backup is mandatory. No destructive operation occurs if it fails.
                $preBackup = RedFoxDatabaseBackup::create($pdo, $backupDir, $key);
                $dbHost = (string)($GLOBALS['dbhost'] ?? 'localhost');
                $dbName = (string)($GLOBALS['dbname'] ?? '');
                $dbUser = (string)($GLOBALS['usernamedb'] ?? '');
                $dbPassword = (string)($GLOBALS['passworddb'] ?? '');
                if ($dbName === '' || $dbUser === '') throw new RuntimeException('مشخصات دیتابیس کامل نیست.');

                try {
                    RedFoxDatabaseBackup::restore($pdo, $uploadedFile, $key, $dbHost, $dbName, $dbUser, $dbPassword);
                } catch (Throwable $restoreError) {
                    try {
                        RedFoxDatabaseBackup::restore($pdo, $preBackup, $key, $dbHost, $dbName, $dbUser, $dbPassword);
                    } catch (Throwable $rollbackError) {
                        throw new RuntimeException(
                            'Restore ناموفق بود و Rollback نیز شکست خورد: ' . redfox_exception_fingerprint($restoreError) . ' | ' . redfox_exception_fingerprint($rollbackError),
                            0,
                            $restoreError
                        );
                    }
                    throw new RuntimeException('Restore رد شد/ناموفق بود و دیتابیس قبلی با موفقیت Rollback شد: ' . redfox_exception_fingerprint($restoreError), 0, $restoreError);
                }

                $flash = '✅ بکاپ رمزنگاری‌شده با موفقیت بازیابی شد.';
                $flashType = 'success';
                $detail = 'بکاپ اجباری وضعیت قبل در storage/restore-backups/' . $job . ' نگهداری شد.';
                if (function_exists('rx_log_event_structured')) {
                    rx_log_event_structured('warning', 'database.restore.completed', [
                        'job' => $job,
                        'actor' => (string)$admin['username'],
                        'uploaded_sha256' => hash_file('sha256', $uploadedFile),
                        'pre_backup' => basename($preBackup),
                    ]);
                }
            } catch (Throwable $e) {
                error_log('[secure-restore] ' . redfox_exception_fingerprint($e));
                $flash = 'Restore انجام نشد.';
                $flashType = 'error';
                $detail = redfox_public_exception($e, 'panel/restore.php');
            } finally {
                @unlink($uploadedFile);
                if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
                @unlink($maintenance);
                if (is_dir($workDir)) @rmdir($workDir);
            }
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>بازیابی امن بکاپ</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-alert{padding:14px;border-radius:12px;margin-bottom:16px;white-space:pre-wrap}.success{background:var(--color-success-soft);color:var(--color-success)}.error{background:var(--color-danger-soft);color:var(--color-danger)}.warn{background:var(--color-warning-soft);color:var(--color-warning)}.field{margin:14px 0}.field label{display:block;margin-bottom:7px;color:var(--text-muted)}.field input{width:min(680px,100%);box-sizing:border-box}</style>
</head><body><section id="container"><?php include 'header.php'; ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">♻️ بازیابی امن بکاپ</h1><div class="page-head__sub">فقط فرمت داخلی، رمزنگاری‌شده و authenticated با پسوند RXB</div></div>
<div class="rx-alert warn">⚠️ فایل‌های SQL، ZIP، GZ و dump دلخواه عمداً پذیرفته نمی‌شوند. پیش از هر Restore یک بکاپ رمزنگاری‌شده اجباری ساخته می‌شود و در صورت خطا Rollback خودکار انجام خواهد شد.</div>
<?php if ($flash !== ''): ?><div class="rx-alert <?=htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($flash . ($detail !== '' ? "\n" . $detail : ''), ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<div class="card"><h2>آپلود فایل داخلی .rxb</h2>
<form method="post" enctype="multipart/form-data">
<?=redfox_csrf_field()?>
<div class="field"><label>فایل بکاپ (حداکثر ۲۵۶ MB)</label><input type="file" name="backupfile" accept=".rxb,application/octet-stream" required></div>
<div class="field"><label>برای تأیید دقیقاً بنویسید: <code>RESTORE ENCRYPTED BACKUP</code></label><input type="text" name="confirm" autocomplete="off" required></div>
<button class="btn btn-danger" type="submit" onclick="return confirm('تمام دیتابیس با بکاپ رمزنگاری‌شده جایگزین شود؟')">اجرای Restore امن</button>
</form></div>
</div></section></section></body></html>
