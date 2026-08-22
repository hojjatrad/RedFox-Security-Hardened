<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('croncard', 180);


if (!rx_cron_require_or_skip('croncard', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
    __DIR__ . '/../keyboard.php',
    __DIR__ . '/../jdf.php',
])) {
    return;
}
if (!rx_cron_db_ready('croncard')) {
    return;
}
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
$datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
$PaySetting = select("PaySetting","ValuePay","NamePay",'statuscardautoconfirm',"select")['ValuePay'];
$paymentverify = select("PaySetting","ValuePay","NamePay","autoconfirmcart","select")['ValuePay'];
if($PaySetting == "onautoconfirm")return;
if($paymentverify == "offauto")return;
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
list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL$rxShard ORDER BY id ASC LIMIT 50");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $timecheck = $setting['timeauto_not_verify']*60;
    if($row['at_updated'] == null)continue;
    $since_start = time() - strtotime($row['at_updated']);
    // Red Fox: the previous hard 1-hour cap (since_start >= 3600) abandoned any
    // receipt the admin left pending for more than an hour, so it was never
    // auto-confirmed. Removed so the auto-confirm reliably fires after the
    // configured "timeauto_not_verify" minutes, no matter how long the admin
    // was away. Only waiting receipts are touched and the UPDATE below is atomic.
    if ($since_start <= $timecheck)continue;
    $Payment_report = $row;
    $list_Exceptions = select("PaySetting","ValuePay","NamePay","Exception_auto_cart","select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions,true) : [];
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    if(in_array($Balance_id['id'],$list_Exceptions))continue;
    $textbotlang =languagechange('../text.json');
    if ($Payment_report['payment_Status'] == "paid") {
        continue;
    }


        $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ? AND payment_Status <> 'paid'")
            ->execute(['تایید توسط ربات بدون بررسی', $Payment_report['id_order']]);
        $confirmResult = payment_confirm_paid(
            (string)$Payment_report['id_order'],
            'chashbackcart',
            ['method'=>'تایید خودکار کارت به کارت','expected_method'=>['cart to cart','arze digital offline'],'thread_id'=>$paymentreports]
        );
        if (empty($confirmResult['ok'])) continue;
        $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");

        // ── Red Fox: notify each administrator to review the auto-confirmed receipt later ──
        try {
            $rxAdminsForReview = $pdo->prepare("SELECT id_admin FROM admin WHERE rule = 'administrator'");
            $rxAdminsForReview->execute();
            foreach ($rxAdminsForReview->fetchAll(PDO::FETCH_COLUMN, 0) as $rxAdminChat) {
                $rxAdminChat = trim((string)$rxAdminChat);
                if ($rxAdminChat === '' || !ctype_digit($rxAdminChat)) {
                    continue;
                }
                telegram('sendmessage', [
                    'chat_id'   => $rxAdminChat,
                    'text'      => "⏰ <b>تأیید خودکار رسید — نیازمند بررسی بعدی</b>\n\nرسید زیر به دلیل عدم تأیید دستی شما پس از {$setting['timeauto_not_verify']} دقیقه، توسط ربات تأیید و حساب کاربر شارژ شد. لطفاً رسید اصلی را در پیام قبلی بررسی کنید و در صورت نیاز اصلاح نمایید.\n\n🆔 کد پیگیری: <code>" . htmlspecialchars((string)$Payment_report['id_order'], ENT_QUOTES) . "</code>\n\n👤 آیدی کاربر: {$Balance_id['id']}\n\n💰 مبلغ: " . number_format((int)$Payment_report['price']) . " تومان\n\n📌 روش: {$Payment_report['Payment_Method']}",
                    'parse_mode' => 'HTML',
                ]);
            }
        } catch (Throwable $rxAdminReviewErr) {
            // best-effort notification; never break the main confirmation flow
        }
}
