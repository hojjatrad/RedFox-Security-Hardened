<?php
/** Main Telegram webhook entry point. */
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
require_once __DIR__ . '/config.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length < 1 || $length > 1048576 || !($pdo instanceof PDO)) {
    http_response_code($pdo instanceof PDO ? 413 : 503);
    exit;
}
try {
    $stmt = $pdo->query('SELECT webhook_secret_token FROM setting LIMIT 1');
    $storedSecret = trim((string)($stmt ? $stmt->fetchColumn() : ''));
} catch (Throwable $e) {
    $storedSecret = '';
}
$incomingSecret = trim((string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
if (strlen($storedSecret) < 32 || $incomingSecret === '' || !hash_equals($storedSecret, $incomingSecret)) {
    http_response_code(401);
    exit;
}

define('REDFOX_ALLOW_BOTAPI_ROUTER', true);
require __DIR__ . '/re/index.php';
