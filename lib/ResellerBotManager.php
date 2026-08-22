<?php
declare(strict_types=1);

final class RedFoxResellerBotManager
{
    private PDO $pdo;
    private string $root;

    public function __construct(PDO $pdo, string $root)
    {
        $canonical = realpath($root);
        if (!is_string($canonical) || $canonical === '' || is_link($root) || !is_dir($canonical)) {
            throw new InvalidArgumentException('ریشه برنامه معتبر نیست.');
        }
        $this->pdo = $pdo;
        $this->root = rtrim($canonical, '/\\');
        require_once $this->root . '/lib/TelegramWebhook.php';
    }

    public function repairAll(): array
    {
        $q = $this->pdo->query("SELECT id,id_user,username,bot_token,webhook_secret_token FROM botsaz WHERE bot_token IS NOT NULL AND bot_token<>''");
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $q->closeCursor();
        $ok = 0;
        $fail = 0;
        $details = [];
        foreach ($rows as $row) {
            try {
                $details[] = $this->repair($row);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                $owner = preg_match('/^[0-9]{1,20}$/', (string)($row['id_user'] ?? ''))
                    ? (string)$row['id_user'] : 'invalid';
                $fingerprint = $this->exceptionFingerprint($e);
                $details[] = ['owner' => $owner, 'ok' => false, 'error' => 'repair_failed', 'ref' => $fingerprint];
                $this->record((int)($row['id'] ?? 0), 'failed', 'repair_failed:' . $fingerprint);
            }
        }
        return ['ok' => $ok, 'failed' => $fail, 'details' => $details];
    }

    public function repair(array $row): array
    {
        $owner = trim((string)($row['id_user'] ?? ''));
        $username = ltrim(trim((string)($row['username'] ?? '')), '@');
        $token = (string)($row['bot_token'] ?? '');
        if (function_exists('rx_secret_decrypt')) {
            try {
                $token = (string)rx_secret_decrypt($token);
            } catch (Throwable $e) {
                throw new RuntimeException('رمزگشایی توکن ربات ناموفق بود.', 0, $e);
            }
        }
        if (!preg_match('/^[0-9]{1,20}$/', $owner)
            || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $username)
            || !preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $token)) {
            throw new RuntimeException('اطلاعات مالک، یوزرنیم یا توکن ربات نامعتبر است.');
        }

        $rowId = (int)($row['id'] ?? 0);
        if ($rowId < 1) throw new RuntimeException('شناسه رکورد ربات نامعتبر است.');

        $secret = strtolower((string)($row['webhook_secret_token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
            $secret = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare(
                "UPDATE botsaz SET webhook_secret_token=?
                  WHERE id=? AND (webhook_secret_token IS NULL OR webhook_secret_token='' OR webhook_secret_token NOT REGEXP '^[A-Fa-f0-9]{64}$')"
            );
            $stmt->execute([$secret, $rowId]);
            if ($stmt->rowCount() !== 1) {
                $fresh = $this->pdo->prepare('SELECT webhook_secret_token FROM botsaz WHERE id=? LIMIT 1');
                $fresh->execute([$rowId]);
                $secret = strtolower((string)$fresh->fetchColumn());
                if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
                    throw new RuntimeException('ثبت secret وبهوک ناموفق بود.');
                }
            }
        }

        $base = $this->root . '/vpnbot';
        if (!$this->isCanonicalDirectory($base)) {
            throw new RuntimeException('پوشه پایه ربات‌ها ناامن است.');
        }
        $dir = $base . '/' . $owner . $username;
        if (is_link($dir)) throw new RuntimeException('مسیر ربات symlink است.');

        if (!is_dir($dir)) {
            $old = $this->findByToken($base, $token);
            if ($old !== null) {
                if (is_link($old) || is_link($dir) || file_exists($dir) || !@rename($old, $dir)) {
                    throw new RuntimeException('انتقال امن پوشه قدیمی ربات ناموفق بود.');
                }
            } else {
                $template = $this->root . '/vpnbot/Default';
                if (!$this->isCanonicalDirectory($template) || !$this->copyTree($template, $dir)) {
                    throw new RuntimeException('پوشه ربات موجود نیست و بازسازی آن ناموفق بود.');
                }
            }
        }
        if (!$this->isCanonicalDirectory($dir) || realpath($dir) !== $dir || dirname($dir) !== $base) {
            throw new RuntimeException('مسیر ربات ناامن است.');
        }

        $src = $this->root . '/vpnbot/update';
        if (!$this->isCanonicalDirectory($src)) $src = $this->root . '/vpnbot/Default';
        if (!$this->isCanonicalDirectory($src)) throw new RuntimeException('قالب بروزرسانی ناامن است.');

        foreach (['index.php', 'func.php', 'keyboard.php', 'botapi.php', 'admin.php', '.htaccess'] as $file) {
            $source = $src . '/' . $file;
            $destination = $dir . '/' . $file;
            if (!$this->copyRegularAtomically($source, $destination, $file === '.htaccess' ? 0644 : 0640)) {
                throw new RuntimeException('جایگزینی امن فایل ربات ناموفق بود.');
            }
            if (function_exists('opcache_invalidate')) @opcache_invalidate($destination, true);
        }

        $cfg = $dir . '/config.php';
        if (is_link($cfg)) throw new RuntimeException('config ربات symlink است.');
        $body = is_file($cfg) ? @file_get_contents($cfg) : false;
        if (!is_string($body) || strlen($body) > 1048576) {
            throw new RuntimeException('config ربات معتبر نیست.');
        }
        $body = $this->setConfigValue($body, 'ApiToken', $token);
        $body = $this->setConfigValue($body, 'WebhookSecret', $secret);
        if (!$this->atomicWrite($cfg, $body, 0600, true)) {
            throw new RuntimeException('ثبت امن config ربات ناموفق بود.');
        }

        $webhook = $this->webhookUrl(basename($dir));
        $outcome = redfox_apply_telegram_webhook(
            fn(string $method, array $parameters): array => $this->telegramRequest($token, $method, $parameters),
            $webhook,
            $secret,
            true
        );
        if (empty($outcome['ok'])) {
            $code = preg_match('/^[A-Z0-9_-]{1,64}$/', (string)($outcome['code'] ?? ''))
                ? (string)$outcome['code'] : 'TG-WH-UNKNOWN';
            throw new RuntimeException('ثبت Webhook ربات ناموفق بود؛ کد ' . $code . '.');
        }

        $version = trim((string)@file_get_contents($this->root . '/version'));
        if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $version)
            || !$this->atomicWrite($dir . '/.redfox-template-version', $version . "\n", 0640, false)) {
            throw new RuntimeException('ثبت نسخه قالب ربات ناموفق بود.');
        }

        $this->record($rowId, 'ready', '');
        return ['owner' => $owner, 'ok' => true, 'directory' => basename($dir), 'webhook' => $webhook];
    }

    private function setConfigValue(string $body, string $name, string $value): string
    {
        $pattern = '/\$' . preg_quote($name, '/') . '\s*=\s*([\'\"]).*?\1\s*;/s';
        $line = '$' . $name . ' = ' . var_export($value, true) . ';';
        if (preg_match($pattern, $body) === 1) {
            $updated = preg_replace_callback($pattern, static fn() => $line, $body, 1, $count);
            if (!is_string($updated) || $count !== 1) throw new RuntimeException('قالب config قابل بروزرسانی نیست.');
            return $updated;
        }
        return rtrim($body) . "\n" . $line . "\n";
    }

    private function webhookUrl(string $dir): string
    {
        if (!preg_match('/^[0-9]{1,20}[A-Za-z0-9_]{1,64}$/', $dir)) {
            throw new RuntimeException('نام پوشه وبهوک نامعتبر است.');
        }
        if (function_exists('redfox_configured_base_url')) {
            $origin = redfox_configured_base_url();
            if (is_string($origin) && $origin !== '') {
                return rtrim($origin, '/') . '/vpnbot/' . rawurlencode($dir) . '/index.php';
            }
        }
        $domain = strtolower(trim((string)($GLOBALS['domainhosts'] ?? '')));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? '';
        $domain = rtrim($domain, '/');
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new RuntimeException('دامنه معتبر در config.php تنظیم نشده است.');
        }
        return 'https://' . $domain . '/vpnbot/' . rawurlencode($dir) . '/index.php';
    }

    private function telegramRequest(string $token, string $method, array $parameters): array
    {
        if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $token)
            || !preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $method)) {
            return ['ok' => false];
        }
        if (function_exists('telegram')) {
            try {
                $response = telegram($method, $parameters, $token);
                return is_array($response) ? $response : ['ok' => false];
            } catch (Throwable $e) {
                return ['ok' => false];
            }
        }
        if (function_exists('telegramApiRequest')) {
            try {
                $response = telegramApiRequest($token, $method, $parameters);
                return is_array($response) ? $response : ['ok' => false];
            } catch (Throwable $e) {
                return ['ok' => false];
            }
        }
        if (!function_exists('curl_init')) return ['ok' => false];

        $curl = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        if ($curl === false) return ['ok' => false];
        if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'telegram');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300 || strlen($raw) > 1048576) {
            return ['ok' => false];
        }
        $decoded = json_decode($raw, true, 32);
        return is_array($decoded) ? $decoded : ['ok' => false];
    }

    private function findByToken(string $base, string $token): ?string
    {
        if (!$this->isCanonicalDirectory($base)) return null;
        $count = 0;
        foreach (scandir($base) ?: [] as $entry) {
            if (++$count > 5000) break;
            if (!preg_match('/^[0-9]{1,20}[A-Za-z0-9_]{1,64}$/', $entry)) continue;
            $directory = $base . '/' . $entry;
            $file = $directory . '/config.php';
            if (!$this->isCanonicalDirectory($directory) || is_link($file) || !is_file($file)) continue;
            $stat = @lstat($file);
            if (!is_array($stat) || (int)($stat['size'] ?? 0) > 1048576) continue;
            $body = @file_get_contents($file);
            if (is_string($body) && str_contains($body, $token)) return $directory;
        }
        return null;
    }

    private function copyTree(string $source, string $destination): bool
    {
        if (!$this->isCanonicalDirectory($source) || is_link($destination)) return false;
        if (!is_dir($destination) && !@mkdir($destination, 0750)) return false;
        if (!$this->isCanonicalDirectory($destination)) return false;
        @chmod($destination, 0750);
        foreach (scandir($source) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            $a = $source . '/' . $file;
            $b = $destination . '/' . $file;
            if (is_link($a)) return false;
            if (is_dir($a)) {
                if (!$this->copyTree($a, $b)) return false;
            } elseif (!$this->copyRegularAtomically($a, $b, $file === '.htaccess' ? 0644 : 0640)) {
                return false;
            }
        }
        return true;
    }

    private function copyRegularAtomically(string $source, string $destination, int $permissions): bool
    {
        clearstatcache(true, $source);
        $sourceStat = @lstat($source);
        if (is_link($source) || !is_file($source) || !is_array($sourceStat)
            || (((int)($sourceStat['mode'] ?? 0) & 0170000) !== 0100000)) return false;
        $input = @fopen($source, 'rb');
        if (!is_resource($input)) return false;
        $opened = @fstat($input);
        if (!is_array($opened) || (int)$sourceStat['dev'] !== (int)$opened['dev'] || (int)$sourceStat['ino'] !== (int)$opened['ino']) {
            fclose($input);
            return false;
        }
        $content = '';
        while (!feof($input)) {
            $chunk = fread($input, 1048576);
            if (!is_string($chunk)) { fclose($input); return false; }
            $content .= $chunk;
            if (strlen($content) > 16 * 1024 * 1024) { fclose($input); return false; }
        }
        fclose($input);
        return $this->atomicWrite($destination, $content, $permissions, is_file($destination));
    }

    private function atomicWrite(string $destination, string $content, int $permissions, bool $mustExist): bool
    {
        $directory = dirname($destination);
        if (!$this->isCanonicalDirectory($directory) || is_link($destination)) return false;
        if ($mustExist && !is_file($destination)) return false;
        if (!$mustExist && file_exists($destination) && !is_file($destination)) return false;
        try {
            $temporary = $directory . '/.rx-write-' . bin2hex(random_bytes(16)) . '.tmp';
        } catch (Throwable $e) {
            return false;
        }
        $handle = @fopen($temporary, 'x+b');
        if (!is_resource($handle)) return false;
        $ok = @fchmod($handle, $permissions) && $this->writeAll($handle, $content) && @fflush($handle);
        if ($ok && function_exists('fsync')) $ok = @fsync($handle);
        $stat = @fstat($handle);
        if (!is_array($stat) || (((int)($stat['mode'] ?? 0) & 0170000) !== 0100000)) $ok = false;
        fclose($handle);
        if (!$ok || !$this->isCanonicalDirectory($directory) || is_link($temporary) || !is_file($temporary)
            || is_link($destination) || ($mustExist && !is_file($destination))) {
            @unlink($temporary);
            return false;
        }
        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            return false;
        }
        return !is_link($destination) && is_file($destination) && @chmod($destination, $permissions);
    }

    private function writeAll($handle, string $content): bool
    {
        $length = strlen($content);
        $offset = 0;
        while ($offset < $length) {
            $written = @fwrite($handle, substr($content, $offset));
            if (!is_int($written) || $written < 1) return false;
            $offset += $written;
        }
        return true;
    }

    private function isCanonicalDirectory(string $directory): bool
    {
        clearstatcache(true, $directory);
        if (is_link($directory)) return false;
        $stat = @lstat($directory);
        $real = @realpath($directory);
        return is_array($stat)
            && (((int)($stat['mode'] ?? 0) & 0170000) === 0040000)
            && is_string($real) && $real === $directory;
    }

    private function exceptionFingerprint(Throwable $e): string
    {
        return substr(hash('sha256', get_class($e) . '|' . (string)$e->getCode() . '|'
            . basename((string)$e->getFile()) . '|' . (string)$e->getLine()), 0, 16);
    }

    private function record(int $id, string $status, string $error): void
    {
        if ($id < 1 || !in_array($status, ['ready', 'failed'], true)) return;
        try {
            $this->pdo->prepare('UPDATE botsaz SET sync_status=?,sync_error=?,last_synced_at=? WHERE id=?')
                ->execute([$status, mb_substr($error, 0, 255), time(), $id]);
        } catch (Throwable $e) {
        }
    }
}
