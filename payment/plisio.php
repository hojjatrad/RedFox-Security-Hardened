<?php


if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 131072) {
    http_response_code(413);
    exit('Invalid body size');
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/x-www-form-urlencoded') {
    http_response_code(415);
    exit('Form body required');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../lib/PaymentConfirm.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();
$callbackRate = redfox_login_rate_check('callback-plisio', redfox_client_ip(), 120, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    http_response_code(429);
    exit('Too Many Requests');
}
$setting = select("setting", "*");
$reportRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
$paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;


function rxPlisio_log($event, $msg, $ctx = []) {
    if (function_exists('rx_log_event')) {
        rx_log_event($event, $msg, $ctx);
    } else {
        error_log('[plisio-cb] ' . $event . ': ' . $msg . ' ' . json_encode($ctx));
    }
}


function rxPlisio_readBody(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return [];
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length < 1 || $length > 131072) return [];
    return is_array($_POST) ? $_POST : [];
}


function rxPlisio_stringifyScalars(array $data): array
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = rxPlisio_stringifyScalars($v);
        } elseif (is_bool($v)) {
            $data[$k] = $v ? '1' : '';
        } elseif ($v === null) {
            $data[$k] = '';
        } elseif (is_int($v) || is_float($v)) {
            $data[$k] = (string) $v;
        }
    }
    return $data;
}


function rxPlisio_verifySignature(array $data, string $apiKey): bool
{
    if ($apiKey === '') return false;
    if (empty($data['verify_hash']) || !is_string($data['verify_hash'])) return false;

    $received = strtolower((string)$data['verify_hash']);
    if (!preg_match('/^[a-f0-9]{40}$/', $received)) return false;
    unset($data['verify_hash']);
    ksort($data);
    if (isset($data['expire_utc'])) $data['expire_utc'] = (string)$data['expire_utc'];
    if (isset($data['tx_urls']) && is_string($data['tx_urls'])) {
        $data['tx_urls'] = html_entity_decode($data['tx_urls'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $postString = serialize($data);
    $expected = hash_hmac('sha1', $postString, $apiKey);
    return hash_equals($expected, $received);
}


$data = rxPlisio_readBody();
if (empty($data)) {
    http_response_code(400);
    rxPlisio_log('PLISIO_BAD_BODY', 'Empty body', ['ip' => redfox_client_ip()]);
    exit('Empty body');
}

$apiKey = trim((string)(select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select")['ValuePay'] ?? ''));
if ($apiKey === '' || $apiKey === '0' || strlen($apiKey) < 16) {
    http_response_code(503);
    rxPlisio_log('PLISIO_NO_KEY', 'API key not configured', []);
    exit('no api key');
}

if (!rxPlisio_verifySignature($data, $apiKey)) {
    http_response_code(401);
    rxPlisio_log('PLISIO_BAD_SIGNATURE', 'Callback signature rejected', []);
    exit('invalid signature');
}

$status     = strtolower(trim((string)($data['status']        ?? '')));
$orderId    = trim((string)($data['order_number'] ?? ''));
$txnId      = trim((string)($data['txn_id']       ?? ''));
$invoiceTot = (string)($data['invoice_total_sum'] ?? '0');
$invoiceUrl = (string)($data['invoice_url']       ?? '');
$sourceAmt  = trim((string)($data['source_amount']   ?? ''));
$sourceCur  = trim((string)($data['source_currency'] ?? ''));
$invoiceCur = trim((string)($data['currency']        ?? ''));
$paidAmount = '';
if ($sourceAmt !== '' && $sourceAmt !== '0') {
    $paidAmount = $sourceAmt;
} elseif ($invoiceTot !== '' && $invoiceTot !== '0') {
    $paidAmount = $invoiceTot;
}
$paidCurrency = $sourceCur !== '' ? $sourceCur : $invoiceCur;


if ($status === 'new' || $status === 'pending') {
    http_response_code(200);
    echo 'ok';
    return;
}

if ($orderId === '' && $txnId === '') {
    http_response_code(400);
    rxPlisio_log('PLISIO_NO_REF', 'Missing order_number/txn_id', []);
    exit('no ref');
}


$Payment_report = null;
if ($orderId !== '' && preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $orderId)) {
    $Payment_report = select('Payment_report', '*', 'id_order', $orderId, 'select');
}

if (!is_array($Payment_report) || empty($Payment_report)) {
    http_response_code(404);
    rxPlisio_log('PLISIO_REPORT_MISSING', 'Payment_report not found', [
        'order' => $orderId, 'txn' => $txnId,
    ]);
    exit('not found');
}
if ((string)($Payment_report['Payment_Method'] ?? '') !== 'plisio'
    || strtolower((string)($Payment_report['provider_name'] ?? '')) !== 'plisio'
    || $txnId === ''
    || !hash_equals((string)($Payment_report['provider_invoice_id'] ?? ''), $txnId)) {
    http_response_code(409);
    rxPlisio_log('PLISIO_BINDING_MISMATCH', 'Provider/order binding rejected', ['order' => $orderId]);
    exit('binding mismatch');
}


if ($status === 'expired') {
    $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  {$Payment_report['price']} تومان";
    payment_mark_expired($Payment_report['id_order'], $textexpire);
    http_response_code(200);
    echo 'ok';
    return;
}
if (in_array($status, ['cancelled', 'mismatch', 'error'], true)) {
    $reasonMap = [
        'cancelled' => 'پرداخت از سمت کاربر/درگاه لغو شد',
        'mismatch'  => 'مبلغ واریزی با فاکتور همخوانی نداشت',
        'error'     => 'خطایی در پردازش پرداخت رخ داد',
    ];
    payment_notify_user_failed($Payment_report['id_order'], $reasonMap[$status] ?? '');
    http_response_code(200);
    echo 'ok';
    return;
}


if ($status !== 'completed') {
    http_response_code(200);
    rxPlisio_log('PLISIO_STATUS_OTHER', 'Unhandled status, acked', ['s' => $status, 'order' => $orderId]);
    echo 'ok';
    return;
}
if (strtolower((string)($Payment_report['payment_Status'] ?? '')) === 'expire') {
    http_response_code(409);
    exit('expired order requires review');
}
$expectedCurrency = strtoupper(trim((string)($Payment_report['provider_currency'] ?? '')));
$callbackCurrency = strtoupper(trim((string)($data['currency'] ?? '')));
$expectedAmount = (float)($Payment_report['provider_amount'] ?? -1);
$receivedAmount = isset($data['amount']) && is_numeric($data['amount']) ? (float)$data['amount'] : -1;
$tolerance = max(0.000001, abs($expectedAmount) * 0.000001);
if ($expectedCurrency === '' || !hash_equals($expectedCurrency, $callbackCurrency)
    || $expectedAmount <= 0 || $receivedAmount + $tolerance < $expectedAmount) {
    http_response_code(409);
    rxPlisio_log('PLISIO_AMOUNT_MISMATCH', 'Currency or amount rejected', ['order' => $orderId]);
    exit('amount mismatch');
}
$bind = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:txn WHERE id=:id AND provider_name='plisio' AND (provider_payment_id IS NULL OR provider_payment_id='' OR provider_payment_id=:txn_same)");
$bind->execute([':txn' => $txnId, ':txn_same' => $txnId, ':id' => (int)$Payment_report['id']]);

if ($paidAmount !== '') {
    try {
        update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}
if ($paidCurrency !== '') {
    try {
        update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}

$paidLine = $paidAmount !== ''
    ? ("📥 مبلغ واریز شده : <b>{$paidAmount}</b>" . ($paidCurrency !== '' ? ' ' . strtoupper($paidCurrency) : ''))
    : "📥 مبلغ واریز شده : —";

$result = payment_confirm_paid(
    $Payment_report['id_order'],
    'chashbackplisio',
    [
        'method'      => 'plisio (webhook)',
        'expected_method' => 'plisio',
        'link_label'  => 'لینک پرداخت plisio',
        'link_url'    => $invoiceUrl,
        'thread_id'   => $paymentreports,
        'extra_lines' => [$paidLine],
    ]
);

if (!empty($result['ok'])) {
    rxPlisio_log('PLISIO_PAID', 'Confirmed via webhook', [
        'order' => $Payment_report['id_order'],
        'user'  => $Payment_report['id_user'],
        'price' => $Payment_report['price'],
    ]);
}

http_response_code(200);
echo 'ok';
