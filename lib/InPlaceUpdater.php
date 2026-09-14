<?php
declare(strict_types=1);

require_once __DIR__ . '/SecureUpdater.php';
require_once __DIR__ . '/DatabaseBackup.php';
require_once __DIR__ . '/MigrationRunner.php';
require_once __DIR__ . '/ResellerBotManager.php';

final class RedFoxInPlaceUpdater
{
    private PDO $pdo;
    private string $root;

    public function __construct(PDO $pdo, string $root)
    {
        $this->pdo = $pdo;
        $this->root = rtrim($root, '/');
    }

    private function preserve(string $r): bool
    {
        if (in_array($r, [
            'config.php', '.env', '.env.local', '.user.ini', 'text.json',
            'license.json', 'rx_inline_button_map.json', 'images.jpeg',
            'images.jpg', 'custom.jpg', '.release-manifest.json',
            '.release-signature.txt',
        ], true)) return true;
        if (preg_match('#^(?:storage|logs|updates)/#', $r)) return true;
        if (str_starts_with($r, 'Upload/')) return $r !== 'Upload/.htaccess';
        if (str_starts_with($r, 'vpnbot/')) {
            return !($r === 'vpnbot/index.php' || str_starts_with($r, 'vpnbot/Default/') || str_starts_with($r, 'vpnbot/update/'));
        }
        return false;
    }

    public function plan(string $package, bool $allowUnsigned = false): array
    {
        $current = trim((string) file_get_contents($this->root . '/version'));
        try {
            $info = RedFoxSecureUpdater::inspect($package, $current);
            $info['signed'] = true;
        } catch (RedFoxUnsignedUpdatePackage $e) {
            if (!$allowUnsigned) throw $e;
            $info = RedFoxSecureUpdater::inspectUnsigned($package, $current);
        }
        $changed = $added = $same = $skipped = 0;
        $items = [];
        foreach ($info['manifest']['files'] as $r => $hash) {
            if ($this->preserve($r)) { $skipped++; continue; }
            $path = $this->root . '/' . $r;
            if (!is_file($path)) { $added++; $items[$r] = 'added'; }
            elseif (!hash_equals(strtolower((string) $hash), hash_file('sha256', $path))) { $changed++; $items[$r] = 'changed'; }
            else { $same++; }
        }
        return ['info' => $info, 'changed' => $changed, 'added' => $added, 'same' => $same, 'skipped' => $skipped, 'items' => $items];
    }

    private function syncResellerBots(): int
    {
        $r = (new RedFoxResellerBotManager($this->pdo, $this->root))->repairAll();
        return (int) ($r['ok'] ?? 0);
    }

    private function runtimeCheck(): void
    {
        foreach (['version', 'config.php', 'index.php', 'panel/index.php', 'lib/Security.php', 're/rx/index/compiled.php'] as $f) {
            if (!is_file($this->root . '/' . $f) || filesize($this->root . '/' . $f) === 0) {
                throw new RuntimeException('فایل ضروری ناقص است: ' . $f);
            }
        }
    }

    public function install(
        string $package,
        string $source,
        string $actor,
        bool $allowUnsigned = true,
        ?string $requestedJobId = null,
        bool $createDatabaseBackup = true
    ): array {
        ignore_user_abort(true);
        @set_time_limit(900);

        $backupKey = rx_key_from_env('REDFOX_BACKUP_KEY');
        $dbHost = (string) ($GLOBALS['dbhost'] ?? 'localhost');
        $dbName = (string) ($GLOBALS['dbname'] ?? '');
        $dbUser = (string) ($GLOBALS['usernamedb'] ?? '');
        $dbPassword = (string) ($GLOBALS['passworddb'] ?? '');
        if ($dbName === '' || $dbUser === '') {
            throw new RuntimeException('مشخصات دیتابیس برای بروزرسانی کامل نیست.');
        }
        $doBackup = $createDatabaseBackup && $backupKey !== null;
        if (!$doBackup && $createDatabaseBackup && $backupKey === null) {
            $doBackup = false; // بکاپ اختیاری اگر کلید نباشد
        }

        $previousVersion = trim((string) file_get_contents($this->root . '/version'));
        $plan = $this->plan($package, $allowUnsigned);
        $i = $plan['info'];
        $job = ($requestedJobId !== null && preg_match('/^[a-f0-9]{32}$/', $requestedJobId))
            ? $requestedJobId
            : bin2hex(random_bytes(16));

        $base = $this->root . '/storage/update-backups/' . $job;
        $filesBackup = $base . '/files';
        $dbDir = $base . '/database';
        if (!mkdir($filesBackup, 0750, true) || !mkdir($dbDir, 0750, true)) {
            throw new RuntimeException('Cannot create update backup directory');
        }

        $this->pdo->prepare(
            "INSERT INTO update_jobs(job_id,version,source,signed_package,status,progress_percent,progress_stage,processed_files,total_action_files,total_files,changed_files,added_files,skipped_files,file_backup_path,actor,created_at,started_at) VALUES(?,?,?,?, 'running',2,'آماده‌سازی',0,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $job, $i['version'], $source, !empty($i['signed']) ? 1 : 0,
            count($plan['items']), $i['files'], $plan['changed'], $plan['added'], $plan['skipped'],
            $filesBackup, $actor, time(), time()
        ]);

        $setProgress = function (int $pct, string $stage, int $processed = 0) use ($job) {
            $this->pdo->prepare('UPDATE update_jobs SET progress_percent=?,progress_stage=?,processed_files=? WHERE job_id=?')
                ->execute([max(0, min(100, $pct)), $stage, $processed, $job]);
        };

        $lock = fopen($this->root . '/storage/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->pdo->prepare(
                "UPDATE update_jobs SET status='failed',progress_stage='ناموفق: بروزرسانی دیگری فعال است',error_message='Another update is running',finished_at=? WHERE job_id=?"
            )->execute([time(), $job]);
            throw new RuntimeException('Another update is running');
        }

        $maintenance = $this->root . '/storage/maintenance.flag';
        file_put_contents($maintenance, json_encode([
            'job_id' => $job, 'started_at' => time(), 'version' => $i['version']
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);

        register_shutdown_function(function () use ($maintenance, $job) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                @unlink($maintenance);
                try {
                    $fatalSummary = 'php-fatal:type-' . (int) ($err['type'] ?? 0) . ':'
                        . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename((string) ($err['file'] ?? 'unknown')))
                        . ':' . max(0, (int) ($err['line'] ?? 0));
                    $this->pdo->prepare(
                        "UPDATE update_jobs SET status='failed',progress_stage='Fatal Error — Maintenance آزاد شد',error_message=?,finished_at=? WHERE job_id=? AND status='running'"
                    )->execute([mb_substr($fatalSummary, 0, 1900), time(), $job]);
                } catch (Throwable $e) {
                }
            }
        });

        $setProgress(5, 'فعال‌سازی حالت نگهداری');
        $added = [];
        $backupPath = '';

        try {
            $setProgress(10, $doBackup ? 'تهیه بکاپ رمزنگاری‌شده دیتابیس' : 'آماده‌سازی فایل‌ها (بدون بکاپ)');

            if ($doBackup) {
                $backupPath = RedFoxDatabaseBackup::create($this->pdo, $dbDir, $backupKey);
                $this->pdo->prepare('UPDATE update_jobs SET database_backup_path=? WHERE job_id=?')
                    ->execute([$backupPath, $job]);
            }

            $setProgress(25, $doBackup ? 'بکاپ دیتابیس ذخیره شد؛ آماده‌سازی فایل‌ها' : 'آماده‌سازی فایل‌ها');

            $z = new ZipArchive();
            if ($z->open($package) !== true) throw new RuntimeException('Cannot reopen update ZIP');

            $processed = 0;
            $totalAction = max(1, count($plan['items']));

            foreach ($plan['items'] as $r => $kind) {
                if ($this->preserve($r)) continue;
                $body = $z->getFromName($i['prefix'] . $r);
                if (!is_string($body)) throw new RuntimeException('Package file missing during install: ' . $r);
                if (!empty($i['signed']) && !hash_equals(strtolower((string) $i['manifest']['files'][$r]), hash('sha256', $body))) {
                    throw new RuntimeException('Package hash changed during install: ' . $r);
                }
                $dst = $this->root . '/' . $r;
                $dir = dirname($dst);
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Cannot create ' . $dir);
                $realDir = realpath($dir);
                $realRoot = realpath($this->root);
                if (!$realDir || !$realRoot || ($realDir !== $realRoot && strpos($realDir, $realRoot . DIRECTORY_SEPARATOR) !== 0) || is_link($dst)) {
                    throw new RuntimeException('Unsafe destination path: ' . $r);
                }
                if (is_file($dst)) {
                    $bk = $filesBackup . '/' . $r;
                    $bd = dirname($bk);
                    if (!is_dir($bd)) mkdir($bd, 0750, true);
                    if (!copy($dst, $bk)) throw new RuntimeException('Cannot backup ' . $r);
                } else {
                    $added[] = $r;
                }
                $tmp = $dst . '.update-' . $job;
                if (file_put_contents($tmp, $body, LOCK_EX) === false) throw new RuntimeException('Cannot write ' . $r);
                chmod($tmp, str_ends_with($r, '.sh') ? 0755 : 0644);
                if (!rename($tmp, $dst)) throw new RuntimeException('Cannot replace ' . $r);
                $processed++;
                $progressStep = max(1, (int) ceil($totalAction / 60));
                if ($processed === $totalAction || $processed % $progressStep === 0) {
                    $setProgress(25 + (int) floor($processed / $totalAction * 55), 'بروزرسانی فایل‌ها: ' . $processed . ' از ' . $totalAction, $processed);
                }
            }
            $z->close();

            $setProgress(82, 'اجرای Migrationهای دیتابیس', $processed);
            $migrations = RedFoxMigrationRunner::run($this->pdo, $this->root . '/migrations', false, function ($pct, $stage) use ($setProgress, $processed) {
                $setProgress(82 + (int) floor($pct * .10), 'Migration: ' . $stage, $processed);
            });

            $setProgress(94, 'همگام‌سازی ربات‌های نماینده', $processed);
            $syncedBotFiles = $this->syncResellerBots();

            $setProgress(97, 'کنترل نهایی فایل‌ها', $processed);
            $this->runtimeCheck();

            $setProgress(99, 'بازنشانی Runtime و OPcache', $processed);
            if (function_exists('opcache_reset')) @opcache_reset();

            if (!empty($i['signed'])) {
                $z = new ZipArchive();
                $z->open($package);
                file_put_contents($this->root . '/.release-manifest.json', RedFoxSecureUpdater::canonical($i['manifest']), LOCK_EX);
                file_put_contents($this->root . '/.release-signature.txt', trim((string) $z->getFromName($i['prefix'] . 'update-signature.txt')), LOCK_EX);
                $z->close();
            } else {
                @unlink($this->root . '/.release-manifest.json');
                @unlink($this->root . '/.release-signature.txt');
            }

            $this->pdo->prepare(
                "UPDATE update_jobs SET status='completed',progress_percent=100,progress_stage='بروزرسانی با موفقیت تکمیل شد',processed_files=total_action_files,database_backup_path=?,finished_at=? WHERE job_id=?"
            )->execute([$backupPath, time(), $job]);

            $this->pdo->prepare(
                'INSERT INTO update_history(version,previous_version,package_sha256,status,actor,details,key_id,channel,backup_path,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $i['version'], $previousVersion, $i['package_sha256'], 'installed', $actor,
                'in-place diff update ' . $job, $i['key_id'], $i['channel'], $backupPath, time()
            ]);

            @unlink($maintenance);
            if (function_exists('opcache_reset')) @opcache_reset();

            return [
                'ok' => true, 'job_id' => $job, 'version' => $i['version'],
                'changed' => $plan['changed'], 'added' => $plan['added'], 'skipped' => $plan['skipped'],
                'db_backup' => $backupPath, 'migrations' => $migrations
            ];
        } catch (Throwable $e) {
            // ── Rollback files ──
            foreach ($plan['items'] as $r => $kind) {
                $dst = $this->root . '/' . $r;
                $bk = $filesBackup . '/' . $r;
                if (is_file($bk)) {
                    @copy($bk, $dst);
                } elseif (in_array($r, $added, true)) {
                    @unlink($dst);
                }
            }

            // ── Rollback database ──
            $rollbackError = '';
            if ($doBackup && $backupPath !== '' && is_file($backupPath)) {
                try {
                    RedFoxDatabaseBackup::restore($this->pdo, $backupPath, $backupKey, $dbHost, $dbName, $dbUser, $dbPassword);
                } catch (Throwable $rollback) {
                    $rollbackError = ' | DATABASE ROLLBACK FAILED: ' . redfox_exception_fingerprint($rollback);
                }
            } else {
                $rollbackError = $doBackup ? ' | DATABASE ROLLBACK UNAVAILABLE' : ' | NO BACKUP CREATED (key missing)';
            }

            try {
                $this->pdo->prepare(
                    "UPDATE update_jobs SET status='failed',progress_stage='خطا و بازگردانی فایل و دیتابیس',database_backup_path=?,error_message=?,finished_at=? WHERE job_id=?"
                )->execute([$backupPath ?: null, mb_substr(redfox_exception_fingerprint($e) . $rollbackError, 0, 1900), time(), $job]);
            } catch (Throwable $ignored) {
            }

            @unlink($maintenance);
            if ($rollbackError !== '') {
                throw new RuntimeException(redfox_exception_fingerprint($e) . $rollbackError, 0, $e);
            }
            throw $e;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
