<?php
/** Generated from manifest.php at release build time. Do not edit directly. */

/* ---- layouts_1.php ---- */
require_once 'config.php';

if (!isset($from_id))           { $from_id = 0; }
if (!isset($datain))            { $datain = ''; }
if (!isset($text))              { $text = ''; }
if (!isset($message_id))        { $message_id = 0; }
if (!isset($callback_query_id)) { $callback_query_id = ''; }
if (!isset($username))          { $username = ''; }
if (!isset($first_name))        { $first_name = ''; }

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('DB_UNAVAILABLE', 'Keyboard layouts loaded with no PDO; skipping DB-dependent setup.', [
            'where' => 'layouts_1',
        ]);
    }
    return;
}

$setting = select("setting", "*", null, null,"select");
$textbotlang = languagechange(REFACTORED_LEGACY_ROOT.'/text.json');
if (!is_array($textbotlang)) {
    $textbotlang = [];
}
if (!isset($textbotlang['Admin']) || !is_array($textbotlang['Admin'])) {
    $textbotlang['Admin'] = [];
}
$textbotlang['Admin']['backadmin'] = $textbotlang['Admin']['backadmin'] ?? "🏠 بازگشت به منوی مدیریت";
$textbotlang['Admin']['backmenu'] = $textbotlang['Admin']['backmenu'] ?? "▶️ بازگشت به منوی قبل";
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}

if (!function_exists('rx_inlineButtonMapFile')) {
    function rx_inlineButtonMapFile() {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/').'/rx_inline_button_map.json';
    }
}
if (!function_exists('rx_storeInlineButtonText')) {
    function rx_storeInlineButtonText($text) {
        $code = 'rxb_' . substr(hash('sha256', (string)$text), 0, 24);
        $file = rx_inlineButtonMapFile();
        $map = [];
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            if (is_array($decoded)) $map = $decoded;
        }
        $map[$code] = (string)$text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveInlineButtonText')) {
    function rx_resolveInlineButtonText($code) {
        if (!is_string($code) || strpos($code, 'rxb_') !== 0) return null;
        $file = rx_inlineButtonMapFile();
        if (!is_file($file)) return null;
        $map = json_decode((string)@file_get_contents($file), true);
        return is_array($map) && isset($map[$code]) ? (string)$map[$code] : null;
    }
}
if (!function_exists('rx_safeCallbackData')) {
    function rx_safeCallbackData($callbackData, $buttonText = '') {
        $callbackData = (string)$callbackData;
        if ($callbackData === '') $callbackData = (string)$buttonText;
        return strlen($callbackData) <= 64 ? $callbackData : rx_storeInlineButtonText($buttonText !== '' ? $buttonText : $callbackData);
    }
}

if (!function_exists('rx_keyboardToInline')) {
    function rx_keyboardToInline($keyboardArray) {
        if (!is_array($keyboardArray)) return json_encode($keyboardArray);
        if (isset($keyboardArray['inline_keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!isset($keyboardArray['keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $inline = ['inline_keyboard' => []];
        foreach ($keyboardArray['keyboard'] as $row) {
            $newRow = [];
            foreach ($row as $btn) {
                if (isset($btn['text'])) {
                    $buttonText = (string) $btn['text'];
                    $callbackData = isset($btn['callback_data']) ? (string)$btn['callback_data'] : $buttonText;
                    if ($buttonText === ($GLOBALS['textbotlang']['users']['backbtn'] ?? '')) {
                        $callbackData = 'backuser';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backadmin'] ?? '')) {
                        $callbackData = 'admin';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backmenu'] ?? '')) {
                        $callbackData = 'backmenu';
                    }
                    $newRow[] = ['text' => $buttonText, 'callback_data' => rx_safeCallbackData($callbackData, $buttonText)];
                }
            }
            if (!empty($newRow)) $inline['inline_keyboard'][] = $newRow;
        }
        return json_encode($inline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'accountwallet' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',
    'text_affiliates' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'aqayepardakht' => '',
    'zarinpey' => '',
    'zarinpal' => '',
    'text_fq' => '',
    'textpaymentnotverify' =>"",
    'textrequestagent' => '',
    'textpanelagent' => '👨‍💻 پنل نمایندگی',
    'text_wheel_luck' => '',
    'text_star_telegram' => "",
    'text_extend' => '',
    'textsnowpayment' => ''

);
if ($table_exists) {
    $textdatabot =  select("textbot", "*", null, null,"fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
$adminrulecheck = select("admin", "*", "id_admin", $from_id,"select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
$users = select("user", "*", "id", $from_id,"select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
        'agent' => '',
        'limit_usertest' => '',
        'Processing_value' => '',
        'Processing_value_four' => '',
        'cardpayment' => ""
    );
}
$replacements = [
    'text_usertest' => $datatextbot['text_usertest'],
    'text_Purchased_services' => $datatextbot['text_Purchased_services'],
    'text_support' => $datatextbot['text_support'],
    'text_help' => $datatextbot['text_help'],
    'accountwallet' => $datatextbot['accountwallet'],
    'text_sell' => $datatextbot['text_sell'],
    'text_Tariff_list' => $datatextbot['text_Tariff_list'],
    'text_affiliates' => $datatextbot['text_affiliates'],
    'text_wheel_luck' => $datatextbot['text_wheel_luck'],
    'text_extend' => $datatextbot['text_extend']
];
$admin_idss = select("admin", "*", "id_admin", $from_id,"count");
$temp_addtional_key = [];
$keyboardLayout = json_decode($setting['keyboardmain'], true);
$keyboardRows = [];
if (is_array($keyboardLayout) && isset($keyboardLayout['keyboard']) && is_array($keyboardLayout['keyboard'])) {
    $keyboardRows = $keyboardLayout['keyboard'];
}
$filteredRows=[];foreach($keyboardRows as$row){$nr=[];foreach((array)$row as$btn){$bt=(string)($btn['text']??'');if($bt==='text_help'||$bt===(string)($datatextbot['text_help']??'')||trim($bt)==='آموزش')continue;$nr[]=$btn;}if($nr)$filteredRows[]=$nr;}$keyboardRows=$filteredRows;
$hasApps=$hasHelp=false;foreach($keyboardRows as$row)foreach((array)$row as$btn){$t=(string)($btn['text']??'');if($t==='📱 نرم‌افزارهای اتصال')$hasApps=true;if($t==='📚 آموزش اتصال')$hasHelp=true;}if(!$hasHelp)$keyboardRows[]=[['text'=>'📚 آموزش اتصال']];if(!$hasApps)$keyboardRows[]=[['text'=>'📱 نرم‌افزارهای اتصال']];

if (!empty($keyboardRows) && function_exists('rx_usertest_panel_active') && !rx_usertest_panel_active()) {
    $rxFilteredRows = [];
    foreach ($keyboardRows as $rxRow) {
        if (!is_array($rxRow)) {
            continue;
        }
        $rxNewRow = [];
        foreach ($rxRow as $rxBtn) {
            if (is_array($rxBtn) && isset($rxBtn['text']) && $rxBtn['text'] === 'text_usertest') {
                continue;
            }
            $rxNewRow[] = $rxBtn;
        }
        if (!empty($rxNewRow)) {
            $rxFilteredRows[] = $rxNewRow;
        }
    }
    $keyboardRows = $rxFilteredRows;
}

if ($setting['inlinebtnmain'] == "oninline" && !empty($keyboardRows)) {
    $trace_keyboard = $keyboardRows;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == "text_sell") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
            }
            if ($keyboard['text'] == "accountwallet") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "account";
            }
            if ($keyboard['text'] == "text_Tariff_list") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
            }
            if ($keyboard['text'] == "text_wheel_luck") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
            }
            if ($keyboard['text'] == "text_affiliates") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
            }
            if ($keyboard['text'] == "text_extend") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
            }
            if ($keyboard['text'] == "text_support") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
            }
            if ($keyboard['text'] == "text_Purchased_services") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "backorder";
            }
            if ($keyboard['text'] == "text_help") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpbtns";
            }
            if ($keyboard['text'] == "text_usertest") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
            }
        }
    }
    if ($admin_idss != 0) {
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin, 'callback_data' => "admin"];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot["textpanelagent"] ?? "");
        if (trim($rx_lbl_agent) === "") $rx_lbl_agent = "👨‍💻 پنل نمایندگی";
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent, 'callback_data' => "agentpanel"];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req, 'callback_data' => "requestagent"];
        }
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboardcustom = $trace_keyboard;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['inline_keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard))
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
} else {
    if ($admin_idss != 0) {
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot["textpanelagent"] ?? "");
        if (trim($rx_lbl_agent) === "") $rx_lbl_agent = "👨‍💻 پنل نمایندگی";
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req];
        }
    }
    $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
    $keyboardcustom = $keyboardRows;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard))
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
}

$_rx_acc_styles  = [];
$_rx_pay_styles  = [];
$_rx_payrcpt_styles = [];
$_rx_adm_styles  = [];
$_rx_set_styles  = [];
$_rx_shp_styles  = [];
$_rx_nav_styles  = [];
$_rx_rol_styles  = [];
$_rx_gw_styles   = [];
$_rx_svc_styles  = [];
$_rx_feat_styles  = [];
$_rx_chan_styles  = [];
$_rx_helpa_styles = [];
$_rx_cat_styles   = [];
$_rx_prod_styles  = [];
$_rx_pedit_styles = [];
if (!empty($setting['keyboard_styles_all'])) {
    $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
    if (is_array($_rx_all_kbs)) {
        if (!empty($_rx_all_kbs['account']))        $_rx_acc_styles = $_rx_all_kbs['account'];
        if (!empty($_rx_all_kbs['payment']))        $_rx_pay_styles = $_rx_all_kbs['payment'];
        if (!empty($_rx_all_kbs['pay_receipt']))    $_rx_payrcpt_styles = $_rx_all_kbs['pay_receipt'];
        if (!empty($_rx_all_kbs['admin_main']))     $_rx_adm_styles = $_rx_all_kbs['admin_main'];
        if (!empty($_rx_all_kbs['admin_settings'])) $_rx_set_styles = $_rx_all_kbs['admin_settings'];
        if (!empty($_rx_all_kbs['admin_shop']))     $_rx_shp_styles = $_rx_all_kbs['admin_shop'];
        if (!empty($_rx_all_kbs['user_nav']))       $_rx_nav_styles = $_rx_all_kbs['user_nav'];
        if (!empty($_rx_all_kbs['admin_roles']))    $_rx_rol_styles = $_rx_all_kbs['admin_roles'];
        if (!empty($_rx_all_kbs['admin_gateways'])) $_rx_gw_styles  = $_rx_all_kbs['admin_gateways'];
        if (!empty($_rx_all_kbs['service']))        $_rx_svc_styles = $_rx_all_kbs['service'];
        if (!empty($_rx_all_kbs['admin_features']))   $_rx_feat_styles  = $_rx_all_kbs['admin_features'];
        if (!empty($_rx_all_kbs['admin_channel']))    $_rx_chan_styles  = $_rx_all_kbs['admin_channel'];
        if (!empty($_rx_all_kbs['admin_help']))       $_rx_helpa_styles = $_rx_all_kbs['admin_help'];
        if (!empty($_rx_all_kbs['admin_category']))   $_rx_cat_styles   = $_rx_all_kbs['admin_category'];
        if (!empty($_rx_all_kbs['admin_products']))   $_rx_prod_styles  = $_rx_all_kbs['admin_products'];
        if (!empty($_rx_all_kbs['admin_product_edit'])) $_rx_pedit_styles = $_rx_all_kbs['admin_product_edit'];
    }
}

if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
    $_rx_adm_styles   = $_rx_adm_styles   + rx_getKeyboardDefaultStyles('admin_main');
    $_rx_set_styles   = $_rx_set_styles   + rx_getKeyboardDefaultStyles('admin_settings');
    $_rx_shp_styles   = $_rx_shp_styles   + rx_getKeyboardDefaultStyles('admin_shop');
    $_rx_rol_styles   = $_rx_rol_styles   + rx_getKeyboardDefaultStyles('admin_roles');
    $_rx_gw_styles    = $_rx_gw_styles    + rx_getKeyboardDefaultStyles('admin_gateways');
    $_rx_feat_styles  = $_rx_feat_styles  + rx_getKeyboardDefaultStyles('admin_features');
    $_rx_chan_styles  = $_rx_chan_styles  + rx_getKeyboardDefaultStyles('admin_channel');
    $_rx_helpa_styles = $_rx_helpa_styles + rx_getKeyboardDefaultStyles('admin_help');
    $_rx_cat_styles   = $_rx_cat_styles   + rx_getKeyboardDefaultStyles('admin_category');
    $_rx_prod_styles  = $_rx_prod_styles  + rx_getKeyboardDefaultStyles('admin_products');
    $_rx_acc_styles   = $_rx_acc_styles   + rx_getKeyboardDefaultStyles('account');
    $_rx_pay_styles   = $_rx_pay_styles   + rx_getKeyboardDefaultStyles('payment');
    $_rx_nav_styles   = $_rx_nav_styles   + rx_getKeyboardDefaultStyles('user_nav');
}
if (!function_exists('rx_kb_style')) {
    function rx_kb_style(array $btn, string $key, array $styles): array {
        if (!empty($styles[$key]) && $styles[$key] !== 'default') {
            $btn['style'] = $styles[$key];
            return $btn;
        }

        if (!isset($btn['style']) && function_exists('rx_kb_guess_style_from_text') && isset($btn['text'])
            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
            if ($rxGuessed !== null) {
                $btn['style'] = $rxGuessed;
            } else {

                $btn['style'] = 'primary';
            }
        }
        return $btn;
    }
}
if (!function_exists('rx_kb_encode')) {

    function rx_kb_encode(array $rows, $forceInline = null): string {
        global $setting;
        $useInline = ($forceInline !== null)
            ? (bool)$forceInline
            : (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
        if ($useInline) {
            return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['keyboard' => $rows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    }
}
$_rx_acc_d  = rx_kb_style(['text' => $datatextbot['text_Discount'],    'callback_data' => "Discount"],    'Discount',    $_rx_acc_styles);
$_rx_acc_b  = rx_kb_style(['text' => $datatextbot['text_Add_Balance'], 'callback_data' => "Add_Balance"], 'Add_Balance', $_rx_acc_styles);
$_rx_acc_rc = rx_kb_style(['text' => '🔁 بررسی مجدد هش کریپتو',         'callback_data' => "recheckcrypto"], 'recheckcrypto', $_rx_acc_styles);
$_rx_acc_k  = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],    'backuser',    $_rx_acc_styles);
$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [$_rx_acc_d, $_rx_acc_b],
        [$_rx_acc_rc],
        [$_rx_acc_k],
    ],
    'resize_keyboard' => true
]);
if($adminrulecheck['rule'] == "administrator"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'admin_status'], 'admin_status', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'], 'callback_data' => 'admin_managepanel'], 'admin_managepanel', $_rx_adm_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'], 'callback_data' => 'admin_addpanel'], 'admin_addpanel', $_rx_adm_styles)
        ],
        [
            rx_kb_style(['text' => "⏳ تنظیم سریع قیمت زمان", 'callback_data' => 'admin_timeprice'], 'admin_timeprice', $_rx_adm_styles),
            rx_kb_style(['text' => "🔋 تنظیم سریع قیمت حجم", 'callback_data' => 'admin_volprice'], 'admin_volprice', $_rx_adm_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser'], 'callback_data' => 'admin_users'], 'admin_users', $_rx_adm_styles),
            rx_kb_style(['text' => "🏬 تنظیمات فروشگاه", 'callback_data' => 'admin_shop'], 'admin_shop', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "💎 مالی", 'callback_data' => 'admin_finance'], 'admin_finance', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🤙 بخش پشتیبانی", 'callback_data' => 'admin_support'], 'admin_support', $_rx_adm_styles),
            rx_kb_style(['text' => "📚 بخش آموزش", 'callback_data' => 'admin_help'], 'admin_help', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "🛠 قابلیت های پنل", 'callback_data' => 'admin_features'], 'admin_features', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "⚙️ تنظیمات عمومی", 'callback_data' => 'admin_settings'], 'admin_settings', $_rx_adm_styles),
            rx_kb_style(['text' => "💵 رسید های تایید نشده", 'callback_data' => 'admin_invoices'], 'admin_invoices', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'admin_back'], 'admin_back', $_rx_adm_styles)]
    ]);
}
if($adminrulecheck['rule'] == "Seller"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'seller_status'], 'seller_status', $_rx_rol_styles)],
        [rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'seller_users'], 'seller_users', $_rx_rol_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'seller_back'], 'seller_back', $_rx_rol_styles)]
    ]);
}
if($adminrulecheck['rule'] == "support"){
$keyboardadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'support_users'], 'support_users', $_rx_rol_styles),
            rx_kb_style(['text' => "👁‍🗨 جستجو کاربر", 'callback_data' => 'support_search'], 'support_search', $_rx_rol_styles)
        ],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'support_back'], 'support_back', $_rx_rol_styles)]
    ]);
}
$CartManage = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه کارت به کارت", 'callback_data' => 'cart_title'], 'cart_title', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "💳 تنظیم شماره کارت", 'callback_data' => 'cart_setnum'], 'cart_setnum', $_rx_gw_styles),
            rx_kb_style(['text' => "❌ حذف شماره کارت", 'callback_data' => 'cart_delnum'], 'cart_delnum', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "👤 آیدی پشتیبانی", 'callback_data' => 'cart_support'], 'cart_support', $_rx_gw_styles),
            rx_kb_style(['text' => "💳 درگاه آفلاین در پیوی", 'callback_data' => 'cart_pvmode'], 'cart_pvmode', $_rx_gw_styles)
        ],
        [['text' => "💰  غیرفعالسازی  نمایش شماره کارت", 'callback_data' => 'cart_hide_num'],['text' => "💰 فعالسازی نمایش شماره کارت", 'callback_data' => 'cart_show_num']],
        [['text' => "♻️ نمایش گروهی شماره کارت", 'callback_data' => 'cart_group_num']],
        [['text' => "📄 خروجی افراد شماره کارت فعال", 'callback_data' => 'cart_export_num']],
        [
            rx_kb_style(['text' => "♻️ تایید خودکار رسید", 'callback_data' => 'cart_autoconfirm'], 'cart_autoconfirm', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک کارت به کارت", 'callback_data' => 'cart_cashback'], 'cart_cashback', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "🔒 نمایش کارت به کارت پس از اولین پرداخت", 'callback_data' => 'cart_firstpay'], 'cart_firstpay', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ کارت به کارت", 'callback_data' => 'cart_min'], 'cart_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ کارت به کارت", 'callback_data' => 'cart_max'], 'cart_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش کارت به کارت", 'callback_data' => 'cart_edu'], 'cart_edu', $_rx_gw_styles)],
        [['text' => "🤖 تایید رسید  بدون بررسی", 'callback_data' => 'cart_autocheck']],
        [['text' => "💳 استثناء کردن کاربر از تایید خودکار", 'callback_data' => 'cart_except_user']],
        [['text' => "⏳ زمان تایید خودکار بدون بررسی", 'callback_data' => 'cart_autotime']],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'], 'cart_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$trnado = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ترنادو", 'callback_data' => 'trnado_name'], 'trnado_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key ترنادو", 'callback_data' => 'trnado_apikey'], 'trnado_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💼 ثبت آدرس ولت ترون (TRC20)", 'callback_data' => 'trnado_wallet'], 'trnado_wallet', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🌐 ثبت آدرس API ترنادو", 'callback_data' => 'trnado_apiurl'], 'trnado_apiurl', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک ارزی ریالی دوم", 'callback_data' => 'trnado_cashback'], 'trnado_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ ارزی ریالی دوم", 'callback_data' => 'trnado_min'], 'trnado_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ ارزی ریالی دوم", 'callback_data' => 'trnado_max'], 'trnado_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش ارزی ریالی  دوم", 'callback_data' => 'trnado_edu'], 'trnado_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'], 'trnado_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$keyboardzarinpal = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 نام درگاه زرین پال", 'callback_data' => 'zpal_name'], 'zpal_name', $_rx_gw_styles),
            rx_kb_style(['text' => "مرچنت زرین پال", 'callback_data' => 'zpal_merchant'], 'zpal_merchant', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش بک زرین پال", 'callback_data' => 'zpal_cashback'], 'zpal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ زرین پال", 'callback_data' => 'zpal_min'], 'zpal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ زرین پال", 'callback_data' => 'zpal_max'], 'zpal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش زرین پال", 'callback_data' => 'zpal_edu'], 'zpal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpal_back'], 'zpal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$keyboardzarinpey = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 نام درگاه زرین پی", 'callback_data' => 'zpey_name'], 'zpey_name', $_rx_gw_styles),
            rx_kb_style(['text' => "🔑 توکن زرین پی", 'callback_data' => 'zpey_token'], 'zpey_token', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش بک زرین پی", 'callback_data' => 'zpey_cashback'], 'zpey_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🧑🏼‍💻 اموزش اتصال", 'callback_data' => 'zpey_tutorial'], 'zpey_tutorial', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ زرین پی", 'callback_data' => 'zpey_min'], 'zpey_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ زرین پی", 'callback_data' => 'zpey_max'], 'zpey_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش زرین پی", 'callback_data' => 'zpey_edu'], 'zpey_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpey_back'], 'zpey_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$aqayepardakht = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه آقای پرداخت", 'callback_data' => 'aqaye_name'], 'aqaye_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "تنظیم مرچنت آقای پرداخت", 'callback_data' => 'aqaye_merchant'], 'aqaye_merchant', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک آقای پرداخت", 'callback_data' => 'aqaye_cashback'], 'aqaye_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ آقای پرداخت", 'callback_data' => 'aqaye_min'], 'aqaye_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ آقای پرداخت", 'callback_data' => 'aqaye_max'], 'aqaye_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش درگاه اقای پرداخت", 'callback_data' => 'aqaye_edu'], 'aqaye_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'aqaye_back'], 'aqaye_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$NowPaymentsManage = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه   plisio", 'callback_data' => 'plisio_name'], 'plisio_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "🧩 api plisio", 'callback_data' => 'plisio_api'], 'plisio_api', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک plisio", 'callback_data' => 'plisio_cashback'], 'plisio_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ plisio", 'callback_data' => 'plisio_min'], 'plisio_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ plisio", 'callback_data' => 'plisio_max'], 'plisio_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش plisio", 'callback_data' => 'plisio_edu'], 'plisio_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'plisio_back'], 'plisio_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$mainAdminId = isset($adminnumber) ? trim((string) $adminnumber) : '';
$currentUserId = isset($from_id) ? trim((string) $from_id) : '';

$settingPanelRows = [
    [rx_kb_style(['text' => "⚙️ وضعیت قابلیت ها", 'callback_data' => 'set_features'], 'set_features', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "📣 گزارشات ربات", 'callback_data' => 'set_reports'], 'set_reports', $_rx_set_styles),
        rx_kb_style(['text' => "📯 تنظیمات کانال", 'callback_data' => 'set_channel'], 'set_channel', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "✅ فعالسازی پنل تحت وب", 'callback_data' => 'set_webpanel'], 'set_webpanel', $_rx_set_styles)],
    [rx_kb_style(['text' => "🗑 بهینه سازی ربات", 'callback_data' => 'set_optimize'], 'set_optimize', $_rx_set_styles)],
];

$settingPanelRows = array_merge($settingPanelRows, [
    [
        rx_kb_style(['text' => "📝 تنظیم متن ربات", 'callback_data' => 'set_text'], 'set_text', $_rx_set_styles),
        rx_kb_style(['text' => "👨‍🔧 بخش ادمین", 'callback_data' => 'set_adminmgr'], 'set_adminmgr', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "➕ محدودیت ساخت اکانت تست برای همه", 'callback_data' => 'set_testlimit'], 'set_testlimit', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "💰 مبلغ عضویت نمایندگی", 'callback_data' => 'set_agentprice'], 'set_agentprice', $_rx_set_styles),
        rx_kb_style(['text' => "🖼 پس زمینه کیوآرکد", 'callback_data' => 'set_qrbg'], 'set_qrbg', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "🔗 وبهوک مجدد ربات های نماینده", 'callback_data' => 'set_webhook'], 'set_webhook', $_rx_set_styles)],
    [
        rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'set_backadmin'], 'set_backadmin', $_rx_set_styles),
        rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'set_backmenu'], 'set_backmenu', $_rx_set_styles)
    ],
]);

$setting_panel = rx_kb_encode($settingPanelRows);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingaqayepardakht = getPaySettingValue("statusaqayepardakht");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$Swapino = getPaySettingValue("statusSwapWallet");
$trnadoo = getPaySettingValue("statustarnado");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$zarinpey = getPaySettingValue("zarinpeystatus");
$affilnecurrency = getPaySettingValue("digistatus");
$arzireyali3 = getPaySettingValue("statusiranpay3");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
    ];
   if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
        if($PaySettingpv == "oncardpv"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'], 'url' => "https://t.me/$usernamecart"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }else{
            $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'], 'callback_data' => "cart_to_offline"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }
    }
    if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textnowpayment'], 'callback_data' => "plisio"], 'plisio', $_rx_pay_styles)
    ];
    }
    if($payment_status_nowpayment == "1"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textsnowpayment'], 'callback_data' => "nowpayment"], 'nowpayment', $_rx_pay_styles)
    ];
    }
   if($affilnecurrency == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textnowpaymenttron'], 'callback_data' => "digitaltron"], 'digitaltron', $_rx_pay_styles)
    ];
    }
   if($Swapino == "onSwapinoBot"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay2'], 'callback_data' => "iranpay1"], 'iranpay1', $_rx_pay_styles)
    ];
    }
   if($trnadoo == "onternado"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay3'], 'callback_data' => "iranpay2"], 'iranpay2', $_rx_pay_styles)
    ];
    }
     if($arzireyali3 == "oniranpay3"  && $paymentexits >= 2){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay1'], 'callback_data' => "iranpay3"], 'iranpay3', $_rx_pay_styles)
    ];
    }
   if($PaySettingaqayepardakht == "onaqayepardakht"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['aqayepardakht'], 'callback_data' => "aqayepardakht"], 'aqayepardakht', $_rx_pay_styles)
    ];
    }
    if($zarinpal == "onzarinpal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['zarinpal'], 'callback_data' => "zarinpal"], 'zarinpal', $_rx_pay_styles)
    ];
    }
    if($zarinpey == "onzarinpey"){
        $zarinpeyLabel = trim($datatextbot['zarinpey'] ?? '');
        if($zarinpeyLabel === ''){
            $zarinpeyLabel = '🟠 زرین پی';
        }
        if($zarinpeyLabel !== ''){
            $step_payment['inline_keyboard'][] = [
                rx_kb_style(['text' => $zarinpeyLabel, 'callback_data' => "zarinpey"], 'zarinpey', $_rx_pay_styles)
        ];
        }
    }
    if($paymentstatussnotverify == "onverifypay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textpaymentnotverify'], 'callback_data' => "paymentnotverify"], 'paymentnotverify', $_rx_pay_styles)
    ];
    }
    if(intval($paymentsstartelegram) == 1){
     $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['text_star_telegram'], 'callback_data' => "startelegrams"], 'startelegrams', $_rx_pay_styles)
    ];
    }
/* ---- layouts_2.php ---- */
    $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => "❌ بستن لیست", 'callback_data' => "colselist"], 'colselist', $_rx_pay_styles)
    ];
    $step_payment = json_encode($step_payment);
$keyboardhelpadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "📚 اضافه کردن آموزش", 'callback_data' => 'help_add'], 'help_add', $_rx_helpa_styles),
            rx_kb_style(['text' => "❌ حذف آموزش", 'callback_data' => 'help_del'], 'help_del', $_rx_helpa_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش آموزش", 'callback_data' => 'help_edit'], 'help_edit', $_rx_helpa_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'help_back'], 'help_back', $_rx_helpa_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'help_backmenu', $_rx_helpa_styles)
        ]
    ]);
$shopkeyboard = rx_kb_encode([
        [rx_kb_style(['text' => "🛒 وضعیت قابلیت های فروشگاه", 'callback_data' => 'shop_status'], 'shop_status', $_rx_shp_styles)],
        [
            rx_kb_style(['text' => "🗂 مدیریت دسته بندی", 'callback_data' => 'shop_category'], 'shop_category', $_rx_shp_styles),
            rx_kb_style(['text' => "🛍 مدیریت محصولات", 'callback_data' => 'shop_products'], 'shop_products', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد هدیه", 'callback_data' => 'shop_giftadd'], 'shop_giftadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد هدیه", 'callback_data' => 'shop_giftdel'], 'shop_giftdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد تخفیف", 'callback_data' => 'shop_discountadd'], 'shop_discountadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد تخفیف", 'callback_data' => 'shop_discountdel'], 'shop_discountdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ حداقل موجودی خرید عمده", 'callback_data' => 'shop_minbulk'], 'shop_minbulk', $_rx_shp_styles),
            rx_kb_style(['text' => "🎁 کش بک تمدید", 'callback_data' => 'shop_renewcb'], 'shop_renewcb', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'shop_backadmin'], 'shop_backadmin', $_rx_shp_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'shop_backmenu'], 'shop_backmenu', $_rx_shp_styles)
        ]
    ]);
$keyboard_Category_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛒 اضافه کردن دسته بندی", 'callback_data' => 'cat_add'], 'cat_add', $_rx_cat_styles),
            rx_kb_style(['text' => "❌ حذف دسته بندی", 'callback_data' => 'cat_del'], 'cat_del', $_rx_cat_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش دسته بندی", 'callback_data' => 'cat_edit'], 'cat_edit', $_rx_cat_styles)],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'cat_back'], 'cat_back', $_rx_cat_styles)]
    ]);
$keyboard_shop_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛍 اضافه کردن محصول", 'callback_data' => 'shopitem_add'], 'shopitem_add', $_rx_prod_styles),
            rx_kb_style(['text' => "❌ حذف محصول", 'callback_data' => 'shopitem_del'], 'shopitem_del', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش محصول", 'callback_data' => 'shopitem_edit'], 'shopitem_edit', $_rx_prod_styles)],
        [
            rx_kb_style(['text' => "⬆️ افزایش گروهی قیمت", 'callback_data' => 'shopitem_priceinc'], 'shopitem_priceinc', $_rx_prod_styles),
            rx_kb_style(['text' => "⬇️ کاهش گروهی قیمت", 'callback_data' => 'shopitem_pricedec'], 'shopitem_pricedec', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'shopitem_back'], 'shopitem_back', $_rx_prod_styles)]
    ]);
$_rx_rules_btn = rx_kb_style(['text' => "✅ قوانین را می پذیرم", 'callback_data' => "acceptrule"], 'rules_accept', $_rx_nav_styles);
$confrimrolls = json_encode(['inline_keyboard' => [[$_rx_rules_btn]]]);
$request_contact = json_encode([
    'keyboard' => [
        [rx_kb_style(['text' => "☎️ ارسال شماره تلفن", 'request_contact' => true], 'contact_phone', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'contact_back'], 'contact_back', $_rx_nav_styles)]
    ],
    'resize_keyboard' => true
]);
$Feature_status = rx_kb_encode([
        [rx_kb_style(['text' => "⚙️ قابلیت مشاهده اطلاعات اکانت", 'callback_data' => 'feat_info'], 'feat_info', $_rx_feat_styles)],
        [
            rx_kb_style(['text' => "🧪 قابلیت اکانت تست", 'callback_data' => 'feat_test'], 'feat_test', $_rx_feat_styles),
            rx_kb_style(['text' => "📚 قابلیت آموزش", 'callback_data' => 'feat_help'], 'feat_help', $_rx_feat_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'feat_back'], 'feat_back', $_rx_feat_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'feat_backmenu', $_rx_feat_styles)
        ]
    ]);
$channelkeyboard = rx_kb_encode([
        [
            rx_kb_style(['text' => "➕ اضافه کردن کانال", 'callback_data' => 'ch_add'], 'ch_add', $_rx_chan_styles),
            rx_kb_style(['text' => "❌ حذف کانال", 'callback_data' => 'ch_del'], 'ch_del', $_rx_chan_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'ch_back'], 'ch_back', $_rx_chan_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'ch_backmenu', $_rx_chan_styles)
        ]
    ]);
$_rx_back_btn = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'nav_back', $_rx_nav_styles);
$backuser = json_encode(['inline_keyboard' => [[$_rx_back_btn]]]);
$backadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"برای بازگشت روی دکمه زیر کلیک کنید"
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (trim((string)$row['name_panel']) === '') continue; // skip corrupt/blank panel rows
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (trim((string)$row['name_panel']) === '') continue; // skip corrupt/blank panel rows
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>$row['name_panel'],'callback_data' => 'locationedit_'.$row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"همه پنل ها",'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"▶️ بازگشت به منوی قبل",'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_channels_joins = json_encode($list_channels_join);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_card_remove = json_encode($list_card_remove);
}

    $stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
    $stmt->execute();
    $result = $stmt->fetchAll();
    $table_exists = count($result) > 0;
    if ($table_exists) {
    $stmt = $pdo->prepare("SELECT h.* FROM help h JOIN content_categories c ON c.section='help' AND c.name=h.category AND c.enabled=1 ORDER BY c.sort_order,h.id");
    $stmt->execute();
    $helpkey = [];
    $stmt = $pdo->prepare("SELECT h.* FROM help h JOIN content_categories c ON c.section='help' AND c.name=h.category AND c.enabled=1 ORDER BY c.sort_order,h.id");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpkey[] = [$row['name_os']];
        }
        $help_arrke = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        foreach ($helpkey as $button) {
            $help_arrke['keyboard'][] = [
                ['text' => $button[0]]
            ];
        }
                $help_arrke['keyboard'][] = [
            ['text' => $textbotlang['users']['backbtn']],
        ];
        $json_list_helpkey = json_encode($help_arrke);
}

    $stmt = $pdo->prepare("SELECT h.* FROM help h JOIN content_categories c ON c.section='help' AND c.name=h.category AND c.enabled=1 ORDER BY c.sort_order,h.id");
    $stmt->execute();
    $helpcwtgory = ['inline_keyboard' => []];
    $datahelp = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(in_array($result['category'],$datahelp))continue;
        if($result['category'] == null)continue;
        $datahelp[] = $result['category'];
            $helpcwtgory['inline_keyboard'][] = [['text' => $result['category'], 'callback_data' => "helpctgoryـ{$result['category']}"]
            ];
        }
if($setting['linkappstatus'] == "1"){
    $helpcwtgory['inline_keyboard'][] = [
        ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappdownlod"],
    ];
    }
$helpcwtgory['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـcategory = json_encode($helpcwtgory);


    $stmt = $pdo->prepare("SELECT * FROM app");
    $stmt->execute();
    $helpapp = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpapp['inline_keyboard'][] = [['text' => $result['name'], 'url' =>$result['link']]
            ];
        }
$helpapp['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـlink = json_encode($helpapp);

    $stmt = $pdo->prepare("SELECT * FROM app");
    $stmt->execute();
    $helpappremove = ['keyboard' => [],'resize_keyboard' => true];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpappremove['keyboard'][] = [
            ['text' => $result['name']],
        ];
        }
$helpappremove['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backadmin']],
];
$json_list_remove_helpـlink = json_encode($helpappremove);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if ($panelcount > 10) {
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;
            if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;
            if ($result['type'] == "Manualsale") {
                $manualStmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $manualStmt->bindParam(':codepanel', $result['code_panel']);
                $manualStmt->execute();
                $configexits = $manualStmt->rowCount();
                if (intval($configexits) == 0) continue;
            }
            if ($users['step'] == "getusernameinfo") {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"];
            } else {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"];
            }
            if (count($temp_row) == 2) {
                $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
                $temp_row = [];
            }
        }
        if (!empty($temp_row)) {
            $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
        }
    } else {
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;
            if ($result['type'] == "Manualsale") {
                $stmts = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $stmts->bindParam(':codepanel', $result['code_panel']);
                $stmts->execute();
                $configexits = $stmts->rowCount();
                if (intval($configexits) == 0) continue;
            }
            if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;
            if ($users['step'] == "getusernameinfo") {
                $list_marzban_panel_users['inline_keyboard'][] = [
                    ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"]
                ];
            } else {
                $list_marzban_panel_users['inline_keyboard'][] = [[
                    'text' => $result['name_panel'],
                    'callback_data' => "location_{$result['code_panel']}"
                ]];
            }
        }
    }
$statusnote = false;
if($setting['statusnamecustom'] == 'onnamecustom')$statusnote = true;
if($setting['statusnoteforf'] == "0" && $users['agent'] == "f")$statusnote = false;
    if($statusnote){
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "buyback"],
];
}else{
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
}
$list_marzban_panel_user = json_encode($list_marzban_panel_users);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users_om = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
        if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;
            $list_marzban_panel_users_om['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_users_om['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_panel_userom = json_encode($list_marzban_panel_users_om);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :ag OR agent = 'all') AND name_panel != :exclude");
    $stmt->execute([':ag' => (string)($users['agent'] ?? ''), ':exclude' => (string)($users['Processing_value_four'] ?? '')]);
    $list_marzban_panel_users_change = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if($panelcount > 10){
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;
        if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;

            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if (count($temp_row) == 2) {
            $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
            $temp_row = [];
        }
    }
if (!empty($temp_row)) {
    $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
}
    }else{
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
        if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;
            $list_marzban_panel_users_change['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"]
            ];
    }
    }
$list_marzban_panel_users_change['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backorder"],
];
$list_marzban_panel_userschange = json_encode($list_marzban_panel_users_change);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = :ag OR agent = 'all')");
    $stmt->execute([':ag' => (string)($users['agent'] ?? '')]);
    $list_marzban_panel_usertest = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
        if (function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel($result)) continue;
            $list_marzban_panel_usertest['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


$textbot = json_encode([
    'keyboard' => [
        [['text' => "تنظیم متن شروع"], ['text' => "دکمه سرویس خریداری شده"]],
        [['text' => "دکمه اکانت تست"], ['text' => "دکمه سوالات متداول"]],
        [['text' => "متن دکمه 📚 آموزش"], ['text' => "متن دکمه ☎️ پشتیبانی"]],
        [['text' => "دکمه افزایش موجودی"],['text' => "متن دکمه زیرمجموعه گیری"]],
        [['text' => "متن دکمه خرید اشتراک"], ['text' => "متن دکمه لیست تعرفه"]],
        [['text' => "متن توضیحات لیست تعرفه"]],
        [['text' => "متن دکمه کیف پول"],['text' => "متن پیش فاکتور"]],
        [['text' => "📝 تنظیم متن توضیحات عضویت اجباری"]],
        [['text' => "📝 تنظیم متن توضیحات سوالات متداول"]],
        [['text' => "⚖️ متن قانون"],['text' => "متن بعد خرید"]],
        [['text' => "متن بعد خرید ibsng"],['text' => "دکمه تمدید"]],
        [['text' => "متن بعد گرفتن اکانت تست"],['text' =>"متن کرون تست"]],
        [['text' => "متن بعد گرفتن اکانت دستی"]],
        [['text' => "متن بعد گرفتن اکانت WGDashboard"]],
        [['text' => "متن انتخاب لوکیشن"],['text' => "متن دکمه کد هدیه"]],
        [['text' => "متن درخواست نمایندگی"],['text' => "متن دکمه  نمایندگی"]],
        [['text' => "متن دکمه گردونه شانس"],['text' => "متن کارت به کارت"]],
        [['text' => "تنظیم متن کارت به کارت خودکار"]],
        [['text' => "متن توضیحات درخواست نمایندگی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol","*",null,null,"fetchAll");
    $protocol = [];
    foreach($getdataprotocol as $result)
    {
        $protocol[] = [['text'=>$result['NameProtocol']]];
    }
    $protocol[] = [['text'=>$textbotlang['Admin']['backadmin']]];
    $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
 }

$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom of the product
    // picker. Same fix as the gift-code/discount-code delete lists below.
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_product_list_admin = json_encode($list_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row goes LAST so it renders at the bottom of the reply keyboard
    // (matches the convention used by $list_Inbound below + every static
    // admin keyboard). Previously this was inserted before the foreach loop
    // which forced the back row to the top of the gift-code delete list.
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin = json_encode($list_Discount);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value']  , PDO::PARAM_STR);
    $stmt->execute();
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $Inboundkeyboard[] = [$row['NameInbound']];
}

}
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom (same fix as the
    // gift-code list above — the original code prepended this row before
    // the foreach loop, forcing it to the top of the discount-code delete
    // keyboard).
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
$payment = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"], 'confirm_discount', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'confirm_back', $_rx_nav_styles)]
    ]
]);
$paymentom = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'confirm_back', $_rx_nav_styles)]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [
            rx_kb_style(['text' => "قیمت"], 'قیمت', $_rx_pedit_styles),
            rx_kb_style(['text' => "حجم"], 'حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "زمان"], 'زمان', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نام محصول"], 'نام محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "نوع کاربری"], 'نوع کاربری', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نوع ریست حجم"], 'نوع ریست حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "یادداشت"], 'یادداشت', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "موقعیت محصول"], 'موقعیت محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "دسته بندی"], 'دسته بندی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "🎛 تنظیم اینباند"], '🎛 تنظیم اینباند', $_rx_pedit_styles),
            rx_kb_style(['text' => "نمایش برای خرید اول"], 'نمایش برای خرید اول', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "مخفی کردن پنل"], 'مخفی کردن پنل', $_rx_pedit_styles),
            rx_kb_style(['text' => "حذف کلی پنل های مخفی"], 'حذف کلی پنل های مخفی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin']], 'backadmin', $_rx_pedit_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'backmenu', $_rx_pedit_styles),
        ]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"],['text' => "vmess"],['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$rxAdminPanelStylesAll = [];
if (function_exists('select')) {
    $__rxRow = select('setting', 'keyboard_styles_all', null, null, 'select');
    if (is_array($__rxRow) && !empty($__rxRow['keyboard_styles_all'])) {
        $__rxDecoded = json_decode($__rxRow['keyboard_styles_all'], true);
        if (is_array($__rxDecoded)) { $rxAdminPanelStylesAll = $__rxDecoded; }
    }
    unset($__rxRow, $__rxDecoded);
}










if (!function_exists('rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!function_exists('rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {

        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;


        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        $file = rx_adminPanelCallbackMapFile();
        $map = [];
        if (is_file($file)) {
            $dec = json_decode((string) @file_get_contents($file), true);
            if (is_array($dec)) $map = $dec;
        }
        $map[$code] = $text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $file = rx_adminPanelCallbackMapFile();
            if (!is_file($file)) return null;
            $map = json_decode((string) @file_get_contents($file), true);
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}







if (!function_exists('rx_finalizeInlineAdminKb')) {
    function rx_finalizeInlineAdminKb(string $json): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;

        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;

                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = rx_makeAdminPanelCallback((string)$btn['text']);
                }

                unset($btn['request_contact'], $btn['request_location'], $btn['request_poll'], $btn['request_users'], $btn['request_chat']);
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}
$rxAdminPanelBtn = function (string $text, string $menuKey, string $default = 'default') use ($rxAdminPanelStylesAll) {
    $allowed = ['default','primary','success','danger'];
    $map = $rxAdminPanelStylesAll[$menuKey] ?? [];

    $rxUseDefaults = (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults());

    if (is_array($map) && array_key_exists($text, $map)) {
        $style = (string)$map[$text];
    } else {
        $style = $rxUseDefaults ? $default : 'default';


        if ($rxUseDefaults && $default === 'default' && function_exists('rx_getKeyboardDefaultStyles')) {
            $rxFallbackMap = rx_getKeyboardDefaultStyles($menuKey);
            if (is_array($rxFallbackMap) && isset($rxFallbackMap[$text])) {
                $rxFallback = (string)$rxFallbackMap[$text];
                if (in_array($rxFallback, $allowed, true)) {
                    $style = $rxFallback;
                }
            }
        }


        if ($rxUseDefaults && $style === 'default' && function_exists('rx_kb_guess_style_from_text')) {
            $guessed = rx_kb_guess_style_from_text($text);
            $style = ($guessed !== null && in_array($guessed, $allowed, true)) ? $guessed : 'primary';
        }
    }

    if (!in_array($style, $allowed, true)) { $style = $rxUseDefaults ? $default : 'default'; }

    $btn = [
        'text' => $text,
        'callback_data' => rx_makeAdminPanelCallback($text),
    ];
    if ($style !== 'default') { $btn['style'] = $style; }
    return $btn;
};

$optionMarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzban', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzban'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("⚙️ تنظیم پروتکل و اینباند", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzban'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_marzban'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_marzban'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_marzban'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_marzban', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
/* ---- admin_panels_1.php ---- */
$optionGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_guard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_guard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_guard'), $rxAdminPanelBtn("⁉️ وضعیت اتصال به پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ تنظیم سرویس ها", 'admin_panel_guard'), $rxAdminPanelBtn("🎛️ تنظیمات سرویس", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_guard'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_guard'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_guard'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_guard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_guard'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_guard')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_guard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionibsng = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_ibsng'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_ibsng', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_ibsng'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_ibsng'), $rxAdminPanelBtn('🎛 تنظیم نام گروه', 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_ibsng'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_ibsng'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_ibsng'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_ibsng'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_ibsng')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_ibsng', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$option_mikrotik = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_mikrotik'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_mikrotik', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_mikrotik'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_mikrotik'), $rxAdminPanelBtn('🎛 تنظیم نام گروه', 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_mikrotik'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_mikrotik'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_mikrotik'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_mikrotik')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_mikrotik', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$options_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_s_ui'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_s_ui', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_s_ui'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_s_ui'), $rxAdminPanelBtn("⚙️ تنظیم پروتکل و اینباند", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_s_ui'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_s_ui'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_s_ui'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_s_ui'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_s_ui'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_s_ui')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_s_ui', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionwg = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_wg'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_wg', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_wg'), $rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_wg'), $rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_wg'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_wg'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_wg'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_wg'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_wg')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_wg', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionmarzneshin = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzneshin'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzneshin', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzneshin'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⚙️ تنظیمات سرویس", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_marzneshin'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_marzneshin'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_marzneshin'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_marzneshin'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_marzneshin')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_marzneshin', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionManualsale = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_manualsale'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_manualsale'), $rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("➕ اضافه کردن کانفیگ", 'admin_panel_manualsale', 'success'), $rxAdminPanelBtn("❌ حذف کانفیگ ", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("✏️ ویرایش کانفیگ", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_manualsale'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_manualsale', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionX_ui_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_x_ui_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_x_ui_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionalireza_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_alireza_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_alireza_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_alireza_single'), $rxAdminPanelBtn("👤 ویرایش نام کاربری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("💎 تنظیم شناسه اینباند", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_alireza_single'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_alireza_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_alireza_single'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_alireza_single'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_alireza_single')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_alireza_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionhiddfy = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_hiddify'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_hiddify', 'danger')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_hiddify'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 تغییر گروه کاربری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("💡 روش ساخت نام کاربری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🚨 محدودیت ساخت اکانت", 'admin_panel_hiddify'), $rxAdminPanelBtn("🔗 uuid admin", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ زمان سرویس تست", 'admin_panel_hiddify'), $rxAdminPanelBtn("💾 حجم اکانت تست", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر لوکیشن", 'admin_panel_hiddify'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_hiddify'), $rxAdminPanelBtn("⚙️ قیمت حجم سرویس دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 حداقل حجم دلخواه", 'admin_panel_hiddify'), $rxAdminPanelBtn("📍 حداکثر حجم دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📍 حداقل زمان دلخواه", 'admin_panel_hiddify'), $rxAdminPanelBtn("📍 حداکثر زمان دلخواه", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📦 انبار شبکه ملی", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("📌 ثبت پنل اضطراری", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🚨 پنل اضطراری", 'admin_panel_hiddify'), $rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("🫣 مخفی کردن پنل برای یک کاربر", 'admin_panel_hiddify')],
        [$rxAdminPanelBtn("❌  حذف کاربر از لیست مخفی شدگان", 'admin_panel_hiddify', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
if($setting['statussupportpv'] == "onpvsupport"){
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}else{
$supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'callback_data' => "support"],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"],['text' => "Seller"],['text' => "support"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$affiliates =  json_encode([
    'keyboard' => [
        [['text' => "🧮 تنظیم درصد زیرمجموعه"]],
        [['text' => "🏞 تنظیم بنر زیرمجموعه گیری"]],
        [['text' => "🎁 پورسانت بعد از خرید"],['text' => "🎁 هدیه استارت"]],
        [['text' => "🎉 پورسانت فقط برای خرید اول"]],
        [['text' => "🌟 مبلغ هدیه استارت"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata =  json_encode([
    'keyboard' => [
        [['text' => "خروجی کاربران"],['text' => "خروجی سفارشات"]],
        [['text' => "خروجی گرفتن پرداخت ها"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$helpedit =  json_encode([
    'keyboard' => [
        [['text' =>"ویرایش نام"],['text' =>"ویرایش توضیحات"]],
        [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => "ریست حجم و زمان"]],
        [['text' => "اضافه شدن زمان و حجم به ماه بعد"]],
        [['text'=> "ریست زمان و اضافه کردن حجم قبلی"]],
        [['text' => "ریست شدن حجم و اضافه شدن زمان"]],
        [['text' => "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"],['text' => "day"],['text' => "week"]],
        [['text' => "month"],['text' => "year"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "مرزبان" , 'callback_data' => "typepanel#marzban"],
            ['text' => "🎛 پاسارگارد" , 'callback_data' => "typepanel#pasargard"]
        ],
        [
            ['text' => "مرزنشین" , 'callback_data' => "typepanel#marzneshin"],
            ['text' => "هیدیفای" , 'callback_data' => 'typepanel#hiddify']
        ],
        [
            ['text' => 'ثنایی تک پورت', 'callback_data' => 'typepanel#x-ui_single'],
            ['text' => 'علیرضا تک پورت' , 'callback_data' => 'typepanel#alireza_single']
        ],
        [
            ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale'],
            ['text' => "Guard (GuardCore)", 'callback_data' => 'typepanel#guard']
        ],
        [
            ['text' => "WGDashboard", 'callback_data' => 'typepanel#WGDashboard'],
            ['text' => "s_ui", 'callback_data' => 'typepanel#s_ui']
        ],
        [
            ['text' => "ibsng", 'callback_data' => 'typepanel#ibsng'],
            ['text' => "میکروتیک", 'callback_data' => 'typepanel#mikrotik']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel","*","MethodUsername","متن دلخواه نماینده + عدد ترتیبی","count");
if($setting['inlinebtnmain'] == "oninline"){
    // ── Red Fox: بررسی دسترسی‌های سوپر نماینده ──
    $_rxSuperPerms = [];
    try {
        $_rxSpRow = select('user', 'reseller_perms', 'id', $from_id, 'select');
        if (is_array($_rxSpRow) && !empty($_rxSpRow['reseller_perms'])) {
            $_rxSuperPerms = json_decode($_rxSpRow['reseller_perms'], true) ?: [];
        }
    } catch (Throwable $_e) {}
    $_rxHasSuper = false;
    foreach ($_rxSuperPerms as $_v) { if (!empty($_v)) { $_rxHasSuper = true; break; } }

    $_rxAgentRows = [];
    $_rxAgentRows[] = ['text' => "🗂 خرید انبوه", 'callback_data' => "kharidanbuh"];
    if ($panelechekc != 0) { $_rxAgentRows[0][] = ['text' => "👤 انتخاب نام دلخواه", 'callback_data' => "selectname"]; }
    if ($_rxHasSuper) {
        $_rxSuperBtns = [];
        if (!empty($_rxSuperPerms['products']))     $_rxSuperBtns[] = ['text' => "🛍 محصولات", 'callback_data' => "super_products"];
        if (!empty($_rxSuperPerms['categories']))   $_rxSuperBtns[] = ['text' => "📂 دسته‌بندی", 'callback_data' => "super_categories"];
        if (!empty($_rxSuperPerms['extend_user']))  $_rxSuperBtns[] = ['text' => "⏰ افزایش حجم/زمان", 'callback_data' => "super_extend"];
        if (!empty($_rxSuperBtns)) $_rxAgentRows[] = $_rxSuperBtns;
        $_rxSuperBtns2 = [];
        if (!empty($_rxSuperPerms['charge_user']))  $_rxSuperBtns2[] = ['text' => "💰 شارژ کاربر", 'callback_data' => "super_charge"];
        if (!empty($_rxSuperPerms['manage_users'])) $_rxSuperBtns2[] = ['text' => "👥 جستجوی کاربر", 'callback_data' => "super_search"];
        if (!empty($_rxSuperBtns2)) $_rxAgentRows[] = $_rxSuperBtns2;
        $_rxSuperBtns3 = [];
        if (!empty($_rxSuperPerms['reports']))      $_rxSuperBtns3[] = ['text' => "📊 گزارش فروش", 'callback_data' => "super_reports"];
        $_rxSuperBtns3[] = ['text' => "🤖 هوش مصنوعی", 'callback_data' => "reseller_ai_menu"];
        $_rxAgentRows[] = $_rxSuperBtns3;
    } else {
        $_rxAgentRows[] = [['text' => "🤖 هوش مصنوعی", 'callback_data' => "reseller_ai_menu"]];
    }
    $_rxAgentRows[] = [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]];

    $keyboardagent = json_encode(['inline_keyboard' => $_rxAgentRows], JSON_UNESCAPED_UNICODE);
}else{
    // ── حالت کیبورد معمولی (غیر inline) ──
    $_rxSuperPerms2 = [];
    try {
        $_rxSpRow2 = select('user', 'reseller_perms', 'id', $from_id, 'select');
        if (is_array($_rxSpRow2) && !empty($_rxSpRow2['reseller_perms'])) {
            $_rxSuperPerms2 = json_decode($_rxSpRow2['reseller_perms'], true) ?: [];
        }
    } catch (Throwable $_e) {}
    $_rxHasSuper2 = false;
    foreach ($_rxSuperPerms2 as $_v) { if (!empty($_v)) { $_rxHasSuper2 = true; break; } }

    $_rxKbRows = [];
    $row1 = [['text' => "🗂 خرید انبوه"]];
    if ($panelechekc != 0) $row1[] = ['text' => "👤 انتخاب نام دلخواه"];
    $_rxKbRows[] = $row1;
    if ($_rxHasSuper2) {
        $srow = [];
        if (!empty($_rxSuperPerms2['products']))     $srow[] = ['text' => "🛍 محصولات"];
        if (!empty($_rxSuperPerms2['categories']))   $srow[] = ['text' => "📂 دسته‌بندی"];
        if (!empty($_rxSuperPerms2['extend_user']))  $srow[] = ['text' => "⏰ حجم/زمان کاربر"];
        if (!empty($srow)) $_rxKbRows[] = $srow;
        $srow2 = [];
        if (!empty($_rxSuperPerms2['charge_user']))  $srow2[] = ['text' => "💰 شارژ کاربر"];
        if (!empty($_rxSuperPerms2['manage_users'])) $srow2[] = ['text' => "👥 جستجوی کاربر"];
        if (!empty($srow2)) $_rxKbRows[] = $srow2;
        if (!empty($_rxSuperPerms2['reports'])) $_rxKbRows[] = [['text' => "📊 گزارش فروش"]];
    }
    $_rxKbRows[] = [['text' => "🤖 هوش مصنوعی ربات من"]];
    $_rxKbRows[] = [['text' => $textbotlang['users']['backbtn']]];

    $keyboardagent = json_encode(['keyboard' => $_rxKbRows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
}
// Red Fox FIX: خط زیر حذف شد — keyboardagent قبلاً json_encode شده است
// double-encode کردن باعث می‌شد کیبورد خراب شود و پنل نمایندگی کار نکند!
$Swapinokey = json_encode([
    'keyboard' => [
        [['text' => "تنظیم api"]],
        [['text' => "🗂 نام درگاه ارزی ریالی"]],
        [['text' => "💰 کش بک ارزی ریالی"],['text' => "📚 تنظیم آموزش ارزی ریالی اول"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه رمز ارز آفلاین"]],
        [['text' => "⬇️ حداقل مبلغ رمزارز آفلاین"],['text' => "⬆️ حداکثر مبلغ رمزارز آفلاین"]],
        [['text' => "📚 تنظیم آموزش  ارزی افلاین"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 ساخت کانفیگ دستی", 'admin_panel_athmarzban'), $rxAdminPanelBtn("🖥 مدیریت نود ها", 'admin_panel_athmarzban')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionathx_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 ساخت کانفیگ دستی", 'admin_panel_athx_ui')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$configedit = json_encode([
    'keyboard' => [
        [['text' => "مخشصات کانفیگ"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => "api  درگاه ارزی ریالی"]],
        [['text' => "🗂 نام درگاه ارزی ریالی سوم"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی سوم"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی سوم"]],
        [['text' => "💰 کش بک ارزی ریالی سوم"]],
        [['text' => "📚 تنظیم آموزش ارزی ریالی سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => "👤 تنظیم آیدی پشتیبانی"]],
        [['text' => "🔼 اضافه کردن دپارتمان"],['text' => "🔽 حذف کردن دپارتمان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$departeman = [];

$departemans = [
    'keyboard' => [],
    'resize_keyboard' => true,
];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departeman[] = [$row['name_departman']];
    }
    foreach ($departeman as $button) {
        $departemans['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
}

$departemans['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backadmin']],
    ['text' => $textbotlang['Admin']['backmenu']]
];

$departemanslist = json_encode($departemans);


$list_departman = ['inline_keyboard' => []];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_departman['inline_keyboard'][] = [[
            'text' => $result['name_departman'],
            'callback_data' => "departman_{$result['id']}"
        ]];
    }
}

$list_departman['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_departman = json_encode($list_departman);
$active_panell =  json_encode([
    'keyboard' => [
        [['text' => "📣 گزارشات ربات"]],
    ],
    'resize_keyboard' => true
]);
$lottery =  json_encode([
    'keyboard' => [
        [['text' => "1️⃣ تنظیم جایزه نفر اول"],['text' => "2️⃣ تنظیم جایزه نفر دوم"]],
        [['text' => "3️⃣ تنظیم جایزه نفر سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard =  json_encode([
    'keyboard' => [
        [['text' => "🎲 مبلغ برنده شدن کاربر"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
/* ---- admin_panels_2.php ---- */
$keyboardlinkapp = json_encode([
    'keyboard' => [
        [['text' => "🔗 اضافه کردن برنامه"],['text' => "❌ حذف برنامه"]],
        [['text' => "✏️ ویرایش برنامه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if (!function_exists('rxProductLookupTokens')) {
function rxProductLookupTokens($rawToken)
{
    $tokens = [];
    $addToken = static function ($value) use (&$tokens) {
        if ($value === null) return;
        $value = trim((string) $value);
        if ($value === '') {
            $tokens[''] = '';
            return;
        }
        $decodedHtml = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decodedUrl = rawurldecode($decodedHtml);
        foreach ([$value, $decodedHtml, $decodedUrl] as $candidate) {
            $candidate = trim((string) $candidate);
            foreach (['prodcutservice_', 'prodcutservices_', 'prodcutserviceom_', 'prodcutservicesom_'] as $prefix) {
                if (strpos($candidate, $prefix) === 0) {
                    $candidate = substr($candidate, strlen($prefix));
                    break;
                }
            }
            $tokens[$candidate] = $candidate;
        }
    };

    $addToken($rawToken);
    if (function_exists('rx_resolveInlineButtonText')) {
        $resolved = rx_resolveInlineButtonText((string) $rawToken);
        if ($resolved !== null) {
            $addToken($resolved);
        }
    }

    return array_values($tokens);
}
}

if (!function_exists('rxResolveProductForPanel')) {
function rxResolveProductForPanel($productToken, $panelName, $agent = null, $category = null, $serviceTime = null)
{
    global $pdo;

    if (!($pdo instanceof PDO)) return false;
    $panelName = trim((string) $panelName);
    if ($panelName === '') return false;
    $agent = $agent === null ? null : trim((string) $agent);
    $tokens = rxProductLookupTokens($productToken);

    $queryProduct = static function ($extraSql, array $extraParams = []) use ($pdo, $panelName, $agent, $category, $serviceTime) {
        $sql = "SELECT * FROM product WHERE (Location = :rx_location_where OR Location = '/all')";
        $params = [
            ':rx_location_where' => $panelName,
            ':rx_location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :rx_agent_where OR agent = 'all')";
            $params[':rx_agent_where'] = $agent;
            $params[':rx_agent_order'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (category = :rx_category OR category = :rx_category_id)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }

        $sql .= ' ' . $extraSql;
        $sql .= " ORDER BY CASE WHEN Location = :rx_location_order THEN 0 ELSE 1 END";
        if ($agent !== null && $agent !== '') {
            $sql .= ", CASE WHEN agent = :rx_agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END";
        }
        $sql .= ", id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + $extraParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    };

    foreach ($tokens as $token) {
        if (preg_match('/^pid:(\d+)$/', (string) $token, $m)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $m[1]]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByCodeForPanel')) {
            $row = nmProductByCodeForPanel($token, $panelName, $agent);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $row = $queryProduct('AND code_product = :rx_code_product', [':rx_code_product' => $token]);
        if ($row) return $row;
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') continue;
        if (ctype_digit($token)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $token]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByNameForPanel')) {
            $row = nmProductByNameForPanel($token, $panelName, $agent, $category);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $cleanName = preg_replace('/\s*-\s*[0-9,\.]+\s*تومان\s*$/u', '', $token);
        foreach (array_unique([$token, trim((string) $cleanName)]) as $nameCandidate) {
            if ($nameCandidate === '') continue;
            $row = $queryProduct('AND name_product = :rx_name_product', [':rx_name_product' => $nameCandidate]);
            if ($row) return $row;
        }
    }

    $nonEmptyTokens = array_filter($tokens, static function ($token) { return trim((string) $token) !== ''; });
    if (empty($nonEmptyTokens)) {
        $sql = "SELECT * FROM product WHERE (Location = :rx_location_where OR Location = '/all')";
        $params = [':rx_location_where' => $panelName];
        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :rx_agent_where OR agent = 'all')";
            $params[':rx_agent_where'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (category = :rx_category OR category = :rx_category_id)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }
        $sql .= " ORDER BY id ASC LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) return $rows[0];
    }

    return false;
}
}

function KeyboardProduct($location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume", $agentFilter = null, $params = []){
    global $pdo,$textbotlang,$from_id;
    $product = ['inline_keyboard' => []];
    $statusshowprice = select("shopSetting","*","Namevalue","statusshowprice","select")['value'];
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    if($valuetow != null){
            $valuetow = "-$valuetow";
    }else{
            $valuetow = "";
        }
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($agentFilter !== null) {
            $productAgent = (string) ($result['agent'] ?? '');
            if ($productAgent !== (string) $agentFilter && $productAgent !== 'all') {
                continue;
            }
        }
        $hide_panel = json_decode($result['hide_panel'], true);
        if (!is_array($hide_panel)) {
            if ($hide_panel === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log(sprintf('Invalid hide_panel JSON for product #%s: %s', $result['id'] ?? 'unknown', json_last_error_msg()));
            }
            $hide_panel = [];
        }


        if(intval($pricediscount) != 0){
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] -$resultper;
        }
        $namekeyboard = $result['name_product']." - ".number_format($result['price_product']) ."تومان";
        if($statusshowprice == "onshowprice"){
            $result['name_product'] = $namekeyboard;
        }
        $callbackToken = trim((string)($result['code_product'] ?? ''));
        $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        if ($callbackToken === '' || strlen($callbackData) > 64) {
            $callbackToken = 'pid:' . (string)($result['id'] ?? '');
            $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        }
        $product['inline_keyboard'][] = [
                ['text' =>  $result['name_product'], 'callback_data' => $callbackData]
            ];
    }
    if ($statuscustom)$product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume]];
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser],
    ];
    return json_encode($product);
}
function KeyboardCategory($location,$agent,$backuser = "backuser"){
    global $pdo,$textbotlang;
    $list_category = ['inline_keyboard' => []];
    // Use the shared, collation-safe matcher so the list shows exactly the
    // categories that have a sellable product (matching by remark OR id OR
    // name/title, normalised). The old code joined product.category =
    // category.remark directly, which under mixed collations / id-based values
    // produced an empty list (national-net "no categories" bug).
    if (function_exists('nmSellableCategoryRows')) {
        foreach (nmSellableCategoryRows($location, $agent) as $row) {
            $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id']]];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM category");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmts = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category AND (agent = :agent OR agent = 'all')");
            $stmts->bindParam(':location', $location, PDO::PARAM_STR);
            $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
            $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
            $stmts->execute();
            if($stmts->rowCount() == 0)continue;
            $list_category['inline_keyboard'][] = [['text' =>$row['remark'],'callback_data' => "categorynames_".$row['id']]];
        }
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ بازگشت به منوی قبل","callback_data" => $backuser],
    ];
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT Service_time FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
    $stmt->execute([
        ':location' => $name_panel,
        ':agent' => $agent
    ]);
    $montheproduct = array_flip(array_flip($stmt->fetchAll(PDO::FETCH_COLUMN)));
    $monthkeyboard = ['inline_keyboard' => []];
    if (in_array("1",$montheproduct)){
        $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1day'], 'callback_data' => "{$callback_data}1"]
                ];
            }
    if (in_array("7",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['7day'], 'callback_data' => "{$callback_data}7"]
                ];
            }
    if (in_array("31",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}31"]
                ];
            }
    if (in_array("30",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}30"]
                ];
            }
    if (in_array("61",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}61"]
                ];
            }
    if (in_array("60",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}60"]
                ];
            }
    if (in_array("91",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}91"]
                ];
            }
    if (in_array("90",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}90"]
                ];
            }
    if (in_array("121",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}121"]
                ];
            }
    if (in_array("120",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}120"]
                ];
            }
    if (in_array("181",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}181"]
                ];
            }
    if (in_array("180",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}180"]
                ];
            }
    if (in_array("365",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['365'], 'callback_data' => "{$callback_data}365"]
                ];
            }
    if (in_array("0",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['unlimited'], 'callback_data' => "{$callback_data}0"]
                ];
            }
    if($statusbtnextend)$monthkeyboard['inline_keyboard'][] = [['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]];
    if ($statuscustomvolume == true)$monthkeyboard['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => "customsellvolume"]];
    $monthkeyboard['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $callback_data_back]
            ];
    return json_encode($monthkeyboard);
}
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه استار"]],
        [['text' => "💰 کش بک استار"],['text' => "📚 تنظیم آموزش استار"]],
        [['text' => "⬇️ حداقل مبلغ استار"],['text' => "⬆️ حداکثر مبلغ استار"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => "🆓 محدودیت رایگان"],['text' => "↙️ محدودیت کلی"]],
        [['text' => "🔄 ریست محدودیت کل کاربران"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin(){
    global $pdo, $textbotlang, $setting;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' => $row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];

    $json = json_encode($list_category);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($list_category);
    }
    return $json;
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => "API NOWPAYMENT"],['text' => "🔐 IPN Secret nowpayment"]],
        [['text' => "🗂 نام درگاه nowpayment"],['text' => "💰 کش بک nowpayment"]],
        [['text' => "📚 تنظیم آموزش nowpayment"]],
        [['text' => "⬇️ حداقل مبلغ nowpayment"],['text' => "⬆️ حداکثر مبلغ nowpayment"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ استثناء کردن کاربر"],['text' => "❌ حذف کاربر از لیست"]],
        [['text' => "👁 نمایش لیست افراد"]],
        [['text' => "▶️ بازگشت به منوی تظنیمات کارت"]]
    ],
    'resize_keyboard' => true
]);
function keyboard_config($config_split,$id_invoice,$back_active = true){
    global $textbotlang;
    try {
        $invoiceForKeyboard = select("invoice", "*", "id_invoice", $id_invoice, "select");
        if (function_exists("nmServicePanelAccessBlocked") && is_array($invoiceForKeyboard) && nmServicePanelAccessBlocked($invoiceForKeyboard)) {
            return json_encode(["inline_keyboard" => [[["text" => function_exists("nmServiceRestrictedNotice") ? nmServiceRestrictedNotice() : "این سرویس در حال حاضر به دلیل شرایط اینترنت ملی در دسترس نیست !", "callback_data" => "none"]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } catch (Throwable $e) {
        error_log("keyboard_config national block check failed: " . redfox_exception_fingerprint($e));
    }
    $keyboard_config = ['inline_keyboard' => []];
    $keyboard_config['inline_keyboard'][] = [
        ['text' => "⚙️ کانفیگ", 'callback_data' => "none"],
        ['text' => "✏️نام کانفیگ", 'callback_data' => "none"],
        ];
    foreach (array_values($config_split) as $i => $config){
        if(!is_string($config) || $config === ''){
            error_log('Invalid configuration entry encountered while building keyboard');
            continue;
        }

        $split_config = explode("://",$config,2);
        if(count($split_config) !== 2){
            error_log('Malformed configuration string: missing scheme separator');
            continue;
        }

        $type_prtocol = $split_config[0];
        $payload = $split_config[1];
        if(isBase64($payload)){
            $decoded = base64_decode($payload, true);
            if($decoded === false){
                error_log('Failed to decode base64 configuration payload');
                continue;
            }
            $payload = $decoded;
        }

        $displayName = '';
        if($type_prtocol == "vmess"){
            $configJson = json_decode($payload, true);
            if(is_array($configJson) && isset($configJson['ps'])){
                $displayName = $configJson['ps'];
            }
        }else{
            $parts = explode("#",$payload,2);
            if(count($parts) === 2){
                $displayName = $parts[1];
            }
        }

        if($displayName === '' || $displayName === null){
            $displayName = sprintf('Config %d', $i + 1);
        }

        $keyboard_config['inline_keyboard'][] = [
            ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
            ['text' => urldecode($displayName), 'callback_data' => "none"],
        ];

    }
    $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "configget_$id_invoice"."_1520"]];
    if($back_active){
    $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"]];
    }
    return json_encode($keyboard_config);
}
$keyboard_buy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🛍خرید اشتراک", 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboard_stat = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "⏱️ آمار کل", 'callback_data' => 'stat_all_bot'],
                ],[
                    ['text' => "⏱️ یک ساعت اخیر", 'callback_data' => 'hoursago_stat'],
                ],
                [
                    ['text' => "⛅️ امروز", 'callback_data' => 'today_stat'],
                    ['text' => "☀️ دیروز", 'callback_data' => 'yesterday_stat'],
                ],
                [
                    ['text' => "☀️ ماه فعلی ", 'callback_data' => 'month_current_stat'],
                    ['text' => "⛅️ ماه قبل", 'callback_data' => 'month_old_stat'],
                ],
                [
                    ['text' => "🗓 مشاهده آمار در تاریخ مشخص", 'callback_data' => 'view_stat_time'],
                ],
                [
                    ['text' => "❌ بستن", 'callback_data' => 'close_stat'],
                ]
            ]
        ]);
if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline") {



    $all_vars = get_defined_vars();
    foreach ($all_vars as $varName => $varValue) {
        if (is_string($varValue) && strpos($varValue, '"keyboard"') !== false && strpos($varValue, '"inline_keyboard"') === false) {

            $rxDecodedKb = json_decode($varValue, true);
            if (!is_array($rxDecodedKb) || !isset($rxDecodedKb['keyboard']) || !is_array($rxDecodedKb['keyboard'])) {
                continue;
            }
            $rxHasReplyOnly = false;
            foreach ($rxDecodedKb['keyboard'] as $rxRow) {
                if (!is_array($rxRow)) continue;
                foreach ($rxRow as $rxBtn) {
                    if (is_array($rxBtn) && (
                        isset($rxBtn['request_contact']) || isset($rxBtn['request_location']) ||
                        isset($rxBtn['request_poll']) || isset($rxBtn['request_users']) ||
                        isset($rxBtn['request_chat'])
                    )) { $rxHasReplyOnly = true; break 2; }
                }
            }
            if ($rxHasReplyOnly) continue;
            if (function_exists('rx_keyboardToInline')) {
                $$varName = rx_keyboardToInline($rxDecodedKb);
            }
        }
    }
    unset($all_vars, $rxDecodedKb, $rxRow, $rxBtn, $rxHasReplyOnly);
}