<?php
/**
 * دیباگ دکمه‌های ربات
 * این فایل را روی سرور اجرا کنید تا دقیقاً مشکل مشخص شود.
 * نحوه اجرا: php debug_buttons.php
 */

require_once 'config.php';

echo "=== دیباگ دکمه‌های ربات ===\n\n";

// 1. بررسی اتصال دیتابیس
echo "1. بررسی اتصال دیتابیس:\n";
echo "   PDO: " . ($pdo ? "✅ متصل" : "❌ قطع") . "\n";
echo "   MySQLi: " . ($connect ? "✅ متصل" : "❌ قطع") . "\n\n";

// 2. بررسی مقادیر textbot
echo "2. بررسی مقادیر textbot:\n";
$datatextbotget = select("textbot", "*", null, null, "fetchAll");
$datatextbot = [];
$datatextbot_init = [
    'text_usertest' => '', 'text_Purchased_services' => '', 'text_support' => '',
    'text_help' => '', 'text_start' => '', 'text_bot_off' => '', 'text_sell' => '',
    'text_Add_Balance' => '', 'text_Tariff_list' => '', 'text_affiliates' => '',
    'text_wheel_luck' => '', 'text_extend' => '', 'accountwallet' => '',
];
foreach ($datatextbot_init as $k => $v) { $datatextbot[$k] = $v; }
foreach ($datatextbotget as $row) {
    if (isset($datatextbot[$row['id_text']])) {
        $datatextbot[$row['id_text']] = $row['text'];
    }
}
$critical_keys = ['text_sell', 'text_extend', 'text_Purchased_services', 'text_Tariff_list', 'text_affiliates', 'accountwallet', 'text_usertest', 'text_wheel_luck'];
foreach ($critical_keys as $key) {
    $val = $datatextbot[$key];
    $status = ($val !== '') ? "✅" : "❌ خالی!";
    echo "   $key: $status => " . mb_substr($val, 0, 50) . "\n";
}
echo "\n";

// 3. بررسی تنظیمات
echo "3. بررسی تنظیمات کلیدی:\n";
$setting = select("setting", "*", null, null, "select");
if (is_array($setting)) {
    echo "   inlinebtnmain: " . ($setting['inlinebtnmain'] ?? 'ندارد') . "\n";
    echo "   verifystart: " . ($setting['verifystart'] ?? 'ندارد') . "\n";
    echo "   roll_Status: " . ($setting['roll_Status'] ?? 'ندارد') . "\n";
    echo "   Bot_Status: " . ($setting['Bot_Status'] ?? 'ندارد') . "\n";
    echo "   statusnamecustom: " . ($setting['statusnamecustom'] ?? 'ندارد') . "\n";
    echo "   statusnoteforf: " . ($setting['statusnoteforf'] ?? 'ندارد') . "\n";
    echo "   get_number: " . ($setting['get_number'] ?? 'ندارد') . "\n";
    echo "   iran_number: " . ($setting['iran_number'] ?? 'ندارد') . "\n";
    echo "   antispam_status: " . ($setting['antispam_status'] ?? 'ندارد') . "\n";
} else {
    echo "   ❌ جدول setting یافت نشد!\n";
}
echo "\n";

// 4. بررسی keyboardmain
echo "4. بررسی keyboardmain:\n";
if (is_array($setting) && isset($setting['keyboardmain'])) {
    $kb = json_decode($setting['keyboardmain'], true);
    if (is_array($kb) && isset($kb['keyboard'])) {
        echo "   تعداد ردیف‌ها: " . count($kb['keyboard']) . "\n";
        foreach ($kb['keyboard'] as $i => $row) {
            foreach ($row as $btn) {
                $text = $btn['text'] ?? 'ندارد';
                $has_sell = ($text === 'text_sell') ? " ← زبان‌کی" : "";
                $has_farsi = preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $text) ? " ← فارسی!" : "";
                echo "   ردیف $i: \"$text\"$has_sell$has_farsi\n";
            }
        }
    } else {
        echo "   ❌ keyboardmain نامعتبر!\n";
    }
}
echo "\n";

// 5. بررسی channels
echo "5. بررسی کانال‌ها:\n";
$channels_id = select("channels", "link", null, null, "FETCH_COLUMN");
echo "   تعداد کانال‌ها: " . count($channels_id) . "\n";
echo "\n";

// 6. بررسی پنل‌های مربان
echo "6. بررسی پنل‌های مربان:\n";
if ($connect) {
    $result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM marzban_panel WHERE status = 'active'");
    $row = mysqli_fetch_assoc($result);
    echo "   پنل‌های فعال: " . $row['cnt'] . "\n";
    if ($row['cnt'] == 0) {
        echo "   ⚠️ هیچ پنل فعالی وجود ندارد! دکمه خرید پیام خطا می‌دهد.\n";
    }
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM marzban_panel WHERE status = 'active'");
    $row = $stmt->fetch();
    echo "   پنل‌های فعال: " . $row['cnt'] . "\n";
}
echo "\n";

// 7. شبیه‌سازی فشردن دکمه
echo "7. شبیه‌سازی فشردن دکمه‌ها:\n";
$test_texts = [
    'text_sell' => $datatextbot['text_sell'],
    'text_extend' => $datatextbot['text_extend'],
    'text_Purchased_services' => $datatextbot['text_Purchased_services'],
    'text_Tariff_list' => $datatextbot['text_Tariff_list'],
];

// ابتدا بررسی stripReplyStyleEmoji
require_once 'botapi.php';

foreach ($test_texts as $key => $original) {
    if ($original === '') {
        echo "   $key: ❌ مقدار خالی - دکمه‌ای نمایش داده نمی‌شود\n";
        continue;
    }
    
    $processed = $original;
    if (function_exists('rx_restorePremiumReplyText')) {
        $processed = rx_restorePremiumReplyText($processed);
    }
    if (function_exists('stripReplyStyleEmoji')) {
        $processed = stripReplyStyleEmoji($processed);
    }
    $processed = convertPersianNumbersToEnglish($processed);
    
    $match = ($processed === $original);
    echo "   $key:\n";
    echo "      اصلی:    \"$original\"\n";
    echo "      پردازش:  \"$processed\"\n";
    echo "      بایت‌ها:  " . bin2hex(mb_substr($original, 0, 20, 'UTF-8')) . "\n";
    echo "      تطابق:   " . ($match ? "✅" : "❌ نتطبیق ندارد!") . "\n";
}
echo "\n";

// 8. بررسی $statusnote
echo "8. بررسی statusnote:\n";
$statusnote = false;
if (is_array($setting) && $setting['statusnamecustom'] == 'onnamecustom') {
    $statusnote = true;
}
echo "   statusnamecustom = " . ($setting['statusnamecustom'] ?? 'ندارد') . "\n";
echo "   statusnote = " . ($statusnote ? 'true' : 'false') . "\n";
echo "   → handler اول text_sell (با شرط statusnote): " . ($statusnote ? "اجرا می‌شود" : "رد می‌شود") . "\n";
echo "   → handler دوم text_sell (بدون شرط): باید اجرا شود\n";
echo "\n";

// 9. بررسی check_active_btn
echo "9. بررسی check_active_btn:\n";
if (is_array($setting) && isset($setting['keyboardmain'])) {
    require_once 'function.php';
    if (function_exists('check_active_btn')) {
        foreach ($critical_keys as $key) {
            $active = check_active_btn($setting['keyboardmain'], $key);
            echo "   $key: " . ($active ? "✅ فعال" : "❌ غیرفعال - در keyboardmain نیست!") . "\n";
        }
    }
}
echo "\n";

// 10. خلاصه
echo "=== خلاصه مشکلات احتمالی ===\n";
$issues = [];

// بررسی مقادیر خالی textbot
foreach ($critical_keys as $key) {
    if ($datatextbot[$key] === '') {
        $issues[] = "مقدار $key در جدول textbot خالی است";
    }
}

// بررسی پنل‌های فعال
if ($connect) {
    $result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM marzban_panel WHERE status = 'active'");
    $row = mysqli_fetch_assoc($result);
    if ($row['cnt'] == 0) {
        $issues[] = "هیچ پنل مربان فعالی وجود ندارد (دکمه خرید خطا می‌دهد)";
    }
}

// بررسی guardها
if (is_array($setting)) {
    if ($setting['verifystart'] == 'onverify') {
        $issues[] = "احراز هویت فعال است (verifystart=onverify) - کاربران تأیید نشده بلاک می‌شوند";
    }
    if ($setting['roll_Status'] == 'rolleon') {
        $issues[] = "قوانین فعال است (roll_Status=rolleon) - کاربران باید قوانین را بپذیرند";
    }
    if ($setting['Bot_Status'] == 'botstatusoff') {
        $issues[] = "ربات غیرفعال است (Bot_Status=botstatusoff)";
    }
    if (count($channels_id) > 0) {
        $issues[] = "کانال‌های اجباری تنظیم شده‌اند (" . count($channels_id) . " کانال) - کاربران باید عضو شوند";
    }
    if ($setting['antispam_status'] == '1') {
        $issues[] = "ضد اسپم فعال است - ممکن است پیام‌ها را بلاک کند";
    }
}

if (!$connect) {
    $issues[] = "اتصال MySQLi قطع است - handler‌های text_sell و text_extend خطا می‌دهند";
}

if (empty($issues)) {
    echo "✅ مشکل آشکاری یافت نشد. احتمالاً مشکل مربوط به encoding متن یا وضعیت کاربر خاص است.\n";
    echo "   لاگ‌های PHP را بررسی کنید: tail -f /var/log/php_errors.log\n";
} else {
    foreach ($issues as $i => $issue) {
        echo "⚠️ " . ($i+1) . ". $issue\n";
    }
}
