<?php

declare(strict_types=1);

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
@ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';

$sendError = static function (int $status): never {
    while (ob_get_level() > 0) @ob_end_clean();
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo "Subscription unavailable\n";
    exit;
};

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    $sendError(405);
}
if ((string)($_SERVER['QUERY_STRING'] ?? '') !== '') $sendError(400);

$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/sub/index.php'))), '/');
$prefix = $scriptDir === '' || $scriptDir === '.' ? '/sub' : $scriptDir;
if (preg_match('#^' . preg_quote($prefix, '#') . '/([a-f0-9]{64})/?$#i', $path, $match) !== 1) {
    $sendError(404);
}
$token = strtolower($match[1]);
$ipRate = redfox_login_rate_check('subscription-ip', redfox_client_ip(), 120, 60);
$tokenRate = redfox_login_rate_check('subscription-token', hash('sha256', $token), 60, 60);
if (empty($ipRate['allowed']) || empty($tokenRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($ipRate['retry_after'] ?? 60), (int)($tokenRate['retry_after'] ?? 60)));
    $sendError(429);
}

try {
    $stmt = $pdo->prepare('SELECT * FROM invoice WHERE subscription_token=:token LIMIT 2');
    $stmt->execute([':token' => $token]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) $sendError(404);
    $invoice = $rows[0];
    $userId = trim((string)($invoice['id_user'] ?? ''));
    $panel = trim((string)($invoice['Service_location'] ?? ''));
    $username = trim((string)($invoice['username'] ?? ''));
    if ($userId === '' || $panel === '' || $username === '' || strlen($panel) > 191 || strlen($username) > 191) {
        $sendError(404);
    }
    $stmt = $pdo->prepare('SELECT User_Status FROM user WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    if (strtolower((string)$stmt->fetchColumn()) === 'block') $sendError(403);

    $manager = new ManagePanel();
    $result = $manager->DataUser($panel, $username);
    if (!is_array($result) || strtolower((string)($result['status'] ?? '')) === 'unsuccessful') $sendError(502);
    $rawLinks = $result['links'] ?? [];
    if (is_string($rawLinks)) $rawLinks = preg_split('/\r?\n/', $rawLinks) ?: [];
    if (!is_array($rawLinks) || count($rawLinks) > 256) $sendError(502);
    $links = [];
    $total = 0;
    foreach ($rawLinks as $link) {
        if (!is_scalar($link)) continue;
        $link = trim((string)$link);
        if ($link === '' || strlen($link) > 16384 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $link)) continue;
        $total += strlen($link) + 2;
        if ($total > 2097152) $sendError(502);
        $links[] = $link;
    }
    if (!$links) $sendError(404);

    while (ob_get_level() > 0) @ob_end_clean();
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    if ($method !== 'HEAD') echo implode("\r\n", $links) . "\r\n";
} catch (Throwable $e) {
    error_log('[subscription] request failed: ' . get_class($e));
    $sendError(503);
}
