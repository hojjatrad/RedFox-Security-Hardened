<?php
/**
 * ============================================================
 *  REDFOX+ Doctor — ابزار دیاگنوستیک جامع خودکار
 * ============================================================
 *  این فایل را کنار botapi.php آپلود کنید و در مرورگر باز کنید.
 *  بعد از اتمام بررسی، فوراً حذف کنید (امنیتی).
 * ============================================================
 */

// ── جلوگیری از اجرای تصادفی در محیط CLI ──
if (php_sapi_name() === 'cli') {
    echo "لطفاً از طریق مرورگر اجرا کنید.\n";
    exit;
}

// ── تنظیمات اولیه ──
error_reporting(E_ALL);
ini_set('display_errors', '0');
@ignore_user_abort(true);
@set_time_limit(120);

$results = [];
$errors = [];
$warnings = [];
$fixes_applied = [];
$version_diffs = [];

// ── توابع کمکی ──
function check($name, $status, $detail = '', $fixable = false) {
    global $results;
    $results[] = [
        'name' => $name,
        'status' => $status, // 'ok', 'error', 'warning', 'info', 'fixed'
        'detail' => $detail,
        'fixable' => $fixable,
    ];
}

function fix($name, $action) {
    global $fixes_applied;
    $fixes_applied[] = ['name' => $name, 'action' => $action];
}

// ═══════════════════════════════════════════
//  بخش ۱: اتصال دیتابیس
// ═══════════════════════════════════════════

$connect = null;
$pdo = null;
$config_loaded = false;
$dbname = '';
$usernamedb = '';
$passworddb = '';
$dbhost = 'localhost';
$APIKEY = '';
$adminnumber = '';

// تلاش برای بارگذاری config.php
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    try {
        // جلوگیری از اجرای خروجی‌های config
        ob_start();
        
        // تنظیم متغیرهای محیطی اگر نیاز باشد
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SCRIPT_NAME'] = '/rx_doctor.php';
        
        include $config_path;
        $config_loaded = true;
        
        ob_end_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        $errors[] = "خطا در بارگذاری config.php: " . $e->getMessage();
    }
}

// اگر config لود شد ولی اتصال نبود
if ($config_loaded && !($connect instanceof mysqli)) {
    // تلاش مستقیم
    if ($dbname && $usernamedb) {
        @mysqli_report(MYSQLI_REPORT_OFF);
        $connect = @mysqli_connect($dbhost, $usernamedb, $passworddb, $dbname);
        if ($connect) @mysqli_set_charset($connect, 'utf8mb4');
    }
}

// بررسی نهایی اتصال
if ($connect instanceof mysqli) {
    check('اتصال دیتابیس (MySQLi)', 'ok', "متصل به `$dbname`@$dbhost");
} else {
    check('اتصال دیتابیس (MySQLi)', 'error', 
        "اتصال MySQLi برقرار نیست! " . ($config_loaded ? "config.php لود شد ولی اتصال null است." : "config.php لود نشد."));
}

if ($pdo instanceof PDO) {
    check('اتصال دیتابیس (PDO)', 'ok', 'اتصال PDO برقرار است');
} else {
    // تلاش ساخت PDO
    if ($dbname && $usernamedb) {
        try {
            $dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $usernamedb, $passworddb, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            check('اتصال دیتابیس (PDO)', 'ok', 'PDO با موفقیت ساخته شد');
        } catch (Throwable $e) {
            check('اتصال دیتابیس (PDO)', 'error', $e->getMessage());
        }
    } else {
        check('اتصال دیتابیس (PDO)', 'error', 'اطلاعات اتصال ناقص است');
    }
}

// ═══════════════════════════════════════════
//  بخش ۲: وضعیت PHP و ماژول‌ها
// ═══════════════════════════════════════════

check('نسخه PHP', 'info', phpversion());

$required_exts = ['mysqli', 'pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl'];
foreach ($required_exts as $ext) {
    if (extension_loaded($ext)) {
        check("ماژول $ext", 'ok');
    } else {
        check("ماژول $ext", 'error', "این ماژول نصب نیست!");
    }
}

// ═══════════════════════════════════════════
//  بخش ۳: فایل‌های حیاتی
// ═══════════════════════════════════════════

$critical_files = [
    'botapi.php' => 'ورودی اصلی ربات',
    'config.php' => 'تنظیمات اتصال',
    're/rx/index/compiled.php' => 'بوت‌استرپ اصلی',
    're/rx/keyboard/compiled.php' => 'سیستم کیبورد',
    're/rx/function/compiled.php' => 'توابع',
    'text.json' => 'فایل زبان',
    'table.php' => 'ساختار دیتابیس',
    'installer/index.php' => 'نصب‌کننده',
];

foreach ($critical_files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $lines = count(file($path));
        check("فایل: $file", 'ok', "$desc — $lines خط");
    } else {
        check("فایل: $file", 'error', "$desc — فایل وجود ندارد!");
    }
}

// ═══════════════════════════════════════════
//  بخش ۴: بررسی جداول دیتابیس
// ═══════════════════════════════════════════

if ($connect instanceof mysqli) {
    $required_tables = ['setting', 'admin', 'textbot', 'users', 'marzban_panel', 'channels'];
    $existing_tables = [];
    
    $res = mysqli_query($connect, "SHOW TABLES");
    if ($res) {
        while ($row = mysqli_fetch_row($res)) {
            $existing_tables[] = $row[0];
        }
    }
    
    foreach ($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            $count_res = mysqli_query($connect, "SELECT COUNT(*) as c FROM `$table`");
            $count = $count_res ? mysqli_fetch_assoc($count_res)['c'] : '?';
            check("جدول: $table", 'ok', "$count رکورد");
        } else {
            check("جدول: $table", 'error', "جدول وجود ندارد!");
        }
    }
    
    // بررسی جداول دیگر
    $other_tables = array_diff($existing_tables, $required_tables);
    if (!empty($other_tables)) {
        check('جداول اضافی', 'info', implode(', ', $other_tables));
    }
}

// ═══════════════════════════════════════════
//  بخش ۵: بررسی تنظیمات (setting)
// ═══════════════════════════════════════════

$settings = [];
if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT * FROM setting LIMIT 1");
    if ($res) {
        $settings = mysqli_fetch_assoc($res);
        if ($settings) {
            // مقادیر بحرانی
            $critical_settings = [
                'Bot_Status' => ['expected' => 'botstatuson', 'desc' => 'وضعیت ربات'],
                'roll_Status' => ['expected' => 'rolleon', 'desc' => 'وضعیت محدودیت'],
                'verifystart' => ['expected' => 'offverify', 'desc' => 'تأیید شروع'],
                'inlinebtnmain' => ['expected' => 'offinline', 'desc' => 'دکمه inline'],
                'statusnamecustom' => ['expected' => 'offnamecustom', 'desc' => 'نام سفارشی'],
            ];
            
            foreach ($critical_settings as $key => $info) {
                if (isset($settings[$key])) {
                    $val = $settings[$key];
                    if ($val === $info['expected']) {
                        check("تنظیم: {$info['desc']}", 'ok', "$key = $val");
                    } else {
                        check("تنظیم: {$info['desc']}", 'warning', 
                            "$key = «$val» (انتظار: {$info['expected']})", true);
                    }
                } else {
                    check("تنظیم: {$info['desc']}", 'error', "ستون $key وجود ندارد!");
                }
            }
            
            // نمایش همه ستون‌ها
            check('ستون‌های setting', 'info', implode(', ', array_keys($settings)));
        } else {
            check('جدول setting', 'error', 'هیچ رکوردی ندارد! باید مقدار پیش‌فرض درج شود.', true);
        }
    }
}

// ═══════════════════════════════════════════
//  بخش ۶: بررسی ادمین ← کلیدی‌ترین بخش!
// ═══════════════════════════════════════════

$admin_records = [];
$admin_found = false;

if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT * FROM admin");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $admin_records[] = $row;
        }
    }
    
    if (empty($admin_records)) {
        check('جدول admin', 'error', 
            '🚨 جدول admin خالی است! ← علت اصلی نبودن دکمه مدیریت', true);
        
        // تلاش برای پیدا کردن ID ادمین از تنظیمات
        $admin_id_from_settings = '';
        if (isset($settings['adminnumber'])) {
            $admin_id_from_settings = $settings['adminnumber'];
        }
        
        if ($admin_id_from_settings) {
            check('آیدی ادمین از تنظیمات', 'info', $admin_id_from_settings);
        }
        
        // بررسی installer برای الگوی INSERT
        $installer_path = __DIR__ . '/installer/index.php';
        if (file_exists($installer_path)) {
            $installer_content = file_get_contents($installer_path);
            if (preg_match('/INSERT\s+(?:INTO\s+)?admin.*?VALUES\s*\(([^)]+)\)/si', $installer_content, $m)) {
                check('الگوی INSERT ادمین', 'info', 'found in installer');
            }
        }
    } else {
        check('جدول admin', 'ok', count($admin_records) . ' ادمین');
        foreach ($admin_records as $i => $adm) {
            $adm_id = $adm['id_admin'] ?? 'N/A';
            $adm_user = $adm['username'] ?? 'N/A';
            $adm_rule = $adm['rule'] ?? 'N/A';
            check("ادمین #" . ($i+1), 'info', "ID=$adm_id, user=$adm_user, rule=$adm_rule");
        }
        $admin_found = true;
    }
    
    // بررسی ساختار جدول admin
    $res = mysqli_query($connect, "DESCRIBE admin");
    if ($res) {
        $cols = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cols[] = $row['Field'];
        }
        check('ستون‌های جدول admin', 'info', implode(', ', $cols));
    }
}

// ═══════════════════════════════════════════
//  بخش ۷: بررسی textbot (فایل زبان دیتابیس)
// ═══════════════════════════════════════════

$textbot_data = [];
if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT * FROM textbot");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $textbot_data[] = $row;
        }
    }
    
    if (empty($textbot_data)) {
        check('جدول textbot', 'error', 'جدول textbot خالی است! دکمه‌ها متن نخواهند داشت.', true);
    } else {
        check('جدول textbot', 'ok', count($textbot_data) . ' رکورد');
        
        // بررسی کلیدهای حیاتی
        $critical_keys = ['text_sell', 'text_extend', 'text_Purchased_services', 'text_support'];
        $textbot_map = [];
        foreach ($textbot_data as $row) {
            if (isset($row['key_name']) && isset($row['value'])) {
                $textbot_map[$row['key_name']] = $row['value'];
            }
        }
        
        // اگر ساختار متفاوت است
        if (empty($textbot_map) && !empty($textbot_data)) {
            $first = $textbot_data[0];
            check('ساختار textbot', 'info', 'ستون‌ها: ' . implode(', ', array_keys($first)));
            
            // حدس ستون‌ها
            $cols = array_keys($first);
            if (count($cols) >= 2) {
                foreach ($textbot_data as $row) {
                    $textbot_map[$row[$cols[0]]] = $row[$cols[1]];
                }
            }
        }
        
        foreach ($critical_keys as $key) {
            if (isset($textbot_map[$key])) {
                $val = mb_substr($textbot_map[$key], 0, 30);
                check("textbot: $key", 'ok', "«$val»");
            } else {
                check("textbot: $key", 'warning', "این کلید وجود ندارد!");
            }
        }
    }
    
    // ساختار جدول
    $res = mysqli_query($connect, "DESCRIBE textbot");
    if ($res) {
        $cols = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cols[] = $row['Field'] . '(' . $row['Type'] . ')';
        }
        check('ستون‌های textbot', 'info', implode(', ', $cols));
    }
}

// ═══════════════════════════════════════════
//  بخش ۸: بررسی پنل مربان (Marzban)
// ═══════════════════════════════════════════

if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT * FROM marzban_panel");
    if ($res) {
        $panels = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $panels[] = $row;
        }
        
        if (empty($panels)) {
            check('جدول marzban_panel', 'warning', 
                'هیچ پنلی تعریف نشده! دکمه خرید کار نخواهد کرد.');
        } else {
            $active_panels = array_filter($panels, fn($p) => ($p['status'] ?? '') === 'active');
            check('پنل‌های مربان', 'info', 
                count($panels) . ' پنل، ' . count($active_panels) . ' فعال');
            
            if (empty($active_panels)) {
                check('پنل فعال', 'error', 
                    '🚨 هیچ پنل فعالی نیست! ← علت خطا در دکمه خرید', true);
            } else {
                foreach ($active_panels as $p) {
                    $url = $p['url'] ?? $p['domain'] ?? 'N/A';
                    check('پنل فعال', 'ok', $url);
                }
            }
            
            // نمایش ستون‌ها
            if (!empty($panels)) {
                check('ستون‌های marzban_panel', 'info', implode(', ', array_keys($panels[0])));
            }
        }
    } else {
        check('جدول marzban_panel', 'error', 'جدول وجود ندارد یا خطا در کوئری!');
    }
}

// ═══════════════════════════════════════════
//  بخش ۹: بررسی کانال‌ها
// ═══════════════════════════════════════════

if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT * FROM channels");
    if ($res) {
        $channels = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $channels[] = $row;
        }
        
        if (empty($channels)) {
            check('کانال‌های اجباری', 'info', 'هیچ کانال اجباری تعریف نشده (خوب)');
        } else {
            check('کانال‌های اجباری', 'info', count($channels) . ' کانال');
            foreach ($channels as $ch) {
                $ch_name = $ch['channel_name'] ?? $ch['name'] ?? 'N/A';
                $ch_id = $ch['channel_id'] ?? $ch['chat_id'] ?? 'N/A';
                check("کانال", 'info', "$ch_name ($ch_id)");
            }
        }
    }
}

// ═══════════════════════════════════════════
//  بخش ۱۰: بررسی فایل زبان (text.json)
// ═══════════════════════════════════════════

$textjson_path = __DIR__ . '/text.json';
if (file_exists($textjson_path)) {
    $textjson_content = file_get_contents($textjson_path);
    $textjson = json_decode($textjson_content, true);
    
    if ($textjson) {
        check('فایل text.json', 'ok', 'معتبر — ' . count($textjson) . ' بخش');
        
        // بررسی کلیدهای مهم
        $important_keys = [
            'Admin/textpaneladmin' => 'متن دکمه مدیریت',
            'Main/text_sell' => 'متن دکمه خرید',
            'Main/text_extend' => 'متن دکمه تمدید',
        ];
        
        foreach ($important_keys as $path => $desc) {
            $parts = explode('/', $path);
            $val = $textjson;
            foreach ($parts as $p) {
                $val = $val[$p] ?? null;
            }
            if ($val) {
                check("text.json: $desc", 'ok', "«$val»");
            } else {
                check("text.json: $desc", 'warning', "کلید $path یافت نشد");
            }
        }
    } else {
        check('فایل text.json', 'error', 'JSON نامعتبر! خطا: ' . json_last_error_msg());
    }
} else {
    check('فایل text.json', 'error', 'فایل text.json وجود ندارد!');
}

// ═══════════════════════════════════════════
//  بخش ۱۱: شبیه‌سازی ساخت کیبورد
// ═══════════════════════════════════════════

if ($connect instanceof mysqli && !empty($textbot_data)) {
    // شبیه‌سازی آنچه keyboard/compiled.php انجام می‌دهد
    
    // ۱. ساخت $datatextbot
    $datatextbot = [];
    foreach ($textbot_data as $row) {
        $cols = array_keys($row);
        if (count($cols) >= 2) {
            $datatextbot[$row[$cols[0]]] = $row[$cols[1]];
        }
    }
    
    // ۲. بارگذاری text.json
    $textbotlang = [];
    if (file_exists($textjson_path)) {
        $textbotlang = json_decode(file_get_contents($textjson_path), true) ?: [];
    }
    
    // ۳. شبیه‌سازی replacements
    $replacements_keys = ['text_sell', 'text_extend', 'text_Purchased_services', 'text_support'];
    $replacements = [];
    foreach ($replacements_keys as $key) {
        if (isset($datatextbot[$key])) {
            $replacements[$key] = $datatextbot[$key];
        }
    }
    check('شبیه‌سازی replacements', 'info', count($replacements) . ' کلید: ' . implode(', ', array_keys($replacements)));
    
    // ۴. بررسی دکمه ادمین
    if (!empty($admin_records)) {
        $test_admin_id = $admin_records[0]['id_admin'] ?? 0;
        
        // شبیه‌سازی select()
        $admin_check = mysqli_query($connect, "SELECT COUNT(*) as c FROM admin WHERE id_admin = '$test_admin_id'");
        $admin_count = $admin_check ? mysqli_fetch_assoc($admin_check)['c'] : 0;
        
        if ($admin_count > 0) {
            $admin_btn_text = $textbotlang['Admin']['textpaneladmin'] ?? 'NOT SET';
            check('شبیه‌سازی دکمه ادمین', 'ok', 
                "admin_id=$test_admin_id → COUNT=$admin_count → دکمه: «$admin_btn_text»");
        } else {
            check('شبیه‌سازی دکمه ادمین', 'error', 
                "admin_id=$test_admin_id → COUNT=0! select() برمی‌گرداند 0");
        }
    } else {
        check('شبیه‌سازی دکمه ادمین', 'error', 
            'ادمینی نیست! دکمه مدیریت نمایش داده نمی‌شود.');
    }
    
    // ۵. بررسی handler text_sell
    if (isset($datatextbot['text_sell'])) {
        $expected_text = $datatextbot['text_sell'];
        check('شبیه‌سازی handler text_sell', 'info', 
            "match condition: \$text == «$expected_text»");
        
        // بررسی وجود پنل فعال برای handler
        $panel_check = mysqli_query($connect, "SELECT COUNT(*) as c FROM marzban_panel WHERE status='active'");
        $panel_count = $panel_check ? mysqli_fetch_assoc($panel_check)['c'] : 0;
        
        if ($panel_count > 0) {
            check('handler text_sell: پنل فعال', 'ok', "$panel_count پنل فعال");
        } else {
            check('handler text_sell: پنل فعال', 'error', 
                '🚨 پنل فعال نیست! handler خطا می‌دهد.');
        }
    }
}

// ═══════════════════════════════════════════
//  بخش ۱۲: تست اتصال به API تلگرام
// ═══════════════════════════════════════════

if ($APIKEY) {
    $masked_key = substr($APIKEY, 0, 10) . '...' . substr($APIKEY, -5);
    check('توکن ربات', 'info', $masked_key);
    
    if (function_exists('curl_init')) {
        $ch = @curl_init("https://api.telegram.org/bot{$APIKEY}/getMe");
        @curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = @curl_exec($ch);
        $http_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $bot_name = $data['result']['username'] ?? 'N/A';
            check('اتصال تلگرام API', 'ok', "ربات @$bot_name فعال است");
        } else {
            check('اتصال تلگرام API', 'error', "HTTP $http_code — توکن نامعتبر یا مشکل شبکه");
        }
    }
} else {
    check('توکن ربات', 'error', 'توکن ربات تنظیم نشده!');
}

if ($adminnumber) {
    check('آیدی ادمین (config)', 'info', $adminnumber);
}

// ═══════════════════════════════════════════
//  بخش ۱۳: مقایسه با نسخه‌های قبلی
// ═══════════════════════════════════════════

// بررسی تفاوت‌های کلیدی نسخه جدید vs قبلی

$version_diffs = [];

// ۱. config.php: قبلی مستقیم connect می‌کرد، جدید try-catch + env
if ($config_loaded) {
    $config_content = file_get_contents($config_path);
    
    if (strpos($config_content, 'rx_env') !== false) {
        $version_diffs[] = [
            'item' => 'config.php',
            'change' => 'جدید: credentials از متغیر محیطی خوانده می‌شود (rx_env)',
            'impact' => 'اگر ENV تنظیم نشده و $dbname خالی باشد → اتصال null',
            'risk' => 'high',
        ];
    }
    
    if (strpos($config_content, 'HostingSecrets') !== false) {
        $version_diffs[] = [
            'item' => 'config.php',
            'change' => 'جدید: نیازمند lib/Security.php, HostingSecrets.php, Secrets.php, Observability.php, SchemaGuard.php',
            'impact' => 'اگر هر کدام نباشد → fatal error → ربات سکوت',
            'risk' => 'high',
        ];
    }
    
    if (strpos($config_content, 'maintenance.flag') !== false) {
        $version_diffs[] = [
            'item' => 'config.php',
            'change' => 'جدید: بررسی maintenance.flag',
            'impact' => 'اگر فایل storage/maintenance.flag وجود داشته باشد → 503',
            'risk' => 'medium',
        ];
    }
    
    if (strpos($config_content, 'BOT_PANEL_VERIFY_TLS') !== false) {
        $version_diffs[] = [
            'item' => 'config.php',
            'change' => 'جدید: TLS verification اجباری',
            'impact' => 'اگر SSL ندارید → اتصال به API تلگرام/مربان fail',
            'risk' => 'medium',
        ];
    }
    
    if (strpos($config_content, 'redfox_secure_session_start') !== false) {
        $version_diffs[] = [
            'item' => 'config.php',
            'change' => 'جدید: session امن + CSRF + security headers',
            'impact' => 'بر panel/ اثر می‌گذارد ولی نه botapi.php',
            'risk' => 'low',
        ];
    }
}

// ۲. بررسی فایل‌های lib جدید
$new_lib_files = [
    'lib/Security.php' => 'توابع امنیتی (redfox_exception_fingerprint, redfox_normalize_domain و...)',
    'lib/HostingSecrets.php' => 'خواندن secrets از هاست',
    'lib/Secrets.php' => 'مدیریت secrets',
    'lib/Observability.php' => 'لاگ‌گیری',
    'lib/SchemaGuard.php' => 'بررسی ساختار دیتابیس',
    'proxy.php' => 'پروکسی (include شده در config.php)',
];

foreach ($new_lib_files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $lines = count(file($path));
        check("فایل جدید: $file", 'ok', "$desc — $lines خط");
    } else {
        $version_diffs[] = [
            'item' => $file,
            'change' => "جدید: نیازمند $file ($desc)",
            'impact' => 'فایل وجود ندارد! ← fatal error در config.php',
            'risk' => 'critical',
        ];
    }
}

// ۳. installer: INSERT admin
$installer_path = __DIR__ . '/installer/index.php';
if (file_exists($installer_path)) {
    $installer_content = file_get_contents($installer_path);
    
    if (strpos($installer_content, "INSERT") !== false && strpos($installer_content, "admin") !== false) {
        // بررسی اینکه آیا installer ادمین را insert می‌کند
        if (preg_match('/admin.*?INSERT|INSERT.*?admin/si', $installer_content)) {
            $version_diffs[] = [
                'item' => 'installer',
                'change' => 'installer ادمین را درج می‌کند',
                'impact' => 'اگر نصب موفق بوده باید ادمین وجود داشته باشد',
                'risk' => $admin_found ? 'low' : 'high',
            ];
        }
    }
}

// ۴. table.php: ساختار جداول
$table_path = __DIR__ . '/table.php';
if (file_exists($table_path)) {
    $table_content = file_get_contents($table_path);
    
    // بررسی default settings
    if (preg_match("/INSERT.*setting.*VALUES.*?'(.*?)'.*?'(.*?)'.*?'(.*?)'/si", $table_content, $m)) {
        // فقط اطلاعاتی
    }
    
    // بررسی roll_Status پیش‌فرض
    if (strpos($table_content, "'rolleon'") !== false) {
        $version_diffs[] = [
            'item' => 'table.php',
            'change' => 'roll_Status پیش‌فرض = rolleon',
            'impact' => 'کاربران جدید باید verify شوند ولی ادمین bypass می‌شود',
            'risk' => 'medium',
        ];
    }
}

// ═══════════════════════════════════════════
//  بخش ۱۴: تشخیص علت اصلی
// ═══════════════════════════════════════════

$root_causes = [];

// ۱. اتصال DB
if (!($connect instanceof mysqli)) {
    $root_causes[] = [
        'cause' => 'اتصال MySQLi برقرار نیست',
        'explanation' => 'بدون اتصال دیتابیس، هیچ handler‌ای کار نمی‌کند. تمام handler‌هایی که از mysqli_query استفاده می‌کنند fatal error می‌گیرند.',
        'fix' => 'اطمینان حاصل کنید config.php اطلاعات اتصال صحیح دارد. در نسخه جدید از rx_env() استفاده می‌شود.',
    ];
}

// ۲. ادمین
if (!$admin_found) {
    $root_causes[] = [
        'cause' => '🚨 جدول admin خالی است',
        'explanation' => 'دکمه مدیریت نمایش داده نمی‌شود چون admin_idss = select("admin", "*", "id_admin", $from_id, "count") → 0',
        'fix' => 'ادمین را به جدول admin اضافه کنید',
    ];
}

// ۳. پنل مربان
$panel_check2 = null;
if ($connect instanceof mysqli) {
    $res = mysqli_query($connect, "SELECT COUNT(*) as c FROM marzban_panel WHERE status='active'");
    $panel_check2 = $res ? mysqli_fetch_assoc($res)['c'] : 0;
}
if ($panel_check2 !== null && $panel_check2 == 0) {
    $root_causes[] = [
        'cause' => 'پنل مربان فعال وجود ندارد',
        'explanation' => 'handler text_sell اول پنل فعال چک می‌کند. اگر نباشد خطای «پنلی تعریف نشده» برمی‌گرداند.',
        'fix' => 'از دکمه مدیریت → پنل مربان → افزودن پنل جدید',
    ];
}

// ۴. فایل‌های lib جدید
foreach ($new_lib_files as $file => $desc) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $root_causes[] = [
            'cause' => "فایل $file وجود ندارد",
            'explanation' => "config.php این فایل را require می‌کند. بدون آن fatal error رخ می‌دهد.",
            'fix' => "فایل $file را از نسخه ZIP جدید آپلود کنید",
        ];
    }
}

// ═══════════════════════════════════════════
//  بخش ۱۵: اصلاح خودکار (اختیاری)
// ═══════════════════════════════════════════

$auto_fix = isset($_GET['fix']) && $_GET['fix'] === '1';
$fix_nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';

// ایجاد nonce ساده
$expected_nonce = md5('rxdoctor' . date('YmdH'));

if ($auto_fix && $fix_nonce === $expected_nonce) {
    // اصلاح ۱: اگر setting خالی است
    if ($connect instanceof mysqli && empty($settings)) {
        $default_settings = [
            'Bot_Status' => 'botstatuson',
            'roll_Status' => 'rolleon',
            'verifystart' => 'offverify',
            'inlinebtnmain' => 'offinline',
            'statusnamecustom' => 'offnamecustom',
        ];
        
        $cols = implode('`, `', array_keys($default_settings));
        $vals = implode("', '", array_values($default_settings));
        
        $res = @mysqli_query($connect, "INSERT INTO setting (`$cols`) VALUES ('$vals')");
        if ($res) {
            fix('جدول setting', 'مقادیر پیش‌فرض درج شد');
            // بارگذاری مجدد
            $res = mysqli_query($connect, "SELECT * FROM setting LIMIT 1");
            $settings = mysqli_fetch_assoc($res);
        }
    }
    
    // اصلاح ۲: اگر ادمین نیست ولی adminnumber در config هست
    if ($connect instanceof mysqli && !$admin_found && $adminnumber) {
        $res = @mysqli_query($connect, 
            "INSERT INTO admin (id_admin, username, password, rule) VALUES ('$adminnumber', 'admin', '', 'administrator')");
        if ($res) {
            fix('جدول admin', "ادمین $adminnumber اضافه شد");
            $admin_found = true;
        }
    }
}

// ═══════════════════════════════════════════
//  خروجی HTML
// ═══════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REDFOX+ Doctor — تشخیص خودکار مشکلات</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #0a0a0f;
            color: #e0e0e0;
            line-height: 1.7;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        
        h1 {
            text-align: center;
            color: #ff4444;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        /* ── کارت‌ها ── */
        .card {
            background: #12121a;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 18px;
            color: #4fc3f7;
            margin-bottom: 15px;
            border-bottom: 1px solid #222;
            padding-bottom: 10px;
        }
        
        /* ── ردیف‌های نتیجه ── */
        .check-row {
            display: flex;
            align-items: flex-start;
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            font-size: 14px;
        }
        .check-row:hover { background: #1a1a25; }
        .check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-left: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .check-icon.ok { background: #1b5e20; color: #4caf50; }
        .check-icon.error { background: #b71c1c; color: #ff5252; }
        .check-icon.warning { background: #e65100; color: #ffab40; }
        .check-icon.info { background: #0d47a1; color: #64b5f6; }
        .check-icon.fixed { background: #1a237e; color: #7c4dff; }
        .check-name { font-weight: 600; min-width: 200px; }
        .check-detail { color: #999; font-size: 13px; margin-right: 10px; flex: 1; }
        
        /* ── علت اصلی ── */
        .root-cause {
            background: linear-gradient(135deg, #1a0000, #2d0000);
            border: 2px solid #ff1744;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .root-cause h2 { color: #ff1744; }
        .cause-item {
            background: rgba(255, 23, 68, 0.1);
            border: 1px solid #ff1744;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .cause-title {
            font-size: 16px;
            font-weight: bold;
            color: #ff6e40;
            margin-bottom: 8px;
        }
        .cause-explain { color: #ccc; font-size: 14px; margin-bottom: 8px; }
        .cause-fix {
            background: #1b5e20;
            color: #a5d6a7;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        .cause-fix::before { content: '🔧 '; }
        
        /* ── تفاوت نسخه‌ها ── */
        .diff-item {
            background: #1a1a25;
            border-right: 4px solid #ff9800;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 0 8px 8px 0;
        }
        .diff-item.high { border-right-color: #ff1744; }
        .diff-item.medium { border-right-color: #ff9800; }
        .diff-item.low { border-right-color: #4caf50; }
        .diff-item.critical { border-right-color: #ff0000; background: #2d0000; }
        .diff-name { font-weight: 600; color: #4fc3f7; }
        .diff-change { color: #e0e0e0; font-size: 14px; margin: 4px 0; }
        .diff-impact { color: #ffab40; font-size: 13px; }
        .diff-risk {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }
        .diff-risk.critical { background: #ff0000; color: #fff; }
        .diff-risk.high { background: #ff1744; color: #fff; }
        .diff-risk.medium { background: #ff9800; color: #000; }
        .diff-risk.low { background: #4caf50; color: #fff; }
        
        /* ── خلاصه ── */
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .summary-box {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
        }
        .summary-box .num { font-size: 32px; font-weight: bold; }
        .summary-box .label { font-size: 12px; color: #888; margin-top: 4px; }
        .summary-box.ok { background: #1b3a1b; }
        .summary-box.ok .num { color: #4caf50; }
        .summary-box.error { background: #3a1b1b; }
        .summary-box.error .num { color: #ff5252; }
        .summary-box.warning { background: #3a2a1b; }
        .summary-box.warning .num { color: #ffab40; }
        .summary-box.info { background: #1b2a3a; }
        .summary-box.info .num { color: #64b5f6; }
        
        /* ── دکمه‌ها ── */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-fix {
            background: linear-gradient(135deg, #ff1744, #d50000);
            color: #fff;
            margin: 10px 5px;
        }
        .btn-fix:hover { opacity: 0.9; }
        .btn-danger {
            background: #333;
            color: #ff5252;
            margin: 10px 5px;
        }
        
        /* ── اسکریپت دکمه‌ها ── */
        .sim-keyboard {
            background: #1a1a25;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }
        .sim-btn {
            display: inline-block;
            background: #2a2a3a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 8px 16px;
            margin: 4px;
            font-size: 14px;
            color: #e0e0e0;
        }
        .sim-btn.admin { border-color: #ff9800; color: #ff9800; }
        .sim-btn.missing { border-color: #ff1744; color: #ff1744; text-decoration: line-through; }
        
        @media (max-width: 600px) {
            .summary { grid-template-columns: repeat(2, 1fr); }
            .check-name { min-width: 120px; font-size: 13px; }
        }
        
        .timestamp {
            text-align: center;
            color: #555;
            font-size: 12px;
            margin-top: 20px;
        }
        
        .alert-box {
            background: #2d0000;
            border: 1px solid #ff1744;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-size: 15px;
            color: #ff8a80;
        }
    </style>
</head>
<body>
<div class="container">

<h1>🔴 REDFOX+ Doctor</h1>
<p class="subtitle">تشخیص خودکار مشکلات ربات — <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
// ── محاسبه آمار ──
$counts = ['ok' => 0, 'error' => 0, 'warning' => 0, 'info' => 0, 'fixed' => 0];
foreach ($results as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
}
?>

<!-- خلاصه -->
<div class="summary">
    <div class="summary-box ok">
        <div class="num"><?php echo $counts['ok']; ?></div>
        <div class="label">✅ سالم</div>
    </div>
    <div class="summary-box error">
        <div class="num"><?php echo $counts['error']; ?></div>
        <div class="label">❌ خطا</div>
    </div>
    <div class="summary-box warning">
        <div class="num"><?php echo $counts['warning']; ?></div>
        <div class="label">⚠️ هشدار</div>
    </div>
    <div class="summary-box info">
        <div class="num"><?php echo $counts['info']; ?></div>
        <div class="label">ℹ️ اطلاعات</div>
    </div>
</div>

<?php if (!empty($root_causes)): ?>
<!-- علل اصلی -->
<div class="root-cause">
    <h2>🚨 علل اصلی مشکلات</h2>
    <?php foreach ($root_causes as $rc): ?>
    <div class="cause-item">
        <div class="cause-title"><?php echo htmlspecialchars($rc['cause']); ?></div>
        <div class="cause-explain"><?php echo htmlspecialchars($rc['explanation']); ?></div>
        <div class="cause-fix"><?php echo htmlspecialchars($rc['fix']); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($fixes_applied)): ?>
<div class="alert-box">
    ✅ <?php echo count($fixes_applied); ?> مورد خودکار اصلاح شد:
    <ul>
    <?php foreach ($fixes_applied as $f): ?>
        <li><strong><?php echo htmlspecialchars($f['name']); ?>:</strong> <?php echo htmlspecialchars($f['action']); ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- نتایج کامل -->
<div class="card">
    <h2>📊 نتایج کامل بررسی</h2>
    <?php
    $current_section = '';
    foreach ($results as $r):
        // تشخیص بخش از نام
        $section = 'other';
        $name = $r['name'];
        if (strpos($name, 'اتصال دیتابیس') !== false || strpos($name, 'ماژول') !== false) $section = 'env';
        elseif (strpos($name, 'فایل:') !== false) $section = 'files';
        elseif (strpos($name, 'جدول:') !== false || strpos($name, 'ستون‌') !== false) $section = 'db';
        elseif (strpos($name, 'تنظیم:') !== false) $section = 'settings';
        elseif (strpos($name, 'ادمین') !== false || strpos($name, 'جدول admin') !== false) $section = 'admin';
        elseif (strpos($name, 'textbot') !== false) $section = 'textbot';
        elseif (strpos($name, 'پنل') !== false || strpos($name, 'marzban') !== false) $section = 'panel';
        elseif (strpos($name, 'کانال') !== false) $section = 'channels';
        elseif (strpos($name, 'text.json') !== false) $section = 'textjson';
        elseif (strpos($name, 'شبیه‌سازی') !== false || strpos($name, 'handler') !== false) $section = 'simulation';
        elseif (strpos($name, 'توکن') !== false || strpos($name, 'تلگرام') !== false) $section = 'telegram';
        elseif (strpos($name, 'نسخه') !== false) $section = 'version';
        
        if ($section !== $current_section):
            $current_section = $section;
            $section_titles = [
                'env' => '🖥️ محیط اجرا',
                'files' => '📁 فایل‌ها',
                'db' => '🗄️ دیتابیس',
                'settings' => '⚙️ تنظیمات',
                'admin' => '👤 ادمین',
                'textbot' => '📝 فایل زبان (textbot)',
                'panel' => '🌐 پنل مربان',
                'channels' => '📢 کانال‌ها',
                'textjson' => '📄 text.json',
                'simulation' => '🎮 شبیه‌سازی',
                'telegram' => '🤖 تلگرام',
                'version' => '🔄 نسخه',
                'other' => '📋 سایر',
            ];
    ?>
    <h3 style="color: #4fc3f7; margin: 15px 0 8px; font-size: 15px;">
        <?php echo $section_titles[$section] ?? '📋'; ?>
    </h3>
    <?php endif; ?>
    
    <div class="check-row">
        <div class="check-icon <?php echo $r['status']; ?>">
            <?php
            $icons = ['ok' => '✓', 'error' => '✗', 'warning' => '!', 'info' => 'i', 'fixed' => '⚡'];
            echo $icons[$r['status']] ?? '?';
            ?>
        </div>
        <div class="check-name"><?php echo htmlspecialchars($r['name']); ?></div>
        <div class="check-detail"><?php echo htmlspecialchars($r['detail']); ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- تفاوت نسخه‌ها -->
<?php if (!empty($version_diffs)): ?>
<div class="card">
    <h2>🔄 تفاوت‌های نسخه جدید با قبلی</h2>
    <p style="color: #888; margin-bottom: 15px; font-size: 13px;">
        این تغییرات ممکن است علت مشکلات باشند:
    </p>
    <?php foreach ($version_diffs as $diff): ?>
    <div class="diff-item <?php echo $diff['risk']; ?>">
        <div class="diff-name"><?php echo htmlspecialchars($diff['item']); ?></div>
        <div class="diff-change"><?php echo htmlspecialchars($diff['change']); ?></div>
        <div class="diff-impact">اثر: <?php echo htmlspecialchars($diff['impact']); ?></div>
        <span class="diff-risk <?php echo $diff['risk']; ?>">
            <?php 
            $risk_labels = ['critical' => 'بحرانی', 'high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'];
            echo $risk_labels[$diff['risk']] ?? $diff['risk']; 
            ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- شبیه‌سازی کیبورد -->
<?php if ($connect instanceof mysqli && !empty($textbot_data)): ?>
<div class="card">
    <h2>🎮 شبیه‌سازی کیبورد ربات</h2>
    <p style="color: #888; margin-bottom: 10px; font-size: 13px;">
        این کیبوردی است که کاربر باید ببیند:
    </p>
    <div class="sim-keyboard">
        <?php
        $datatextbot_sim = [];
        foreach ($textbot_data as $row) {
            $cols = array_keys($row);
            if (count($cols) >= 2) $datatextbot_sim[$row[$cols[0]]] = $row[$cols[1]];
        }
        
        $keyboard_buttons = [
            ['key' => 'text_sell', 'emoji' => '🔐'],
            ['key' => 'text_extend', 'emoji' => '🔄'],
            ['key' => 'text_Purchased_services', 'emoji' => '📋'],
            ['key' => 'text_support', 'emoji' => '💬'],
        ];
        
        foreach ($keyboard_buttons as $btn):
            $key = $btn['key'];
            $text = $datatextbot_sim[$key] ?? null;
        ?>
            <span class="sim-btn <?php echo $text ? '' : 'missing'; ?>">
                <?php echo $text ? htmlspecialchars($text) : "❌ $key (موجود نیست)"; ?>
            </span>
        <?php endforeach; ?>
        
        <?php if ($admin_found): ?>
            <br><br>
            <span class="sim-btn admin">👨‍💼 پنل مدیریت</span>
            <span style="color: #4caf50; font-size: 12px;">← ادمین باید این را ببیند</span>
        <?php else: ?>
            <br><br>
            <span class="sim-btn missing">👨‍💼 پنل مدیریت</span>
            <span style="color: #ff1744; font-size: 12px;">← نمایش داده نمی‌شود! (ادمین در DB نیست)</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- اقدامات -->
<div class="card">
    <h2>🛠️ اقدامات</h2>
    
    <?php if (!$admin_found && $connect instanceof mysqli && $adminnumber): ?>
    <a class="btn btn-fix" href="?fix=1&nonce=<?php echo $expected_nonce; ?>">
        ⚡ اصلاح خودکار: اضافه کردن ادمین
    </a>
    <?php endif; ?>
    
    <?php if ($connect instanceof mysqli && empty($settings)): ?>
    <a class="btn btn-fix" href="?fix=1&nonce=<?php echo $expected_nonce; ?>">
        ⚡ اصلاح خودکار: تنظیمات پیش‌فرض
    </a>
    <?php endif; ?>
    
    <a class="btn btn-danger" href="javascript:if(confirm('آیا مطمئنید؟'))fetch('?delete=1&nonce=<?php echo $expected_nonce; ?>').then(()=>{alert('حذف شد. این صفحه را ببندید.');window.close();})">
        🗑️ حذف این فایل (امنیتی)
    </a>
</div>

<p class="timestamp">
    REDFOX+ Doctor v1.0 — ساخته شده در <?php echo date('Y-m-d H:i:s'); ?>
    <br>
    ⚠️ این فایل را بعد از استفاده حذف کنید!
</p>

</div>
</body>
</html>
<?php
// ── حذف خودکار ──
if (isset($_GET['delete']) && $_GET['delete'] === '1' && $fix_nonce === $expected_nonce) {
    @unlink(__FILE__);
    exit('حذف شد.');
}
