<?php
/** Generated from manifest.php at release build time. Do not edit directly. */

/* ---- bootstrap.php ---- */
$version = file_get_contents('version');
date_default_timezone_set('Asia/Tehran');
$new_marzban = isset($new_marzban) ? $new_marzban : false;
ini_set('default_charset', 'UTF-8');

ini_set('memory_limit', '-1');
require_once 'config.php';
require_once __DIR__ . '/../../../lib/BotContentMenu.php';
require_once 'botapi.php';
require_once 'jdf.php';
require_once 'function.php';
require_once 'keyboard.php';
require_once 'vendor/autoload.php';
require_once 'panels.php';
require_once 'infocard.php';

if (!function_exists('rxOwnedInvoice')) {
    /** Load an invoice only when it belongs to the current Telegram user. */
    function rxOwnedInvoice(PDO $pdo, mixed $invoiceId, mixed $userId): array
    {
        $invoiceId = trim((string)$invoiceId);
        $userId = trim((string)$userId);
        if ($invoiceId === '' || $userId === '') {
            throw new RuntimeException('Invoice authorization failed.');
        }
        $stmt = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=:invoice AND id_user=:user LIMIT 1');
        $stmt->execute([':invoice' => $invoiceId, ':user' => $userId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Attempt to access a non-owned invoice', [
                    'from_id' => $userId,
                    'invoice' => substr($invoiceId, 0, 128),
                ]);
            }
            throw new RuntimeException('Invoice authorization failed.');
        }
        return $invoice;
    }
}

$textbotlang = languagechange('text.json');
if ($is_bot)
    return;
if (isset($update['chat_member'])) {
    $status = $update['chat_member']['new_chat_member']['status'];
    $from_id = $update['chat_member']['new_chat_member']['user']['id'];
    $user = select("user", "id", $from_id);
    $keyboard_channel_left = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📌 عضویت مجدد", 'url' => "https://t.me/{$update['chat_member']['chat']['username']}"],
            ],
        ]
    ]);
    if (in_array($status, ['left', 'kicked', 'restricted'])) {
        sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
        return;
    }
}
if (!in_array($Chat_type, ["private", "supergroup"]))
    return;
if (isset($chat_member))
    return;
$first_name = sanitizeUserName($first_name);
$setting = select("setting", "*");
if (!is_array($setting)) {
    $rxSettingMissingMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_setting_missing.flag';
    if (!is_file($rxSettingMissingMarker) || (time() - (int) @filemtime($rxSettingMissingMarker)) > 3600) {
        error_log('Settings data is unavailable. Ensure the `setting` table exists and contains records.');
        @touch($rxSettingMissingMarker);
    }
    unset($rxSettingMissingMarker);
    return;
}
$ManagePanel = new ManagePanel();
$keyboard_check = json_decode($setting['keyboardmain'], true);
if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    update("setting", "keyboardmain", $keyboardmain, null, null);
}

if (!checktelegramip())
    die("Unauthorized access");

if (intval($from_id) == 0)
    return;

$user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
$isNewUser = !is_array($user);
$otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
$tronadoOldDomain = 'tronseller.storeddownloader.fun';
$tronadoRecommendedUrl = (defined('TRONADO_ORDER_TOKEN_ENDPOINTS') && isset(TRONADO_ORDER_TOKEN_ENDPOINTS[0]))
    ? TRONADO_ORDER_TOKEN_ENDPOINTS[0]
    : 'https://bot.tronado.cloud/api/v1/Order/GetOrderToken';
$tronadoWarningFlag = REFACTORED_LEGACY_ROOT . '/urlpaymenttron_warning.flag';
if (!file_exists($tronadoWarningFlag)) {
    $storedUrl = getPaySettingValue('urlpaymenttron');
    if (is_string($storedUrl) && stripos($storedUrl, $tronadoOldDomain) !== false) {
        $warningText = "⚠️ دامنه قدیمی ترنادو هنوز در تنظیمات استفاده می‌شود. لطفاً آدرس جدید را جایگزین کنید:\n{$tronadoRecommendedUrl}";
        if (!empty($setting['Channel_Report'])) {
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text' => $warningText,
                'parse_mode' => 'HTML'
            ];
            if (!empty($otherreport)) {
                $payload['message_thread_id'] = $otherreport;
            }
            telegram('sendmessage', $payload);
        } else {
            error_log($warningText);
        }
        file_put_contents($tronadoWarningFlag, (string) time());
    }
}
if ($isNewUser && $setting['statusnewuser'] == "onnewuser") {
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $newuser = sprintf($textbotlang['Admin']['ManageUser']['newuser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $newuser,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
$date = time();
if ($from_id != 0 && $isNewUser) {
    if ($setting['verifystart'] != "onverify") {
        $valueverify = 1;
    } else {
        $valueverify = 0;
    }
    $randomString = bin2hex(random_bytes(6));
    $initialProcessingValue = '0';
    $initialProcessingValueOne = 'none';
    $initialProcessingValueTow = 'none';
    $initialProcessingValueFour = '0';
    $initialRollStatus = '0';
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four,tg_name) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four,:tg_name)");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':showcard', $setting['showcard']);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->bindParam(':roll_status', $initialRollStatus);
    $stmt->bindParam(':processing_value', $initialProcessingValue);
    $stmt->bindParam(':processing_value_one', $initialProcessingValueOne);
    $stmt->bindParam(':processing_value_tow', $initialProcessingValueTow);
    $stmt->bindParam(':processing_value_four', $initialProcessingValueFour);
    // Red Fox: نام تلگرامی کاربر جدید
    $stmt->bindValue(':tg_name', (string)$first_name);
    $stmt->execute();
    clearSelectCache('user');
    $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    $isNewUser = !is_array($user);
}
// Red Fox: برای کاربران قدیمی، نام تلگرامی را (در اولین پیام پس از آپدیت) ذخیره کن
if (is_array($user) && !empty($first_name) && (empty($user['tg_name']) || $user['tg_name'] === 'none')) {
    try {
        $rxTgNameStmt = $pdo->prepare("UPDATE user SET tg_name = :name WHERE id = :id AND (tg_name IS NULL OR tg_name = '')");
        $rxTgNameStmt->execute([':name' => $first_name, ':id' => $from_id]);
        $user['tg_name'] = $first_name;
    } catch (Throwable $rxTgNameErr) {
        error_log('[redfox] tg_name update failed: ' . redfox_exception_fingerprint($rxTgNameErr));
    }
}
if (!is_array($user)) {
    $user = array();
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'agent' => '',
        'username' => '',
        'limit_usertest' => '',
        'message_count' => '',
        'affiliates' => '',
        'last_message_time' => '',
        'cardpayment' => '',
        'roll_Status' => '',
        'number_username' => '',
        'number' => '',
        'register' => '',
        'codeInvitation' => '',
        'pricediscount' => '',
        'joinchannel' => '',
        'score' => "",
        'limitchangeloc' => ''
    );
} else {
    $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
}
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN", ['cache' => false]);
if (!is_array($admin_ids)) {
    $admin_ids = [];
}
$admin_ids_str = array_map('strval', $admin_ids);

if (
    isset($from_id)
    && intval($from_id) !== 0
    && is_array($setting)
    && (string)($setting['antispam_status'] ?? '0') === '1'
    && !in_array((string)$from_id, $admin_ids_str, true)
    && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)
) {
    try {
        $rxAsGateStmt = $pdo->prepare("SELECT antispam_muted_until FROM user WHERE id = :id LIMIT 1");
        $rxAsGateStmt->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsGateStmt->execute();
        $rxAsGateRow = $rxAsGateStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($rxAsGateRow)) {
            $rxAsGateMutedUntil = (int)($rxAsGateRow['antispam_muted_until'] ?? 0);
        } else {
            $rxAsGateMutedUntil = 0;
        }
    } catch (\Throwable $rxAsGateErr) {

        $rxAsGateMutedUntil = (int)($user['antispam_muted_until'] ?? 0);
    }
    if ($rxAsGateMutedUntil > 0 && time() < $rxAsGateMutedUntil) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (\Throwable $rxAsAckErr) {  }
        }
        return;
    }
}
$helpdata = select("help", "*");
$datatextbotget = select("textbot", "*", null, null, "fetchAll");
$id_invoice = select("invoice", "id_invoice", null, null, "FETCH_COLUMN");
$usernameinvoice = select("invoice", "username", null, null, "FETCH_COLUMN");
$code_Discount = select("Discount", "code", null, null, "FETCH_COLUMN");
$marzban_list = select("marzban_panel", "name_panel", null, null, "FETCH_COLUMN");
$name_product = select("product", "name_product", null, null, "FETCH_COLUMN");
$SellDiscount = select("DiscountSell", "codeDiscount", null, null, "FETCH_COLUMN");
$channels_id = select("channels", "link", null, null, "FETCH_COLUMN");
$pricepayment = select("Payment_report", "price", null, null, "FETCH_COLUMN");
$listcard = select("card_number", "cardnumber", null, null, "FETCH_COLUMN");
$datatxtbot = array();
$topic_id = select("topicid", "*", null, null, "fetchAll");
$statusnote = false;
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'reportcron')
        $reportcron = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];

}
if ($setting['statusnamecustom'] == 'onnamecustom')
    $statusnote = true;
if ($setting['statusnoteforf'] == "0" && $user['agent'] == "f")
    $statusnote = false;
if (!function_exists('createForumTopicIfMissing')) {
    function createForumTopicIfMissing($currentId, $reportKey, $topicName, $channelId)
    {
        $numericId = intval($currentId);
        if ($numericId !== 0) {
            return;
        }

        $channelId = trim((string)$channelId);
        if ($channelId === '' || $channelId === '0') {
            return;
        }

        $response = telegram('createForumTopic', [
            'chat_id' => $channelId,
            'name' => $topicName
        ]);

        if (!is_array($response) || empty($response['ok'])) {
            $context = redfox_remote_error_summary($response);
            error_log("Failed to create forum topic {$reportKey}: {$context}");

            if (is_array($response) && isset($response['error_code']) && in_array($response['error_code'], [400, 403], true)) {
                update("topicid", "idreport", -1, "report", $reportKey);
            }

            return;
        }

        $threadId = $response['result']['message_thread_id'] ?? null;
        if ($threadId !== null) {
            update("topicid", "idreport", $threadId, "report", $reportKey);
        }
    }
}

createForumTopicIfMissing($porsantreport, 'porsantreport', $textbotlang['Admin']['affiliates']['titletopic'], $setting['Channel_Report']);
createForumTopicIfMissing($reportnight, 'reportnight', $textbotlang['Admin']['report']['reportnight'], $setting['Channel_Report']);
createForumTopicIfMissing($reportcron, 'reportcron', $textbotlang['Admin']['report']['reportcron'], $setting['Channel_Report']);
createForumTopicIfMissing($reportbackup, 'backupfile', "🤖 بکاپ ربات نماینده", $setting['Channel_Report']);
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_roll' => '',
    'text_fq' => '',
    'text_dec_fq' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_channel' => '',
    'text_Tariff_list' => '',
    'text_dec_Tariff_list' => '',
    'text_affiliates' => '',
    'text_pishinvoice' => '',
    'accountwallet' => '',
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'crontest' => '',
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_cart' => '',
    'text_cart_auto' => '',
    'textafterpayibsng' => '',
    'text_request_agent_dec' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'aqayepardakht' => '',
    'zarinpey' => '',
    'zarinpal' => '',
    'textpaymentnotverify' => "",
    'text_star_telegram' => '',
    'text_extend' => '',
    'text_wgdashboard' => '',
    'text_Discount' => '',
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
$time_Start = jdate('Y/m/d');
$date_start = jdate('H:i:s', time());
$time_string = "📆 $date_start → ⏰ $time_Start";
$varable_start = [
    '{username}' => $username,
    '{first_name}' => $first_name,
    '{last_name}' => $last_name,
    '{time}' => $time_string,
    '{version}' => $version
];
$datatextbot['text_start'] = strtr($datatextbot['text_start'], $varable_start);
if (function_exists('rx_resolveInlineButtonText') && isset($datain) && is_string($datain) && strpos($datain, 'rxb_') === 0) {
    $rxResolvedInlineText = rx_resolveInlineButtonText($datain);
    if ($rxResolvedInlineText !== null && $rxResolvedInlineText !== '') {
        $datain = $rxResolvedInlineText;
        $text = $rxResolvedInlineText;
    }
}

if (
    function_exists('rx_restorePremiumReplyText')
    && isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] !== 'oninline'
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = rx_restorePremiumReplyText($text);
}

if (
    function_exists('stripReplyStyleEmoji')
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = stripReplyStyleEmoji($text);
}

if (
    is_string($datain) && $datain !== ''
    && function_exists('rx_resolveAdminPanelCallback')
    && (strpos($datain, 'apn:') === 0 || strpos($datain, 'apnh:') === 0)
) {
    $rxResolvedText = rx_resolveAdminPanelCallback($datain);
    if ($rxResolvedText !== null && $rxResolvedText !== '') {

        if (!empty($callback_query_id) && function_exists('telegram')) {
            @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        }
        $text  = $rxResolvedText;
        $datain = '';
    }
}

if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
if ($user['register'] == "none") {
    update("user", "register", time(), "id", $from_id);
}
if (!in_array($user['agent'], ["n", "n2", "f"]))
    update("user", "agent", "f", "id", $from_id);

if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
    $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}

$timebot = time();
$rxAntispamStatus = (string)($setting['antispam_status'] ?? '0');
if ($rxAntispamStatus === '1' && !in_array((string)$from_id, $admin_ids_str, true) && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)) {

    $rxAsMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCount < 1)    { $rxAsMsgCount = 1; }
    if ($rxAsMsgCount > 1000) { $rxAsMsgCount = 1000; }
    $rxAsSeconds = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSeconds < 1)    { $rxAsSeconds = 1; }
    if ($rxAsSeconds > 3600) { $rxAsSeconds = 3600; }
    $rxAsMuteSeconds = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSeconds < 1)     { $rxAsMuteSeconds = 1; }
    if ($rxAsMuteSeconds > 86400) { $rxAsMuteSeconds = 86400; }

    $rxAsShouldDrop = false;
    $rxAsAtomicOk   = false;
    $rxAsTxOpened   = false;
    try {
        $pdo->beginTransaction();
        $rxAsTxOpened = true;

        $rxAsSel = $pdo->prepare(
            "SELECT antispam_window_start, antispam_window_count, antispam_muted_until "
            . "FROM user WHERE id = :id FOR UPDATE"
        );
        $rxAsSel->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsSel->execute();
        $rxAsRow = $rxAsSel->fetch(PDO::FETCH_ASSOC);

        if (!is_array($rxAsRow)) {

            $pdo->commit();
            $rxAsTxOpened = false;
            $rxAsAtomicOk = true;
        } else {
            $rxAsWinStart   = (int)($rxAsRow['antispam_window_start'] ?? 0);
            $rxAsWinCount   = (int)($rxAsRow['antispam_window_count'] ?? 0);
            $rxAsMutedUntil = (int)($rxAsRow['antispam_muted_until']  ?? 0);

            $rxAsNewWinStart   = $rxAsWinStart;
            $rxAsNewWinCount   = $rxAsWinCount;
            $rxAsNewMutedUntil = $rxAsMutedUntil;

            if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {

                $rxAsShouldDrop = true;
            } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {

                $rxAsNewMutedUntil = 0;
                $rxAsNewWinStart   = $timebot;
                $rxAsNewWinCount   = 1;
            } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {

                $rxAsNewWinStart = $timebot;
                $rxAsNewWinCount = 1;
            } else {

                $rxAsNewWinCount = $rxAsWinCount + 1;
                if ($rxAsNewWinCount > $rxAsMsgCount) {

                    $rxAsNewMutedUntil = $timebot + $rxAsMuteSeconds;
                    $rxAsShouldDrop    = true;
                }
            }

            $rxAsUpd = $pdo->prepare(
                "UPDATE user SET "
                . "antispam_window_start = :ws, "
                . "antispam_window_count = :wc, "
                . "antispam_muted_until  = :mu "
                . "WHERE id = :id"
            );
            $rxAsUpd->bindValue(':ws', (string)$rxAsNewWinStart,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':wc', (string)$rxAsNewWinCount,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':mu', (string)$rxAsNewMutedUntil, PDO::PARAM_STR);
            $rxAsUpd->bindValue(':id', (string)$from_id,           PDO::PARAM_STR);
            $rxAsUpd->execute();

            $pdo->commit();
            $rxAsTxOpened = false;

            $user['antispam_window_start'] = (string)$rxAsNewWinStart;
            $user['antispam_window_count'] = (string)$rxAsNewWinCount;
            $user['antispam_muted_until']  = (string)$rxAsNewMutedUntil;

            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('user', 'id', (string)$from_id);
            } elseif (function_exists('clearSelectCache')) {
                clearSelectCache('user');
            }

            $rxAsAtomicOk = true;
        }
    } catch (\Throwable $rxAsTxErr) {

        if ($rxAsTxOpened) {
            try { $pdo->rollBack(); } catch (\Throwable $rxAsRbErr) {  }
        }
        $rxAsAtomicOk = false;
    }

    if (!$rxAsAtomicOk) {
        $rxAsWinStart   = (int)($user['antispam_window_start']  ?? 0);
        $rxAsWinCount   = (int)($user['antispam_window_count']  ?? 0);
        $rxAsMutedUntil = (int)($user['antispam_muted_until']   ?? 0);

        if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {
            $rxAsShouldDrop = true;
        } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {
            update("user", "antispam_muted_until",  "0",                "id", $from_id);
            update("user", "antispam_window_start", (string)$timebot,   "id", $from_id);
            update("user", "antispam_window_count", "1",                "id", $from_id);
            $user['antispam_muted_until']  = "0";
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {
            update("user", "antispam_window_start", (string)$timebot, "id", $from_id);
            update("user", "antispam_window_count", "1",              "id", $from_id);
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } else {
            $rxAsNewCount = $rxAsWinCount + 1;
            update("user", "antispam_window_count", (string)$rxAsNewCount, "id", $from_id);
            $user['antispam_window_count'] = (string)$rxAsNewCount;
            if ($rxAsNewCount > $rxAsMsgCount) {
                $rxAsMuteEnd = $timebot + $rxAsMuteSeconds;
                update("user", "antispam_muted_until", (string)$rxAsMuteEnd, "id", $from_id);
                $user['antispam_muted_until'] = (string)$rxAsMuteEnd;
                $rxAsShouldDrop = true;
            }
        }
    }

    if ($rxAsShouldDrop) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (\Throwable $rxAsAckErr2) {  }
        }
        return;
    }
} else {

    $TimeLastMessage = $timebot - intval($user['last_message_time']);
    if (floor($TimeLastMessage / 60) >= 1) {
        update("user", "last_message_time", $timebot, "id", $from_id);
        update("user", "message_count", "1", "id", $from_id);
    } else {
        if (!in_array($from_id, $admin_ids)) {
            $addmessage = intval($user['message_count']) + 1;
            update("user", "message_count", $addmessage, "id", $from_id);
            $spamThreshold = 35;
            if ($addmessage >= $spamThreshold) {
                $User_Status = "block";
                $textblok = sprintf($textbotlang['users']['spam']['spamedreport'], $from_id);
                $Response = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                        ],
                    ]
                ]);
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $otherservice,
                        'text' => $textblok,
                        'parse_mode' => "HTML",
                        'reply_markup' => $Response
                    ]);
                }
                update("user", "User_Status", $User_Status, "id", $from_id);
                update("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
                sendmessage($from_id, $textbotlang['users']['spam']['spamedmessage'], null, 'html');
                return;
            }
        }
    }
}

if (strpos($text, "/start ") !== false && $user['step'] != "gettextSystemMessage") {
    $affiliatesid = explode(" ", $text)[1];
    if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
        isValidInvitationCode($setting, $from_id, $user['verify']);
        if ($setting['affiliatesstatus'] == "offaffiliates") {
            sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
            return;
        }
        if (is_numeric($affiliatesid) && userExists($affiliatesid)) {
            if ($affiliatesid == $from_id) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                return;
            }
            $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
            if (intval($user['affiliates']) != 0) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            }
            update("user", "affiliates", $affiliatesid, "id", $from_id);
            $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
            sendmessage($from_id, "<b>🎉 خوش آمدی!</b>",
"
شما با دعوت <b>@{$useraffiliates['username']}</b> وارد ربات شدی و به عنوان زیرمجموعه ثبت شدی ✅

برای دریافت هدیه عضویت:
🔘 به منوی <b>زیرمجموعه‌گیری</b> برو
🔘 دکمه <b>🎁 دریافت هدیه عضویت</b> را بزن

با این کار، هم خودت و هم معرفت هدیه می‌گیرید! 💰",
 $keyboard, 'html');
            sendmessage($affiliatesid, "<b>🎉 یک زیرمجموعه جدید!</b>",
"
کاربر <b>@$username</b> با لینک دعوت شما وارد ربات شد ✅

با خریدهای این کاربر، <b>سهم هدیه شما</b> به حسابت واریز می‌شه 🔥", $keyboard, 'html');
            $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
            update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
            $dateacc = date('Y/m/d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent)
                                   VALUES (:user_id, :get_gift, :time, :reagent)
                                   ON DUPLICATE KEY UPDATE reagent = VALUES(reagent), get_gift = VALUES(get_gift), time = VALUES(time)");
            $stmt->execute([
                ':user_id' => $from_id,
                ':get_gift' => 0,
                ':time' => $dateacc,
                ':reagent' => $affiliatesid,
            ]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('reagent_report');
            }
        } else {
            sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
            update("user", "Processing_value", "0", "id", $from_id);
            update("user", "Processing_value_one", "0", "id", $from_id);
            update("user", "Processing_value_tow", "0", "id", $from_id);
            update("user", "Processing_value_four", "0", "id", $from_id);
            step('home', $from_id);
        }
    } else {
        $text = $affiliatesid;
    }
}
if (intval($user['verify']) == 0 && !in_array($from_id, $admin_ids) && $setting['verifystart'] == "onverify" && !rx_auth_skip_user($user)) {
    $textverify = "⚠️ حساب شما احراز هویت نشده است پیام  شما  به ادمین ارسال شده
    در صورت پیگیری  سریع تر می توانید به آیدی زیر پیام دهید
    @{$setting['id_support']}";
    sendmessage($from_id, $textverify, null, 'html');
    return;
}
;

if ($setting['roll_Status'] == "rolleon" && $user['roll_Status'] == 0 && ($text != "✅ قوانین را می پذیرم" and $datain != "acceptrule") && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_roll'], $confrimrolls, 'html');
    return;
}
if ($text == "✅ قوانین را می پذیرم" or $datain == "acceptrule") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Rules'], $keyboard, 'html');
    $confrim = true;
    update("user", "roll_Status", $confrim, "id", $from_id);
}

if ($setting['Bot_Status'] == "botstatusoff" && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_bot_off'], null, 'html');
    return;
}

if ($user['joinchannel'] != "active") {
    if (count($channels_id) != 0) {
        $channels = channel($channels_id);
        if ($datain == "confirmchannel") {
            if (count($channels) == 0) {
                update("user", "joinchannel", "active", "id", $from_id);
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['channel']['confirmed'],
                    'show_alert' => false,
                    'cache_time' => 5,
                ]);
                return;
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select");
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            Editmessagetext($from_id, $message_id, $datatextbot['text_channel'], $keyboardchannel);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 5,
            ]);
            $partsaffiliates = explode("_", $user['Processing_value_four']);
            if ($partsaffiliates[0] == "affiliates") {
                $affiliatesid = $partsaffiliates[1];
                if (!userExists($affiliatesid)) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['affiliatesidyou'], null, 'html');
                    return;
                }
                if ($affiliatesid == $from_id) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                    return;
                }
                $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
                if ($marzbanDiscountaffiliates['Discount'] == "onDiscountaffiliates") {
                    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                    $Balance_add_user = $useraffiliates['Balance'] + $marzbanDiscountaffiliates['price_Discount'];
                    update("user", "Balance", $Balance_add_user, "id", $affiliatesid);
                    $addbalancediscount = number_format($marzbanDiscountaffiliates['price_Discount'], 0);
                    sendmessage($affiliatesid, "🎁 مبلغ $addbalancediscount به موجودی شما از طرف زیر مجموعه با شناسه کاربری $from_id اضافه گردید.", null, 'html');
                }
                sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
                $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
                update("user", "affiliates", $affiliatesid, "id", $from_id);
                update("user", "Processing_value_four", "none", "id", $from_id);
                update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
            }
            return;
        }
        if (count($channels) != 0 && !in_array($from_id, $admin_ids)) {
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select");
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            sendmessage($from_id, $datatextbot['text_channel'], $keyboardchannel, 'html');
            return;
        }
    }
}
if ($text == "/start" || $datain == "start" || $text == "start") {
    sendmessage($from_id, $datatextbot['text_start'], $keyboard, "html");
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    return;
} elseif ($text == "version") {
    sendmessage($from_id, $version, null, 'html');
} elseif ($text == $textbotlang['users']['backbtn'] || $datain == "backuser") {
    if ($datain == "backuser")
        deletemessage($from_id, $message_id);
    $message_id = sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    return;
} elseif ($user['step'] == 'get_number') {
    if (empty($user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['false'], $request_contact, 'html');
        return;
    }
    if ($contact_id != $from_id) {
        sendmessage($from_id, $textbotlang['users']['number']['Warning'], $request_contact, 'html');
        return;
    }
    if ($setting['iran_number'] == "onAuthenticationiran" && !preg_match("/989[0-9]{9}$/", $user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['erroriran'], $request_contact, 'html');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['number']['active'], json_encode(['inline_keyboard' => [], 'remove_keyboard' => true]), 'html');
    sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
    update("user", "number", $user_phone, "id", $from_id);
    if ($setting['verifystart'] == "onverify") {
        update("user", "verify", "1", "id", $from_id);
    }
    step('home', $from_id);
} elseif ($text == $datatextbot['text_Purchased_services'] || $datain == "backorder" || $text == "/services") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_null($invoices) && $setting['NotUser'] == "offnotuser") {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
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
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        ['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json, 'html');
    }


} elseif ($datain == 'next_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
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
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == 'previous_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
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
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT $previous_page, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $backuser = [
        [
            'text' => "🔙 بازگشت به منوی اصلی",
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == "notusernameme") {
    sendmessage($from_id, $textbotlang['users']['stateus']['SendUsername'], $backuser, 'html');
    step('getusernameinfo', $from_id);
} elseif ($user['step'] == "getusernameinfo") {
    if (empty($text))
        return;
    $usernameconfig = "";
    if (strlen($text) > 32) {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            sendmessage($from_id, "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = outputlunksub($text);
        if (!isset($date)) {
            sendmessage($from_id, "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = json_decode($date, true);
        if (!isset($date['username'])) {
            sendmessage($from_id, "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $usernameconfig = $date['username'];
    } else {
        if (!preg_match('/^\w{3,32}$/', $text)) {
            sendmessage($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
            return;
        }
        $usernameconfig = $text;
    }
    update("user", "Processing_value", $usernameconfig, "id", $from_id);
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'html');
    step('getdata', $from_id);
} elseif (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1]);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        if ($DataUserOut['msg'] == "User not found") {
            sendmessage($from_id, $textbotlang['users']['stateus']['notUsernameget'], $keyboard, 'html');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }

    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ][$status];

    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $keyboardinfo = [
        'inline_keyboard' => [
            [
                ['text' => $DataUserOut['username'], 'callback_data' => "username"],
                ['text' => $textbotlang['users']['stateus']['username'], 'callback_data' => 'username'],
            ],
            [
                ['text' => $status_var, 'callback_data' => 'status_var'],
                ['text' => $textbotlang['users']['stateus']['stateus'], 'callback_data' => 'status_var'],
            ],
            [
                ['text' => $expirationDate, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['stateus']['expirationDate'], 'callback_data' => 'expirationDate'],
            ],
            [],
            [
                ['text' => $day, 'callback_data' => 'روز'],
                ['text' => $textbotlang['users']['stateus']['daysleft'], 'callback_data' => 'day'],
            ],
            [
                ['text' => $LastTraffic, 'callback_data' => 'LastTraffic'],
                ['text' => $textbotlang['users']['stateus']['LastTraffic'], 'callback_data' => 'LastTraffic'],
            ],
            [
                ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['stateus']['usedTrafficGb'], 'callback_data' => 'expirationDate'],
            ],
            [
                ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume'],
                ['text' => $textbotlang['users']['stateus']['RemainingVolume'], 'callback_data' => 'RemainingVolume'],
            ]
        ]
    ];
    $marzbanstatusextra = select("shopSetting", "*", "Namevalue", "statusextra", "select")['value'];
    if ($marzbanstatusextra == "onextra") {
        $keyboardinfo['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]],
            ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volumes_' . $DataUserOut['username'] . '_' . $dataget[1]],
        ];
    } else {
        $keyboardinfo['inline_keyboard'][] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]]];
    }
    $keyboardinfo = json_encode($keyboardinfo);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['info'], $keyboardinfo);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
    step('home', $from_id);
} elseif (function_exists('nmMaybeHandleStockCallback') && nmMaybeHandleStockCallback($datain ?? '', $from_id, $message_id ?? null, $callback_query_id ?? null)) {
    return;
} elseif (preg_match('/^quickview_(\w+)/', $datain, $dataget) || preg_match('/^product_(\w+)/', $datain, $dataget) || preg_match('/updateproduct_(\w+)/', $datain, $dataget) || $user['step'] == "getuseragnetservice" || $datain == "productcheckdata") {

    if (is_string($datain) && strpos($datain, 'quickview_') === 0) {
        $id_invoice_qv = $dataget[1];
        $stmtQv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :i AND id_user = :u LIMIT 1");
        $stmtQv->execute([':i' => $id_invoice_qv, ':u' => $from_id]);
        $nameloc_qv = $stmtQv->fetch(PDO::FETCH_ASSOC);
        if (!is_array($nameloc_qv)) {
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ سرویس پیدا نشد', 'show_alert' => true]);
            }
            return;
        }
        if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc_qv)) {
            step('home', $from_id);
            return;
        }
        $panel_qv = select("marzban_panel", "*", "name_panel", $nameloc_qv['Service_location'], "select");
        $cardPath_qv = null;
        if (is_array($panel_qv) && function_exists('nm_renderInfoCardForInvoice')) {
            $cardPath_qv = nm_renderInfoCardForInvoice($panel_qv, $nameloc_qv['username'], $nameloc_qv['id_invoice'], $from_id);
        }
        if ($cardPath_qv !== null && is_file($cardPath_qv)) {
            $note_qv = isset($nameloc_qv['note']) && $nameloc_qv['note'] !== '' ? ' | ' . $nameloc_qv['note'] : '';
            $caption_qv = '✨ <b>' . htmlspecialchars((string)$nameloc_qv['username'], ENT_QUOTES, 'UTF-8') . '</b>'
                        . htmlspecialchars($note_qv, ENT_QUOTES, 'UTF-8');
            $kb_qv = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $nameloc_qv['id_invoice']],
                        ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $nameloc_qv['id_invoice']],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'] ?? '🔙 بازگشت', 'callback_data' => 'backorder'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo'   => new CURLFile($cardPath_qv),
                'caption' => $caption_qv,
                'parse_mode' => 'HTML',
                'reply_markup' => $kb_qv,
            ]);
            @unlink($cardPath_qv);
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            }
            step('home', $from_id);
            return;
        }

        $datain = "product_" . $id_invoice_qv;
        $dataget = [$datain, $id_invoice_qv];
    }

    if ($user['step'] == "getuseragnetservice") {
        $username = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Service_time', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Volume', $username, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $username, PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain == "productcheckdata") {
        $username = $user['Processing_value'];
        $sql = "SELECT * FROM invoice WHERE username = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain[0] == "u") {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "♻️ اطلاعات بروز شد",
            'show_alert' => false,
            'cache_time' => 5,
        ));
    } else {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    }
    if ($user['step'] == "getuseragnetservice" && $stmt->rowCount() > 1) {
        $countservice = $stmt->rowCount();
        $pages = 1;
        update("user", "pagenumber", $pages, "id", $from_id);
        $page = 1;
        $items_per_page = 20;
        $start_index = ($page - 1) * $items_per_page;
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        if ($setting['statusnamecustom'] == 'onnamecustom') {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = "";
                if ($row != null)
                    $data = " | {$row['note']}";
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . $row['username'] . $data . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        } else {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . $row['username'] . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        }
        $backuser = [
            [
                'text' => "🔙 بازگشت به منوی اصلی",
                'callback_data' => 'backuser'
            ]
        ];
        if ($setting['NotUser'] == "onnotuser") {
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
        }
        $keyboardlists['inline_keyboard'][] = $backuser;
        $keyboard_json = json_encode($keyboardlists);
        sendmessage($from_id, "🛍 $countservice عدد سرویس یافت برای مشاهده و مدیریت سرویس روی یکی از سرویس ها کلیک کنید", $keyboard_json, 'html');
        step("home", $from_id);
        return;
    }
    $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($nameloc)) {
        sendmessage($from_id, "❌ سرویس مورد نظر پیدا نشد.", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) {
        step('home', $from_id);
        return;
    }
    $username = $nameloc['id_invoice'];
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $marzban = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($marzban) && isset($marzban['name_panel']) && $marzban['name_panel'] != null) {
        update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['UserNotFound'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['panelNotConnected'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    if (isset($nameloc) && is_array($nameloc) && function_exists('nmServicePanelAccessBlocked') && nmServicePanelAccessBlocked($nameloc)) {
        $nmBlockedKeyboard = function_exists('nmRestrictedServiceKeyboard')
            ? nmRestrictedServiceKeyboard($nameloc)
            : json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🛒 خرید سرویس اینترنت ملی', 'callback_data' => 'nm_buy_service_' . ($nameloc['id_invoice'] ?? '')],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $nmBlockedText = function_exists('nmServiceRestrictedNotice') ? nmServiceRestrictedNotice() : 'این سرویس در حال حاضر به دلیل شرایط اینترنت ملی در دسترس نیست !';
        if (isset($message_id) && (!isset($user['step']) || $user['step'] !== 'getuseragnetservice')) {
            Editmessagetext($from_id, $message_id, $nmBlockedText, $nmBlockedKeyboard);
        } else {
            sendmessage($from_id, $nmBlockedText, $nmBlockedKeyboard, 'HTML');
        }
        step('home', $from_id);
        return;
    }
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

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
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
            ]
        ]
    ]);
    if ($marzban['type'] == "ibsng" || $marzban['type'] == "mikrotik") {
        $userpassword = "🔑 رمز عبور سرویس شما : <code>{$DataUserOut['subscription_url']}</code>";
    } else {
        $userpassword = "";
    }
    if ($marzban['type'] == "Manualsale") {
        $userinfo = select("manualsell", "*", "username", $nameloc['username'], "select");
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
نام کاربری سرویس : {$DataUserOut['username']}
📎 کد پیگیری سرویس : {$nameloc['id_invoice']}

📌 اطلاعات سرویس :
{$userinfo['contentrecord']}";
        if ($user['step'] == "getuseragnetservice") {
            sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
        } elseif ($datain == "productcheckdata") {
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
        } else {
            Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
        }
        return;
    }
    $nameconfig = "";
    if ($nameloc['note'] != null) {
        $nameconfig = "✍️ یادداشت کانفیگ : {$nameloc['note']}";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
    $stmt->execute([
        ':username' => $nameloc['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "*", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $nameloc['name_product'] = $codeproduct['name_product'];
                $nameloc['Volume'] = $codeproduct['Volume_constraint'];
                $nameloc['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }

    $statustimeextra = select("shopSetting", "*", "Namevalue", "statustimeextra", "select")['value'];
    $marzbanstatusextra = select("shopSetting", "*", "Namevalue", "statusextra", "select")['value'];
    $statusdisorder = select("shopSetting", "*", "Namevalue", "statusdisorder", "select")['value'];
    $statuschangeservice = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select")['value'];
    $statusshowconfig = select("shopSetting", "*", "Namevalue", "configshow", "select")['value'];
    $statusremoveserveice = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select")['value'];
    if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
👤 نام کاربری سرویس : <code>{$DataUserOut['username']}</code>
🌍 موقعیت سرویس :{$nameloc['Service_location']}
نام محصول :{$nameloc['name_product']}

📶 اخرین زمان اتصال شما : $lastonline

🔋 ترافیک : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 تاریخ اتمام :  $expirationDate ($day)

$nameconfig";

        $keyboardsetting = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username],
                    ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username],
                ],
                [
                    ['text' => "❌ حذف سرویس", 'callback_data' => 'removeauto-' . $username],
                    ['text' => $textbotlang['users']['Extra_time']['title'], 'callback_data' => 'Extra_time_' . $username],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                ]
            ]
        ];
        if ($marzban['type'] == "ibsng" || $marzban['type'] == "mikrotik") {
            unset($keyboardsetting['inline_keyboard'][1][1]);
            unset($keyboardsetting['inline_keyboard'][0]);
        }
        if ($statustimeextra == "offtimeextraa")
            unset($keyboardsetting['inline_keyboard'][1][1]);
        if ($marzbanstatusextra == "offextra")
            unset($keyboardsetting['inline_keyboard'][0][1]);
        $keyboardsetting['inline_keyboard'] = array_values($keyboardsetting['inline_keyboard']);
        $keyboardsetting = json_encode($keyboardsetting);
    } else {
        $marzbancount = select("marzban_panel", "*", "status", "active", "count");
        if ($DataUserOut['status'] == "active") {
            $namestatus = '❌ خاموش کردن اکانت';
        } else {
            $namestatus = '💡 روشن کردن اکانت';
        }
        $keyboarddate = array(
            'updateinfo' => array(
                'text' => "♻️ بروزرسانی اطلاعات",
                'callback_data' => "updateproduct_"
            ),
            'linksub' => array(
                'text' => $textbotlang['users']['stateus']['linksub'],
                'callback_data' => "subscriptionurl_"
            ),
            'config' => array(
                'text' => $textbotlang['users']['stateus']['config'],
                'callback_data' => "config_"
            ),
            'extend' => array(
                'text' => $textbotlang['users']['extend']['title'],
                'callback_data' => "extend_"
            ),
            'changelink' => array(
                'text' => $textbotlang['users']['changelink']['btntitle'],
                'callback_data' => "changelink_"
            ),
            'removeservice' => array(
                'text' => $textbotlang['users']['stateus']['removeservice'],
                'callback_data' => "removeserviceuser_"
            ),
            'changenameconfig' => array(
                'text' => '📝 تغییر یادداشت',
                'callback_data' => "changenote_"
            ),
            'Extra_volume' => array(
                'text' => $textbotlang['users']['Extra_volume']['sellextra'],
                'callback_data' => "Extra_volume_"
            ),
            'Extra_time' => array(
                'text' => $textbotlang['users']['Extra_time']['title'],
                'callback_data' => "Extra_time_"
            ),
            'changestatus' => array(
                'text' => $namestatus,
                'callback_data' => "changestatus_"
            ),
            'transfor' => array(
                'text' => $textbotlang['Admin']['transfor']['title'],
                'callback_data' => "transfer_"
            ),
            'change-location' => array(
                'text' => $textbotlang['Admin']['change-location']['title'],
                'callback_data' => "changeloc_"
            ),
            'ekhtelal' => array(
                'text' => "⚠️ ارسال گزارش اختلال",
                'callback_data' => "disorder-"
            )
        );
        if ($nameloc['name_product'] == "سرویس تست") {
            unset($keyboarddate['transfor']);
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['removeservice']);
        }
        if ($marzban['type'] == "ibsng" || $marzban['type'] == "mikrotik") {
            unset($keyboarddate['linksub']);
            unset($keyboarddate['config']);
            unset($keyboarddate['extend']);
            unset($keyboarddate['changestatus']);
            unset($keyboarddate['change-location']);
            unset($keyboarddate['changelink']);
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['Extra_time']);
        }
        if ($marzban['type'] == "eylanpanel") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['type'] == "WGDashboard") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changestatus']);
            unset($keyboarddate['change-location']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['status_extend'] == "off_extend") {
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['extend']);
        }
        if ($statusremoveserveice == "off")
            unset($keyboarddate['removeservice']);
        if ($statusshowconfig == "offconfig")
            unset($keyboarddate['config']);
        if ($marzban['type'] == "hiddify") {
            unset($keyboarddate['changelink']);
            unset($keyboarddate['changestatus']);
            unset($keyboarddate['config']);
        }
        if ($statusdisorder == "offdisorder")
            unset($keyboarddate['ekhtelal']);
        if ($nameloc['Service_time'] == "0")
            unset($keyboarddate['Extra_time']);
        if ($nameloc['Volume'] == "0") {
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['Extra_time']);
        }
        if ($statuschangeservice == "offstatus")
            unset($keyboarddate['changestatus']);
        if ($setting['statusnamecustom'] == 'offnamecustom')
            unset($keyboarddate['changenameconfig']);
        if ($marzbancount == 1)
            unset($keyboarddate['change-location']);
        if ($marzban['changeloc'] == "offchangeloc")
            unset($keyboarddate['change-location']);
        if ($statustimeextra == "offtimeextraa")
            unset($keyboarddate['Extra_time']);
        if ($marzbanstatusextra == "offextra")
            unset($keyboarddate['Extra_volume']);
        $_rx_svc_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbd_s = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbd_s) && !empty($_rx_all_kbd_s['service'])) {
                $_rx_svc_styles = $_rx_all_kbd_s['service'];
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_svc_styles = $_rx_svc_styles + rx_getKeyboardDefaultStyles('service');
        }
        $tempArray = [];
        $keyboardsetting = ['inline_keyboard' => []];
        foreach ($keyboarddate as $_rx_svc_key => $keyboardtext) {
            $_rx_svc_btn = ['text' => $keyboardtext['text'], 'callback_data' => $keyboardtext['callback_data'] . $username];
            if (!empty($_rx_svc_styles[$_rx_svc_key]) && $_rx_svc_styles[$_rx_svc_key] !== 'default') {
                $_rx_svc_btn['style'] = $_rx_svc_styles[$_rx_svc_key];
            }
            $tempArray[] = $_rx_svc_btn;
            if (count($tempArray) == 2 or $keyboardtext['text'] == "♻️ بروزرسانی اطلاعات") {
                $keyboardsetting['inline_keyboard'][] = $tempArray;
                $tempArray = [];
            }
        }
        if (count($tempArray) > 0) {
            $keyboardsetting['inline_keyboard'][] = $tempArray;
        }
        $_rx_back_btn = ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'];
        if (!empty($_rx_svc_styles['backorder']) && $_rx_svc_styles['backorder'] !== 'default') {
            $_rx_back_btn['style'] = $_rx_svc_styles['backorder'];
        }
        $keyboardsetting['inline_keyboard'][] = [$_rx_back_btn];
        $keyboardsetting = json_encode($keyboardsetting);
        if ($DataUserOut['sub_updated_at'] !== null) {
            $textconnect = "
📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
        } elseif ($marzban['type'] == "WGDashboard") {
            $textconnect = "";
        } else {
            $textconnect = "📶 اخرین زمان اتصال شما : $lastonline";
        }
        $textinfo = "📊وضعیت سرویس : $status_var
👤 نام سرویس : <code>{$DataUserOut['username']}</code>
$userpassword
$nameconfig
🌍 موقعیت سرویس :{$nameloc['Service_location']}
🗂 نام محصول :{$nameloc['name_product']}

🔋 ترافیک : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 تاریخ اتمام : $expirationDate ($day)

$textconnect

💡 برای قطع دسترسی دیگران کافیست روی گزینه \"تغییر لینک\" کلیک کنید.";
    }
    if ($user['step'] == "getuseragnetservice") {
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } elseif ($datain == "productcheckdata") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } else {
        Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^nm_buy_service_(\w+)/', $datain, $dataget)) {
    $oldInvoiceId = $dataget[1];
    $oldInvoice = rxOwnedInvoice($pdo, $oldInvoiceId, $from_id);
    if (!$oldInvoice || (string)($oldInvoice['id_user'] ?? '') !== (string)$from_id) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ سرویس مورد نظر پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $freshUser = select("user", "*", "id", $from_id, "select");
    if (!$freshUser) $freshUser = $user;
    $panelKeyboard = function_exists('nmNationalBuyPanelKeyboard') ? nmNationalBuyPanelKeyboard($oldInvoice, $freshUser) : false;
    if (!$panelKeyboard) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ موقعیت سرویس قابل انتخاب پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $msg = "📍 موقعیت سرویس اینترنت ملی را انتخاب کنید.";
    if (isset($message_id)) {
        Editmessagetext($from_id, $message_id, $msg, $panelKeyboard);
    } else {
        sendmessage($from_id, $msg, $panelKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^nm_buy_panel_(\w+)_(\w+)/', $datain, $dataget)) {
    $oldInvoiceId = $dataget[1];
    $selectedPanelId = $dataget[2];
    $oldInvoice = rxOwnedInvoice($pdo, $oldInvoiceId, $from_id);
    if (!$oldInvoice || (string)($oldInvoice['id_user'] ?? '') !== (string)$from_id) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ سرویس مورد نظر پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $selectedPanel = function_exists('nmPanelById') ? nmPanelById($selectedPanelId) : select("marzban_panel", "*", "id", $selectedPanelId, "select");
    if (!$selectedPanel) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ پنل انتخاب شده پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $freshUser = select("user", "*", "id", $from_id, "select");
    if (!$freshUser) $freshUser = $user;
    $result = function_exists('nmCreateNationalServiceFromInvoice')
        ? nmCreateNationalServiceFromInvoice($oldInvoice, $freshUser, $selectedPanel)
        : ['status' => false, 'message' => '❌ تابع خرید سرویس اینترنت ملی در دسترس نیست.'];
    $backKeyboard = json_encode(['inline_keyboard' => [
        [['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']],
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $msg = $result['message'] ?? '❌ عملیات خرید سرویس اینترنت ملی انجام نشد.';
    if (isset($message_id)) {
        Editmessagetext($from_id, $message_id, $msg, $backKeyboard);
    } else {
        sendmessage($from_id, $msg, $backKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^infocard_qr_(\w+)$/', $datain, $dataget)) {

    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);
    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler infocard_qr on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'infocard_qr',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || empty($DataUserOut['subscription_url'])) {
        sendmessage($from_id, "❌ امکان دریافت QR Code در حال حاضر وجود ندارد.", null, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));
    $urlimage = "$from_id$randomString.png";
    $qrCode = createqrcode($subscriptionurl);
    file_put_contents($urlimage, $qrCode->getString());
    addBackgroundImage($urlimage, $qrCode, 'images.jpg');
    telegram('sendphoto', [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => "<code>{$subscriptionurl}</code>",
        'parse_mode' => "HTML",
    ]);
    unlink($urlimage);
    if (isset($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ QR Code ارسال شد',
            'show_alert' => false,
            'cache_time' => 5,
        ]);
    }
} elseif (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/sub ") !== false)) {
    if (is_string($text) && $text !== '' && $text[0] == "/") {
        $id_invoice = explode(' ', $text)[1];
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);
    }
    if ($nameloc == false)
        return;
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;

    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) {
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '📦 دریافت اشتراک از انبار پشتیبان', 'callback_data' => 'configget_' . $nameloc['id_invoice'] . '_sub']],
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.

🚨 اگر پنل اضطراری روشن باشد، امکان تمدید مستقیم کانفیگ قبلی پنل اصلی محدود است؛ برای ادامه می‌توانید «تمدید سرویس» را از مسیر اضطراری/انبار انجام دهید یا سرویس جدید تهیه کنید.", $offlineKeyboard, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    if ($marzban_list_get['type'] == "WGDashboard") {
        $textsub = "qrcode اشتراک شما";
    } else {
        $textsub = "
{$textbotlang['users']['stateus']['linksub']}

<code>$subscriptionurl</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    update("user", "Processing_value", $nameloc['username'], "id", $from_id);
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));

    $infoCardDelivered = false;
    if (function_exists('getInfoCardStatus') && getInfoCardStatus() && function_exists('nm_renderInfoCardForInvoice')) {
        $cardPath = nm_renderInfoCardForInvoice($marzban_list_get, $nameloc['username'], $nameloc['id_invoice'], $from_id);
        if ($cardPath !== null) {
            $cardKeyboard = function_exists('nm_appendInfoCardQrButton')
                ? nm_appendInfoCardQrButton($bakinfos, $nameloc['id_invoice'])
                : $bakinfos;
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($cardPath),
                'reply_markup' => $cardKeyboard,
                'caption' => $textsub,
                'parse_mode' => "HTML",
            ]);
            @unlink($cardPath);
            $infoCardDelivered = true;
        }
    }
    if (!$infoCardDelivered) {
        $urlimage = "$from_id$randomString.png";
        $qrCode = createqrcode($subscriptionurl);
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'reply_markup' => $bakinfos,
            'caption' => $textsub,
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
    }
    if ($marzban_list_get['type'] == "WGDashboard") {
        $urlimage = "{$marzban_list_get['inboundid']}_{$nameloc['username']}.conf";
        file_put_contents($urlimage, $DataUserOut['subscription_url']);
        sendDocument($from_id, $urlimage, "⚙️ کانفیگ شما");
        unlink($urlimage);
    }
} elseif (preg_match('/removeauto-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeauto on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeauto',
            ]);
        }
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    update('invoice', 'status', 'removebyuser', 'id_invoice', $id_invoice);
    $tetremove = "ادمین عزیز یک کاربر سرویس خود را پس از پایان حجم یا زمان حدف کرده است
نام کاربری کانفیک : {$nameloc['username']}";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $tetremove,
            'parse_mode' => "HTML"
        ]);
    }
    sendmessage($from_id, "📌 سرویس با موفقیت حذف شد", null, 'html');
} elseif (preg_match('/config_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/link ") !== false)) {
    $textCommand = is_string($text) ? $text : '';
    if ($textCommand !== '' && $textCommand[0] === "/") {
        $parts = explode(' ', $textCommand, 2);
        $id_invoice = $parts[1] ?? null;
        if ($id_invoice === null) {
            sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler config on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'config',
                ]);
            }
            $nameloc = false;
        }
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) {
        step('home', $from_id);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '📦 دریافت اشتراک از انبار پشتیبان', 'callback_data' => 'configget_' . $nameloc['id_invoice'] . '_sub']],
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.

🚨 اگر پنل اضطراری روشن باشد، امکان تمدید مستقیم کانفیگ قبلی پنل اصلی محدود است؛ برای ادامه می‌توانید «تمدید سرویس» را از مسیر اضطراری/انبار انجام دهید یا سرویس جدید تهیه کنید.", $offlineKeyboard, 'html');
        return;
    }
    if (!is_array($DataUserOut['links'])) {
        sendmessage($from_id, "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", keyboard_config($DataUserOut['links'], $nameloc['id_invoice']));
} elseif (preg_match('/configget_(.*)_(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configget on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configget',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) {
        step('home', $from_id);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $product = nmStockProductForInvoice($nameloc);
        if (nmStockFallbackForInvoice($nameloc, $product, 'config_button')) {
            return;
        }
        sendmessage($from_id, "❌ پنل در دسترس نیست و موجودی جایگزین متناسب با حجم این سرویس در انبار شبکه‌ملی پیدا نشد.", null, 'html');
        return;
    }
    if (!isset($DataUserOut['links']) || !is_array($DataUserOut['links']) || count($DataUserOut['links']) === 0) {
        $product = nmStockProductForInvoice($nameloc);
        if (nmStockFallbackForInvoice($nameloc, $product, 'config_button')) {
            return;
        }
        sendmessage($from_id, "❌ خطا در خواندن کانفیگ از پنل و موجودی جایگزین هم برای این حجم موجود نیست.", null, 'html');
        return;
    }
    $config = "";
    if ($dataget[2] == "1520") {
        for ($i = 0; $i < count($DataUserOut['links']); ++$i) {
            $randomString = bin2hex(random_bytes(3));
            $urlimage = "$from_id$randomString.png";
            $qrCode = createqrcode($DataUserOut['links'][$i]);
            file_put_contents($urlimage, $qrCode->getString());
            addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'caption' => "<code>{$DataUserOut['links'][$i]}</code>",
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        }
        return;
    }
    $randomString = bin2hex(random_bytes(3));
    $urlimage = "$from_id$randomString.png";
    $qrCode = createqrcode($DataUserOut['links'][$dataget[2]]);
    file_put_contents($urlimage, $qrCode->getString());
    addBackgroundImage($urlimage, $qrCode, 'images.jpg');
    telegram('sendphoto', [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => "<code>{$DataUserOut['links'][$dataget[2]]}</code>",
        'parse_mode' => "HTML",
    ]);
    unlink($urlimage);
} elseif (preg_match('/changestatus_(\w+)/', $datain, $dataget)) {
    $statuschangeservice = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select")['value'];
    if ($statuschangeservice == "offstatus") {
        sendmessage($from_id, "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changestatus on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changestatus',
            ]);
        }
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    if ($nameloc['Status'] == "disablebyadmin") {
        sendmessage($from_id, "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }

    if (function_exists('nmStockForInvoice') && nmStockForInvoice($nameloc)) {
        sendmessage($from_id, "❌ این سرویس از انبار شبکه‌ملی تحویل شده است و امکان روشن/خاموش کردن آن از پنل اصلی وجود ندارد.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ هنوز به کانفیگ متصل نشده اید و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '📦 دریافت اشتراک از انبار پشتیبان', 'callback_data' => 'configget_' . $nameloc['id_invoice'] . '_sub']],
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.

🚨 اگر پنل اضطراری روشن باشد، امکان تمدید مستقیم کانفیگ قبلی پنل اصلی محدود است؛ برای ادامه می‌توانید «تمدید سرویس» را از مسیر اضطراری/انبار انجام دهید یا سرویس جدید تهیه کنید.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کردن کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کردن کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisable_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmaccountdisable on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmaccountdisable',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extend on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'extend',
            ]);
        }
        return;
    }
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }

    if (function_exists('nmStockForInvoice') && nmStockForInvoice($nameloc)) {
        if (function_exists('nmMaybeHandleStockCallback')) {
            nmMaybeHandleStockCallback('nmstockextend_' . $nameloc['id_invoice'], $from_id, $message_id ?? null, $callback_query_id ?? null);
            return;
        }
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" && !(function_exists('nmPanelEmergencyEnabled') && (nmPanelEmergencyEnabled($marzban_list_get) || nmPanelNationalEnabled($marzban_list_get)))) {
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '📦 دریافت اشتراک از انبار پشتیبان', 'callback_data' => 'configget_' . $nameloc['id_invoice'] . '_sub']],
            [['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.

🚨 حالت اضطراری/نت ملی فعال است؛ تمدید از مسیر پشتیبان انجام می‌شود و در صورت نیاز می‌توانید سرویس جدید تهیه کنید.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
    $stmt->execute([
        ':service_location' => $marzban_list_get['name_panel'],
        ':agent' => $user['agent'],
    ]);
    $product = $stmt->rowCount();
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    if ($product == 0) {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if ($setting['statuscategory'] == "offcategory") {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
        ]);
        $productextend = ['inline_keyboard' => []];
        $statusshowprice = select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value'];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hide_panel = json_decode($result['hide_panel'], true);
            if (is_array($hide_panel) && in_array($nameloc['Service_location'], $hide_panel)) {
                error_log("Product {$result['code_product']} is marked hidden for {$nameloc['Service_location']} but was kept visible for extend.");
            }
            if (intval($user['pricediscount']) != 0) {
                $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                $result['price_product'] = $result['price_product'] - $resultper;
            }
            if ($statusshowprice == "offshowprice") {
                $namekeyboard = $result['name_product'];
            } else {
                $result['price_product'] = number_format($result['price_product']);
                $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
            }
            $productextend['inline_keyboard'][] = [
                ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
            ];
        }
        $productextend['inline_keyboard'][] = [
            ['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]
        ];
        $productextend['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
        ];

        $json_list_product_lists = json_encode($productextend);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], $json_list_product_lists);
    } else {
        $monthkeyboard = keyboardTimeCategory($nameloc['Service_location'], $user['agent'], "productextendmonths_", "product_$id_invoice", false, true);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif ($user['step'] == "gettimecustomvolomforextend") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxOwnedInvoice($pdo, $userdate['id_invoice'], $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextend', $from_id);
} elseif (preg_match('/productextendmonths_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxOwnedInvoice($pdo, $userdate['id_invoice'], $from_id);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND Service_time = :monthe AND (agent = :agent OR agent = 'all')");
    $stmt->execute([
        ':service_location' => $nameloc['Service_location'],
        'monthe' => $monthenumber,
        ':agent' => $user['agent'],
    ]);
    $productextend = ['inline_keyboard' => []];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $statusshowprice = select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value'];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (intval($user['pricediscount']) != 0) {
            $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        if ($statusshowprice == "offshowprice") {
            $namekeyboard = $result['name_product'];
        } else {
            $result['price_product'] = number_format($result['price_product']);
            $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
        }
        $productextend['inline_keyboard'][] = [
            ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
        ];
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $productextend['inline_keyboard'][] = [
            ['text' => "📍 انتخاب سرویس فعلی", 'callback_data' => "serviceextendselect_pre"]
        ];
    }
    $productextend['inline_keyboard'][] = [
        ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
    ];

    $json_list_product_lists = json_encode($productextend);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], $json_list_product_lists);
} elseif (preg_match('/^serviceextendselect_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserforextend" || $datain == "exntedagei") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxOwnedInvoice($pdo, $userdate['id_invoice'], $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($user['step'] == "getvolumecustomuserforextend") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
    } elseif ($datain == "exntedagei") {
        $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
        $stmt->execute([
            ':username' => $nameloc['username'],
        ]);
        if ($stmt->rowCount() == 0) {
            $codeproduct = select("product", "*", "name_product", $nameloc['name_product']);
        } else {
            $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true)))) {
                sendmessage($from_id, "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
                return;
            }
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "code_product", "code_product", $service_other['code_product'], "select");
        }
        if ($codeproduct == false) {
            sendmessage($from_id, "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
            return;
        }
        $codeproduct = $codeproduct['code_product'];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    } else {
        $codeproduct = $dataget[1];
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($user['step'] == "getvolumecustomuserforextend") {
        $product['name_product'] = $nameloc['name_product'];
        $product['code_product'] = "customvolume";
        $product['note'] = "";
        $product['price_product'] = (intval($userdate['volume']) * $custompricevalue) + ($text * $customtimevalueprice);
        $product['Service_time'] = $text;
        $product['Volume_constraint'] = $userdate['volume'];
        step("home", $from_id);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND code_product = :code_product AND (agent = :agent OR agent = 'all')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent'],
        ]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product == false) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.", $keyboard, 'HTML');
        return;
    }
    savedata("save", "time", $product['Service_time']);
    savedata("save", "data_limit", $product['Volume_constraint']);
    savedata("save", "price_product", $product['price_product']);
    savedata("save", "code_product", $product['code_product']);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"],
                ['text' => $textbotlang['users']['extend']['discount'], 'callback_data' => "discountextend"],
            ],
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ]
    ]);
    if (intval($user['pricediscount']) != 0) {
        $result = ($product['price_product'] * $user['pricediscount']) / 100;
        $pricelastextend = number_format(round($product['price_product'] - $result, 0));
    } else {
        $pricelastextend = $product['price_product'];
    }
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$product['name_product']}
💸 مبلغ تمدید : $pricelastextend تومان
⏱ مدت زمان تمدید :{$product['Service_time']} روز
🔋 حجم تمدید :{$product['Volume_constraint']} گیگ
✍️ توضیحات : {$product['note']}
💸 موجودی کیف پول : {$user['Balance']}
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    if ($user['step'] == "getvolumecustomuserforextend") {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif ($datain == "discountextend") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountextend', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountextend") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = rxOwnedInvoice($pdo, $userdate['id_invoice'], $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        sendmessage($from_id, "❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.", $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell WHERE (code_product = :code_product OR code_product = 'all') AND (code_panel = :code_panel OR code_panel = '/all') AND codeDiscount = :codeDiscount AND (agent = :agent OR agent = 'allusers' OR agent = 'all') AND (type = 'all' OR type = 'extend') AND (status IS NULL OR status = '' OR status = 'active') AND (target_user IS NULL OR target_user = '' OR target_user = :uid)");
    $stmt->bindParam(':code_product', $userdate['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $marzban_list_get['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->bindParam(':uid', $from_id, PDO::PARAM_STR);
    $stmt->execute();
    $SellDiscountlimit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM Giftcodeconsumed WHERE id_user = :from_id AND code = :code");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $Checkcodesql = $stmt->rowCount();
    if (intval($SellDiscountlimit['time']) != 0 and time() >= intval($SellDiscountlimit['time'])) {
        sendmessage($from_id, "❌ زمان کد تخفیف به پایان رسیده است.", null, 'HTML');
        return;
    }
    if ($SellDiscountlimit == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidcodedis'], null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['limitDiscount']) > 0 && intval($SellDiscountlimit['usedDiscount']) >= intval($SellDiscountlimit['limitDiscount'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimit'], null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['useuser']) > 0 && intval($Checkcodesql) >= intval($SellDiscountlimit['useuser'])) {
        $textoncode = "⭕️ این کد تنها {$SellDiscountlimit['useuser']}  بار قابل استفاده است";
        sendmessage($from_id, $textoncode, $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($SellDiscountlimit['usefirst'] == "1") {
        $countinvoice = select("invoice", "*", "id_user", $from_id, "count");
        if ($countinvoice != 0) {
            sendmessage($from_id, $textbotlang['users']['Discount']['firstdiscount'], null, 'HTML');
            return;
        }
    }
    $__dvt = strtolower(trim((string)($SellDiscountlimit['value_type'] ?? '')));
    if (!in_array($__dvt, ['percent', 'amount', 'free'], true)) $__dvt = 'percent';
    $__dval = (float)$SellDiscountlimit['price'];
    $__dlabel = $__dvt === 'free' ? 'رایگان' : ($__dvt === 'amount' ? number_format($__dval) . ' تومان' : $SellDiscountlimit['price'] . ' درصد');
    sendmessage($from_id, "🤩 کد تخفیف شما درست بود و تخفیف {$__dlabel} روی فاکتور شما اعمال شد.", $keyboard, 'HTML');
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $info_product['code_product'] = "pre";
        $info_product['name_product'] = $nameloc['name_product'];
        $info_product['price_product'] = ($userdate['data_limit'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice);
        $info_product['Service_time'] = $userdate['time'];
        $info_product['Volume_constraint'] = $userdate['data_limit'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :Location or Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
        $stmt->bindParam(':code_product', $userdate['code_product'], PDO::PARAM_STR);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel'], PDO::PARAM_STR);
        $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$info_product['name_product']}
💸 مبلغ تمدید :{$info_product['price_product']}
⏱ مدت زمان تمدید :{$info_product['Service_time']} روز
🔋 حجم تمدید :{$info_product['Volume_constraint']} گیگ
✍️ توضیحات : {$info_product['note']}
💸 موجودی کیف پول : {$user['Balance']}

✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"],
            ]
        ]
    ]);
    sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    $parametrsendvalue = "dis_" . $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    step("home", $from_id);
} elseif ($datain == "confirmserivce" || $datain == "confirmserdiscount") {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $partsdic = explode("_", $user['Processing_value_four']);
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $randomString = bin2hex(random_bytes(2));
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $prodcut['code_product'] = "custom_volume";
        $prodcut['name_product'] = $nameloc['name_product'];
        $prodcut['price_product'] = ($userdata['data_limit'] * $custompricevalue) + ($userdata['time'] * $customtimevalueprice);
        $prodcut['Service_time'] = $userdata['time'];
        $prodcut['Volume_constraint'] = $userdata['data_limit'];
        $prodcut['inbounds'] = $marzban_list_get['inboundid'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND code_product = :code_product AND (agent = :agent OR agent = 'all')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $userdata['code_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $pricelastextend = $prodcut['price_product'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    if ($datain == "confirmserdiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($SellDiscountlimit != false) {
            $pricelastextend = $partsdic[2];
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($pricelastextend * $user['pricediscount']) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($user['Balance'] < $pricelastextend && $user['agent'] != "n2" && intval($pricelastextend) != 0) {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
            $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $Balance_prim = $pricelastextend - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?,?, ?, ?,?,?,?)");
            $dateacc = date('Y/m/d H:i:s');
            $value = json_encode(array(
                "volumebuy" => $prodcut['Volume_constraint'],
                "Service_time" => $prodcut['Service_time'],
                "oldvolume" => $DataUserOut['data_limit'],
                "oldtime" => $DataUserOut['expire'],
                'code_product' => $prodcut['code_product'],
                'id_order' => $randomString
            ));
            $type = "extend_user";
            $status = "unpaid";
            $extend = '';
            $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend, $status);
            $stmt->execute();
            $stmt->close();
            update("user", "Processing_value_one", "{$nameloc['username']}%$randomString", "id", $from_id);
            update("user", "Processing_value_tow", "getextenduser", "id", $from_id);
            return;
        }
    }
    if ($datain == "confirmserdiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($SellDiscountlimit != false) {
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (?,?)");
            $stmt->bind_param("ss", $from_id, $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @$username  و آیدی عددی $from_id از کد تخفیف {$partsdic[1]} استفاده کرد. و سرویس خود را تمدید کررد.";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $pricelastextend) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if ($nameloc['name_product'] == "سرویس تست") {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
    }
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        if (nmStockCompleteExtendFallback($from_id, $user, $nameloc, $prodcut, $pricelastextend, 'wallet_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = redfox_remote_error_summary($extend);
        $textreports = "خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if ($user['agent'] == "f") {
        $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
    } else {
        $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$user['agent']];
    }
    if (intval($valurcashbackextend) != 0 and intval($pricelastextend) != 0) {
        $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, "تبریک 🎉
📌 به عنوان هدیه تمدید مبلغ $result تومان حساب شما شارژ گردید", null, 'HTML');
    }
    $Balance_Low_user = $user['Balance'] - $pricelastextend;

    if (intval($pricelastextend) > 0) {
        if (($user['agent'] ?? '') === 'n2') {
            $stmtExtendDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
        } else {
            $stmtExtendDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid AND Balance >= :check_delta");
            $stmtExtendDeduct->bindValue(':check_delta', (int) $pricelastextend, PDO::PARAM_INT);
        }
        $stmtExtendDeduct->bindValue(':delta', (int) $pricelastextend, PDO::PARAM_INT);
        $stmtExtendDeduct->bindValue(':uid', $from_id, PDO::PARAM_STR);
        $stmtExtendDeduct->execute();
        if ($stmtExtendDeduct->rowCount() === 0 && function_exists('rx_log_event')) {
            rx_log_event('EXTEND_DOUBLE_SPEND_OR_INSUFFICIENT', 'Atomic extend-deduct affected 0 rows after panel extend already succeeded', [
                'from_id' => $from_id,
                'invoice' => $id_invoice ?? null,
                'price'   => $pricelastextend,
                'agent'   => $user['agent'] ?? null,
            ]);
        }
    } else {

    }
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?, ?, ?,?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
        'id_order' => $randomString
    ));
    $type = "extend_user";
    $status = "paid";
    $extend_json = json_encode(['status' => true], JSON_UNESCAPED_SLASHES);
    $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'],$extend_json, $status);
    $stmt->execute();
    $stmt->close();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
        $scorenew = $user['score'] + 2;
        update("user", "score", $scorenew, "id", $from_id);
    }
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
    $priceproductformat = number_format($pricelastextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
    sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :{$nameloc['username']}
▫️نام کاربر : $first_name
▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}
▫️نام محصول : {$prodcut['name_product']}
▫️حجم محصول : {$prodcut['Volume_constraint']}
▫️زمان محصول : {$prodcut['Service_time']}
▫️مبلغ تمدید : {$prodcut['price_product']} تومان
▫️موجودی قبل از خرید : $balanceformatsellbefore تومان
▫️موجودی بعد از خرید : $balanceformatsell تومان
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changelink on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changelink',
            ]);
        }
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
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchange on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchange',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, '❌ خطایی در تغییر لینک رخ داده است.', null, 'HTML');
        return;
    }
    $textconfig = "✅ کانفیگ شما با موفقیت بروزرسانی گردید.";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $DataUserOut['subscription_url'];
        $textconfig .= "اشتراک شما : <code>$output_config_link</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if ($marzban_list_get['config'] == "onconfig") {
        Editmessagetext($from_id, $message_id, $textconfig, keyboard_config($DataUserOut['configs'], $nameloc['id_invoice'], true));
    } else {
        Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات تغییر لینک در ربات شما ثبت شد .
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :{$nameloc['username']}
▫️نام کاربر : $first_name
▫️موقعیت سرویس : {$marzban_list_get['name_panel']}
▫️نوع کاربر : {$user['agent']}
▫️زمان تغییر لینک : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
        ]);
    }
} elseif (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_volume on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_volume',
            ]);
        }
        return;
    }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان خرید حجم اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = " ⭕️ مقدار حجمی که میخواهید خریداری کنید را ارسال کنید.
❌ مبلغ را به انگلیسی ارسال نمایید.
        ⚠️ هر گیگ  حجم اضافه $extrapricevalue تومان  است.";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('getvolumeextra', $from_id);
} elseif ($user['step'] == "getvolumeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) { step('home', $from_id); return; }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $extrapricevalue * $text],
            ],
            [
                ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discountvolume-' . $extrapricevalue * $text],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید حجم اضافه برای شما ایجاد شد.

📌 تعرفه هر گیگابایت حجم اضافه : $extrapricevalues تومان
🔋 حجم اضافه درخواستی : $text گیگابایت
💰 مبلغ فاکتور شما : $priceextra تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discountvolume-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "prevol_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountvolume', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountvolume") {
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseVol = (strpos($__pv4, 'prevol_') === 0) ? intval(substr($__pv4, 7)) : 0;
    if ($__baseVol <= 0) {
        sendmessage($from_id, "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $dv = nm_validateSellDiscount($text, 'volume', '', $marzban_list_get['code_panel'], $user, $from_id);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__discounted = (int)round(nm_applySellDiscountToPrice($dv['row'], $__baseVol));
    if ($__discounted < 0) $__discounted = 0;
    update("user", "Processing_value_four", "disv_" . $text . "_" . $__baseVol . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseVol, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextradiscount-' . $__baseVol],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextra(discount)?-(\w+)/', $datain, $dataget)) {
    $volume = $dataget[2];
    $__volDiscountCode = '';
    $__volCharge = (float)$volume;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'disv_') === 0) {
            $__pp = explode('_', $__pv4);
            $__volDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__volCharge = (float)$__pp[3];
        }
    }
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $__volCharge && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuevolume = intval($volume) / intval($extrapricevalue);
            if (intval($user['pricediscount']) != 0) {
                $result = ($__volCharge * $user['pricediscount']) / 100;
                $__volCharge = $__volCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $__volCharge - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
            step('get_step_payment', $from_id);
            update("user", "Processing_value_one", "{$nameloc['username']}%{$valuevolume}", "id", $from_id);
            update("user", "Processing_value_tow", "getextravolumeuser", "id", $from_id);
            if ($__volDiscountCode !== '') {
                nm_markSellDiscountUsed($__volDiscountCode, $from_id, $username, 'volume');
                update("user", "Processing_value_four", "", "id", $from_id);
            }
            return;
        }
    }
    deletemessage($from_id, $message_id);
    $volumepricelast = $__volCharge;
    if (intval($user['pricediscount']) != 0) {
        $result = ($__volCharge * $user['pricediscount']) / 100;
        $volumepricelast = $__volCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volumepricelast) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $__allowNegVx = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    if ($volumepricelast > 0) {
        $__chargeVx = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $volumepricelast, $__allowNegVx) : ['ok' => false];
        if (empty($__chargeVx['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeVx['new_balance'];
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    if ($__volDiscountCode !== '') {
        nm_markSellDiscountUsed($__volDiscountCode, $from_id, $username, 'volume');
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'volume_value' => intval($volume) / intval($extrapricevalue),
        'priceـper_gig' => $extrapricevalue,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $data_limit = intval($volume) / intval($extrapricevalue);
    $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $data_limit);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = redfox_remote_error_summary($extra_volume);
        $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_volume['msg']}";
        sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volumepricelast,
        ':output' => json_encode(['status' => true], JSON_UNESCAPED_SLASHES),
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($volumepricelast, 0);
    $volumes = $volume / $extrapricevalue;
    $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس  : {$nameloc['username']}
▫️حجم اضافه : $volumes گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
    sendmessage($from_id, $textvolume, $keyboardextrafnished, 'HTML');
    $text_report = "⭕️ یک کاربر حجم اضافه خریده است

اطلاعات کاربر :
🪪 آیدی عددی : $from_id
🛍 حجم خریداری شده  : $volumes گیگ
💰 مبلغ پرداختی : $volumesformat تومان
👤 نام کاربری کانفیگ : {$nameloc['username']}
موجودی کاربر قبل خرید : {$user['Balance']}
";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/changeloc_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    if ($user['limitchangeloc'] > $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changeloc on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changeloc',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['changeloc'] == "offchangeloc") {
        sendmessage($from_id, "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || $DataUserOut['status'] == "disabled") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_userschange);
} elseif (preg_match('/changelocselectlo-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_one", $dataget[1], "id", $from_id);
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $userlimitlast = $limitchangeloc['all'] - $user['limitchangeloc'];
    $userlimitlastfree = $limitchangeloc['free'] - $user['limitchangeloc'];
    if ($userlimitlastfree < 0)
        $userlimitlastfree = 0;
    $Pricechange = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['priceChangeloc'];
    $textchange = "📍 با  تایید کردن انتقال موقعیت سرویس شما در این موقعیت حذف و به موقعیت جدید منتقل خواهد شد.
💰 هزینه انتقال $Pricechange تومان می باشد
📌 محدودیت باقی مانده شما : $userlimitlast عدد (تعداد محدودیت رایگان باقی مانده :‌$userlimitlastfree عدد)

✅ برای تایید انتقال روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['change-location']['confirm'], 'callback_data' => "confirmchangeloccha_" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textchange, $keyboardextend);
} elseif (preg_match('/confirmchangeloccha_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchangeloccha on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchangeloccha',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $marzban_list_get_new = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $limitfree = true;
    if ($user['limitchangeloc'] < $limitchangeloc['free'] and intval($setting['statuslimitchangeloc']) == 1) {
        $limitfree = false;
    }
    if ($user['limitchangeloc'] >= $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    if ($marzban_list_get_new['changeloc'] == "offchangeloc") {
        sendmessage($from_id, "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    if ($marzban_list_get_new == false) {
        sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید", null, 'html');
        return;
    }
    $Pricechange = $marzban_list_get_new['priceChangeloc'];
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $prodcut['code_product'] = "🛍 حجم دلخواه";
        $product['inbounds'] = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND name_product = :name_product AND (agent = :agent OR agent = 'all')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            'name_product' => $nameloc['name_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product['inbounds'] != null) {
        $marzban_list_get_new['inboundid'] = $prodcut['inbounds'];
    }
    if ($marzban_list_get_new['type'] == "Manualsale" && $marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        sendmessage($from_id, "❌ امکان انتقال به پنل وجود ندارد.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] != "active") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($limitfree == false) {
        $Pricechange = 0;
    }
    if ($user['Balance'] < $Pricechange && $user['agent'] != "n2" && $limitfree) {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($Pricechange * $user['pricediscount']) / 100;
                $Pricechange = $Pricechange - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($Pricechange) != 0) {
                $Balance_prim = $Pricechange - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                step('get_step_payment', $from_id);
                return;
            }
        }
    }
    if (intval($user['pricediscount']) != 0 and intval($Pricechange) != 0) {
        $result = ($Pricechange * $user['pricediscount']) / 100;
        $Pricechange = $Pricechange - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $Pricechange) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $value = json_encode(array(
        "old_panel" => $marzban_list_get['code_panel'],
        "new_panel" => $marzban_list_get_new['code_panel'],
        "volume" => $DataUserOut['data_limit'],
        "used_traffic" => $DataUserOut['used_traffic'],
        "expire" => $DataUserOut['expire'],
        "stateus" => $DataUserOut['status']
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $type = "change_location";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product']);
    $stmt->execute();
    $stmt->close();
    if ($DataUserOut['data_limit'] == 0 || $DataUserOut['data_limit'] == null) {
        $data_limit = 0;
    } else {
        $data_limit = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    }
    $datac = array(
        'expire' => $DataUserOut['expire'],
        'data_limit' => $data_limit,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest'
    );
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";
    if ($marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
    } else {
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "خطا هنگام تغییر موقعیت سرویس
دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}
نام پنل مقصد : {$marzban_list_get_new['name_panel']}";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    }
    $output_config_link = "";
    if ($marzban_list_get_new['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get_new['config'] == "onconfig") {
        if (is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $configs) {
                $output_config_link .= "\n" . $configs;
            }
        }
    }
    $limitnew = $user['limitchangeloc'] + 1;
    update("user", "limitchangeloc", $limitnew, "id", $from_id);
    $textchangeloc = "✅ کانفیگ شما باموفقیت به سرور ({$marzban_list_get_new['name_panel']}) انتقال یافت.

🖥 نام سرویس : {$nameloc['username']}
💠 حجم سرویس : $RemainingVolume
⏳ زمان انقضا :  $expirationDate | $day

🔗 لینک اشتراک شما:

<code>$output_config_link</code>";
    if (intval($Pricechange) != 0) {
        $__allowNegPc = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargePc = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $Pricechange, $__allowNegPc) : ['ok' => false];
        if (empty($__chargePc['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargePc['new_balance'];
    }
    update("invoice", "Service_location", $marzban_list_get_new['name_panel'], "username", $nameloc['username']);
    if ($marzban_list_get_new['inboundid'] != null) {
        update("invoice", "inboundid", $marzban_list_get_new['inboundid'], "username", $nameloc['username']);
    }
    Editmessagetext($from_id, $message_id, $textchangeloc, $keyboardextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $format_byte = formatBytes($data_limit);
    $textreport = "
تغییر موقعیت سرویس

🔻آیدی عددی : <code>$from_id</code>
🔻نام کاربری : @$username
🔻نام پنل قدیم : {$marzban_list_get['name_panel']}
🔻نام پنل جدید : {$marzban_list_get_new['name_panel']}
🔻 نام کاربری مشتری در پنل  :{$nameloc['username']}
🔻حجم نهایی سرویس : $format_byte
🔻موجودی کاربر : $balanceformatsell تومان";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/disorder-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler disorder on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'disorder',
            ]);
        }
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ علت اختلال خود را بنویسید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textdisorder, $keyboarddisorder);
    step("getdesdisorder", $from_id);
} elseif ($user['step'] == "getdesdisorder") {
    update("user", "Processing_value", $text, "id", $from_id);
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ آیا از ارسال گزارش اختلال اطمینان دارید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و ارسال گزارش اختلال", 'callback_data' => "confirmdisorders-" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    sendmessage($from_id, $textdisorder, $keyboarddisorder, 'html');
    step("home", $from_id);
} elseif (preg_match('/confirmdisorders-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmdisorders on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmdisorders',
            ]);
        }
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    $textdisorder = "
    ⚠️ یک کاربر با اطلاعات زیر یک گزارش اختلال در سرویس ثبت کرده است .

- نام کاربری : @$username
- آیدی عددی : $from_id
- نام کاربری کانفیگ : {$nameloc['username']}
- نام پلن تهیه شده : {$nameloc['name_product']}
- موقعیت سرویس : {$nameloc['Service_location']}
- توضیحات اختلال : {$user['Processing_value']}";
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

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
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

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
    $textdisorder .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
    foreach ($admin_ids as $admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        sendmessage($admin, $textdisorder, $Response, 'html');
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "✅ با تشکر از ثبت درخواست ،درخواست شما  ارسال شده و درحال بررسی توسط پشتیبانی می باشد.", $bakinfos, 'html');
} elseif (preg_match('/Extra_time_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_time on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_time',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان خرید زمان اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = "📆 تعداد روز اضافه مورد نظر را وارد کنید ( برحسب روز ) :

📌 تعرفه هر روز:  $extratimepricevalue";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('gettimeextra', $from_id);
} elseif ($user['step'] == "gettimeextra") {
    if (!ctype_digit($text) || $text < 1) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextratime = $extratimepricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $extratimepricevalue * $text],
            ],
            [
                ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discounttime-' . $extratimepricevalue * $text],
            ]
        ]
    ]);
    $priceextratime = number_format($priceextratime, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید زمان اضافه برای شما ایجاد شد.

📌 تعرفه هر روز زمان اضافه : $extratimepricevalue تومان
📆 تعداد روز اضافه درخواستی : $text روز
💰 مبلغ فاکتور شما : $priceextratime تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discounttime-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "pretime_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscounttime', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscounttime") {
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseTime = (strpos($__pv4, 'pretime_') === 0) ? intval(substr($__pv4, 8)) : 0;
    if ($__baseTime <= 0) {
        sendmessage($from_id, "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $dv = nm_validateSellDiscount($text, 'time', '', $marzban_list_get['code_panel'], $user, $from_id);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__discounted = (int)round(nm_applySellDiscountToPrice($dv['row'], $__baseTime));
    if ($__discounted < 0) $__discounted = 0;
    update("user", "Processing_value_four", "distime_" . $text . "_" . $__baseTime . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseTime, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratimediscount-' . $__baseTime],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextratime(discount)?-(\w+)/', $datain, $dataget)) {
    $tmieextra = $dataget[2];
    $__timeDiscountCode = '';
    $__timeCharge = (float)$tmieextra;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'distime_') === 0) {
            $__pp = explode('_', $__pv4);
            $__timeDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__timeCharge = (float)$__pp[3];
        }
    }
    $pricelasttime = $__timeCharge;
    $nameloc = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $__timeCharge && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuetime = $tmieextra / $extratimepricevalue;
            if (intval($user['pricediscount']) != 0) {
                $result = ($__timeCharge * $user['pricediscount']) / 100;
                $pricelasttime = $__timeCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($pricelasttime) != 0) {
                $Balance_prim = $pricelasttime - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                step('get_step_payment', $from_id);
                update("user", "Processing_value_one", "{$nameloc['username']}%{$valuetime}", "id", $from_id);
                update("user", "Processing_value_tow", "getextratimeuser", "id", $from_id);
                if ($__timeDiscountCode !== '') {
                    nm_markSellDiscountUsed($__timeDiscountCode, $from_id, $username, 'time');
                    update("user", "Processing_value_four", "", "id", $from_id);
                }
                return;
            }
        }
    }
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0 and intval($pricelasttime) != 0) {
        $result = ($__timeCharge * $user['pricediscount']) / 100;
        $pricelasttime = $__timeCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $Balance_Low_user = $user['Balance'] - $pricelasttime;
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if ($Balance_Low_user < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }

    $__allowNegEt = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    if ($pricelasttime > 0) {
        $__chargeEt = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $pricelasttime, $__allowNegEt) : ['ok' => false];
        if (empty($__chargeEt['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEt['new_balance'];
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $__chargedEtUser = true;
    if ($__timeDiscountCode !== '') {
        nm_markSellDiscountUsed($__timeDiscountCode, $from_id, $username, 'time');
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
    $extratimeday = $tmieextra / $extratimepricevalue;
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'day' => $extratimeday,
        'priceـper_day' => $extratimeday,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $timeservice = $DataUserOut['expire'] - time();
    $day = floor($timeservice / 86400);
    $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $extratimeday);
    if ($extra_time['status'] == false) {
        $extra_time['msg'] = redfox_remote_error_summary($extra_time);
        $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_time['msg']}";
        sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }

        if (!empty($__chargedEtUser) && function_exists('balance_atomic_credit')) {
            balance_atomic_credit($from_id, $pricelasttime);
        }
        return;
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_time_user";
    $output = json_encode(['status' => true], JSON_UNESCAPED_SLASHES);
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $pricelasttime,
        ':output' => $output,
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($tmieextra);
    $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️زمان اضافه : $extratimeday روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
    sendmessage($from_id, $textextratime, $keyboardextrafnished, 'HTML');
    $volumes = $tmieextra / $extratimepricevalue;
    $text_report = "⭕️ یک کاربر زمان اضافه خریده است

اطلاعات کاربر :
🪪 آیدی عددی : $from_id
🛍 زمان خریداری شده  : $volumes روز
💰 مبلغ پرداختی : $volumesformat تومان
👤 نام کاربری کانفیگ : {$nameloc['username']}";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/deletelist-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);
    if (is_array($nameloc) && (string) $nameloc['id_user'] === (string) $from_id) {
        if (function_exists('deleteInvoiceFromList')) {
            deleteInvoiceFromList($id_invoice, $from_id);
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :invoice_id AND id_user = :user_id");
            $stmtDelete->bindParam(':invoice_id', $id_invoice);
            $stmtDelete->bindParam(':user_id', $from_id);
            $stmtDelete->execute();
        }
        sendmessage($from_id, "📌 سرویس از لیست شما حذف شد", null, 'html');
    } else {
        sendmessage($from_id, "❌ امکان حذف سرویس وجود ندارد.", null, 'html');
    }
} elseif (preg_match('/removeserviceuser_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeserviceuser on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeserviceuser',
            ]);
        }
        return;
    }
    savedata("clear", "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 دلیل حذف سرویس خود را ارسال کنید.", $bakinfos);
    step("getdisdeleteconfig", $from_id);
} elseif ($user['step'] == "getdisdeleteconfig") {
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    savedata("save", "descritionsremove", $text);
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorusertest'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        step("home", $from_id);
        return;
    }
    $requestcheck = select("cancel_service", "*", "username", $nameloc['username'], "count");
    if ($requestcheck != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorexits'], null, 'html');
        return;
    }
    $confirmremove = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅  درخواست حذف سرویس را دارم", 'callback_data' => "confirmremoveservices-$id_invoice"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['stateus']['descriptions_removeservice'], $confirmremove, "html");
    step("home", $from_id);
} elseif (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user = :from_id AND status = 'waiting'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $checkcancelservicecount = $stmt->rowCount();
    if ($checkcancelservicecount != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['exitsrequsts'], null, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmremoveservices on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmremoveservices',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $stmt = $connect->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
    $descriptions = "0";
    $Status = "waiting";
    $stmt->bind_param("ssss", $from_id, $nameloc['username'], $descriptions, $Status);
    $stmt->execute();
    $stmt->close();
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['panelNotConnected'], null, 'html');
        step('home', $from_id);
        return;
    }

    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);
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
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $textinfoadmin = "سلام ادمین 👋

📌 یک درخواست حذف سرویس  توسط کاربر برای شما ارسال شده است. لطفا بررسی کرده و در صورت درست بودن و موافقت تایید کنید.

📊 اطلاعات سرویس کاربر :
آیدی عددی کاربر : $from_id
نام کاربری کاربر : @$username
نام کاربری کانفیگ : {$nameloc['username']}
وضعیت سرویس : $status_var
موقعیت سرویس : {$nameloc['Service_location']}
کد سرویس:{$nameloc['id_invoice']}

🟢 اخرین زمان اتصال شما : $lastonline

📥 حجم مصرفی : $usedTrafficGb
♾ حجم سرویس : $LastTraffic
🪫 حجم باقی مانده : $RemainingVolume
📅 فعال تا تاریخ : $expirationDate ($day)

<b>❌ ادمین گرامی توجه داشته باشید دکمه حذف سرویس که میزنید ربات خودکار حساب میکند و احتمال اشتباه وجود دارد پیشنهاد می شود از  حذف دستی  استفاده نمایید</b>

دلیل حذف سرویس : {$userdata['descritionsremove']}";
    $confirmremoveadmin = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌حذف دستی", 'callback_data' => "remoceserviceadminmanual-{$nameloc['id_invoice']}"],
                ['text' => "❌حذف سرویس", 'callback_data' => "remoceserviceadmin-{$nameloc['id_invoice']}"],
                ['text' => "❌عدم تایید حذف", 'callback_data' => "rejectremoceserviceadmin-{$nameloc['id_invoice']}"],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
        step("home", $admin);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['stateus']['sendrequestsremove'], $keyboard, 'html');
} elseif (preg_match('/transfer_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler transfer on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'transfer',
            ]);
        }
        return;
    }
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['transfornotvalid'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['discription'], $bakinfos);
    step("getidfortransfer", $from_id);
    update("user", "Processing_value_one", $nameloc['username'], "id", $from_id);
    update("user", "Processing_value_tow", $nameloc['id_invoice'], "id", $from_id);
} elseif ($user['step'] == "getidfortransfer") {
    if (!userExists($text)) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notusertrns'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $confirmtransfer = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید انتقال سرویس", 'callback_data' => "confrimtransfers_{$user['Processing_value_tow']}"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['transfor']['confirm'], $confirmtransfer, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/confrimtransfers_(\w+)/', $datain, $dataget)) {
    if ($from_id == $user['Processing_value_one']) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notsendserviceyou'], $keyboard, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = rxOwnedInvoice($pdo, $id_invoice, $from_id);

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confrimtransfers on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confrimtransfers',
            ]);
        }
        return;
    }
    update("invoice", "id_user", $user['Processing_value_one'], "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "backorder"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['confirmed'], $bakinfos);
    $texttransfer = "✅ کاربر گرامی  سرویس با نام کاربری {$nameloc['username']} از طرف کاربر با شناسه کاربری $from_id  به حساب کاربری شما منتقل گردید.";
    sendmessage($user['Processing_value_one'], $texttransfer, $keyboard, 'HTML');
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $value = $user['Processing_value_one'];
    $dateacc = date('Y/m/d H:i:s');
    $type = "transfertouser";
    $price = "0";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $price);
    $stmt->execute();
    $stmt->close();
} elseif ($text == $datatextbot['text_usertest'] || $datain == "usertestbtn" || $text == "usertest") {
    if (!check_active_btn($setting['keyboardmain'], "text_usertest")) {
        sendmessage($from_id, "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($locationproduct != 1) {
        if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
            sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
            step('get_number', $from_id);
        }
        if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
            return;
        if ($user['limit_usertest'] <= 0 && !in_array($from_id, $admin_ids)) {
            sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
            return;
        }
        sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_usertest, 'html');
    }
}
/* ---- user_flow.php ---- */
// Unified content menus — identical in main and reseller bots.
if ($text==='📚 آموزش اتصال'||$datain==='rxhelp_categories') { sendmessage($from_id,'📚 دسته‌بندی آموزش را انتخاب کنید:',rx_content_category_keyboard($pdo,'help','rxhelpcat_'),'HTML'); return; }
if (preg_match('/^rxhelpcat_([A-Za-z0-9_-]+)$/',$datain,$m)) { $cat=rx_content_untoken($m[1]);if($cat!==null)Editmessagetext($from_id,$message_id,'📚 آموزش‌های '.htmlspecialchars($cat),rx_content_help_items($pdo,$cat,'rxhelp_categories'),'HTML');return; }
if (preg_match('/^rxhelpitem_(\d+)$/',$datain,$m)) { $h=rx_content_help_row($pdo,(int)$m[1]);if(!$h)return;$back=json_encode(['inline_keyboard'=>[[['text'=>'🔙 آموزش‌ها','callback_data'=>'rxhelp_categories']]]],JSON_UNESCAPED_UNICODE);if(($h['type_Media_os']??'')==='photo'&&!empty($h['Media_os']))telegram('sendPhoto',['chat_id'=>$from_id,'photo'=>$h['Media_os'],'caption'=>$h['Description_os'],'reply_markup'=>$back,'parse_mode'=>'HTML']);elseif(($h['type_Media_os']??'')==='video'&&!empty($h['Media_os']))telegram('sendVideo',['chat_id'=>$from_id,'video'=>$h['Media_os'],'caption'=>$h['Description_os'],'reply_markup'=>$back,'parse_mode'=>'HTML']);else sendmessage($from_id,(string)$h['Description_os'],$back,'HTML');return; }
if ($text==='📱 نرم‌افزارهای اتصال'||$datain==='rxapp_categories') { sendmessage($from_id,'📱 سیستم‌عامل را انتخاب کنید:',rx_content_category_keyboard($pdo,'app','rxappcat_'),'HTML'); return; }
if (preg_match('/^rxappcat_([A-Za-z0-9_-]+)$/',$datain,$m)) { $cat=rx_content_untoken($m[1]);if($cat!==null)Editmessagetext($from_id,$message_id,'📥 نرم‌افزارهای '.htmlspecialchars($cat),rx_content_software_keyboard($pdo,$cat,'rxapp_categories'),'HTML');return; }

if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || ($text == $datatextbot['text_usertest'] || $datain == "usertestbtn" || $text == "usertest")) {
    if (!check_active_btn($setting['keyboardmain'], "text_usertest")) {
        sendmessage($from_id, "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $userlimit = select("user", "*", "id", $from_id, "select");
    if ($userlimit['limit_usertest'] <= 0 && !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 1) {
        $panel = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "select");
        if ($panel['hide_user'] != null) {
            $list_user = json_decode($panel['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
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
        'username' => $username_ac,
        'type' => 'usertest'
    );
    $date = time();
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?,  ?, ?, ?, ?, ?,?,?,?,?)");
    $Status = "active";
    $info_product['name_product'] = "سرویس تست";
    $info_product['price_product'] = "0";
    $Status = "active";
    $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $marzban_list_get['val_usertest'], $marzban_list_get['time_usertest'], $Status, $notifctions);
    $stmt->execute();
    $stmt->close();
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], "usertest", $username_ac, $datac);
    if ($dataoutput['username'] == null) {
        $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        update("invoice", "Status", "Unsuccessful", "id_invoice", $randomString);
        return;
    }
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }

    $usertestinfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    if ($marzban_list_get['type'] == "WGDashboard") {
        $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    $datatextbot['textaftertext'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textaftertext'];
    $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textaftertext']);
    $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $marzban_list_get['time_usertest'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $marzban_list_get['val_usertest'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    step('home', $from_id);
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت تست در ربات شما ثبت شد .
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :$username_ac
▫️نام کاربر : $first_name
▫️موقعیت سرویس : {$marzban_list_get['name_panel']}
▫️زمان خریداری شده : {$marzban_list_get['time_usertest']} ساعت
▫️حجم خریداری شده : {$marzban_list_get['val_usertest']} MB
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif ($text == $datatextbot['text_help'] || $datain == "helpbtn" || $datain == "helpbtns" || $text == "/help" || $text == "help") {
    if (!check_active_btn($setting['keyboardmain'], "text_help")) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    if ($setting['categoryhelp'] == "1") {
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, "📌 یک دسته را انتخاب نمایید", $json_list_helpـcategory, 'HTML');
        } else {
            sendmessage($from_id, "📌 یک دسته را انتخاب نمایید", $json_list_helpـcategory, 'HTML');
        }
    } else {
        $helplist = select("help", "*", null, null, "fetchAll");
        $helpidos = ['inline_keyboard' => []];
        foreach ($helplist as $result) {
            $helpidos['inline_keyboard'][] = [
                ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
            ];
        }
        if ($setting['linkappstatus'] == "1") {
            $helpidos['inline_keyboard'][] = [
                ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappdownlod"],
            ];
        }
        $helpidos['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"],
        ];
        $json_list_help = json_encode($helpidos);
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        } else {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        }
    }
} elseif (preg_match('/^helpctgoryـ(.*)/', $datain, $dataget)) {
    $hq=$pdo->prepare("SELECT h.* FROM help h JOIN content_categories c ON c.section='help' AND c.name=h.category AND c.enabled=1 WHERE h.category=? ORDER BY h.id");$hq->execute([$dataget[1]]);$helplist=$hq->fetchAll(PDO::FETCH_ASSOC);
    $helpidos = ['inline_keyboard' => []];
    foreach ($helplist as $result) {
        $helpidos['inline_keyboard'][] = [
            ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
        ];
    }
    $helpidos['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "helpbtns"],
    ];
    $json_list_help = json_encode($helpidos);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
} elseif (preg_match('/^helpos_(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns"],
            ]
        ]
    ]);
    $helpid = $dataget[1];
    $helpdata = select("help", "*", "id", $helpid, "select");
    if ($helpdata !== false) {
        if (strlen($helpdata['Media_os']) != 0) {
            if ($helpdata['type_Media_os'] == "video") {
                $backinfoss = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
                ]);
                telegram('sendvideo', [
                    'chat_id' => $from_id,
                    'video' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "document") {
                $backinfoss = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
                ]);
                telegram('sendDocument', [
                    'chat_id' => $from_id,
                    'document' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "photo") {
                $backinfoss = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
                ]);
                telegram('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            sendmessage($from_id, $helpdata['Description_os'], $backinfoss, 'HTML');
        }
    }
} elseif ($text == $datatextbot['text_support'] || $datain == "supportbtns" || $text == "/support") {
    if (!check_active_btn($setting['keyboardmain'], "text_support")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain == "supportbtns") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $supportoption);
    } else {
        sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $supportoption, 'HTML');
    }
} elseif ($datain == "support") {
    Editmessagetext($from_id, $message_id, "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.", $list_departman, 'HTML');
} elseif (preg_match('/^departman_(.*)/', $datain, $dataget)) {
    $iddeparteman = $dataget[1];
    savedata("clear", "iddeparteman", $iddeparteman);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 پیام خود را ارسال نمایید", $backuser, 'HTML');
    step("gettextticket", $from_id);
} elseif ($user['step'] == "ai_support_chat" && $text) {
    // Red Fox فاز ۵: مکالمه‌ی چندمرحله‌ای با هوش مصنوعی
    $cfg = function_exists('redfox_ai_config') ? redfox_ai_config() : ['status' => 'off'];
    $rxAiActive = ($cfg['status'] ?? 'off') === 'on' && !empty($cfg['api_key']);
    
    // اگر کاربر خواست پایان دهد یا به ادمین ارجاع شود
    $rxEndWords = ['تمام', 'پایان', 'خداحافظ', 'ادمین', 'پشتیبان', 'بای', 'خست', 'انسان', 'اپراتور'];
    $rxWantsAdmin = false;
    foreach ($rxEndWords as $rxW) {
        if (mb_strpos($text, $rxW) !== false) { $rxWantsAdmin = true; break; }
    }
    
    if ($rxWantsAdmin) {
        // کاربر می‌خواهد به ادمین ارجاع شود
        step("home", $from_id);
        if (function_exists('redfox_ai_log')) redfox_ai_log($from_id, 'user', '[پایان مکالمه — درخواست ادمین] ' . $text, 1);
        sendmessage($from_id, "👋 مکالمه با دستیار هوشمند پایان یافت. پیام شما به ادمین ارجاع داده شد.", null, 'HTML');
        // تیکت ایجاد و به ادمین ارسال
        $rxTime = date('Y/m/d H:i:s');
        $rxTrack = bin2hex(random_bytes(4));
        $rxDep = select("departman", "*", null, null, "select");
        $rxDepId = is_array($rxDep) ? ($rxDep['idsupport'] ?? '') : '';
        if ($rxDepId === '') { $rxAdmins = select("admin", "id_admin", null, null, "FETCH_COLUMN"); $rxDepId = $rxAdmins[0] ?? ''; }
        $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (?,?,?,?,?,?,'Unseen')")
            ->execute([$rxTrack, $rxDepId, $from_id, 'هوش مصنوعی → ادمین', $text, $rxTime]);
        $rxTextFwd = "📣 ارجاع از مکالمه‌ی هوش مصنوعی

🪪 آیدی: <a href=\"tg://user?id=$from_id\">$from_id</a>

📝 پیام: $text";
        $rxKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به کاربر', 'callback_data' => 'Responsesupport_' . $rxTrack]]]]);
        sendmessage($rxDepId, $rxTextFwd, $rxKb, 'HTML');
        return;
    }
    
    if ($rxAiActive) {
        // ادامه مکالمه با AI
        if (function_exists('redfox_ai_log')) redfox_ai_log($from_id, 'user', $text, 0);
        $rxReply = function_exists('redfox_ai_ask') ? redfox_ai_ask($text, $from_id, $cfg) : null;
        
        if ($rxReply !== null) {
            // بررسی نشانگر ESCALATE
            $rxEsc = false;
            if (($cfg['confidence_escalation'] ?? 'on') === 'on' && stripos($rxReply, '[ESCALATE]') !== false) {
                $rxEsc = true;
                $rxReply = trim(str_ireplace('[ESCALATE]', '', $rxReply));
            }
            if ($rxEsc) {
                // خودکار به ادمین
                step("home", $from_id);
                if (function_exists('redfox_ai_log')) redfox_ai_log($from_id, 'assistant', '[ارجاع خودکار] ' . $rxReply, 1);
                sendmessage($from_id, "🤖 این سؤال نیاز به بررسی ادمین دارد.
📨 پیام شما ارجاع داده شد.", null, 'HTML');
                return;
            }
            
            // پاسخ عادی — کاربر در حالت چت باقی می‌ماند
            if (function_exists('redfox_ai_log')) redfox_ai_log($from_id, 'assistant', $rxReply, 0);
            $rxAiText = "🤖 <i>دستیار هوشمند:</i>\n\n" . $rxReply . "\n\n💬 سؤال دیگری دارید؟ (برای صحبت با ادمین: «ادمین» را بفرستید)";
            $rxKb = json_encode(['inline_keyboard' => [
                [['text' => '👥 ارجاع به ادمین', 'callback_data' => 'aichat_admin']],
            ]]);
            sendmessage($from_id, $rxAiText, $rxKb, 'HTML');
            return;
        } else {
            // AI جواب نداد → ادمین
            step("home", $from_id);
            sendmessage($from_id, "🤖 متأسفم، الان نمی‌توانم پاسخ دهم. پیام شما به ادمین ارجاع داده شد.", null, 'HTML');
            $rxTrack = bin2hex(random_bytes(4));
            $rxDep = select("departman", "*", null, null, "select");
            $rxDepId = is_array($rxDep) ? ($rxDep['idsupport'] ?? '') : '';
            if ($rxDepId === '') { $rxAdmins = select("admin", "id_admin", null, null, "FETCH_COLUMN"); $rxDepId = $rxAdmins[0] ?? ''; }
            $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (?,?,?,?,?,?,'Unseen')")
                ->execute([$rxTrack, $rxDepId, $from_id, 'هوش مصنوعی → ادمین', $text, date('Y/m/d H:i:s')]);
            return;
        }
    } else {
        // AI خاموش است → برگشت به home
        step("home", $from_id);
        sendmessage($from_id, "💬 مکالمه پایان یافت.", $keyboard, 'HTML');
        return;
    }
} elseif ($datain == "aichat_admin") {
    // کاربر دکمه‌ی ارجاع به ادمین را در چت AI زد
    step("home", $from_id);
    sendmessage($from_id, "📨 پیام شما به ادمین ارجاع داده شد. لطفاً از بخش پشتیبانی دوباره پیام بدهید تا ادمین مستقیم پاسخ دهد.", $keyboard, 'HTML');
    return;
} elseif ($user['step'] == "gettextticket" && $text) {
    $userdata = json_decode($user['Processing_value'], true);
    $departeman = select("departman", "*", "id", $userdata['iddeparteman'], "select");
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (:Tracking,:idsupport,:iduser,:name_departman,:text,:time,:status)");
    $status = "Unseen";
    $stmt->bindParam(':Tracking', $randomString);
    $stmt->bindParam(':idsupport', $departeman['idsupport']);
    $stmt->bindParam(':iduser', $from_id);
    $stmt->bindParam(':name_departman', $departeman['name_departman']);
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    // ── Red Fox: پشتیبانی هوش مصنوعی (فاز ۱) ──
    // اگر AI روشن بود و جواب داد، خودش به کاربر پاسخ می‌دهد و ادمین درگیر نمی‌شود.
    // در غیر این صورت (خاموش / کلمه‌ی ارجاع / خطا) جریان عادیِ ارسال به ادمین اجرا می‌شود.
    if (function_exists('redfox_ai_handle_support') && redfox_ai_handle_support($from_id, $username, $text, $departeman, $randomString)) {
        // Red Fox فاز ۵: مکالمه‌ی چندمرحله‌ای — کاربر در حالت AI chat می‌ماند
        step("ai_support_chat", $from_id);
        return;
    }
    // ── پایان بخش هوش مصنوعی ──
    if ($photo) {
        sendphoto($departeman['idsupport'], $photoid, null);
    }
    if ($video) {
        sendvideo($departeman['idsupport'], $videoid, null);
    }
    $textsuppoer = "
    📣 پشتیبان عزیز یک پیام از سمت کاربر برای شما ارسال گردید.

آیدی عددی کاربر : <a href = \"tg://user?id=$from_id\">$from_id</a>
زمان ارسال : $timejalali
وضعیت پیام : پاسخ داده نشده
نام کاربری کاربر : @$username
نام دپارتمان : {$departeman['name_departman']}

متن پیام : $text $caption";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesupport_' . $randomString],
            ],
        ]
    ]);
    sendmessage($departeman['idsupport'], $textsuppoer, $Response, 'HTML');
    sendmessage($from_id, "✅ پیام شما با موفقیت ارسال و پس از بررسی به شما پاسخ داده خواهد شد.", $keyboard, 'HTML');
    step("home", $from_id);
    step("home", $departeman['idsupport']);
} elseif (preg_match('/^aifb_(\d)_(\w+)$/', $datain, $dataget)) {
    // فاز ۲: بازخورد کاربر روی پاسخ هوش مصنوعی (۱=مفید، ۰=ارجاع به ادمین)
    $value = (int)$dataget[1];
    $tracking = $dataget[2];
    $trakingdetail = select("support_message", "*", "Tracking", $tracking);
    if (is_array($trakingdetail) && (string)$trakingdetail['iduser'] === (string)$from_id) {
        if ($value === 0) {
            if (function_exists('redfox_ai_log')) {
                redfox_ai_log($from_id, 'user', '[کاربر: پاسخ خوب نبود — درخواست ادمین]', 1);
            }
            if (function_exists('redfox_ai_forward_to_admin')) {
                redfox_ai_forward_to_admin($tracking, 'کاربر ناراضی بود');
            }
            Editmessagetext($from_id, $message_id, "✅ درخواست شما به ادمین ارجاع داده شد. به‌زودی پاسخ خواهید گرفت.", null);
        } else {
            if (function_exists('redfox_ai_log')) {
                redfox_ai_log($from_id, 'user', '[کاربر: پاسخ مفید بود]', 0);
            }
            Editmessagetext($from_id, $message_id, "🙏 خوشحالیم که پاسخ مفید بود!", null);
        }
    }
} elseif (preg_match('/Responsesupport_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $trakingdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($trakingdetail)) {
        if (function_exists('rx_log_event')) {
            rx_log_event('SUPPORT_REPLY_UNKNOWN_TICKET', 'Reply attempt for missing ticket', [
                'from_id' => $from_id,
                'tracking' => $idtraking,
            ]);
        }
        sendmessage($from_id, "❌ این تیکت وجود ندارد.", null, 'HTML');
        return;
    }

    if ((string) ($trakingdetail['idsupport'] ?? '') !== (string) $from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('SUPPORT_REPLY_FORBIDDEN', 'Non-support user attempted to reply to ticket', [
                'from_id' => $from_id,
                'tracking' => $idtraking,
                'idsupport' => $trakingdetail['idsupport'] ?? null,
            ]);
        }
        sendmessage($from_id, "❌ شما به این تیکت دسترسی ندارید.", null, 'HTML');
        return;
    }
    if ($trakingdetail['status'] == "Answered") {
        sendmessage($from_id, "❌ پیام توسط ادمین دیگری پاسخ داده شده.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید", $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextsupport", $from_id);
} elseif ($user['step'] == "getextsupport") {
    $trakingdetail = select("support_message", "*", "Tracking", $user['Processing_value']);
    if (!is_array($trakingdetail)) {
        sendmessage($from_id, "❌ این تیکت وجود ندارد.", null, 'HTML');
        step("home", $from_id);
        return;
    }

    if ((string) ($trakingdetail['idsupport'] ?? '') !== (string) $from_id) {
        sendmessage($from_id, "❌ شما به این تیکت دسترسی ندارید.", null, 'HTML');
        step("home", $from_id);
        return;
    }
    $time = date('Y/m/d H:i:s');
    update("support_message", "status", "Answered", "Tracking", $user['Processing_value']);
    update("support_message", "result", $text, "Tracking", $user['Processing_value']);
    // فاز ۳: هوش مصنوعی از پاسخ ادمین یاد بگیرد (سؤال کاربر + پاسخ ادمین)
    if (function_exists('redfox_ai_learn') && is_array($trakingdetail) && !empty($trakingdetail['text'])) {
        redfox_ai_learn($trakingdetail['text'], $text, 'admin');
    }
    $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesusera_' . $trakingdetail['Tracking']],
            ],
        ]
    ]);
    sendmessage($trakingdetail['iduser'], $textSendAdminToUser, $Response, 'HTML');
    sendmessage($from_id, "پیام با موفقیت ارسال گردید", null, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/Responsesusera_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید", $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextuserfors", $from_id);
} elseif ($user['step'] == "getextuserfors") {
    $trakingdetail = select("support_message", "*", "Tracking", $user['Processing_value']);
    step("home", $from_id);
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $randomString = bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (:Tracking,:idsupport,:iduser,:name_departman,:text,:time,:status)");
    $status = "Customerresponse";
    $stmt->bindParam(':Tracking', $randomString);
    $stmt->bindParam(':idsupport', $trakingdetail['idsupport']);
    $stmt->bindParam(':iduser', $trakingdetail['iduser']);
    $stmt->bindParam(':name_departman', $trakingdetail['name_departman']);
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    $textsuppoer = "
    📣 پشتیبان عزیز یک پیام از سمت کاربر برای شما ارسال گردید.

آیدی عددی کاربر : <a href = \"tg://user?id=$from_id\">$from_id</a>
زمان ارسال : $timejalali
وضعیت پیام : پاسخ مشتری
نام کاربری کاربر : @$username
نام دپارتمان : {$trakingdetail['name_departman']}

متن پیام : $text";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesupport_' . $randomString],
            ],
        ]
    ]);
    if ($photo) {
        sendphoto($trakingdetail['idsupport'], $photoid, null);
    }
    if ($video) {
        sendvideo($trakingdetail['idsupport'], $videoid, null);
    }
    sendmessage($trakingdetail['idsupport'], $textsuppoer, $Response, 'HTML');
    sendmessage($from_id, "✅  پیام شما برای این درخواست با موفقیت ارسال گردید پس از بررسی پاسخ داده خواهد شد.", null, 'HTML');
} elseif ($datain == "fqQuestions") {
    sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
} elseif ($text == $datatextbot['accountwallet'] || $datain == "account" || $text == "/wallet") {
    $dateacc = jdate('Y/m/d');
    $current_time = time();
    $timeacc = jdate('H:i:s', $current_time);
    if (!is_string($user['codeInvitation']) || trim($user['codeInvitation']) === '') {
        $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
    }
    $first_name = htmlspecialchars($first_name);
    $Balanceuser = number_format($user['Balance'], 0);
    if ($user['number'] == "none") {
        $numberphone = "🔴 ارسال نشده است 🔴";
    } else {
        $numberphone = $user['number'];
    }
    if ($user['number'] == "confrim number by admin") {
        $numberphone = "✅ تایید شده توسط ادمین";
    } else {
        $numberphone = $numberphone;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->execute([
        ':id_user' => $from_id
    ]);
    $countorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $countpayment = $stmt->rowCount();
    $groupuser = [
        'f' => "عادی",
        'n' => "نماینده",
        'n2' => "نمایندگی پیشرفته",
    ][$user['agent']];
    $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    if (intval($setting['scorestatus']) == 1) {
        $textscore = "🥅 امتیاز حساب کاربری شما : {$user['score']}";
    } else {
        $textscore = "";
    }
    $textinvite = "";
    if ($setting['verifybucodeuser'] == "onverify" and $setting['verifystart'] == "onverify") {
        $textscore = "

🔗 لینک ریفرال جهت احراز زیر مجموعه :
https://t.me/$usernamebot?start={$user['codeInvitation']}";
    }
    $text_account = "
🗂 اطلاعات حساب کاربری شما :

🪪 شناسه کاربری: <code>$from_id</code>
👤 نام: <code>$first_name</code>
👨‍👩‍👦 کد معرف شما : <code>{$user['codeInvitation']}</code>
📱 شماره تماس :$numberphone
⌚️زمان ثبت نام : $userjoin
💰 موجودی: $Balanceuser تومان
🛒 تعداد سرویس های خریداری شده : $countorder عدد
📑 تعداد فاکتور های پرداخت شده :  : $countpayment عدد
🤝 تعداد زیر مجموعه های شما : {$user['affiliatescount']} نفر
🔖 گروه کاربری : $groupuser
$textscore
$textinvite

📆 $dateacc → ⏰ $timeacc
                    ";
    if ($datain == "account") {
        Editmessagetext($from_id, $message_id, $text_account, $keyboardPanel);
    } else {
        sendmessage($from_id, $text_account, $keyboardPanel, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (($text == $datatextbot['text_sell'] || $datain == "buy" || $datain == "buyback" || $text == "/buy" || $text == "buy") && $statusnote) {
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain == "buy") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['notestep'], $backuser);
    } elseif ($datain == "buyback") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    }
    step("statusnamecustom", $from_id);
    return;
} elseif ($text == $datatextbot['text_sell'] || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom") {
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active'");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;

    if (mysqli_num_rows($locationproduct) == 1) {
        $singlePanelRow = mysqli_fetch_assoc($locationproduct);
        if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
            if ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback") {
                Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_user);
            } else {
                sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'HTML');
            }
            return;
        }
        $location = $singlePanelRow['name_panel'];
        $locationproduct = select("marzban_panel", "*", "name_panel", $location, "select");
        if ($locationproduct['hide_user'] != null) {
            $list_user = json_decode($locationproduct['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
        }
        // Capacity is decided by the real VPN panel API; local invoices never block purchase.
        if ($user['step'] == "statusnamecustom") {
            savedata('clear', "nameconfig", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        if ($setting['statuscategory'] == "offcategory") {
            $marzban_list_get = $locationproduct;
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = $eextraprice[$user['agent']];
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
                if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
                    return;
                }
                $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
                sendmessage($from_id, $textcustom, $backuser, 'html');
                step('gettimecustomvol', $from_id);
                return;
            }
            if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                if ($setting['statusnamecustom'] == 'onnamecustom') {
                    $backuser = "buyback";
                } else {
                    $backuser = "backuser";
                }
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser));
                } else {
                    sendmessage($from_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser), 'HTML');
                }
            } else {
                $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')";
                $queryParams = [
                    ':location' => $location,
                    ':agent' => $user['agent']
                ];
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                    $statuscustom = true;
                } else {
                    $statuscustom = false;
                }
                $textproduct = $textbotlang['users']['sell']['Service-select-first'];
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
                } else {
                    sendmessage($from_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams), 'HTML');
                }
            }
        } else {
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            $statuscustom = false;
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
                $statuscustom = true;
            if ($statusnote) {
                $back = "buyback";
            } else {
                $back = "backuser";
            }
            $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", $back, $statuscustom, false);
            if ($datain == "buy" || $datain == "buybacktow") {
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
            } else {
                sendmessage($from_id, $textbotlang['Admin']['month']['title'], $monthkeyboard, 'HTML');
            }
        }
        return;
    }
    if ($user['step'] == "statusnamecustom") {
        savedata('clear', "nameconfig", $text);
        step("home", $from_id);
    }
    error_log($text);
    if ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback") {
        Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_user);
    } else {
        sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'HTML');
    }
} elseif (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") {
    $userdate = json_decode($user['Processing_value'], true);
    if ($datain != "backproduct") {
        $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    } else {
        $location = $userdate['name_panel'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $locationproductcount = select("marzban_panel", "*", "name_panel", $location, "count");
    // Capacity check is advisory only; do not block users.
    if ($statusnote) {
        savedata('save', "name_panel", $location);
    } else {
        savedata('clear', "name_panel", $location);
    }
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
        $mainvolume = $mainvolume[$user['agent']];
        $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
        $maxvolume = $maxvolume[$user['agent']];
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
        if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
            return;
        }
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        step('gettimecustomvol', $from_id);
        return;
    }
    if (function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get))) {



        if (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent'])) {
            $back = isset($userdate['nameconfig']) ? "buybacktow" : "buyback";
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $back));
            return;
        }
    }
    if ($setting['statuscategory'] == "offcategory") {
        if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], "buybacktow"));
        } else {
            $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')";
            $queryParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                $datakeyboard = "prodcutservices_";
            } else {
                $datakeyboard = "prodcutservice_";
            }
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                $statuscustom = true;
            } else {
                $statuscustom = false;
            }
            if (isset($userdate['nameconfig'])) {
                $back = "buybacktow";
            } else {
                $back = "buyback";
            }
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, "customsellvolume", $user['agent'], $queryParams));
        }
    } else {
        $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
        $productCountStmt->execute($productCountParams);
        $nullproduct = (int)$productCountStmt->fetchColumn();
        if ($nullproduct == 0) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
            return;
        }
        $statuscustom = false;
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
            $statuscustom = true;
        $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", "buybacktow", $statuscustom, false);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif (preg_match('/^categorynames_(.*)/', $datain, $dataget)) {
    $categorynames = $dataget[1];
    $categoryId = $categorynames;
    $categoryRow = select("category", "*", "id", $categoryId, "select");
    $categorynames = is_array($categoryRow) && isset($categoryRow['remark']) ? $categoryRow['remark'] : $categoryId;
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");


    $catValues = function_exists('nmCategoryLookupValues') ? nmCategoryLookupValues($categorynames) : [];
    foreach ([$categorynames, $categoryId] as $extra) { if (trim((string)$extra) !== '') $catValues[] = (string)$extra; }
    $catValues = array_values(array_unique(array_filter(array_map('strval', $catValues), static function ($v) { return trim($v) !== ''; })));
    if (!$catValues) $catValues = [(string)$categorynames];
    $catIn = [];
    $catParams = [];
    foreach ($catValues as $ci => $cv) { $key = ':catv' . $ci; $catIn[] = $key; $catParams[$key] = $cv; }
    $catClause = 'category IN (' . implode(',', $catIn) . ')';
    if (isset($userdate['monthproduct']) && !(function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get)))) {
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND {$catClause} AND Service_time = :service_time AND (agent = :agent OR agent = 'all')";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':service_time' => $userdate['monthproduct'],
            ':agent' => $user['agent']
        ], $catParams);
    } else {
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND {$catClause} AND (agent = :agent OR agent = 'all')";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':agent' => $user['agent']
        ], $catParams);
    }
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservices_";
    } else {
        $datakeyboard = "prodcutservice_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select-first'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get))) {


        if (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($marzban_list_get['name_panel'], $user['agent'])) {
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], "location_{$marzban_list_get['code_panel']}"));
            return;
        }
    }
    if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($userdate['name_panel'], $user['agent']))) {
        savedata("save", "monthproduct", $monthenumber);
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active'");
        $stmt->execute();
        $count_panel = $stmt->rowCount();
        if ($count_panel == 1) {
            $back = "buybacktow";
        } else {
            $back = "location_{$marzban_list_get['code_panel']}";
        }
        Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], $back));
    } else {
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND Service_time = :service_time AND (agent = :agent OR agent = 'all')";
        $queryParams = [
            ':location' => $userdate['name_panel'],
            ':service_time' => $monthenumber,
            ':agent' => $user['agent']
        ];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
            $datakeyboard = "prodcutservices_";
        } else {
            $datakeyboard = "prodcutservice_";
        }
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
            $statuscustom = true;
        } else {
            $statuscustom = false;
        }
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select-first'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
    }
} elseif ($datain == "customsellvolume") {
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvol', $from_id);
} elseif ($user['step'] == "gettimecustomvol") {
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit((string) $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ((int) $text > (int) $maxvolume || (int) $text < (int) $mainvolume) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        step('getvolumecustomusername', $from_id);
    } else {
        step('getvolumecustomuser', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusername" || preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    if ($user['step'] == "getvolumecustomusername") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusers', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuser', $from_id);
        deletemessage($from_id, $message_id);
    }
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
} elseif ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuser") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($user['step'] == "getvolumecustomuser") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusers" || $user['step'] == "endstepuser") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
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
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($loc, $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase preview failed: product not found for code=' . $loc . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        $resultper = ($info_product['price_product'] * $user['pricediscount']) / 100;
        $info_product['price_product'] = $info_product['price_product'] - $resultper;
    }
    $randomString = bin2hex(random_bytes(2));
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    if (isset($username_ac))
        update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product_price_product = number_format($info_product['price_product']);
    $userBalance = number_format($user['Balance']);
    $replacements = [
        '{username}' => $username_ac,
        '{name_product}' => $info_product['name_product'],
        '{Service_time}' => $info_product['Service_time'],

        '{note}' => $info_product['note'] ?? '',
        '{price}' => $info_product_price_product,
        '{Volume}' => $info_product['Volume_constraint'],
        '{userBalance}' => $userBalance
    ];
    $textin = strtr($datatextbot['text_pishinvoice'], $replacements);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textin = str_replace('گیگ', "", $textin);
    }
    if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], ["نام کاربری دلخواه", "نام کاربری دلخواه + عدد رندوم"])) {
        Editmessagetext($from_id, $message_id, $textin, $payment);
    } else {
        sendmessage($from_id, $textin, $payment, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && in_array($datain, ["confirmandgetservice", "confirmandgetserviceDiscount"], true)) {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));

    $parts = explode("_", $user['Processing_value_one']);

    $partsdic = explode("_", $user['Processing_value_four']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }

    if (!array_key_exists('category', $info_product)) {
        $info_product['category'] = '';
    }
    if (!array_key_exists('note', $info_product)) {
        $info_product['note'] = '';
    }
    if ($datain == "confirmandgetserviceDiscount") {
        $discountcode = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "count");
        if ($discountcode == 0) {
            sendmessage($from_id, "❌ امکان خرید با این کد کد تخفیف وجود ندارد", null, 'HTML');
            return;
        }
        $priceproduct = $partsdic[1];
    } else {
        $priceproduct = $info_product['price_product'];
    }
    $username_ac = strtolower($user['Processing_value_tow']);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        sendmessage($from_id, "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
        return;
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
        $stmt->bindParam(':codeproduct', $info_product['code_product']);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions);
    $stmt->execute();
    $stmt->close();
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2" && intval($priceproduct) != 0) {
        $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        $Balance_prim = $priceproduct - $user['Balance'];
        if ($Balance_prim <= 1)
            $Balance_prim = 0;
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
        } else {
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            update("user", "Processing_value_one", $username_ac, "id", $from_id);
            update("user", "Processing_value_tow", "getconfigafterpay", "id", $from_id);
            if ($datain == "confirmandgetserviceDiscount")
                update("user", "Processing_value_four", "dis_{$partsdic[0]}", "id", $from_id);
        }
        return;
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (intval($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    Editmessagetext($from_id, $message_id, "♻️ در حال ساختن سرویس شما...", null);
    if ($datain == "confirmandgetserviceDiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
        if ($SellDiscountlimit != false) {
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (?,?)");
            $stmt->bind_param("ss", $from_id, $partsdic[0]);
            $stmt->execute();
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[0]);
            $text_report = "⭕️ یک کاربر با نام کاربری @$username  و آیدی عددی $from_id از کد تخفیف {$partsdic[0]} استفاده کرد.";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
        }
    }
    $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($info_product['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buy'
    );
    $Shoppinginfo = [
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ];
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (nmStockCompleteBuyFromInventory($from_id, $user, $marzban_list_get, $info_product, $randomString, $username_ac, true, 'national_buy')) {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. لطفاً محصول دیگری انتخاب کنید یا با پشتیبانی ارتباط بگیرید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        $emergencyPanel = function_exists('nmPanelEmergencyPanel') ? nmPanelEmergencyPanel($marzban_list_get) : false;
        if ($emergencyPanel) {
            $emergencyProduct = function_exists('nmEmergencyProductFor') ? nmEmergencyProductFor($info_product, $emergencyPanel) : $info_product;
                $datac['data_limit'] = ($emergencyProduct['Volume_constraint'] ?? $info_product['Volume_constraint']) * pow(1024, 3);
                $datac['expire'] = strtotime('+' . (int)($emergencyProduct['Service_time'] ?? $info_product['Service_time']) . ' day');
                $emergencyOut = $ManagePanel->createUser($emergencyPanel['name_panel'], $emergencyProduct['code_product'], $username_ac, $datac);
            if (isset($emergencyOut['username']) && $emergencyOut['username'] !== null && $emergencyOut['username'] !== '') {
                $dataoutput = $emergencyOut;
                $marzban_list_get = $emergencyPanel;
            }
        }
    }
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        if (function_exists('nmPanelEmergencyEnabled') && nmPanelEmergencyEnabled($marzban_list_get)) {
            if (nmStockCompleteBuyFromInventory($from_id, $user, $marzban_list_get, $info_product, $randomString, $username_ac, true, 'emergency_buy_stock')) {
                sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
        }
        $errorMessage = $dataoutput['msg'] ?? 'unknown error';
        if (is_array($errorMessage) || is_object($errorMessage)) {
            $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $errorMessage = (string) $errorMessage;
        }
        $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
        sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
        $texterros = "⭕️ خطای ساخت اشتراک
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        return;
    }
    update("invoice", "Status", "active", "username", $username_ac);
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }
    $Shoppinginfo = json_encode($Shoppinginfo);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (intval($priceproduct) != 0) {

        if (($user['agent'] ?? '') === 'n2') {
            $stmtBuyDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
        } else {
            $stmtBuyDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid AND Balance >= :check_delta");
            $stmtBuyDeduct->bindValue(':check_delta', (int) $priceproduct, PDO::PARAM_INT);
        }
        $stmtBuyDeduct->bindValue(':delta', (int) $priceproduct, PDO::PARAM_INT);
        $stmtBuyDeduct->bindValue(':uid', $from_id, PDO::PARAM_STR);
        $stmtBuyDeduct->execute();
        if ($stmtBuyDeduct->rowCount() === 0 && function_exists('rx_log_event')) {
            rx_log_event('PURCHASE_DOUBLE_SPEND_OR_INSUFFICIENT', 'Atomic buy-deduct affected 0 rows after panel account already created', [
                'from_id'   => $from_id,
                'invoice'   => $randomString ?? null,
                'price'     => $priceproduct,
                'agent'     => $user['agent'] ?? null,
            ]);
        }
    }
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if ($affiliatescommission['status_commission'] == "oncommission" && ($user['affiliates'] != null && intval($user['affiliates']) != 0)) {
        if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
            if ($countinvoice == 1) {
                $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
                if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                    sendmessage($user['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $user['affiliates']);
                }

                $stmtAffComm1 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
                $stmtAffComm1->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
                $stmtAffComm1->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
                $stmtAffComm1->execute();
                $result = number_format($result);
                $dateacc = date('Y/m/d H:i:s');
                $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
تایم : $dateacc";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($user['affiliates'], $textadd, null, 'HTML');
            }
        } else {

            $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
            $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
            if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                sendmessage($user['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                $scorenew = $user_Balance['score'] + 2;
                update("user", "score", $scorenew, "id", $user['affiliates']);
            }

            $stmtAffComm2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
            $stmtAffComm2->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
            $stmtAffComm2->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
            $stmtAffComm2->execute();
            $result = number_format($result);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
            $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
تایم : $dateacc";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($user['affiliates'], $textadd, null, 'HTML');
        }
    }
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = "📌 خرید اول کاربر";
    }
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت در ربات شما ثبت شد .

$textonebuy
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :$username_ac
▫️نام کاربر : $first_name
▫️موقعیت سرویس سرویس : {$userdate['name_panel']}
▫️نام محصول :{$info_product['name_product']}
▫️زمان خریداری شده :{$info_product['Service_time']} روز
▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB
▫️موجودی قبل خرید : $balanceformatsellbefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️دسته بندی محصول : {$info_product['category']}
▫️قیمت محصول : {$info_product['price_product']} تومان
▫️قیمت نهایی : $priceproduct تومان
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "aptdc") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscount', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscount") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!isset($userdate['name_panel'])) {
        sendmessage($from_id, "❌ مراحل خرید را مجددا از اول انجام دهید", $keyboard, 'HTML');
        return;
    }
    $parts = explode("_", (string)$user['Processing_value_one']);
    if (($parts[0] ?? '') === "customvolume") {
        $info_product = [
            'code_product' => 'customvolume',
            'name_product' => $textbotlang['users']['customsellvolume']['title'],
            'Volume_constraint' => $parts[2] ?? 0,
            'Service_time' => $parts[1] ?? 0,
        ];
    } elseif (function_exists('rxResolveProductForPanel')) {
        $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :Location or Location = '/all') LIMIT 1");
        $stmt->bindParam(':code_product', $user['Processing_value_one'], PDO::PARAM_STR);
        $stmt->bindParam(':Location', $userdate['name_panel'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($info_product) || !isset($info_product['code_product'])) {
        error_log('Discount code check failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        sendmessage($from_id, "❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.", $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell WHERE (code_product = :code_product OR code_product = 'all') AND (code_panel = :code_panel OR code_panel = '/all') AND codeDiscount = :codeDiscount AND (agent = :agent OR agent = 'allusers' OR agent = 'all') AND (type = 'all' OR type = 'buy') AND (status IS NULL OR status = '' OR status = 'active') AND (target_user IS NULL OR target_user = '' OR target_user = :uid)");
    $stmt->bindParam(':code_product', $info_product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $marzban_list_get['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->bindParam(':uid', $from_id, PDO::PARAM_STR);
    $stmt->execute();
    $SellDiscountlimit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM Giftcodeconsumed WHERE id_user = :from_id AND code = :code");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $Checkcodesql = $stmt->rowCount();
    if ($SellDiscountlimit == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidcodedis'], null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['time']) != 0 and time() >= intval($SellDiscountlimit['time'])) {
        sendmessage($from_id, "❌ زمان کد تخفیف به پایان رسیده است.", null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['limitDiscount']) > 0 && intval($SellDiscountlimit['usedDiscount']) >= intval($SellDiscountlimit['limitDiscount'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimit'], null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['useuser']) > 0 && $Checkcodesql >= intval($SellDiscountlimit['useuser'])) {
        $textoncode = "⭕️ این کد تنها {$SellDiscountlimit['useuser']}  بار قابل استفاده است";
        sendmessage($from_id, $textoncode, $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($SellDiscountlimit['usefirst'] == "1") {
        $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
        $_stmt->bind_param("s", $from_id); $_stmt->execute();
        $countinvoice = $_stmt->get_result(); $_stmt->close();
        if (mysqli_num_rows($countinvoice) != 0) {
            sendmessage($from_id, $textbotlang['users']['Discount']['firstdiscount'], null, 'HTML');
            return;
        }
    }
    $__dvt = strtolower(trim((string)($SellDiscountlimit['value_type'] ?? '')));
    if (!in_array($__dvt, ['percent', 'amount', 'free'], true)) $__dvt = 'percent';
    $__dval = (float)$SellDiscountlimit['price'];
    $__dlabel = $__dvt === 'free' ? 'رایگان' : ($__dvt === 'amount' ? number_format($__dval) . ' تومان' : $SellDiscountlimit['price'] . ' درصد');
    sendmessage($from_id, "🤩 کد تخفیف شما درست بود و تخفیف {$__dlabel} روی فاکتور شما اعمال شد.", null, 'HTML');
    step('payment', $from_id);
    $parts = explode("_", $user['Processing_value_one']);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Discount purchase preview failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $info_productmain = $info_product['price_product'];
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>{$user['Processing_value_tow']}</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت اصلی : <del>$info_productmain تومان</del>
💶 قیمت با تخفیف: {$info_product['price_product']}  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : {$user['Balance']}

        💰 سفارش شما آماده پرداخت است.  ";
    $paymentDiscount = json_encode([
        'inline_keyboard' => [
            [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetserviceDiscount"]],
            [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]]
        ]
    ]);
    $parametrsendvalue = $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
} elseif ($text == "🗂 خرید انبوه" || $datain == "kharidanbuh") {
    if ($setting['bulkbuy'] == "offbulk") {
        sendmessage($from_id, "❌ این بخش در حال غیرفعال می باشد", null, 'HTML');
        return;
    }
    $PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shopSetting WHERE Namevalue = 'minbalancebuybulk'"))['value'];
    if ($user['Balance'] < $PaySetting) {
        sendmessage($from_id, "❌ برای خرید انبوه باید حداقل $PaySetting تومان موجودی داشته باشید.", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;

    if ($datain == "kharidanbuh") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    }
    step('getcountconfig', $from_id);
} elseif ($user['step'] == "getcountconfig") {
    if (intval($text) > 15 || intval($text) < 1)
        return sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backuser, 'HTML');
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_userom, 'HTML');
    update("user", "Processing_value_four", $text, "id", $from_id);
    step('home', $from_id);
} elseif (preg_match('/^locationom_(.*)/', $datain, $dataget)) {
    $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1], "select");
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND agent = :agent");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        return;
    }
    update("user", "Processing_value", $location, "id", $from_id);
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND agent = :agent";
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolumeom", $user['agent'], $productCountParams));
} elseif ($datain == "customsellvolumeom") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $textcustom = "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :
📌 تعرفه هر گیگ :  $custompricevalue
🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvolom', $from_id);
} elseif ($user['step'] == "gettimecustomvolom") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        step('getvolumecustomusernameom', $from_id);
    } else {
        step('getvolumecustomuserom', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusernameom" || preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    if ($user['step'] == "getvolumecustomusernameom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusersom', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuserom', $from_id);
    }
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
} elseif ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserom") {
    if ($user['step'] == "getvolumecustomuserom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > $maxtime || intval($text) < $maintime) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusersom" || $user['step'] == "endstepuserom") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase preview failed: product not found for code=' . ($loc ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $randomString = bin2hex(random_bytes(2));
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ($info_product['Volume_constraint'] == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product['price_product'] = intval($info_product['price_product']) * intval($user['Processing_value_four']);
    $price_product_format = number_format($info_product['price_product']);
    $userbalancepish = number_format($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>$username_ac</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت: $price_product_format  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : $userbalancepish
⭕️تعداد کانفیگ : {$user['Processing_value_four']}

💰 سفارش شما آماده پرداخت است.  ";
    sendmessage($from_id, $textin, $paymentom, 'HTML');
    step('payments', $from_id);
} elseif ($user['step'] == "payments" && $datain == "confirmandgetservice") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $user['Processing_value_one']);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
    Editmessagetext($from_id, $message_id, $text_inline, null);
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $Balance_prim = $priceproduct - $user['Balance'];
            $Balance_prims = $user['Balance'] - $priceproduct;
            if ($Balance_prims <= 1)
                $Balance_prims = 0;
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $datep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + $user['Processing_value_four'];
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + $user['Processing_value_four'];
            update("setting", "numbercount", $value);
        }
    }
    if ($info_product['Service_time'] == 0) {
        $datep = 0;
    } else {
        $datep = strtotime(date("Y-m-d H:i:s", $datep));
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", $datep)),
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buyomdh'
    );
    if ($info_product['inbounds'] != null) {
        $marzban_list_get['inboundid'] = $info_product['inbounds'];
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    for ($i = 0; $i < $user['Processing_value_four']; $i++) {
        $random_number = rand(1000000, 9999999);
        $username_acc = $username_ac . "_" . $i;
        if (isset($usernameinvoice) && is_array($usernameinvoice) && in_array($username_acc, $usernameinvoice)) {
            $username_acc = $random_number . "_" . $username_acc;
        }
        $randomString = bin2hex(random_bytes(4));
        if (in_array($randomString, $id_invoice)) {
            $randomString = $random_number . $randomString;
        }

        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            $stock = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($marzban_list_get, $info_product, $from_id, $randomString, 'national_direct_buy')
                : false;
            if (!$stock) {
                if (!empty($nmInventoryDirectCharge)) {
                    try {
                        if (function_exists('balance_atomic_charge')) {
                            $__allowNegNm = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
                            balance_atomic_charge($from_id, (float)$nmInventoryDirectCharge, $__allowNegNm);
                            $__nmRow = select("user", "*", "id", $from_id, "select");
                            $newBalanceForStockCharge = (float)($__nmRow['Balance'] ?? 0);
                        } else {
                            $freshUserForStockCharge = select("user", "*", "id", $from_id, "select");
                            $newBalanceForStockCharge = (float)($freshUserForStockCharge['Balance'] ?? $user['Balance'] ?? 0) - (float)$nmInventoryDirectCharge;
                            update("user", "Balance", $newBalanceForStockCharge, "id", $from_id);
                        }
                    } catch (Throwable $e) {
                        error_log('nm national direct partial charge failed: ' . redfox_exception_fingerprint($e));
                    }
                }
                sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. خرید انجام نشد و مبلغی از کیف پول کسر نشد.", $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
            $Status = "active";
            $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
            $stmt->execute();
            $stmt->close();
            try {
                update("invoice", "user_info", $stock['content'], "id_invoice", $randomString);
                update("invoice", "source_panel_code", $marzban_list_get['code_panel'] ?? '', "id_invoice", $randomString);
            } catch (Throwable $e) {
                error_log('nm national direct invoice update failed: ' . redfox_exception_fingerprint($e));
            }
            $inventoryInvoice = [
                'id_user' => $from_id,
                'id_invoice' => $randomString,
                'username' => $username_acc,
                'Service_location' => $marzban_list_get['name_panel'] ?? '',
                'name_product' => $info_product['name_product'] ?? '',
                'Volume' => $info_product['Volume_constraint'] ?? 0,
                'Service_time' => $info_product['Service_time'] ?? 0,
                'price_product' => $info_product['price_product'] ?? 0,
            ];
            nmStockDeliverConfig($stock, $inventoryInvoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار شبکه‌ملی تحویل شد');
            $nmInventoryDirectCharge = (float)($nmInventoryDirectCharge ?? 0) + (float)($info_product['price_product'] ?? 0);
            continue;
        }

        $get_username_Check = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc);
        if (isset($get_username_Check['username']) || (isset($usernameinvoice) && is_array($usernameinvoice) && in_array($username_acc, $usernameinvoice))) {
            $username_acc = $random_number . "_" . $username_acc;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت اکانت در بخش انبوه
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            step('home', $from_id);
            return;
        }
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
        $Status = "active";
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
        $stmt->execute();
        $stmt->close();
        $config = "";
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        if ($marzban_list_get['config'] == "onconfig") {
            if (is_array($dataoutput['configs'])) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= "\n" . $configs;
                }
            }
        }
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        if ($marzban_list_get['type'] == "WGDashboard") {
            $datatextbot['textafterpay'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
        }
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (function_exists('balance_atomic_charge')) {
        $__allowNegBp = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        balance_atomic_charge($from_id, (float)$priceproduct, $__allowNegBp);
        $__bulkBalanceRow = select("user", "*", "id", $from_id, "select");
        $Balance_prim = (float)($__bulkBalanceRow['Balance'] ?? 0);
    } else {
        $user_Balance = select("user", "*", "id", $from_id, "select");
        $Balance_prim = $user_Balance['Balance'] - $priceproduct;
        update("user", "Balance", $Balance_prim, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
    $count_service = $user['Processing_value_four'];
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت انبوه در ربات شما ثبت شد .
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :{$username_ac}_0-$count_service
▫️نام کاربر : $first_name
▫️موقعیت سرویس سرویس : {$user['Processing_value']}
▫️نام محصول :{$info_product['name_product']}
▫️زمان خریداری شده :{$info_product['Service_time']} روز
▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB
▫️موجودی قبل خرید : $balanceformatsellbefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️قیمت محصول : {$info_product['price_product']} تومان
▫️قیمت نهایی : {$info_product['price_product']} تومان
▫️تعداد کانفیگ : {$user['Processing_value_four']} عدد
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($datain == "Add_Balance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
    $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "💸 مبلغ را  به تومان وارد کنید:
✅  حداقل مبلغ $minbalance حداکثر مبلغ $maxbalance تومان می باشد", $bakinfos, 'HTML');
    step('getprice', $from_id);
    update("user", 'Processing_value', $message_id, "id", $from_id);
} elseif ($datain == "rcc_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, "❌ درخواست بررسی مجدد لغو شد.", null);
    } else {
        sendmessage($from_id, "❌ درخواست بررسی مجدد لغو شد.", null, 'HTML');
    }
} elseif ($datain == "recheckcrypto") {
    $rccListRows = [];
    try {
        $rccListStmt = $pdo->prepare(
            "SELECT id_order, crypto_currency, crypto_amount, price, time
               FROM Payment_report
              WHERE id_user = :u
                AND payment_Status = 'reject'
                AND crypto_tx_hash IS NOT NULL
                AND crypto_tx_hash <> ''
              ORDER BY id DESC
              LIMIT 10"
        );
        $rccListStmt->execute([':u' => (string) $from_id]);
        $rccListRows = $rccListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {  }

    if (empty($rccListRows)) {
        $rccEmptyMsg = "📭 <b>هیچ تراکنش رد شده‌ای ندارید</b>\n\n"
            . "تراکنش‌های کریپتویی شما که توسط ربات رد شده باشن، در این بخش نمایش داده میشن.";
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $rccEmptyMsg, null);
        } else {
            sendmessage($from_id, $rccEmptyMsg, null, 'HTML');
        }
        step('home', $from_id);
        return;
    }

    $rccKbRows = [];
    foreach ($rccListRows as $r) {
        $rccLabel = '🪙 ' . (string) ($r['crypto_currency'] ?? '?')
            . ' | ' . number_format((int) ($r['price'] ?? 0)) . ' ت'
            . ' | ' . (string) ($r['id_order'] ?? '');
        $rccKbRows[] = [
            ['text' => $rccLabel, 'callback_data' => 'rcc_view_' . (string) ($r['id_order'] ?? '')],
        ];
    }
    $rccKb = json_encode(['inline_keyboard' => $rccKbRows], JSON_UNESCAPED_UNICODE);
    $rccListMsg = "🔁 <b>تراکنش‌های رد شده شما</b>\n\n"
        . "روی هر تراکنش کلیک کنید تا جزئیاتش رو ببینید و در صورت لزوم برای بررسی دستی ادمین ارسال کنید.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccListMsg, $rccKb);
    } else {
        sendmessage($from_id, $rccListMsg, $rccKb, 'HTML');
    }
    step('home', $from_id);
} elseif (strpos((string) $datain, 'rcc_view_') === 0) {
    $rccVOid = substr((string) $datain, strlen('rcc_view_'));
    $rccVRow = null;
    try {
        $rccVStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccVStm->execute([':o' => $rccVOid, ':u' => (string) $from_id]);
        $rccVRow = $rccVStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccVRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccVExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccVRow['crypto_currency'] ?? ''), (string) ($rccVRow['crypto_tx_hash'] ?? ''))
        : (string) ($rccVRow['crypto_tx_hash'] ?? '');
    $rccVReasonRaw = (string) ($rccVRow['crypto_last_error'] ?? ($rccVRow['dec_not_confirmed'] ?? ''));
    $rccVReason = $rccVReasonRaw !== '' ? mb_substr($rccVReasonRaw, 0, 200) : '—';

    $rccVText = "🧾 <b>جزئیات تراکنش رد شده</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccVOid) . "</code>\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccVRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccVRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccVRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccVRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📅 زمان: " . htmlspecialchars((string) ($rccVRow['time'] ?? '-')) . "\n"
        . "📝 دلیل عدم تایید: " . htmlspecialchars($rccVReason) . "\n"
        . "🔍 <a href=\"" . htmlspecialchars($rccVExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>";

    $rccVKb = json_encode([
        'inline_keyboard' => [
            [['text' => '📨 ارسال مجدد برای بررسی ادمین', 'callback_data' => 'rcc_resubmit_' . $rccVOid]],
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'recheckcrypto']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccVText, $rccVKb);
    } else {
        sendmessage($from_id, $rccVText, $rccVKb, 'HTML');
    }
} elseif (strpos((string) $datain, 'rcc_resubmit_') === 0) {
    $rccROid = substr((string) $datain, strlen('rcc_resubmit_'));
    $rccRRow = null;
    try {
        $rccRStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccRStm->execute([':o' => $rccROid, ':u' => (string) $from_id]);
        $rccRRow = $rccRStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccRRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    try {
        $rccRUpd = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'ManualPending', at_updated = :now
              WHERE id_order = :o AND payment_Status = 'reject'"
        );
        $rccRUpd->execute([':now' => date('Y/m/d H:i:s'), ':o' => $rccROid]);
        if ($rccRUpd->rowCount() < 1) {
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => '❌ قبلاً ارسال شده',
                    'show_alert' => true,
                ]);
            }
            return;
        }
    } catch (Throwable $e) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ خطای داخلی',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccRExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccRRow['crypto_currency'] ?? ''), (string) ($rccRRow['crypto_tx_hash'] ?? ''))
        : (string) ($rccRRow['crypto_tx_hash'] ?? '');
    $rccRUserTag = '@' . ($user['username'] ?? 'none');
    $rccRReasonRaw = (string) ($rccRRow['crypto_last_error'] ?? ($rccRRow['dec_not_confirmed'] ?? ''));
    $rccRReason = $rccRReasonRaw !== '' ? mb_substr($rccRReasonRaw, 0, 200) : '—';
    $rccRAdminCaption = "🔁 <b>درخواست بررسی دستی توسط کاربر</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$rccRUserTag})\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccRRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccRRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccRRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccRRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📝 دلیل اولیه رد: " . htmlspecialchars($rccRReason) . "\n"
        . "🔍 <a href=\"" . htmlspecialchars($rccRExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>";

    if (function_exists('crypto_lookup_verified_hash')) {
        $rccRExisting = crypto_lookup_verified_hash((string) ($rccRRow['crypto_tx_hash'] ?? ''));
        if (is_array($rccRExisting)) {
            $rccRAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string) $rccRExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string) ($rccRExisting['user_id'] ?? '-')) . "</code>";
        }
    }

    $rccRAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید خودکار', 'callback_data' => 'cmauto_' . $rccROid],
                ['text' => '✏️ تایید دستی',   'callback_data' => 'cmmanual_' . $rccROid],
            ],
            [
                ['text' => '🗑️ لغو و حذف از دیتابیس', 'callback_data' => 'cmdelete_' . $rccROid],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $rccRAdminIds = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($rccRAdminIds)) {
        $rccRAdminIds = [];
    }
    if (function_exists('telegram')) {
        foreach ($rccRAdminIds as $rccRAdminOne) {
            if (!is_numeric($rccRAdminOne)) continue;
            @telegram('sendmessage', [
                'chat_id' => (string)$rccRAdminOne,
                'text' => $rccRAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccRAdminKb,
            ]);
        }
    }

    $rccRUserMsg = "✅ <b>درخواست شما برای بررسی دستی ارسال شد</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام می‌شود.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccRUserMsg, null);
    } else {
        sendmessage($from_id, $rccRUserMsg, null, 'HTML');
    }
} elseif (preg_match('/^rcc_pick_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $rccPick)) {
    $rccCur = $rccPick[1];
    update("user", "Processing_value_one", $rccCur, "id", $from_id);
    $rccCurFa = [
        'TRX' => 'ترون (TRX)',
        'TON' => 'تون (TON)',
        'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
        'USDT_TON' => 'تتر روی تون (USDT-TON)',
    ][$rccCur] ?? $rccCur;
    $askAmount = "💎 ارز انتخابی: <b>{$rccCurFa}</b>\n\n"
               . "🪙 لطفاً <b>مقدار ارز پرداخت‌شده</b> را وارد کنید (مثلاً <code>0.2</code> یا <code>1.25</code>):";
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $askAmount, $cancelKb);
    } else {
        sendmessage($from_id, $askAmount, $cancelKb, 'HTML');
    }
    step('rcc_amount', $from_id);
} elseif ($user['step'] == "rcc_amount" && empty($datain)) {
    $coinAmount = trim(str_replace([',', '،'], ['.', '.'], (string) $text));
    if (!is_numeric($coinAmount) || (float) $coinAmount <= 0) {
        sendmessage($from_id, "❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.", null, 'HTML');
        return;
    }
    $rccCurForRate = (string) ($user['Processing_value_one'] ?? '');
    $rateForCalc = function_exists('crypto_irt_rate_for') ? crypto_irt_rate_for($rccCurForRate) : null;
    if ($rateForCalc === null || $rateForCalc <= 0) {
        sendmessage($from_id, "❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.", null, 'HTML');
        return;
    }
    $calcIrr = (int) round(((float) $coinAmount) * $rateForCalc);
    if ($calcIrr <= 0) {
        sendmessage($from_id, "❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_tow", $coinAmount, "id", $from_id);
    update("user", "Processing_value", (string) $calcIrr, "id", $from_id);
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    sendmessage(
        $from_id,
        "💰 <b>محاسبه‌ی خودکار قیمت</b>\n\n"
        . "💎 ارز: <b>{$rccCurForRate}</b>\n"
        . "🪙 مقدار: <code>{$coinAmount}</code>\n"
        . "📈 نرخ لحظه‌ای: <code>" . number_format((float) $rateForCalc) . "</code> تومان\n"
        . "💵 <b>معادل تومانی محاسبه‌شده:</b> " . number_format($calcIrr) . " تومان\n\n"
        . "🔗 حالا <b>هش (TxID) تراکنش</b> را ارسال کنید:\n"
        . "<i>می‌توانید لینک کامل Tonviewer / Tonscan / Tronscan یا هش خام را بفرستید.</i>",
        $cancelKb,
        'HTML'
    );
    step('rcc_hash', $from_id);
} elseif ($user['step'] == "rcc_hash" && empty($datain)) {
    $hashTry = function_exists('crypto_extract_hash') ? crypto_extract_hash((string) $text) : null;
    if ($hashTry === null) {
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش خام یا لینک تراکنش را بفرستید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $hashTry, "id", $from_id);
    $photoKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⏭ ارسال بدون عکس (رد شدن)', 'callback_data' => 'rcc_skip_photo']],
            [['text' => '❌ انصراف',                    'callback_data' => 'rcc_cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید.", $photoKb, 'HTML');
    step('rcc_photo', $from_id);
} elseif ($user['step'] == "rcc_photo" && ($datain == "rcc_skip_photo" || !empty($photoid))) {
    $rccCur     = (string) $user['Processing_value_one'];
    $rccCoin    = (string) $user['Processing_value_tow'];
    $rccIrr     = (int)    $user['Processing_value'];
    $rccHash    = (string) $user['Processing_value_four'];
    $rccPhotoId = $datain === "rcc_skip_photo" ? '' : (string) $photoid;
    if (!in_array($rccCur, ['TRX', 'TON', 'USDT_TRC20', 'USDT_TON'], true) || $rccCoin === '' || $rccIrr <= 0 || $rccHash === '') {
        sendmessage($from_id, "❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    try {
        $dupChk = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h LIMIT 1");
        $dupChk->execute([':h' => $rccHash]);
        if ($dupChk->fetch()) {
            sendmessage($from_id, "❌ این هش قبلاً برای فاکتور دیگری ثبت شده است. اگر این پرداخت متعلق به شماست، با پشتیبانی تماس بگیرید.", null, 'HTML');
            step('home', $from_id);
            return;
        }
    } catch (Throwable $e) {  }
    $rccOrderId = bin2hex(random_bytes(6));
    $rccNow = date('Y/m/d H:i:s');
    $rccCoinFmt = number_format((float) $rccCoin, 9, '.', '');
    $rccCoinFmt = rtrim(rtrim($rccCoinFmt, '0'), '.');
    $rccNetwork = in_array($rccCur, ['TRX', 'USDT_TRC20'], true) ? 'TRON' : 'TON';
    try {
        $rccIns = $connect->prepare(
            "INSERT INTO Payment_report
                (id_user, id_order, time, price, payment_Status, Payment_Method,
                 id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_tx_hash, crypto_hash_at, dec_not_confirmed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $userIdStr = (string) $from_id;
        $statusManual = 'ManualPending';
        $methodLabel = 'manual crypto recheck';
        $invoiceMeta = '0|0';
        $irrStr = (string) $rccIrr;
        $nowUnix = time();
        $rccIns->bind_param(
            'sssssssssssis',
            $userIdStr, $rccOrderId, $rccNow, $irrStr, $statusManual, $methodLabel,
            $invoiceMeta, $rccCur, $rccNetwork, $rccCoinFmt, $rccHash, $nowUnix, $rccPhotoId
        );
        $rccIns->execute();
        $rccIns->close();
    } catch (Throwable $e) {
        error_log('[recheckcrypto] insert failed: ' . redfox_exception_fingerprint($e));
        sendmessage($from_id, "❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }

    sendmessage(
        $from_id,
        "✅ <b>درخواست بررسی دستی شما ثبت شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "💎 ارز: <b>{$rccCur}</b>\n"
        . "🪙 مقدار: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>" . substr($rccHash, 0, 16) . "…</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام خواهد شد.",
        null,
        'HTML'
    );

    $explorerUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url($rccCur, $rccHash)
        : $rccHash;
    $userTagLine = '@' . ($user['username'] ?? 'none');
    $rccAdminCaption = "🔁 <b>درخواست بررسی دستی پرداخت کریپتو</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$userTagLine})\n"
        . "💎 ارز: <b>{$rccCur}</b> ({$rccNetwork})\n"
        . "🪙 مقدار ادعاشده: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی ادعاشده: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>{$rccHash}</code>\n"
        . "🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در مرورگر بلاکچین</a>";
    if (function_exists('crypto_lookup_verified_hash')) {
        $rccExisting = crypto_lookup_verified_hash($rccHash);
        if (is_array($rccExisting)) {
            $rccAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string)$rccExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string)($rccExisting['user_id'] ?? '-')) . "</code>\n"
                . "📅 تایید در: " . htmlspecialchars((string)($rccExisting['verified_at'] ?? '-'));
        }
    }
    $rccAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ کیف پول', 'callback_data' => 'confirmcryptomanual_' . $rccOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $rccOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $admin_ids_local = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($admin_ids_local)) $admin_ids_local = [];
    foreach ($admin_ids_local as $idAdminLocal) {
        if (!is_numeric($idAdminLocal)) continue;
        if ($rccPhotoId !== '') {
            telegram('sendphoto', [
                'chat_id' => $idAdminLocal,
                'photo'   => $rccPhotoId,
                'caption' => $rccAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccAdminKb,
            ]);
        } else {
            sendmessage((string) $idAdminLocal, $rccAdminCaption, $rccAdminKb, 'HTML');
        }
    }
    if (!empty($setting['Channel_Report'])) {
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'caption' => $rccAdminCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => $rccAdminKb,
        ];
        if (!empty($paymentreports)) {
            $payload['message_thread_id'] = $paymentreports;
        }
        if ($rccPhotoId !== '') {
            $payload['photo'] = $rccPhotoId;
            telegram('sendphoto', $payload);
        } else {
            unset($payload['caption']);
            $payload['text'] = $rccAdminCaption;
            telegram('sendmessage', $payload);
        }
    }
    step('home', $from_id);
} elseif ($user['step'] == "getprice") {
    deletemessage($from_id, $user['Processing_value']);
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
    $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
    $balancelast = $text;
    if ($text > $maxbalance or $text < $minbalance) {
        $minbalance = number_format($minbalance);
        $maxbalance = number_format($maxbalance);
        sendmessage($from_id, "❌ خطا
💬 مبلغ باید حداقل $minbalance تومان و حداکثر $maxbalance تومان باشد", null, 'HTML');
        return;
    }
    if ($user['Balance'] < 0 and intval($setting['Debtsettlement']) == 1) {
        $balancruser = abs($user['Balance']);
        if ($text < $balancruser) {
            sendmessage($from_id, "❌ شما بدهی دارید، باید حداقل $balancruser تومان پرداخت کنید.
         میبغ خود را مجددا ارسال نمایید", null, 'HTML');
            return;
        }
    }
    update("user", "Processing_value", $balancelast, "id", $from_id);
    update("user", "Processing_value_four", "", "id", $from_id);
    $__askdisc = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، کد تخفیف دارم", 'callback_data' => "chargehasdiscount"],
            ],
            [
                ['text' => "➡️ خیر، ادامه به پرداخت", 'callback_data' => "chargenodiscount"],
            ]
        ]
    ]);
    sendmessage($from_id, "🎁 آیا برای شارژ کیف پول کد تخفیف دارید؟", $__askdisc, 'HTML');
    step('home', $from_id);
} elseif ($datain == "chargenodiscount") {
    update("user", "Processing_value_four", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment);
    step('get_step_payment', $from_id);
} elseif ($datain == "chargehasdiscount") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountcharge', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountcharge") {
    $__amount = intval($user['Processing_value']);
    if ($__amount <= 0) {
        sendmessage($from_id, "❌ مبلغ شارژ نامعتبر است. مجددا تلاش کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $dv = nm_validateSellDiscount($text, 'charge', '', '', $user, $from_id);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__gatewayAmount = (int) round(nm_applySellDiscountToPrice($dv['row'], $__amount));
    if ($__gatewayAmount <= 0) {
        sendmessage($from_id, "❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).", $backuser, 'HTML');
        return;
    }
    $__bonus = $__amount - $__gatewayAmount;
    if ($__bonus < 0) $__bonus = 0;
    update("user", "Processing_value", $__gatewayAmount, "id", $from_id);
    update("user", "Processing_value_four", "chg|" . $__bonus, "id", $from_id);
    nm_markSellDiscountUsed($text, $from_id, $username, 'charge');
    $__amountfmt  = number_format($__amount, 0);
    $__gatewayfmt = number_format($__gatewayAmount, 0);
    $__txt = "🤩 کد تخفیف {$dv['label']} روی شارژ کیف پول اعمال شد.\n\n💎 مبلغ شارژ کیف پول : {$__amountfmt} تومان\n💸 مبلغ قابل پرداخت : {$__gatewayfmt} تومان\n\nروش پرداخت را انتخاب کنید:";
    sendmessage($from_id, $__txt, $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    $__chargeBonus = function_exists('nm_pending_charge_bonus') ? nm_pending_charge_bonus($user) : 0;
    if ($datain == "cart_to_offline") {
        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
        $from_id_sql = (string) $from_id;

        $stale_cutoff = date('Y/m/d H:i:s', time() - 15 * 60);
        $_purge = $connect->prepare("DELETE FROM Payment_report WHERE id_user = ? AND payment_Status = 'Unpaid' AND Payment_Method = 'cart to cart' AND time < ?");
        $_purge->bind_param("ss", $from_id_sql, $stale_cutoff);
        $_purge->execute();
        $_purge->close();

        $_stmt = $connect->prepare("SELECT id FROM Payment_report WHERE id_user = ? AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting') AND Payment_Method = 'cart to cart' LIMIT 1");
        $_stmt->bind_param("s", $from_id_sql);
        $_stmt->execute();
        $checkpay = $_stmt->get_result();
        $_stmt->close();
        if (mysqli_num_rows($checkpay) != 0) {
            sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['issetpay'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancecart", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancecart", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        $cardQuery = mysqli_query($connect, "SELECT * FROM card_number  ORDER BY RAND() LIMIT 1");
        if ($cardQuery === false) {
            error_log('Failed to fetch card_number data: ' . 'mysql_errno_' . (int)mysqli_errno($connect));
            sendmessage($from_id, "❌ خطای داخلی در بازیابی کارت بانکی رخ داد. لطفاً بعداً تلاش کنید.", null, 'HTML');
            return;
        }

        $card_info = mysqli_fetch_assoc($cardQuery);
        if (!$card_info || empty($card_info['cardnumber']) || empty($card_info['namecard'])) {
            sendmessage($from_id, "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            mysqli_free_result($cardQuery);
            return;
        }

        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        mysqli_free_result($cardQuery);
        $price_copy = $user['Processing_value'];
        if ($PaySetting == "onautoconfirm") {
            $random_number = rand(0, 2000);
            $user['Processing_value'] = intval($user['Processing_value']) + $random_number;
            if (in_array($user['Processing_value'], $pricepayment)) {
                $random_number = rand(0, 2000);
                $user['Processing_value'] = intval($user['Processing_value']) + $random_number;
            }
            $valueshow = "{$user['Processing_value']}0";
            $replacements = [
                '{price}' => $valueshow,
                '{card_number}' => $card_number,
                '{name_card}' => $PaySettingname,
            ];
            $price_copy = $valueshow;
            $textcart = strtr($datatextbot['text_cart_auto'], $replacements);
            update("user", "Processing_value", $user['Processing_value'], "id", $from_id);
        } else {
            $valueprice = number_format($user['Processing_value']);
            $replacements = [
                '{price}' => $valueprice,
                '{card_number}' => $card_number,
                '{name_card}' => $PaySettingname,
            ];
            $price_copy = intval($user['Processing_value'] . "0");
            $textcart = strtr($datatextbot['text_cart'], $replacements);
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "cart to cart";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        deletemessage($from_id, $message_id);
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        if ($setting['statuscopycart'] == "1") {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "کپی شماره کارت", 'copy_text' => ["text" => $card_number]],
                        ['text' => "کپی مبلغ", 'copy_text' => ["text" => $price_copy]]
                    ],
                    [
                        rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                    ]
                ]
            ]);
        } else {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [
                        rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                    ]
                ]
            ]);
        }
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], $data['text']);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], $data['text']);
            }
        }
        $message_id = telegram('sendmessage', [
            'chat_id' => $from_id,
            'text' => $textcart,
            'reply_markup' => $sendresidcart,
            'parse_mode' => "html",
        ]);
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "aqayepardakht") {
        if ($user['Processing_value'] < 5000) {
            sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalanceaqayepardakht", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalanceaqayepardakht", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayaqayepardakht($user['Processing_value'], $randomString);
        if ($pay['status'] != "success") {
            $text_error = 'پاسخ درگاه آقای پرداخت نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک اقای پردات
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "aqayepardakht";
        $providerName = 'aqayepardakht';
        $providerInvoiceId = (string)$pay['transid'];
        $providerAmount = (string)$user['Processing_value'];
        $providerCurrency = 'IRT';
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://panel.aqayepardakht.ir/startpay/" . $pay['transid']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ فاکتور پرداخت ایجاد شد.\n\n🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش امکان‌پذیر نیست.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpaqayepardakht", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "zarinpey") {
        $minbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpey", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpey", "select")['ValuePay'];

        if ($user['Processing_value'] < $minbalance || $user['Processing_value'] > $maxbalance) {
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($textbotlang['users']['Balance']['zarinpey'], $minbalance, $maxbalance), null, 'HTML');
            return;
        }

        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayZarinpey($user['Processing_value'], $randomString, $from_id);

        if (empty($pay['success'])) {
            $error_text = 'پاسخ درگاه زرین‌پی نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پی\n✍️ دلیل خطا : {$error_text}\n\nآیدی کابر : $from_id\nنام کاربری کاربر : @$username";
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => 'HTML'
                ]);
            }
            return;
        }

        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpay";
        $pendingMetadata = [
            'gateway' => 'zarinpay',
            'authority' => $pay['authority'] ?? null,
            'amount_rial' => $pay['amount_rial'] ?? null,
        ];
        $pendingMetadata = array_filter(
            $pendingMetadata,
            static function ($value, $key) {
                if ($key === 'gateway') {
                    return true;
                }

                return !($value === null || $value === '');
            },
            ARRAY_FILTER_USE_BOTH
        );

        if (!empty($pendingMetadata)) {
            $pendingNote = json_encode($pendingMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $pendingNote = (string) ($pay['authority'] ?? '');
        }

        $providerName = 'zarinpay';
        $providerInvoiceId = (string)$pay['authority'];
        $providerAmount = (string)$pay['amount_rial'];
        $providerCurrency = 'IRR';
        $stmt->bind_param("ssssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pendingNote, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();

        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['payment_link'] ?? 'https://zarinpay.me'],
                ]
            ]
        ]);

        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ فاکتور پرداخت ایجاد شد.\n\n🔢 شماره فاکتور : $randomString\n💰 مبلغ فاکتور : $price_format تومان\n\n❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش امکان‌پذیر نیست.\n\n📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.\n\nجهت پرداخت از دکمه زیر استفاده کنید👇🏻";

        $gethelp = getPaySettingValue('helpzarinpey', '2');
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if (is_array($data)) {
                if ($data['type'] == "text") {
                    sendmessage($from_id, $data['text'], null, 'HTML');
                } elseif ($data['type'] == "photo") {
                    sendphoto($from_id, $data['photoid'], null);
                } elseif ($data['type'] == "video") {
                    sendvideo($from_id, $data['videoid'], null);
                }
            }
        }

        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "zarinpal") {
        if ($user['Processing_value'] < 5000) {
            sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpal", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpal", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayZarinpal($user['Processing_value'], $randomString);
        if ($pay['data']['code'] != 100) {
            $text_error = 'پاسخ درگاه زرین‌پال نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پال
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpal";
        $providerName = 'zarinpal';
        $providerInvoiceId = (string)$pay['data']['authority'];
        $providerAmount = (string)$user['Processing_value'];
        $providerCurrency = 'IRT';
        $stmt->bind_param("ssssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerInvoiceId, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://www.zarinpal.com/pg/StartPay/" . $pay['data']['authority']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "
✅ فاکتور پرداخت ایجاد شد.

🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpzarinpal", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "plisio") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        if ($usdprice <= 1) {
            sendmessage($from_id, $textbotlang['users']['Balance']['nowpayments'], null, 'HTML');
            return;
        }
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceplisio", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceplisio", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = plisio($randomString, $trxprice);
        $Payment_Method = "plisio";
        if (!is_array($pay) || isset($pay['message']) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $text_error = 'پاسخ درگاه Plisio نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $providerName = 'plisio';
        $providerInvoiceId = (string)$pay['txn_id'];
        $providerAmount = (string)$trxprice;
        $providerCurrency = 'TRX';
        $stmt->bind_param("ssssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerInvoiceId, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpplisio", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "nowpayment") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalancenowpayment", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalancenowpayment", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = nowPayments('invoice', $usdprice, $randomString, "order");
        $Payment_Method = "nowpayment";
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $text_error = 'پاسخ درگاه NowPayments نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $providerName = 'nowpayments';
        $providerInvoiceId = (string)$pay['id'];
        $providerAmount = (string)$usdprice;
        $providerCurrency = 'USD';
        $stmt->bind_param("ssssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerInvoiceId, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

<blockquote>⚠️ پس از پرداخت، در صورتی که مبلغ تراکنش به‌درستی واریز شده باشد، موجودی شما حداکثر تا ۱۵ دقیقه آینده به‌صورت خودکار شارژ خواهد شد.</blockquote>

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpnowpayment", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "iranpay1") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay1", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay1", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 1";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $pay = createInvoiceiranpay1($user['Processing_value'], $randomString);
        if ($pay['status'] != "100" || empty($pay['payment_url_bot']) || empty($pay['Authority'])) {
            $text_error = 'پاسخ درگاه پرداخت ریالی نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $bindIranPay1 = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed=:authority,provider_name='iranpay1',provider_invoice_id=:authority,provider_amount=:amount,provider_currency='IRT' WHERE id_order=:order_id AND id_user=:user_id AND Payment_Method='Currency Rial 1' AND payment_Status='Unpaid'");
        $bindIranPay1->execute([':authority'=>(string)$pay['Authority'], ':amount'=>(string)$user['Processing_value'], ':order_id'=>$randomString, ':user_id'=>$from_id]);
        if ($bindIranPay1->rowCount() !== 1) {
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "پرداخت", 'url' => $pay['payment_url_bot']]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay1", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "iranpay2") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay2", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay2", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,provider_name,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 2";
        $providerName = 'tronado';
        $providerAmount = (string)$trxprice;
        $providerCurrency = 'TRX';
        $stmt->bind_param("ssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerName, $providerAmount, $providerCurrency);
        $stmt->execute();
        $payment = trnado($randomString, $trxprice);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس ترنادو'];
        } elseif ((isset($payment['success']) && $payment['success'] === false) || (isset($payment['error']) && !isset($payment['IsSuccessful']))) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $safeHttpCode = filter_var($paymentErrorData['status_code'] ?? null, FILTER_VALIDATE_INT);
            $safeCurlCode = filter_var($paymentErrorData['errno'] ?? null, FILTER_VALIDATE_INT);
            $text_error = 'tronado_transport_failure'
                . ($safeHttpCode !== false && $safeHttpCode !== null ? ':http-' . $safeHttpCode : '')
                . ($safeCurlCode !== false && $safeCurlCode !== null ? ':curl-' . $safeCurlCode : '');
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : <pre>$safeErrorText</pre>

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $paymentSuccessful = function_exists('rxGatewayTruthy') ? rxGatewayTruthy($payment['IsSuccessful'] ?? false) : (($payment['IsSuccessful'] ?? null) == true || ($payment['IsSuccessful'] ?? null) === 'true');
        $paymentToken = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($payment) : (string) ($payment['Data']['Token'] ?? '');
        if (!$paymentSuccessful || $paymentToken === '') {
            $text_error = 'tronado_response_invalid';
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : <pre>$safeErrorText</pre>

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $providerBind = $pdo->prepare('UPDATE Payment_report SET dec_not_confirmed=:token,provider_invoice_id=:token WHERE id_order=:order_id AND provider_name=\'tronado\'');
        $providerBind->execute([':token' => $paymentToken, ':order_id' => $randomString]);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://t.me/tronado_robot/customerpayment?startapp={$paymentToken}"]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .
❌ پس از تراکنش 15 تا یک ساعت زمان میبرد تا تراکنش تایید شود

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay2", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "iranpay3") {
        $dateacc = date('Y/m/d');
        $query = "SELECT SUM(price) as price FROM Payment_report WHERE  Payment_Method = 'Currency Rial 1' AND  time LIKE '%$dateacc%'";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sumpayment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (intval($sumpayment['price']) > 1000000) {
            sendmessage($from_id, "تعداد افراد در صف درخواست درگاه پرداخت بشدت زیاد است 📊

‼️درحال حاظر از روش پرداخت دیگری استفاده کنید", null, 'HTML');
            return;
        }
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], null, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 3";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $paylink = createInvoice($trxprice);
        if (!$paylink['success']) {
            $text_error = 'پاسخ درگاه ارزی نامعتبر بود.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "dec_not_confirmed", $paylink['data']['id'], "id_order", $randomString);
        $pricetoman = number_format($user['Processing_value'], 0);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "💎 پرداخت", 'url' => "t.me/AvidTrx_Bot?start=" . $paylink['data']['id']]
                ],
            ]
        ]);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code> تومان

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay3", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        step("getvoocherx", $from_id);
        savedata("clear", "id_payment", $randomString);
    } elseif ($datain == "digitaltron") {

        $mainbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "minbalancedigitaltron", "select")['ValuePay'];
        $maxbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "maxbalancedigitaltron", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancedigitaltron || $user['Processing_value'] > $maxbalancedigitaltron) {
            $minF = number_format($mainbalancedigitaltron);
            $maxF = number_format($maxbalancedigitaltron);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $minF و حداکثر $maxF تومان باشد", null, 'HTML');
            return;
        }

        if (!function_exists('crypto_active_wallets')) {
            sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $available = crypto_active_wallets();
        if (empty($available)) {
            sendmessage($from_id, "❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است. لطفاً با پشتیبانی در تماس باشید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $faShort = [
            'TRX' => '🟥 ترون (TRX)',
            'TON' => '🟦 تون (TON)',
            'USDT_TRC20' => '🟢 تتر روی ترون',
            'USDT_TON'   => '🟢 تتر روی تون',
        ];
        $styleCurrency = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('currency_pick') : null;
        $rows = [];
        foreach ($available as $w) {
            $cur = (string) $w['currency'];
            $label = $faShort[$cur] ?? $cur;
            $btn = ['text' => $label, 'callback_data' => 'digitaltron_pay_' . $cur];
            if ($styleCurrency) $btn['style'] = $styleCurrency;
            $rows[] = [$btn];
        }
        $rows[] = [['text' => '❌ بستن', 'callback_data' => 'colselist']];
        $picker = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        $msg = "💎 <b>پرداخت با ارز دیجیتال</b>\n\n"
             . "شبکه‌ای که می‌خواهید با آن پرداخت کنید را انتخاب کنید. بعد از انتخاب، ربات یک آدرس کیف پول و یک <b>مبلغ دقیق</b> به شما اعلام می‌کند.\n\n"
             . "ℹ️ پس از پرداخت، فقط هش (Hash / TxID) تراکنش را برای ربات می‌فرستید — نیازی به ارسال عکس نیست. "
             . "هش هم به‌صورت خام و هم به‌صورت لینک (مثلاً <code>tonviewer.com/transaction/...</code> یا <code>tronscan.org/#/transaction/...</code>) قابل ارسال است.";
        sendmessage($from_id, $msg, $picker, 'HTML');
    } elseif (preg_match('/^digitaltron_(?:pay|paymode_(?:ext|ir))_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $dpmm)) {

        $cur = $dpmm[1];
        $amountIrt = (int) ($user['Processing_value'] ?? 0);
        if ($amountIrt <= 0) {
            sendmessage($from_id, "❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $invoiceMeta = sprintf('%s|%s', $user['Processing_value_tow'] ?? '', $user['Processing_value_one'] ?? '');
        $result = crypto_create_invoice($from_id, $amountIrt, $cur, $invoiceMeta);
        if (empty($result['ok'])) {
            $errMap = [
                'currency-not-supported' => 'این ارز فعال نیست.',
                'wallet-not-configured'  => 'آدرس کیف پول این ارز توسط ادمین تنظیم نشده است.',
                'below-min'              => 'مبلغ کمتر از حداقل مجاز است.',
                'above-max'              => 'مبلغ بیشتر از حداکثر مجاز است.',
                'rate-unavailable'       => 'دریافت نرخ لحظه‌ای ممکن نبود؛ لطفاً دقایقی دیگر تلاش کنید.',
                'db-write-failed'        => 'خطای داخلی در ثبت فاکتور.',
            ];
            $reason = $result['error'] ?? 'unknown';
            $faMsg = $errMap[$reason] ?? 'خطای داخلی در ساخت فاکتور ارز دیجیتال.';
            sendmessage($from_id, "❌ {$faMsg}", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $displayDecimals = function_exists('crypto_display_decimals') ? crypto_display_decimals($cur) : 2;
        $amountStr = number_format((float) $result['amount_coin'], $displayDecimals, '.', '');
        if (strpos($amountStr, '.') !== false) {
            $amountStr = rtrim(rtrim($amountStr, '0'), '.');
        }
        $expireMin = max(1, (int) round(($result['expires_at'] - time()) / 60));
        $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
            ? '<i>(لینک‌های Tronscan قابل قبول است)</i>'
            : '<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>';
        $walletEsc = htmlspecialchars((string) $result['wallet']);
        $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
        $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);

        $amountNotice = "❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.";

        $memoBlock = "";
        if ($isTonNetwork && $walletMemo !== '') {
            $memoEsc = htmlspecialchars($walletMemo);
            $memoBlock = "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{$memoEsc}</code>\n"
                       . "⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n";
        }

        $invoiceMsg = "💎 <b>فاکتور پرداخت کریپتو</b>\n\n"
             . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
             . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
             . "💸 مبلغ تومانی: " . number_format($amountIrt) . " تومان\n\n"
             . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n\n"
             . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
             . $memoBlock
             . $amountNotice . "\n"
             . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
             . "بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{$explorerHint}";

        $styleWallet = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_wallet')  : null;
        $styleAmount = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_amount')  : null;
        $styleMemo   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_memo')    : null;
        $stylePaid   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('paid_submit')  : null;
        $styleBack   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('invoice_back') : null;

        $btnCopyWallet = ['text' => '🔗 کپی آدرس ولت', 'copy_text' => ['text' => (string) $result['wallet']]];
        if ($styleWallet) $btnCopyWallet['style'] = $styleWallet;

        $btnCopyAmount = ['text' => '🪙 کپی مقدار', 'copy_text' => ['text' => $amountStr]];
        if ($styleAmount) $btnCopyAmount['style'] = $styleAmount;

        $btnPaid = ['text' => '✅ پرداخت کردم | ارسال هش (TXID) 🧾', 'callback_data' => 'digitaltron_submit_' . $result['order_id']];
        if ($stylePaid) $btnPaid['style'] = $stylePaid;

        $invoiceKbRows = [
            [$btnCopyWallet, $btnCopyAmount],
        ];
        if ($isTonNetwork && $walletMemo !== '') {
            $btnCopyMemo = ['text' => '🏷 کپی ممو', 'copy_text' => ['text' => $walletMemo]];
            if ($styleMemo) $btnCopyMemo['style'] = $styleMemo;
            $invoiceKbRows[] = [$btnCopyMemo];
        }
        $invoiceKbRows[] = [$btnPaid];
        $btnInvoiceBack = ['text' => '🔙 بازگشت (لغو فاکتور)', 'callback_data' => 'crypto_cancel_' . $result['order_id']];
        if ($styleBack) $btnInvoiceBack['style'] = $styleBack;
        $invoiceKbRows[] = [$btnInvoiceBack];
        $invoiceKb = json_encode(['inline_keyboard' => $invoiceKbRows], JSON_UNESCAPED_UNICODE);

        $resp = null;
        $fancyQrPath = null;
        $fancyOk = false;
        try {
            $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
            $autoload = $rootDir . '/vendor/autoload.php';
            if (is_file($autoload)) @require_once $autoload;
            if (class_exists('\\Endroid\\QrCode\\Builder\\Builder') && function_exists('addBackgroundImage')) {
                $builder = new \Endroid\QrCode\Builder\Builder(
                    writer: new \Endroid\QrCode\Writer\PngWriter(),
                    writerOptions: [],
                    data: (string) $result['wallet'],
                    encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                    errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                    size: 560,
                    margin: 2,
                );
                $built = $builder->build();
                $fancyQrPath = $rootDir . DIRECTORY_SEPARATOR . 'cryptoqr_' . bin2hex(random_bytes(3)) . '.png';
                @file_put_contents($fancyQrPath, $built->getString());
                $made = @addBackgroundImage($fancyQrPath, $built, 'images.jpeg');
                if ($made && is_file($fancyQrPath) && function_exists('telegram')) {
                    $resp = @telegram('sendphoto', [
                        'chat_id'      => (string) $from_id,
                        'photo'        => new \CURLFile($fancyQrPath),
                        'caption'      => $invoiceMsg,
                        'parse_mode'   => 'HTML',
                        'reply_markup' => $invoiceKb,
                    ]);
                    $fancyOk = true;
                }
            }
        } catch (\Throwable $_) {  }
        if ($fancyQrPath && is_file($fancyQrPath)) { @unlink($fancyQrPath); }

        if (!$fancyOk) {
            $resp = sendmessage($from_id, $invoiceMsg, $invoiceKb, 'HTML');
        }
        updatePaymentMessageId($resp, $result['order_id']);
    } elseif (preg_match('/^crypto_cancel_([A-Za-z0-9_\-]+)$/', (string) $datain, $dcm)) {

        $orderId = $dcm[1];
        if (function_exists('getDatabaseConnection')) {
            $pdo = getDatabaseConnection();
            if ($pdo instanceof \PDO) {
                try {
                    if (function_exists('rx_release_unpaid_discount')) {
                        $rxRep = $pdo->prepare("SELECT time FROM Payment_report WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','AwaitingHash') LIMIT 1");
                        $rxRep->execute([':o' => $orderId, ':u' => (string) $from_id]);
                        $rxRepRow = $rxRep->fetch(\PDO::FETCH_ASSOC);
                        $rxRefTime = (is_array($rxRepRow) && !empty($rxRepRow['time'])) ? strtotime(str_replace('/', '-', (string) $rxRepRow['time'])) : null;
                        rx_release_unpaid_discount((string) $from_id, null, $rxRefTime ?: null);
                    }
                    $stmt = $pdo->prepare(
                        "UPDATE Payment_report
                            SET payment_Status = 'expire'
                          WHERE id_order = :o
                            AND id_user = :u
                            AND payment_Status IN ('Unpaid', 'AwaitingHash')"
                    );
                    $stmt->execute([':o' => $orderId, ':u' => (string) $from_id]);
                } catch (\Throwable $_) {  }
            }
        }
        if (!empty($message_id) && function_exists('deletemessage')) {
            @deletemessage((string) $from_id, (int) $message_id);
        }
        sendmessage((string) $from_id, "✅ فاکتور لغو شد.", $keyboard, 'HTML');
    } elseif (preg_match('/^digitaltron_submit_([A-Za-z0-9_\-]+)$/', (string) $datain, $dsm)) {

        $orderId = $dsm[1];
        $reportStmt = $connect->prepare("SELECT id_order, payment_Status FROM Payment_report WHERE id_order = ? AND id_user = ? LIMIT 1");
        $userIdStr = (string) $from_id;
        $reportStmt->bind_param('ss', $orderId, $userIdStr);
        $reportStmt->execute();
        $rowChk = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (!is_array($rowChk)) {
            sendmessage($from_id, "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
            return;
        }
        if (!in_array($rowChk['payment_Status'], ['Unpaid', 'AwaitingHash'], true)) {
            sendmessage($from_id, "❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.", $keyboard, 'HTML');
            return;
        }
        update("user", "Processing_value_four", $orderId, "id", $from_id);
        step('digitaltron_hash_input', $from_id);
        $cancelKb = json_encode([
            'inline_keyboard' => [
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_hash_input']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage(
            $from_id,
            "📨 لطفاً <b>هش (TxID) تراکنش</b> را ارسال کنید.\n\n"
            . "هر دو فرمت قابل قبول است:\n"
            . "• هش خام (مثلاً <code>09d6aaee138447689f92a0ee7a4382dd01fcc88413f8644f2dd8fb772b0c9402</code>)\n"
            . "• لینک کامل از Tonviewer / Tonscan / Tronscan",
            $cancelKb,
            'HTML'
        );
    } elseif ($datain == "startelegrams") {
        $rates = requireTronRates(['USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $usd = $rates['USD'];
        if (!is_numeric($usd) || $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $userAmountUsd = round($user['Processing_value'] / $usd, 2);
        $starPriceSetting = getPaySettingValue('star_price_usd', '0.016');
        if (is_string($starPriceSetting)) {
            $starPriceSetting = str_replace(',', '.', $starPriceSetting);
        }
        $starPriceUsd = is_numeric($starPriceSetting) ? (float) $starPriceSetting : 0.016;
        if ($starPriceUsd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $starAmount = (int) ceil($userAmountUsd / $starPriceUsd);
        if ($starAmount < 1) {
            $starAmount = 1;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancestar", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancestar", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,provider_name,provider_invoice_id,provider_amount,provider_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Star Telegram";
        $providerName = 'telegram_stars';
        $providerInvoiceId = $randomString;
        $providerAmount = (string)$starAmount;
        $providerCurrency = 'XTR';
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $providerName, $providerInvoiceId, $providerAmount, $providerCurrency);
        $stmt->execute();
        $affilnecurrency = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
        $invoiceParams = [
            'title' => "Buy for Price {$user['Processing_value']}",
            'description' => "Buy price",
            'payload' => $randomString,
            'currency' => "XTR",
            'prices' => json_encode(array(
                array(
                    'label' => "Price",
                    'amount' => $starAmount
                )
            ))
        ];
        if (($invoiceParams['currency'] ?? null) === 'XTR') {
            unset($invoiceParams['provider'], $invoiceParams['provider_token']);
        }
        $straCreateLink = telegram('createInvoiceLink', $invoiceParams);
        if ($straCreateLink['ok'] == false) {
            $text_error = 'Telegram ایجاد فاکتور Stars را نپذیرفت.';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
خطا در هنگام ساخت فاکتور استار
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $straCreateLink['result']]
                ]
            ]
        ]);
        $formatprice = number_format($user['Processing_value'], 0);
        $approxStarUsd = number_format($starAmount * $starPriceUsd, 2);
        $textstar = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری: <code>$randomString</code>
💲 مبلغ تراکنش: $starAmount ⭐ (حدوداً $approxStarUsd دلار | معادل $formatprice تومان)

📌 لطفاً مبلغ $formatprice تومان را به استار تلگرام تبدیل کرده و واریز نمایید.

💢 نکات مهم قبل از پرداخت: 👇
🔹 هر تراکنش ۱ روز معتبر است؛ بعد از انقضا از واریز خودداری کنید.

✅ در صورت مشکل، با پشتیبانی در ارتباط باشید.";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpstar", "select")['ValuePay'];
        if (intval($gethelp) != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textstar, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    }

    if ($__chargeBonus > 0 && isset($randomString) && $randomString !== '') {
        update("Payment_report", "charge_bonus", $__chargeBonus, "id_order", $randomString);
    }
}
/* ---- panel_dispatch.php ---- */
// Re-authorize every privileged reseller-bot action server-side. Hiding a
// keyboard button is not authorization, and stale conversation steps must not
// survive permission revocation.
$rxCurrentResellerPerms = json_decode((string)($user['reseller_perms'] ?? '{}'), true);
if (!is_array($rxCurrentResellerPerms)) $rxCurrentResellerPerms = [];
$rxCurrentResellerRole = (string)($user['agent'] ?? '');
$rxResellerCan = static function (string $permission) use ($rxCurrentResellerPerms, $rxCurrentResellerRole): bool {
    return in_array($rxCurrentResellerRole, ['n', 'n2'], true)
        && !empty($rxCurrentResellerPerms[$permission]);
};
$rxAssertLockedPermission = static function (string $permission) use ($pdo, $from_id): array {
    $stmt = $pdo->prepare('SELECT id,Balance,agent,reseller_perms,bottype FROM user WHERE id=? FOR UPDATE');
    $stmt->execute([(string)$from_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $perms = is_array($row) ? json_decode((string)($row['reseller_perms'] ?? '{}'), true) : null;
    if (!is_array($row)
        || !in_array((string)($row['agent'] ?? ''), ['n', 'n2'], true)
        || !is_array($perms)
        || empty($perms[$permission])) {
        throw new RuntimeException('reseller permission revoked');
    }
    return $row;
};
$rxOwnBotToken = '';
try {
    $rxOwnBot = select('botsaz', 'bot_token', 'id_user', (string)$from_id, 'select');
    if (is_array($rxOwnBot)) $rxOwnBotToken = (string)($rxOwnBot['bot_token'] ?? '');
} catch (Throwable $rxBotLookupError) {
    redfox_log_exception($rxBotLookupError, 'reseller.scope.bot');
}
$rxFindScopedUser = static function (string $query, bool $byUsername = false) use ($pdo, $from_id, $rxOwnBotToken): ?array {
    if ($byUsername) {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $query)) return null;
        $identitySql = 'u.username = :identity';
    } else {
        if (!preg_match('/^[0-9]{1,20}$/', $query)) return null;
        $identitySql = 'u.id = :identity';
    }
    $sql = "SELECT u.* FROM user u WHERE {$identitySql} AND ("
        . "EXISTS (SELECT 1 FROM invoice i WHERE i.id_user=u.id AND i.refral=:owner";
    $params = [':identity' => $query, ':owner' => (string)$from_id];
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR i.bottype=:invoice_bot';
        $params[':invoice_bot'] = $rxOwnBotToken;
    }
    $sql .= ')';
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR u.bottype=:user_bot';
        $params[':user_bot'] = $rxOwnBotToken;
    }
    $sql .= ') LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$rxFindScopedInvoice = static function (string $userId) use ($pdo, $from_id, $rxOwnBotToken): ?array {
    if (!preg_match('/^[0-9]{1,20}$/', $userId)) return null;
    $sql = 'SELECT i.* FROM invoice i WHERE i.id_user=:user_id AND (i.refral=:owner';
    $params = [':user_id' => $userId, ':owner' => (string)$from_id];
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR i.bottype=:bot_token';
        $params[':bot_token'] = $rxOwnBotToken;
    }
    $sql .= ') ORDER BY i.time_sell DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$rxPrivilegedStepPermissions = [
    'super_create_product' => 'products',
    'super_create_category' => 'categories',
    'super_extend_main' => 'extend_user',
    'super_extend_val_main' => 'extend_user',
    'super_charge_main' => 'charge_user',
    'super_charge_amt_main' => 'charge_user',
    'super_search_main' => 'manage_users',
];
$rxCurrentStep = (string)($user['step'] ?? '');
if (isset($rxPrivilegedStepPermissions[$rxCurrentStep])
    && !$rxResellerCan($rxPrivilegedStepPermissions[$rxCurrentStep])) {
    step('home', $from_id);
    sendmessage($from_id, '⛔️ دسترسی این عملیات لغو شده است.', $keyboard, 'HTML');
    return;
}
$rxPrivilegedRequests = [
    'super_products' => 'products',
    'super_categories' => 'categories',
    'super_extend' => 'extend_user',
    'super_charge' => 'charge_user',
    'super_search' => 'manage_users',
    'super_reports' => 'reports',
];
$rxPrivilegedTexts = [
    '🛍 محصولات' => 'products',
    '📂 دسته‌بندی' => 'categories',
    '⏰ حجم/زمان کاربر' => 'extend_user',
    '⏰ افزایش حجم/زمان' => 'extend_user',
    '💰 شارژ کاربر' => 'charge_user',
    '👥 جستجوی کاربر' => 'manage_users',
    '📊 گزارش فروش' => 'reports',
];
$rxRequestedPrivilege = $rxPrivilegedRequests[(string)$datain]
    ?? $rxPrivilegedTexts[(string)$text]
    ?? null;
if ($rxRequestedPrivilege !== null && !$rxResellerCan($rxRequestedPrivilege)) {
    step('home', $from_id);
    if ($message_id && $datain) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این دسترسی برای شما فعال نیست.',
            'show_alert' => true,
        ]);
    } else {
        sendmessage($from_id, '⛔️ دسترسی این عملیات برای شما فعال نیست.', $keyboard, 'HTML');
    }
    return;
}

if (preg_match('/^Confirmpay_user_([A-Za-z0-9_.:\-]{1,191})_([A-Za-z0-9_.:\-]{1,191})$/', $datain, $dataget)) {
    $id_payment = $dataget[1];
    $id_order = $dataget[2];


    $stmtPay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :id_order AND Payment_Method='Currency Rial 2' AND provider_name='tronado' AND provider_invoice_id=:provider_id LIMIT 2");
    $stmtPay->bindValue(':id_order', $id_order, PDO::PARAM_STR);
    $stmtPay->bindValue(':provider_id', $id_payment, PDO::PARAM_STR);
    $stmtPay->execute();
    $Payment_report = $stmtPay->fetch(PDO::FETCH_ASSOC);
    $duplicatePaymentReport = $stmtPay->fetch(PDO::FETCH_ASSOC);
    if (!is_array($Payment_report) || is_array($duplicatePaymentReport) || (string)($Payment_report['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('PAYMENT_CONFIRM_FORBIDDEN', 'Non-owner attempted Confirmpay_user', [
                'from_id' => $from_id, 'id_order' => $id_order, 'id_payment' => $id_payment,
            ]);
        }
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Confirmpayadmin'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $StatusPayment = StatusPayment($id_payment);
    if (is_array($StatusPayment) && ($StatusPayment['payment_status'] ?? '') === "finished") {
        $statusPaymentId = trim((string)($StatusPayment['payment_id'] ?? ''));
        $statusOrderId = trim((string)($StatusPayment['order_id'] ?? ''));
        $statusAmount = $StatusPayment['pay_amount'] ?? null;
        $statusCurrency = strtoupper(trim((string)($StatusPayment['pay_currency'] ?? '')));
        if (($statusPaymentId !== '' && !hash_equals($id_payment, $statusPaymentId))
            || ($statusOrderId !== '' && !hash_equals($id_order, $statusOrderId))
            || ($statusAmount !== null && abs((float)$statusAmount - (float)$Payment_report['provider_amount']) > 0.00000001)
            || ($statusCurrency !== '' && !hash_equals(strtoupper((string)$Payment_report['provider_currency']), $statusCurrency))) {
            if (function_exists('rx_log_event')) {
                rx_log_event('PAYMENT_PROVIDER_MISMATCH', 'Tronado/StatusPayment response did not match bound invoice', [
                    'order_id'=>$id_order, 'provider_id'=>$id_payment,
                ]);
            }
            return;
        }
        $finalPaymentId = $statusPaymentId !== '' ? $statusPaymentId : $id_payment;
        try {
            $bindFinalPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='tronado' AND provider_invoice_id=:invoice_id AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
            $bindFinalPayment->execute([':payment_id'=>$finalPaymentId, ':same_payment_id'=>$finalPaymentId, ':id'=>(int)$Payment_report['id'], ':invoice_id'=>$id_payment]);
            if ($bindFinalPayment->rowCount() !== 1 && !hash_equals((string)($Payment_report['provider_payment_id'] ?? ''), $finalPaymentId)) {
                throw new RuntimeException('Provider payment id already bound');
            }
        } catch (Throwable $e) {
            error_log('[Confirmpay_user] provider payment binding failed: ' . redfox_exception_fingerprint($e));
            return;
        }
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['finished'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $confirm=payment_confirm_paid((string)$Payment_report['id_order'],'chashbackiranpay2',[
            'method'=>'درگاه ارزی ریالی','expected_method'=>'Currency Rial 2','thread_id'=>$paymentreports,
            'extra_lines'=>['شناسه پرداخت: '.htmlspecialchars((string)$id_payment)]
        ]);
        if(empty($confirm['ok'])){
            sendmessage($from_id,'⚠️ پرداخت تایید شد اما تکمیل سفارش نیازمند بررسی پشتیبانی است.',null,'HTML');
            return;
        }
        update("user", "Processing_value_one", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_tow", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_four", "none", "id", $Payment_report['id_user']);
    } elseif (($StatusPayment['payment_status'] ?? '') === "expired") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['expired'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "refunded") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['refunded'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "waiting") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['waiting'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "sending") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['sending'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } else {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Failed'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    }
}
if (preg_match('/^sendresidcart-([A-Za-z0-9_-]{1,128})$/', $datain, $dataget)) {
    $timefivemin = date('Y/m/d H:i:s', time() - 120);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user=:id_user AND Payment_Method='cart to cart' AND at_updated>:cutoff");
    $stmt->execute([':id_user'=>(string)$from_id, ':cutoff'=>$timefivemin]);
    $paymentcount = (int)$stmt->fetchColumn();
    if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.", null, 'HTML');
        return;
    }
    $receiptLookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='cart to cart' LIMIT 1");
    $receiptLookup->execute([':order_id'=>$dataget[1], ':id_user'=>(string)$from_id]);
    $payemntcheck = $receiptLookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($payemntcheck)) {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Card receipt order ownership mismatch',['order_id'=>$dataget[1],'from_id'=>$from_id]);
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "🖼 تصویر رسید خود را ارسال نمایید", $backuser, 'HTML');
    step('cart_to_cart_user', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif (preg_match('/^sendresidarze-([A-Za-z0-9_-]{1,128})$/', $datain, $dataget) and $text_inline != null) {
    $receiptLookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='arze digital offline' LIMIT 1");
    $receiptLookup->execute([':order_id'=>$dataget[1], ':id_user'=>(string)$from_id]);
    $payemntcheck = $receiptLookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($payemntcheck)) {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Crypto receipt order ownership mismatch',['order_id'=>$dataget[1],'from_id'=>$from_id]);
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.", $backuser, 'HTML');
    step('getresidcurrency', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == "digitaltron_hash_input") {


    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        if (!empty($message_id)) {
            @deletemessage($from_id, $message_id);
        }
        sendmessage(
            $from_id,
            "🏠 از حالت ارسال هش خارج شدید. هر زمان خواستید می‌توانید روی دکمه «📨 ارسال هش تراکنش» در فاکتور خودتان کلیک کنید.",
            $keyboard,
            'HTML'
        );
        return;
    }


    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 40
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);


        if ($trimmed !== '') {
            sendmessage($from_id, "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.", $keyboard, 'HTML');
        }
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_attach_hash')) {
        sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_extract_hash') || crypto_extract_hash($text) === null) {
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش ۶۴ رقمی hex یا لینک کامل تراکنش را ارسال کنید.", null, 'HTML');
        return;
    }
    $r = crypto_attach_hash($orderId, (string) $text, (string)$from_id);
    if (empty($r['ok'])) {
        $faMap = [
            'invalid-hash'      => 'هش وارد شده معتبر نیست.',
            'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است.',
            'order-not-pending' => 'این فاکتور در وضعیت قابل پرداخت نیست.',
            'no-db'             => 'خطای داخلی در پایگاه داده.',
            'db-update-failed'  => 'خطای داخلی در ذخیره‌سازی.',
        ];
        $msg = $faMap[$r['error'] ?? ''] ?? 'خطای داخلی در ثبت هش.';
        sendmessage($from_id, "❌ {$msg}", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    sendmessage(
        $from_id,
        "✅ هش تراکنش شما ثبت شد.\n\n"
        . "ربات هر <b>۱ دقیقه</b> یک بار به‌صورت خودکار شبکه را بررسی می‌کند:\n\n"
        . "✅ اگر همه چیز درست باشد → معمولاً <b>۱ تا ۲ دقیقه</b> طول می‌کشد و موجودی شارژ می‌شود.\n"
        . "❌ اگر مبلغ یا آدرس مقصد اشتباه باشد → <b>در همان دقیقه‌ی اول</b> پیام رد دریافت می‌کنید.\n"
        . "⏰ اگر هش روی شبکه پیدا نشود → پس از <b>۳۰ دقیقه</b> فاکتور لغو می‌شود.\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
        . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
        $keyboard,
        'HTML'
    );
} elseif ($user['step'] == "getresidcurrency") {
    $format_balance = number_format($user['Balance'], 0);
    $ownedReceipt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='arze digital offline' LIMIT 1");
    $ownedReceipt->execute([':order_id'=>(string)$user['Processing_value'], ':id_user'=>(string)$from_id]);
    $PaymentReport = $ownedReceipt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($PaymentReport) || strtolower((string)$PaymentReport['payment_Status']) !== 'unpaid') {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Crypto receipt final handler rejected order',['order_id'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
        return;
    }
    $Paymentusercount = select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
    step('home', $from_id);
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $textdiscount = "";
    $format_price_cart = number_format($PaymentReport['price'], 0);
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $user['Processing_value_one'], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
            return;
        }
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید سرویس جدید

نام کاربری سرویس : {$get_invoice['username']}
نام محصول : {$get_invoice['name_product']}
حجم محصول : {$get_invoice['Volume']} گیگ
زمان محصول : {$get_invoice['Service_time']} روز
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان


توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextenduser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($nameloc['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
تمدید
نام کاربری سرویس : $usernamepanel
نام محصول : {$prodcut['name_product']}
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextravolumeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید حجم اضافه
نام کاربری سرویس : $usernamepanel
حجم خریداری شده  : $volumes
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextratimeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید زمان اضافه
نام کاربری سرویس : $usernamepanel
تعداد روز خریداری شده  : $time
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } else {

        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .
افزایش موجودی
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    }
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => $textbotlang['users']['Balance']['receiptimage'],
                'parse_mode' => "HTML",
            ]);
        }
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    update("Payment_report", "dec_not_confirmed", "$text $caption", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($user['step'] == "cart_to_cart_user") {
    $format_balance = number_format($user['Balance'], 0);
    if (!$photo or isset($update['message']['media_group_id'])) {
        sendmessage($from_id, "❌  فقط مجاز به ارسال یک تصویر هستید", null, 'HTML');
        return;
    }
    $ownedReceipt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='cart to cart' LIMIT 1");
    $ownedReceipt->execute([':order_id'=>(string)$user['Processing_value'], ':id_user'=>(string)$from_id]);
    $PaymentReport = $ownedReceipt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($PaymentReport) || strtolower((string)$PaymentReport['payment_Status']) !== 'unpaid') {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Card receipt final handler rejected order',['order_id'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
        return;
    }
    step('home', $from_id);
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price'], 0);
    $split_data = explode('|', $PaymentReport['id_invoice']);
    if ($split_data[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $split_data[1], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
            return;
        }
        $textdiscount = "";
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید سرویس جدید
نام کاربری سرویس  : {$get_invoice['username']}
نام محصول : {$get_invoice['name_product']}
حجم محصول : {$get_invoice['Volume']} گیگ
زمان محصول : {$get_invoice['Service_time']} روز
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextenduser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($service_other['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
تمدید
نام کاربری سرویس : $usernamepanel
نام محصول : {$prodcut['name_product']}
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد", $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextravolumeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید حجم اضافه
نام کاربری سرویس : $usernamepanel
حجم خریداری شده  : $volumes
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.", $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextratimeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید زمان اضافه
نام کاربری سرویس : $usernamepanel
تعداد روز خریداری شده  : $time
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد", $keyboard, 'HTML');
    } else {

        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .
افزایش موجودی
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        telegram('sendphoto', [
            'chat_id' => $id_admin,
            'photo' => $photoid,
            'caption' => $caption,
            'parse_mode' => "HTML",
        ]);
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($datain == "Discount") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }
    $checklimit = select("Discount", "*", "code", $text, "select");
    $__gstatus = strtolower(trim((string)($checklimit['status'] ?? '')));
    if ($__gstatus !== '' && $__gstatus !== 'active') {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gtarget = trim((string)($checklimit['target_user'] ?? ''));
    if ($__gtarget !== '' && $__gtarget !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gexp = intval($checklimit['expire_at'] ?? 0);
    if ($__gexp !== 0 && time() >= $__gexp) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    if (intval($checklimit['limituse']) > 0 && intval($checklimit['limitused']) >= intval($checklimit['limituse'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_user = $user['Balance'] + $get_codesql['price'];
    update("user", "Balance", $balance_user, "id", $from_id);
    $discountlimitadd = intval($checklimit['limitused']) + 1;
    update("Discount", "limitused", $discountlimitadd, "code", $text);
    step('home', $from_id);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], $get_codesql['price']);
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:id_user, :code)");
    $stmt->execute([
        ':id_user' => $from_id,
        ':code' => $text,
    ]);
    $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == $datatextbot['text_Tariff_list'] || $datain == "Tariff_list") {
    sendmessage($from_id, $datatextbot['text_dec_Tariff_list'], null, 'HTML');
} elseif ($datain == "colselist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
} elseif ($text == $datatextbot['text_affiliates'] || $datain == "affiliatesbtn") {
    if (!check_active_btn($setting['keyboardmain'], "text_affiliates")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['affiliatesstatus'] == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    $textaffiliates = "{$affiliates['description']}\n\n🔗 https://t.me/$usernamebot?start=$from_id";
    if (strlen($affiliates['id_media']) >= 5) {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $affiliates['id_media'],
            'caption' => $textaffiliates,
            'parse_mode' => "HTML",
        ]);
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $sqlPanel = "SELECT COUNT(*) AS orders, COALESCE(SUM(price_product), 0) AS total_price
                 FROM invoice
                 WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
                 AND refral = :refral
                 AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sqlPanel);
    $stmt->execute([':refral' => $from_id]);
    $inforefral = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'total_price' => 0];
    $orders_count = (int)($inforefral['orders'] ?? 0);
    $total_purchase = (float)($inforefral['total_price'] ?? 0);
    $keyboard_share = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🎁 دریافت هدیه عضویت", 'callback_data' => "get_gift_start"],
                ['text' => "🔗 اشتراک گذاری لینک", 'url' => "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id"],
            ],
        ]
    ]);
    $text_start = "";
    $text_porsant = "";
    $Percent_porsant = $setting['affiliatespercentage'];
    $sum_order = number_format($total_purchase, 0);
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $text_start = "<b>🎁 هدیه عضویت:</b>
• 🎉 مجموع هدیه: {$affiliatescommission['price_Discount']} تومان
• 🔻 ۵۰٪ برای شما (معرف)
• 🔻 ۵۰٪ برای زیرمجموعه (کاربر جدید)
";
    }
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $text_porsant = "<b>💸 پورسانت خرید:</b>
•  $Percent_porsant درصد از مبلغ خرید زیرمجموعه به شما تعلق می‌گیره";
    }
    $textaffiliates = "<b>💼 زیرمجموعه‌گیری و هدیه خوش‌آمد</b>

با دعوت دوستان از طریق <b>لینک اختصاصی</b>، بدون پرداخت حتی ۱ ریال کیف پولت شارژ میشه و از خدمات ربات استفاده می‌کنی!

$text_start
$text_porsant

<b>📊 آمار شما:</b>
• 👥 زیرمجموعه‌ها: {$user['affiliatescount']} نفر
• 🛒 خریدها: $orders_count عدد
• 💵 مجموع خرید: $sum_order تومان

<b>📢 دعوت کن، هدیه بگیر، رشد کن!</b>
";

    sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
} elseif ($datain == "get_gift_start") {
    $gift_status = select("affiliates", "*", null, null, "select");
    if ($gift_status['Discount'] == "offDiscountaffiliates") {
        sendmessage($from_id, "📛 این بخش درحال حاضر غیرفعال می باشد", $keyboard, 'HTML');
        return;
    }
    if (!userExists($user['affiliates'])) {
        sendmessage($from_id, "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
        return;
    }
    $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
    if (!$reagent) {
        $affiliateId = intval($user['affiliates']);
        if ($affiliateId && userExists($affiliateId)) {
            $stmt = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent)
                                   VALUES (:user_id, :get_gift, :time, :reagent)
                                   ON DUPLICATE KEY UPDATE reagent = VALUES(reagent), get_gift = VALUES(get_gift), time = VALUES(time)");
            $stmt->execute([
                ':user_id' => $from_id,
                ':get_gift' => 0,
                ':time' => date('Y/m/d H:i:s'),
                ':reagent' => $affiliateId,
            ]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('reagent_report');
            }
            $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
        }
        if (!$reagent) {
            sendmessage($from_id, "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
            return;
        }
    }
    if (!empty($reagent['get_gift'])) {
        sendmessage($from_id, "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>
این هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
        return;
    }
    update("reagent_report", "get_gift", true, "user_id", $from_id);
    $reagent['get_gift'] = true;
    $price_gift_Start = select("affiliates", "*", null, null, "select");
    $price_gift_Start = intval($price_gift_Start['price_Discount']) / 2;
    $useraffiliates = select("user", "*", 'id', $reagent['reagent'], "select");
    $Balance_add_regent = $useraffiliates['Balance'] + $price_gift_Start;
    update("user", "Balance", $Balance_add_regent, "id", $reagent['reagent']);
    $Balance_add_user = $user['Balance'] + $price_gift_Start;
    update("user", "Balance", $Balance_add_user, "id", $from_id);
    $addbalancediscount = number_format($price_gift_Start, 0);
    sendmessage($reagent['reagent'], "🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.", null, 'html');
    sendmessage($from_id, "🎉 هدیه عضویت برای شما فعال شد!", null, 'html');
    $report_join_gift = "🎁 پرداخت هدیه عضویت
 -آیدی عددی : $from_id
 - نام کاربری : @$username
 - آیدی عددی معرف : {$reagent['reagent']}
 - موجودی زیرمجموعه قبل از هدیه : {$user['Balance']}
 - موجودی زیرمجموعه بعد از هدیه : $Balance_add_user
  - موجودی معرف قبل از هدیه : {$useraffiliates['Balance']}
 - موجودی معرف بعد از هدیه : $Balance_add_regent
 ";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $porsantreport,
            'text' => $report_join_gift,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $locations = select("marzban_panel", "*", "code_panel", $dataget[2], "select");
    $location = $locations['name_panel'];
    $eextraprice = json_decode($locations['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    update("user", "Processing_value_one", $location, "id", $from_id);

    $textextra = sprintf($textbotlang['users']['Extra_volume']['enterextravolume'], $extrapricevalue);
    sendmessage($from_id, $textextra, $backuser, 'HTML');
    step('getvolumeextras', $from_id);
} elseif ($user['step'] == "getvolumeextras") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['Extra_volume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextras_(\w+)/', $datain, $dataget)) {
    $volume = $dataget[1];
    if ($user['Balance'] < $volume && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $volume - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volume = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }

    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_one'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + (intval($volume) / intval($extrapricevalue) * pow(1024, 3));
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price) VALUES (:id_user, :username, :value, :type, :time, :price)");
    $value = $data_limit;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $user['Processing_value'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volume,
    ]);
    $data_limit_new = (intval($volume) / intval($extrapricevalue));
    $extra_volume = $ManagePanel->extra_volume($user['Processing_value'], $marzban_list_get['code_panel'], $data_limit_new);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = redfox_remote_error_summary($extra_volume);
        $textreports = "خطای خرید حجم اضافه
نام پنل : {$user['Processing_value_one']}
نام کاربری سرویس : {$user['Processing_value']}
دلیل خطا : {$extra_volume['msg']}";
        sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegEv = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargeEv = balance_atomic_charge($from_id, (float)$volume, $__allowNegEv);
        if (empty($__chargeEv['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEv['new_balance'];
    } else {
        $Balance_Low_user = $user['Balance'] - $volume;
        update("user", "Balance", $Balance_Low_user, "id", $from_id);
    }
    $back = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
    $volumes = $volume / $extrapricevalue;
    $volumes = number_format($volumes, 0);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumepurchase'], $from_id, $volumes, $volume, $user['Balance'], $user['Processing_value']);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchservice") {
    sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
    step('getuseragnetservice', $from_id);
} elseif ($datain == "Responseuser") {
    step('getmessageAsuser', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backuser, 'HTML');
} elseif ($user['step'] == "getmessageAsuser") {
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        if ($text) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['usermessage'], $from_id, $username, $caption . $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['userresponse'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
} elseif (($text == $datatextbot['textpanelagent'] || $text == '👨‍💻 پنل نمایندگی' || $datain == "agentpanel") && $user['agent'] != "f") {
    if ($setting['inlinebtnmain'] == "oninline") {
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    }
} elseif ($datain == "reseller_ai_menu" || $text == "🤖 هوش مصنوعی ربات من") {
    // Red Fox: منوی هوش مصنوعی برای نماینده — نمایش وضعیت + دکمه خرید/درخواست
    $rxBotToken = '';
    $rxBotUsername = '';
    try {
        $rxBs = $pdo->prepare("SELECT bot_token, username FROM botsaz WHERE id_user = :uid LIMIT 1");
        $rxBs->execute([':uid' => (string)$from_id]);
        $rxBsRow = $rxBs->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxBsRow)) {
            $rxBotToken = (string)$rxBsRow['bot_token'];
            $rxBotUsername = (string)$rxBsRow['username'];
        }
    } catch (Throwable $e) {}

    if ($rxBotToken === '') {
        sendmessage($from_id, "❌ شما هنوز ربات نماینده‌ای نساخته‌اید. ابتدا از طریق مدیریت، ربات خود را بسازید.", $keyboard, 'HTML');
        return;
    }

    // وضعیت اشتراک
    $rxAiRow = null;
    try {
        $rxAst = $pdo->prepare("SELECT * FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
        $rxAst->execute([':t' => $rxBotToken]);
        $rxAiRow = $rxAst->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    $rxPrice = '0'; $rxDays = '30';
    try {
        $rxPrice = (string)$pdo->query("SELECT reseller_ai_price FROM setting LIMIT 1")->fetchColumn();
        $rxDays  = (string)$pdo->query("SELECT reseller_ai_days FROM setting LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}

    $rxStatusText = "🔴 غیرفعال";
    $rxExpInfo = "—";
    if (is_array($rxAiRow)) {
        $rxSt = (string)($rxAiRow['status'] ?? 'disabled');
        $rxExp = (string)($rxAiRow['expires_at'] ?? '');
        if ($rxSt === 'active' && $rxExp !== '') {
            $rxExpTs = strtotime($rxExp);
            if ($rxExpTs && $rxExpTs > time()) {
                $rxStatusText = "🟢 فعال";
                $rxDiff = $rxExpTs - time();
                $rxD = floor($rxDiff / 86400);
                $rxH = floor(($rxDiff % 86400) / 3600);
                $rxExpInfo = $rxExp . " (" . (int)$rxD . " روز و " . (int)$rxH . " ساعت باقی)";
            } else {
                $rxStatusText = "🔴 منقضی";
                $rxExpInfo = $rxExp;
            }
        } elseif ($rxSt === 'pending') {
            $rxStatusText = "🟡 در انتظار تأیید";
        }
    }

    $rxPriceFmt = number_format((int)$rxPrice);
    $rxMsg = "🤖 <b>قابلیت پاسخ‌گویی هوش مصنوعی برای ربات شما</b>\n\n";
    $rxMsg .= "🤖 ربات شما: @" . htmlspecialchars($rxBotUsername) . "\n";
    $rxMsg .= "📊 وضعیت اشتراک: <b>" . $rxStatusText . "</b>\n";
    $rxMsg .= "⏰ تاریخ انقضا: " . $rxExpInfo . "\n\n";
    $rxMsg .= "💡 <b>این قابلیت چیست؟</b>\n";
    $rxMsg .= "با فعال‌سازی این قابلیت، مشتریانِ ربات شما می‌توانند سؤالات خود را به دستیار هوش مصنوعی بپرسند و پاسخ آنی بگیرند — بدون نیاز به حضور شما!\n\n";
    $rxMsg .= "💰 <b>قیمت:</b> " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxMsg .= "👇 برای خرید یا تمدید، روی دکمه زیر بزنید:";

    $rxKb = json_encode([
        'inline_keyboard' => [
            [['text' => "🛒 خرید / تمدید هوش مصنوعی (" . $rxPriceFmt . " ت)", 'callback_data' => "reseller_ai_buy"]],
            [['text' => "🔙 بازگشت", 'callback_data' => "agentpanel"]],
        ]
    ]);
    if ($message_id && $datain) {
        Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML');
    } else {
        sendmessage($from_id, $rxMsg, $rxKb, 'HTML');
    }
} elseif ($datain == "reseller_ai_buy") {
    // Red Fox: نماینده درخواست خرید هوش مصنوعی داد → ثبت + اطلاع به ادمین
    $rxBotToken = '';
    $rxBotUsername = '';
    try {
        $rxBs = $pdo->prepare("SELECT bot_token, username FROM botsaz WHERE id_user = :uid LIMIT 1");
        $rxBs->execute([':uid' => (string)$from_id]);
        $rxBsRow = $rxBs->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxBsRow)) {
            $rxBotToken = (string)$rxBsRow['bot_token'];
            $rxBotUsername = (string)$rxBsRow['username'];
        }
    } catch (Throwable $e) {}

    if ($rxBotToken === '') {
        telegram('answerCallbackQuery', ['callback_query_id'=>$callback_query_id, 'text'=>'ربات نماینده‌ای یافت نشد', 'show_alert'=>true]);
        return;
    }

    // ثبت/به‌روزرسانی رکورد با وضعیت pending
    $rxNow = date('Y-m-d H:i:s');
    try {
        $rxChk = $pdo->prepare("SELECT id, status FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
        $rxChk->execute([':t'=>$rxBotToken]);
        $rxExist = $rxChk->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxExist) && $rxExist['status'] === 'pending') {
            telegram('answerCallbackQuery', ['callback_query_id'=>$callback_query_id, 'text'=>'شما قبلاً درخواست داده‌اید و در انتظار تأیید است.', 'show_alert'=>true]);
            return;
        }
        if (is_array($rxExist)) {
            $pdo->prepare("UPDATE reseller_ai_feature SET status='pending', updated_at=:u WHERE bot_token=:t")
                ->execute([':u'=>$rxNow, ':t'=>$rxBotToken]);
        } else {
            $pdo->prepare("INSERT INTO reseller_ai_feature (reseller_id, bot_token, bot_username, status, created_at, updated_at) VALUES (:rid, :bt, :bu, 'pending', :c, :u)")
                ->execute([':rid'=>(string)$from_id, ':bt'=>$rxBotToken, ':bu'=>$rxBotUsername, ':c'=>$rxNow, ':u'=>$rxNow]);
        }
    } catch (Throwable $e) {
        error_log('[reseller_ai_buy] ' . redfox_exception_fingerprint($e));
    }

    $rxPrice = '0'; $rxDays = '30';
    try {
        $rxPrice = (string)$pdo->query("SELECT reseller_ai_price FROM setting LIMIT 1")->fetchColumn();
        $rxDays  = (string)$pdo->query("SELECT reseller_ai_days FROM setting LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}
    $rxPriceFmt = number_format((int)$rxPrice);

    // پیام به کاربر
    $rxUserMsg = "✅ درخواست شما ثبت شد!\n\n";
    $rxUserMsg .= "💰 مبلغ: " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxUserMsg .= "📌 لطفاً مبلغ را واریز کنید و رسید را ارسال کنید. پس از تأیید ادمین، قابلیت هوش مصنوعی برای ربات شما فعال خواهد شد.\n";
    $rxUserMsg .= "⏰ شما می‌توانید وضعیت را از منوی «🤖 هوش مصنوعی ربات من» پیگیری کنید.";
    Editmessagetext($from_id, $message_id, $rxUserMsg, json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['users']['backbtn'],'callback_data'=>'agentpanel']]]]), 'HTML');

    // اطلاع به ادمین
    $rxAdminMsg = "🤖 <b>درخواست خرید قابلیت هوش مصنوعی</b>\n\n";
    $rxAdminMsg .= "👤 نماینده: <a href=\"tg://user?id=$from_id\">$from_id</a> (@$username)\n";
    $rxAdminMsg .= "🤖 ربات: @" . htmlspecialchars($rxBotUsername) . "\n";
    $rxAdminMsg .= "💰 مبلغ درخواستی: " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxAdminMsg .= "✅ برای تأیید و فعال‌سازی به پنل وب → «هوش مصنوعی نماینده‌ها» مراجعه کنید.";
    $rxAdminKb = json_encode(['inline_keyboard'=>[[['text'=>'🔗 پنل مدیریت هوش مصنوعی','url'=>'https://' . ($domainhosts ?? '') . '/panel/reseller_ai.php']]]]);
    foreach ($admin_ids as $rxAdmin) {
        sendmessage((string)$rxAdmin, $rxAdminMsg, $rxAdminKb, 'HTML');
    }
// ── Red Fox: قابلیت‌های سوپر نماینده در ربات اصلی ──
// ════════════════════════════════════════════════════════════════════
//  ۱) پاداش روزانه (/daily)
// ════════════════════════════════════════════════════════════════════
} elseif (($text == "/daily" || $text == "daily" || $text == "🎁 پاداش روزانه") && !in_array($from_id, $admin_ids)) {
    $drStatus = '';
    $drAmount = 1000;
    $drStreakBonus = 500;
    $drMaxStreak = 7;
    try {
        $drRow = $pdo->query("SELECT daily_reward_status, daily_reward_amount, daily_reward_streak_bonus, daily_reward_max_streak FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $drStatus = (string)($drRow['daily_reward_status'] ?? 'off');
        $drAmount = (int)($drRow['daily_reward_amount'] ?? 1000);
        $drStreakBonus = (int)($drRow['daily_reward_streak_bonus'] ?? 500);
        $drMaxStreak = (int)($drRow['daily_reward_max_streak'] ?? 7);
    } catch (Throwable $e) {}
    if ($drStatus !== 'on') {
        sendmessage($from_id, "❌ پاداش روزانه فعلاً غیرفعال است.", $keyboard, 'HTML');
    } else {
        $today = date('Y-m-d');
        $drExisting = null;
        try {
            $drSt = $pdo->prepare("SELECT * FROM daily_rewards WHERE user_id = ? LIMIT 1");
            $drSt->execute([$from_id]);
            $drExisting = $drSt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        if ($drExisting && $drExisting['last_claim_date'] === $today) {
            sendmessage($from_id, "✅ شما امروز پاداش خود را دریافت کرده‌اید.\n🔄 فردا دوباره /daily بزنید!\n🔥 استریک شما: " . (int)$drExisting['streak_count'] . " روز", $keyboard, 'HTML');
        } else {
            // محاسبه استریک
            $streak = 1;
            if ($drExisting) {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($drExisting['last_claim_date'] === $yesterday) {
                    $streak = (int)$drExisting['streak_count'] + 1;
                    if ($streak > $drMaxStreak) $streak = 1; // ریست بعد از ماکزیمم
                }
            }
            $reward = $drAmount + (($streak - 1) * $drStreakBonus);
            // شارژ موجودی
            $newBalance = (int)$user['Balance'] + $reward;
            update("user", "Balance", $newBalance, "id", $from_id);
            // ثبت در جدول
            try {
                if ($drExisting) {
                    $pdo->prepare("UPDATE daily_rewards SET last_claim_date = ?, streak_count = ?, total_claimed = total_claimed + 1, total_amount = total_amount + ? WHERE user_id = ?")
                        ->execute([$today, $streak, $reward, $from_id]);
                } else {
                    $pdo->prepare("INSERT INTO daily_rewards (user_id, last_claim_date, streak_count, total_claimed, total_amount) VALUES (?, ?, 1, 1, ?)")
                        ->execute([$from_id, $today, $reward]);
                }
            } catch (Throwable $e) {}
            $msg = "🎁 <b>پاداش روزانه شما!</b>\n\n";
            $msg .= "💰 مبلغ: <b>" . number_format($reward) . " تومان</b>\n";
            $msg .= "🔥 استریک: <b>$streak روز</b>\n";
            if ($streak < $drMaxStreak) {
                $nextReward = $drAmount + ($streak * $drStreakBonus);
                $msg .= "📅 فردا: " . number_format($nextReward) . " تومان (با استریک بیشتر!)\n";
            } else {
                $msg .= "🏆 شما به حداکثر استریک رسیدید!\n";
            }
            $msg .= "💎 موجودی جدید: <b>" . number_format($newBalance) . " تومان</b>";
            sendmessage($from_id, $msg, $keyboard, 'HTML');
        }
    }
    step('home', $from_id);

// ════════════════════════════════════════════════════════════════════
//  ۲) فعال/غیرفعال‌سازی تمدید خودکار
// ════════════════════════════════════════════════════════════════════
} elseif ($text == "🔄 تمدید خودکار" || $datain == "toggle_autorenew") {
    rx_require_schema($pdo,[],['user'=>['auto_renew']]);
    $currentAR = 0;
    try {
        $arRow = $pdo->prepare("SELECT auto_renew FROM user WHERE id = ? LIMIT 1");
        $arRow->execute([$from_id]);
        $currentAR = (int)$arRow->fetchColumn();
    } catch (Throwable $e) {}
    $newAR = $currentAR ? 0 : 1;
    try { $pdo->prepare("UPDATE user SET auto_renew = ? WHERE id = ?")->execute([$newAR, $from_id]); } catch (Throwable $e) {}
    if ($newAR) {
        sendmessage($from_id, "✅ <b>تمدید خودکار فعال شد!</b>\n\n💡 سرویس‌های شما قبل از انقضا، خودکار از کیف پول تمدید می‌شوند.\n⚠️ مطمئن شوید موجودی کافی دارید.", $keyboard, 'HTML');
    } else {
        sendmessage($from_id, "❌ تمدید خودکار غیرفعال شد.\n💡 برای فعال‌سازی مجدد دوباره بزنید.", $keyboard, 'HTML');
    }
    step('home', $from_id);

// ════════════════════════════════════════════════════════════════════
//  ۳) کمپین فروش ویژه — نمایش کمپین‌های فعال
// ════════════════════════════════════════════════════════════════════
} elseif ($text == "⚡ تخفیف ویژه" || $datain == "flash_sale_info") {
    $activeCampaigns = [];
    try {
        $fcStmt = $pdo->query("SELECT * FROM flash_campaigns WHERE is_active = 1 AND ends_at > NOW() AND starts_at <= NOW() ORDER BY ends_at ASC LIMIT 5");
        $activeCampaigns = $fcStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if (empty($activeCampaigns)) {
        sendmessage($from_id, "📭 در حال حاضر کمپین تخفیف فعالی وجود ندارد.", $keyboard, 'HTML');
    } else {
        $fcMsg = "⚡ <b>کمپین‌های فروش ویژه فعال!</b>\n\n";
        foreach ($activeCampaigns as $fc) {
            $fcMsg .= "🔥 <b>" . htmlspecialchars((string)$fc['title'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            $fcMsg .= "💶 تخفیف: <b>" . (int)$fc['discount_percent'] . "٪</b>\n";
            $fcMsg .= "⏰ تا: " . htmlspecialchars((string)$fc['ends_at']) . "\n\n";
        }
        $fcMsg .= "💡 همین حالا خرید کنید و از تخفیف بهره‌مند شوید!";
        $fcKb = json_encode(['inline_keyboard' => [[['text' => '🛍 خرید با تخفیف', 'callback_data' => 'buy']]]]);
        sendmessage($from_id, $fcMsg, $fcKb, 'HTML');
    }
    step('home', $from_id);

// ── Red Fox: قابلیت‌های سوپر نماینده در ربات اصلی ──
} elseif (($datain == "super_products" || $text == "🛍 محصولات") && $rxResellerCan('products')) {
    $rxMsg = "🛍 <b>مدیریت محصولات شما</b>\n\nبرای ایجاد یا ویرایش محصول، فرمت زیر را ارسال کنید:\n\n<code>نام_محصول-حجم-زمان-قیمت</code>\n\nمثال:\n<code>ماهانه ۱۰ گیگ-10-30-50000</code>\n(نام - حجم GB - زمان روز - قیمت تومان)\n\n💡 حجم 0 = نامحدود | زمان 0 = نامحدود";
    $rxKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "agentpanel"]]]]);
    if ($message_id && $datain) { Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML'); }
    else { sendmessage($from_id, $rxMsg, $rxKb, 'HTML'); }
    step('super_create_product', $from_id);
} elseif ($user['step'] == "super_create_product" && $text && $rxResellerCan('products')) {
    // فرمت: نام-حجم-زمان-قیمت
    $parts = explode('-', (string)$text);
    if (count($parts) >= 4) {
        $pName = mb_substr(trim($parts[0]), 0, 191);
        $pVolRaw = trim($parts[1]);
        $pTimeRaw = trim($parts[2]);
        $pPriceRaw = trim($parts[3]);
        $pVol = ctype_digit($pVolRaw) ? (int)$pVolRaw : -1;
        $pTime = ctype_digit($pTimeRaw) ? (int)$pTimeRaw : -1;
        $pPrice = ctype_digit($pPriceRaw) ? (int)$pPriceRaw : 0;
        if ($pName !== '' && $pPrice >= 1 && $pPrice <= 1000000000
            && $pVol >= 0 && $pVol <= 100000 && $pTime >= 0 && $pTime <= 36500) {
            $pCode = 'sr_' . bin2hex(random_bytes(3));
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO product (code_product, name_product, price_product, Volume_constraint, Service_time, agent, Location, category, note, data_limit_reset, hide_panel) VALUES (:code, :name, :price, :vol, :time, :agent, '/all', '', '', 'no_reset', '{}')");
                $stmt->bindValue(':code', $pCode);
                $stmt->bindValue(':name', $pName);
                $stmt->bindValue(':price', (string)$pPrice);
                $stmt->bindValue(':vol', (string)$pVol);
                $stmt->bindValue(':time', (string)$pTime);
                $stmt->bindValue(':agent', (string)$from_id); // محصول فقط برای این نماینده
                $stmt->execute();
                $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                    ->execute([
                        (string)$from_id,
                        'bot',
                        'product.create',
                        $pCode,
                        json_encode(['price' => $pPrice, 'volume_gb' => $pVol, 'days' => $pTime], JSON_UNESCAPED_UNICODE),
                        null,
                        time(),
                    ]);
                $pdo->commit();
                $safeProductName = htmlspecialchars($pName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                sendmessage($from_id, "✅ محصول «{$safeProductName}» با موفقیت ساخته شد!\n📦 حجم: " . ($pVol ?: 'نامحدود') . " GB\n⏳ زمان: " . ($pTime ?: 'نامحدود') . " روز\n💶 قیمت: " . number_format($pPrice) . " ت\n🔑 کد: <code>$pCode</code>", $keyboard, 'HTML');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                redfox_log_exception($e, 'reseller.product.create');
                sendmessage($from_id, "❌ ساخت محصول به‌دلیل خطای داخلی انجام نشد.", $keyboard, 'HTML');
            }
        } else { sendmessage($from_id, "❌ نام، حجم، زمان یا قیمت خارج از محدوده مجاز است.", $keyboard, 'HTML'); }
    } else { sendmessage($from_id, "❌ فرمت اشتباه. مثال:\n<code>ماهانه ۱۰ گیگ-10-30-50000</code>", $keyboard, 'HTML'); }
    step('home', $from_id);

} elseif (($datain == "super_categories" || $text == "📂 دسته‌بندی") && $rxResellerCan('categories')) {
    sendmessage($from_id, "📂 <b>ایجاد دسته‌بندی</b>\n\nنام دسته‌بندی را ارسال کنید:", null, 'HTML');
    step('super_create_category', $from_id);
} elseif ($user['step'] == "super_create_category" && $text && $rxResellerCan('categories')) {
    $catName = mb_substr(trim((string)$text), 0, 191);
    if ($catName !== '') {
        try {
            $categoryId = 'rc-' . bin2hex(random_bytes(8));
            $now = time();
            $pdo->beginTransaction();
            $rxAssertLockedPermission('categories');
            $pdo->prepare("INSERT INTO reseller_categories (reseller_id,name,slug,sort_order,status,created_at,updated_at) VALUES (?,?,?,0,'active',?,?)")
                ->execute([(string)$from_id, $catName, $categoryId, $now, $now]);
            $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    (string)$from_id,
                    'bot',
                    'category.create',
                    $categoryId,
                    json_encode(['name_length' => mb_strlen($catName, 'UTF-8')], JSON_UNESCAPED_UNICODE),
                    null,
                    $now,
                ]);
            $pdo->commit();
            $safeCategoryName = htmlspecialchars($catName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            sendmessage($from_id, "✅ دسته‌بندی مستقل «{$safeCategoryName}» ساخته شد!", $keyboard, 'HTML');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redfox_log_exception($e, 'reseller.category.create');
            sendmessage($from_id, "❌ ساخت دسته‌بندی به‌دلیل خطای داخلی انجام نشد.", $keyboard, 'HTML');
        }
    } else { sendmessage($from_id, "❌ نام خالی است.", $keyboard, 'HTML'); }
    step('home', $from_id);

} elseif (($datain == "super_extend" || $text == "⏰ حجم/زمان کاربر") && $rxResellerCan('extend_user')) {
    sendmessage($from_id, "⏰ <b>افزایش حجم/زمان کاربر</b>\n\nآیدی عددی کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_extend_main', $from_id);
} elseif ($user['step'] == "super_extend_main" && $text && $rxResellerCan('extend_user')) {
    if (!ctype_digit((string)$text)) { sendmessage($from_id, "❌ آیدی عددی معتبر ارسال کنید.", $keyboard, 'HTML'); return; }
    $rxTargetUser = $rxFindScopedUser((string)$text);
    $rxTargetInv = $rxTargetUser ? $rxFindScopedInvoice((string)$text) : null;
    if (!$rxTargetInv) { sendmessage($from_id, "❌ کاربر یا سرویس در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'extend_target', (string)$text);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\nسرویس: <code>{$rxTargetInv['username']}</code>\n\nفرمت: <code>حجم_روز</code> (مثال: <code>5_30</code>):", $keyboard, 'HTML');
    step('super_extend_val_main', $from_id);
} elseif ($user['step'] == "super_extend_val_main" && $text && $rxResellerCan('extend_user')) {
    $parts = explode('_', (string)$text);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) { sendmessage($from_id, "❌ فرمت اشتباه. مثال: 5_30", $keyboard, 'HTML'); return; }
    $volGB = (int)$parts[0]; $days = (int)$parts[1];
    if (($volGB < 1 && $days < 1) || $volGB > 100000 || $days > 36500) {
        sendmessage($from_id, "❌ حجم یا زمان خارج از محدوده مجاز است.", $keyboard, 'HTML');
        return;
    }
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetUid = is_array($pv) ? (string)($pv['extend_target'] ?? '') : '';
    $rxTargetUser = $rxFindScopedUser($targetUid);
    $rxTargetInv = $rxTargetUser ? $rxFindScopedInvoice($targetUid) : null;
    if (!$rxTargetInv) { sendmessage($from_id, "❌ سرویس در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    if (isset($ManagePanel)) {
        // Persist a freshly authorized intent under row locks before the
        // irreversible provider request. Revocation or ownership changes
        // racing the conversation step cannot authorize this operation.
        $extendAuditId = 0;
        try {
            $pdo->beginTransaction();
            $rxAssertLockedPermission('extend_user');

            $lockedBotToken = '';
            if ($rxOwnBotToken !== '') {
                $botScopeLock = $pdo->prepare('SELECT bot_token FROM botsaz WHERE id_user=? AND bot_token=? LIMIT 1 FOR UPDATE');
                $botScopeLock->execute([(string)$from_id, $rxOwnBotToken]);
                $tokenValue = $botScopeLock->fetchColumn();
                if ($tokenValue !== false) $lockedBotToken = (string)$tokenValue;
            }

            $invoiceId = trim((string)($rxTargetInv['id_invoice'] ?? ''));
            if ($invoiceId === '') {
                throw new RuntimeException('extend invoice identity missing');
            }
            if ($lockedBotToken !== '') {
                $invoiceLock = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=? AND id_user=? AND (refral=? OR bottype=?) LIMIT 1 FOR UPDATE');
                $invoiceLock->execute([$invoiceId, $targetUid, (string)$from_id, $lockedBotToken]);
            } else {
                $invoiceLock = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=? AND id_user=? AND refral=? LIMIT 1 FOR UPDATE');
                $invoiceLock->execute([$invoiceId, $targetUid, (string)$from_id]);
            }
            $lockedInvoice = $invoiceLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lockedInvoice)) {
                throw new RuntimeException('extend invoice scope changed');
            }
            $rxTargetInv = $lockedInvoice;

            $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    (string)$from_id,
                    'bot',
                    'service.extend.requested',
                    $invoiceId,
                    json_encode(['target_user_id' => $targetUid, 'volume_gb' => $volGB, 'days' => $days], JSON_UNESCAPED_UNICODE),
                    null,
                    time(),
                ]);
            $extendAuditId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redfox_log_exception($e, 'reseller.service.extend.audit_request');
            sendmessage($from_id, "❌ تمدید انجام نشد؛ مجوز، مالکیت سرویس یا ثبت گزارش امنیتی معتبر نبود.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }

        $panel = select("marzban_panel", "*", "name_panel", (string)$rxTargetInv['Service_location'], "select");
        if (!is_array($panel) || empty($panel['code_panel'])) {
            try {
                $pdo->prepare("UPDATE reseller_audit_log SET action='service.extend.failed',details=? WHERE id=? AND reseller_id=?")
                    ->execute([json_encode(['reason' => 'panel_not_found'], JSON_UNESCAPED_UNICODE), $extendAuditId, (string)$from_id]);
            } catch (Throwable $ignored) {
            }
            sendmessage($from_id, "❌ پنل سرویس در دسترس نیست.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }

        try {
            $extResult = $ManagePanel->extend("ریست حجم و زمان", $volGB, $days, $rxTargetInv['username'], $rxTargetInv['code_product'] ?? 'custom', $panel['code_panel']);
        } catch (Throwable $e) {
            $extResult = ['status' => false];
            redfox_log_exception($e, 'reseller.service.extend.provider');
        }
        $extendSucceeded = !empty($extResult['status']);
        try {
            if ($extendAuditId > 0) {
                $pdo->prepare('UPDATE reseller_audit_log SET action=?, details=? WHERE id=? AND reseller_id=?')
                    ->execute([
                        $extendSucceeded ? 'service.extend.succeeded' : 'service.extend.failed',
                        json_encode(['target_user_id' => $targetUid, 'volume_gb' => $volGB, 'days' => $days], JSON_UNESCAPED_UNICODE),
                        $extendAuditId,
                        (string)$from_id,
                    ]);
            }
        } catch (Throwable $e) {
            redfox_log_exception($e, 'reseller.service.extend.audit_result');
        }
        if ($extendSucceeded) {
            sendmessage($from_id, "✅ به کاربر <code>$targetUid</code>، $volGB گیگ و $days روز اضافه شد.", $keyboard, 'HTML');
            @sendmessage($targetUid, "🎁 سرویس شما تمدید شد!\n💾 $volGB گیگ\n⏰ $days روز", null, 'HTML');
        } else {
            sendmessage($from_id, "❌ خطا در تمدید.", $keyboard, 'HTML');
        }
    } else { sendmessage($from_id, "❌ پنل یافت نشد.", $keyboard, 'HTML'); }
    step('home', $from_id);
} elseif (($datain == "super_charge" || $text == "💰 شارژ کاربر") && $rxResellerCan('charge_user')) {
    sendmessage($from_id, "💰 <b>شارژ کیف پول کاربر</b>\n\nآیدی عددی کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_charge_main', $from_id);
} elseif ($user['step'] == "super_charge_main" && $text && $rxResellerCan('charge_user')) {
    if (!ctype_digit((string)$text)) { sendmessage($from_id, "❌ آیدی عددی معتبر.", $keyboard, 'HTML'); return; }
    $rxTarget = $rxFindScopedUser((string)$text);
    if (!$rxTarget) { sendmessage($from_id, "❌ کاربر در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'charge_target', (string)$text);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\nموجودی فعلی: " . number_format((int)$rxTarget['Balance']) . " ت\n\nمبلغ مثبت انتقال از کیف پول نمایندگی (حداکثر ۱٬۰۰۰٬۰۰۰٬۰۰۰):", $keyboard, 'HTML');
    step('super_charge_amt_main', $from_id);
} elseif ($user['step'] == "super_charge_amt_main" && $text && $rxResellerCan('charge_user')) {
    $amountText = trim((string)$text);
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetUid = is_array($pv) ? (string)($pv['charge_target'] ?? '') : '';
    if (!ctype_digit($amountText) || $amountText === '0' || strlen($amountText) > 10) {
        sendmessage($from_id, "❌ مبلغ باید عددی مثبت و حداکثر یک میلیارد تومان باشد.", $keyboard, 'HTML');
        return;
    }
    $amt = (int)$amountText;
    if ($amt < 1 || $amt > 1000000000 || !$rxFindScopedUser($targetUid)) {
        sendmessage($from_id, "❌ مبلغ یا کاربر خارج از محدوده مجاز است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $chargeError = '';
    try {
        $pdo->beginTransaction();

        if ($targetUid === '' || hash_equals((string)$from_id, $targetUid)) {
            $chargeError = 'انتقال به حساب نمایندگی مجاز نیست.';
            throw new RuntimeException('reseller self charge denied');
        }

        // Lock actor and target in one deterministic key order. This avoids
        // reciprocal-transfer deadlocks caused by locking each owner first.
        $userLocks = $pdo->prepare('SELECT id,Balance,agent,reseller_perms,bottype FROM user WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $userLocks->execute([(string)$from_id, $targetUid]);
        $lockedUsers = [];
        while ($lockedUser = $userLocks->fetch(PDO::FETCH_ASSOC)) {
            $lockedUsers[(string)$lockedUser['id']] = $lockedUser;
        }
        $ownerRow = $lockedUsers[(string)$from_id] ?? null;
        $targetRow = $lockedUsers[$targetUid] ?? null;
        $lockedPerms = is_array($ownerRow) ? json_decode((string)($ownerRow['reseller_perms'] ?? '{}'), true) : [];
        if (!is_array($ownerRow)
            || !in_array((string)($ownerRow['agent'] ?? ''), ['n', 'n2'], true)
            || !is_array($lockedPerms)
            || empty($lockedPerms['charge_user'])) {
            $chargeError = 'دسترسی شارژ نمایندگی لغو شده است.';
            throw new RuntimeException('reseller charge permission revoked');
        }
        if (!is_array($targetRow)) {
            $chargeError = 'کاربر دیگر در دسترس نیست.';
            throw new RuntimeException('scoped customer disappeared');
        }
        $ownerBalance = (int)($ownerRow['Balance'] ?? 0);
        if ($ownerBalance < $amt) {
            $chargeError = 'موجودی نمایندگی کافی نیست.';
            throw new RuntimeException('reseller balance insufficient');
        }

        // Lock the token ownership row used by legacy own-bot scoping so it
        // cannot be reassigned while target scope is being established.
        $lockedBotToken = '';
        if ($rxOwnBotToken !== '') {
            $botScopeLock = $pdo->prepare('SELECT bot_token FROM botsaz WHERE id_user=? AND bot_token=? LIMIT 1 FOR UPDATE');
            $botScopeLock->execute([(string)$from_id, $rxOwnBotToken]);
            $lockedBotTokenValue = $botScopeLock->fetchColumn();
            if ($lockedBotTokenValue !== false) $lockedBotToken = (string)$lockedBotTokenValue;
        }

        // The target row and the exact ownership evidence are both locked and
        // rechecked inside this transaction. A concurrent referral/bot-scope
        // change can no longer turn the earlier precheck into an out-of-scope
        // credit.
        $scopeLocked = $lockedBotToken !== ''
            && (string)($targetRow['bottype'] ?? '') !== ''
            && hash_equals($lockedBotToken, (string)$targetRow['bottype']);
        if (!$scopeLocked) {
            if ($lockedBotToken !== '') {
                $scopeLock = $pdo->prepare('SELECT id_invoice FROM invoice WHERE id_user=? AND (refral=? OR bottype=?) LIMIT 1 FOR UPDATE');
                $scopeLock->execute([$targetUid, (string)$from_id, $lockedBotToken]);
            } else {
                $scopeLock = $pdo->prepare('SELECT id_invoice FROM invoice WHERE id_user=? AND refral=? LIMIT 1 FOR UPDATE');
                $scopeLock->execute([$targetUid, (string)$from_id]);
            }
            $scopeLocked = $scopeLock->fetchColumn() !== false;
        }
        if (!$scopeLocked) {
            $chargeError = 'کاربر از محدوده نمایندگی خارج شده است.';
            throw new RuntimeException('customer scope changed before charge');
        }
        $targetBalance = (int)($targetRow['Balance'] ?? 0);

        $debit = $pdo->prepare('UPDATE user SET Balance=Balance-? WHERE id=? AND Balance>=?');
        $debit->execute([$amt, (string)$from_id, $amt]);
        if ($debit->rowCount() !== 1) {
            $chargeError = 'موجودی نمایندگی کافی نیست.';
            throw new RuntimeException('conditional reseller debit failed');
        }
        $credit = $pdo->prepare('UPDATE user SET Balance=Balance+? WHERE id=?');
        $credit->execute([$amt, $targetUid]);
        if ($credit->rowCount() !== 1) {
            $chargeError = 'واریز به کاربر انجام نشد.';
            throw new RuntimeException('customer credit failed');
        }
        $pdo->prepare('INSERT INTO reseller_wallet_ledger(from_user_id,to_user_id,amount,reason,actor_reseller_id,created_at) VALUES(?,?,?,?,?,?)')
            ->execute([(string)$from_id, $targetUid, $amt, 'main_bot_customer_charge', (string)$from_id, time()]);
        $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
            ->execute([(string)$from_id, 'bot', 'customer.charge', $targetUid, json_encode(['amount' => $amt]), null, time()]);
        $pdo->commit();
        $newBal = (int)$targetBalance + $amt;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redfox_log_exception($e, 'reseller.customer.charge');
        sendmessage($from_id, "❌ " . ($chargeError !== '' ? $chargeError : 'انتقال اعتبار به‌دلیل خطای داخلی انجام نشد.'), $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ از کیف پول نمایندگی به کاربر <code>$targetUid</code> منتقل شد.\nمبلغ: " . number_format($amt) . " ت\nموجودی جدید کاربر: " . number_format($newBal) . " ت", $keyboard, 'HTML');
    @sendmessage($targetUid, "💰 کیف پول شما توسط نماینده به مبلغ " . number_format($amt) . " تومان شارژ شد.\nموجودی جدید: " . number_format($newBal) . " تومان", null, 'HTML');
} elseif (($datain == "super_search" || $text == "👥 جستجوی کاربر") && $rxResellerCan('manage_users')) {
    sendmessage($from_id, "🔍 آیدی عددی یا یوزرنیم کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_search_main', $from_id);
} elseif ($user['step'] == "super_search_main" && $text && $rxResellerCan('manage_users')) {
    $q = trim((string)$text);
    $rxTarget = $rxFindScopedUser($q, !ctype_digit($q));
    if (!$rxTarget) { sendmessage($from_id, "❌ کاربر در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    $rxMsg = "👤 <b>اطلاعات کاربر</b>\n\n🆔 آیدی: <code>{$rxTarget['id']}</code>\n👤 نام: " . htmlspecialchars((string)$rxTarget['username']) . "\n💰 موجودی: " . number_format((int)$rxTarget['Balance']) . " ت\n📊 وضعیت: " . htmlspecialchars((string)$rxTarget['User_Status']) . "\n🏷 نوع: " . htmlspecialchars((string)$rxTarget['agent']);
    sendmessage($from_id, $rxMsg, $keyboard, 'HTML');
    step('home', $from_id);
} elseif (($datain == "super_reports" || $text == "📊 گزارش فروش") && $rxResellerCan('reports')) {
    $rid = (string)$from_id;
    $done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
    try {
        $salesStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE refral = ? AND Status IN ($done)");
        $salesStmt->execute([$rid]);
        $sales = (int)$salesStmt->fetchColumn();
        $revStmt = $pdo->prepare("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ($done)");
        $revStmt->execute([$rid]);
        $rev = (int)$revStmt->fetchColumn();
        $custStmt = $pdo->prepare("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE refral = ?");
        $custStmt->execute([$rid]);
        $custs = (int)$custStmt->fetchColumn();
    } catch (Throwable $e) { $sales = 0; $rev = 0; $custs = 0; }
    $rxMsg = "📊 <b>گزارش فروش شما</b>\n\n🛍 فروش موفق: <b>$sales</b>\n💰 درآمد کل: <b>" . number_format($rev) . " ت</b>\n👥 مشتریان: <b>$custs</b>\n💎 موجودی شما: <b>" . number_format((int)$user['Balance']) . " ت</b>";
    sendmessage($from_id, $rxMsg, $keyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['users']['agenttext']['customnameusername'] || $datain == "selectname") {
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
    step('selectusernamecustom', $from_id);
} elseif ($user['step'] == "selectusernamecustom") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['submitusername'], $keyboardagent, 'html');
    update("user", "namecustom", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == $datatextbot['textrequestagent'] || $datain == "requestagent") {
    if ($user['Balance'] < $setting['agentreqprice']) {
        $priceagent = number_format($setting['agentreqprice']);
        sendmessage($from_id, sprintf($textbotlang['users']['agenttext']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
        return;
    }
    $existingAgentRequest = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($existingAgentRequest) {
        // فقط درخواستی که هنوز در حال بررسی (waiting) است مانع ثبت درخواست جدید می‌شود.
        if ($existingAgentRequest['status'] == "waiting") {
            sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], null, 'html');
            return;
        }
        // اگر درخواست قبلی رد شده بود، رکورد قدیمی پاک می‌شود تا کاربر بتواند مجدداً درخواست دهد.
        if ($existingAgentRequest['status'] == "reject") {
            $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id AND status = 'reject'");
            $stmtDelOld->execute([':id' => $from_id]);
        }
    }
    if ($user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['agenttext']['isagent'], null, 'html');
        return;
    }
    if ($datain == "requestagent") {
        Editmessagetext($from_id, $message_id, $datatextbot['text_request_agent_dec'], $backuser);
    } else {
        sendmessage($from_id, $datatextbot['text_request_agent_dec'], $backuser, 'html');
    }
    step("getagentrequest", $from_id);
} elseif ($user['step'] == "getagentrequest" && $text) {
    // اطمینان از نبود درخواست قدیمیِ رد/تاییدشده تا INSERT جدید با خطای کلید تکراری مواجه نشود.
    $oldReq = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($oldReq && $oldReq['status'] == "waiting") {
        // اگر همزمان درخواست در حال بررسی ثبت شده، از ثبت دوباره جلوگیری می‌کنیم.
        sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($oldReq) {
        $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id");
        $stmtDelOld->execute([':id' => $from_id]);
    }
    $balancelow = $user['Balance'] - $setting['agentreqprice'];
    update("user", "Balance", $balancelow, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['agenttext']['endrequest'], $keyboard, 'html');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
    $status = "waiting";
    $type = "None";
    $current_time = time();
    $description = $text;
    $requestAgentInserted = false;
    try {
        $stmt->execute([
            ':id' => $from_id,
            ':username' => $username,
            ':time' => $current_time,
            ':description' => $description,
            ':status' => $status,
            ':type' => $type,
        ]);
        $requestAgentInserted = true;
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1366) {
            $tableConverted = ensureTableUtf8mb4('Requestagent');
            if ($tableConverted) {
                try {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $description,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . redfox_exception_fingerprint($retryException));
                }
            }

            if (!$requestAgentInserted) {
                $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                if ($sanitisedDescription !== $description) {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $sanitisedDescription,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } else {
                    throw $e;
                }
            }
        } else {
            throw $e;
        }
    }

    if (!$requestAgentInserted) {
        throw new RuntimeException('Failed to persist agent request description.');
    }
    $textrequestagent = sprintf($textbotlang['users']['agenttext']['agent-request'], $from_id, $username, $first_name, $text);
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
    }
} elseif ($text == "/privacy") {
    sendmessage($from_id, $datatextbot['text_roll'], null, 'HTML');
} elseif ($text == $datatextbot['text_wheel_luck'] || $datain == "wheel_luck" || $text == "/gift") {
    if (!check_active_btn($setting['keyboardmain'], "text_wheel_luck")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['wheelagent'] == "0" and $user['agent'] != "f") {
        sendmessage($from_id, "❌ این دکمه برای شما غیرفعال می باشد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if (intval($setting['statusfirstwheel']) == 1 and $countinvoice != 0) {
        sendmessage($from_id, "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.", null, 'HTML');
        return;
    }
    if ($setting['wheelـluck'] == "0" or ($setting['wheelagent'] == "0" and $users['agent'] != "f")) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['feature-disabled'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if (intval($setting['Dice']) == 1) {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎲",
        ]);
        sleep(4.5);
    } else {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎰",
        ]);
        sleep(2);
    }
    if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
        $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
        error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['error'] ?? '❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.', null, 'HTML');
        return;
    }
    $diceValue = (int) $diceResponse['result']['dice']['value'];
    $dateacc = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    $status = false;
    if (intval($setting['Dice']) == 1) {
        if ($diceValue === 6) {
            $status = true;
        }
    } else {
        if (in_array($diceValue, [1, 43, 64, 22], true)) {
            $status = true;
        }
    }
    if ($status) {
        $balance_last = intval($setting['wheelـluck_price']) + $user['Balance'];
        update("user", "Balance", $balance_last, "id", $from_id);
        $price = number_format($setting['wheelـluck_price']);
        sendmessage($from_id, sprintf($textbotlang['users']['wheel_luck']['winner-congratulations'], $price), null, 'HTML');
        $pricelast = $setting['wheelـluck_price'];
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => sprintf($textbotlang['users']['wheel_luck']['wheel-winner'], $username, $from_id),
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['notWinner'], null, 'HTML');
        $pricelast = 0;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price) VALUES (:id_user,:first_name,:wheel_code,:time,:price)");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':wheel_code', $diceValue);
    $stmt->bindParam(':time', $dateacc);
    $stmt->bindParam(':price', $pricelast);
    $stmt->execute();
} elseif ($text == "/tron") {
    $rates = requireTronRates(['TRX']);
    if ($rates === null) {
        sendmessage($from_id, "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['TRX'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tron-price'], $price), null, 'HTML');
} elseif ($text == "/usd") {
    $rates = requireTronRates(['USD']);
    if ($rates === null) {
        sendmessage($from_id, "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['USD'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tether-price'], $price), null, 'HTML');
} elseif ($text == $datatextbot['text_extend'] or $datain == "extendbtn") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
        return;
    }
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $_start_index_i = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_start_index_i, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_si2 = (int)$start_index; $_p2 = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_si2, $_p2);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == 'previous_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $_pp = (int)$previous_page; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_pp, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == "linkappdownlod" || $text === "📱 نرم‌افزارهای اتصال") {
    $platforms=[];try{$platforms=$pdo->query("SELECT DISTINCT platform FROM app WHERE enabled=1 ORDER BY platform")->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable$e){}
    if(!$platforms){sendmessage($from_id,'❌ هنوز نرم‌افزار فعالی ثبت نشده است.',null,'HTML');return;}
    $kb=['inline_keyboard'=>[]];foreach($platforms as$platform){$token=rtrim(strtr(base64_encode((string)$platform),'+/','-_'),'=');$kb['inline_keyboard'][]=[['text'=>'💻 '.(string)$platform,'callback_data'=>'appplatform_'.$token]];}$kb['inline_keyboard'][]=[['text'=>$textbotlang['users']['backbtn'],'callback_data'=>'backuser']];sendmessage($from_id,'📱 سیستم‌عامل خود را انتخاب کنید:',json_encode($kb,JSON_UNESCAPED_UNICODE),'HTML');
} elseif (preg_match('/^appplatform_([A-Za-z0-9_-]+)$/',$datain,$appMatch)) {
    $raw=strtr($appMatch[1],'-_','+/');$raw.=str_repeat('=',(4-strlen($raw)%4)%4);$platform=base64_decode($raw,true);if(!is_string($platform)){return;}$q=$pdo->prepare('SELECT name,link FROM app WHERE enabled=1 AND platform=? ORDER BY name');$q->execute([$platform]);$apps=$q->fetchAll(PDO::FETCH_ASSOC);$kb=['inline_keyboard'=>[]];foreach($apps as$app)$kb['inline_keyboard'][]=[['text'=>$app['name'],'url'=>$app['link']]];$kb['inline_keyboard'][]=[['text'=>'بازگشت به سیستم‌عامل‌ها','callback_data'=>'linkappdownlod']];Editmessagetext($from_id,$message_id,'📥 نرم‌افزارهای '.htmlspecialchars($platform,ENT_QUOTES,'UTF-8'),json_encode($kb,JSON_UNESCAPED_UNICODE),'HTML');
} elseif (preg_match('/changenote_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $rxNoteCheck = select("invoice", "id_user", "id_invoice", $id_invoice, "select");
    if (!is_array($rxNoteCheck) || (string)($rxNoteCheck['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changenote on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changenote',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['SendNote'], $backinfoss);
    step("getnotedit", $from_id);
} elseif ($user['step'] == "getnotedit") {
    $invoice = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!is_array($invoice)) {
        if (function_exists('rx_log_event')) rx_log_event('INVOICE_OWNERSHIP_DENIED','getnotedit stopped before mutation',['invoice'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        return;
    }
    if (strlen($text) > 150) {
        sendmessage($from_id, $textbotlang['users']['note']['ErrorLongNote'], $keyboard, "html");
        return;
    }
    $text = sanitizeUserName($text);
    $id_invoice = $user['Processing_value'];
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    update("invoice", "note", $text, "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
    step("home", $from_id);
    $timejalali = jdate('Y/m/d H:i:s');
    $textreport = "📌  یک کاربر یادداشت سرویس خود را تغییر داد.

▫️ نام کاربری سرویس : {$invoice['username']}
▫️ یاداشت قبلی :‌ {$invoice['note']}
▫️ یاداشت جدید :‌  $text

زمان تغییر یادداشت : $timejalali ";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}

/* ---- finalize.php ---- */
if (isset($update['pre_checkout_query'])) {
    $pc = $update['pre_checkout_query'];
    $idOrder = trim((string)($pc['invoice_payload'] ?? ''));
    $payer = (string)($pc['from']['id'] ?? '');
    $currency = strtoupper(trim((string)($pc['currency'] ?? '')));
    $totalAmount = $pc['total_amount'] ?? null;
    $row = false;
    if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $idOrder)) {
        $q = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=? AND id_user=? AND Payment_Method='Star Telegram' AND provider_name='telegram_stars' AND provider_invoice_id=? LIMIT 2");
        $q->execute([$idOrder, $payer, $idOrder]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $duplicate = $q->fetch(PDO::FETCH_ASSOC);
        if (is_array($duplicate)) $row = false;
    }
    $ok = is_array($row)
        && strtolower((string)$row['payment_Status']) === 'unpaid'
        && $currency === 'XTR'
        && strtoupper((string)$row['provider_currency']) === 'XTR'
        && is_int($totalAmount)
        && $totalAmount > 0
        && (int)$row['provider_amount'] === $totalAmount;
    telegram('answerPreCheckoutQuery', [
        'pre_checkout_query_id'=>(string)($pc['id'] ?? ''),
        'ok'=>$ok,
        'error_message'=>$ok ? null : 'فاکتور، ارز یا مبلغ پرداخت نامعتبر است',
    ]);
    if ($ok) {
        $audit = json_encode($pc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $save = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed=? WHERE id=? AND payment_Status='Unpaid'");
        $save->execute([$audit, (int)$row['id']]);
    } elseif (function_exists('rx_log_event')) {
        rx_log_event('STARS_PRECHECKOUT_REJECTED', 'Telegram Stars pre-checkout binding failed', [
            'order_id'=>$idOrder, 'payer'=>$payer, 'currency'=>$currency,
        ]);
    }
} elseif (isset($update['message']['successful_payment'])) {
    $sp = $update['message']['successful_payment'];
    $idOrder = trim((string)($sp['invoice_payload'] ?? ''));
    $payer = (string)($update['message']['from']['id'] ?? '');
    $currency = strtoupper(trim((string)($sp['currency'] ?? '')));
    $totalAmount = $sp['total_amount'] ?? null;
    $telegramCharge = trim((string)($sp['telegram_payment_charge_id'] ?? ''));
    $providerCharge = trim((string)($sp['provider_payment_charge_id'] ?? ''));
    $validIdentifiers = preg_match('/^[A-Za-z0-9_-]{1,128}$/', $idOrder)
        && preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $telegramCharge)
        && ($providerCharge === '' || preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $providerCharge));
    $row = false;
    if ($validIdentifiers) {
        $q = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=? AND id_user=? AND Payment_Method='Star Telegram' AND provider_name='telegram_stars' AND provider_invoice_id=? LIMIT 2");
        $q->execute([$idOrder, $payer, $idOrder]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $duplicate = $q->fetch(PDO::FETCH_ASSOC);
        if (is_array($duplicate)) $row = false;
    }
    $bound = is_array($row)
        && in_array(strtolower((string)$row['payment_Status']), ['unpaid','paid'], true)
        && $currency === 'XTR'
        && strtoupper((string)$row['provider_currency']) === 'XTR'
        && is_int($totalAmount)
        && $totalAmount > 0
        && (int)$row['provider_amount'] === $totalAmount;
    if (!$bound) {
        if (function_exists('rx_log_event')) {
            rx_log_event('STARS_PAYMENT_REJECTED', 'Telegram Stars successful_payment binding failed', [
                'order_id'=>$idOrder, 'payer'=>$payer, 'currency'=>$currency,
            ]);
        }
        return;
    }
    try {
        $claim = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:charge,dec_not_confirmed=:audit WHERE id=:id AND provider_name='telegram_stars' AND provider_invoice_id=:invoice AND (provider_payment_id IS NULL OR provider_payment_id=:same_charge)");
        $claim->execute([
            ':charge'=>$telegramCharge,
            ':same_charge'=>$telegramCharge,
            ':audit'=>json_encode($sp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id'=>(int)$row['id'],
            ':invoice'=>$idOrder,
        ]);
        if ($claim->rowCount() !== 1 && !hash_equals((string)($row['provider_payment_id'] ?? ''), $telegramCharge)) {
            throw new RuntimeException('Telegram charge id already bound');
        }
    } catch (Throwable $e) {
        error_log('[telegram_stars] payment claim failed: ' . redfox_exception_fingerprint($e));
        return;
    }
    $extraLines = ['Telegram charge: '.htmlspecialchars($telegramCharge, ENT_QUOTES, 'UTF-8')];
    if ($providerCharge !== '') $extraLines[] = 'Provider charge: '.htmlspecialchars($providerCharge, ENT_QUOTES, 'UTF-8');
    $confirm = payment_confirm_paid($idOrder, 'chashbackstar', [
        'method'=>'Telegram Stars',
        'expected_method'=>'Star Telegram',
        'thread_id'=>$paymentreports,
        'extra_lines'=>$extraLines,
    ]);
    if (empty($confirm['ok'])) sendmessage($payer,'⚠️ پرداخت ثبت شد اما تکمیل سفارش نیازمند بررسی پشتیبانی است.',null,'HTML');
} elseif (preg_match('/extends_(\w+)_(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $invoiceIdForExtend = $dataget[2] ?? '';
    $nameloc = false;
    if ($invoiceIdForExtend !== '') {
        $nameloc = rxOwnedInvoice($pdo, $invoiceIdForExtend, $from_id);
        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extends on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $invoiceIdForExtend, 'handler' => 'extends',
                ]);
            }
            $nameloc = false;
        }
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $username, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if (function_exists('nmStockPanelForInvoice') && is_array($nameloc)) {
        $marzban_list_get = nmStockPanelForInvoice($nameloc);
    } else {
        $marzban_list_get = false;
    }
    if (!is_array($marzban_list_get)) {
        $panelRef = $user['Processing_value_four'] ?? '';
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $panelRef, "select");
        if (!is_array($marzban_list_get)) {
            $marzban_list_get = select("marzban_panel", "*", "code_panel", $panelRef, "select");
        }
    }
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید", null, 'html');
        return;
    }
    $location = $marzban_list_get['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    if (is_array($nameloc) && !empty($nameloc['id_invoice'])) {
        update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    }

    if (is_array($nameloc) && function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get) && function_exists('nmStockExtendProductKeyboard')) {
        $stockKeyboard = nmStockExtendProductKeyboard($nameloc, $user, $marzban_list_get);
        if (!$stockKeyboard) {
            Editmessagetext($from_id, $message_id, "❌ برای این سرویس موجودی انبار قابل تمدید وجود ندارد.", json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE), 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, "📦 وضعیت نت ملی فعال است؛ محصول انباری تمدید را انتخاب کنید:", $stockKeyboard, 'HTML');
        return;
    }

    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
    $queryParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $customVolumeData = json_decode($marzban_list_get['customvolume'] ?? '{}', true);
    if (!is_array($customVolumeData)) $customVolumeData = [];
    $statuscustomvolume = $customVolumeData[$user['agent']] ?? '0';
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $codeproduct = $dataget[1];
    $username = $dataget[2];
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend preview failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
            ]
        ]
    ]);
    sendmessage($from_id, sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcut['Volume_constraint'], $prodcut['note'], $user['Balance']), $keyboardextend, 'html');
} elseif (preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget)) {
    $codeproduct = $dataget[1];
    $usernamePanelExtends = $dataget[2];
    deletemessage($from_id, $message_id);
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend confirmation failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $nameloc = false;
    if (!empty($user['Processing_value_one'])) {
        $nameloc = rxOwnedInvoice($pdo, $user['Processing_value_one'], $from_id);
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $usernamePanelExtends, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if ($user['Balance'] < $prodcut['price_product'] && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $prodcut['price_product'] - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    // ── Red Fox: نماینده‌ها اجازه‌ی منفی‌شدن کیف پول ندارند (کف صفر) ──
    // مدل پیشین به نماینده‌ی پیشرفته (n2) اجازه می‌داد تا سقف maxbuyagent بدهی
    // منفی شود. طبق درخواست مالک، اعتبار نمایندگی نباید منفی شود؛ اگر فروش
    // کانفیگ موجودی را زیر صفر ببرد، فروش متوقف و به نماینده (برای شارژ) و
    // به ادمین (اطلاع‌رسانی) پیام داده می‌شود. (نماینده‌ی عادی n از قبل از طریق
    // مسیر پرداخت هدایت می‌شود و منفی نمی‌شود، لذا این گارد فقط روی n2 اثر دارد.)
    if (!function_exists('redfox_notify_reseller_low_balance')) {
        function redfox_notify_reseller_low_balance($from_id, $price, $balance) {
            global $pdo;
            try {
                $chatId = trim((string)$from_id);
                // پیام شارژ به نماینده
                if (function_exists('sendmessage')) {
                    sendmessage($chatId, "⚠️ موجودی کیف پول/اعتبار نمایندگی شما برای این فروش کافی نیست.\n\n💰 مبلغ لازم: " . number_format((int)$price) . " تومان\n💎 موجودی فعلی شما: " . number_format((int)$balance) . " تومان\n\n💳 لطفاً کیف پول خود را شارژ کنید و دوباره تلاش کنید.", null, 'HTML');
                }
                // پیام اطلاع به ادمین‌ها
                $admins = [];
                if ($pdo instanceof PDO) {
                    $st = $pdo->prepare("SELECT id_admin FROM admin WHERE rule = 'administrator'");
                    $st->execute();
                    $admins = $st->fetchAll(PDO::FETCH_COLUMN, 0);
                }
                $txt = "🔁 تلاش ناموفق فروش توسط نماینده\n\n🪪 آیدی نماینده: {$chatId}\n💰 مبلغ فروش: " . number_format((int)$price) . " تومان\n💎 موجودی فعلی: " . number_format((int)$balance) . " تومان\n\n📌 این نماینده موجودی کافی نداشت و فروش متوقف شد؛ به شارژ کیف پول نیاز دارد.";
                foreach ($admins as $aid) {
                    $aid = trim((string)$aid);
                    if ($aid === '' || !ctype_digit($aid)) {
                        continue;
                    }
                    if (function_exists('sendmessage')) {
                        sendmessage($aid, $txt, null, 'HTML');
                    }
                }
            } catch (Throwable $e) {
                error_log('[redfox] notify_reseller_low_balance failed: ' . redfox_exception_fingerprint($e));
            }
        }
    }
    if ($user['agent'] === 'n2') {
        if (((float)$user['Balance'] - (float)$prodcut['price_product']) < 0) {
            redfox_notify_reseller_low_balance($from_id, $prodcut['price_product'], $user['Balance']);
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
        $prodcut['price_product'] = $prodcut['price_product'] - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (!is_array($nameloc)) {
            sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
            return;
        }
        $stockNew = function_exists('nmStockReserveForProduct') ? nmStockReserveForProduct($marzban_list_get, $prodcut, $from_id, $nameloc['id_invoice'], 'normal_extend_national_stock') : false;
        if (!$stockNew) {
            sendmessage($from_id, "❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.", $keyboard, 'HTML');
            return;
        }
        $__pp = (float)$prodcut['price_product'];
        // Red Fox: نماینده‌ها منفی نمی‌شوند (کف صفر) — گارد بالاتر موجودی کافی را تضمین می‌کند.
        $__allowNeg = 0;
        $__charge = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp, $__allowNeg) : ['ok' => false, 'reason' => 'helper-missing'];
        if (empty($__charge['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
            return;
        }
        $Balance_Low_user = $__charge['new_balance'];
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        $invoiceNew = array_merge($nameloc, [
            'name_product' => $prodcut['name_product'],
            'price_product' => $prodcut['price_product'],
            'Volume' => $prodcut['Volume_constraint'],
            'Service_time' => $prodcut['Service_time'],
            'time_sell' => time(),
            'user_info' => $stockNew['content'],
            'source_panel_code' => $marzban_list_get['code_panel'],
        ]);
        nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
        sendmessage($from_id, "✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.", $keyboard, 'HTML');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $__pp2 = (float)$prodcut['price_product'];
    // Red Fox: نماینده‌ها منفی نمی‌شوند (کف صفر) — گارد بالاتر موجودی کافی را تضمین می‌کند.
    $__allowNeg2 = 0;
    $__charge2 = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp2, $__allowNeg2) : ['ok' => false, 'reason' => 'helper-missing'];
    if (empty($__charge2['ok'])) {
        sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
        return;
    }
    $Balance_Low_user = $__charge2['new_balance'];
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel']);
    if (empty($extend['status']) && function_exists('balance_atomic_credit')) {
        balance_atomic_credit($from_id, $__pp2);
        $Balance_Low_user = $user['Balance'];
    }
    if ($extend['status'] == false) {
        $fallbackInvoice = is_array($nameloc) ? $nameloc : ['id_invoice' => '', 'id_user' => $from_id, 'username' => $usernamePanelExtends, 'Service_location' => $marzban_list_get['name_panel'], 'name_product' => $prodcut['name_product'], 'Volume' => $prodcut['Volume_constraint'], 'Service_time' => $prodcut['Service_time']];
        if (nmStockCompleteExtendFallback($from_id, $user, array_merge($fallbackInvoice, ['username' => $usernamePanelExtends]), $prodcut, 0, 'paid_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = redfox_remote_error_summary($extend);
        $textreports = "خطای تمدید سرویس
        نام پنل : {$marzban_list_get['name_panel']}
        نام کاربری سرویس : $usernamePanelExtends
        دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (is_array($nameloc)) {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "user_info", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        try { update("invoice", "source_panel_code", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
    ));
    $dateacc = date('Y/m/d H:i:s');
    $type = "extends_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $usernamePanelExtends,
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $prodcut['price_product'],
        ':output' => json_encode(['status' => true], JSON_UNESCAPED_SLASHES)
    ]);
    $prodcut['price_product'] = number_format($prodcut['price_product']);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamePanelExtends
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید {$prodcut['price_product']} تومان
";
    sendmessage($from_id, $textextend, $keyboard, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewaldetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
}
if (in_array($from_id, $admin_ids))
    require_once 'admin.php';

$pdo = null;
$connect->close();