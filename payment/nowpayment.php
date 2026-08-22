<?php
/** Authenticated NOWPayments IPN endpoint. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logFile = __DIR__ . '/../logs/nowpayment_ipn.log';
$log = static function (string $event, array $context = []) use ($logFile): void {
    unset($context['secret'], $context['signature'], $context['raw_body']);
    $line = '[' . date('c') . '] ' . $event . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
};
$reject = static function (int $code, string $public, string $event, array $context = []) use ($log): void {
    $log($event, $context);
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($public);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $reject(405, 'Method Not Allowed', 'REJECT_METHOD');
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') $reject(415, 'JSON required', 'REJECT_CONTENT_TYPE');
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength === false || $contentLength < 2 || $contentLength > 131072) $reject(413, 'Invalid body size', 'REJECT_SIZE', ['length' => $contentLength]);
$rawBody = file_get_contents('php://input', false, null, 0, 131073);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 131072) $reject(400, 'Invalid body', 'REJECT_BODY');
$data = json_decode($rawBody, true, 32, JSON_BIGINT_AS_STRING);
if (!is_array($data)) $reject(400, 'Invalid JSON', 'REJECT_JSON');

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../botapi.php';
    require_once __DIR__ . '/../panels.php';
    require_once __DIR__ . '/../function.php';
    require_once __DIR__ . '/../keyboard.php';
    require_once __DIR__ . '/../jdf.php';
    if (is_file(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../lib/PaymentConfirm.php';
} catch (Throwable $e) {
    $reject(500, 'Bootstrap failed', 'BOOTSTRAP_FAILED', ['error' => get_class($e)]);
}
if (!isset($pdo) || !$pdo instanceof PDO) $reject(500, 'Database unavailable', 'DB_UNAVAILABLE');
$callbackRate = redfox_login_rate_check('callback-nowpayments', redfox_client_ip(), 120, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    $reject(429, 'Too Many Requests', 'REJECT_RATE');
}

$secretStmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay='nowpayment_ipn_secret' LIMIT 1");
$secretStmt->execute();
$ipnSecret = trim((string)($secretStmt->fetchColumn() ?: ''));
if ($ipnSecret === '' || $ipnSecret === '0' || strlen($ipnSecret) < 16) {
    $reject(503, 'IPN is not configured', 'SECRET_NOT_CONFIGURED');
}
$signature = strtolower(trim((string)($_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '')));
if (!preg_match('/^[a-f0-9]{128}$/', $signature)) $reject(401, 'Missing or invalid signature', 'SIGNATURE_MISSING');
ksort($data);
$canonical = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$expected = hash_hmac('sha512', (string)$canonical, $ipnSecret);
if (!hash_equals($expected, $signature)) $reject(401, 'Invalid signature', 'SIGNATURE_MISMATCH');

$status = strtolower(trim((string)($data['payment_status'] ?? '')));
if (!in_array($status, ['finished', 'confirmed', 'sending'], true)) {
    http_response_code(200);
    exit('ignored');
}
$orderId = trim((string)($data['order_id'] ?? ''));
$invoiceId = trim((string)($data['invoice_id'] ?? ''));
$paymentId = trim((string)($data['payment_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $orderId)
    || !preg_match('/^[A-Za-z0-9_\-]{1,191}$/', $invoiceId)
    || !preg_match('/^[A-Za-z0-9_\-]{1,191}$/', $paymentId)) {
    $reject(400, 'Invalid identifiers', 'INVALID_IDENTIFIERS');
}

$stmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order=:order_id LIMIT 1');
$stmt->execute([':order_id' => $orderId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($report)) $reject(404, 'Order not found', 'ORDER_NOT_FOUND', ['order_id' => $orderId]);
if (strtolower((string)($report['Payment_Method'] ?? '')) !== 'nowpayment'
    || strtolower((string)($report['provider_name'] ?? '')) !== 'nowpayments') {
    $reject(409, 'Provider mismatch', 'PROVIDER_MISMATCH', ['order_id' => $orderId]);
}
if (!hash_equals((string)($report['provider_invoice_id'] ?? ''), $invoiceId)) {
    $reject(409, 'Invoice mismatch', 'INVOICE_MISMATCH', ['order_id' => $orderId]);
}
$currency = strtoupper(trim((string)($data['price_currency'] ?? '')));
$expectedCurrency = strtoupper(trim((string)($report['provider_currency'] ?? '')));
$providerAmount = (float)($data['price_amount'] ?? -1);
$expectedAmount = (float)($report['provider_amount'] ?? -2);
$tolerance = max(0.000001, abs($expectedAmount) * 0.000001);
if ($expectedCurrency === '' || !hash_equals($expectedCurrency, $currency)
    || $expectedAmount <= 0 || $providerAmount <= 0 || abs($providerAmount - $expectedAmount) > $tolerance) {
    $reject(409, 'Amount mismatch', 'AMOUNT_MISMATCH', ['order_id' => $orderId]);
}
if (strtolower((string)($report['payment_Status'] ?? '')) === 'expire') {
    $reject(409, 'Expired order requires review', 'EXPIRED_PAYMENT', ['order_id' => $orderId]);
}

$bind = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='nowpayments' AND (provider_payment_id IS NULL OR provider_payment_id='' OR provider_payment_id=:payment_id_same)");
$bind->execute([':payment_id' => $paymentId, ':payment_id_same' => $paymentId, ':id' => (int)$report['id']]);
$bound = $pdo->prepare('SELECT provider_payment_id FROM Payment_report WHERE id=:id');
$bound->execute([':id' => (int)$report['id']]);
if (!hash_equals($paymentId, (string)$bound->fetchColumn())) {
    $reject(409, 'Payment identifier mismatch', 'PAYMENT_ID_MISMATCH', ['order_id' => $orderId]);
}

$ManagePanel = class_exists('ManagePanel') ? new ManagePanel() : null;
$paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
$txHash = trim((string)($data['payin_hash'] ?? ''));
$result = payment_confirm_paid($orderId, 'cashbacknowpayment', [
    'source' => 'nowpayments-ipn',
    'expected_method' => 'nowpayment',
    'method' => 'nowpayment',
    'thread_id' => $paymentreports,
    'provider_invoice_id' => $invoiceId,
    'provider_payment_id' => $paymentId,
    'link_label' => $txHash !== '' ? 'لینک پرداخت' : '',
    'link_url' => $txHash !== '' ? 'https://tronscan.org/#/transaction/' . rawurlencode($txHash) : '',
]);
if (empty($result['ok'])) {
    $log('CONFIRM_NOT_COMPLETED', ['order_id' => $orderId, 'status' => $result['status'] ?? null, 'error' => $result['error'] ?? null]);
    http_response_code(in_array(($result['status'] ?? ''), ['claimed', 'fulfillment_done'], true) ? 202 : 409);
    exit('not-completed');
}
$log('CONFIRMED', ['order_id' => $orderId, 'payment_id' => $paymentId]);
http_response_code(200);
echo 'ok';
