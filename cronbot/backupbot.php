<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/CronGuard.php';
rx_cron_authorize();
date_default_timezone_set('Asia/Tehran');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/backup-cron.log');
ignore_user_abort(true);
@set_time_limit(0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/DatabaseBackup.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$lockPath = __DIR__ . '/../storage/backup.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
register_shutdown_function(static function () use ($lock): void {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
});

try {
    if (!($pdo instanceof PDO)) throw new RuntimeException('PDO connection is unavailable.');
    if (function_exists('rx_cron_telemetry_start')) rx_cron_telemetry_start($pdo, 'backup');
    $key = rx_key_from_env('REDFOX_BACKUP_KEY');
    if ($key === null) throw new RuntimeException('REDFOX_BACKUP_KEY is missing.');
    $setting = select('setting', '*');
    $channel = trim((string)($setting['Channel_Report'] ?? ''));
    if ($channel === '') throw new RuntimeException('Channel_Report is not configured.');
    $topic = select('topicid', 'idreport', 'report', 'backupfile', 'select')['idreport'] ?? null;

    $outDir = __DIR__ . '/../storage/backups/' . date('Y/m');
    $backup = RedFoxDatabaseBackup::create($pdo, $outDir, $key);
    $payload = [
        'chat_id' => $channel,
        'document' => new CURLFile($backup, 'application/octet-stream', basename($backup)),
        'caption' => "🔐 بکاپ رمزنگاری‌شده RedFox\n" . date('Y-m-d H:i') . "\nکلید بازیابی خارج از Telegram نگهداری شود.",
    ];
    if (!empty($topic)) $payload['message_thread_id'] = $topic;
    $sent = telegram('sendDocument', $payload);
    if (!$sent) throw new RuntimeException('Telegram sendDocument failed.');

    // Encrypted local copies are retained for 30 days; checksum sidecars follow them.
    $root = __DIR__ . '/../storage/backups';
    $iterator = is_dir($root) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) : [];
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.rxb(?:\.sha256)?$/', $file->getFilename()) && $file->getMTime() < time() - 30 * 86400) {
            @unlink($file->getPathname());
        }
    }
} catch (Throwable $e) {
    error_log('[backup-cron] ' . redfox_exception_fingerprint($e));
    http_response_code(500);
}
