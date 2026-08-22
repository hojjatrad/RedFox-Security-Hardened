<?php
declare(strict_types=1);

function rx_request_id(): string
{
    static $id = null;
    if ($id !== null) return $id;
    $incoming = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $id = preg_match('/^[A-Za-z0-9_.:-]{8,80}$/', $incoming)
        ? $incoming
        : bin2hex(random_bytes(12));
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('X-Request-ID: ' . $id);
    return $id;
}

function rx_redact($value)
{
    $keys = ['token','password','secret','api_key','authorization','cookie','merchant'];
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $name = strtolower((string)$k);
            $hide = false;
            foreach ($keys as $word) {
                if (str_contains($name, $word)) { $hide = true; break; }
            }
            $value[$k] = $hide ? '[REDACTED]' : rx_redact($v);
        }
        return $value;
    }
    if (is_string($value)) {
        $value = preg_replace('/\b\d{8,12}:[A-Za-z0-9_-]{30,}\b/', '[TELEGRAM_TOKEN_REDACTED]', $value);
        $value = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $value);
    }
    return $value;
}

function rx_log_event_structured(string $level, string $event, array $context = []): void
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/application.jsonl';
    if (is_file($file) && (int)filesize($file) > 5 * 1024 * 1024) @rename($file, $file . '.1');
    $row = [
        'ts' => gmdate('c'), 'level' => strtoupper($level), 'event' => $event,
        'request_id' => rx_request_id(), 'context' => rx_redact($context),
    ];
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

rx_request_id();
