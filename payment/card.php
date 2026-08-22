<?php
declare(strict_types=1);

ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('error_log', __DIR__ . '/../logs/card-webhook.log');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/lib/IntegrationAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Method not allowed']);
    exit;
}
rx_require_integration_auth($pdo);
$rate = redfox_login_rate_check('card-webhook', redfox_client_ip(), 60, 60);
if (empty($rate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($rate['retry_after'] ?? 60)));
    http_response_code(429);
    echo json_encode(['status' => false, 'msg' => 'Too many requests']);
    exit;
}
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength === false || $contentLength < 2 || $contentLength > 16384) {
    http_response_code(413);
    echo json_encode(['status' => false, 'msg' => 'Invalid payload size']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay='statuscardautoconfirm' LIMIT 1");
    $stmt->execute();
    if ((string)$stmt->fetchColumn() !== 'onautoconfirm') {
        http_response_code(503);
        echo json_encode(['status' => false, 'msg' => 'Automatic card confirmation is disabled']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => false, 'msg' => 'Service unavailable']);
    exit;
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if (!in_array($contentType, ['application/json', 'application/x-www-form-urlencoded'], true)) {
    http_response_code(415);
    echo json_encode(['status' => false, 'msg' => 'Unsupported media type']);
    exit;
}
$payload = $_POST;
if ($contentType === 'application/json') {
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    $payload = is_string($raw) ? json_decode($raw, true, 16, JSON_BIGINT_AS_STRING) : null;
    if (json_last_error() !== JSON_ERROR_NONE) $payload = null;
}
if (!is_array($payload) || count($payload) > 8 || !is_string($payload['bank'] ?? null) || !is_string($payload['message'] ?? null)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Invalid payload']);
    exit;
}
$bankInput = strtolower(trim($payload['bank']));
$message = trim($payload['message']);
if ($bankInput === '' || $message === '' || strlen($message) > 12000) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'The bank and message fields are required']);
    exit;
}

$bankAliases = [
    'blu' => 'blu',
    'meli' => 'meli', 'melli' => 'meli',
    'grdsh' => 'gardeshgari', 'gardeshgari' => 'gardeshgari',
    'sadhrat' => 'saderat', 'saderat' => 'saderat',
    'melet' => 'mellat', 'mellat' => 'mellat',
    'terjart' => 'tejarat', 'tejarat' => 'tejarat',
    'keshavarsi' => 'keshavarzi', 'keshavarzi' => 'keshavarzi',
    'resalet' => 'resalat', 'resalat' => 'resalat',
    'sheahr' => 'shahr', 'shahr' => 'shahr',
    'maskan' => 'maskan', 'parsian' => 'parsian',
    'sphe' => 'sepah', 'sepah' => 'sepah',
    'paselc' => 'pasargad', 'pasargad' => 'pasargad',
    'gharz' => 'gharzolhasaneh', 'gharzolhasaneh' => 'gharzolhasaneh',
];
$bank = $bankAliases[$bankInput] ?? '';
if ($bank === '') {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Unsupported bank']);
    exit;
}

/** Convert Persian and Arabic-Indic numerals before applying bank-specific patterns. */
function rxCardNormalizeDigits(string $value): string
{
    return strtr($value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
}

function rxCardExtractToman(string $bank, string $message): ?int
{
    $message = rxCardNormalizeDigits($message);
    $patterns = [
        'blu' => '/([0-9][0-9,]*)\s*ریال\s+به\s+حساب\s+شما\s+نشست/u',
        'meli' => '/انتقال\s*:\s*([0-9][0-9,]*)/u',
        'gardeshgari' => '/مبلغ\s*:\s*([0-9][0-9,]*)/u',
        'saderat' => '/انتقال\s*:\s*([0-9][0-9,]*)/u',
        'mellat' => '/واریز\s*([0-9][0-9,]*)/u',
        'tejarat' => '/واریز\s*:\s*([0-9][0-9,]*)/u',
        'keshavarzi' => '/واريز\s*([0-9][0-9,]*)/u',
        'resalat' => '/\+\s*([0-9][0-9,]*)/u',
        'shahr' => '/مبلغ\s*:\s*([0-9][0-9,]*)\s*ريال/u',
        'maskan' => '/انتقال\s+اينترنت\s*:\D*([0-9][0-9,]*)/u',
        'parsian' => '/مبلغ\s*:\s*([0-9][0-9,]*)\s*\+/u',
        'sepah' => '/مبلغ\s*:\s*([0-9][0-9,]*)\s*ريال/u',
        'pasargad' => '/\+\s*([0-9][0-9,]*)/u',
        'gharzolhasaneh' => '/([0-9][0-9,]*)\s*\+/u',
    ];
    if (!preg_match($patterns[$bank], $message, $match)) return null;
    $rial = filter_var(str_replace(',', '', $match[1]), FILTER_VALIDATE_INT);
    if (!is_int($rial) || $rial <= 0 || $rial > 10000000000000 || $rial % 10 !== 0) return null;
    return intdiv($rial, 10);
}

$amount = rxCardExtractToman($bank, $message);
if ($amount === null) {
    http_response_code(422);
    echo json_encode(['status' => false, 'msg' => 'The bank amount could not be parsed']);
    exit;
}
$eventDigest = hash('sha256', $bank . "\n" . preg_replace('/\s+/u', ' ', rxCardNormalizeDigits($message)));

try {
    $reserve = $pdo->prepare("INSERT INTO card_webhook_events (event_digest,bank,amount,status,received_at) VALUES (?,?,?,'received',?)");
    $reserve->execute([$eventDigest, $bank, $amount, time()]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['status' => false, 'msg' => 'Duplicate bank event']);
        exit;
    }
    rx_log_event_structured('error', 'card_webhook_reservation_failed', ['exception' => get_class($e), 'code' => (string)$e->getCode()]);
    http_response_code(503);
    echo json_encode(['status' => false, 'msg' => 'Event storage unavailable; apply migration 051']);
    exit;
}

$finishEvent = static function (string $status, string $error = '', ?string $orderId = null) use ($pdo, $eventDigest): void {
    try {
        $stmt = $pdo->prepare('UPDATE card_webhook_events SET status=?,error_message=?,matched_order_id=?,completed_at=? WHERE event_digest=?');
        $stmt->execute([$status, mb_substr($error, 0, 500), $orderId, time(), $eventDigest]);
    } catch (Throwable $e) {
        rx_log_event_structured('error', 'card_webhook_finalization_failed', ['exception' => get_class($e), 'code' => (string)$e->getCode()]);
    }
};

try {
    $candidate = $pdo->prepare("SELECT * FROM Payment_report
        WHERE price=:amount AND Payment_Method='cart to cart'
          AND payment_Status IN ('Unpaid','waiting')
        ORDER BY time ASC LIMIT 2");
    $candidate->execute([':amount' => $amount]);
    $orders = $candidate->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $finishEvent('failed', 'Order lookup failed');
    http_response_code(503);
    echo json_encode(['status' => false, 'msg' => 'Order lookup failed']);
    exit;
}
if (count($orders) !== 1) {
    $finishEvent('ambiguous', count($orders) === 0 ? 'No pending order matched the amount' : 'More than one pending order matched the amount');
    http_response_code(409);
    echo json_encode(['status' => false, 'msg' => 'The amount does not identify exactly one pending card order']);
    exit;
}
$order = $orders[0];
$orderId = (string)$order['id_order'];

try {
    $bind = $pdo->prepare("UPDATE Payment_report SET provider_name='bank_sms',provider_payment_id=? WHERE id_order=? AND payment_Status IN ('Unpaid','waiting')");
    $bind->execute([$eventDigest, $orderId]);

    require_once __DIR__ . '/../jdf.php';
    require_once __DIR__ . '/../botapi.php';
    require_once __DIR__ . '/../Marzban.php';
    require_once __DIR__ . '/../panels.php';
    require_once __DIR__ . '/../function.php';
    require_once __DIR__ . '/../keyboard.php';
    require_once __DIR__ . '/../lib/PaymentConfirm.php';

    $paymentReportTopic = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
    $confirm = payment_confirm_paid($orderId, 'chashbackcart', [
        'method' => 'تایید خودکار پیام بانکی',
        'expected_method' => 'cart to cart',
        'thread_id' => $paymentReportTopic,
        'extra_lines' => ['Bank event: ' . substr($eventDigest, 0, 16)],
    ]);
    if (empty($confirm['ok'])) {
        $finishEvent('failed', (string)($confirm['error'] ?? $confirm['status'] ?? 'Payment confirmation failed'), $orderId);
        http_response_code(409);
        echo json_encode(['status' => false, 'msg' => 'Payment requires reconciliation']);
        exit;
    }
    $finishEvent('processed', '', $orderId);
    echo json_encode(['status' => true, 'order_id' => $orderId], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $finishEvent('failed', 'Unhandled payment processing failure', $orderId);
    rx_log_event_structured('error', 'card_webhook_processing_failed', ['exception' => get_class($e), 'code' => (string)$e->getCode(), 'order_id' => $orderId]);
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Processing failed']);
}
