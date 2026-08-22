<?php

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 65536) {
    http_response_code(413);
    exit('Invalid body size');
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    http_response_code(415);
    exit('JSON required');
}
$rawCallbackBody = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($rawCallbackBody) || $rawCallbackBody === '' || strlen($rawCallbackBody) > 65536) {
    http_response_code(400);
    exit('Invalid body');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../lib/PaymentConfirm.php';
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$ManagePanel = new ManagePanel();
$callbackRate = redfox_login_rate_check('callback-iranpay1', redfox_client_ip(), 60, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    http_response_code(429);
    exit('Too Many Requests');
}
$data = json_decode($rawCallbackBody, true, 32, JSON_BIGINT_AS_STRING);
if (!is_array($data)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('IRANPAY_BAD_BODY', 'Callback body was not valid JSON', [
            'remote_ip' => redfox_client_ip(),
        ]);
    }
    http_response_code(400);
    exit('Invalid body');
}
$rawHashId = isset($data['hashid']) ? (string) $data['hashid'] : '';
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $rawHashId)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('IRANPAY_BAD_HASHID', 'hashid failed format validation', [
            'remote_ip' => redfox_client_ip(),
            'hashid_excerpt' => substr($rawHashId, 0, 64),
        ]);
    }
    http_response_code(400);
    exit('Invalid hashid');
}
$hashid = htmlspecialchars($rawHashId, ENT_QUOTES, 'UTF-8');
$authority = trim(isset($data['authority']) ? (string) $data['authority'] : '');
$StatusPayment = trim(isset($data['status']) ? (string) $data['status'] : '');
if (!preg_match('/^[A-Za-z0-9_\-:.]{1,191}$/', $authority)) {
    http_response_code(400);
    exit('Invalid authority');
}
$setting = select("setting", "*");
$PaySetting = select("PaySetting", "*", "NamePay", "marchent_floypay", "select")['ValuePay'] ?? '';
if (!is_string($PaySetting) || trim($PaySetting) === '') {
    http_response_code(503);
    exit('Gateway is not configured');
}
try {
    $lookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND Payment_Method='Currency Rial 1' AND provider_name='iranpay1' AND provider_invoice_id=:authority LIMIT 2");
    $lookup->execute([':order_id'=>$hashid, ':authority'=>$authority]);
    $matches = $lookup->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[iranpay1] provider binding lookup failed: ' . redfox_exception_fingerprint($e));
    http_response_code(503);
    exit('Security migration required');
}
if (count($matches) !== 1 || !in_array(strtolower((string)($matches[0]['payment_Status'] ?? '')), ['unpaid','paid'], true)) {
    http_response_code(404);
    exit('Bound transaction not found');
}
$Payment_reports = $matches[0];
$invoice_id = (string)$Payment_reports['id_order'];
$price = $Payment_reports['price'];
$datatextbotget = select("textbot", "*", null, null, "fetchAll");
$datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'text_wgdashboard' => '',
    'textafterpayibsng' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}

$dec_payment_status = "";
$payment_status = "";
if ($StatusPayment == 100) {
    $curl = curl_init();
    $data = [
        "ApiKey" => $PaySetting,
        "authority" => $authority,
        "hashid" => $invoice_id,
    ];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://tetra98.com/api/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    $response = curl_exec($curl);
    $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlErrno = (int)curl_errno($curl);
    curl_close($curl);
    $verifyData = is_string($response) ? json_decode($response, true) : null;
    $verifyOk = ($curlErrno === 0 && $http_code === 200 && is_array($verifyData) && isset($verifyData['status']) && (int) $verifyData['status'] === 100);
    if ($verifyOk) {
        $verifiedAuthority = trim((string)($verifyData['authority'] ?? $verifyData['data']['authority'] ?? ''));
        if ($verifiedAuthority !== '' && !hash_equals($authority, $verifiedAuthority)) {
            http_response_code(409);
            exit('Provider authority mismatch');
        }
        $verifiedHash = trim((string)($verifyData['hashid'] ?? $verifyData['data']['hashid'] ?? ''));
        if ($verifiedHash !== '' && !hash_equals($invoice_id, $verifiedHash)) {
            http_response_code(409);
            exit('Provider order mismatch');
        }
        $verifiedAmount = $verifyData['amount'] ?? $verifyData['data']['amount'] ?? null;
        if ($verifiedAmount !== null && abs((float)$verifiedAmount - (float)$Payment_reports['provider_amount']) > 0.00000001) {
            http_response_code(409);
            exit('Provider amount mismatch');
        }
        try {
            $bindPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='iranpay1' AND provider_invoice_id=:authority AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
            $bindPayment->execute([':payment_id'=>$authority, ':same_payment_id'=>$authority, ':id'=>(int)$Payment_reports['id'], ':authority'=>$authority]);
            if ($bindPayment->rowCount() !== 1 && !hash_equals((string)($Payment_reports['provider_payment_id'] ?? ''), $authority)) {
                throw new RuntimeException('Provider payment id already bound');
            }
        } catch (Throwable $e) {
            error_log('[iranpay1] payment id binding failed: ' . redfox_exception_fingerprint($e));
            http_response_code(409);
            exit('Provider transaction binding failed');
        }
        $payment_status = "پرداخت موفق";
        $dec_payment_status = "از انجام تراکنش متشکریم!";
        $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
        $confirm=payment_confirm_paid($invoice_id,'chashbackiranpay1',[
            'method'=>'درگاه ارزی ریالی اول','expected_method'=>'Currency Rial 1','thread_id'=>$paymentreports,
            'extra_lines'=>['authority: '.htmlspecialchars($authority)]
        ]);
        if(empty($confirm['ok'])&&($confirm['reason']??'')!=='already completed'){
            $payment_status='نیازمند بررسی';
            $dec_payment_status='پرداخت تایید شده اما تکمیل اثرات نیازمند بررسی ادمین است.';
        }

    } else {
        $payment_status = "ناموفق";
        $dec_payment_status = "";
    }
}
?>
<html>

<head>
<link rel="stylesheet" href="../assets/css/public-fa.css">
    <title>فاکتور پرداخت</title>
    <style>
        @font-face {
            font-family: 'vazir';
            src: url('/Vazir.eot');
            src: local('☺'), url('../fonts/Vazir.woff') format('woff'), url('../fonts/Vazir.ttf') format('truetype');
        }

        body {
            font-family: vazir;
            background-color:
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .confirmation-box {
            background-color:
            border-radius: 8px;
            width: 25%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color:
            margin-bottom: 20px;
        }

        p {
            color:
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="confirmation-box">
        <h1><?php echo $payment_status ?></h1>
        <p>شماره تراکنش:<span><?php echo $invoice_id ?></span></p>
        <p>مبلغ پرداختی: <span><?php echo $price ?></span>تومان</p>
        <p>تاریخ: <span> <?php echo jdate('Y/m/d') ?> </span></p>
        <p><?php echo $dec_payment_status ?></p>
    </div>
</body>

</html>

