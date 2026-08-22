<?php
/**
 * Fail-closed authentication shared by the legacy integration API.
 * Only setting.integration_secret_token is trusted. Bot tokens, files and query
 * parameters are deliberately never accepted as integration credentials.
 */
function rx_integration_request_token(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!is_array($headers)) $headers = [];
    $normalized = [];
    foreach ($headers as $name => $value) {
        if (is_scalar($value)) $normalized[strtolower((string)$name)] = trim((string)$value);
    }

    $authorization = trim((string)($normalized['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '')));
    if ($authorization !== '' && strlen($authorization) <= 512 && preg_match('/^Bearer\s+(\S+)$/i', $authorization, $m)) {
        $token = (string)$m[1];
        return strlen($token) <= 256 ? $token : '';
    }
    $token = trim((string)($normalized['token'] ?? ($_SERVER['HTTP_TOKEN'] ?? '')));
    return strlen($token) <= 256 ? $token : '';
}

function rx_integration_redacted_headers(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!is_array($headers)) return [];
    foreach ($headers as $name => $value) {
        if (in_array(strtolower((string)$name), ['authorization', 'token', 'cookie'], true)) {
            $headers[$name] = '[REDACTED]';
        }
    }
    return $headers;
}

function rx_integration_reject(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($status === 405) header('Allow: POST');
    echo json_encode(['status' => false, 'msg' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function rx_require_integration_auth(PDO $pdo): string
{
    $stored = '';
    try {
        $stmt = $pdo->query('SELECT integration_secret_token FROM setting LIMIT 1');
        $stored = trim((string)($stmt ? $stmt->fetchColumn() : ''));
    } catch (Throwable $e) {
        $stored = '';
    }
    $provided = rx_integration_request_token();
    if (strlen($stored) < 32 || $provided === '' || !hash_equals($stored, $provided)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => false, 'msg' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $stored;
}

/** Authenticate and parse one bounded JSON mutation request. */
function rx_require_integration_json(PDO $pdo, int $maxBytes = 65536): array
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        rx_integration_reject(405, 'Method not allowed');
    }
    $rate = redfox_login_rate_check('legacy-integration-api', redfox_client_ip(), 120, 60);
    if (empty($rate['allowed'])) {
        header('Retry-After: ' . max(1, (int)($rate['retry_after'] ?? 60)));
        rx_integration_reject(429, 'Too many requests');
    }
    $maxBytes = max(1024, min($maxBytes, 1048576));
    $declared = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($declared < 1 || $declared > $maxBytes) {
        rx_integration_reject(413, 'Invalid body size');
    }
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        rx_integration_reject(415, 'JSON body required');
    }
    rx_require_integration_auth($pdo);
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (!is_string($raw) || $raw === '' || strlen($raw) > $maxBytes) {
        rx_integration_reject(400, 'Invalid body');
    }
    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    } catch (Throwable $error) {
        error_log('[integration-api] invalid JSON: ' . redfox_exception_fingerprint($error));
        rx_integration_reject(400, 'Invalid JSON');
    }
    if (!is_array($decoded)) {
        rx_integration_reject(400, 'JSON object required');
    }
    return $decoded;
}
