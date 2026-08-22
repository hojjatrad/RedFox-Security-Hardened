<?php


declare(strict_types=1);

if (!function_exists('rx_env')) require_once dirname(__DIR__, 2) . '/lib/HostingSecrets.php';

if (class_exists('RedFoxLogger')) {
    return;
}

final class RedFoxLogger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARN = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';


    private const LEVEL_RANK = [
        'debug'    => 10,
        'info'     => 20,
        'warning'  => 30,
        'error'    => 40,
        'critical' => 50,
    ];


    private static $logDir;


    private static $initialised = false;


    private static $minLevel = 'info';


    public static function init(?string $logDir = null): void
    {
        if (self::$initialised && $logDir === null) {
            return;
        }

        $base = $logDir ?: dirname(__DIR__, 2) . '/logs';
        $base = rtrim($base, "/\\");

        if (is_link($base)
            || (!is_dir($base) && !@mkdir($base, 0750, true))
            || !is_dir($base)) {
            self::$logDir = '';
        } else {
            clearstatcache(true, $base);
            $baseStat = @lstat($base);
            $canonical = @realpath($base);
            if (!is_array($baseStat)
                || (((int)($baseStat['mode'] ?? 0) & 0170000) !== 0040000)
                || !is_string($canonical) || $canonical === '') {
                self::$logDir = '';
            } else {
                @chmod($canonical, 0750);
                self::$logDir = rtrim($canonical, "/\\");
            }
        }

        $envLevel = rx_env('REDFOX_LOG_LEVEL');
        if (is_string($envLevel) && $envLevel !== '') {
            $envLevel = strtolower(trim($envLevel));
            if (isset(self::LEVEL_RANK[$envLevel])) {
                self::$minLevel = $envLevel;
            }
        } elseif (defined('REDFOX_LOG_LEVEL')) {
            $constLevel = strtolower((string) constant('REDFOX_LOG_LEVEL'));
            if (isset(self::LEVEL_RANK[$constLevel])) {
                self::$minLevel = $constLevel;
            }
        }

        self::$initialised = true;
    }


    public static function setMinLevel(string $level): void
    {
        $level = strtolower(trim($level));
        if (isset(self::LEVEL_RANK[$level])) {
            self::$minLevel = $level;
        }
    }


    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$initialised) {
            self::init();
        }


        $rank = self::LEVEL_RANK[$level] ?? self::LEVEL_RANK['info'];
        $minRank = self::LEVEL_RANK[self::$minLevel] ?? self::LEVEL_RANK['info'];
        if ($rank < $minRank) {
            return;
        }

        $fxCtxFingerprint = '';
        foreach (['panel', 'username', 'user_id', 'reason', 'msg'] as $fxKey) {
            if (isset($context[$fxKey]) && is_scalar($context[$fxKey])) {
                $fxCtxFingerprint .= '|' . $fxKey . '=' . substr((string)$context[$fxKey], 0, 256);
            }
        }
        $fxDedupKey = 'fx|' . $level . '|' . substr($message, 0, 120) . $fxCtxFingerprint;
        $fxCacheDir = self::$logDir !== '' ? self::$logDir . '/.dedup' : '';
        if ($fxCacheDir !== ''
            && !is_link($fxCacheDir)
            && (is_dir($fxCacheDir) || @mkdir($fxCacheDir, 0700))
            && self::isCanonicalDirectory($fxCacheDir)) {
            @chmod($fxCacheDir, 0700);
            $fxCacheFile = $fxCacheDir . '/' . hash('sha256', $fxDedupKey);
            $fxHandle = self::openLockedRegularFile($fxCacheFile, 'c+', 0600);
            if (is_resource($fxHandle)) {
                $fxStat = fstat($fxHandle);
                $fxMtime = is_array($fxStat) ? (int)($fxStat['mtime'] ?? 0) : 0;
                $fxSize = is_array($fxStat) ? (int)($fxStat['size'] ?? 0) : 0;
                $fxDuplicate = $fxSize > 0 && $fxMtime > 0 && (time() - $fxMtime) < 21600;
                if (!$fxDuplicate) {
                    $marker = (string)time();
                    if (!ftruncate($fxHandle, 0) || !rewind($fxHandle)
                        || !self::writeAll($fxHandle, $marker) || !fflush($fxHandle)) {
                        // A failed marker must not suppress a future real event.
                        @ftruncate($fxHandle, 0);
                    } elseif (function_exists('fsync')) {
                        @fsync($fxHandle);
                    }
                }
                self::closeLockedFile($fxHandle);
                if ($fxDuplicate) return;
            }
            if (mt_rand(1, 100) === 1) {
                foreach (glob($fxCacheDir . '/*') ?: [] as $oldCache) {
                    clearstatcache(true, $oldCache);
                    $oldStat = @lstat($oldCache);
                    if (is_array($oldStat)
                        && (((int)$oldStat['mode'] & 0170000) === 0100000)
                        && (time() - (int)($oldStat['mtime'] ?? 0)) > 86400) {
                        @unlink($oldCache);
                    }
                }
            }
        }
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '');
        $entry = [
            'ts' => date('Y-m-d H:i:s'),
            'level' => $level,
            'msg' => self::sanitiseString($message),
            'ip' => self::clientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => self::sanitiseString($requestPath),
        ];

        if (!empty($context)) {

            $entry['ctx'] = self::sanitiseContext($context);
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($line) && strlen($line) > 32768) {
            $entry['ctx'] = ['_truncated' => 'entry_too_large'];
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($line === false) {
            $line = '{"ts":"' . date('Y-m-d H:i:s') . '","level":"' . $level . '","msg":"<unencodable log entry>"}';
        }

        $file = self::$logDir !== '' ? self::$logDir . '/api-' . date('Y-m-d') . '.log' : '';
        $rotated = $file !== '' ? $file . '.1' : '';
        $lockFile = self::$logDir !== '' ? self::$logDir . '/.api-write.lock' : '';
        if ($file === '' || $lockFile === '') {
            error_log('[RedFoxLogger fallback] ' . $line);
            return;
        }

        // One verified lock serialises size checks, rotation and append across
        // PHP workers. Every opened path is compared with its file descriptor
        // so a symlink swap cannot redirect writes or chmod another file.
        $rotationLock = self::openLockedRegularFile($lockFile, 'c+', 0600);
        if (!is_resource($rotationLock)) {
            error_log('[RedFoxLogger fallback] ' . $line);
            return;
        }

        $safeToWrite = true;
        clearstatcache(true, $file);
        $fileStat = @lstat($file);
        if (is_array($fileStat) && (((int)$fileStat['mode'] & 0170000) !== 0100000)) {
            $safeToWrite = false;
        }
        if ($safeToWrite && is_array($fileStat) && (int)($fileStat['size'] ?? 0) >= 10 * 1024 * 1024) {
            clearstatcache(true, $rotated);
            $rotatedStat = @lstat($rotated);
            if (is_array($rotatedStat) && (((int)$rotatedStat['mode'] & 0170000) !== 0100000)) {
                $safeToWrite = false;
            } else {
                if (is_array($rotatedStat) && !@unlink($rotated)) $safeToWrite = false;
                if ($safeToWrite && !@rename($file, $rotated)) $safeToWrite = false;
            }
        }

        $written = false;
        if ($safeToWrite) {
            $handle = self::openLockedRegularFile($file, 'ab', 0640);
            if (is_resource($handle)) {
                $payload = $line . PHP_EOL;
                $written = self::writeAll($handle, $payload) && fflush($handle);
                if ($written && function_exists('fsync')) $written = @fsync($handle);
                self::closeLockedFile($handle);
            }
        }
        self::closeLockedFile($rotationLock);
        if ($written !== true) {
            error_log('[RedFoxLogger fallback] ' . $line);
        }
    }

    public static function debug(string $msg, array $ctx = []): void { self::log(self::DEBUG, $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void { self::log(self::INFO, $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void { self::log(self::WARN, $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log(self::ERROR, $msg, $ctx); }
    public static function critical(string $msg, array $ctx = []): void { self::log(self::CRITICAL, $msg, $ctx); }


    public static function userFacing(string $msg, array $ctx = []): void { self::log(self::DEBUG, $msg, $ctx); }


    public static function exception(Throwable $e, string $note = '', array $ctx = []): void
    {
        $ctx['exception'] = get_class($e);
        $ctx['file'] = basename((string)$e->getFile());
        $ctx['line'] = $e->getLine();
        self::log(self::ERROR, $note !== '' ? $note : 'Unhandled exception', $ctx);
    }

    private static function isCanonicalDirectory(string $directory): bool
    {
        clearstatcache(true, $directory);
        if (is_link($directory)) return false;
        $stat = @lstat($directory);
        $real = @realpath($directory);
        return is_array($stat)
            && (((int)($stat['mode'] ?? 0) & 0170000) === 0040000)
            && is_string($real)
            && rtrim($real, "/\\") === rtrim($directory, "/\\");
    }

    private static function writeAll($handle, string $payload): bool
    {
        $length = strlen($payload);
        $offset = 0;
        while ($offset < $length) {
            $written = @fwrite($handle, substr($payload, $offset));
            if (!is_int($written) || $written < 1) return false;
            $offset += $written;
        }
        return true;
    }

    /** Open and exclusively lock a regular file without trusting path-only checks. */
    private static function openLockedRegularFile(string $path, string $mode, int $permissions)
    {
        $parent = dirname($path);
        if (!self::isCanonicalDirectory($parent)) return false;
        $parentBefore = @lstat($parent);
        clearstatcache(true, $path);
        if (is_link($path)) return false;
        $handle = @fopen($path, $mode);
        if (!is_resource($handle)) return false;
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }
        clearstatcache(true, $path);
        clearstatcache(true, $parent);
        $pathStat = @lstat($path);
        $handleStat = @fstat($handle);
        $parentAfter = @lstat($parent);
        $regular = self::isCanonicalDirectory($parent)
            && is_array($parentBefore)
            && is_array($parentAfter)
            && (((int)($parentBefore['mode'] ?? 0) & 0170000) === 0040000)
            && (((int)($parentAfter['mode'] ?? 0) & 0170000) === 0040000)
            && (int)$parentBefore['dev'] === (int)$parentAfter['dev']
            && (int)$parentBefore['ino'] === (int)$parentAfter['ino']
            && is_array($pathStat)
            && is_array($handleStat)
            && (((int)$pathStat['mode'] & 0170000) === 0100000)
            && (((int)$handleStat['mode'] & 0170000) === 0100000)
            && (int)$pathStat['dev'] === (int)$handleStat['dev']
            && (int)$pathStat['ino'] === (int)$handleStat['ino'];
        if (!$regular) {
            @flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
        if (!@fchmod($handle, $permissions)) {
            @flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
        return $handle;
    }

    private static function closeLockedFile($handle): void
    {
        if (!is_resource($handle)) return;
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function clientIp(): string
    {
        if (function_exists('redfox_client_ip')) {
            $ip = trim((string)redfox_client_ip());
        } else {
            $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private static function sanitiseString(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer ***', $value) ?? $value;
        $value = preg_replace('/(?<![A-Za-z0-9_])\d{6,20}:[A-Za-z0-9_-]{20,100}(?![A-Za-z0-9_-])/', '[REDACTED_BOT_TOKEN]', $value) ?? $value;
        $value = preg_replace('#\b(?:vless|vmess|trojan|ss)://[^\s"<>]+#i', '[REDACTED_CONFIG_URI]', $value) ?? $value;
        $value = preg_replace('/((?:password|passwd|token|api[_-]?key|secret|authorization|private[_-]?key)\s*[=:]\s*)[^\s&;,]+/i', '$1***', $value) ?? $value;
        $value = preg_replace('/("(?:password|passwd|token|api[_-]?key|secret|authorization|private[_-]?key)"\s*:\s*")[^"]*/i', '$1***', $value) ?? $value;
        $value = preg_replace('/([?&](?:token|secret|password|api[_-]?key|key|signature|authorization)=)[^&#\s]*/i', '$1***', $value) ?? $value;
        if (strlen($value) > 2000) $value = substr($value, 0, 2000) . '…(truncated)';
        return $value;
    }

    private static function sanitiseContext(array $ctx, int $depth = 0): array
    {
        if ($depth >= 4) return ['_truncated' => 'max_depth'];
        $sensitive = '/password|passwd|token|api[_-]?key|secret|authorization|cookie|session|private[_-]?key|(?:^|_)(?:err|error)$|curl.*error|description|response|body|payload|(?:^|_)(?:url|path)$/i';
        $safe = [];
        $count = 0;
        foreach ($ctx as $k => $v) {
            if (++$count > 50) {
                $safe['_truncated'] = 'max_items';
                break;
            }
            $key = substr((string)$k, 0, 100);
            if (preg_match($sensitive, $key) === 1) {
                $safe[$key] = '***';
            } elseif (is_array($v)) {
                $safe[$key] = self::sanitiseContext($v, $depth + 1);
            } elseif (is_string($v)) {
                $safe[$key] = self::sanitiseString($v);
            } elseif (is_object($v) || is_resource($v)) {
                $safe[$key] = '<non-scalar>';
            } else {
                $safe[$key] = $v;
            }
        }
        return $safe;
    }
}

RedFoxLogger::init();

