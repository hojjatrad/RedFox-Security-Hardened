<?php

$botinfo = select("botsaz", "* ", "bot_token", $ApiToken, "select");
$userbot = select("user", "*", "id", $botinfo['id_user'], "select");
$hide_panel = json_decode($botinfo['hide_panel'], true);
$text_bot_var =  json_decode(file_get_contents(__DIR__ . '/text.json'), true);

// ── Red Fox: بارگذاری استایل دکمه‌ها ──
$rxStyles = function_exists('rxVpnbotLoadStyles') ? rxVpnbotLoadStyles() : [];

$keyboarddate = array(
    'text_sell' => ['label' => $text_bot_var['btn_keyboard']['buy'], 'style' => 'buy'],
    'text_usertest' => ['label' => $text_bot_var['btn_keyboard']['test'], 'style' => 'test'],
    'text_Purchased_services' => ['label' => $text_bot_var['btn_keyboard']['my_service'], 'style' => 'my_service'],
    'accountwallet' => ['label' => $text_bot_var['btn_keyboard']['wallet'], 'style' => 'wallet'],
    'text_support' => ['label' => $text_bot_var['btn_keyboard']['support'], 'style' => 'support'],
    'text_help_unified' => ['label' => '📚 آموزش اتصال', 'style' => 'help'],
    'text_apps_unified' => ['label' => '📱 نرم‌افزارهای اتصال', 'style' => 'apps'],
    'text_Admin' => ['label' => "👨‍💼 پنل مدیریت", 'style' => 'admin'],
);
$list_admin = select("botsaz", "* ", "bot_token", $ApiToken, "select");
$admin_idsmain = select("admin", "id_admin", null, null, "FETCH_COLUMN");
if (!in_array($from_id, json_decode($list_admin['admin_ids'], true)) && !in_array($from_id, $admin_idsmain)) unset($keyboarddate['text_Admin']);

// ── کیبورد اصلی با استایل ──
$keyboard = ['keyboard' => [], 'resize_keyboard' => true];
$tempArray = [];
foreach ($keyboarddate as $kd) {
    $tempArray[] = function_exists('rxVpnbotBtn') ? rxVpnbotBtn($kd['label'], $kd['style']) : ['text' => $kd['label']];
    if (count($tempArray) == 2) {
        $keyboard['keyboard'][] = $tempArray;
        $tempArray = [];
    }
}
if (count($tempArray) > 0) {
    $keyboard['keyboard'][] = $tempArray;
}
$keyboard  = json_encode($keyboard, JSON_UNESCAPED_UNICODE);

// ── دکمه بازگشت ──
// دکمه‌های reply keyboard نمی‌توانند style داشته باشند — باعث خطای Telegram می‌شود
$_rxBackBtn = ['text' => "🏠 بازگشت به منوی اصلی"];
$backuser = json_encode([
    'keyboard' => [[$_rxBackBtn]],
    'resize_keyboard' => true,
    'input_field_placeholder' => "برای بازگشت روی دکمه زیر کلیک کنید"
], JSON_UNESCAPED_UNICODE);

// ── کیبورد تست ──
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = '{$userbot['agent']}' OR agent = 'all')");
$stmt->execute();
$list_marzban_panel_usertest = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true))) continue;
    if (in_array($result['name_panel'], $hide_panel)) continue;
    $btn = function_exists('rxVpnbotInlineBtn')
        ? rxVpnbotInlineBtn($result['name_panel'], ['callback_data' => "locationtest_{$result['code_panel']}"], 'panel_location')
        : ['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"];
    $list_marzban_panel_usertest['inline_keyboard'][] = [$btn];
}
$_btnBack = function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("🏠 بازگشت به منوی اصلی", ['callback_data' => "backuser"], 'back') : ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"];
$list_marzban_panel_usertest['inline_keyboard'][] = [$_btnBack];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest, JSON_UNESCAPED_UNICODE);

// ── کیبورد ادمین ──
$rxAdminBtnFn = function($text, $styleKey = 'admin') {
    if (function_exists('rxVpnbotBtn')) return rxVpnbotBtn($text, $styleKey);
    return ['text' => $text];
};
// Red Fox: دکمه‌ی سوپر نماینده در کیبورد ادمین (اگر دسترسی دارد)
$_rxOwnerId = (string)($dataBase['id_user'] ?? '');
$_rxIsSuper = function_exists('rxVpnbotIsSuper') ? rxVpnbotIsSuper($_rxOwnerId) : false;

$_rxAdminRows = [
    [$rxAdminBtnFn("📊 آمار ربات", 'admin')],
    [$rxAdminBtnFn("📋 لیست محصولات", 'admin'), $rxAdminBtnFn("➕ محصول جدید", 'admin')],
    [$rxAdminBtnFn("💰 تنظیمات فروشگاه", 'admin'), $rxAdminBtnFn("⚙️ وضعیت قابلیت ها", 'admin')],
    [$rxAdminBtnFn("👥 لیست کاربران", 'admin')],
    [$rxAdminBtnFn("🔍 جستجوی کاربر", 'admin'), $rxAdminBtnFn("👨‍🔧  مدیریت ادمین ها", 'admin')],
    [$rxAdminBtnFn("📝 تنظیم متون", 'admin')],
    [$rxAdminBtnFn("📞 تنظیم نام کاربری پشتیبانی", 'admin'), $rxAdminBtnFn("📬 گزارش ربات", 'admin')],
    [$rxAdminBtnFn("📣 جوین اجباری", 'admin')],
    [$rxAdminBtnFn("💳 شماره کارت‌ها", 'admin')],
    [$rxAdminBtnFn("🌐 پورتال وب", 'admin')],
];
if ($_rxIsSuper) {
    $_rxAdminRows[] = [$rxAdminBtnFn("⭐ سوپر نماینده", 'admin')];
}
$_rxAdminRows[] = [$rxAdminBtnFn("🏠 بازگشت به منوی اصلی", 'back')];

$keyboardadmin = json_encode(['keyboard' => $_rxAdminRows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);

$keyboardprice = json_encode([
    'keyboard' => [
        [$rxAdminBtnFn("🔋 قیمت حجم", 'admin'), $rxAdminBtnFn("⌛️ قیمت زمان", 'admin')],
        [$rxAdminBtnFn("💰 تنظیم قیمت محصول", 'admin'), $rxAdminBtnFn("✏️ تنظیم نام محصول", 'admin')],
        [$rxAdminBtnFn("بازگشت به منوی ادمین", 'back')],
    ],
    'resize_keyboard' => true
], JSON_UNESCAPED_UNICODE);

$keyboard_change_price = json_encode([
    'keyboard' => [
        [$rxAdminBtnFn("💎 متن کارت", 'admin'), $rxAdminBtnFn("🛍 دکمه خرید", 'buy')],
        [$rxAdminBtnFn("🔑 دکمه تست", 'test'), $rxAdminBtnFn("🛒 دکمه سرویس های من", 'my_service')],
        [$rxAdminBtnFn("👤 دکمه حساب کاربری", 'wallet'), $rxAdminBtnFn("☎️ متن دکمه پشتیبانی", 'support')],
        [$rxAdminBtnFn("💸 متن مرحله افزایش موجودی", 'wallet')],
        [$rxAdminBtnFn("بازگشت به منوی ادمین", 'back')],
    ],
    'resize_keyboard' => true
], JSON_UNESCAPED_UNICODE);

$backadmin = json_encode([
    'keyboard' => [
        [$rxAdminBtnFn("بازگشت به منوی ادمین", 'back')],
    ],
    'resize_keyboard' => true
], JSON_UNESCAPED_UNICODE);

// ── کیبورد انتخاب لوکیشن خرید ──
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = '{$userbot['agent']}' OR agent = 'all')");
$stmt->execute();
$list_marzban_panel_users = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true))) continue;
    if (in_array($result['name_panel'], $hide_panel)) continue;
    $btn = function_exists('rxVpnbotInlineBtn')
        ? rxVpnbotInlineBtn($result['name_panel'], ['callback_data' => "location_{$result['code_panel']}"], 'panel_location')
        : ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"];
    $list_marzban_panel_users['inline_keyboard'][] = [$btn];
}
$list_marzban_panel_users['inline_keyboard'][] = [$_btnBack];
$list_marzban_panel_user = json_encode($list_marzban_panel_users, JSON_UNESCAPED_UNICODE);

// ── کیبورد پرداخت ──
$payment = json_encode([
    'inline_keyboard' => [
        [function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("💰 پرداخت و دریافت سرویس", ['callback_data' => "confirmandgetservice"], 'payment_confirm') : ['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"]],
        [function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("🏠 بازگشت به منوی اصلی", ['callback_data' => "backuser"], 'payment_back') : ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"]]
    ]
], JSON_UNESCAPED_UNICODE);
$KeyboardBalance = json_encode([
    'inline_keyboard' => [
        [function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("💸 افزایش موجودی", ['callback_data' => "AddBalance"], 'payment_add_balance') : ['text' => "💸 افزایش موجودی", 'callback_data' => "AddBalance"]],
        [function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("🏠 بازگشت به منوی اصلی", ['callback_data' => "backuser"], 'payment_back') : ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"]]
    ]
], JSON_UNESCAPED_UNICODE);

if (!function_exists('rxVpnbotProductAllowed')) {
    function rxVpnbotProductAllowed($product, $userbot, $dataBase) {
        if (!is_array($product)) return false;
        $a = (string)($product['agent'] ?? '');
        return $a === 'all' || $a === (string)($userbot['agent'] ?? '') || $a === (string)($dataBase['id_user'] ?? '');
    }
}

function KeyboardProduct($location, $query, $pricediscount, $datakeyboard, $statuscustom = false, $backuser = "backuser", $valuetow = null, $customvolume = "customsellvolume", $params = [])
{
    global $pdo, $textbotlang;
    $product = ['inline_keyboard' => []];
    $statusRow=select("shopSetting","*","Namevalue","statusshowprice","select");$statusshowprice=is_array($statusRow)?(string)($statusRow['value']??'offshowprice'):'offshowprice';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $valuetow = $valuetow != null ? "-$valuetow" : "";
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productlist = is_file(__DIR__.'/product.json')?(json_decode((string)file_get_contents(__DIR__.'/product.json'),true)?:[]):[];
        $productlist_name = is_file(__DIR__.'/product_name.json')?(json_decode((string)file_get_contents(__DIR__.'/product_name.json'),true)?:[]):[];
        if (isset($productlist[$result['code_product']])) $result['price_product'] = $productlist[$result['code_product']];
        $result['name_product'] = empty($productlist_name[$result['code_product']]) ? $result['name_product'] : $productlist_name[$result['code_product']];
        $hide_panel = json_decode($result['hide_panel'], true);
        if (!is_array($hide_panel)) {
            $hide_panel = [];
        }
        if (intval($pricediscount) != 0) {
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        $namekeyboard = $result['name_product'] . " - " . number_format($result['price_product']) . "تومان";
        if ($statusshowprice == "onshowprice")$result['name_product'] = $namekeyboard;
        // Red Fox: استایل محصول
        $callback=function_exists('rxVpnbotCallbackData')?rxVpnbotCallbackData((string)$datakeyboard,(string)$result['code_product'].$valuetow):"{$datakeyboard}{$result['code_product']}{$valuetow}";
        $label=trim((string)$result['name_product']);if($label==='')$label='محصول بدون نام';$label=mb_strimwidth($label,0,120,'…','UTF-8');
        $btn = function_exists('rxVpnbotInlineBtn')
            ? rxVpnbotInlineBtn($label, ['callback_data' => $callback], 'product_item')
            : ['text' => $label, 'callback_data' => $callback];
        $product['inline_keyboard'][] = [$btn];
    }
    if ($statuscustom) {
        $btnCv = function_exists('rxVpnbotInlineBtn')
            ? rxVpnbotInlineBtn($textbotlang['users']['customsellvolume']['title'], ['callback_data' => $customvolume], 'custom_volume')
            : ['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume];
        $product['inline_keyboard'][] = [$btnCv];
    }
    $btnBack = function_exists('rxVpnbotInlineBtn')
        ? rxVpnbotInlineBtn($textbotlang['users']['stateus']['backinfo'], ['callback_data' => $backuser], 'back')
        : ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser];
    $product['inline_keyboard'][] = [$btnBack];
    return json_encode($product, JSON_UNESCAPED_UNICODE);
}
function KeyboardCategory($location, $agent, $backuser = "backuser", $ownerId = null)
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    if ($ownerId !== null && $ownerId !== '') {
        try {
            $rc = $pdo->prepare("SELECT c.id,c.name FROM reseller_categories c WHERE c.reseller_id=? AND c.status='active' AND EXISTS(SELECT 1 FROM reseller_product_categories m JOIN product p ON p.code_product=m.product_code WHERE m.reseller_id=c.reseller_id AND m.category_id=c.id AND (p.Location=? OR p.Location='/all') AND (p.agent=? OR p.agent=? OR p.agent='all')) ORDER BY c.sort_order,c.id");
            $rc->execute([(string)$ownerId,$location,$agent,(string)$ownerId]);
            foreach($rc->fetchAll(PDO::FETCH_ASSOC) as $cat){
                $btn=function_exists('rxVpnbotInlineBtn')?rxVpnbotInlineBtn((string)$cat['name'],['callback_data'=>'resellercategory_'.$cat['id']],'category_item'):['text'=>(string)$cat['name'],'callback_data'=>'resellercategory_'.$cat['id']];
                $list_category['inline_keyboard'][]=[$btn];
            }
        } catch(Throwable $e) { error_log('[vpnbot categories] '.redfox_exception_fingerprint($e)); }
    }
    if (!empty($list_category['inline_keyboard'])) {
        $btnBack = function_exists('rxVpnbotInlineBtn') ? rxVpnbotInlineBtn("▶️ بازگشت به منوی قبل", ['callback_data' => $backuser], 'back') : ['text' => "▶️ بازگشت به منوی قبل", 'callback_data' => $backuser];
        $list_category['inline_keyboard'][] = [$btnBack];
        return json_encode($list_category, JSON_UNESCAPED_UNICODE);
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmts = $pdo->prepare(
            "SELECT * FROM product
             WHERE (Location = :location OR Location = '/all')
             AND category = :category
             AND (agent = :agent OR agent = :owner OR agent = 'all')"
        );
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
        $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
        $ownerId = $ownerId === null ? '' : (string)$ownerId;
        $stmts->bindParam(':owner', $ownerId, PDO::PARAM_STR);
        $stmts->execute();
        if ($stmts->rowCount() == 0) continue;
        // Red Fox: استایل دسته‌بندی
        $btn = function_exists('rxVpnbotInlineBtn')
            ? rxVpnbotInlineBtn($row['remark'], ['callback_data' => "categorynames_" . $row['id']], 'category_item')
            : ['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id']];
        $list_category['inline_keyboard'][] = [$btn];
    }
    $btnBack = function_exists('rxVpnbotInlineBtn')
        ? rxVpnbotInlineBtn("▶️ بازگشت به منوی قبل", ['callback_data' => $backuser], 'back')
        : ['text' => "▶️ بازگشت به منوی قبل", 'callback_data' => $backuser];
    $list_category['inline_keyboard'][] = [$btnBack];
    return json_encode($list_category, JSON_UNESCAPED_UNICODE);
}
