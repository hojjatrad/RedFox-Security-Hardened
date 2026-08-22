<?php


declare(strict_types=1);

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}

ob_start();

@ini_set('display_errors',         '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors',             '1');
error_reporting(E_ALL);

$GLOBALS['__miniapp_response_sent'] = false;

function __miniapp_emit(int $http, array $payload): void
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
    $GLOBALS['__miniapp_response_sent'] = true;
}

register_shutdown_function(static function () {
    if (!empty($GLOBALS['__miniapp_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        error_log('[miniapp] fatal type ' . (int)($err['type'] ?? 0) . ' in ' . basename((string)($err['file'] ?? 'unknown')) . ':' . (int)($err['line'] ?? 0));
        __miniapp_emit(500, [
            'status' => false,
            'msg'    => 'Internal server error',
            'obj'    => [],
        ]);
        return;
    }
    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'obj'    => [],
    ]);
});

try {
    require_once __DIR__ . '/lib/Bootstrap.php';
    require_once __DIR__ . '/handlers/BaseHandler.php';

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        RedFoxResponse::fail(405, 'Method not allowed');
    }
    if (strlen((string)($_SERVER['QUERY_STRING'] ?? '')) > 4096) {
        RedFoxResponse::fail(414, 'Request URI too long');
    }
    $expectedOrigin = redfox_configured_origin();
    $requestOrigin = rtrim(trim((string)($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
    if ($expectedOrigin === '' || ($method === 'POST' && $requestOrigin === '')
        || ($requestOrigin !== '' && !hash_equals($expectedOrigin, $requestOrigin))) {
        RedFoxResponse::fail(403, 'Origin denied');
    }
    $preAuthRate = redfox_login_rate_check('miniapp-preauth-ip', redfox_client_ip(), 180, 60);
    if (empty($preAuthRate['allowed'])) {
        header('Retry-After: ' . max(1, (int)($preAuthRate['retry_after'] ?? 60)));
        RedFoxResponse::fail(429, 'Rate limited');
    }
    $token = RedFoxAuth::extractBearerToken();
    if ($token === null) {
        RedFoxResponse::unauthorized('Authorization header missing or malformed');
    }
    $user = RedFoxAuth::userFromToken($token);
    if ($user === null) {
        RedFoxResponse::forbidden('Token invalid');
    }
    if (($user['User_Status'] ?? '') === 'block') {
        RedFoxResponse::fail(403, 'user blocked');
    }
    $userId = (string)($user['id'] ?? '');
    if ($userId === '' || !ctype_digit($userId)) {
        RedFoxResponse::forbidden('Token invalid');
    }
    $userRate = redfox_login_rate_check('miniapp-user', $userId, 120, 60);
    if (empty($userRate['allowed'])) {
        header('Retry-After: ' . max(1, (int)($userRate['retry_after'] ?? 60)));
        RedFoxResponse::fail(429, 'Rate limited');
    }
    if ($method === 'POST') {
        $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
        $allowedTypes = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];
        if (!in_array($contentType, $allowedTypes, true)) {
            RedFoxResponse::fail(415, 'Unsupported media type');
        }
        $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
        $maxBody = $contentType === 'multipart/form-data' ? 5 * 1024 * 1024 : 131072;
        if ($contentLength === false || $contentLength < 1 || $contentLength > $maxBody) {
            RedFoxResponse::fail(413, 'Invalid payload size');
        }
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    $actions = [
        'user_info'              => 'UserInfoHandler',
        'invoices'               => 'InvoicesHandler',
        'service'                => 'ServiceHandler',
        'countries'              => 'CountriesHandler',
        'categories'             => 'CategoriesHandler',
        'time_ranges'            => 'TimeRangesHandler',
        'services'               => 'ServicesHandler',
        'custom_price'           => 'CustomPriceHandler',
        'purchase'               => 'PurchaseHandler',
        'payment_methods'        => 'PaymentMethodsHandler',
        'payment_init'           => 'PaymentInitHandler',
        'payment_receipt'        => 'PaymentReceiptHandler',
        'payment_status'         => 'PaymentStatusHandler',
        'crypto_currencies'      => 'CryptoCurrenciesHandler',
        'crypto_invoice_init'    => 'CryptoInvoiceInitHandler',
        'crypto_submit_hash'     => 'CryptoSubmitHashHandler',
        'crypto_cancel_invoice'  => 'CryptoCancelInvoiceHandler',
        'service_action'         => 'ServiceActionHandler',
        'service_renew_options'  => 'ServiceRenewOptionsHandler',
        'service_renew_confirm'  => 'ServiceRenewConfirmHandler',
        'service_extra_quote'    => ['class' => 'ServiceExtraHandler', 'mode' => 'quote'],
        'service_extra_confirm'  => ['class' => 'ServiceExtraHandler', 'mode' => 'confirm'],
        'service_simple_action'  => 'ServiceSimpleActionHandler',
        'brand_info'             => ['class' => 'BrandHandler', 'mode' => 'info'],
        'brand_save'             => ['class' => 'BrandHandler', 'mode' => 'save'],
        'brand_upload_logo'      => ['class' => 'BrandHandler', 'mode' => 'upload'],
        'service_configs'        => 'ServiceConfigsHandler',
        'pending_payments'       => 'PendingPaymentsHandler',
        'redeem_giftcode'        => 'GiftCodeHandler',
        'discount_validate'      => 'DiscountValidateHandler',
    ];

    $payload = RedFoxInput::payload();
    $action = RedFoxInput::string($payload, 'actions');

    if ($action === '' || !isset($actions[$action])) {
        RedFoxResponse::badRequest('Action invalid');
    }

    if ($method === 'GET' && $action !== 'brand_info') {
        RedFoxResponse::fail(405, 'POST required');
    }
    if ($method === 'POST' && ($contentType ?? '') === 'multipart/form-data'
        && !in_array($action, ['brand_upload_logo', 'payment_receipt'], true)) {
        RedFoxResponse::fail(415, 'Multipart is not accepted for this action');
    }

    $entry = $actions[$action];
    if (is_string($entry)) {
        $handlerClass = $entry;
        $handlerMode = null;
    } else {
        $handlerClass = (string)($entry['class'] ?? '');
        $handlerMode = $entry['mode'] ?? null;
    }

    $handlerFile = __DIR__ . '/handlers/' . $handlerClass . '.php';
    if (!is_file($handlerFile)) {
        RedFoxLogger::critical('Handler file missing', ['handler' => $handlerClass]);
        RedFoxResponse::serverError('Handler not available');
    }
    require_once $handlerFile;

    if (!class_exists($handlerClass)) {
        RedFoxLogger::critical('Handler class missing', ['handler' => $handlerClass]);
        RedFoxResponse::serverError('Handler class not loadable');
    }


    $handler = new $handlerClass($user, $payload);
    if ($handlerMode !== null && property_exists($handler, 'mode')) {
        $handler->mode = $handlerMode;
    }
    $handler->handle();

    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'obj'    => [],
    ]);
} catch (Throwable $e) {
    if ($e instanceof InvalidArgumentException) {
        __miniapp_emit(400, ['status' => false, 'msg' => 'Invalid request payload', 'obj' => []]);
        return;
    }
    if (class_exists('RedFoxLogger')) {
        try {
            RedFoxLogger::exception($e, 'miniapp.php top-level exception');
        } catch (Throwable $_) {  }
    }
    __miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'obj'    => [],
    ]);
}

