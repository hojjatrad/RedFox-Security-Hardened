<?php

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 16384) {
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
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
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
$callbackRate = redfox_login_rate_check('callback-aqayepardakht', redfox_client_ip(), 60, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    http_response_code(429);
    exit('Too Many Requests');
}

$rawInvoiceId = isset($_POST['invoice_id']) ? (string) $_POST['invoice_id'] : '';
$rawTransid   = isset($_POST['transid']) ? (string) $_POST['transid'] : '';
if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $rawInvoiceId) || !preg_match('/^[A-Za-z0-9_\-:.]{1,191}$/', $rawTransid)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('AQAYE_BAD_CALLBACK_ID', 'invoice_id or transid failed format validation', [
            'remote_ip' => redfox_client_ip(),
            'invoice_excerpt' => substr($rawInvoiceId, 0, 64),
        ]);
    } else {
        error_log('[aqayepardakht] bad callback identifier format');
    }
    http_response_code(400);
    exit('Invalid callback identifiers');
}
$invoice_id = $rawInvoiceId;
$setting = select("setting", "*");
$PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht","select")['ValuePay'];
try {
    $lookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND Payment_Method='aqayepardakht' AND provider_name='aqayepardakht' AND provider_invoice_id=:transid LIMIT 2");
    $lookup->execute([':order_id'=>$invoice_id, ':transid'=>$rawTransid]);
    $matches = $lookup->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[aqayepardakht] provider binding lookup failed: ' . redfox_exception_fingerprint($e));
    http_response_code(503);
    exit('Security migration required');
}
if (count($matches) !== 1 || !in_array(strtolower((string)($matches[0]['payment_Status'] ?? '')), ['unpaid','paid'], true)) {
    http_response_code(404);
    exit('Bound transaction not found');
}
$Payment_report_row = $matches[0];
$Payment_report = $Payment_report_row['price'];
$price = $Payment_report;
    $datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
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


$data = [
'pin'    => $PaySetting,
'amount'    => $Payment_report,
'transid' => $rawTransid,
];
$data = json_encode($data);
$ch = curl_init('https://panel.aqayepardakht.ir/api/v2/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json',
'Content-Length: ' . strlen($data))
);
$resultRaw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = (int)curl_errno($ch);
curl_close($ch);
$result = is_string($resultRaw) ? json_decode($resultRaw, true) : null;
$verified = $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300 && is_array($result) && (string)($result['code'] ?? '') === '1';
if ($verified) {
    $verifiedTransid = trim((string)($result['transid'] ?? $result['data']['transid'] ?? ''));
    if ($verifiedTransid !== '' && !hash_equals($rawTransid, $verifiedTransid)) {
        http_response_code(409);
        exit('Provider transaction mismatch');
    }
    $verifiedAmount = $result['amount'] ?? $result['data']['amount'] ?? null;
    if ($verifiedAmount !== null && abs((float)$verifiedAmount - (float)$Payment_report_row['provider_amount']) > 0.00000001) {
        http_response_code(409);
        exit('Provider amount mismatch');
    }
    try {
        $bindPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='aqayepardakht' AND provider_invoice_id=:invoice_id AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
        $bindPayment->execute([':payment_id'=>$rawTransid, ':same_payment_id'=>$rawTransid, ':id'=>(int)$Payment_report_row['id'], ':invoice_id'=>$rawTransid]);
        if ($bindPayment->rowCount() !== 1 && !hash_equals((string)($Payment_report_row['provider_payment_id'] ?? ''), $rawTransid)) {
            throw new RuntimeException('Provider payment id already bound');
        }
    } catch (Throwable $e) {
        error_log('[aqayepardakht] payment id binding failed: ' . redfox_exception_fingerprint($e));
        http_response_code(409);
        exit('Provider transaction binding failed');
    }
    $payment_status = "پرداخت موفق";
    $price = $Payment_report;
    $dec_payment_status = "از انجام تراکنش متشکریم!";

    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $confirm=payment_confirm_paid($invoice_id,'chashbackaqaypardokht',[
        'method'=>'درگاه آقای پرداخت','expected_method'=>'aqayepardakht','thread_id'=>$paymentreports,
        'extra_lines'=>['شناسه تراکنش: '.htmlspecialchars($rawTransid)]
    ]);
    if(empty($confirm['ok'])&&($confirm['reason']??'')!=='already completed'){
        $payment_status='نیازمند بررسی';
        $dec_payment_status='پرداخت تایید شده اما تکمیل اثرات نیازمند بررسی ادمین است.';
    }

}else {
    $providerCode = is_array($result) ? (string)($result['code'] ?? '') : '';
    $payment_status = [
        '0' => "پرداخت انجام نشد",
        '2' => "تراکنش قبلا وریفای و پرداخت شده است",
    ][$providerCode] ?? "تایید پرداخت ناموفق بود";
    $dec_payment_status = "";
    if ($curlErrno !== 0) {
        error_log('[aqayepardakht] verify transport error errno=' . $curlErrno);
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
            font-family:vazir;
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
            width:25%;
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
        <p>مبلغ پرداختی:  <span><?php echo  $price; ?></span>تومان</p>
        <p>تاریخ: <span>  <?php echo jdate('Y/m/d')  ?>  </span></p>
        <p><?php echo $dec_payment_status ?></p>
    </div>
</body>
</html>


