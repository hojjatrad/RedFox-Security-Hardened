<?php
require_once __DIR__ . '/config.php';
$rxWebhookHeader = trim((string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
$rxWebhookType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
$rxWebhookLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST'
    || !preg_match('#^application/json(?:\s*;|$)#i', $rxWebhookType)
    || $rxWebhookLength === false || $rxWebhookLength < 2 || $rxWebhookLength > 1048576
    || !isset($WebhookSecret) || strlen((string)$WebhookSecret) < 32
    || $rxWebhookHeader === '' || !hash_equals((string)$WebhookSecret, $rxWebhookHeader)) {
    http_response_code(401);
    header('Cache-Control: no-store');
    exit;
}
if (!defined('REDFOX_VPNBOT_WEBHOOK_AUTHENTICATED')) {
    define('REDFOX_VPNBOT_WEBHOOK_AUTHENTICATED', true);
}
unset($rxWebhookHeader, $rxWebhookType, $rxWebhookLength);
// Red Fox vpnbot — مدیریت خطا + مسیر پویا
ini_set('log_errors', '1');
// Root config.php installs a private logs/php-error.log destination.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ثبت هندلر برای استثنائات مهلک
set_exception_handler(function ($e) {
    error_log('[vpnbot] FATAL: ' . redfox_exception_fingerprint($e) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    http_response_code(200);
    exit;
});

$version = @file_get_contents('version') ?: '0.0.0';
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('max_execution_time', '600');

// --- محاسبه مسیر فایل‌های اصلی ربات (پویا برای هر دامنه/پوشه) ---
$rootPath = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT');
$PHP_SELF = filter_input(INPUT_SERVER, 'PHP_SELF');
$Pathfile = dirname(dirname($PHP_SELF, 2));
$Pathfiles = rtrim($rootPath . $Pathfile, '/\\') . '/';

// بررسی: آیا function.php در مسیر محاسبه‌شده هست؟
if (!is_file($Pathfiles . 'function.php')) {
    // fallback: محاسبه از مسیر فیزیکی فایل فعلی
    $Pathfiles = realpath(__DIR__ . '/../../') . '/';
    error_log('[vpnbot] PathFiles fallback to: ' . $Pathfiles);
}

// --- شامل کردن فایل‌ها (با try-catch) ---
try {
    require_once __DIR__ . '/config.php';
    if (!is_file($Pathfiles . 'function.php')) {
        throw new RuntimeException('Main bot function.php not found at: ' . $Pathfiles);
    }
    require_once $Pathfiles . 'function.php';
    require_once $Pathfiles . 'config.php';
    require_once $Pathfiles . 'lib/MessageTemplates.php';
    require_once $Pathfiles . 'lib/BotContentMenu.php';
    require_once $Pathfiles . 'jdf.php';
    require_once $Pathfiles . 'panels.php';
    require_once __DIR__ . '/func.php';
    require_once __DIR__ . '/botapi.php';
    require_once __DIR__ . '/keyboard.php';
    if (is_file($Pathfiles . 'vendor/autoload.php')) {
        require_once $Pathfiles . 'vendor/autoload.php';
    }
    $ManagePanel = new ManagePanel();
} catch (Throwable $e) {
    error_log('[vpnbot] INCLUDE FAILED: ' . redfox_exception_fingerprint($e));
    http_response_code(200);
    exit;
}

// Red Fox FIX: chdir به پوشه‌ی vpnbot — فایل‌های function.php ربات اصلی
// مسیر کاری را تغییر داده بود. این همه مسیرهای نسبی (data/, text.json, etc.) را درست می‌کند.
@chdir(__DIR__);
$datain=rxVpnbotResolveCallback((string)($datain??''));

// Payload logging is disabled: webhook bodies may contain credentials and personal data.
// Red Fox: DEBUG نقاط کنترلی
function rx_dbg($point) {
    // Intentionally disabled in production: Telegram payloads and identifiers must not be logged in web root.
}
function rxVpnbotOwnedInvoice(PDO $pdo, string $invoiceId, int|string $userId, string $botToken): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=:invoice AND id_user=:user AND bottype=:bot LIMIT 1');
    $stmt->execute([':invoice' => $invoiceId, ':user' => (string)$userId, ':bot' => $botToken]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
rx_dbg('A: after_includes');
$globalCustomVolumeFlag = null;
$customVolumeFlagKeys = [
    'sellcustomvolume',
    'sell_custom_volume',
    'custom_volume_sell',
    'customVolumeSell',
    'customvolume_sell',
    'customvolume_status',
    'enable_custom_volume',
];

function normalizeBooleanFlag($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return intval($value) !== 0;
    }
    if ($value === null) {
        return null;
    }
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
        return null;
    }
    if (in_array($normalized, ['1', 'true', 'yes', 'on', 'enable', 'enabled'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off', 'disable', 'disabled'], true)) {
        return false;
    }
    return null;
}

function resolveBooleanFlagFromArray(array $source, array $keys)
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $source)) {
            continue;
        }
        $normalized = normalizeBooleanFlag($source[$key]);
        if ($normalized !== null) {
            return $normalized;
        }
    }
    return null;
}

function resolveGlobalCustomVolumeFlag(array $setting, array $settingmain)
{
    global $customVolumeFlagKeys;
    $flag = resolveBooleanFlagFromArray($setting, $customVolumeFlagKeys);
    if ($flag !== null) {
        return $flag;
    }
    return resolveBooleanFlagFromArray($settingmain, $customVolumeFlagKeys);
}

function resolveGlobalCustomVolumeFlagFromShopSettings()
{
    global $customVolumeFlagKeys;
    foreach ($customVolumeFlagKeys as $key) {
        $row = select("shopSetting", "*", "Namevalue", $key, "select");
        if (!is_array($row)) {
            continue;
        }
        $candidate = $row['value'] ?? $row['Value'] ?? null;
        $normalized = normalizeBooleanFlag($candidate);
        if ($normalized !== null) {
            return $normalized;
        }
    }
    return null;
}

function isCustomVolumeEnabledForAgent($panel, $agent)
{
    global $globalCustomVolumeFlag;
    if ($globalCustomVolumeFlag === true) {
        return true;
    }
    if ($globalCustomVolumeFlag === false) {
        return false;
    }
    if (!is_array($panel) || !isset($panel['customvolume'])) {
        return false;
    }
    $rawCustomVolume = json_decode($panel['customvolume'], true);
    if (!is_array($rawCustomVolume)) {
        return false;
    }
    $agentKey = $agent ?? null;
    if ($agentKey !== null && array_key_exists($agentKey, $rawCustomVolume)) {
        $value = $rawCustomVolume[$agentKey];
    } elseif (array_key_exists('all', $rawCustomVolume)) {
        $value = $rawCustomVolume['all'];
    } else {
        return false;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return intval($value) === 1;
    }
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'on', 'yes', 'enable', 'enabled'], true);
}

$text_bot_var = json_decode(file_get_contents(__DIR__ . '/text.json'), true);
rx_dbg('B: before_ipcheck');
$rxIpOk = checktelegramip();
rx_dbg('C: ipcheck=' . ($rxIpOk ? 'PASS' : 'FAIL'));
if (!$rxIpOk)
    die("Unauthorized access");

rx_dbg('D: after_ipcheck');
$textbotlang = json_decode(file_get_contents($Pathfiles . 'text.json'), true)['fa'];
rx_dbg('E: textbotlang_loaded');
$dataBase = select("botsaz", "*", "bot_token", $ApiToken, "select");
rx_dbg('F: botsaz=' . (is_array($dataBase) ? 'FOUND' : 'NOT_FOUND'));
$admin_ids = json_decode($dataBase['admin_ids']);
$setting = json_decode($dataBase['setting'], true);

// ── Red Fox: بررسی انقضای ربات نماینده ──────────────────────────────
$rxBotStatus = (string)($dataBase['bot_status'] ?? 'active');
$rxBotExp = trim((string)($dataBase['bot_expires_at'] ?? ''));
$rxBotExpired = false;
if ($rxBotStatus === 'disabled') {
    $rxBotExpired = true;
} elseif ($rxBotExp !== '' && function_exists('strtotime')) {
    $rxExpTs = strtotime($rxBotExp);
    if ($rxExpTs && $rxExpTs < time()) $rxBotExpired = true;
}
if ($rxBotExpired && isset($from_id) && !in_array($from_id, json_decode($dataBase['admin_ids'], true))) {
    // ربات منقضی/غیرفعال است — فقط به کاربر عادی پیام نشان بده
    @sendmessage($from_id, "⚠️ این ربات موقتاً غیرفعال است.\n\n📍 دلیل: " . ($rxBotStatus === 'disabled' ? 'غیرفعال‌شده توسط مدیر' : 'انقضای اشتراک ربات') . "\n\n📞 لطفاً با پشتیبانی در ارتباط باشید.", null, 'HTML');
    http_response_code(200);
    exit;
}

if (!empty($setting['channel'])) {
    $channel = channel_check("@" . $setting['channel']);
    if (count($channel) != 0) {
        $keyboardchannel = [
            'inline_keyboard' => [
                [
                    ['text' => "عضویت در کانال", 'url' => "https://t.me/" . $setting['channel']]
                ],
                [
                    ['text' => "✅ عضو شدم", 'callback_data' => "confirmchannel"]
                ],
            ]
        ];
        $keyboardchannel = json_encode($keyboardchannel);
        sendmessage($from_id, "📌 جهت استفاده از تمامی قابلیت های ربات در کنال زیر عضو شده و سپس روی دکمه عضو شدم کلیک کنید", $keyboardchannel, "html");
        return;
    }
    if ($datain == "confirmchannel") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "✅  عضویت شما با موفقیت تایید شد", $keyboard, 'HTML');
    }
}

if (!isset($setting['show_product'])) {
    $setting['show_product'] = false;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
}
if (!isset($setting['active_step_note'])) {
    $setting['active_step_note'] = false;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
}
$settingmain = select("setting", "*", null, null, "select");
$globalCustomVolumeFlag = resolveGlobalCustomVolumeFlag((array)$setting, (array)$settingmain);
if ($globalCustomVolumeFlag === null) {
    $shopSettingFlag = resolveGlobalCustomVolumeFlagFromShopSettings();
    if ($shopSettingFlag !== null) {
        $globalCustomVolumeFlag = $shopSettingFlag;
    }
}
$showcard = 1;
$users_ids = select("user", "*", "bottype", $ApiToken, "FETCH_COLUMN");
if (!is_dir('data')) {
    mkdir('data');
}
if (!in_array($from_id, $users_ids) && $settingmain['statusnewuser'] == "onnewuser" && $from_id != 0) {

    $newuser = sprintf($textbotlang['Admin']['ManageUser']['newuser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $newuser, null, 'HTML');
    }
}

if ($from_id != 0) {
    $randomString = bin2hex(random_bytes(6));
    $date = time();
    $valueverify = 1;
    if (!is_dir("data/$from_id")) {
        @mkdir("data", 0775, true);
        @mkdir("data/$from_id", 0775, true);
        $data_user = json_encode(array(
            "Balance" => 0,
        ));
        file_put_contents("data/$from_id/$from_id.json", $data_user);
    }
    $initialProcessingValue = '0';
    $initialProcessingValueOne = 'none';
    $initialProcessingValueTow = 'none';
    $initialProcessingValueFour = '0';
    $initialRollStatus = '0';
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,bottype,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0',:bottype,'1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four)");
    $stmt->bindParam(':bottype', $ApiToken);
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $settingmain['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':showcard', $showcard);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->bindParam(':roll_status', $initialRollStatus);
    $stmt->bindParam(':processing_value', $initialProcessingValue);
    $stmt->bindParam(':processing_value_one', $initialProcessingValueOne);
    $stmt->bindParam(':processing_value_tow', $initialProcessingValueTow);
    $stmt->bindParam(':processing_value_four', $initialProcessingValueFour);
    $stmt->execute();
}
$user = select("user", "*", "id", $from_id, "select");
$user['Balance'] = json_decode(file_get_contents(__DIR__ . "/data/$from_id/$from_id.json"), true)['Balance'];
$usernameinvoice = select("invoice", "username", null, null, "FETCH_COLUMN");
$buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
$reportnight = select("topicid", "idreport", "report", "reportnight", "select")['idreport'];
$reporttest = select("topicid", "idreport", "report", "reporttest", "select")['idreport'];
$errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
$porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
$reportcron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
$otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];

$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$admin_idsmain = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$id_invoice = select("invoice", "id_invoice", null, null, "FETCH_COLUMN");
$userbot = select("user", "*", "id", $dataBase['id_user'], "select");
if ($user['bottype'] != $ApiToken) {
    update("user", "bottype", $ApiToken, "id", $from_id);
}
if ($user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
if ($text == "/start") {
    rx_dbg('G: start_received');
    // Red Fox فاز۳: پیام خوش‌آمدگویی اختصاصی از text.json
    $_brandStart = isset($text_bot_var['brand']['start_message']) && $text_bot_var['brand']['start_message'] !== ''
        ? str_replace('{name}', $first_name, $text_bot_var['brand']['start_message'])
        : "✋سلام $first_name عزیز به ربات ما خوش اومدی.\n\nبرای ادامه  یک بخش را انتخاب کنید:";
    $textstart = $_brandStart;
    if (!in_array($from_id, $admin_ids)) {
        rx_dbg('H: not_admin');
        // FIX: بررسی NULL-safe قیمت‌ها — اگر تنظیمات موجود نباشد، ربات نباید قفل شود
        $_minTime = (int)($setting['minpricetime'] ?? 0);
        $_priceTime = (int)($setting['pricetime'] ?? 0);
        $_minVol = (int)($setting['minpricevolume'] ?? 0);
        $_priceVol = (int)($setting['pricevolume'] ?? 0);
        rx_dbg('H2: minprice=' . $_minTime . ' price=' . $_priceTime . ' minvol=' . $_minVol . ' pricevol=' . $_priceVol);
        // فقط اگر هر چهار مقدار تنظیم شده باشند و حداقل از قیمت بیشتر باشد، قفل کن
        if ($_priceTime > 0 && $_priceVol > 0 && ($_minTime > $_priceTime || $_minVol > $_priceVol)) {
            rx_dbg('I: PRICE_CHECK_BLOCKED');
            foreach ($admin_ids as $admin) {
                sendmessage($admin, "❌ ادمین عزیز قیمت حجم یا زمان بروزرسانی شده است جهت فعالسازی ربات به پنل ادمین مراجعه و قیمت های جدید را اعمال کنید.", null, 'HTML');
            }
            sendmessage($from_id, "❌ درحال حاضر ربات در حال بروزرسانی است ساعتی دیگر مراجعه نمایید.", null, 'HTML');
            return;
        }
    }
    rx_dbg('J: before_sendmessage');
    $rxSendResult = sendmessage($from_id, $textstart, $keyboard, 'html');
    rx_dbg('K: after_sendmessage ok=' . (is_array($rxSendResult) ? ($rxSendResult['ok'] ? 'YES' : 'NO:' . redfox_remote_error_summary($rxSendResult)) : 'NULL'));
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    return;
} elseif ($text == "🏠 بازگشت به منوی اصلی" || $datain == "backuser") {
    if ($datain == "backuser")
        deletemessage($from_id, $message_id);
    sendmessage($from_id, "▶️ به منوی اصلی بازگشتید!", $keyboard, 'html');
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    return;
} elseif ($text == $text_bot_var['btn_keyboard']['wallet'] or $datain == "account") {
    $dateacc = jdate('Y/m/d');
    $current_time = time();
    $timeacc = jdate('H:i:s', $current_time);
    $first_name = htmlspecialchars($first_name);
    $Balanceuser = number_format($user['Balance'], 0);
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid' AND bottype = :apibot");
    $stmt->execute([
        ':from_id' => $from_id,
        ':apibot' => $ApiToken
    ]);
    $countpayment = $stmt->rowCount();
    $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    $text_account = "
🗂 اطلاعات حساب کاربری شما :


👤 نام: <code>$first_name</code>
⌚️زمان ثبت نام : $userjoin
💡 شناسه کاربری: <code>$from_id</code>
💰 موجودی: $Balanceuser تومان
💵 تعداد فاکتور های پرداخت شده : $countpayment عدد

📆 $dateacc → ⏰ $timeacc";
    if ($datain == "account") {
        step("home", $from_id);
        Editmessagetext($from_id, $message_id, $text_account, $KeyboardBalance);
    } else {
        sendmessage($from_id, $text_account, $KeyboardBalance, 'HTML');
    }
    return;
} elseif ($text == $text_bot_var['btn_keyboard']['my_service'] or $datain == "backorder") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND bottype = :apibot");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':apibot', $ApiToken);
    $stmt->execute();
    $invoices = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stmt->rowCount() == 0) {
        sendmessage($from_id, "⛔️ شما هیچ سرویسی فعالی ندارید", null, 'html');
        return;
    }
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND bottype = '$ApiToken' ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data = "";
        if ($row != null)
            $data = " | {$row['note']}";
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "✨" . $row['username'] . $data . "✨",
                'callback_data' => "product_" . $row['id_invoice']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => "بعدی",
            'callback_data' => 'next_page'
        ]
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, "🛍 برای مشاهده اطلاعات سرویس خود از لیست زیر سرویس خود را انتخاب نمایید", $keyboard_json);
    } else {
        sendmessage($from_id, "🛍 برای مشاهده اطلاعات سرویس خود از لیست زیر سرویس خود را انتخاب نمایید", $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page') {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND bottype = :apibot");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':apibot', $ApiToken);
    $stmt->execute();
    $numpage = $stmt->rowCount();
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND bottype = '$ApiToken' ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "✨" . $row['username'] . "✨",
                'callback_data' => "product_" . $row['id_invoice']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => "بعدی",
            'callback_data' => 'next_page'
        ],
        [
            'text' => "قبلی",
            'callback_data' => 'previous_page'
        ]
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, "🛍 برای مشاهده اطلاعات سرویس خود از لیست زیر سرویس خود را انتخاب نمایید", $keyboard_json);
} elseif ($datain == 'previous_page') {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND bottype = :apibot");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':apibot', $ApiToken);
    $stmt->execute();
    $numpage = $stmt->rowCount();
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND bottype = '$ApiToken' ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "✨" . $row['username'] . "✨",
                'callback_data' => "product_" . $row['id_invoice']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => "بعدی",
            'callback_data' => 'next_page'
        ],
        [
            'text' => "قبلی",
            'callback_data' => 'previous_page'
        ]
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, "🛍 برای مشاهده اطلاعات سرویس خود از لیست زیر سرویس خود را انتخاب نمایید", $keyboard_json);
} elseif ($text == $text_bot_var['btn_keyboard']['support']) {
    // Red Fox: اگر اشتراک هوش مصنوعی فعال است، مکالمه با AI شروع می‌شود
    $rxAiStatus = function_exists('rxVpnbotAiStatus') ? rxVpnbotAiStatus($ApiToken) : ['ok'=>false];
    if ($rxAiStatus['ok']) {
        $rxAiActive = true;
    } else {
        $rxAiActive = false;
    }
    // دکمه‌ی پشتیبانی با لینک همیشه نمایش داده می‌شود
    $Keyboardsupport = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📞 ارتباط با پشتیبانی", 'url' => 'https://t.me/' . $setting['support_username']],
            ],
        ]
    ]);
    if ($rxAiActive) {
        // AI فعال است → ورود به حالت چت با هوش مصنوعی
        $textsupport = "🤖 <b>پشتیبانی هوشمند فعال است!</b>\n\n💬 سؤال خود را بپرسید تا دستیار هوشمند پاسخ دهد.\n\n📌 برای پایان مکالمه و بازگشت به منوی اصلی، «بازگشت» را بفرستید.\n📞 برای ارتباط مستقیم با پشتیبانی از دکمه زیر:";
        sendmessage($from_id, $textsupport, $Keyboardsupport, 'html');
        step('vpnbot_ai_chat', $from_id);
    } else {
        $textsupport = "📞 برای ارتباط با ما  روی دکمه زیر کلیک کنید";
        sendmessage($from_id, $textsupport, $Keyboardsupport, 'html');
    }
} elseif ($user['step'] == "vpnbot_ai_chat" && $text) {
    // Red Fox: مکالمه‌ی چندمرحله‌ای با هوش مصنوعی در ربات نماینده
    // پایان مکالمه
    $rxEndWords = ['بازگشت', 'تمام', 'پایان', 'خداحافظ', 'بای', 'منو', 'انصراف', '/start'];
    foreach ($rxEndWords as $rxW) {
        if (mb_strpos(trim(mb_strtolower((string)$text)), $rxW) !== false) {
            step('home', $from_id);
            sendmessage($from_id, "👋 مکالمه پایان یافت.", $keyboard, 'HTML');
            return;
        }
    }

    $rxAiStatus = function_exists('rxVpnbotAiStatus') ? rxVpnbotAiStatus($ApiToken) : ['ok'=>false];
    if (!$rxAiStatus['ok']) {
        // اشتراک منقضی یا غیرفعال شده
        step('home', $from_id);
        $Keyboardsupport = json_encode([
            'inline_keyboard' => [
                [['text' => "📞 ارتباط با پشتیبانی", 'url' => 'https://t.me/' . $setting['support_username']]],
            ]
        ]);
        sendmessage($from_id, "⚠️ قابلیت پاسخ‌گویی هوش مصنوعی در حال حاضر فعال نیست.\n📞 از طریق دکمه زیر با پشتیبانی در ارتباط باشید.", $Keyboardsupport, 'html');
        return;
    }

    if (function_exists('rxVpnbotAiHandleSupport') && rxVpnbotAiHandleSupport($from_id, $username, $text, $ApiToken, $setting)) {
        // AI پاسخ داد — کاربر در حالت چت باقی می‌ماند
        return;
    }

    // AI جواب نداد یا نیاز به ارجاع دارد → لینک پشتیبانی
    step('home', $from_id);
    $Keyboardsupport = json_encode([
        'inline_keyboard' => [
            [['text' => "📞 ارتباط با پشتیبانی", 'url' => 'https://t.me/' . $setting['support_username']]],
        ]
    ]);
    sendmessage($from_id, "📨 لطفاً از طریق دکمه زیر با پشتیبانی در ارتباط باشید.", $Keyboardsupport, 'html');
} elseif ($text == $text_bot_var['btn_keyboard']['test']) {
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, "❌ سرویس تست درحال حاضر غیرفعال می باشد.", null, 'HTML');
        return;
    }
    if ($locationproduct != 1) {
        if ($user['limit_usertest'] <= 0) {
            sendmessage($from_id, "⚠️ محدودیت دریافت اکانت تست شما به پایان رسیده است .", $keyboard, 'html');
            return;
        }
        sendmessage($from_id, "📌 موقعیت سرویس خود را انتخاب کنید.", $list_marzban_usertest, 'html');
    }
}
if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || ($text == $text_bot_var['btn_keyboard']['test'])) {
    $userlimit = select("user", "*", "id", $from_id, "select");
    if ($userlimit['limit_usertest'] <= 0) {
        sendmessage($from_id, "⚠️ محدودیت دریافت اکانت تست شما به پایان رسیده است .", $keyboard, 'html');
        return;
    }
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 1) {
        $panel = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "select");
        if ($panel['hide_user'] != null) {
            $list_user = json_decode($panel['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, "❌ سرویس تست درحال حاضر غیرفعال می باشد.", null, 'HTML');
                return;
            }
        }
        $location = $panel['code_panel'];
    } else {
        if (isset($dataget[1])) {
            $location = $dataget[1];
        } else {
            if ($user['step'] != "createusertest") {
                return;
            } else {
                $location = $user['Processing_value_one'];
            }
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $location, "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if ($user['step'] != "createusertest") {
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $location, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
            return;
        }
    } else {
        $name_panel = $location;
    }
    if ($user['step'] == "createusertest") {
        $name_panel = $user['Processing_value_one'];
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
            return;
        }
    } else {
        deletemessage($from_id, $message_id);
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $value = "usertest";
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $value);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, "❌ موجودی این سرویس به پایان رسیده.", null, 'HTML');
            return;
        }
    }
    $limit_usertest = $userlimit['limit_usertest'] - 1;
    update("user", "limit_usertest", $limit_usertest, "id", $from_id);
    $randomString = bin2hex(random_bytes(4));
    $text = strtolower($text);
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $name_panel, "select");
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $user['username'], $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", strtotime("+" . $marzban_list_get['time_usertest'] . "hours"))),
        'data_limit' => $marzban_list_get['val_usertest'] * 1048576,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest_' . $dataBase['username']
    );
    $date = time();
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,bottype,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?)");
    $Status = "active";
    $info_product['name_product'] = "سرویس تست";
    $info_product['price_product'] = "0";
    $Status = "active";
    $stmt->bind_param("ssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $marzban_list_get['val_usertest'], $marzban_list_get['time_usertest'], $Status, $ApiToken, $notifctions);
    $stmt->execute();
    $stmt->close();
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], "usertest", $username_ac, $datac);
    if (empty($dataoutput['username'])) {
        $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
        if (strlen($settingmain['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $settingmain['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ], $APIKEY);
        }
        step('home', $from_id);
        update("invoice", "Status", "Unsuccessful", "id_invoice", $randomString);
        return;
    }
    $output_config_link = "";
    $config = "";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get['config'] == "onconfig") {
        foreach ($dataoutput['configs'] as $configs) {
            $config .= "\n" . $configs;
        }
    }
    $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

{connection_links}";
    if ($marzban_list_get['type'] == "WGDashboard") {
        $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    if ($marzban_list_get['type'] == "ibsng") {
        $datatextbot['textafterpay'] = $datatextbot['textafterpayibsng'];
    }
    $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textaftertext']);
    $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $marzban_list_get['time_usertest'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $marzban_list_get['val_usertest'], $textcreatuser);
    $trimmedSubscription = trim($output_config_link);
    $trimmedConfigList = trim($config);
    $connectionSections = [];
    if ($trimmedSubscription !== '') {
        $connectionSections[] = "لینک اتصال:\n<code>{$trimmedSubscription}</code>";
    }
    if ($trimmedConfigList !== '') {
        $connectionSections[] = "لینک اشتراک :\n<code>{$trimmedConfigList}</code>";
    }
    $connectionLinksBlock = implode("\n\n", $connectionSections);
    $textcreatuser = str_replace('{connection_links}', $connectionLinksBlock, $textcreatuser);
    if ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "ibsng") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    if ($marzban_list_get['sublink'] == "onsublink") {
        $urlimage = "$from_id$randomString.png";
        $qrCode = createqrcode($output_config_link);
        file_put_contents($urlimage, $qrCode->getString());
        if (!addBackgroundImage($urlimage, $qrCode, $Pathfiles . 'images.jpg')) {
            error_log("Unable to apply background image for QR code using path '{$Pathfiles}images.jpg'");
        }
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => $textcreatuser,
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
        if ($marzban_list_get['type'] == "WGDashboard") {
            $urlimage = "{$marzban_list_get['inboundid']}_{$dataoutput['username']}.conf";
            file_put_contents($urlimage, $output_config_link);
            sendDocument($from_id, $urlimage, "⚙️ کانفیگ شما");
            unlink($urlimage);
        }
    } elseif ($marzban_list_get['config'] == "onconfig") {
        if (count($dataoutput['configs']) == 1) {
            $urlimage = "$from_id$randomString.png";
            $qrCode = createqrcode($config);
            file_put_contents($urlimage, $qrCode->getString());
            if (!addBackgroundImage($urlimage, $qrCode, $Pathfiles . 'images.jpg')) {
                error_log("Unable to apply background image for QR code using path '{$Pathfiles}images.jpg'");
            }
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'caption' => $textcreatuser,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        } else {
            sendmessage($from_id, $textcreatuser, $usertestinfo, 'HTML');
        }
    } else {
        sendmessage($from_id, $textcreatuser, $usertestinfo, 'HTML');
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    step('home', $from_id);
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($settingmain['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report_admin = rx_message_template($pdo,'report_reseller_test',[
        'user_id'=>$from_id,'agent_id'=>$userbot['id'],'reseller_bot'=>$dataBase['username'],'user_username'=>$username,'config_username'=>$username_ac,'customer_name'=>$first_name,'panel_name'=>$marzban_list_get['name_panel'],'test_hours'=>$marzban_list_get['time_usertest'],'test_volume'=>$marzban_list_get['val_usertest'],'tracking_code'=>$randomString,'purchase_time'=>$timejalali
    ]);
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report_admin,
            'parse_mode' => "HTML"
        ], $APIKEY);
    }
}
// Unified help/software menus for every reseller bot.
if ($text==='📚 آموزش اتصال'||$datain==='rxhelp_categories') { sendmessage($from_id,'📚 دسته‌بندی آموزش را انتخاب کنید:',rx_content_category_keyboard($pdo,'help','rxhelpcat_'),'HTML'); return; }
if (preg_match('/^rxhelpcat_([A-Za-z0-9_-]+)$/',$datain,$m)) { $cat=rx_content_untoken($m[1]);if($cat!==null)Editmessagetext($from_id,$message_id,'📚 آموزش‌های '.htmlspecialchars($cat),rx_content_help_items($pdo,$cat,'rxhelp_categories'),'HTML');return; }
if (preg_match('/^rxhelpitem_(\d+)$/',$datain,$m)) { $h=rx_content_help_row($pdo,(int)$m[1]);if(!$h)return;$back=json_encode(['inline_keyboard'=>[[['text'=>'🔙 آموزش‌ها','callback_data'=>'rxhelp_categories']]]],JSON_UNESCAPED_UNICODE);if(($h['type_Media_os']??'')==='photo'&&!empty($h['Media_os']))telegram('sendPhoto',['chat_id'=>$from_id,'photo'=>$h['Media_os'],'caption'=>$h['Description_os'],'reply_markup'=>$back,'parse_mode'=>'HTML']);elseif(($h['type_Media_os']??'')==='video'&&!empty($h['Media_os']))telegram('sendVideo',['chat_id'=>$from_id,'video'=>$h['Media_os'],'caption'=>$h['Description_os'],'reply_markup'=>$back,'parse_mode'=>'HTML']);else sendmessage($from_id,(string)$h['Description_os'],$back,'HTML');return; }
if ($text==='📱 نرم‌افزارهای اتصال'||$datain==='rxapp_categories') { sendmessage($from_id,'📱 سیستم‌عامل را انتخاب کنید:',rx_content_category_keyboard($pdo,'app','rxappcat_'),'HTML'); return; }
if (preg_match('/^rxappcat_([A-Za-z0-9_-]+)$/',$datain,$m)) { $cat=rx_content_untoken($m[1]);if($cat!==null)Editmessagetext($from_id,$message_id,'📥 نرم‌افزارهای '.htmlspecialchars($cat),rx_content_software_keyboard($pdo,$cat,'rxapp_categories'),'HTML');return; }
$rxBuyPressed=($text===(string)($text_bot_var['btn_keyboard']['buy']??'')||in_array(trim((string)$text),['🛍 خرید اشتراک','🛍️ خرید اشتراک','خرید اشتراک','خرید سرویس'],true)||in_array((string)$datain,['buy','buyback'],true));
if ($rxBuyPressed) {
    rx_dbg('BUY_V2_ENTER owner='.(string)($dataBase['id_user']??''));
    $pq=$pdo->prepare("SELECT * FROM marzban_panel WHERE status='active' AND (agent=? OR agent=? OR agent='all') ORDER BY id");
    $pq->execute([(string)($userbot['agent']??''),(string)($dataBase['id_user']??'')]);$panels=$pq->fetchAll(PDO::FETCH_ASSOC);$pq->closeCursor();
    $visible=[];$hidden=is_array($hide_panel??null)?$hide_panel:[];foreach($panels as$p){$hu=json_decode((string)($p['hide_user']??''),true);if(is_array($hu)&&in_array($from_id,$hu))continue;if(in_array($p['name_panel'],$hidden,true))continue;$visible[]=$p;}
    if(!$visible){sendmessage($from_id,'❌ در حال حاضر پنل فعالی برای خرید این ربات تعریف نشده است. لطفاً با فروشنده تماس بگیرید.',$keyboard,'HTML');return;}
    if(count($visible)>1){$kb=['inline_keyboard'=>[]];foreach($visible as$p)$kb['inline_keyboard'][]=[['text'=>$p['name_panel'],'callback_data'=>'location_'.$p['code_panel']]];$kb['inline_keyboard'][]=[['text'=>'🔙 بازگشت','callback_data'=>'backuser']];sendmessage($from_id,'📍 موقعیت سرویس را انتخاب کنید:',json_encode($kb,JSON_UNESCAPED_UNICODE),'HTML');return;}
    $panel=$visible[0];savedata('clear','name_panel',(string)$panel['name_panel']);
    $sql="SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND (agent=:agent OR agent=:owner OR agent='all') ORDER BY CAST(price_product AS UNSIGNED) LIMIT 80";$params=[':loc'=>$panel['name_panel'],':agent'=>(string)($userbot['agent']??''),':owner'=>(string)($dataBase['id_user']??'')];$cq=$pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location=:loc OR Location='/all') AND (agent=:agent OR agent=:owner OR agent='all')");$cq->execute($params);$count=(int)$cq->fetchColumn();$cq->closeCursor();
    if($count===0){sendmessage($from_id,'❌ هنوز محصولی برای این ربات و پنل تعریف نشده است. فروشنده باید ابتدا محصول قابل فروش ایجاد کند.',$keyboard,'HTML');return;}
    $custom=isCustomVolumeEnabledForAgent($panel,$userbot['agent']??null)&&($panel['type']??'')!=='Manualsale';$key=(($panel['MethodUsername']??'')===$textbotlang['users']['customusername']||($panel['MethodUsername']??'')==='نام کاربری دلخواه + عدد رندوم')?'selectproductbuyy_':'selectproductbuy_';$productKb=KeyboardProduct($panel['name_panel'],$sql,0,$key,$custom,'backuser',null,'customvolumebuy',$params);$sent=sendmessage($from_id,'🛍️ سرویس موردنظر را انتخاب کنید:',$productKb,'HTML');if(empty($sent['ok'])){error_log('[vpnbot BUY_V2 send failed] '.json_encode($sent,JSON_UNESCAPED_UNICODE));sendmessage($from_id,'❌ نمایش فهرست محصولات ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',null,'HTML');}return;
}
if (preg_match('/^location_([^\s]+)$/',(string)$datain,$lm)) {
    $q=$pdo->prepare("SELECT * FROM marzban_panel WHERE code_panel=? AND status='active' AND (agent=? OR agent=? OR agent='all') LIMIT 1");$q->execute([$lm[1],(string)($userbot['agent']??''),(string)($dataBase['id_user']??'')]);$panel=$q->fetch(PDO::FETCH_ASSOC);if(!$panel){telegram('answerCallbackQuery',['callback_query_id'=>$callback_query_id,'text'=>'پنل معتبر نیست','show_alert'=>true]);return;}savedata('clear','name_panel',(string)$panel['name_panel']);$sql="SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND (agent=:agent OR agent=:owner OR agent='all') ORDER BY CAST(price_product AS UNSIGNED) LIMIT 80";$params=[':loc'=>$panel['name_panel'],':agent'=>(string)($userbot['agent']??''),':owner'=>(string)($dataBase['id_user']??'')];$cq=$pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location=:loc OR Location='/all') AND (agent=:agent OR agent=:owner OR agent='all')");$cq->execute($params);if((int)$cq->fetchColumn()===0){Editmessagetext($from_id,$message_id,'❌ محصولی برای این پنل تعریف نشده است.',json_encode(['inline_keyboard'=>[[['text'=>'🔙 بازگشت','callback_data'=>'backuser']]]],JSON_UNESCAPED_UNICODE),'HTML');return;}$custom=isCustomVolumeEnabledForAgent($panel,$userbot['agent']??null)&&($panel['type']??'')!=='Manualsale';$key=(($panel['MethodUsername']??'')===$textbotlang['users']['customusername']||($panel['MethodUsername']??'')==='نام کاربری دلخواه + عدد رندوم')?'selectproductbuyy_':'selectproductbuy_';Editmessagetext($from_id,$message_id,'🛍️ سرویس موردنظر را انتخاب کنید:',KeyboardProduct($panel['name_panel'],$sql,0,$key,$custom,'backuser',null,'customvolumebuy',$params),'HTML');return;
}
if ($rxBuyPressed && !empty($setting['active_step_note'])) {
    sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    step("statusnamecustom", $from_id);
    return;
} elseif ($rxBuyPressed || $user['step'] == "statusnamecustom") {
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active'");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
if (mysqli_num_rows($locationproduct) == 1) {
    $location = mysqli_fetch_assoc($locationproduct)['name_panel'];
    $locationproduct = select("marzban_panel", "*", "name_panel", $location, "select");
    $query = "SELECT * FROM product
              WHERE (Location = '{$locationproduct['name_panel']}' OR Location = '/all')
              AND (agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}' OR agent = 'all')";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $productnotexits = $stmt->rowCount();


        $productnotexits = $stmt->rowCount();
        if ($locationproduct['hide_user'] != null) {
            $list_user = json_decode($locationproduct['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
        }
        // Local invoice count is advisory only; real VPN API decides capacity.
        if ($user['step'] == "statusnamecustom") {
            savedata('clear', "note", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        $marzban_list_get = $locationproduct;
        if ($productnotexits != 0 and $setting['show_product'] == false) {
            if ($settingmain['statuscategorygenral'] == "offcategorys" && ($setting['statuscategorygenral'] ?? 'offcategorys') == "offcategorys") {
                $customVolumeEnabled = isCustomVolumeEnabledForAgent($locationproduct, $userbot['agent'] ?? null);
                $statuscustom = $customVolumeEnabled && $locationproduct['type'] != "Manualsale";
                if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                    $keyboarddata = "selectproductbuyy_";
                } else {
                    $keyboarddata = "selectproductbuy_";
                }
                $prodcut = KeyboardProduct(
                    $marzban_list_get['name_panel'],
                    $query,
                    0,
                    $keyboarddata,
                    $statuscustom,
                    "backuser",
                    null,
                    $customvolume = "customvolumebuy"
                );
                sendmessage($from_id, "🛍️ لطفاً سرویسی که می‌خواهید خریداری کنید را انتخاب کنید!", $prodcut, 'HTML');
                return;
            } else {
                $__np = $pdo->prepare("SELECT COUNT(*) FROM product WHERE agent IN (?, ?, 'all')"); $__np->execute([(string)$userbot['agent'], (string)$dataBase['id_user']]); $nullproduct = (int)$__np->fetchColumn();
                if ($nullproduct == 0) {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                    return;
                }
                sendmessage(
                    $from_id,
                    "📌 دسته بندی خود را انتخاب نمایید!",
                    KeyboardCategory($marzban_list_get['name_panel'], $userbot['agent'], "backuser", (string)$dataBase['id_user']),
                    'HTML'
                );
                return;
            }
        } else {

            $customVolumeEnabled = isCustomVolumeEnabledForAgent($locationproduct, $userbot['agent'] ?? null);

            if ($customVolumeEnabled && $locationproduct['type'] != "Manualsale") {

                $marzban_list_get = $locationproduct;
                $eextraprice      = $setting['pricevolume'];

                $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
                $mainvolume = $mainvolume[$userbot['agent']];
                $maxvolume  = json_decode($marzban_list_get['maxvolume'], true);
                $maxvolume  = $maxvolume[$userbot['agent']];

                $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
        🔔قیمت هر گیگ حجم $eextraprice تومان می باشد.
        🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
                sendmessage($from_id, $textcustom, $backuser, 'html');
                step('gettimecustomvol', $from_id);

            } else {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
            }
            return;
        }
    }
    if ($user['step'] == "statusnamecustom") {
        savedata('clear', "note", $text);
        step("home", $from_id);
    }
    sendmessage($from_id, "📌 موقعیت سرویس خود را انتخاب کنید", $list_marzban_panel_user, 'HTML');

} elseif ($datain == "customvolumebuy") {

    $userdate        = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice     = $setting['pricevolume'];
    $mainvolume      = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume      = $mainvolume[$userbot['agent']];
    $maxvolume       = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume       = $maxvolume[$userbot['agent']];

    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $eextraprice تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvol', $from_id);

} elseif (preg_match('/^location_(.*)/', $datain, $dataget)) {

    $userdate        = json_decode($user['Processing_value'], true);
    $locationproduct = select("marzban_panel", "*", "code_panel", $dataget[1], "select");

    if (isset($userdate['note'])) {
        savedata("save", "name_panel", $locationproduct['name_panel']);
    } else {
        savedata("clear", "name_panel", $locationproduct['name_panel']);
    }
    // Local invoice count never blocks reseller purchases.


    $query = "SELECT * FROM product
              WHERE (Location = '{$locationproduct['name_panel']}' OR Location = '/all')
              AND (agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}' OR agent = 'all')";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $productnotexits = $stmt->rowCount();

    if ($productnotexits != 0 and $setting['show_product'] == false) {

        if ($settingmain['statuscategorygenral'] == "offcategorys" && ($setting['statuscategorygenral'] ?? 'offcategorys') == "offcategorys") {
            $customVolumeEnabled = isCustomVolumeEnabledForAgent($locationproduct, $userbot['agent'] ?? null);
            $statuscustom = $customVolumeEnabled && $locationproduct['type'] != "Manualsale";
            if ($locationproduct['MethodUsername'] == $textbotlang['users']['customusername'] || $locationproduct['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                $keyboarddata = "selectproductbuyy_";
            } else {
                $keyboarddata = "selectproductbuy_";
            }
            $prodcut = KeyboardProduct(
                $locationproduct['name_panel'],
                $query,
                0,
                $keyboarddata,
                $statuscustom,
                "backuser",
                null,
                $customvolume = "customvolumebuy"
            );
            Editmessagetext($from_id, $message_id, "🛍️ لطفاً سرویسی که می‌خواهید خریداری کنید را انتخاب کنید!", $prodcut, 'HTML');
        } else {
            $__np = $pdo->prepare("SELECT COUNT(*) FROM product WHERE agent IN (?, ?, 'all')"); $__np->execute([(string)$userbot['agent'], (string)$dataBase['id_user']]); $nullproduct = (int)$__np->fetchColumn();
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($locationproduct['name_panel'], $userbot['agent'], "backuser", (string)$dataBase['id_user']));
        }

    } else {

        $customVolumeEnabled = isCustomVolumeEnabledForAgent($locationproduct, $userbot['agent'] ?? null);

        if ($customVolumeEnabled && $locationproduct['type'] != "Manualsale") {

            deletemessage($from_id, $message_id);
            $marzban_list_get = $locationproduct;
            $eextraprice      = $setting['pricevolume'];

            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$userbot['agent']];
            $maxvolume  = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume  = $maxvolume[$userbot['agent']];

            $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
    🔔قیمت هر گیگ حجم $eextraprice تومان می باشد.
    🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
            sendmessage($from_id, $textcustom, $backuser, 'html');
            step('gettimecustomvol', $from_id);

        } else {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        }
        return;
    }

} elseif (preg_match('/^resellercategory_(\d+)$/', $datain, $dataget)) {
    $categoryId=(int)$dataget[1];$ownerId=(string)($dataBase['id_user']??'');
    $cq=$pdo->prepare("SELECT id,name FROM reseller_categories WHERE id=? AND reseller_id=? AND status='active' LIMIT 1");$cq->execute([$categoryId,$ownerId]);$rcat=$cq->fetch(PDO::FETCH_ASSOC);
    if(!$rcat){telegram('answerCallbackQuery',['callback_query_id'=>$callback_query_id,'text'=>'دسته نامعتبر است','show_alert'=>true]);return;}
    $userdate=json_decode($user['Processing_value'],true)?:[];$locationproduct=select("marzban_panel","*","name_panel",(string)($userdate['name_panel']??''),"select");
    if(!is_array($locationproduct)){sendmessage($from_id,"❌ پنل انتخابی یافت نشد.",null,'HTML');return;}
    $query="SELECT p.* FROM product p JOIN reseller_product_categories m ON m.product_code=p.code_product WHERE m.reseller_id=:owner AND m.category_id=:category AND (p.Location=:location OR p.Location='/all') AND (p.agent=:agent OR p.agent=:owner2 OR p.agent='all')";
    $params=[':owner'=>$ownerId,':category'=>$categoryId,':location'=>$locationproduct['name_panel'],':agent'=>$userbot['agent'],':owner2'=>$ownerId];
    $statuscustom=isCustomVolumeEnabledForAgent($locationproduct,$userbot['agent']??null)&&$locationproduct['type']!="Manualsale";
    $keyboarddata=($locationproduct['MethodUsername']==$textbotlang['users']['customusername']||$locationproduct['MethodUsername']=="نام کاربری دلخواه + عدد رندوم")?"selectproductbuyy_":"selectproductbuy_";
    $productsKeyboard=KeyboardProduct($locationproduct['name_panel'],$query,0,$keyboarddata,$statuscustom,"backuser",null,"customvolumebuy",$params);
    Editmessagetext($from_id,$message_id,"🛍️ ".htmlspecialchars($rcat['name'])." — محصول را انتخاب کنید",$productsKeyboard);
} elseif (preg_match('/^categorynames_(.*)/', $datain, $dataget)) {

    $categorynames = $dataget[1];
    $categorynames = select("category", "remark", "id", $categorynames, "select")['remark'];
    $userdate      = json_decode($user['Processing_value'], true);
    $locationproduct = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "seelct");

    $query = "SELECT * FROM product
          WHERE (Location = '{$locationproduct['name_panel']}' OR Location = '/all')
          AND (agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}' OR agent = 'all')
          AND category = '$categorynames' ";

    $customVolumeEnabled = isCustomVolumeEnabledForAgent($locationproduct, $userbot['agent'] ?? null);
    $statuscustom = $customVolumeEnabled && $locationproduct['type'] != "Manualsale";
    if ($locationproduct['MethodUsername'] == $textbotlang['users']['customusername'] || $locationproduct['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $keyboarddata = "selectproductbuyy_";
    } else {
        $keyboarddata = "selectproductbuy_";
    }
    $prodcut = KeyboardProduct(
        $locationproduct['name_panel'],
        $query,
        0,
        $keyboarddata,
        $statuscustom,
        "backuser",
        null,
        $customvolume = "customvolumebuy"
    );
    Editmessagetext($from_id, $message_id, "🛍️ لطفاً سرویسی که می‌خواهید خریداری کنید را انتخاب کنید!", $prodcut, 'HTML');

} elseif ($user['step'] == "gettimecustomvol") {

    $userdate        = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");

    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$userbot['agent']];
    $maxvolume  = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume  = $maxvolume[$userbot['agent']];
    $maintime   = json_decode($marzban_list_get['maintime'], true);
    $maintime   = $maintime[$userbot['agent']];
    $maxtime    = json_decode($marzban_list_get['maxtime'], true);
    $maxtime    = $maxtime[$userbot['agent']];

    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }

    $customtimevalueprice = $setting['pricetime'];
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');

    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        step('getvolumecustomusername', $from_id);
    } else {
        step('getvolumecustomuser', $from_id);
    }

} elseif ($user['step'] == "getvolumecustomusername" || preg_match('/selectproductbuyy_(.*)/', $datain, $dataget)) {
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($user['step'] == "getvolumecustomusername") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$userbot['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$userbot['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        step('endstepuserscustom', $from_id);
        savedata("save", "time", $text);
    } else {
        $prodcut = $dataget[1];
        savedata("save", "code_product", $prodcut);
        step('endstepusers', $from_id);
    }
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
} elseif ($user['step'] == "endstepusers" || $user['step'] == "endstepuserscustom" || $user['step'] == "getvolumecustomuser" || preg_match('/selectproductbuy_(.*)/', $datain, $dataget)) {
    $userdate = json_decode($user['Processing_value'], true);
    if ($user['step'] == "getvolumecustomuser") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, "زمان نامعتبر است", $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$userbot['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$userbot['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        savedata("save", "time", $text);
        $userdate['time'] = $text;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
            return;
        }
        if ($user['step'] == "endstepusers") {
            $code_product = $userdate['code_product'];
        }
    } else {
        if (isset($dataget[1])) {
            $code_product = $dataget[1];
        } else {
            $code_product = $userdate['code_product'] ?? null;
        }
    }
    $needsProductCode = !in_array($user['step'], ['endstepuserscustom', 'getvolumecustomuser'], true);
    if ($needsProductCode && ($code_product === null || $code_product === '')) {
        sendmessage($from_id, "❌ خطایی در هنگام خرید رخ داده لطفا مراحل را از اول طی کنید", $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if (!in_array($user['step'], ["endstepuserscustom", "getvolumecustomuser"])) {
        $product = select("product", "*", "code_product", $code_product);
        if ($product == false || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) {
            sendmessage($from_id, "❌ خطایی در هنگام خرید رخ داده لطفا مراحل را از اول طی کنید", $keyboard, 'html');
            step("home", $from_id);
            return;
        }
        savedata("save", "code_product", $code_product);
        $productlist = readJsonFileIfExists(__DIR__ . '/product.json');
        if (isset($productlist[$product['code_product']])) {
            $product['price_product'] = $productlist[$product['code_product']];
        }
        $datapish = array(
            "Volume_constraint" => $product['Volume_constraint'],
            "name_product" => $product['name_product'],
            "code_product" => $product['code_product'],
            "Service_time" => $product['Service_time'],
            "price_product" => $product['price_product']
        );
    } else {
        $custompricevalue = $setting['pricevolume'];
        $customtimevalueprice = $setting['pricetime'];
        $datapish = array(
            "Volume_constraint" => $userdate['volume'],
            "name_product" => $textbotlang['users']['customsellvolume']['title'],
            "code_product" => "customvolume",
            "Service_time" => $userdate['time'],
            "price_product" => ($userdate['volume'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice)
        );
    }
    $randomString = bin2hex(random_bytes(2));
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    savedata("save", "username", $username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    if (intval($datapish['Volume_constraint']) == 0)
        $datapish['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($datapish['Service_time']) == 0)
        $datapish['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product_price_product = number_format($datapish['price_product']);
    $userBalance = number_format($user['Balance']);
    $replacements = [
        '{username}' => $username_ac,
        '{Service_time}' => $datapish['Service_time'],
        '{price}' => $info_product_price_product,
        '{Volume}' => $datapish['Volume_constraint'],
        '{userBalance}' => $userBalance
    ];
    $textpishfactor = "📇 پیش فاکتور شما:
👤 نام کاربری:  {username}
📆 مدت اعتبار: {Service_time} روز
💶 قیمت:  {price} تومان
👥 حجم اکانت: {Volume} گیگ
💵 موجودی کیف پول شما : {userBalance}

💰 سفارش شما آماده پرداخت است";
    $textin = strtr($textpishfactor, $replacements);
    if (intval($datapish['Volume_constraint']) == 0) {
        $textin = str_replace('گیگ', "", $textin);
    }
    if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], [$textbotlang['users']['customusername'], "نام کاربری دلخواه + عدد رندوم"])) {
        Editmessagetext($from_id, $message_id, $textin, $payment);
    } else {
        sendmessage($from_id, $textin, $payment, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && $datain == "confirmandgetservice") {
    $userdate = json_decode($user['Processing_value'], true);
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    if (!isset($userdate['name_panel'])) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل خرید را از اول انجام دهید", $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل خرید را از اول انجام دهید", $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if (isset($userdate['code_product'])) {
        $product = $userdate['code_product'];
        $product = select("product", "*", "code_product", $product);
        if (!is_array($product) || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) { sendmessage($from_id, "❌ محصول برای این ربات مجاز نیست.", null, 'HTML'); if (function_exists('step')) step('home', $from_id); return; }
        $priceBot = $product['price_product'];
        $productlist = readJsonFileIfExists(__DIR__ . '/product.json');
        if (isset($productlist[$product['code_product']])) {
            $product['price_product'] = $productlist[$product['code_product']];
        }
        $pricevalue = $product['price_product'];
        $datafactor = array(
            "Volume_constraint" => $product['Volume_constraint'],
            "name_product" => $product['name_product'],
            "Service_time" => $product['Service_time'],
            "code_product" => $product['code_product'],
            "price_product" => $product['price_product'],
            "price_productMain" => $priceBot,
            "data_limit_reset" => $product['data_limit_reset']
        );
    } else {
        $custompricevalue = $setting['pricevolume'];
        $customtimevalueprice = $setting['pricetime'];
        $custompricevalueBot = $setting['minpricevolume'];
        $customtimevaluepriceBot = $setting['minpricetime'];
        $datafactor = array(
            "Volume_constraint" => $userdate['volume'],
            "name_product" => $textbotlang['users']['customsellvolume']['title'],
            "Service_time" => $userdate['time'],
            "code_product" => "customvolume",
            "price_product" => ($userdate['volume'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice),
            "price_productMain" => intval(($userdate['volume'] * $custompricevalueBot) + ($userdate['time'] * $customtimevaluepriceBot)),
            "data_limit_reset" => "no_reset"
        );
    }
    $botbalance = select("botsaz", "*", "bot_token", $ApiToken, "select");
    $userbotbalance = select("user", "*", "id", $botbalance['id_user'], "select");
    if (($datafactor['price_productMain'] > $userbotbalance['Balance']) && $userbotbalance['agent'] != "n2") {
        $creditVars=['agent_id'=>$userbotbalance['id'],'agent_balance'=>number_format((int)$userbotbalance['Balance']),'required_amount'=>number_format((int)$datafactor['price_productMain'])];
        sendmessage($from_id,rx_message_template($pdo,'alert_reseller_no_credit_customer',$creditVars),$keyboard,'HTML');
        step("home", $from_id);
        foreach ($admin_ids as $admin) sendmessage($admin,rx_message_template($pdo,'alert_reseller_no_credit_admin',$creditVars),null,'HTML');
        return;
    }
    $username_ac = strtolower($userdate['username']);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $random_number = rand(1000000, 9999999);
    if (in_array($randomString, $id_invoice)) {
        $randomString = $random_number . $randomString;
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $datafactor['code_product']);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
            return;
        }
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,bottype,note,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?, ?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $datafactor['name_product'], $datafactor['price_product'], $datafactor['Volume_constraint'], $datafactor['Service_time'], $Status, $ApiToken, $userdate['note'], $notifctions);
    $stmt->execute();
    $stmt->close();
    if ($datafactor['price_product'] > $user['Balance'] && intval($datafactor['price_product']) != 0) {
        $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        $Balance_prim = $datafactor['price_product'] - $user['Balance'];
        if ($Balance_prim <= 1)
            $Balance_prim = 0;
        $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$userbot['agent']]);
        $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$userbot['agent']]);
        $bakinfos = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "❌ موجودی شما برای خرید سرویس کافی نمی باشد.
💸  برای افزایش موجودی مبلغ را  به تومان وارد کنید:
✅  حداقل مبلغ $minbalance حداکثر مبلغ $maxbalance تومان می باشد", $bakinfos, 'HTML');
        step('get_price', $from_id);
        return;
    }
    Editmessagetext($from_id, $message_id, "♻️ در حال ساختن سرویس شما...", null);
    $datetimestep = strtotime("+" . $datafactor['Service_time'] . "days");
    if ($datafactor['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $datafactor['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buy_agent_user_bot'
    );
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $datafactor['code_product'], $username_ac, $datac);
    if (empty($dataoutput['username'])) {
        $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
        sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
        $texterros = "⭕️ خطای ساخت اشتراک  در ربات نماینده
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
        if (strlen($settingmain['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $settingmain['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ], $APIKEY);
        }
        step('home', $from_id);
        return;
    }
    update("invoice", "Status", "active", "username", $username_ac);
    $configqr = "";
    $output_config_link = "";
    $config = "";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get['config'] == "onconfig") {
        if (isset($dataoutput['configs']) and count($dataoutput['configs']) != 0) {
            foreach ($dataoutput['configs'] as $configs) {
                $config .= "\n" . $configs;
                $configqr .= $configs;
            }
        } else {
            $config .= "";
            $configqr .= "";
        }
    }
    $textafterpay = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

{connection_links}
";
    $textmanual = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
🇺🇳 لوکیشن: {location}

 اطلاعات سرویس :
{connection_links}
";
    if ($marzban_list_get['type'] == "ibsng") {
        $datatextbot['textafterpay'] = $datatextbot['textafterpayibsng'];
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $textafterpay = $textmanual;
    }
    if ($marzban_list_get['type'] == "WGDashboard") {
        $datatextbot['textafterpay'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    if (intval($datafactor['Service_time']) == 0)
        $datafactor['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($datafactor['Volume_constraint']) == 0)
        $datafactor['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $textafterpay);
    $textcreatuser = str_replace('{name_service}', $datafactor['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $datafactor['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $datafactor['Volume_constraint'], $textcreatuser);
    $trimmedSubscription = trim($output_config_link);
    $trimmedConfigList = trim($config);
    $connectionSections = [];
    if ($trimmedSubscription !== '') {
        $connectionSections[] = "لینک اتصال:\n<code>{$trimmedSubscription}</code>";
    }
    if ($trimmedConfigList !== '') {
        $connectionSections[] = "لینک اشتراک :\n<code>{$trimmedConfigList}</code>";
    }
    $connectionLinksBlock = implode("\n\n", $connectionSections);
    $textcreatuser = str_replace('{connection_links}', $connectionLinksBlock, $textcreatuser);
    if (intval($datafactor['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "ibsng") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    if ($marzban_list_get['type'] == "Manualsale" | $marzban_list_get['type'] == "ibsng") {
        sendmessage($from_id, $textcreatuser, null, 'HTML');
    } else {
        if (count($dataoutput['configs']) != 1 and $marzban_list_get['config'] == "onconfig") {
            sendmessage($from_id, $textcreatuser, null, 'HTML');
        } else {
            if ($marzban_list_get['sublink'] == "offsublink") {
                $output_config_link = $configqr;
            }
            $urlimage = "$from_id$randomString.png";
            $qrCode = createqrcode($output_config_link);
            file_put_contents($urlimage, $qrCode->getString());
            if (!addBackgroundImage($urlimage, $qrCode, $Pathfiles . 'images.jpg')) {
                error_log("Unable to apply background image for QR code using path '{$Pathfiles}images.jpg'");
            }
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'caption' => $textcreatuser,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
            if ($marzban_list_get['type'] == "WGDashboard") {
                $urlimage = "{$marzban_list_get['inboundid']}_{$dataoutput['username']}.conf";
                file_put_contents($urlimage, $output_config_link);
                sendDocument($from_id, $urlimage, "⚙️ کانفیگ شما");
                unlink($urlimage);
            }
        }
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (intval($userbotbalance['pricediscount']) != 0) {
        $resultper = ($datafactor['price_productMain'] * $userbotbalance['pricediscount']) / 100;
        $datafactor['price_productMain'] = $datafactor['price_productMain'] - $resultper;
    }
    if (intval($datafactor['price_product']) != 0) {
        $Balance_prim = $user['Balance'] - $datafactor['price_product'];
        $userbalance = json_decode(file_get_contents(__DIR__ . "/data/$from_id/$from_id.json"), true);
        $userbalance['Balance'] = $Balance_prim;
        file_put_contents("data/$from_id/$from_id.json", json_encode($userbalance));
    }
    $Balancebot = $userbotbalance['Balance'] - $datafactor['price_productMain'];
    update("user", "Balance", $Balancebot, "id", $userbotbalance['id']);
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($settingmain['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':bottype', $ApiToken);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = "📌 خرید اول کاربر";
    }
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $balanceagent_before = number_format($userbotbalance['Balance'], 0);
    $balanceagent_after = number_format($Balancebot, 0);
    $balance_after = number_format($Balance_prim, 0);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = rx_message_template($pdo,'report_reseller_purchase',[
        'first_purchase'=>$textonebuy,'user_id'=>$from_id,'agent_id'=>$userbot['id'],'user_username'=>ltrim((string)$username,'@'),'reseller_bot'=>ltrim((string)$dataBase['username'],'@'),'config_username'=>$username_ac,'customer_name'=>$first_name,'panel_name'=>$userdate['name_panel'],'service_days'=>$datafactor['Service_time'],'volume_gb'=>$datafactor['Volume_constraint'],'user_balance_before'=>$balanceformatsellbefore,'user_balance_after'=>$balance_after,'agent_balance_before'=>$balanceagent_before,'agent_balance_after'=>$balanceagent_after,'tracking_code'=>$randomString,'product_price'=>$datafactor['price_product'],'purchase_time'=>$timejalali
    ]);
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ], $APIKEY);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "AddBalance") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $text_bot_var['text_account']['add_balance'], $bakinfos);
    step("get_price", $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backuser, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (?,?,?,?,?,?,?,?)");
    $payment_Status = "Unpaid";
    $Payment_Method = "cart to cart";
    $invoice = "0 | 0";
    $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice, $ApiToken);
    $stmt->execute();

    // ── Red Fox: انتخاب رندوم کارت از reseller_cards ──
    $rxCardMsg = '';
    $rxResellerId = (string)($dataBase['id_user'] ?? '');
    $rxCardRow = null;
    try {
        $rxCs = $pdo->prepare("SELECT * FROM reseller_cards WHERE reseller_id = ? ORDER BY RAND() LIMIT 1");
        $rxCs->execute([$rxResellerId]);
        $rxCardRow = $rxCs->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if ($rxCardRow) {
        $fmtPrice = number_format((int)$text);
        $rxCardMsg = "💳 <b>پرداخت کارت‌به‌کارت</b>\n\n";
        $rxCardMsg .= "💸 مبلغ: <b>{$fmtPrice} تومان</b>\n\n";
        $rxCardMsg .= "💳 کارت مقصد:\n";
        $rxCardMsg .= "<code>" . htmlspecialchars((string)$rxCardRow['cardnumber'], ENT_QUOTES, 'UTF-8') . "</code>\n";
        $rxCardMsg .= "👤 به نام: <b>" . htmlspecialchars((string)$rxCardRow['namecard'], ENT_QUOTES, 'UTF-8') . "</b>\n\n";
        $rxCardMsg .= "✅ پس از واریز، روی دکمه «پرداخت کردم» بزنید و تصویر رسید را ارسال کنید.";
        $rxCardKb = json_encode(['inline_keyboard' => [[
            ['text' => '✅ پرداخت کردم — ارسال رسید', 'callback_data' => "sendresidcart-" . $randomString],
        ]]]);
    } else {
        // fallback: استفاده از cart_info
        $rxCardMsg = $setting['cart_info'];
        $rxCardKb = $backuser;
    }
    sendmessage($from_id, $rxCardMsg, $rxCardKb, 'HTML');
    step("getresidcart", $from_id);
    savedata("clear", "id_order", $randomString);
} elseif ($user['step'] == "getresidcart") {
    if (empty($photo)) {
        sendmessage($from_id, "❌ لطفاً فقط تصویر رسید را ارسال کنید.", null, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    $PaymentReport = select("Payment_report", '*', "id_order", $userdate['id_order'], "select");
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$userdate['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$userdate['id_order']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price']);
    $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .
افزایش موجودی
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    foreach ($admin_ids as $id_admin) {
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => "🖼 تصویر رسید ارسالی",
                'parse_mode' => "HTML",
            ]);
        }
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
        step('home', $id_admin);
    }
    step('home', $from_id);
    sendmessage($from_id, "💎 رسید شما ارسال و پس از بررسی حساب کاربری شما شارژ خواهد شد.", $keyboard, 'HTML');
} elseif (preg_match('/product_(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user AND bottype = :bottype";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $nameloc['id_invoice'];
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $marzban = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban['name_panel'] != null) {
        update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    update("invoice", "user_info", json_encode($DataUserOut), "id_invoice", $nameloc['id_invoice']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['panelNotConnected'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = 'آنلاین';
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = 'آفلاین';
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateTime = new DateTime($DataUserOut['online_at'], new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            $lastonline = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
        } else {
            $lastonline = "متصل نشده";
        }
    }

    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
    ][$status];

    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    if ($timeDiff < 0) {
        $day = 0;
    } else {
        $day = "";
        $timemonth = floor($timeDiff / 2592000);
        if ($timemonth > 0) {
            $day .= $timemonth . $textbotlang['users']['stateus']['month'];
            $timeDiffday = $timeDiff - (2592000 * $timemonth);
        } else {
            $timeDiffday = $timeDiff;
        }
        $timereminday = floor($timeDiffday / 86400);
        if ($timereminday > 0) {
            $day .= $timereminday . $textbotlang['users']['stateus']['day'];
        }
        $timehoures = intval(($timeDiffday - ($timereminday * 86400)) / 3600);
        if ($timehoures > 0) {
            $day .= $timehoures . $textbotlang['users']['stateus']['hour'];
        }
        $timehoursall = $timeDiffday - ($timereminday * 86400);
        $timehoursall = $timehoursall - ($timehoures * 3600);
        $timeminuts = intval($timehoursall / 60);
        if ($timeminuts > 0) {
            $day .= $timeminuts . $textbotlang['users']['stateus']['min'];
        }
        $day .= " دیگر";
    }

    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }

    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $keyboardsetting = ['inline_keyboard' => []];
    $keyboarddateservies = array(
        'extend' => array(
            'text' => $textbotlang['users']['extend']['title'],
            'callback_data' => "extend_"
        ),
        'changelink' => array(
            'text' => $textbotlang['users']['changelink']['btntitle'],
            'callback_data' => "changelink_"
        ),
    );
    if ($marzban['status_extend'] == "off_extend") {
        unset($keyboarddateservies['extend']);
    }
    if (count($keyboarddateservies) != 0) {
        $tempArrayservices = [];
        foreach ($keyboarddateservies as $keyboardtextservice) {
            $tempArrayservices[] = ['text' => $keyboardtextservice['text'], 'callback_data' => $keyboardtextservice['callback_data'] . $username];
            if (count($tempArrayservices) == 2) {
                $keyboardsetting['inline_keyboard'][] = $tempArrayservices;
                $tempArrayservices = [];
            }
        }
        if (count($tempArrayservices) > 0) {
            $keyboardsetting['inline_keyboard'][] = $tempArrayservices;
        }
    }
    $keyboardsetting['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']];
    if ($marzban['type'] == "Manualsale") {
        $userinfo = select("manualsell", "*", "username", $nameloc['username'], "select");
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
    نام کاربری سرویس : {$DataUserOut['username']}
    📎 کد پیگیری سرویس : {$nameloc['id_invoice']}

    📌 اطلاعات سرویس :
    {$userinfo['contentrecord']}";
        Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
        return;
    }
    $output = "";
    $configLinks = [];
    if ($marzban['sublink'] == "onsublink") {
        $output = $DataUserOut['subscription_url'];
    }
    if ($marzban['config'] == "onconfig" && isset($DataUserOut['links']) && is_array($DataUserOut['links'])) {
        foreach ($DataUserOut['links'] as $link) {
            $trimmedLink = trim($link);
            if ($trimmedLink !== '') {
                $configLinks[] = $trimmedLink;
            }
        }
    }
    $config = implode("\n", $configLinks);
    $trimmedSubscription = trim($output);
    $trimmedConfigList = trim($config);
    $connectionSections = [];
    if ($trimmedSubscription !== '') {
        $connectionSections[] = "لینک اتصال:\n<code>{$trimmedSubscription}</code>";
    }
    if ($trimmedConfigList !== '') {
        $connectionSections[] = "لینک اشتراک :\n<code>{$trimmedConfigList}</code>";
    }
    $connectionLinksBlock = implode("\n\n", $connectionSections);

    $keyboardsetting = json_encode($keyboardsetting);
    if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
نام کاربری سرویس : {$DataUserOut['username']}
موقعیت سرویس :{$nameloc['Service_location']}
مدت زمان سرویس :{$nameloc['Service_time']} روز

📶 اخرین زمان اتصال شما : $lastonline

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)


{$connectionLinksBlock}
";
    } else {
        if ($DataUserOut['sub_updated_at'] !== null) {
            $textinfo = "وضعیت سرویس : $status_var
👤 نام سرویس : {$DataUserOut['username']}
🌍 موقعیت سرویس :{$nameloc['Service_location']}
🖇 کد سرویس:{$nameloc['id_invoice']}


🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)


📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>

{$connectionLinksBlock}
";
        } else {
            $textinfo = "وضعیت سرویس : $status_var
👤 نام سرویس : {$DataUserOut['username']}
🌍 موقعیت سرویس :{$nameloc['Service_location']}
🖇 کد سرویس:{$nameloc['id_invoice']}

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

📶 اخرین زمان اتصال شما : $lastonline


{$connectionLinksBlock}
";
        }
    }
    Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {

    $id_invoice = $dataget[1];
    savedata("clear", "id_invoice", $id_invoice);
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$id_invoice, $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage(
            $from_id,
            "❌ هنوز به سرویس متصل نشده اید برای تمدید، ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید",
            null,
            'html'
        );
        return;
    }

    savedata("save", "name_panel", $nameloc['Service_location']);
    deletemessage($from_id, $message_id);

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $query = "SELECT * FROM product
              WHERE (Location = '{$nameloc['Service_location']}' OR Location = '/all')
              AND (agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}' OR agent = 'all')";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $productnotexits = $stmt->rowCount();

    if ($productnotexits != 0 && $setting['show_product'] == false) {

        $customVolumeEnabled = isCustomVolumeEnabledForAgent($marzban_list_get, $userbot['agent'] ?? null);
        $statuscustom = $customVolumeEnabled && $marzban_list_get['type'] != "Manualsale";

        $query = "SELECT * FROM product
                  WHERE (Location = '{$marzban_list_get['name_panel']}' OR Location = '/all')
                  AND (agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}' OR agent = 'all')";
        $prodcut = KeyboardProduct(
            $marzban_list_get['name_panel'],
            $query,
            0,
            "selectproductextends_",
            $statuscustom,
            "backuser",
            null,
            $customvolume = "customvolumeextend"
        );
        sendmessage($from_id, "🛍️ لطفاً سرویسی که می‌خواهید تمدید کنید را انتخاب کنید!", $prodcut, 'HTML');

    } else {

        $custompricevalue = $setting['pricevolume'];
        $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
        $mainvolume = $mainvolume[$userbot['agent']];
        $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
        $maxvolume = $maxvolume[$userbot['agent']];

        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";

        sendmessage($from_id, $textcustom, $backuser, 'html');
        step('gettimecustomvolextend', $from_id);
    }

} elseif ($datain == "customvolumeextend") {

    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");

    $custompricevalue = $setting['pricevolume'];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$userbot['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$userbot['agent']];

    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";

    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvolextend', $from_id);

} elseif ($user['step'] == "gettimecustomvolextend") {

    savedata("save", "volume", $text);

    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$userdate['id_invoice'], $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");

    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$userbot['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$userbot['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$userbot['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$userbot['agent']];

    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }

    $customtimevalueprice = $setting['pricetime'];
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
    📌 تعرفه هر روز  : $customtimevalueprice  تومان
    ⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";

    sendmessage($from_id, $textcustom, $backuser, 'html');
    step("gettimecustomextend", $from_id);

} elseif ($user['step'] == "gettimecustomextend" || preg_match('/^selectproductextends_(.*)/', $datain, $dataget)) {
    if ($user['step'] == "gettimecustomextend") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
            return;
        }
    }
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$userdate['id_invoice'], $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($user['step'] == "gettimecustomextend") {
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$userbot['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$userbot['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $custompricevalue = $setting['pricevolume'];
        $customtimevalueprice = $setting['pricetime'];
        $datapish = array(
            "Volume_constraint" => $userdate['volume'],
            "name_product" => $textbotlang['users']['customsellvolume']['title'],
            "code_product" => "customvolume",
            "Service_time" => $text,
            "price_product" => ($userdate['volume'] * $custompricevalue) + ($text * $customtimevalueprice)
        );
        savedata("save", "time", $text);
    } else {
        $product = $dataget[1];
        savedata("save", "code_product", $product);
        $product = select("product", "*", "code_product", $product);
        if (!is_array($product) || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) { sendmessage($from_id, "❌ محصول برای این ربات مجاز نیست.", null, 'HTML'); if (function_exists('step')) step('home', $from_id); return; }
        $productlist = readJsonFileIfExists(__DIR__ . '/product.json');
        if (isset($productlist[$product['code_product']])) {
            $product['price_product'] = $productlist[$product['code_product']];
        }
        $datapish = array(
            "Volume_constraint" => $product['Volume_constraint'],
            "name_product" => $product['name_product'],
            "code_product" => $product['code_product'],
            "Service_time" => $product['Service_time'],
            "price_product" => $product['price_product']
        );
    }
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

💸 مبلغ تمدید :{$datapish['price_product']}
⏱ مدت زمان تمدید : {$datapish['Service_time']} روز
🔋 حجم تمدید :{$datapish['Volume_constraint']} گیگ
💸 موجودی کیف پول : {$user['Balance']}
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce-" . $nameloc['id_invoice']],
            ],
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ]
    ]);
    if ($user['step'] != "gettimecustomextend") {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend, 'HTML');
    } else {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    }
    step("home", $from_id);
} elseif (preg_match('/^confirmserivce-(.*)/', $datain, $dataget)) {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $id_invoice = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$id_invoice, $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }
    if (isset($userdate['code_product'])) {
        $product = $userdate['code_product'];
        $product = select("product", "*", "code_product", $product);
        if (!is_array($product) || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) { sendmessage($from_id, "❌ محصول برای این ربات مجاز نیست.", null, 'HTML'); if (function_exists('step')) step('home', $from_id); return; }
        $productlist = readJsonFileIfExists(__DIR__ . '/product.json');
        $priceproductmain = $product['price_product'];
        if (isset($productlist[$product['code_product']])) {
            $product['price_product'] = $productlist[$product['code_product']];
        }
        $datafactor = array(
            "Volume_constraint" => $product['Volume_constraint'],
            "name_product" => $product['name_product'],
            "code_product" => $product['code_product'],
            "Service_time" => $product['Service_time'],
            "price_product" => $product['price_product'],
            "price_productMain" => $priceproductmain,
        );
    } else {
        $custompricevalue = $setting['pricevolume'];
        $customtimevalueprice = $setting['pricetime'];
        $custompricevalueBot = $setting['minpricevolume'];
        $customtimevaluepriceBot = $setting['minpricetime'];
        $datafactor = array(
            "Volume_constraint" => $userdate['volume'],
            "name_product" => $textbotlang['users']['customsellvolume']['title'],
            "Service_time" => $userdate['time'],
            "code_product" => "custom_volume",
            "price_product" => ($userdate['volume'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice),
            "price_productMain" => ($userdate['volume'] * $custompricevalueBot) + ($userdate['time'] * $customtimevaluepriceBot),
            "data_limit_reset" => "no_reset"
        );
    }
    $productlist_name = readJsonFileIfExists(__DIR__ . '/product_name.json');
    $datafactor['name_product'] = empty($productlist_name[$datafactor['code_product']]) ? $datafactor['name_product'] : $productlist_name[$datafactor['code_product']];
    $botbalance = select("botsaz", "*", "bot_token", $ApiToken, "select");
    $userbotbalance = select("user", "*", "id", $botbalance['id_user'], "select");
    if ($datafactor['price_productMain'] >= $userbotbalance['Balance'] && $userbotbalance['agent'] != "n2") {
        $creditVars=['agent_id'=>$userbotbalance['id'],'agent_balance'=>number_format((int)$userbotbalance['Balance']),'required_amount'=>number_format((int)$datafactor['price_productMain'])];
        sendmessage($from_id,rx_message_template($pdo,'alert_reseller_no_credit_customer',$creditVars),$keyboard,'HTML');
        step("home", $from_id);
        foreach ($admin_ids as $admin) sendmessage($admin,rx_message_template($pdo,'alert_reseller_no_credit_admin',$creditVars),null,'HTML');
        return;
    }
    if ($datafactor['price_product'] > $user['Balance'] && intval($datafactor['price_product']) != 0) {
        $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        $Balance_prim = $datafactor['price_product'] - $user['Balance'];
        if ($Balance_prim <= 1)
            $Balance_prim = 0;
        $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$userbot['agent']]);
        $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$userbot['agent']]);
        $bakinfos = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "❌ موجودی شما برای خرید سرویس کافی نمی باشد.
💸  برای افزایش موجودی مبلغ را  به تومان وارد کنید:
✅  حداقل مبلغ $minbalance حداکثر مبلغ $maxbalance تومان می باشد", $bakinfos, 'HTML');
        step('get_price', $from_id);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $datafactor['Volume_constraint'], $datafactor['Service_time'], $nameloc['username'], $datafactor['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        $extend['msg'] = redfox_remote_error_summary($extend);
        $textreports = "
خطای تمدید سرویس در ربات نماینده
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس در ربات رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($settingmain['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $settingmain['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ], $APIKEY);
        }
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (?, ?, ?, ?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = $datafactor['Volume_constraint'] . "_" . $datafactor['Service_time'];
    $value = json_encode(array(
        "volumebuy" => $datafactor['Volume_constraint'],
        "Service_time" => $datafactor['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $datafactor['code_product'],
        'id_order' => $nameloc['id_invoice']
    ));
    $type = "extend_user";
    $stmt->bind_param("sssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $datafactor['price_product'], json_encode(['status' => true], JSON_UNESCAPED_SLASHES));
    $stmt->execute();
    $stmt->close();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    if (intval($datafactor['price_product']) != 0) {
        $Balance_prim = $user['Balance'] - $datafactor['price_product'];
        $userbalance = json_decode(file_get_contents(__DIR__ . "/data/$from_id/$from_id.json"), true);
        $userbalance['Balance'] = $Balance_prim;
        file_put_contents("data/$from_id/$from_id.json", json_encode($userbalance));
    }
    if (intval($userbotbalance['pricediscount']) != 0) {
        $resultper = ($datafactor['price_productMain'] * $userbotbalance['pricediscount']) / 100;
        $datafactor['price_productMain'] = $datafactor['price_productMain'] - $resultper;
    }
    $Balancebot = $userbotbalance['Balance'] - $datafactor['price_productMain'];
    update("user", "Balance", $Balancebot, "id", $userbotbalance['id']);
    $keyboardextendfnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $priceproductformat = number_format($datafactor['price_product']);
    $balanceformatsell = number_format($userbalance = json_decode(file_get_contents(__DIR__ . "/data/$from_id/$from_id.json"), true)['Balance']);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$datafactor['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
    sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = rx_message_template($pdo,'report_reseller_extend',[
        'user_id'=>$from_id,'agent_id'=>$userbot['id'],'reseller_bot'=>$dataBase['username'],'user_username'=>$username,'config_username'=>$nameloc['username'],'customer_name'=>$first_name,'panel_name'=>$nameloc['Service_location'],'product_name'=>$datafactor['name_product'],'volume_gb'=>$datafactor['Volume_constraint'],'service_days'=>$datafactor['Service_time'],'product_price'=>$datafactor['price_product'],'user_balance_before'=>$balanceformatsellbefore,'user_balance_after'=>$balanceformatsell,'agent_balance_before'=>number_format((int)$userbotbalance['Balance']),'agent_balance_after'=>number_format((int)$Balancebot),'purchase_time'=>$timejalali
    ]);
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ], $APIKEY);
    }
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$id_invoice, $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "disabled" || $DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.", null, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['changelink']['confirm'], 'callback_data' => "confirmchange_" . $nameloc['id_invoice']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['warnchange'], $keyboardextend);
} elseif (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxVpnbotOwnedInvoice($pdo, (string)$id_invoice, $from_id, (string)$ApiToken);
    if (!is_array($nameloc)) {
        sendmessage($from_id, '❌ این سرویس متعلق به حساب شما نیست یا در این ربات وجود ندارد.', null, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, '❌ خطایی در تغییر لینک رخ داده است.', null, 'HTML');
        return;
    }
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $DataUserOut['subscription_url'];
    }
    if ($marzban_list_get['config'] == "onconfig") {
        if (!isset($DataUserOut['configs']))
            return;
        if (isset($DataUserOut['configs']) and count($DataUserOut['configs']) != 0) {
            foreach ($DataUserOut['configs'] as $configs) {
                $config .= "\n" . $configs;
            }
        } else {
            $config .= "";
        }
        $output_config_link = $config;
    }
    $textconfig = "✅ کانفیگ شما با موفقیت بروزرسانی گردید.
اشتراک شما :
<code>$output_config_link</code>";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
}
require_once 'admin.php';

