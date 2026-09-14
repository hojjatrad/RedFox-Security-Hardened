<?php
/**
 * ابزار تشخیص مشکلات مهاجرت و سینک
 * فایل موقت — بعد از تشخیص مشکل حذف شود
 */
declare(strict_types=1);
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Secrets.php';

header('Content-Type: text/plain; charset=utf-8');

// Auth check
if (empty($_SESSION['user'])) { http_response_code(401); echo "Not logged in"; exit; }
$stmt = $pdo->prepare('SELECT rule FROM admin WHERE username=?');
$stmt->execute([(string)$_SESSION['user']]);
$admin = $stmt->fetch();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo "Not admin"; exit; }

echo "═══════════════════════════════════════════\n";
echo "   🔍 ابزار تشخیص مشکلات مهاجرت و سینک\n";
echo "═══════════════════════════════════════════\n\n";

// ── 1. MySQL Version ──
echo "── 1. نسخه MySQL ──\n";
try {
    $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "  نسخه: $ver\n";
} catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }

// ── 2. Destination DB ──
echo "\n── 2. دیتابیس مقصد (فعلی) ──\n";
try {
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "  نام: $dbName\n";
} catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }

// ── 3. Destination tables ──
echo "\n── 3. جداول مقصد و تعداد ردیف‌ها ──\n";
$destTables = ['user','invoice','marzban_panel','Requestagent','Payment_report','botsaz',
               'reseller_cards','reseller_ai_feature','rx_user_cache','admin'];
foreach ($destTables as $t) {
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        $cols = $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN);
        echo "  ✅ `{$t}`: {$cnt} ردیف, " . count($cols) . " ستون\n";
    } catch (Throwable $e) {
        echo "  ❌ `{$t}`: وجود ندارد — " . $e->getMessage() . "\n";
    }
}

// ── 4. Source DB (vpbotni1_b) ──
echo "\n── 4. دیتابیس مبدأ (vpbotni1_b) ──\n";
$_dbhost = (string)($GLOBALS['dbhost'] ?? 'localhost');
$_dbuser = (string)($GLOBALS['usernamedb'] ?? '');
$_dbpass = (string)($GLOBALS['passworddb'] ?? '');

try {
    $dsn = "mysql:host={$_dbhost};dbname=vpbotni1_b;charset=utf8mb4";
    $srcPdo = new PDO($dsn, $_dbuser, $_dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "  ✅ اتصال موفق\n";
    
    $srcTables = ['user','invoice','marzban_panel','Requestagent','Payment_report','botsaz',
                  'reseller_cards','reseller_ai_feature'];
    echo "\n  جداول مبدأ:\n";
    foreach ($srcTables as $t) {
        try {
            $cnt = (int)$srcPdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            echo "  ✅ `{$t}`: {$cnt} ردیف\n";
        } catch (Throwable $e) {
            echo "  ❌ `{$t}`: وجود ندارد\n";
        }
    }
    
    // ── 5. Sample data from source ──
    echo "\n── 5. نمونه داده از مبدأ ──\n";
    try {
        $sample = $srcPdo->query("SELECT id, username, agent, affiliates FROM `user` LIMIT 3")->fetchAll();
        echo "  نمونه کاربران:\n";
        foreach ($sample as $s) {
            echo "    id={$s['id']} username={$s['username']} agent=" . ($s['agent']??'NULL') . " affiliates=" . ($s['affiliates']??'NULL') . "\n";
        }
    } catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }
    
    // ── 6. Check source marzban_panel ──
    echo "\n── 6. پنل‌های مبدأ (marzban_panel) ──\n";
    try {
        $panels = $srcPdo->query("SELECT id, name_panel, url_panel, type, username_panel, password_panel FROM marzban_panel LIMIT 5")->fetchAll();
        echo "  تعداد: " . count($panels) . "\n";
        foreach ($panels as $p) {
            $passLen = strlen((string)($p['password_panel'] ?? ''));
            $isEnc = str_starts_with((string)($p['password_panel'] ?? ''), 'rxenc:v1:');
            echo "  id={$p['id']} name={$p['name_panel']} type={$p['type']} url={$p['url_panel']} user={$p['username_panel']} pass_len={$passLen} encrypted=" . ($isEnc ? 'YES' : 'NO') . "\n";
        }
    } catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }
    
    // ── 7. Check Service_location match ──
    echo "\n── 7. تطابق Service_location با name_panel ──\n";
    try {
        $slSrc = $srcPdo->query("SELECT DISTINCT Service_location FROM invoice WHERE Service_location IS NOT NULL AND Service_location != '' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        echo "  مقادیر Service_location در مبدأ: " . implode(', ', $slSrc ?: ['(خالی)']) . "\n";
    } catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }
    
    // ── 8. Test INSERT ──
    echo "\n── 8. تست INSERT ──\n";
    try {
        // Get source user columns
        $srcCols = $srcPdo->query("SHOW COLUMNS FROM `user`")->fetchAll(PDO::FETCH_COLUMN);
        echo "  ستون‌های مبدأ: " . implode(', ', $srcCols) . "\n";
        
        // Get dest user columns
        $dstCols = $pdo->query("SHOW COLUMNS FROM `user`")->fetchAll(PDO::FETCH_COLUMN);
        echo "  ستون‌های مقصد: " . implode(', ', $dstCols) . "\n";
        
        $missing = array_diff($srcCols, $dstCols);
        $extra = array_diff($dstCols, $srcCols);
        if (!empty($missing)) echo "  ⚠️ ستون‌هایی که در مبدأ هست ولی در مقصد نیست: " . implode(', ', $missing) . "\n";
        if (!empty($extra)) echo "  ℹ️ ستون‌هایی که در مقصد هست ولی در مبدأ نیست: " . implode(', ', $extra) . "\n";
        
        // Try a real INSERT from source
        $sampleRow = $srcPdo->query("SELECT * FROM `user` LIMIT 1")->fetch();
        if ($sampleRow) {
            $commonCols = array_intersect($srcCols, $dstCols);
            $filteredRow = array_intersect_key($sampleRow, array_flip($commonCols));
            
            $colList = '`' . implode('`,`', array_keys($filteredRow)) . '`';
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($filteredRow));
            $valList = implode(',', $vals);
            
            $updateParts = [];
            foreach (array_keys($filteredRow) as $col) {
                $updateParts[] = "`{$col}` = VALUES(`{$col}`)";
            }
            $sql = "INSERT INTO `user` ({$colList}) VALUES ({$valList}) ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
            
            echo "  SQL نمونه (اول 120 کاراکتر): " . substr($sql, 0, 120) . "...\n";
            
            try {
                $affected = $pdo->exec($sql);
                echo "  ✅ اجرا شد — ردیف‌های تحت تأثیر: {$affected}\n";
            } catch (Throwable $e) {
                echo "  ❌ خطا در اجرا: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  ⚠️ هیچ کاربری در مبدأ نیست!\n";
        }
    } catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }
    
    // ── 9. Sync test ──
    echo "\n── 9. تست سینک ──\n";
    try {
        $syncQ = $pdo->query("SELECT COUNT(*) FROM invoice i LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND i.username IS NOT NULL AND i.username != '' AND mp.type IN ('marzban','marzneshin','pasargard')");
        $syncCnt = (int)$syncQ->fetchColumn();
        echo "  فاکتورهای قابل سینک: {$syncCnt}\n";
        
        if ($syncCnt > 0) {
            $sample = $pdo->query("SELECT i.id_invoice, i.username, mp.url_panel, mp.password_panel, mp.type FROM invoice i LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND i.username IS NOT NULL AND i.username != '' AND mp.type IN ('marzban','marzneshin','pasargard') LIMIT 1")->fetch();
            if ($sample) {
                echo "  نمونه: invoice={$sample['id_invoice']} user={$sample['username']} panel={$sample['url_panel']} type={$sample['type']}\n";
                $rawPass = (string)($sample['password_panel'] ?? '');
                $isEnc = str_starts_with($rawPass, 'rxenc:v1:');
                echo "  رمز عبور: encrypted=" . ($isEnc ? 'YES' : 'NO') . " length=" . strlen($rawPass) . "\n";
                
                if ($isEnc) {
                    try {
                        $decrypted = rx_secret_decrypt($rawPass);
                        echo "  ✅ رمزگشایی موفق: length=" . strlen((string)$decrypted) . "\n";
                    } catch (Throwable $e) {
                        echo "  ❌ رمزگشایی ناموفق: " . $e->getMessage() . "\n";
                        // Check if REDFOX_MASTER_KEY is set
                        $mk = rx_env('REDFOX_MASTER_KEY') ?: '';
                        echo "  REDFOX_MASTER_KEY: " . ($mk !== '' ? 'تنظیم شده (length=' . strlen($mk) . ')' : 'تنظیم نشده!') . "\n";
                    }
                }
            }
        }
    } catch (Throwable $e) { echo "  خطا: " . $e->getMessage() . "\n"; }

} catch (Throwable $e) {
    echo "  ❌ اتصال ناموفق: " . $e->getMessage() . "\n";
}

echo "\n═══════════════════════════════════════════\n";
echo "   پایان تشخیص\n";
echo "═══════════════════════════════════════════\n";
