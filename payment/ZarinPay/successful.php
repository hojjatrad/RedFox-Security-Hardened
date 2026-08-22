<?php

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/lib/Security.php';
redfox_secure_session_start();

$callbackMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($callbackMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
$callbackPayload = [];
if ($callbackMethod === 'GET') {
    if (strlen((string)($_SERVER['QUERY_STRING'] ?? '')) > 4096) {
        http_response_code(414);
        exit('Request URI Too Long');
    }
    $callbackPayload = $_GET;
} else {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength < 1 || $contentLength > 65536) {
        http_response_code(413);
        exit('Invalid body size');
    }
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType === 'application/json') {
        $rawBody = file_get_contents('php://input', false, null, 0, 65537);
        $callbackPayload = is_string($rawBody) ? json_decode($rawBody, true, 16, JSON_BIGINT_AS_STRING) : null;
    } elseif ($contentType === 'application/x-www-form-urlencoded') {
        $callbackPayload = $_POST;
    } else {
        http_response_code(415);
        exit('Unsupported Media Type');
    }
    if (!is_array($callbackPayload)) {
        http_response_code(400);
        exit('Invalid callback body');
    }
}

$normalizeValue = static function ($value) {
    if ($value === null) {
        return null;
    }

    if (is_scalar($value)) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    return null;
};

$sessionAuthority = $normalizeValue($_SESSION['authority'] ?? null);
$sessionOrderId = $normalizeValue($_SESSION['order_id'] ?? null);
$callbackAuthority = $normalizeValue($callbackPayload['authority'] ?? null);
$callbackOrderId = $normalizeValue($callbackPayload['order_id'] ?? null);

if ($sessionAuthority !== null && $callbackAuthority !== null && !hash_equals($sessionAuthority, $callbackAuthority)) {
    http_response_code(400);
    exit('شناسه درگاه با نشست پرداخت مطابقت ندارد.');
}
if ($sessionOrderId !== null && $callbackOrderId !== null && !hash_equals($sessionOrderId, $callbackOrderId)) {
    http_response_code(400);
    exit('شناسه سفارش با نشست پرداخت مطابقت ندارد.');
}

$authority = $callbackAuthority ?: $sessionAuthority;
$suppliedOrderId = $callbackOrderId ?: $sessionOrderId;
$hasSessionData = $sessionAuthority !== null && $sessionOrderId !== null;
$hasCallbackData = $callbackAuthority !== null;

if ($authority === null || !preg_match('/^[A-Za-z0-9_\-]{1,191}$/', $authority)) {
    http_response_code(400);
    echo 'شناسه معتبر درگاه یافت نشد.';
    exit;
}

require_once $projectRoot . '/config.php';
$callbackRate = redfox_login_rate_check('callback-zarinpay', redfox_client_ip(), 60, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    http_response_code(429);
    exit('Too Many Requests');
}
require_once $projectRoot . '/jdf.php';
require_once $projectRoot . '/botapi.php';
require_once $projectRoot . '/Marzban.php';
require_once $projectRoot . '/function.php';
require_once $projectRoot . '/panels.php';
require_once $projectRoot . '/keyboard.php';
require_once $projectRoot . '/lib/PaymentConfirm.php';

$ManagePanel = new ManagePanel();

$textbotlang = languagechange($projectRoot . '/text.json');

$datatextbotRecords = select('textbot', '*', null, null, 'fetchAll');
$datatextbot = [
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'text_wgdashboard' => '',
    'textafterpayibsng' => '',
];

if (is_array($datatextbotRecords)) {
    foreach ($datatextbotRecords as $row) {
        $key = $row['id_text'] ?? null;
        if ($key !== null && array_key_exists($key, $datatextbot)) {
            $datatextbot[$key] = $row['text'];
        }
    }
}

try {
    $lookup = $pdo->prepare("SELECT * FROM Payment_report WHERE provider_name='zarinpay' AND provider_invoice_id=:authority AND Payment_Method='zarinpay' LIMIT 2");
    $lookup->execute([':authority' => $authority]);
    $matches = $lookup->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[zarinpay-callback] provider binding lookup failed: ' . redfox_exception_fingerprint($e));
    http_response_code(503);
    exit('مهاجرت امنیتی پرداخت اعمال نشده است.');
}
if (count($matches) !== 1) {
    http_response_code(404);
    echo 'تراکنش یکتای متناظر با شناسه درگاه یافت نشد.';
    exit;
}
$paymentReport = $matches[0];
$invoiceId = (string)$paymentReport['id_order'];
if ($suppliedOrderId !== null && !hash_equals($invoiceId, $suppliedOrderId)) {
    http_response_code(400);
    exit('شناسه سفارش با شناسه ثبت‌شده درگاه مطابقت ندارد.');
}
if (!in_array(strtolower((string)($paymentReport['payment_Status'] ?? '')), ['unpaid','paid'], true)) {
    http_response_code(409);
    exit('وضعیت سفارش برای تایید خودکار معتبر نیست.');
}

try {
    $payload = json_encode([
        'authority' => $authority,
    ], JSON_UNESCAPED_UNICODE);

    $token = getPaySettingValue('token_zarinpey');
    if (empty($token) || $token === '0') {
        throw new Exception('توکن زرین پی تنظیم نشده است.');
    }

    $ch = curl_init('https://zarinpay.me/api/verify-payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errno = (int)curl_errno($ch);
        curl_close($ch);
        throw new RuntimeException('zarinpay_transport_error_' . $errno);
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result) || empty($result['success'])) {
        $message = $result['message'] ?? 'پرداخت انجام نشد';
        throw new Exception($message);
    }

    $verifyCode = $result['data']['code'] ?? null;
    if (!in_array($verifyCode, [100, 101], true)) {
        throw new RuntimeException('پرداخت تایید نشد.');
    }

    $transaction = is_array($result['data']['transaction'] ?? null) ? $result['data']['transaction'] : [];
    $verifiedAuthority = trim((string)($result['data']['authority'] ?? $transaction['authority'] ?? ''));
    if ($verifiedAuthority !== '' && !hash_equals($authority, $verifiedAuthority)) {
        throw new RuntimeException('شناسه تاییدشده درگاه با فاکتور مطابقت ندارد.');
    }
    $verifiedOrder = trim((string)($result['data']['order_id'] ?? $transaction['order_id'] ?? ''));
    if ($verifiedOrder !== '' && !hash_equals($invoiceId, $verifiedOrder)) {
        throw new RuntimeException('سفارش تاییدشده درگاه با فاکتور مطابقت ندارد.');
    }
    $verifiedAmount = $result['data']['amount'] ?? $transaction['amount'] ?? null;
    $expectedProviderAmount = $paymentReport['provider_amount'] ?? null;
    if ($verifiedAmount !== null && $expectedProviderAmount !== null && abs((float)$verifiedAmount - (float)$expectedProviderAmount) > 0.00000001) {
        throw new RuntimeException('مبلغ تاییدشده درگاه با فاکتور مطابقت ندارد.');
    }

    $transactionId = trim((string)($transaction['payment_id'] ?? $result['data']['payment_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_\-:.]{1,191}$/', $transactionId)) {
        throw new RuntimeException('شناسه یکتای پرداخت در پاسخ درگاه وجود ندارد.');
    }
    $bindPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='zarinpay' AND provider_invoice_id=:authority AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
    $bindPayment->execute([':payment_id'=>$transactionId, ':id'=>(int)$paymentReport['id'], ':authority'=>$authority, ':same_payment_id'=>$transactionId]);
    if ($bindPayment->rowCount() !== 1 && !hash_equals((string)($paymentReport['provider_payment_id'] ?? ''), $transactionId)) {
        throw new RuntimeException('شناسه پرداخت قبلاً به تراکنش دیگری متصل شده است.');
    }

    $setting = select('setting', '*');
    $paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
    $payment_status = 'پرداخت موفق';
    $dec_payment_status = 'از انجام تراکنش متشکریم!';

    $confirm=payment_confirm_paid((string)$paymentReport['id_order'],'',[
        'method'=>'زرین پی','expected_method'=>'zarinpay','thread_id'=>$paymentreports,
        'extra_lines'=>$transactionId!==''?['شناسه تراکنش: '.htmlspecialchars($transactionId)]:[]
    ]);
    if(empty($confirm['ok'])&&($confirm['reason']??'')!=='already completed'){
        $payment_status='نیازمند بررسی';
        $dec_payment_status='پرداخت تایید شده اما تکمیل اثرات نیازمند بررسی ادمین است.';
    }

} catch (Throwable $e) {
    error_log('[zarinpay-callback] verification failed: ' . get_class($e) . ': ' . redfox_exception_fingerprint($e));
    if ($hasCallbackData) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'verification_failed',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: failed.php');
    }

    session_unset();
    session_destroy();

    exit;
}

if ($hasCallbackData) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    session_unset();
    session_destroy();
    exit;
}

$price = $paymentReport['price'];
$payment_status = $payment_status ?? 'پرداخت موفق';
$dec_payment_status = $dec_payment_status ?? 'از انجام تراکنش متشکریم!';
?>
   <!DOCTYPE html>
   <html lang="en">

   <head>
<link rel="stylesheet" href="../../assets/css/public-fa.css">
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>پرداخت موفق</title>


      <style>
         * {
            font-family: "vazir";
            direction: rtl;
         }

         .card {
            box-shadow: 0 15px 16.8px rgba(0, 0, 0, 0.031), 0 100px 134px rgba(0, 0, 0, 0.05);
            background-color: white;
            border-radius: 15px;
            padding: 35px;
         }

         .top {
            padding-bottom: 25px;
            min-width: 250px;
            text-align: center;
            border-bottom: dashed
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            border-left: 0.18em dashed
            position: relative;
         }

         .top:before {
            background-color:
            position: absolute;
            content: "";
            display: block;
            width: 20px;
            height: 20px;
            border-radius: 100%;
            bottom: 0;
            right: -10px;
            margin-bottom: -10px;
         }

         svg,
         h3 {
            color:
         }

         svg {
            margin: 0 auto;
            width: 60px;
            height: 60px;
         }

         h3 {
            margin-top: 0px;
            margin-bottom: 10px;
         }

         span {
            color:
            font-size: 12px;
         }

         .bottom {
            text-align: center;
            margin-top: 30px;
         }

         .key-value {
            display: flex;
            justify-content: space-between;
         }

         .key-value span:first-child {
            font-weight: 0;
         }

         a {
            padding: 8px 20px;
            background-color:
            text-decoration: none;
            color: white;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 20px;
            display: block;
         }

         .outer-container {
            background-color:
            position: absolute;
            display: table;
            width: 100%;
            height: 100%;
            top: 0;
            right: 0;
         }

         .inner-container {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
         }

         .centered-content {
            display: inline-block;
            text-align: left;
            background:
            margin-top: 10px;
         }
      </style>

      <link href="https://cdnjs.cloudflare.com/ajax/libs/vazir-font/27.2.0/font-face.css" rel="stylesheet"
         type="text/css">


   </head>

   <body>
      <div class="outer-container">
         <div class="inner-container">
            <div class="card centered-content">
               <div class="top">

                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                     <path fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd" />
                  </svg>
                  <h3>
                     پرداخت موفق!
                  </h3>
                  <span>شماره تراکنش: <?php echo htmlspecialchars($invoiceId, ENT_QUOTES, 'UTF-8'); ?></span>
               </div>
               <div class="bottom">
                  <div class="key-value">
                     <span>پرداخت با موفقیت انجام شد</span>
                  </div>
                  <div class="key-value">
                     <span>مبلغ پرداختی: <?php echo number_format($price) ?> تومان</span>
                  </div>
                  <div class="key-value">
                     <span>زمان: <?php echo jdate('Y/m/d H:i') ?></span>
                  </div>
                  <a href="https://t.me/<?php echo htmlspecialchars($usernamebot, ENT_QUOTES, 'UTF-8'); ?>"> برگشت به ربات</a>
               </div>
            </div>
         </div>
      </div>
   </body>

   </html>
<?php
session_unset();
session_destroy();
exit;


