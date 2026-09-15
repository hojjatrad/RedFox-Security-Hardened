<?php
/**
 * Red Fox — گزارش و مدیریت کامل یک نماینده (نسخه‌ی پیشرفته).
 *  - آمار کامل: موجودی، درآمد، خرج، سود خالص
 *  - لیست کامل فاکتورها (مشتری/محصول/حجم/زمان/قیمت/تاریخ/وضعیت) با صفحه‌بندی
 *  - فیلتر بازه زمانی + جستجو
 *  - خروجی CSV
 *  - خلاصه‌ی هر مشتری + زیرمجموعه‌ها
 *  - پرداخت‌ها + محصولات
 *  - مدیریت: نوع، اعتبار، سقف بدهی، لغو
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$adminRow = null;
if (!empty($_SESSION['user'])) {
    $q = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
    $q->bindValue(':u', $_SESSION['user'], PDO::PARAM_STR); $q->execute();
    $adminRow = $q->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');
$flash = ''; $flashType = 'info';

function redfox_one($sql, $params = []) {
    global $pdo;
    try { $s = $pdo->prepare($sql); $s->execute($params); $v = $s->fetchColumn(); return $v === false ? 0 : $v; }
    catch (Throwable $e) { return 0; }
}
function redfox_rows($sql, $params = []) {
    global $pdo;
    try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}

$rid = trim((string)($_GET['id'] ?? ''));
if(($_GET['ajax']??'')==='balance'&&ctype_digit($rid)){header('Content-Type:application/json; charset=utf-8');$b=(int)redfox_one('SELECT Balance FROM user WHERE id=?',[$rid]);$r=(int)redfox_one("SELECT COALESCE(SUM(CAST(price AS UNSIGNED)),0) FROM Payment_report WHERE id_user=? AND payment_Status='paid'",[$rid]);$agx=(string)redfox_one('SELECT agent FROM user WHERE id=?',[$rid]);$m=(int)redfox_one("SELECT COALESCE(MIN(CAST(price_product AS UNSIGNED)),0) FROM product WHERE agent=? OR agent='all'",[$agx]);echo json_encode(['ok'=>true,'balance'=>$b,'recharge'=>$r,'spent'=>max(0,$r-$b),'minimum'=>$m,'can_buy'=>$m===0||$b>=$m],JSON_UNESCAPED_UNICODE);exit;}

/* ---- مدیریت (POST) ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $act = (string)($_POST['action'] ?? '');
    $aid = trim((string)($_POST['agent_id'] ?? ''));
    if (!ctype_digit($aid)) $aid = '';
    try {
        if ($act === 'settype' && $aid !== '') {
            $t = (string)($_POST['type'] ?? '');
            if (in_array($t, ['n','n2'], true)) { $pdo->prepare("UPDATE user SET agent=? WHERE id=?")->execute([$t,$aid]); $pdo->prepare("UPDATE Requestagent SET type=? WHERE id=?")->execute([$t,$aid]); $flash='نوع تغییر کرد.'; $flashType='success'; }
        } elseif ($act === 'addcredit' && $aid !== '') {
            $amt = (int)($_POST['amount'] ?? 0);
            if ($amt !== 0) { $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?")->execute([$amt,$aid]); $flash='موجودی تغییر کرد.'; $flashType='success'; }
        } elseif ($act === 'setmaxbuy' && $aid !== '') {
            $mb = (string)($_POST['maxbuy'] ?? '0'); if (!ctype_digit(ltrim($mb,'-')) && $mb!=='0') $mb='0';
            $pdo->prepare("UPDATE user SET maxbuyagent=? WHERE id=?")->execute([$mb,$aid]); $flash='سقف بدهی تنظیم شد.'; $flashType='success';
        } elseif ($act === 'revoke' && $aid !== '') {
            $pdo->prepare("UPDATE user SET agent='f' WHERE id=?")->execute([$aid]);
            $pdo->prepare("UPDATE Requestagent SET status='reject' WHERE id=?")->execute([$aid]);
            $flash='نمایندگی لغو شد.'; $flashType='success';
        }
    } catch (Throwable $e) { $flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/reseller_report.php'),ENT_QUOTES); $flashType='error'; }
    header("Location: reseller_report.php?id=" . urlencode($aid ?: $rid));
    exit;
}

// اگر آیدی نبود → انتخاب نماینده
if ($rid === '' || !ctype_digit($rid)) {
    $resellers = redfox_rows("SELECT id, username, namecustom, agent FROM user WHERE agent IN ('n','n2') ORDER BY id DESC LIMIT 2000");
    ?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>گزارش نماینده | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css"></head>
<body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📊 گزارش نماینده</h1><div class="page-head__sub">یک نماینده را برای دیدن گزارش کامل انتخاب کنید</div></div>
<div class="card"><div class="card__head"><h2 class="card__title" style="color:var(--text-main)">انتخاب نماینده</h2></div>
<div class="table-wrap"><table class="app-table display" id="resTable" style="width:100%"><thead><tr><th>آیدی</th><th>نام</th><th>نوع</th><th>زیرمجموعه</th><th>فروش</th><th>درآمد</th><th></th></tr></thead><tbody>
<?php foreach ($resellers as $r):
    $dc = (int)redfox_one("SELECT COUNT(*) FROM user WHERE affiliates = ?", [$r['id']]);
    $sc = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE refral = ?", [$r['id']]);
    $rv = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')", [$r['id']]);
?>
<tr><td><code><?= htmlspecialchars((string)$r['id'],ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)($r['username'] ?: $r['namecustom'] ?: '—'),ENT_QUOTES,'UTF-8') ?></td>
<td><?= $r['agent']==='n2'?'پیشرفته':'عادی' ?></td>
<td><?= $dc ?></td><td><?= $sc ?></td><td><?= number_format($rv) ?></td>
<td><a class="btn btn-sm btn-primary" href="reseller_report.php?id=<?= urlencode((string)$r['id']) ?>">📊 گزارش</a></td></tr>
<?php endforeach; ?></tbody></table></div></div>
</div></section></section>
<script src="js/datatable.js" defer></script><script>document.addEventListener("DOMContentLoaded",function(){RedFoxDT.init("#resTable");});</script>
</body></html>
<?php exit; }

/* ---- گزارش کامل برای آیدی $rid ---- */
$reseller = redfox_rows("SELECT * FROM user WHERE id = ?", [$rid]);
$reseller = $reseller[0] ?? null;
if (!$reseller || !in_array((string)$reseller['agent'], ['n','n2'], true)) {
    ?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>گزارش نماینده</title><link rel="stylesheet" href="css/theme.css"></head>
<body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="rx-flash error" style="padding:12px;border-radius:10px">نماینده‌ای با این آیدی یافت نشد.</div>
<a class="btn btn-sm btn-primary" href="reseller_report.php">بازگشت</a></div></section></section></body></html><?php exit; }

$ag = (string)$reseller['agent'];

// فیلتر بازه زمانی
$rxFromDate = trim((string)($_GET['from'] ?? ''));
$rxToDate   = trim((string)($_GET['to'] ?? ''));
$rxTab      = (string)($_GET['tab'] ?? 'overview');
$rxPage     = max(1, (int)($_GET['p'] ?? 1));
$rxPerPage  = 50;
$rxDateWhere = '';
$rxDateParams = [];
if ($rxFromDate !== '') { $rxFromTs = strtotime($rxFromDate.' 00:00:00'); if ($rxFromTs) { $rxDateWhere .= ' AND CAST(time_sell AS UNSIGNED) >= ?'; $rxDateParams[] = $rxFromTs; } }
if ($rxToDate !== '') { $rxToTs = strtotime($rxToDate.' 23:59:59'); if ($rxToTs) { $rxDateWhere .= ' AND CAST(time_sell AS UNSIGNED) <= ?'; $rxDateParams[] = $rxToTs; } }

// آمار پایه
$done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
$downline = redfox_rows("SELECT id, username, namecustom, register, Balance, agent FROM user WHERE affiliates = ?", [$rid]);
$customerIds = [];
foreach (redfox_rows("SELECT DISTINCT id_user FROM invoice WHERE refral = ? AND id_user IS NOT NULL AND id_user <> ''", [$rid]) as $c) $customerIds[] = (string)$c['id_user'];

$salesCount = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE refral = ?", [$rid]);
$salesDone = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE refral = ? AND Status IN ($done)", [$rid]);
$revenueDone = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ($done)", [$rid]);

// کلِ خرجِ نماینده (پرداخت‌های ثبت‌شده‌ی خودش)
$totalRecharge = (int)redfox_one("SELECT COALESCE(SUM(CAST(price AS UNSIGNED)),0) FROM Payment_report WHERE id_user = ? AND payment_Status = 'paid'", [$rid]);
$minimumPurchase=(int)redfox_one("SELECT COALESCE(MIN(CAST(price_product AS UNSIGNED)),0) FROM product WHERE agent=? OR agent='all'",[$ag]);$currentBalance=(int)($reseller['Balance']??0);$spentApprox=max(0,$totalRecharge-$currentBalance);$canPurchase=$minimumPurchase===0||$currentBalance>=$minimumPurchase;

// آمار بازه‌ی فیلترشده
$salesFiltered = 0; $revenueFiltered = 0;
if ($rxDateWhere !== '') {
    $salesFiltered = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE refral = ? $rxDateWhere", array_merge([$rid], $rxDateParams));
    $revenueFiltered = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ($done) $rxDateWhere", array_merge([$rid], $rxDateParams));
}

// خروجی CSV
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reseller_' . $rid . '_invoices.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
    fputcsv($out, ['کد فاکتور', 'آیدی مشتری', 'نام کاربری', 'یوزرنیم سرویس', 'محصول', 'حجم(GB)', 'زمان(روز)', 'قیمت(تومان)', 'لوکیشن', 'تاریخ فروش', 'وضعیت']);
    $csvSql = "SELECT id_invoice, id_user, username, name_product, Volume, Service_time, price_product, Service_location, time_sell, Status FROM invoice WHERE refral = ?";
    $csvParams = [$rid];
    if ($rxDateWhere !== '') { $csvSql .= $rxDateWhere; $csvParams = array_merge($csvParams, $rxDateParams); }
    $csvSql .= " ORDER BY time_sell DESC LIMIT 5000";
    foreach (redfox_rows($csvSql, $csvParams) as $inv) {
        $ts = (int)$inv['time_sell'];
        fputcsv($out, [
            $inv['id_invoice'], $inv['id_user'], $inv['username'], $inv['username'],
            $inv['name_product'], $inv['Volume'], $inv['Service_time'], $inv['price_product'],
            $inv['Service_location'], ($ts > 0 ? date('Y/m/d H:i', $ts) : $inv['time_sell']), $inv['Status'],
        ]);
    }
    fclose($out);
    exit;
}

// --- فاکتورها با صفحه‌بندی ---
$invOffset = ($rxPage - 1) * $rxPerPage;
$invSql = "SELECT SQL_CALC_FOUND_ROWS id_invoice, id_user, username, name_product, Volume, Service_time, price_product, Service_location, time_sell, note, Status FROM invoice WHERE refral = ?";
$invParams = [$rid];
if ($rxDateWhere !== '') { $invSql .= $rxDateWhere; $invParams = array_merge($invParams, $rxDateParams); }
$invSql .= " ORDER BY time_sell DESC LIMIT $rxPerPage OFFSET $invOffset";
$invoices = redfox_rows($invSql, $invParams);
$invTotal = (int)redfox_one("SELECT FOUND_ROWS()");
$invPages = max(1, (int)ceil($invTotal / $rxPerPage));

// --- آمار بر اساس محصول ---
$byProduct = redfox_rows("SELECT name_product, COUNT(*) c, COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) r FROM invoice WHERE refral = ? GROUP BY name_product ORDER BY c DESC LIMIT 20", [$rid]);
// --- آمار بر اساس وضعیت ---
$byStatus = redfox_rows("SELECT Status, COUNT(*) c FROM invoice WHERE refral = ? GROUP BY Status", [$rid]);
// --- آمار روزانه ---
$daily = redfox_rows("SELECT FROM_UNIXTIME(CAST(time_sell AS UNSIGNED), '%Y-%m-%d') d, COUNT(*) c, COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) r FROM invoice WHERE refral = ? AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ? GROUP BY d ORDER BY d ASC", [$rid, strtotime('-30 days 00:00:00')]);
// --- خلاصه‌ی مشتریان ---
$custSummary = [];
if (!empty($customerIds)) {
    $phC = implode(',', array_fill(0, count($customerIds), '?'));
    $custSummary = redfox_rows("SELECT i.id_user, u.username, u.namecustom, u.Balance, u.User_Status,
            COUNT(*) cnt,
            COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) total,
            MAX(i.time_sell) last_buy,
            SUM(CASE WHEN i.Status IN ($done) THEN 1 ELSE 0 END) AS active_count
     FROM invoice i LEFT JOIN user u ON u.id = i.id_user
     WHERE i.refral = ? GROUP BY i.id_user ORDER BY total DESC LIMIT 50", [$rid]);
}
// --- پرداخت‌ها ---
$payCust = [];
if (!empty($customerIds)) {
    $phC = implode(',', array_fill(0, count($customerIds), '?'));
    $payCust = redfox_rows("SELECT Payment_Method, payment_Status, COUNT(*) c, COALESCE(SUM(CAST(price AS UNSIGNED)),0) r FROM Payment_report WHERE id_user IN ($phC) GROUP BY Payment_Method, payment_Status", $customerIds);
}
$payResellerRecharge = redfox_rows("SELECT Payment_Method, payment_Status, COUNT(*) c, COALESCE(SUM(CAST(price AS UNSIGNED)),0) r FROM Payment_report WHERE id_user = ? GROUP BY Payment_Method, payment_Status", [$rid]);

// --- محصولات ---
$products = redfox_rows("SELECT id, name_product, price_product, Volume_constraint, Service_time, agent, Location FROM product WHERE agent = ? OR agent = 'all' ORDER BY CAST(price_product AS UNSIGNED) ASC LIMIT 200", [$ag]);

$netProfit = $revenueDone - $totalRecharge; // سود تقریبی = درآمد - شارژ
$rxStatusFa=['active'=>'فعال','end_of_time'=>'پایان زمان','end_of_volume'=>'پایان حجم','sendedwarn'=>'هشدار ارسال‌شده','send_on_hold'=>'در انتظار اتصال','Unpaid'=>'پرداخت‌نشده','pending'=>'در انتظار','paid'=>'پرداخت‌شده','failed'=>'ناموفق'];
function rxTabLink($tab, $label, $current) {
    global $rid, $rxFromDate, $rxToDate;
    $qs = http_build_query(['id'=>$rid, 'tab'=>$tab] + ($rxFromDate?['from'=>$rxFromDate]:[]) + ($rxToDate?['to'=>$rxToDate]:[]));
    $cls = $current === $tab ? 'rx-tab rx-tab-active' : 'rx-tab';
    return "<a href=\"reseller_report.php?$qs\" class=\"$cls\">$label</a>";
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>گزارش نماینده #<?= htmlspecialchars($rid,ENT_QUOTES) ?> | ربات رد فاکس</title>
<link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-card-title{color:var(--text-main)}
.rx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:16px}
.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:12px;padding:14px}
.rx-stat .n{font-size:20px;font-weight:800;color:var(--text-main)}.rx-stat .l{color:var(--text-muted);font-size:11px;margin-top:3px}
.rx-stat.profit .n{color:var(--color-success)}.rx-stat.loss .n{color:var(--color-danger)}
.rx-inline{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}
.rx-inline input,.rx-inline select{padding:7px 9px;border-radius:7px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:12px}
.rx-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px}
.rx-tab{padding:9px 16px;border-radius:9px;background:var(--surface-1);border:1px solid var(--border-soft);color:var(--text-muted);font-size:13px;font-weight:600;text-decoration:none;transition:.12s}
.rx-tab:hover{color:var(--text-main)}
.rx-tab-active{background:var(--accent);color:var(--accent-fg);border-color:var(--accent)}
.rx-bar{display:flex;align-items:flex-end;gap:4px;height:80px;margin-top:8px}
.rx-bar .b{flex:1;background:linear-gradient(180deg,var(--accent),var(--accent-mid));border-radius:4px 4px 0 0;min-height:3px}
.rx-page-btn{padding:6px 14px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:12px;text-decoration:none;margin:2px}
.rx-page-btn.active{background:var(--accent);color:var(--accent-fg)}
.rx-filter-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px;padding:12px;background:var(--surface-1);border-radius:10px;border:1px solid var(--border-soft)}
.rx-filter-bar input[type=date]{padding:7px;border-radius:7px;border:1px solid var(--border-mid);background:var(--surface-2,var(--surface-1));color:var(--text-main);font-size:12px}
.rx-filter-bar .btn{padding:7px 14px;font-size:12px}
.badge{padding:3px 8px;border-radius:10px;font-size:11px;font-weight:700}
.badge-active{background:var(--color-success-soft);color:var(--color-success)}
.badge-expired{background:var(--color-danger-soft);color:var(--color-danger)}
.badge-other{background:var(--surface-2);color:var(--text-muted)}
code{color:var(--text-main)}.text-muted{color:var(--text-muted)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📊 گزارش نماینده #<?= htmlspecialchars($rid,ENT_QUOTES) ?></h1>
<div class="page-head__sub"><?= htmlspecialchars((string)($reseller['username'] ?: $reseller['namecustom'] ?: ''),ENT_QUOTES,'UTF-8') ?> — <?= $ag==='n2'?'نماینده پیشرفته':'نماینده عادی' ?></div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>
<a class="btn btn-sm" href="reseller_report.php" style="margin-bottom:14px">↩️ انتخاب نماینده دیگر</a>

<!-- فیلتر بازه زمانی -->
<div class="rx-filter-bar">
<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<input type="hidden" name="id" value="<?= htmlspecialchars($rid,ENT_QUOTES) ?>">
<input type="hidden" name="tab" value="<?= htmlspecialchars($rxTab,ENT_QUOTES) ?>">
<label class="text-muted" style="font-size:12px">از:</label>
<input type="date" name="from" value="<?= htmlspecialchars($rxFromDate,ENT_QUOTES) ?>">
<label class="text-muted" style="font-size:12px">تا:</label>
<input type="date" name="to" value="<?= htmlspecialchars($rxToDate,ENT_QUOTES) ?>">
<button class="btn btn-sm btn-primary" type="submit">🔍 فیلتر</button>
<?php if ($rxFromDate || $rxToDate): ?><a class="btn btn-sm" href="reseller_report.php?id=<?= urlencode($rid) ?>&tab=<?= urlencode($rxTab) ?>">پاک کردن</a><?php endif; ?>
</form>
<a class="btn btn-sm btn-success" href="reseller_report.php?id=<?= urlencode($rid) ?>&export=csv&from=<?= urlencode($rxFromDate) ?>&to=<?= urlencode($rxToDate) ?>">📥 خروجی CSV</a>
</div>

<!-- آمار کلی -->
<div class="rx-grid">
<div class="rx-stat"><div class="n" id="liveBalance"><?=number_format($currentBalance)?></div><div class="l">اعتبار باقی‌مانده</div></div><div class="rx-stat"><div class="n" id="liveSpent"><?=number_format($spentApprox)?></div><div class="l">مصرف تقریبی از شارژ</div></div><div class="rx-stat <?=$canPurchase?'profit':'loss'?>"><div class="n" id="liveCanBuy"><?=$canPurchase?'قابل خرید':'نیازمند شارژ'?></div><div class="l">حداقل خرید: <?=number_format($minimumPurchase)?></div></div>
<div class="rx-stat"><div class="n"><?= number_format($totalRecharge) ?></div><div class="l">کل شارژ (ورودی)</div></div>
<div class="rx-stat <?= $netProfit>=0?'profit':'loss' ?>"><div class="n"><?= ($netProfit>=0?'+':'') . number_format($netProfit) ?></div><div class="l">سود/زیان تقریبی</div></div>
<div class="rx-stat"><div class="n"><?= $salesDone ?></div><div class="l">فروش موفق</div></div>
<div class="rx-stat"><div class="n"><?= number_format($revenueDone) ?></div><div class="l">درآمد فروش</div></div>
<div class="rx-stat"><div class="n"><?= count($customerIds) ?></div><div class="l">مشتری</div></div>
<?php if ($rxDateWhere !== ''): ?>
<div class="rx-stat"><div class="n"><?= $salesFiltered ?></div><div class="l">فروش در بازه</div></div>
<div class="rx-stat"><div class="n"><?= number_format($revenueFiltered) ?></div><div class="l">درآمد در بازه</div></div>
<?php endif; ?>
</div>

<!-- تب‌ها -->
<div class="rx-tabs">
<?= rxTabLink('overview','📋 نمای کلی',$rxTab) ?>
<?= rxTabLink('invoices','🧾 فاکتورها ('.$salesCount.')',$rxTab) ?>
<?= rxTabLink('customers','👥 مشتریان',$rxTab) ?>
<?= rxTabLink('products','🛍 محصولات',$rxTab) ?>
<?= rxTabLink('payments','💳 پرداخت‌ها',$rxTab) ?>
<?= rxTabLink('downline','👥 زیرمجموعه‌ها',$rxTab) ?>
</div>

<?php // ===== TAB: OVERVIEW ===== ?>
<?php if ($rxTab === 'overview'): ?>

<?php if ($isMainAdmin): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ مدیریت سریع</h2></div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="post" class="rx-inline"><input type="hidden" name="action" value="settype"><input type="hidden" name="agent_id" value="<?= htmlspecialchars($rid,ENT_QUOTES) ?>">
<label class="text-muted" style="font-size:12px">نوع:</label><select name="type"><option value="n" <?= $ag==='n'?'selected':'' ?>>عادی</option><option value="n2" <?= $ag==='n2'?'selected':'' ?>>پیشرفته</option></select>
<button class="btn btn-sm btn-primary" type="submit">اعمال</button></form>
<form method="post" class="rx-inline"><input type="hidden" name="action" value="addcredit"><input type="hidden" name="agent_id" value="<?= htmlspecialchars($rid,ENT_QUOTES) ?>">
<input type="number" name="amount" placeholder="مبلغ (+/-)"><button class="btn btn-sm btn-primary" type="submit">تغییر موجودی</button></form>
<form method="post" class="rx-inline"><input type="hidden" name="action" value="setmaxbuy"><input type="hidden" name="agent_id" value="<?= htmlspecialchars($rid,ENT_QUOTES) ?>">
<input type="number" name="maxbuy" value="<?= htmlspecialchars((string)($reseller['maxbuyagent'] ?? '0'),ENT_QUOTES) ?>" placeholder="سقف بدهی"><button class="btn btn-sm" type="submit">سقف</button></form>
<form method="post" onsubmit="return confirm('لغو نمایندگی؟')"><input type="hidden" name="action" value="revoke"><input type="hidden" name="agent_id" value="<?= htmlspecialchars($rid,ENT_QUOTES) ?>"><button class="btn btn-sm btn-danger" type="submit">🚫 لغو</button></form>
</div></div>
<?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📈 فروش ۳۰ روز اخیر</h2></div>
<?php if (empty($daily)): ?><p class="text-muted">داده‌ای نیست.</p><?php else: $mx=1; foreach($daily as $d) $mx=max($mx,(int)$d['c']); ?>
<div class="rx-bar"><?php foreach($daily as $d): ?><div class="b" style="height:<?= max(4,(int)(($d['c']/$mx)*100)) ?>%" title="<?= htmlspecialchars((string)$d['d']) ?>: <?= (int)$d['c'] ?> فروش / <?= number_format((int)$d['r']) ?>"></div><?php endforeach; ?></div>
<?php endif; ?></div>

<div class="rx-grid" style="grid-template-columns:1fr 1fr;gap:16px">
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🛍 فروش بر اساس محصول</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>محصول</th><th>تعداد</th><th>درآمد</th></tr></thead><tbody>
<?php foreach($byProduct as $p): ?><tr><td><?= htmlspecialchars((string)$p['name_product'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$p['c'] ?></td><td><?= number_format((int)$p['r']) ?></td></tr><?php endforeach; ?>
<?php if(empty($byProduct)): ?><tr><td colspan="3" class="text-muted">داده‌ای نیست.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📦 وضعیت فاکتورها</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>وضعیت</th><th>تعداد</th></tr></thead><tbody>
<?php foreach($byStatus as $s): ?><tr><td><?=htmlspecialchars($rxStatusFa[$s['Status']]??(string)$s['Status'],ENT_QUOTES,'UTF-8')?></td><td><?= (int)$s['c'] ?></td></tr><?php endforeach; ?>
<?php if(empty($byStatus)): ?><tr><td colspan="2" class="text-muted">داده‌ای نیست.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div>

<?php endif; // overview ?>

<?php // ===== TAB: INVOICES ===== ?>
<?php if ($rxTab === 'invoices'): ?>
<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">🧾 فاکتورهای فروش (<?= $invTotal ?>)</h2></div>
<div class="table-wrap">
<table class="app-table" style="width:100%">
<thead><tr><th>کد فاکتور</th><th>مشتری</th><th>یوزرنیم</th><th>محصول</th><th>حجم</th><th>زمان</th><th>قیمت</th><th>لوکیشن</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
<tbody>
<?php foreach ($invoices as $inv):
    $ts = (int)$inv['time_sell']; $isDone = in_array($inv['Status'],['active','end_of_time','end_of_volume','sendedwarn','send_on_hold']);
    $badgeCls = $isDone ? 'badge-active' : ($inv['Status']==='Unpaid'?'badge-other':'badge-other');
?>
<tr>
<td><code><?= htmlspecialchars((string)$inv['id_invoice'],ENT_QUOTES) ?></code></td>
<td><code><?= htmlspecialchars((string)$inv['id_user'],ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)$inv['username'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= htmlspecialchars((string)$inv['name_product'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= htmlspecialchars((string)$inv['Volume'],ENT_QUOTES) ?> GB</td>
<td><?= htmlspecialchars((string)$inv['Service_time'],ENT_QUOTES) ?> روز</td>
<td><?= number_format((int)$inv['price_product']) ?></td>
<td class="text-muted"><?= htmlspecialchars((string)$inv['Service_location'],ENT_QUOTES,'UTF-8') ?></td>
<td class="text-muted"><?= $ts > 0 ? htmlspecialchars(date('Y/m/d H:i',$ts)) : htmlspecialchars((string)$inv['time_sell']) ?></td>
<td><span class="badge <?= $badgeCls ?>"><?=htmlspecialchars($rxStatusFa[$inv['Status']]??(string)$inv['Status'],ENT_QUOTES)?></span></td>
</tr>
<?php endforeach; ?>
<?php if (empty($invoices)): ?><tr><td colspan="10" class="text-muted">فاکتوری ثبت نشده.</td></tr><?php endif; ?>
</tbody></table></div>

<!-- صفحه‌بندی -->
<?php if ($invPages > 1): ?>
<div style="margin-top:12px;display:flex;gap:2px;flex-wrap:wrap">
<?php for ($pi=1; $pi<=$invPages; $pi++): 
    $qs = http_build_query(['id'=>$rid,'tab'=>'invoices','p'=>$pi] + ($rxFromDate?['from'=>$rxFromDate]:[]) + ($rxToDate?['to'=>$rxToDate]:[]));
    $cls = $pi === $rxPage ? 'rx-page-btn active' : 'rx-page-btn';
?>
<a href="reseller_report.php?<?= $qs ?>" class="<?= $cls ?>"><?= $pi ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
<p class="text-muted" style="font-size:12px;margin-top:8px">نمایش <?= count($invoices) ?> از <?= $invTotal ?> فاکتور — <a href="reseller_report.php?id=<?= urlencode($rid) ?>&export=csv&from=<?= urlencode($rxFromDate) ?>&to=<?= urlencode($rxToDate) ?>">📥 خروجی CSV همه</a></p>
</div>
<?php endif; // invoices ?>

<?php // ===== TAB: CUSTOMERS ===== ?>
<?php if ($rxTab === 'customers'): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">👥 خلاصه‌ی مشتریان (<?= count($custSummary) ?>)</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>آیدی</th><th>نام</th><th>وضعیت</th><th>موجودی</th><th>تعداد خرید</th><th>سرویس فعال</th><th>مجموع خرید</th><th>آخرین خرید</th></tr></thead><tbody>
<?php foreach ($custSummary as $cs):
    $lts = (int)$cs['last_buy'];
?>
<tr><td><code><?= htmlspecialchars((string)$cs['id_user'],ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)($cs['username'] ?: $cs['namecustom'] ?: '—'),ENT_QUOTES,'UTF-8') ?></td>
<td><?php if(($cs['User_Status']??'')==='Active'): ?><span style="color:var(--color-success)">🟢 فعال</span><?php elseif(($cs['User_Status']??'')==='Block'): ?><span style="color:var(--color-danger)">🔴 مسدود</span><?php else: ?><?=htmlspecialchars((string)($cs['User_Status']??'—'),ENT_QUOTES)?><?php endif; ?></td>
<td><?= number_format((int)($cs['Balance']??0)) ?></td>
<td><?= (int)$cs['cnt'] ?></td>
<td><?php $ac=(int)($cs['active_count']??0); ?><?php if($ac>0): ?><span style="color:var(--color-success)">🟢 <?=$ac?></span><?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?></td>
<td><?= number_format((int)$cs['total']) ?></td>
<td class="text-muted"><?= $lts > 0 ? date('Y/m/d', $lts) : '—' ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($custSummary)): ?><tr><td colspan="8" class="text-muted">مشتری‌ای ثبت نشده.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; // customers ?>

<?php // ===== TAB: PRODUCTS ===== ?>
<?php if ($rxTab === 'products'): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🛒 محصولات قابل‌فروش</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>محصول</th><th>قیمت</th><th>حجم</th><th>زمان</th><th>سطح</th><th>لوکیشن</th></tr></thead><tbody>
<?php foreach($products as $p): ?><tr><td><?= htmlspecialchars((string)$p['name_product'],ENT_QUOTES,'UTF-8') ?></td><td><?= number_format((int)$p['price_product']) ?></td><td><?= htmlspecialchars((string)$p['Volume_constraint'],ENT_QUOTES) ?></td><td><?= htmlspecialchars((string)$p['Service_time'],ENT_QUOTES) ?></td><td class="text-muted"><?= htmlspecialchars((string)$p['agent'],ENT_QUOTES) ?></td><td class="text-muted"><?= htmlspecialchars((string)$p['Location'],ENT_QUOTES,'UTF-8') ?></td></tr><?php endforeach; ?>
<?php if(empty($products)): ?><tr><td colspan="6" class="text-muted">محصولی تعریف نشده.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; // products ?>

<?php // ===== TAB: PAYMENTS ===== ?>
<?php if ($rxTab === 'payments'): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">💳 پرداخت‌های مشتریان این نماینده</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>روش</th><th>وضعیت</th><th>تعداد</th><th>مبلغ</th></tr></thead><tbody>
<?php foreach($payCust as $p): ?><tr><td><?= htmlspecialchars((string)$p['Payment_Method'],ENT_QUOTES,'UTF-8') ?></td><td class="text-muted"><?= htmlspecialchars((string)$p['payment_Status'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$p['c'] ?></td><td><?= number_format((int)$p['r']) ?></td></tr><?php endforeach; ?>
<?php if(empty($payCust)): ?><tr><td colspan="4" class="text-muted">پرداختی ثبت نشده.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🔋 شارژ کیف پولِ خودِ نماینده</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>روش</th><th>وضعیت</th><th>تعداد</th><th>مبلغ</th></tr></thead><tbody>
<?php foreach($payResellerRecharge as $p): ?><tr><td><?= htmlspecialchars((string)$p['Payment_Method'],ENT_QUOTES,'UTF-8') ?></td><td class="text-muted"><?= htmlspecialchars((string)$p['payment_Status'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$p['c'] ?></td><td><?= number_format((int)$p['r']) ?></td></tr><?php endforeach; ?>
<?php if(empty($payResellerRecharge)): ?><tr><td colspan="4" class="text-muted">شارژی ثبت نشده.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; // payments ?>

<?php // ===== TAB: DOWNLINE ===== ?>
<?php if ($rxTab === 'downline'): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">👥 زیرمجموعه‌ها (<?= count($downline) ?>)</h2></div>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>آیدی</th><th>نام</th><th>نوع</th><th>موجودی</th><th>تاریخ عضویت</th></tr></thead><tbody>
<?php foreach($downline as $d): ?>
<tr><td><code><?= htmlspecialchars((string)$d['id'],ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)($d['username'] ?: $d['namecustom'] ?: '—'),ENT_QUOTES,'UTF-8') ?></td>
<td class="text-muted"><?= htmlspecialchars((string)($d['agent'] ?? ''),ENT_QUOTES) ?></td>
<td><?= number_format((int)($d['Balance'] ?? 0)) ?></td>
<td class="text-muted"><?= htmlspecialchars((string)($d['register'] ?? ''),ENT_QUOTES,'UTF-8') ?></td></tr>
<?php endforeach; ?>
<?php if(empty($downline)): ?><tr><td colspan="5" class="text-muted">زیرمجموعه‌ای ندارد.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; // downline ?>

</div></section></section><script>setInterval(async function(){try{let r=await fetch('reseller_report.php?ajax=balance&id=<?=urlencode($rid)?>',{cache:'no-store'}),j=await r.json();if(!j.ok)return;let f=n=>new Intl.NumberFormat('fa-IR').format(n),b=document.getElementById('liveBalance');if(b)b.textContent=f(j.balance);let s=document.getElementById('liveSpent');if(s)s.textContent=f(j.spent);let c=document.getElementById('liveCanBuy');if(c)c.textContent=j.can_buy?'قابل خرید':'نیازمند شارژ';}catch(e){}},10000);</script></body></html>
