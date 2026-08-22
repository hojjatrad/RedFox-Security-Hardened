<?php
/**
 * Red Fox — گزارش‌های آماری + برون‌بری Excel/CSV.
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

/* ---------- برون‌بری (قبل از HTML) ---------- */
$export = (string)($_GET['export'] ?? '');
if (in_array($export, ['csv', 'xlsx'], true)) {
    $rows = [];
    try {
        $st = $pdo->query("SELECT id_user, username, name_product, price_product, Volume, Service_time, Status, time_sell FROM invoice ORDER BY time_sell DESC LIMIT 5000");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $rows = []; }

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="redfox-sales-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM برای اکسل
        fputcsv($out, ['آیدی کاربر','نام کاربری','محصول','مبلغ','حجم','زمان(روز)','وضعیت','زمان فروش']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id_user'],$r['username'],$r['name_product'],$r['price_product'],$r['Volume'],$r['Service_time'],$r['Status'], $r['time_sell'] ? date('Y-m-d H:i', (int)$r['time_sell']) : '']);
        }
        fclose($out);
        exit;
    } else { // xlsx
        if (is_file(__DIR__ . '/../vendor/autoload.php')) {
            require __DIR__ . '/../vendor/autoload.php';
            try {
                $sh = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $a = $sh->getActiveSheet();
                $a->fromArray(['آیدی کاربر','نام کاربری','محصول','مبلغ','حجم','زمان(روز)','وضعیت','زمان فروش'], null, 'A1');
                $ri = 2;
                foreach ($rows as $r) {
                    $a->fromArray([$r['id_user'],$r['username'],$r['name_product'],$r['price_product'],$r['Volume'],$r['Service_time'],$r['Status'], $r['time_sell'] ? date('Y-m-d H:i', (int)$r['time_sell']) : ''], null, 'A' . $ri);
                    $ri++;
                }
                $a->setRTL(true);
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="redfox-sales-' . date('Y-m-d') . '.xlsx"');
                \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($sh, 'Xlsx')->save('php://output');
                exit;
            } catch (Throwable $e) {
                error_log('[stats.php] xlsx export failed: ' . redfox_exception_fingerprint($e));
            }
        }
        // fallback به CSV اگر xlsx ممکن نبود
        header('Location: stats.php?export=csv'); exit;
    }
}

/* ---------- محاسبه‌ی آمار ---------- */
function redfox_one($sql, $params = []) {
    global $pdo;
    try { $s = $pdo->prepare($sql); $s->execute($params); $v = $s->fetchColumn(); return $v === false ? 0 : $v; }
    catch (Throwable $e) { return 0; }
}

$totalUsers   = (int)redfox_one("SELECT COUNT(*) FROM user");
$activeUsers  = (int)redfox_one("SELECT COUNT(*) FROM user WHERE User_Status IS NULL OR User_Status NOT IN ('block','Block')");
$blockedUsers = (int)redfox_one("SELECT COUNT(*) FROM user WHERE User_Status IN ('block','Block')");
$agentsCount  = (int)redfox_one("SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')");

// فروش‌های کامل (غیر از لغوشده/پرداخت‌نشده)
$doneStatus = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
$totalSales     = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE Status IN ($doneStatus)");
$totalRevenue   = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE Status IN ($doneStatus)");

$startToday  = strtotime('today');
$startMonth  = strtotime('first day of this month 00:00:00');
$salesToday  = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE Status IN ($doneStatus) AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ?", [$startToday]);
$revToday    = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE Status IN ($doneStatus) AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ?", [$startToday]);
$salesMonth  = (int)redfox_one("SELECT COUNT(*) FROM invoice WHERE Status IN ($doneStatus) AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ?", [$startMonth]);
$revMonth    = (int)redfox_one("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE Status IN ($doneStatus) AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ?", [$startMonth]);

// پرفروش‌ترین محصولات
$topProducts = [];
try {
    $topProducts = $pdo->query("SELECT name_product, COUNT(*) AS cnt, COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) AS rev FROM invoice WHERE Status IN ($doneStatus) GROUP BY name_product ORDER BY cnt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// فروش ۷ روز اخیر
$daily = [];
try {
    $d = $pdo->prepare("SELECT FROM_UNIXTIME(CAST(time_sell AS UNSIGNED), '%Y-%m-%d') AS d, COUNT(*) AS c, COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) AS r FROM invoice WHERE Status IN ($doneStatus) AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) >= ? GROUP BY d ORDER BY d ASC");
    $d->execute([strtotime('-6 days 00:00:00')]);
    $daily = $d->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>آمار و گزارش‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-card-title{color:var(--text-main)}
.rx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:18px}
.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:16px}
.rx-stat .num{font-size:24px;font-weight:800;color:var(--text-main)}
.rx-stat .lbl{color:var(--text-muted);font-size:12px;margin-top:4px}
.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:10px}
.barwrap{display:flex;align-items:flex-end;gap:8px;height:120px;margin-top:10px}
.barwrap .bar{flex:1;background:linear-gradient(180deg,var(--accent),var(--accent-mid));border-radius:6px 6px 0 0;min-height:4px;position:relative}
.barwrap .bar small{position:absolute;bottom:-18px;left:0;right:0;text-align:center;color:var(--text-muted);font-size:10px}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📊 آمار و گزارش‌ها</h1>
<div class="page-head__sub">گزارش فروش، کاربران و برون‌بری Excel/CSV</div></div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
<a class="btn btn-sm btn-success" href="stats.php?export=xlsx">⬇️ خروجی Excel</a>
<a class="btn btn-sm btn-primary" href="stats.php?export=csv">⬇️ خروجی CSV</a>
</div>

<div class="rx-grid">
<div class="rx-stat"><div class="num"><?= number_format($totalUsers) ?></div><div class="lbl">کل کاربران</div></div>
<div class="rx-stat"><div class="num"><?= number_format($activeUsers) ?></div><div class="lbl">کاربران فعال</div></div>
<div class="rx-stat"><div class="num"><?= number_format($agentsCount) ?></div><div class="lbl">نماینده‌ها</div></div>
<div class="rx-stat"><div class="num"><?= number_format($blockedUsers) ?></div><div class="lbl">مسدودشده‌ها</div></div>
<div class="rx-stat"><div class="num"><?= number_format($totalSales) ?></div><div class="lbl">کل فروش</div></div>
<div class="rx-stat"><div class="num"><?= number_format($totalRevenue) ?></div><div class="lbl">درآمد کل (تومان)</div></div>
<div class="rx-stat"><div class="num"><?= number_format($salesToday) ?></div><div class="lbl">فروش امروز</div></div>
<div class="rx-stat"><div class="num"><?= number_format($revToday) ?></div><div class="lbl">درآمد امروز</div></div>
<div class="rx-stat"><div class="num"><?= number_format($salesMonth) ?></div><div class="lbl">فروش این ماه</div></div>
<div class="rx-stat"><div class="num"><?= number_format($revMonth) ?></div><div class="lbl">درآمد این ماه</div></div>
</div>

<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">📈 فروش ۷ روز اخیر</h2></div>
<?php if (empty($daily)): ?><p class="text-muted">داده‌ای برای ۷ روز اخیر نیست.</p><?php else:
$max = 1; foreach ($daily as $dy) $max = max($max, (int)$dy['c']); ?>
<div class="barwrap">
<?php foreach ($daily as $dy): ?>
<div class="bar" style="height:<?= max(6, (int)(($dy['c']/$max)*100)) ?>%" title="<?= htmlspecialchars((string)$dy['d']) ?> — <?= (int)$dy['c'] ?> فروش / <?= number_format((int)$dy['r']) ?> تومان">
<small><?= htmlspecialchars((string)substr($dy['d'],5)) ?></small></div>
<?php endforeach; ?>
</div>
<p class="rx-help">نمودار میله‌ای تعداد فروش در ۷ روز اخیر (بر اساس تاریخ فروش).</p>
<?php endif; ?>
</div>

<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">🏆 پرفروش‌ترین محصولات</h2></div>
<?php if (empty($topProducts)): ?><p class="text-muted">داده‌ای موجود نیست.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%">
<thead><tr><th>محصول</th><th>تعداد فروش</th><th>درآمد (تومان)</th></tr></thead><tbody>
<?php foreach ($topProducts as $p): ?>
<tr><td><?= htmlspecialchars((string)$p['name_product'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= (int)$p['cnt'] ?></td><td><?= number_format((int)$p['rev']) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<p class="rx-help">💡 خروجی Excel/CSV شامل فهرست کامل سفارش‌ها (تا ۵۰۰۰ رکورد اخیر) است.</p>
</div>

</div></section></section></body></html>
