<?php
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__));
}
if (!defined('REFACTORED_LOG_DIR')) {
    define('REFACTORED_LOG_DIR', REFACTORED_LEGACY_ROOT . DIRECTORY_SEPARATOR . 'logs');
}
$rxPhpErrorLog = REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'php-error.log';
if (!is_link(REFACTORED_LOG_DIR)
    && !is_link($rxPhpErrorLog)
    && (is_dir(REFACTORED_LOG_DIR) || @mkdir(REFACTORED_LOG_DIR, 0750, true))) {
    @chmod(REFACTORED_LOG_DIR, 0750);
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    ini_set('error_log', $rxPhpErrorLog);
}
unset($rxPhpErrorLog);


error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);

if (!function_exists('redfox_exception_fingerprint')) {
    function redfox_exception_fingerprint(Throwable $error): string {
        return preg_replace('/[^A-Za-z0-9_\\\\]/', '_', get_class($error)) ?: 'Throwable';
    }
}

if (!function_exists('redfox_dedup_error_log')) {
    function redfox_dedup_error_log($key, $message, $ttl = 21600) {
        $cacheDir = sys_get_temp_dir() . '/redfox_log_dedup';
        if (is_link($cacheDir)
            || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0700, true))
            || !is_dir($cacheDir)) {
            return false;
        }
        @chmod($cacheDir, 0700);
        $cacheFile = $cacheDir . '/' . hash('sha256', (string)$key);
        if (is_link($cacheFile)) return false;
        $handle = @fopen($cacheFile, 'c+');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }
        $duplicate = (int)@filesize($cacheFile) > 0 && (time() - (int)@filemtime($cacheFile)) < max(1, (int)$ttl);
        if (!$duplicate) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)time());
            fflush($handle);
            @touch($cacheFile);
            @chmod($cacheFile, 0600);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        if ($duplicate) return false;
        error_log(redfox_runtime_log_sanitize((string)$message));
        return true;
    }
}

if (!function_exists('redfox_runtime_log_sanitize')) {
    function redfox_runtime_log_sanitize($value) {
        $text = str_replace(["\r", "\n", "\0"], ' ', (string)$value);
        $text = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $text);
        $text = preg_replace('/\b\d{6,12}:[A-Za-z0-9_-]{20,}\b/', '[TELEGRAM_TOKEN_REDACTED]', $text);
        $text = preg_replace('/([?&](?:token|secret|password|pass|hash|key|signature|authorization|initData|phone)=)[^&\s]*/i', '$1[REDACTED]', $text);
        $text = preg_replace('/("(?:token|secret|password|pass|api_key|hash|signature|authorization)"\s*:\s*")[^"]*/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b((?:token|secret|password|pass|api_key|hash|signature|authorization)\s*=\s*)[^\s&,}]+/i', '$1[REDACTED]', $text);
        return mb_substr((string)$text, 0, 4096, 'UTF-8');
    }
}

if (!function_exists('rx_log_event')) {
    function rx_log_event($type, $message, $context = []) {
        $rxStatus = isset($context['status']) ? (string)$context['status'] : '';
        $rxUriRaw = (string)($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'CLI'));
        $rxUriPath = (string)(parse_url($rxUriRaw, PHP_URL_PATH) ?: 'CLI');
        $rxUriTpl = preg_replace('#/\d+(?=/|$)#', '/*', $rxUriPath);
        $rxBodyRaw = isset($context['body']) ? redfox_runtime_log_sanitize($context['body']) : '';
        $rxKey = 'rx|' . $type . '|' . $rxStatus . '|' . $rxUriTpl . '|' . md5(substr($rxBodyRaw, 0, 200));
        $rxCacheDir = sys_get_temp_dir() . '/redfox_log_dedup';
        if (is_link($rxCacheDir)
            || (!is_dir($rxCacheDir) && !@mkdir($rxCacheDir, 0700, true))
            || !is_dir($rxCacheDir)) {
            return;
        }
        @chmod($rxCacheDir, 0700);
        $rxCacheFile = $rxCacheDir . '/' . hash('sha256', $rxKey);
        if (is_link($rxCacheFile)) return;
        $rxCacheHandle = @fopen($rxCacheFile, 'c+');
        if (!is_resource($rxCacheHandle) || !flock($rxCacheHandle, LOCK_EX)) {
            if (is_resource($rxCacheHandle)) fclose($rxCacheHandle);
            return;
        }
        $rxDuplicate = (int)@filesize($rxCacheFile) > 0 && (time() - (int)@filemtime($rxCacheFile)) < 21600;
        if (!$rxDuplicate) {
            ftruncate($rxCacheHandle, 0);
            rewind($rxCacheHandle);
            fwrite($rxCacheHandle, (string)time());
            fflush($rxCacheHandle);
            @touch($rxCacheFile);
            @chmod($rxCacheFile, 0600);
        }
        flock($rxCacheHandle, LOCK_UN);
        fclose($rxCacheHandle);
        if ($rxDuplicate) return;

        $line = '[' . date('Y-m-d H:i:s') . '] [' . redfox_runtime_log_sanitize($type) . '] ' . redfox_runtime_log_sanitize($message);
        $line .= ' | method=' . redfox_runtime_log_sanitize($_SERVER['REQUEST_METHOD'] ?? 'CLI');
        $line .= ' | uri=' . redfox_runtime_log_sanitize($rxUriPath);
        $line .= ' | script=' . redfox_runtime_log_sanitize(basename((string)($_SERVER['SCRIPT_FILENAME'] ?? 'unknown')));
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safeKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$key) ?: 'context';
                $sensitiveKey = preg_match('/token|secret|password|pass|api.?key|hash|signature|authorization/i', $safeKey) === 1;
                $line .= ' | ' . $safeKey . '=' . ($sensitiveKey ? '[REDACTED]' : redfox_runtime_log_sanitize($value));
            }
        }
        $logFile = REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'runtime.log';
        if (is_link($logFile)) return;
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        @chmod($logFile, 0640);
    }
}

set_error_handler(function($severity, $message, $file, $line) {


    static $rx_suppressed_severities = null;
    if ($rx_suppressed_severities === null) {
        $rx_suppressed_severities = [
            E_WARNING, E_USER_WARNING,
            E_NOTICE, E_USER_NOTICE,
            E_DEPRECATED, E_USER_DEPRECATED,
            E_STRICT,
        ];
    }
    if (in_array($severity, $rx_suppressed_severities, true)) {
        return true;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    rx_log_event('PHP_ERROR', 'PHP error raised', ['severity' => $severity, 'file' => basename((string)$file), 'line' => $line]);
    return false;
});

set_exception_handler(function($e) {
    rx_log_event('UNCAUGHT_THROWABLE', 'Unhandled throwable', [
        'class' => get_class($e),
        'code' => (string)$e->getCode(),
        'file' => basename((string)$e->getFile()),
        'line' => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Internal error. Check logs/runtime.log\n";
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        rx_log_event('FATAL_SHUTDOWN', 'Fatal PHP shutdown', [
            'severity' => $err['type'],
            'file' => basename((string)$err['file']),
            'line' => $err['line'],
        ]);
        if (!headers_sent()) {
            http_response_code(500);
        }
    }
    $status = function_exists('http_response_code') ? http_response_code() : null;
    if ((int)$status >= 500) {
        rx_log_event('HTTP_5XX', 'Request finished with server error status', ['status' => $status]);
    }
});
