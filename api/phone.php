<?php


declare(strict_types=1);


if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$declaredLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declaredLength < 1 || $declaredLength > 65536) {
    http_response_code(413);
    exit;
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    http_response_code(415);
    exit;
}

ob_start();

@ini_set('display_errors',         '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors',             '1');
error_reporting(E_ALL);


$GLOBALS['__verify_response_sent'] = false;

function __verify_emit(int $http, array $payload): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $GLOBALS['__verify_response_sent'] = true;
}

register_shutdown_function(static function () {
    if (!empty($GLOBALS['__verify_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        error_log('[api-entry] fatal type ' . (int)($err['type'] ?? 0) . ' in ' . basename((string)($err['file'] ?? 'unknown')) . ':' . (int)($err['line'] ?? 0));
        __verify_emit(500, [
            'status' => false,
            'msg'    => 'Internal server error',
            'token'  => null,
        ]);
        return;
    }
    __verify_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'token'  => null,
    ]);
});

try {
    require_once __DIR__ . '/lib/Bootstrap.php';
    if (!redfox_request_origin_is_same_site()) {
        __verify_emit(403, ['status' => false, 'msg' => 'Request origin rejected', 'token' => null]);
        exit;
    }
    $phoneRate = redfox_login_rate_check('miniapp-phone', redfox_client_ip(), 20, 300);
    if (empty($phoneRate['allowed'])) {
        if (!headers_sent()) header('Retry-After: ' . max(1, (int)($phoneRate['retry_after'] ?? 300)));
        __verify_emit(429, ['status' => false, 'msg' => 'Too many requests', 'token' => null]);
        exit;
    }
    require_once __DIR__ . '/handlers/PhoneVerifyHandler.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    PhoneVerifyHandler::run();

    __verify_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'token'  => null,
    ]);
} catch (Throwable $e) {
    redfox_log_exception($e, 'api-entry.uncaught');
    __verify_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'token'  => null,
    ]);
}
