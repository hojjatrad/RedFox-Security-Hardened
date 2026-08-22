<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib/Auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function rx_clientlog_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$expectedOrigin = redfox_configured_origin();
$requestOrigin = rtrim(trim((string)($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
if ($expectedOrigin === '') {
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'service unavailable']);
}
if ($requestOrigin === '' || !hash_equals($expectedOrigin, $requestOrigin)) {
    rx_clientlog_reply(403, ['ok'=>false, 'msg'=>'origin denied']);
}
header('Access-Control-Allow-Origin: ' . $expectedOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rx_clientlog_reply(405, ['ok'=>false, 'msg'=>'POST only']);
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    rx_clientlog_reply(415, ['ok'=>false, 'msg'=>'application/json required']);
}
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength === false || $contentLength < 2 || $contentLength > 16384) {
    rx_clientlog_reply(413, ['ok'=>false, 'msg'=>'payload size denied']);
}

$raw = file_get_contents('php://input', false, null, 0, 16385);
if (!is_string($raw) || $raw === '' || strlen($raw) > 16384) {
    rx_clientlog_reply(400, ['ok'=>false, 'msg'=>'invalid payload']);
}
$payload = json_decode($raw, true, 16, JSON_BIGINT_AS_STRING);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE || count($payload) > 16) {
    rx_clientlog_reply(400, ['ok'=>false, 'msg'=>'invalid json']);
}
foreach (['msg', 'where', 'stack', 'level'] as $field) {
    if (isset($payload[$field]) && !is_string($payload[$field])) {
        rx_clientlog_reply(400, ['ok'=>false, 'msg'=>'invalid payload']);
    }
}
if (isset($payload['diag']) && !is_array($payload['diag'])) {
    rx_clientlog_reply(400, ['ok'=>false, 'msg'=>'invalid payload']);
}

$botToken = is_string($APIKEY ?? null) ? trim($APIKEY) : '';
$initData = isset($payload['initData']) && is_string($payload['initData']) ? $payload['initData'] : '';
unset($payload['initData'], $payload['init_data'], $payload['initDataUnsafe']);
if ($botToken === '' || $botToken === '0') {
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'authentication unavailable']);
}
try {
    $telegramUser = RedFoxAuth::validateInitData($initData, $botToken);
} catch (Throwable $e) {
    rx_clientlog_reply(401, ['ok'=>false, 'msg'=>'invalid Telegram authentication']);
}
$userId = (string)($telegramUser['id'] ?? '');
if ($userId === '' || !ctype_digit($userId)) {
    rx_clientlog_reply(401, ['ok'=>false, 'msg'=>'invalid Telegram user']);
}

$ip = redfox_client_ip();
$ipRate = redfox_login_rate_check('clientlog-ip', $ip, 30, 60);
$userRate = redfox_login_rate_check('clientlog-user', $userId, 20, 60);
if (empty($ipRate['allowed']) || empty($userRate['allowed'])) {
    $retryAfter = max(1, (int)($ipRate['retry_after'] ?? 0), (int)($userRate['retry_after'] ?? 0));
    header('Retry-After: ' . $retryAfter);
    rx_clientlog_reply(429, ['ok'=>false, 'msg'=>'rate limited']);
}

function rx_clientlog_redact(string $value, int $limit): string
{
    $value = substr($value, 0, $limit);
    $value = (string)preg_replace('/(?<![A-Za-z0-9_])\d{6,20}:[A-Za-z0-9_-]{20,100}(?![A-Za-z0-9_-])/', '[REDACTED_BOT_TOKEN]', $value);
    $value = (string)preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=:-]+/i', '$1 [REDACTED]', $value);
    $value = (string)preg_replace('/([?&](?:token|secret|password|passwd|authorization|api[_-]?key|key|data|initData)=)[^&#\s]*/i', '$1[REDACTED]', $value);
    $value = (string)preg_replace('#\b(?:vless|vmess|trojan|ss)://[^\s"<>]+#i', '[REDACTED_SUBSCRIPTION_URI]', $value);
    return $value;
}

$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
$refParts = parse_url($ref);
$refSafe = is_array($refParts)
    ? (($refParts['scheme'] ?? '') . '://' . ($refParts['host'] ?? '') . ($refParts['path'] ?? ''))
    : '';
$level = strtolower(trim((string)($payload['level'] ?? 'error')));
if (!in_array($level, ['error','warning','info'], true)) $level = 'error';
$entry = [
    'ts'      => date('c'),
    'user_id' => $userId,
    'ip'      => $ip,
    'ua'      => rx_clientlog_redact((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 400),
    'ref'     => rx_clientlog_redact($refSafe, 400),
    'level'   => $level,
    'msg'     => rx_clientlog_redact(isset($payload['msg']) ? (string)$payload['msg'] : '', 1000),
    'where'   => rx_clientlog_redact(isset($payload['where']) ? (string)$payload['where'] : '', 400),
    'stack'   => rx_clientlog_redact(isset($payload['stack']) ? (string)$payload['stack'] : '', 4000),
    'diag'    => ['bootStarted' => !empty($payload['diag']['bootStarted'])],
];
if ($entry['msg'] === '' || stripos($entry['msg'], 'Script error') === 0) {
    rx_clientlog_reply(200, ['ok'=>true]);
}

$dedupKey = $userId . '|' . $entry['level'] . '|' . substr($entry['msg'], 0, 120) . '|' . substr($entry['where'], 0, 80);
$dedupDir = sys_get_temp_dir() . '/redfox_log_dedup';
if (is_link($dedupDir)
    || (!is_dir($dedupDir) && !@mkdir($dedupDir, 0700, true))
    || !is_dir($dedupDir)) {
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
}
@chmod($dedupDir, 0700);
$dedupFile = $dedupDir . '/' . hash('sha256', $dedupKey);
if (is_link($dedupFile)) rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
$dedupHandle = @fopen($dedupFile, 'c+');
if (!is_resource($dedupHandle) || !flock($dedupHandle, LOCK_EX)) {
    if (is_resource($dedupHandle)) fclose($dedupHandle);
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
}
$duplicate = is_file($dedupFile) && (time() - (int)@filemtime($dedupFile)) < 21600 && (int)@filesize($dedupFile) > 0;
if (!$duplicate) {
    ftruncate($dedupHandle, 0);
    rewind($dedupHandle);
    fwrite($dedupHandle, (string)time());
    fflush($dedupHandle);
    @touch($dedupFile);
}
flock($dedupHandle, LOCK_UN);
fclose($dedupHandle);
@chmod($dedupFile, 0600);
if ($duplicate) rx_clientlog_reply(200, ['ok'=>true]);

$line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($line)) {
    rx_clientlog_reply(400, ['ok'=>false, 'msg'=>'unencodable log entry']);
}
$line .= PHP_EOL;

$logDir = dirname(__DIR__) . '/logs';
if (is_link($logDir)
    || (!is_dir($logDir) && !@mkdir($logDir, 0750, true))
    || !is_dir($logDir)) {
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
}
@chmod($logDir, 0750);
$logFile = $logDir . '/client-' . date('Y-m-d') . '.log';
$lockFile = $logDir . '/.clientlog.lock';
if (is_link($logFile) || is_link($lockFile)) rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
$lockHandle = @fopen($lockFile, 'c+');
if (!is_resource($lockHandle) || !flock($lockHandle, LOCK_EX)) {
    if (is_resource($lockHandle)) fclose($lockHandle);
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
}

$dailyLimit = 5 * 1024 * 1024;
$totalLimit = 50 * 1024 * 1024;
$retainAfter = time() - (14 * 86400);
$files = array_values(array_filter(glob($logDir . '/client-????-??-??.log') ?: [], static function (string $file): bool {
    return is_file($file) && !is_link($file);
}));
foreach ($files as $file) {
    if ($file !== $logFile && (int)@filemtime($file) < $retainAfter) @unlink($file);
}
$files = array_values(array_filter(glob($logDir . '/client-????-??-??.log') ?: [], static function (string $file): bool {
    return is_file($file) && !is_link($file);
}));
usort($files, static function (string $a, string $b): int { return ((int)@filemtime($a)) <=> ((int)@filemtime($b)); });
$totalSize = 0;
foreach ($files as $file) $totalSize += max(0, (int)@filesize($file));
while ($totalSize + strlen($line) > $totalLimit && $files !== []) {
    $oldest = array_shift($files);
    if ($oldest === $logFile) continue;
    $oldSize = max(0, (int)@filesize($oldest));
    if (@unlink($oldest)) $totalSize -= $oldSize;
}
$currentSize = is_file($logFile) ? max(0, (int)@filesize($logFile)) : 0;
if ($currentSize + strlen($line) > $dailyLimit || $totalSize + strlen($line) > $totalLimit) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    rx_clientlog_reply(429, ['ok'=>false, 'msg'=>'daily logging quota reached']);
}
$written = @file_put_contents($logFile, $line, FILE_APPEND);
if ($written !== false) @chmod($logFile, 0640);
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
if ($written === false) {
    rx_clientlog_reply(503, ['ok'=>false, 'msg'=>'logging unavailable']);
}

rx_clientlog_reply(200, ['ok'=>true]);
