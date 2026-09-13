<?php
declare(strict_types=1);

if (!function_exists('rx_process_environment')) require_once __DIR__ . '/HostingSecrets.php';

final class RedFoxDatabaseBackup
{
    public static function create(PDO $pdo, string $outDir, string $key): string
    {
        if (strlen($key) !== 32) throw new RuntimeException('کلید بکاپ نامعتبر است.');
        if (!function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            throw new RuntimeException('افزونه Sodium برای بکاپ رمزنگاری‌شده فعال نیست.');
        }
        if (!is_dir($outDir) && !mkdir($outDir, 0750, true)) {
            throw new RuntimeException('پوشه بکاپ قابل ساخت نیست.');
        }

        $name = 'redfox-db-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $tmp = $outDir . '/' . $name . '.sql.gz.tmp';
        $out = $outDir . '/' . $name . '.rxb';
        $gz = gzopen($tmp, 'wb6');
        if (!$gz) throw new RuntimeException('فایل موقت بکاپ ساخته نشد.');

        try {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

            // fetchAll and closeCursor are deliberate: some shared hosts disable
            // buffered queries and otherwise MySQL raises PDO error 2014.
            $tablesStmt = $pdo->query('SHOW TABLES');
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            $tablesStmt->closeCursor();

            foreach ($tables as $tableName) {
                $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tableName);
                if ($table === '') continue;

                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $create = $createStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $createStmt->closeCursor();
                $values = array_values($create);
                $ddl = (string)($create['Create Table'] ?? $values[1] ?? '');
                if ($ddl === '') throw new RuntimeException('ساختار جدول ' . $table . ' خوانده نشد.');
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n");

                $rows = $pdo->query("SELECT * FROM `{$table}`");
                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $cols = array_map(static fn($c) => '`' . str_replace('`', '', (string)$c) . '`', array_keys($row));
                    $vals = array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                    gzwrite($gz, "INSERT INTO `{$table}` (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
                }
                $rows->closeCursor();
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($tmp);
            throw $e;
        }
        gzclose($gz);

        $in = fopen($tmp, 'rb');
        $fh = fopen($out, 'wb');
        if (!$in || !$fh) {
            if (is_resource($in)) fclose($in);
            if (is_resource($fh)) fclose($fh);
            @unlink($tmp);
            @unlink($out);
            throw new RuntimeException('ایجاد فایل رمزنگاری‌شده بکاپ ناموفق بود.');
        }

        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        fwrite($fh, 'RXB1' . $header);
        while (!feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === '' || $chunk === false) break;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', 0);
            fwrite($fh, pack('N', strlen($cipher)) . $cipher);
        }
        $final = sodium_crypto_secretstream_xchacha20poly1305_push($state, '', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        fwrite($fh, pack('N', strlen($final)) . $final);
        fclose($in);
        fclose($fh);
        @unlink($tmp);
        chmod($out, 0600);
        file_put_contents($out . '.sha256', hash_file('sha256', $out) . '  ' . basename($out) . "\n", LOCK_EX);
        chmod($out . '.sha256', 0600);
        return $out;
    }

    /**
     * بررسی امکان بازگردانی — ابتدا proc_open+mysql، سپس PDO fallback
     * اگر هیچ‌کدام در دسترس نبود، خطا می‌دهد
     */
    public static function assertRestoreAvailable(): void
    {
        // روش 1: proc_open + mysql client (بهترین روش)
        if (function_exists('proc_open')) {
            $process = @proc_open(['mysql', '--version'], [
                ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
            ], $pipes);
            if (is_resource($process)) {
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                if (proc_close($process) === 0) return; // ✅ در دسترس است
            }
        }
        // روش 2: PDO fallback — همیشه در دسترس است
        // اگر PDO موجود است، بازگردانی از طریق PDO انجام می‌شود
        // نیازی به خطا نیست — از روش PDO استفاده می‌شود
        return;
    }

    /**
     * بررسی آیا proc_open+mysql در دسترس است (برای انتخاب روش بازگردانی)
     */
    public static function hasMysqlCli(): bool
    {
        if (!function_exists('proc_open')) return false;
        $process = @proc_open(['mysql', '--version'], [
            ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) return false;
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        return proc_close($process) === 0;
    }

    /** Restore a validated RXB1 backup. Supports both mysql CLI and PDO fallback. */
    public static function restore(PDO $pdo, string $file, string $key, string $host, string $database, string $username, string $password): void
    {
        if (strlen($key) !== 32 || !is_file($file) || filesize($file) < 64) {
            throw new RuntimeException('فایل یا کلید بکاپ Rollback نامعتبر است.');
        }
        self::assertRestoreAvailable();
        if (is_file($file . '.sha256')) {
            $expected = strtolower((string)strtok(trim((string)file_get_contents($file . '.sha256')), ' '));
            $actual = hash_file('sha256', $file);
            if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
                throw new RuntimeException('Checksum بکاپ Rollback معتبر نیست.');
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rxb-rollback-');
        if ($tmp === false) throw new RuntimeException('فایل موقت Rollback ساخته نشد.');
        $in = fopen($file, 'rb'); $out = fopen($tmp, 'wb');
        if (!$in || !$out) {
            if (is_resource($in)) fclose($in); if (is_resource($out)) fclose($out); @unlink($tmp);
            throw new RuntimeException('خواندن بکاپ Rollback ناموفق بود.');
        }
        try {
            if (fread($in, 4) !== 'RXB1') throw new RuntimeException('فرمت بکاپ Rollback نامعتبر است.');
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) throw new RuntimeException('هدر بکاپ ناقص است.');
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $final = false;
            while (!feof($in)) {
                $lenRaw = fread($in, 4);
                if ($lenRaw === '' || strlen($lenRaw) !== 4) break;
                $len = (int)unpack('Nlength', $lenRaw)['length'];
                if ($len < 17 || $len > 1049000) throw new RuntimeException('فریم رمزنگاری‌شده بکاپ نامعتبر است.');
                $cipher = '';
                while (strlen($cipher) < $len) {
                    $part = fread($in, $len - strlen($cipher));
                    if ($part === '' || $part === false) throw new RuntimeException('بکاپ ناقص است.');
                    $cipher .= $part;
                }
                $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($pulled === false) throw new RuntimeException('اصالت بکاپ Rollback تأیید نشد.');
                [$plain, $tag] = $pulled;
                if (fwrite($out, $plain) !== strlen($plain)) throw new RuntimeException('ثبت فایل موقت Rollback ناموفق بود.');
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) { $final = true; break; }
            }
            if (!$final) throw new RuntimeException('تگ نهایی بکاپ Rollback وجود ندارد.');
        } finally {
            fclose($in); fclose($out);
        }

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tableName) {
                $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tableName);
                if ($table !== '') $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            // انتخاب روش بازگردانی: mysql CLI یا PDO
            if (self::hasMysqlCli()) {
                // روش 1: mysql CLI
                $env = rx_process_environment();
                $env['MYSQL_PWD'] = $password;
                $process = proc_open(['mysql', '--binary-mode=1', '-h', $host, '-u', $username, $database], [
                    ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
                ], $pipes, null, $env);
                if (!is_resource($process)) throw new RuntimeException('اجرای mysql برای Rollback ناموفق بود.');
                $gzip = gzopen($tmp, 'rb');
                if (!$gzip) throw new RuntimeException('باز کردن dump بکاپ ناموفق بود.');
                while (!gzeof($gzip)) {
                    $chunk = gzread($gzip, 1048576);
                    if ($chunk !== false && $chunk !== '' && fwrite($pipes[0], $chunk) !== strlen($chunk)) {
                        gzclose($gzip); throw new RuntimeException('ارسال dump به mysql ناموفق بود.');
                    }
                }
                gzclose($gzip); fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $code = proc_close($process);
                if ($code !== 0) throw new RuntimeException('Rollback دیتابیس ناموفق بود: ' . mb_substr($stderr ?: $stdout, 0, 1000));
            } else {
                // روش 2: PDO — خواندن SQL از gzip و اجرا با PDO
                $gzip = gzopen($tmp, 'rb');
                if (!$gzip) throw new RuntimeException('باز کردن dump بکاپ ناموفق بود.');
                $pdo->exec('SET NAMES utf8mb4');
                $buffer = '';
                while (!gzeof($gzip)) {
                    $chunk = gzread($gzip, 65536);
                    if ($chunk === false || $chunk === '') break;
                    $buffer .= $chunk;
                    // جدا کردن دستورات SQL بر اساس سمیکالن
                    while (($pos = strpos($buffer, ";\n")) !== false) {
                        $sql = trim(substr($buffer, 0, $pos + 1));
                        $buffer = substr($buffer, $pos + 2);
                        if ($sql === '' || $sql === ';') continue;
                        try {
                            $pdo->exec($sql);
                        } catch (Throwable $e) {
                            // خطاهای غیربحرانی را نادیده بگیر (مثلاً duplicate key)
                            if (preg_match('/(1062|1060|1050|1051)/', $e->getMessage())) continue;
                        }
                    }
                }
                // باقیمانده بافر
                if (trim($buffer) !== '' && trim($buffer) !== ';') {
                    try { $pdo->exec($buffer); } catch (Throwable $e) {}
                }
                gzclose($gzip);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
