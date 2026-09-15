<?php
declare(strict_types=1);
/**
 * دیاگنوستیک سریع ربات - نسخه سخت‌شده
 * این فایل در production فقط از CLI یا با احراز هویت ادمین قابل اجراست.
 * دسترسی وب بدون لاگین ادمین → 404
 */
$isCLI = (PHP_SAPI === 'cli');
if (!$isCLI) {
    require_once __DIR__ . '/lib/Security.php';
    redfox_secure_session_start();
    @require_once __DIR__ . '/config.php';
    $isAdmin = false;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && !empty($_SESSION['user'])) {
        try {
            $q = $GLOBALS['pdo']->prepare('SELECT rule FROM admin WHERE username=? LIMIT 1');
            $q->execute([$_SESSION['user']]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            $isAdmin = is_array($r) && ($r['rule'] ?? '') === 'administrator';
        } catch (Throwable $e) { $isAdmin = false; }
    }
    if (!$isAdmin) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><html><head><meta charset="utf-8"><title>404</title></head><body style="background:#0a0a0f;color:#fff;font-family:sans-serif;text-align:center;padding:60px"><h1>404 - Not Found</h1></body></html>');
    }
    // ادمین احراز شد - ادامه با نمایش HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="direction:rtl;font-family:monospace;font-size:14px;background:#1a1a2e;color:#0f0;padding:20px;">';
    echo "⚠️ حالت دیباگ با دسترسی ادمین فعال است - پس از اتمام، این فایل را از هاست حذف کنید.\n\n";
} else {
    echo '<pre style="direction:rtl;font-family:monospace;font-size:14px;background:#1a1a2e;color:#0f0;padding:20px;">';
}

echo "╔══════════════════════════════════════════╗\n";
echo "║     🔍 دیاگنوستیک سریع ربات RedFox     ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// بارگذاری تنظیمات
require_once 'config.php';

if (!$pdo) {
    echo "❌❌❌ اتصال دیتابیس برقرار نیست! بررسی کنید:\n";
    echo "   - فایل config.php تنظیمات دیتابیس صحیح دارد\n";
    echo "   - MySQL/MariaDB در حال اجراست\n";
    die();
}
echo "✅ اتصال دیتابیس: برقرار (PDO)\n";
echo "   MySQLi: " . ($connect ? "✅ متصل" : "❌ قطع - بعضی handlerها کار نمی‌کنند") . "\n\n";

// ═══════════════════════════════════════════
// بخش ۱: جدول admin
// ═══════════════════════════════════════════
echo "━━━ بخش ۱: جدول admin ━━━\n";
try {
    $admins = $pdo->query("SELECT * FROM admin")->fetchAll();
    echo "   تعداد ادمین‌ها: " . count($admins) . "\n";
    if (count($admins) === 0) {
        echo "   ❌❌❌ هیچ ادمینی تعریف نشده!\n";
        echo "   👉 باید از installer استفاده کنید یا دستی اضافه کنید\n";
    } else {
        foreach ($admins as $a) {
            echo "   - ID: {$a['id_admin']} | کاربر: {$a['username']} | سطح: {$a['rule']}\n";
        }
    }
} catch (Throwable $e) {
    echo "   ❌ خطا: " . $e->getMessage() . "\n";
    echo "   👉 جدول admin وجود ندارد! باید نصب انجام شود.\n";
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۲: جدول setting
// ═══════════════════════════════════════════
echo "━━━ بخش ۲: تنظیمات کلیدی ━━━\n";
try {
    $setting = select("setting", "*", null, null, "select");
    if (!is_array($setting)) {
        echo "   ❌❌❌ جدول setting خالی یا ناموجود!\n";
        echo "   👉 باید نصب انجام شود.\n";
    } else {
        $keys = ['Bot_Status','roll_Status','verifystart','inlinebtnmain',
                 'statusnamecustom','get_number','iran_number','antispam_status',
                 'keyboardmain','statusnoteforf'];
        foreach ($keys as $k) {
            $v = $setting[$k] ?? '⚠️ ناموجود';
            if ($k === 'keyboardmain') {
                $v = mb_substr($v, 0, 60) . '...';
            }
            echo "   $k = $v\n";
        }
    }
} catch (Throwable $e) {
    echo "   ❌ خطا: " . $e->getMessage() . "\n";
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۳: جدول textbot
// ═══════════════════════════════════════════
echo "━━━ بخش ۳: جدول textbot (متن دکمه‌ها) ━━━\n";
try {
    $rows = $pdo->query("SELECT * FROM textbot")->fetchAll();
    echo "   تعداد رکوردها: " . count($rows) . "\n";
    $critical = ['text_sell','text_extend','text_Purchased_services','text_start',
                 'text_Tariff_list','text_affiliates','accountwallet','text_usertest'];
    $found = [];
    foreach ($rows as $r) {
        $found[$r['id_text']] = $r['text'];
    }
    foreach ($critical as $key) {
        $val = $found[$key] ?? null;
        if ($val === null) {
            echo "   ❌ $key = وجود ندارد!\n";
        } elseif ($val === '') {
            echo "   ⚠️ $key = خالی!\n";
        } else {
            echo "   ✅ $key = " . mb_substr($val, 0, 40) . "\n";
        }
    }
} catch (Throwable $e) {
    echo "   ❌ خطا: " . $e->getMessage() . "\n";
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۴: بررسی keyboardmain
// ═══════════════════════════════════════════
echo "━━━ بخش ۴: بررسی ساختار keyboardmain ━━━\n";
if (is_array($setting) && isset($setting['keyboardmain'])) {
    $kb = json_decode($setting['keyboardmain'], true);
    if (!is_array($kb)) {
        echo "   ❌ keyboardmain JSON نامعتبر!\n";
    } elseif (!isset($kb['keyboard'])) {
        echo "   ❌ keyboardmain کلید 'keyboard' ندارد!\n";
    } else {
        echo "   ✅ ساختار معتبر - تعداد ردیف‌ها: " . count($kb['keyboard']) . "\n";
        foreach ($kb['keyboard'] as $i => $row) {
            $btns = [];
            foreach ($row as $btn) {
                $t = $btn['text'] ?? '?';
                $isLangKey = in_array($t, ['text_sell','text_extend','text_usertest','text_wheel_luck',
                    'text_Purchased_services','accountwallet','text_affiliates','text_Tariff_list',
                    'text_support','text_help']);
                $btns[] = $isLangKey ? "🔑$t" : "📝$t";
            }
            echo "   ردیف $i: " . implode(' | ', $btns) . "\n";
        }
    }
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۵: شبیه‌سازی کیبورد
// ═══════════════════════════════════════════
echo "━━━ بخش ۵: شبیه‌سازی ساخت کیبورد ━━━\n";
if (is_array($setting) && isset($setting['keyboardmain'])) {
    // بارگذاری textbot
    $datatextbot = [];
    foreach (['text_sell','text_extend','text_usertest','text_wheel_luck',
              'text_Purchased_services','accountwallet','text_affiliates',
              'text_Tariff_list','text_support','text_help','text_help'] as $k) {
        $datatextbot[$k] = $found[$k] ?? '';
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
        'text_extend' => $datatextbot['text_extend'],
    ];
    
    $kb = json_decode($setting['keyboardmain'], true);
    $rows = $kb['keyboard'] ?? [];
    
    // حذف text_help
    $filtered = [];
    foreach ($rows as $row) {
        $nr = [];
        foreach ($row as $btn) {
            $bt = (string)($btn['text'] ?? '');
            if ($bt === 'text_help') continue;
            $nr[] = $btn;
        }
        if ($nr) $filtered[] = $nr;
    }
    $rows = $filtered;
    
    // اعمال strtr
    $json = json_encode($rows);
    $replaced = strtr($json, $replacements);
    $final = json_decode($replaced, true);
    
    if (!is_array($final)) {
        echo "   ❌ strtr خروجی نامعتبر تولید کرد!\n";
        echo "   ورودی:  " . mb_substr($json, 0, 100) . "\n";
        echo "   خروجی:  " . mb_substr($replaced, 0, 100) . "\n";
    } else {
        echo "   ✅ کیبورد ساخته شد:\n";
        foreach ($final as $i => $row) {
            $btns = [];
            foreach ($row as $btn) {
                $btns[] = $btn['text'] ?? '?';
            }
            echo "   [$i] " . implode(' | ', $btns) . "\n";
        }
        
        // بررسی تطابق
        echo "\n   بررسی تطابق متن دکمه‌ها با textbot:\n";
        foreach ($final as $row) {
            foreach ($row as $btn) {
                $btnText = $btn['text'] ?? '';
                // پیدا کردن کلید متناظر
                foreach ($replacements as $langKey => $dbVal) {
                    if ($dbVal === $btnText && $dbVal !== '') {
                        echo "   ✅ \"$btnText\" ← $langKey\n";
                        break;
                    }
                }
            }
        }
    }
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۶: بررسی کانال‌ها و پنل‌ها
// ═══════════════════════════════════════════
echo "━━━ بخش ۶: کانال‌ها و پنل‌ها ━━━\n";
try {
    $ch = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
    echo "   کانال‌های اجباری: $ch\n";
    if ($ch > 0) echo "   ⚠️ کاربر باید عضو کانال شود\n";
} catch (Throwable $e) {
    echo "   کانال: خطا - " . $e->getMessage() . "\n";
}

try {
    $mp = $pdo->query("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'")->fetchColumn();
    echo "   پنل‌های مربان فعال: $mp\n";
    if ($mp == 0) echo "   ⚠️ هیچ پنل فعالی نیست - دکمه خرید خطا می‌دهد\n";
} catch (Throwable $e) {
    echo "   پنل مربان: خطا - " . $e->getMessage() . "\n";
}

try {
    $allp = $pdo->query("SELECT COUNT(*) FROM marzban_panel")->fetchColumn();
    echo "   کل پنل‌های مربان: $allp\n";
    if ($allp > 0) {
        $panels = $pdo->query("SELECT name_panel, status FROM marzban_panel")->fetchAll();
        foreach ($panels as $p) {
            echo "   - {$p['name_panel']} ({$p['status']})\n";
        }
    }
} catch (Throwable $e) {
    echo "   پنل: خطا - " . $e->getMessage() . "\n";
}
echo "\n";

// ═══════════════════════════════════════════
// بخش ۷: خلاصه و راه‌حل
// ═══════════════════════════════════════════
echo "━━━ بخش ۷: خلاصه مشکلات ━━━\n";
$issues = [];

// بررسی ادمین
if (count($admins ?? []) === 0) {
    $issues[] = "🔴 هیچ ادمینی تعریف نشده → دکمه مدیریت نمایش داده نمی‌شود → نمی‌توان پنل مربان تعریف کرد";
}

// بررسی MySQLi
if (!$connect) {
    $issues[] = "🔴 اتصال MySQLi قطع است → handler text_sell و text_extend خطا می‌دهند (از mysqli_query استفاده می‌کنند)";
}

// بررسی پنل مربان
if (($mp ?? 0) == 0) {
    $issues[] = "🟡 هیچ پنل مربان فعالی نیست → دکمه خرید پیام خطا می‌دهد";
}

// بررسی roll_Status
if (is_array($setting) && ($setting['roll_Status'] ?? '') === 'rolleon') {
    $issues[] = "🟡 قوانین فعال است (rolleon) → کاربر جدید باید اول قوانین را بپذیرد";
}

// بررسی verifystart
if (is_array($setting) && ($setting['verifystart'] ?? '') === 'onverify') {
    $issues[] = "🟡 احراز هویت فعال است → کاربران تأیید نشده بلاک می‌شوند";
}

// بررسی textbot
foreach ($critical as $key) {
    if (!isset($found[$key]) || $found[$key] === '') {
        $issues[] = "🔴 مقدار $key در textbot خالی یا ناموجود است";
    }
}

if (empty($issues)) {
    echo "✅ مشکل آشکاری یافت نشد.\n";
    echo "   لاگ PHP را بررسی کنید: tail -50 /var/log/php*.log\n";
} else {
    foreach ($issues as $i => $issue) {
        echo ($i+1) . ". $issue\n";
    }
}

echo "\n";
echo "━━━ راهنمای رفع مشکلات ━━━\n";
echo "
🔴 اگر ادمین ندارید:
   باید دوباره نصب را اجرا کنید:
   https://your-domain.com/installer/
   یا دستی اضافه کنید:
   INSERT INTO admin (id_admin, username, password, rule) 
   VALUES ('YOUR_TELEGRAM_ID', 'admin', '...', 'administrator');

🔴 اگر MySQLi قطع است:
   در config.php بررسی کنید که افزونه mysqli نصب باشد:
   php -m | grep mysqli
   اگر نیست: sudo apt install php-mysqli

🟡 اگر پنل مربان ندارید:
   اول مشکل ادمین را حل کنید، سپس از پنل مدیریت ربات 
   پنل مربان اضافه کنید.

🟡 اگر قوانین فعال است:
   کاربر باید دکمه '✅ قوانین را می پذیرم' را بزند
   یا roll_Status را در جدول setting به rolloff تغییر دهید.
";

if (!$isCLI) {
    echo '</pre>';
}
