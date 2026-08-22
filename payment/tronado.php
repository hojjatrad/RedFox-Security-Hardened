<?php
/** Authenticated Tronado callback. Requires provider HMAC support/configuration. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$log = static function (string $event, array $context = []): void {
    unset($context['secret'], $context['signature'], $context['body']);
    error_log('[tronado] ' . $event . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''));
};
$reject = static function (int $code, string $message, string $event, array $context = []) use ($log): void {
    $log($event, $context);
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $reject(405, 'Method Not Allowed', 'REJECT_METHOD');
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') $reject(415, 'JSON required', 'REJECT_CONTENT_TYPE');
$length = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($length === false || $length < 2 || $length > 131072) $reject(413, 'Invalid body size', 'REJECT_SIZE');
$rawBody = file_get_contents('php://input', false, null, 0, 131073);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 131072) $reject(400, 'Invalid body', 'REJECT_BODY');

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
$callbackRate = redfox_login_rate_check('callback-tronado', redfox_client_ip(), 120, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    $reject(429, 'Too Many Requests', 'REJECT_RATE');
}

$secretStmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay='tronado_webhook_secret' LIMIT 1");
$secretStmt->execute();
$secret = trim((string)($secretStmt->fetchColumn() ?: ''));
if ($secret === '' || $secret === '0' || strlen($secret) < 16) {
    $reject(503, 'Webhook is not configured', 'SECRET_NOT_CONFIGURED');
}
$signature = trim((string)($_SERVER['HTTP_X_TRONADO_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? ''));
if (stripos($signature, 'sha256=') === 0) $signature = substr($signature, 7);
$signature = strtolower($signature);
if (!preg_match('/^[a-f0-9]{64}$/', $signature)) $reject(401, 'Missing signature', 'SIGNATURE_MISSING');
$expected = hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $signature)) $reject(401, 'Invalid signature', 'SIGNATURE_MISMATCH');

$data = json_decode($rawBody, true, 32, JSON_BIGINT_AS_STRING);
if (!is_array($data)) $reject(400, 'Invalid JSON', 'REJECT_JSON');
$isPaid = $data['IsPaid'] ?? false;
$isPaid = $isPaid === true || $isPaid === 1 || (is_string($isPaid) && in_array(strtolower($isPaid), ['1', 'true'], true));
if (!$isPaid) {
    http_response_code(200);
    exit('ignored');
}
$paymentId = trim((string)($data['PaymentID'] ?? ''));
$orderId = trim((string)($data['OrderId'] ?? ''));
$metadataId = trim((string)($data['Metadata']['PaymentID'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $paymentId)
    || $orderId === '' || !hash_equals($paymentId, $orderId)
    || ($metadataId !== '' && !hash_equals($paymentId, $metadataId))) {
    $reject(400, 'Invalid payment binding', 'INVALID_BINDING');
}

$stmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order=:order_id LIMIT 1');
$stmt->execute([':order_id' => $paymentId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($report)) $reject(404, 'Order not found', 'ORDER_NOT_FOUND', ['order_id' => $paymentId]);
if ((string)($report['Payment_Method'] ?? '') !== 'Currency Rial 2'
    || strtolower((string)($report['provider_name'] ?? '')) !== 'tronado') {
    $reject(409, 'Provider mismatch', 'PROVIDER_MISMATCH', ['order_id' => $paymentId]);
}
if (strtolower((string)($report['payment_Status'] ?? '')) === 'expire') {
    $reject(409, 'Expired order requires review', 'EXPIRED_PAYMENT', ['order_id' => $paymentId]);
}
$reported = isset($data['TronAmount']) && is_numeric($data['TronAmount']) ? (float)$data['TronAmount'] : -1;
$actual = isset($data['ActualTronAmount']) && is_numeric($data['ActualTronAmount']) ? (float)$data['ActualTronAmount'] : -1;
$expectedAmount = (float)($report['provider_amount'] ?? -1);
$tolerance = max(0.000001, abs($expectedAmount) * 0.000001);
if ($expectedAmount <= 0 || $reported <= 0 || $actual <= 0
    || abs($reported - $expectedAmount) > $tolerance || $actual + $tolerance < $expectedAmount) {
    $reject(409, 'Amount mismatch', 'AMOUNT_MISMATCH', ['order_id' => $paymentId]);
}

$providerPaymentId = trim((string)($data['Hash'] ?? $data['TransactionId'] ?? ''));
if ($providerPaymentId === '' || strlen($providerPaymentId) > 191 || !preg_match('/^[A-Za-z0-9_:\-]+$/', $providerPaymentId)) {
    $reject(400, 'Transaction identifier missing', 'TRANSACTION_ID_MISSING');
}
$bind = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:provider_id WHERE id=:id AND provider_name='tronado' AND (provider_payment_id IS NULL OR provider_payment_id='' OR provider_payment_id=:provider_id_same)");
$bind->execute([':provider_id' => $providerPaymentId, ':provider_id_same' => $providerPaymentId, ':id' => (int)$report['id']]);
$bound = $pdo->prepare('SELECT provider_payment_id FROM Payment_report WHERE id=:id');
$bound->execute([':id' => (int)$report['id']]);
if (!hash_equals($providerPaymentId, (string)$bound->fetchColumn())) {
    $reject(409, 'Transaction mismatch', 'TRANSACTION_MISMATCH', ['order_id' => $paymentId]);
}

$ManagePanel = new ManagePanel();
$paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
$result = payment_confirm_paid($paymentId, 'chashbackiranpay2', [
    'source' => 'tronado-hmac',
    'expected_method' => 'Currency Rial 2',
    'method' => 'ترونادو',
    'thread_id' => $paymentreports,
    'provider_payment_id' => $providerPaymentId,
    'link_label' => 'مشاهده تراکنش',
    'link_url' => 'https://tronscan.org/#/transaction/' . rawurlencode($providerPaymentId),
]);
if (empty($result['ok'])) {
    $log('CONFIRM_NOT_COMPLETED', ['order_id' => $paymentId, 'status' => $result['status'] ?? null, 'error' => $result['error'] ?? null]);
    http_response_code(in_array(($result['status'] ?? ''), ['claimed', 'fulfillment_done'], true) ? 202 : 409);
    exit('not-completed');
}
$log('CONFIRMED', ['order_id' => $paymentId, 'transaction_id' => $providerPaymentId]);
http_response_code(200);
echo 'ok';
