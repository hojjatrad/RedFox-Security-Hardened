<?php

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method Not Allowed');
}
if (strlen((string)($_SERVER['QUERY_STRING'] ?? '')) > 4096) {
    http_response_code(414);
    exit('Request URI Too Long');
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
$callbackRate = redfox_login_rate_check('callback-zarinpal', redfox_client_ip(), 60, 300);
if (empty($callbackRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($callbackRate['retry_after'] ?? 300)));
    http_response_code(429);
    exit('Too Many Requests');
}

$rawAuthority = isset($_GET['Authority']) ? (string) $_GET['Authority'] : '';
$rawStatus = isset($_GET['Status']) ? strtoupper(trim((string) $_GET['Status'])) : '';
if (!in_array($rawStatus, ['OK', 'NOK'], true)) {
    http_response_code(400);
    exit('Invalid callback status');
}
if (!preg_match('/^[A-Za-z0-9_\-]{1,191}$/', $rawAuthority)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('ZARINPAL_BAD_AUTHORITY', 'Authority failed format validation', [
            'remote_ip' => redfox_client_ip(),
            'authority_excerpt' => substr($rawAuthority, 0, 64),
        ]);
    } else {
        error_log('[zarinpal] bad authority format');
    }
    http_response_code(400);
    exit('Invalid authority');
}
$Authority = $rawAuthority;
$StatusPayment = $rawStatus;
$setting = select("setting", "*");
$PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal","select")['ValuePay'] ?? '';
if (!is_string($PaySetting) || trim($PaySetting) === '') {
    http_response_code(503);
    exit('Gateway is not configured');
}
try {
    $lookup = $pdo->prepare("SELECT * FROM Payment_report WHERE provider_name='zarinpal' AND provider_invoice_id=:authority AND Payment_Method='zarinpal' LIMIT 2");
    $lookup->execute([':authority'=>$Authority]);
    $matches = $lookup->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[zarinpal] provider binding lookup failed: ' . redfox_exception_fingerprint($e));
    http_response_code(503);
    exit('Security migration required');
}
if (count($matches) !== 1 || !in_array(strtolower((string)($matches[0]['payment_Status'] ?? '')), ['unpaid','paid'], true)) {
    http_response_code(404);
    exit('Bound transaction not found');
}
$Payment_reports = $matches[0];
$price = $Payment_reports['price'];
$invoice_id = (string)$Payment_reports['id_order'];
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

$dec_payment_status = "";
$payment_status = "";
if($StatusPayment == "OK"){
        $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/verify.json',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 0,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Accept: application/json'
  ),
));
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
  "merchant_id" => $PaySetting,
  "amount"=> $price,
  "authority" => $Authority,
  "description" => $Payment_reports['id_user']
        ]));
$responseRaw = curl_exec($curl);
$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlErrno = (int)curl_errno($curl);
curl_close($curl);
$response = is_string($responseRaw) ? json_decode($responseRaw,true) : null;
if ($curlErrno !== 0 || $httpCode < 200 || $httpCode >= 300 || !is_array($response)) {
    error_log('[zarinpal] verify transport/response failure');
    $response = ['data'=>[], 'errors'=>['code'=>'transport']];
}
       $zarinpalErrors = [
			"-9" => "خطا در ارسال داده",
			"-10" => "ای پی یا مرچنت كد پذیرنده صحیح نیست.",
			"-11" => "مرچنت کد فعال نیست،",
			"-12" => "تلاش بیش از دفعات مجاز در یک بازه زمانی کوتاه",
			"-15" => "درگاه پرداخت به حالت تعلیق در آمده است",
			"-16" => "سطح تایید پذیرنده پایین تر از سطح نقره ای است.",
			"-17" => "محدودیت پذیرنده در سطح آبی",
			"-30" => "پذیرنده اجازه دسترسی به سرویس تسویه اشتراکی شناور را ندارد.",
			"-31" => "حساب بانکی تسویه را به پنل اضافه کنید. مقادیر وارد شده برای تسهیم درست نیست. پذیرنده جهت استفاده از خدمات سرویس تسویه اشتراکی شناور، باید حساب بانکی معتبری به پنل کاربری خود اضافه نماید.",
			"-32" => "مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.",
			"-33" => "درصدهای وارد شده صحیح یست.",
			"-34" => "مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.",
			"-35" => "تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است.",
			"-36" => "حداقل مبلغ جهت تسهیم باید ۱۰۰۰۰ ریال باشد",
			"-37" => "یک یا چند شماره شبای وارد شده برای تسهیم از سمت بانک غیر فعال است.",
			"-38" => "خطا٬عدم تعریف صحیح شبا٬لطفا دقایقی دیگر تلاش کنید.",
			"-39" => "	خطایی رخ داده است",
			"-40" => "",
			"-50" => "مبلغ پرداخت شده با مقدار مبلغ ارسالی در متد وریفای متفاوت است.",
			"-51" => "پرداخت ناموفق",
			"-52" => "	خطای غیر منتظره‌ای رخ داده است. ",
			"-53" => "پرداخت متعلق به این مرچنت کد نیست.",
			"-54" => "اتوریتی نامعتبر است.",
    ];
 $providerErrorCode = (string)($response['errors']['code'] ?? '');
 $payment_status = $zarinpalErrors[$providerErrorCode] ?? 'تایید پرداخت ناموفق بود';
 $verifyMessage = (string)($response['data']['message'] ?? '');
 $verifyCode = (int)($response['data']['code'] ?? 0);
 if(in_array($verifyCode,[100,101],true) || $verifyMessage === "Verified" || $verifyMessage === "Paid"){
    $payment_status = "پرداخت موفق";
    $dec_payment_status = "از انجام تراکنش متشکریم!";


    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $refcode = trim((string)($response['data']['ref_id'] ?? ''));
    $cart_number = (string)($response['data']['card_pan'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_\-:.]{1,191}$/', $refcode)) {
        http_response_code(409);
        exit('Provider payment identifier is missing');
    }
    try {
        $bindPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='zarinpal' AND provider_invoice_id=:authority AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
        $bindPayment->execute([':payment_id'=>$refcode, ':same_payment_id'=>$refcode, ':id'=>(int)$Payment_reports['id'], ':authority'=>$Authority]);
        if ($bindPayment->rowCount() !== 1 && !hash_equals((string)($Payment_reports['provider_payment_id'] ?? ''), $refcode)) {
            throw new RuntimeException('Provider payment id already bound');
        }
    } catch (Throwable $e) {
        error_log('[zarinpal] payment id binding failed: ' . redfox_exception_fingerprint($e));
        http_response_code(409);
        exit('Provider transaction binding failed');
    }
    $confirm = payment_confirm_paid($invoice_id,'chashbackzarinpal',[
        'method'=>'درگاه زرین پال','expected_method'=>'zarinpal','thread_id'=>$paymentreports,
        'extra_lines'=>['شماره تراکنش: '.htmlspecialchars($refcode),'شماره کارت: '.htmlspecialchars($cart_number)]
    ]);
    if(empty($confirm['ok']) && ($confirm['reason']??'')!=='already completed'){
        $payment_status='نیازمند بررسی';
        $dec_payment_status='پرداخت تایید شده اما تکمیل اثرات نیازمند بررسی ادمین است.';
    }

}else {
        $payment_status = [
        '0' => "پرداخت انجام نشد",
        '2' => "تراکنش قبلا وریفای و پرداخت شده است",

    ][(string)($response['errors']['code'] ?? '')] ?? 'تایید پرداخت ناموفق بود';
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
        <p>مبلغ پرداختی:  <span><?php echo  $price ?></span>تومان</p>
        <p>تاریخ: <span>  <?php echo jdate('Y/m/d')  ?>  </span></p>
        <p><?php echo $dec_payment_status ?></p>
    </div>
</body>
</html>


