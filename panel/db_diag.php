<?php
/**
 * RedFox+ Database Diagnostic Tool
 * بررسی دقیق وضعیت دیتابیس‌ها برای عیب‌یابی مهاجرت و سینک
 */
declare(strict_types=1);
ob_start();

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
redfox_security_headers();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM admin WHERE username=? LIMIT 1');
$stmt->execute([(string)$_SESSION['user']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }

// ── Collect diagnostics ──
$diag = [];

// 1. Current DB info
$diag['current_db'] = $GLOBALS['dbname'] ?? '(unknown)';
$diag['current_host'] = $GLOBALS['dbhost'] ?? '(unknown)';
$diag['current_user'] = $GLOBALS['usernamedb'] ?? '(unknown)';

// 2. Table row counts in current DB
$diag['tables'] = [];
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        try {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            $diag['tables'][$t] = $cnt;
        } catch (Throwable $e) {
            $diag['tables'][$t] = 'error: ' . $e->getMessage();
        }
    }
} catch (Throwable $e) {
    $diag['tables_error'] = $e->getMessage();
}

// 3. Key table structures
$key_tables = ['user', 'invoice', 'marzban_panel', 'Requestagent'];
$diag['structures'] = [];
foreach ($key_tables as $t) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        $diag['structures'][$t] = $cols;
    } catch (Throwable $e) {
        $diag['structures'][$t] = 'error: ' . $e->getMessage();
    }
}

// 4. Sample data from key tables
$diag['samples'] = [];
foreach (['user', 'invoice', 'marzban_panel'] as $t) {
    try {
        $rows = $pdo->query("SELECT * FROM `{$t}` LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $diag['samples'][$t] = $rows;
    } catch (Throwable $e) {
        $diag['samples'][$t] = 'error: ' . $e->getMessage();
    }
}

// 5. Sync query test
$diag['sync_test'] = [];
try {
    // Test the exact sync query
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoice i LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND i.username IS NOT NULL AND i.username != '' AND mp.type IN ('marzban','marzneshin','pasargard')");
    $diag['sync_test']['matching_rows'] = (int)$stmt->fetchColumn();
    
    // Test without panel filter
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND username IS NOT NULL AND username != ''");
    $diag['sync_test']['invoices_with_username'] = (int)$stmt->fetchColumn();
    
    // Test join without type filter
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoice i LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')");
    $diag['sync_test']['invoices_with_any_panel'] = (int)$stmt->fetchColumn();
    
    // Distinct Service_location values
    $stmt = $pdo->query("SELECT DISTINCT Service_location FROM invoice WHERE Service_location IS NOT NULL AND Service_location != '' LIMIT 20");
    $diag['sync_test']['service_locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Distinct name_panel values
    $stmt = $pdo->query("SELECT name_panel, type, url_panel FROM marzban_panel LIMIT 20");
    $diag['sync_test']['panels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    $diag['sync_test']['error'] = $e->getMessage();
}

// 6. Migration test — try connecting to vpbotni1_b
$diag['migration_test'] = [];
$srcDb = 'vpbotni1_b';
try {
    $srcDsn = "mysql:host={$diag['current_host']};dbname={$srcDb};charset=utf8mb4";
    $srcPdo = new PDO($srcDsn, $diag['current_user'], $GLOBALS['passworddb'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    $diag['migration_test']['connected'] = true;
    $diag['migration_test']['is_same_db'] = ($srcDb === $diag['current_db']);
    
    // Table counts in source
    $srcTables = $srcPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $diag['migration_test']['source_tables'] = [];
    foreach ($srcTables as $t) {
        try {
            $cnt = (int)$srcPdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            $diag['migration_test']['source_tables'][$t] = $cnt;
        } catch (Throwable $e) {
            $diag['migration_test']['source_tables'][$t] = 'error';
        }
    }
    
    // Sample users from source
    try {
        $rows = $srcPdo->query("SELECT id, username, agent, namecustom FROM user LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $diag['migration_test']['source_users'] = $rows;
    } catch (Throwable $e) {
        $diag['migration_test']['source_users_error'] = $e->getMessage();
    }
    
    // Sample invoices from source
    try {
        $rows = $srcPdo->query("SELECT id_invoice, id_user, username, Service_location, Status FROM invoice LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $diag['migration_test']['source_invoices'] = $rows;
    } catch (Throwable $e) {
        $diag['migration_test']['source_invoices_error'] = $e->getMessage();
    }
    
    // Column comparison
    $diag['migration_test']['column_diff'] = [];
    foreach (['user', 'invoice', 'Requestagent'] as $tbl) {
        try {
            $srcCols = $srcPdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN);
            $dstCols = $pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN);
            $diag['migration_test']['column_diff'][$tbl] = [
                'only_in_source' => array_diff($srcCols, $dstCols),
                'only_in_dest' => array_diff($dstCols, $srcCols),
                'common' => array_intersect($srcCols, $dstCols),
            ];
        } catch (Throwable $e) {
            $diag['migration_test']['column_diff'][$tbl] = 'error: ' . $e->getMessage();
        }
    }
    
} catch (Throwable $e) {
    $diag['migration_test']['connected'] = false;
    $diag['migration_test']['error'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ابزار تشخیصی دیتابیس | RedFox+</title><link rel="stylesheet" href="css/theme.css">
<style>
:root{--rx-bg:#0f1117;--rx-card:#1a1d27;--rx-border:#2a2d3a;--rx-text:#e4e6ef;--rx-muted:#8b8fa3;--rx-accent:#6366f1;--rx-green:#22c55e;--rx-red:#ef4444;--rx-orange:#f59e0b}
body{background:var(--rx-bg);color:var(--rx-text);font-family:'Segoe UI',Tahoma,sans-serif;margin:0;padding:20px}
.wrap{max-width:1000px;margin:0 auto}
h1{font-size:1.4em;margin:0 0 20px}
.card{background:var(--rx-card);border:1px solid var(--rx-border);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{margin:0 0 14px;font-size:1.1em;display:flex;align-items:center;gap:8px}
table{width:100%;border-collapse:collapse;font-size:.85em}
th,td{padding:6px 10px;text-align:right;border-bottom:1px solid var(--rx-border)}
th{color:var(--rx-muted);font-weight:600;font-size:.8em}
.ok{color:var(--rx-green)}.err{color:var(--rx-red)}.warn{color:var(--rx-orange)}
pre{background:var(--rx-bg);padding:12px;border-radius:8px;overflow-x:auto;font-size:.8em;direction:ltr;text-align:left;white-space:pre-wrap}
.back{display:inline-block;color:var(--rx-muted);text-decoration:none;margin-bottom:16px;font-size:.9em}
.back:hover{color:var(--rx-text)}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.75em;font-weight:600}
.badge-ok{background:rgba(34,197,94,.15);color:var(--rx-green)}
.badge-err{background:rgba(239,68,68,.15);color:var(--rx-red)}
.badge-warn{background:rgba(245,158,11,.15);color:var(--rx-orange)}
</style>
</head><body>
<div class="wrap">
<a class="back" href="index.php">← بازگشت به پنل</a>
<h1>🔍 ابزار تشخیصی دیتابیس</h1>

<!-- 1. Current DB -->
<div class="card">
<h2>🗄️ دیتابیس فعلی</h2>
<table>
<tr><th>نام دیتابیس</th><td><b><?= htmlspecialchars($diag['current_db']) ?></b></td></tr>
<tr><th>میزبان</th><td><?= htmlspecialchars($diag['current_host']) ?></td></tr>
<tr><th>کاربر MySQL</th><td><?= htmlspecialchars($diag['current_user']) ?></td></tr>
<tr><th>تعداد جداول</th><td><?= count($diag['tables']) ?></td></tr>
</table>
</div>

<!-- 2. Current DB Tables -->
<div class="card">
<h2>📊 جداول دیتابیس فعلی (<?= htmlspecialchars($diag['current_db']) ?>)</h2>
<table>
<tr><th>جدول</th><th>تعداد ردیف</th></tr>
<?php foreach ($diag['tables'] as $t => $cnt): ?>
<tr>
<td><code><?= htmlspecialchars($t) ?></code></td>
<td class="<?= is_int($cnt) && $cnt > 0 ? 'ok' : (is_int($cnt) ? 'warn' : 'err') ?>">
<?= is_int($cnt) ? number_format($cnt) : htmlspecialchars((string)$cnt) ?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<!-- 3. Migration Test -->
<div class="card">
<h2>🔄 تست مهاجرت از <code><?= htmlspecialchars($srcDb) ?></code></h2>
<?php if (!empty($diag['migration_test']['connected'])): ?>
    <p><span class="badge badge-ok">اتصال موفق</span>
    <?php if (!empty($diag['migration_test']['is_same_db'])): ?>
        <span class="badge badge-warn">⚠️ دیتابیس مبدأ و مقصد یکسان هستند!</span>
    <?php endif; ?>
    </p>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">📊 جداول دیتابیس مبدأ</h3>
    <table>
    <tr><th>جدول</th><th>تعداد ردیف مبدأ</th><th>تعداد ردیف مقصد</th><th>وضعیت</th></tr>
    <?php foreach ($diag['migration_test']['source_tables'] as $t => $cnt): ?>
    <?php $dstCnt = $diag['tables'][$t] ?? 'ندارد'; ?>
    <tr>
    <td><code><?= htmlspecialchars($t) ?></code></td>
    <td><?= is_int($cnt) ? number_format($cnt) : $cnt ?></td>
    <td><?= is_int($dstCnt) ? number_format($dstCnt) : $dstCnt ?></td>
    <td>
    <?php if ($dstCnt === 'ندارد'): ?>
        <span class="badge badge-err">جدول در مقصد نیست</span>
    <?php elseif (is_int($cnt) && is_int($dstCnt) && $dstCnt >= $cnt): ?>
        <span class="badge badge-ok">OK</span>
    <?php elseif (is_int($cnt) && is_int($dstCnt) && $dstCnt < $cnt): ?>
        <span class="badge badge-warn">مقصد کمتر (<?= $cnt - $dstCnt ?> کم)</span>
    <?php endif; ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </table>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">👥 نمونه کاربران مبدأ</h3>
    <?php if (!empty($diag['migration_test']['source_users'])): ?>
    <table>
    <tr><th>ID</th><th>username</th><th>agent</th><th>namecustom</th></tr>
    <?php foreach ($diag['migration_test']['source_users'] as $u): ?>
    <tr>
    <td><?= htmlspecialchars((string)($u['id'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($u['username'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($u['agent'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($u['namecustom'] ?? '')) ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="warn"><?= htmlspecialchars((string)($diag['migration_test']['source_users_error'] ?? 'هیچ کاربری یافت نشد')) ?></p>
    <?php endif; ?>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">📋 مقایسه ستون‌ها</h3>
    <?php foreach ($diag['migration_test']['column_diff'] as $tbl => $diff): ?>
    <?php if (is_string($diff)): ?>
        <p><code><?= htmlspecialchars($tbl) ?></code>: <span class="err"><?= htmlspecialchars($diff) ?></span></p>
    <?php else: ?>
        <p><code><?= htmlspecialchars($tbl) ?></code>:
        <?php if (!empty($diff['only_in_source'])): ?>
            <span class="warn">ستون‌های فقط در مبدأ: <?= implode(', ', $diff['only_in_source']) ?></span>
        <?php endif; ?>
        <?php if (!empty($diff['only_in_dest'])): ?>
            <span class="warn"> | ستون‌های فقط در مقصد: <?= implode(', ', $diff['only_in_dest']) ?></span>
        <?php endif; ?>
        <?php if (empty($diff['only_in_source']) && empty($diff['only_in_dest'])): ?>
            <span class="ok">ستون‌ها یکسان ✓</span>
        <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php endforeach; ?>
    
<?php else: ?>
    <p class="err">❌ اتصال ناموفق: <?= htmlspecialchars((string)($diag['migration_test']['error'] ?? 'خطای ناشناخته')) ?></p>
<?php endif; ?>
</div>

<!-- 4. Sync Test -->
<div class="card">
<h2>🔄 تست سینک</h2>
<?php if (!empty($diag['sync_test'])): ?>
    <table>
    <tr><th>تست</th><th>نتیجه</th></tr>
    <tr><td>فاکتورهای فعال با نام کاربری</td><td class="<?= ($diag['sync_test']['invoices_with_username'] ?? 0) > 0 ? 'ok' : 'err' ?>"><?= number_format($diag['sync_test']['invoices_with_username'] ?? 0) ?></td></tr>
    <tr><td>فاکتورهای متصل به پنل (هر نوعی)</td><td class="<?= ($diag['sync_test']['invoices_with_any_panel'] ?? 0) > 0 ? 'ok' : 'err' ?>"><?= number_format($diag['sync_test']['invoices_with_any_panel'] ?? 0) ?></td></tr>
    <tr><td><b>نتیجه سینک (match کامل)</b></td><td class="<?= ($diag['sync_test']['matching_rows'] ?? 0) > 0 ? 'ok' : 'err' ?>" style="font-weight:700;font-size:1.1em"><?= number_format($diag['sync_test']['matching_rows'] ?? 0) ?></td></tr>
    </table>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">📍 Service_location های موجود در invoice</h3>
    <?php if (!empty($diag['sync_test']['service_locations'])): ?>
    <p><?= implode('، ', array_map('htmlspecialchars', $diag['sync_test']['service_locations'])) ?></p>
    <?php else: ?>
    <p class="warn">هیچ Service_location یافت نشد</p>
    <?php endif; ?>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">🖥️ پنل‌های تنظیم‌شده در marzban_panel</h3>
    <?php if (!empty($diag['sync_test']['panels'])): ?>
    <table>
    <tr><th>name_panel</th><th>type</th><th>url_panel</th></tr>
    <?php foreach ($diag['sync_test']['panels'] as $p): ?>
    <tr>
    <td><?= htmlspecialchars((string)($p['name_panel'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($p['type'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($p['url_panel'] ?? '')) ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="err">هیچ پنلی تنظیم نشده!</p>
    <?php endif; ?>
    
    <h3 style="color:var(--rx-muted);font-size:.9em;margin-top:16px">🔍 تحلیل مشکل سینک</h3>
    <?php
    $invoices = $diag['sync_test']['invoices_with_username'] ?? 0;
    $panels = $diag['sync_test']['panels'] ?? [];
    $locations = $diag['sync_test']['service_locations'] ?? [];
    $matching = $diag['sync_test']['matching_rows'] ?? 0;
    
    if ($invoices === 0): ?>
        <p class="err">❌ <b>مشکل:</b> هیچ فاکتور فعالی با نام کاربری وجود ندارد. جدول <code>invoice</code> خالی است یا فاکتورها وضعیت فعال ندارند.</p>
    <?php elseif (empty($panels)): ?>
        <p class="err">❌ <b>مشکل:</b> هیچ پنلی در جدول <code>marzban_panel</code> تنظیم نشده. باید حداقل یک پنل با <code>name_panel</code> متناسب با <code>Service_location</code> فاکتورها اضافه کنید.</p>
    <?php elseif ($matching === 0): ?>
        <p class="err">❌ <b>مشکل:</b> هیچ فاکتوری با پنل match نمی‌شود!</p>
        <p>مقادیر <code>Service_location</code> در فاکتورها: <b><?= implode(', ', $locations) ?: '(خالی)' ?></b></p>
        <p>مقادیر <code>name_panel</code> در پنل‌ها: <b><?= implode(', ', array_column($panels, 'name_panel')) ?: '(خالی)' ?></b></p>
        <p class="warn">⚠️ باید مطمئن شوید مقادیر <code>Service_location</code> در فاکتورها دقیقاً با <code>name_panel</code> در پنل‌ها یکسان باشند.</p>
    <?php else: ?>
        <p class="ok">✅ <?= number_format($matching) ?> فاکتور آماده سینک هستند.</p>
    <?php endif; ?>
    
<?php else: ?>
    <p class="err">خطا در تست سینک</p>
<?php endif; ?>
</div>

</div></body></html>
