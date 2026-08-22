<?php
/**
 * Hosting-safe secret configuration.
 *
 * Operational secrets are stored outside config.php so updater/install repair
 * operations never need to rewrite executable application configuration.
 * Values from the real process environment take precedence over this file.
 * This implementation deliberately does not use putenv(): many shared hosts
 * disable it, and application correctness must not depend on process mutation.
 */

declare(strict_types=1);

if (!class_exists('RedFoxHostingSecretException', false)) {
    final class RedFoxHostingSecretException extends RuntimeException
    {
        public function __construct(
            public readonly string $safeCode,
            string $safeMessage,
            ?Throwable $previous = null
        ) {
            parent::__construct($safeMessage, 0, $previous);
        }
    }
}

if (!function_exists('rx_hosting_secret_allowed_keys')) {
    function rx_hosting_secret_allowed_keys(): array
    {
        return [
            'REDFOX_DB_HOST', 'REDFOX_DB_NAME', 'REDFOX_DB_USER', 'REDFOX_DB_PASSWORD',
            'REDFOX_BOT_TOKEN', 'REDFOX_ADMIN_ID', 'REDFOX_DOMAIN', 'REDFOX_BOT_USERNAME',
            'REDFOX_MASTER_KEY', 'REDFOX_BACKUP_KEY', 'REDFOX_CRON_SECRET',
            'REDFOX_DIAG_SECRET', 'REDFOX_HEALTH_TOKEN', 'REDFOX_RESELLER_API_TOKEN',
            'REDFOX_UPDATE_PUBLIC_KEY', 'REDFOX_UPDATE_PUBLIC_KEYS', 'REDFOX_UPDATE_CHANNEL',
            'REDFOX_TRUSTED_PROXY_CIDRS',
        ];
    }
}

if (!function_exists('rx_hosting_secret_throw')) {
    function rx_hosting_secret_throw(string $code, string $message, ?Throwable $previous = null): never
    {
        throw new RedFoxHostingSecretException($code, $message, $previous);
    }
}

if (!function_exists('rx_hosting_secret_root')) {
    function rx_hosting_secret_root(?string $root = null): string
    {
        $candidate = $root !== null ? rtrim($root, '/\\') : dirname(__DIR__);
        $real = realpath($candidate);
        if ($real === false || !is_dir($real)) {
            rx_hosting_secret_throw('RXSEC-ROOT', 'Application root is unavailable.');
        }
        return rtrim($real, '/\\');
    }
}

if (!function_exists('rx_process_environment_value')) {
    function rx_process_environment_value(string $key): ?string
    {
        $value = false;
        // A disabled function is absent on current PHP versions. Guarding the
        // call keeps restricted shared-hosting configurations operational.
        if (function_exists('getenv')) {
            try {
                $value = getenv($key);
            } catch (Throwable) {
                $value = false;
            }
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }
        // Some SAPIs populate exact environment names here even when getenv is
        // restricted. Never derive a secret from HTTP_* request headers.
        foreach ([$_ENV ?? [], $_SERVER ?? []] as $source) {
            if (is_array($source) && array_key_exists($key, $source)
                && is_scalar($source[$key]) && (string)$source[$key] !== '') {
                return (string)$source[$key];
            }
        }
        return null;
    }
}

if (!function_exists('rx_process_environment')) {
    /** Return the real process environment for a child process without request headers. */
    function rx_process_environment(): array
    {
        $environment = [];
        if (function_exists('getenv')) {
            try {
                $all = getenv();
                if (is_array($all)) $environment = $all;
            } catch (Throwable) {
                $environment = [];
            }
        }
        if (is_array($_ENV ?? null)) {
            foreach ($_ENV as $key => $value) {
                if (is_string($key) && is_scalar($value) && !str_starts_with($key, 'HTTP_')) {
                    $environment[$key] = (string)$value;
                }
            }
        }
        return $environment;
    }
}

if (!function_exists('rx_hosting_secret_read_file')) {
    function rx_hosting_secret_read_file(string $file, string $storageReal): array
    {
        if (!file_exists($file)) {
            return [];
        }
        if (is_link($file) || !is_file($file)) {
            rx_hosting_secret_throw('RXSEC-FILE-TYPE', 'Secure settings file is not a regular file.');
        }
        $real = realpath($file);
        $expected = $storageReal . DIRECTORY_SEPARATOR . 'secure.env.php';
        if ($real === false || $real !== $expected) {
            rx_hosting_secret_throw('RXSEC-FILE-PATH', 'Secure settings path validation failed.');
        }

        try {
            /** @noinspection PhpIncludeInspection */
            $data = (static function (string $__rxSecretFile) {
                return @require $__rxSecretFile;
            })($real);
        } catch (Throwable $error) {
            rx_hosting_secret_throw('RXSEC-FILE-PARSE', 'Secure settings file cannot be parsed.', $error);
        }
        if (!is_array($data)) {
            rx_hosting_secret_throw('RXSEC-FILE-FORMAT', 'Secure settings file has an invalid format.');
        }

        $allowed = array_fill_keys(rx_hosting_secret_allowed_keys(), true);
        $clean = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key]) || !is_scalar($value)) {
                continue;
            }
            $value = (string)$value;
            if ($value !== '' && strlen($value) <= 16384 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}

if (!function_exists('rx_load_hosting_secrets')) {
    function rx_load_hosting_secrets(?string $root = null, bool $refresh = false): array
    {
        $rootReal = rx_hosting_secret_root($root);
        if (!isset($GLOBALS['rx_hosting_secrets_cache']) || !is_array($GLOBALS['rx_hosting_secrets_cache'])) {
            $GLOBALS['rx_hosting_secrets_cache'] = [];
        }
        if (!$refresh && isset($GLOBALS['rx_hosting_secrets_cache'][$rootReal])
            && is_array($GLOBALS['rx_hosting_secrets_cache'][$rootReal])) {
            return $GLOBALS['rx_hosting_secrets_cache'][$rootReal];
        }

        $storage = $rootReal . DIRECTORY_SEPARATOR . 'storage';
        if (!file_exists($storage)) {
            $GLOBALS['rx_hosting_secrets_cache'][$rootReal] = [];
            return [];
        }
        if (is_link($storage) || !is_dir($storage)) {
            rx_hosting_secret_throw('RXSEC-STORAGE-TYPE', 'Storage is not a regular directory.');
        }
        $storageReal = realpath($storage);
        if ($storageReal === false || dirname($storageReal) !== $rootReal) {
            rx_hosting_secret_throw('RXSEC-STORAGE-PATH', 'Storage path validation failed.');
        }

        $data = rx_hosting_secret_read_file($storageReal . DIRECTORY_SEPARATOR . 'secure.env.php', $storageReal);
        $GLOBALS['rx_hosting_secrets_cache'][$rootReal] = $data;
        return $data;
    }
}

if (!function_exists('rx_env')) {
    /**
     * Read a RedFox setting without requiring putenv(). A genuine server
     * environment value always overrides the hosting secrets file.
     */
    function rx_env(string $key, string $default = '', ?string $root = null): string
    {
        $environment = rx_process_environment_value($key);
        if ($environment !== null) {
            return $environment;
        }
        if (!in_array($key, rx_hosting_secret_allowed_keys(), true)) {
            return $default;
        }
        try {
            $stored = rx_load_hosting_secrets($root);
        } catch (Throwable $error) {
            // Configuration reads must degrade to an actionable unavailable
            // state, not make every endpoint white-screen. Writers still throw.
            $code = $error instanceof RedFoxHostingSecretException ? $error->safeCode : 'RXSEC-LOAD';
            error_log('[hosting-secrets] read failed code=' . $code);
            return $default;
        }
        return isset($stored[$key]) ? (string)$stored[$key] : $default;
    }
}

if (!function_exists('rx_hosting_secret_diagnostic')) {
    function rx_hosting_secret_diagnostic(Throwable $error): array
    {
        $code = $error instanceof RedFoxHostingSecretException ? $error->safeCode : 'RXSEC-UNKNOWN';
        $guidance = [
            'RXSEC-ROOT' => 'مسیر اصلی برنامه در دسترس PHP نیست؛ مسیر نصب و open_basedir هاست را بررسی کنید.',
            'RXSEC-STORAGE-CREATE' => 'پوشه storage ساخته نشد؛ آن را داخل ریشه برنامه ایجاد و دسترسی نوشتن کاربر PHP را فعال کنید.',
            'RXSEC-STORAGE-TYPE' => 'مسیر storage باید یک پوشه واقعی باشد و نباید symlink باشد.',
            'RXSEC-STORAGE-PATH' => 'مسیر واقعی storage خارج از ریشه برنامه تشخیص داده شد؛ symlink و open_basedir را بررسی کنید.',
            'RXSEC-STORAGE-WRITE' => 'PHP اجازه نوشتن در storage را ندارد؛ مالکیت پوشه و دسترسی کاربر PHP را بررسی کنید.',
            'RXSEC-LOCK-TYPE' => 'مسیر قفل تنظیمات امن معتبر نیست؛ .secure-env.lock و .secure-env.mutex را در storage بررسی کنید.',
            'RXSEC-LOCK-OPEN' => 'فایل قفل در storage باز نشد؛ quota، مالکیت و دسترسی نوشتن پوشه را بررسی کنید.',
            'RXSEC-LOCK-ACQUIRE' => 'قفل تنظیمات امن گرفته نشد؛ چند ثانیه صبر کنید و نصب هم‌زمان دیگری را متوقف کنید.',
            'RXSEC-LOCK-UNAVAILABLE' => 'هاست هم flock و هم mkdir را غیرفعال کرده است؛ برای جلوگیری از overwrite ناامن حداقل یکی از این دو تابع باید فعال باشد.',
            'RXSEC-LOCK-TIMEOUT' => 'قفل تنظیمات امن بیش از حد درگیر بود؛ عملیات نصب یا ذخیره هم‌زمان را متوقف و پس از چند ثانیه دوباره تلاش کنید.',
            'RXSEC-FILE-TYPE' => 'storage/secure.env.php باید فایل واقعی باشد و نباید symlink باشد.',
            'RXSEC-FILE-PATH' => 'مسیر واقعی secure.env.php نامعتبر است؛ symlink و open_basedir را بررسی کنید.',
            'RXSEC-FILE-PARSE' => 'فایل secure.env.php ناقص یا نامعتبر است؛ آن را تغییر نام دهید و نصب را دوباره اجرا کنید.',
            'RXSEC-FILE-FORMAT' => 'قالب secure.env.php معتبر نیست؛ فایل را تغییر نام دهید تا نصاب نسخه سالم بسازد.',
            'RXSEC-TEMP-CREATE' => 'فایل موقت در storage ساخته نشد؛ quota و امکان ساخت فایل توسط PHP را بررسی کنید.',
            'RXSEC-TEMP-WRITE' => 'نوشتن کامل فایل موقت ممکن نشد؛ فضای دیسک و quota هاست را بررسی کنید.',
            'RXSEC-TEMP-RENAME' => 'جایگزینی اتمیک فایل ممکن نشد؛ مالکیت secure.env.php و محدودیت rename هاست را بررسی کنید.',
            'RXSEC-FINAL-PERM' => 'هاست نتوانست دسترسی امن 0600 را اعمال کند؛ مالکیت فایل و سیاست chmod را بررسی کنید.',
            'RXSEC-UNKNOWN' => 'خطای پیش‌بینی‌نشده رخ داد؛ کد پیگیری را همراه گزارش خطای PHP برای پشتیبانی ارسال کنید.',
        ];
        return [
            'code' => $code,
            'message' => $guidance[$code] ?? $guidance['RXSEC-UNKNOWN'],
        ];
    }
}

if (!function_exists('rx_update_hosting_secrets')) {
    function rx_update_hosting_secrets(array $changes, ?string $root = null): array
    {
        $rootReal = rx_hosting_secret_root($root);
        $storage = $rootReal . DIRECTORY_SEPARATOR . 'storage';
        if (!file_exists($storage)) {
            $oldUmask = umask(0027);
            try {
                $made = @mkdir($storage, 0750, true);
            } finally {
                umask($oldUmask);
            }
            if (!$made && !is_dir($storage)) {
                rx_hosting_secret_throw('RXSEC-STORAGE-CREATE', 'Unable to create storage directory.');
            }
        }
        if (is_link($storage) || !is_dir($storage)) {
            rx_hosting_secret_throw('RXSEC-STORAGE-TYPE', 'Storage is not a regular directory.');
        }
        $storageReal = realpath($storage);
        if ($storageReal === false || dirname($storageReal) !== $rootReal) {
            rx_hosting_secret_throw('RXSEC-STORAGE-PATH', 'Storage path validation failed.');
        }
        if (!is_writable($storageReal)) {
            rx_hosting_secret_throw('RXSEC-STORAGE-WRITE', 'Storage directory is not writable.');
        }

        $allowed = array_fill_keys(rx_hosting_secret_allowed_keys(), true);
        foreach ($changes as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                throw new InvalidArgumentException('Unsupported hosting secret key.');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Hosting secret values must be scalar.');
            }
            if ($value !== null) {
                $text = (string)$value;
                if (strlen($text) > 16384 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
                    throw new InvalidArgumentException('Hosting secret value is invalid.');
                }
            }
        }

        $file = $storageReal . DIRECTORY_SEPARATOR . 'secure.env.php';
        $lockPath = $storageReal . DIRECTORY_SEPARATOR . '.secure-env.lock';
        if (is_link($lockPath) || (file_exists($lockPath) && !is_file($lockPath))) {
            rx_hosting_secret_throw('RXSEC-LOCK-TYPE', 'Secure settings lock is invalid.');
        }

        $lock = null;
        $fallbackLockPath = null;
        $fallbackLockHeld = false;
        $temp = null;
        $oldUmask = umask(0077);
        try {
            $lock = @fopen($lockPath, 'c+b');
            if (!is_resource($lock)) {
                rx_hosting_secret_throw('RXSEC-LOCK-OPEN', 'Unable to open secure settings lock.');
            }
            if (function_exists('fchmod')) {
                @fchmod($lock, 0600);
            }
            if (function_exists('flock')) {
                if (!@flock($lock, LOCK_EX)) {
                    rx_hosting_secret_throw('RXSEC-LOCK-ACQUIRE', 'Unable to lock secure settings.');
                }
            } else {
                // Some shared hosts disable flock(). mkdir remains an atomic
                // cross-process primitive on the supported local/shared filesystems,
                // so use an exclusive directory mutex rather than merging unlocked.
                if (!function_exists('mkdir')) {
                    rx_hosting_secret_throw('RXSEC-LOCK-UNAVAILABLE', 'No safe lock primitive is available.');
                }
                $fallbackLockPath = $storageReal . DIRECTORY_SEPARATOR . '.secure-env.mutex';
                $deadline = microtime(true) + 10.0;
                do {
                    if (@mkdir($fallbackLockPath, 0700)) {
                        $fallbackLockHeld = true;
                        break;
                    }
                    clearstatcache(true, $fallbackLockPath);
                    if (is_link($fallbackLockPath)
                        || (file_exists($fallbackLockPath) && !is_dir($fallbackLockPath))) {
                        rx_hosting_secret_throw('RXSEC-LOCK-TYPE', 'Secure settings mutex is invalid.');
                    }
                    $modified = is_dir($fallbackLockPath) ? @filemtime($fallbackLockPath) : false;
                    // A crashed PHP process can leave the empty mutex directory.
                    // Reap it only after a conservative interval; rmdir is itself
                    // atomic and fails while another process has already replaced it.
                    if (is_int($modified) && $modified > 0 && (time() - $modified) > 300) {
                        @rmdir($fallbackLockPath);
                        continue;
                    }
                    if (function_exists('usleep')) {
                        @usleep(random_int(10000, 40000));
                    } elseif (function_exists('time_nanosleep')) {
                        @time_nanosleep(0, 25000000);
                    }
                } while (microtime(true) < $deadline);
                if (!$fallbackLockHeld) {
                    rx_hosting_secret_throw('RXSEC-LOCK-TIMEOUT', 'Timed out waiting for secure settings lock.');
                }
            }

            // Read only after obtaining the common lock so concurrent panel and
            // installer updates cannot silently overwrite one another.
            $current = rx_hosting_secret_read_file($file, $storageReal);
            foreach ($changes as $key => $value) {
                if ($value === null || (string)$value === '') {
                    unset($current[$key]);
                } else {
                    $current[$key] = (string)$value;
                }
            }
            ksort($current, SORT_STRING);
            $payload = "<?php\n// Generated by RedFox. Keep this file private.\nreturn "
                . var_export($current, true) . ";\n";

            for ($attempt = 0; $attempt < 8; $attempt++) {
                $candidate = $storageReal . DIRECTORY_SEPARATOR . 'secure.env.tmp-' . bin2hex(random_bytes(8));
                $temp = @fopen($candidate, 'x+b');
                if (is_resource($temp)) {
                    break;
                }
            }
            if (!is_resource($temp)) {
                rx_hosting_secret_throw('RXSEC-TEMP-CREATE', 'Unable to create secure settings temporary file.');
            }
            $tempPath = $candidate;
            if (function_exists('fchmod')) {
                @fchmod($temp, 0600);
            }

            $length = strlen($payload);
            $offset = 0;
            while ($offset < $length) {
                $written = @fwrite($temp, substr($payload, $offset));
                if (!is_int($written) || $written <= 0) {
                    rx_hosting_secret_throw('RXSEC-TEMP-WRITE', 'Unable to write secure settings.');
                }
                $offset += $written;
            }
            if (!@fflush($temp)) {
                rx_hosting_secret_throw('RXSEC-TEMP-WRITE', 'Unable to flush secure settings.');
            }
            // fsync is durability hardening, not a compatibility requirement:
            // several network/shared filesystems reject it after a valid flush.
            if (function_exists('fsync')) {
                @fsync($temp);
            }
            @fclose($temp);
            $temp = null;

            if (!function_exists('rename') || !@rename($tempPath, $file)) {
                @unlink($tempPath);
                rx_hosting_secret_throw('RXSEC-TEMP-RENAME', 'Unable to atomically replace secure settings.');
            }
            if (function_exists('chmod')) {
                @chmod($file, 0600);
            }
            clearstatcache(true, $file);
            $mode = function_exists('fileperms') ? @fileperms($file) : false;
            // umask(0077) already creates 0600. If the filesystem reports that
            // group/other bits remain, fail closed instead of exposing secrets.
            if (is_int($mode) && (($mode & 0077) !== 0)) {
                rx_hosting_secret_throw('RXSEC-FINAL-PERM', 'Secure settings permissions are too broad.');
            }
            if (function_exists('opcache_invalidate')) {
                try {
                    @opcache_invalidate($file, true);
                } catch (Throwable) {
                    // Opcache invalidation is best effort; the next request
                    // still observes a newly created path/inode on normal SAPIs.
                }
            }

            if (!isset($GLOBALS['rx_hosting_secrets_cache']) || !is_array($GLOBALS['rx_hosting_secrets_cache'])) {
                $GLOBALS['rx_hosting_secrets_cache'] = [];
            }
            $GLOBALS['rx_hosting_secrets_cache'][$rootReal] = $current;
            return $current;
        } finally {
            if (is_resource($temp)) {
                @fclose($temp);
            }
            if (isset($tempPath) && is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
            if ($fallbackLockHeld && is_string($fallbackLockPath)) {
                @rmdir($fallbackLockPath);
                $fallbackLockHeld = false;
            }
            if (is_resource($lock)) {
                if (function_exists('flock')) {
                    @flock($lock, LOCK_UN);
                }
                @fclose($lock);
            }
            umask($oldUmask);
        }
    }
}
