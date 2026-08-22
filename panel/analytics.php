<?php
/**
 * Red Fox — داشبورد تحلیلی پیشرفته (Analytics Dashboard).
 *  - نمودار درآمد روزانه/هفتگی/ماهانه
 *  - رشد کاربران
 *  - نرخ retaining
 *  - پرفروش‌ترین محصولات
 *  - توزیع فروش بر اساس ساعت/روز
 *  - میانگین ارزش سفارش
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

$done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
$days = max(7, min(90, (int)($_GET['days'] ?? 30)));

// ── آمار کلی ──
$totalUsers = 0; $totalRevenue = 0; $totalSales = 0; $totalResellers = 0;
try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE Status IN ($done)")->fetchColumn();
    $totalSales = (int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE Status IN ($done)")->fetchColumn();
    $totalResellers = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')")->fetchColumn();
} catch (Throwable $e) {}

// ── درآمد روزانه (N روز اخیر) ──
$dailyRevenue = [];
try {
    $cutoff = strtotime("-$days days 00:00:00");
    $stmt = $pdo->prepare("SELECT FROM_UNIXTIME(CAST(time_sell AS UNSIGNED), '%Y-%m-%d') d,
                                  COUNT(*) cnt,
                                  COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) rev
                           FROM invoice
                           WHERE Status IN ($done)
                           AND time_sell REGEXP '^[0-9]+$'
                           AND CAST(time_sell AS UNSIGNED) >= ?
                           GROUP BY d ORDER BY d ASC");
    $stmt->execute([$cutoff]);
    $dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── رشد کاربران (ثبت‌نام روزانه) ──
$dailySignups = [];
try {
    $cutoff2 = strtotime("-$days days 00:00:00");
    $stmt = $pdo->query("SELECT FROM_UNIXTIME(CAST(register AS UNSIGNED), '%Y-%m-%d') d, COUNT(*) cnt
                         FROM user
                         WHERE register REGEXP '^[0-9]+$'
                         AND CAST(register AS UNSIGNED) >= $cutoff2
                         GROUP BY d ORDER BY d ASC");
    $dailySignups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── پرفروش‌ترین محصولات ──
$topProducts = [];
try {
    $topProducts = $pdo->query("SELECT name_product, COUNT(*) cnt, COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) rev
                                FROM invoice WHERE Status IN ($done)
                                GROUP BY name_product ORDER BY cnt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── توزیع فروش بر اساس روز هفته ──
$dayOfWeek = [];
try {
    $dayOfWeek = $pdo->query("SELECT DAYOFWEEK(FROM_UNIXTIME(CAST(time_sell AS UNSIGNED))) dw, COUNT(*) cnt
                              FROM invoice WHERE Status IN ($done) AND time_sell REGEXP '^[0-9]+$'
                              GROUP BY dw ORDER BY dw")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$dayNames = ['','شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
// MySQL: 1=Sunday, 7=Saturday → تبدیل
$dayMap = [1=>'یکشنبه',2=>'دوشنبه',3=>'سه‌شنبه',4=>'چهارشنبه',5=>'پنجشنبه',6=>'جمعه',7=>'شنبه'];

// ─ـ توزیع فروش بر اساس ساعت ──
$hourlySales = [];
try {
    $hourlySales = $pdo->query("SELECT HOUR(FROM_UNIXTIME(CAST(time_sell AS UNSIGNED))) h, COUNT(*) cnt
                                FROM invoice WHERE Status IN ($done) AND time_sell REGEXP '^[0-9]+$'
                                GROUP BY h ORDER BY h")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ─ـ محاسبه‌ی نرخ retaining ──
$retentionRate = 0; $newUsers = 0; $returningUsers = 0;
try {
    $cutoff3 = strtotime("-30 days 00:00:00");
    // کاربرانی که در ۳۰ روز گذشته خرید کرده‌اند
    $stmt = $pdo->prepare("SELECT DISTINCT id_user FROM invoice WHERE Status IN ($done) AND CAST(time_sell AS UNSIGNED) >= ?");
    $stmt->execute([$cutoff3]);
    $recentBuyers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($recentBuyers)) {
        // چند نفر قبلاً هم خرید کرده بودند؟
        $ph = implode(',', array_fill(0, count($recentBuyers), '?'));
        $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE Status IN ($done) AND CAST(time_sell AS UNSIGNED) < ? AND id_user IN ($ph)");
        $stmt2->execute(array_merge([$cutoff3], $recentBuyers));
        $returningUsers = (int)$stmt2->fetchColumn();
        $newUsers = count($recentBuyers) - $returningUsers;
        $retentionRate = count($recentBuyers) > 0 ? round($returningUsers / count($recentBuyers) * 100, 1) : 0;
    }
} catch (Throwable $e) {}

// ─ـ میانگین ارزش سفارش ──
$avgOrder = $totalSales > 0 ? round($totalRevenue / $totalSales) : 0;

// آماده‌سازی داده‌ها برای نمودار
$chartLabels = []; $chartRevenue = []; $chartSales = [];
foreach ($dailyRevenue as $d) {
    $chartLabels[] = substr($d['d'], 5); // MM-DD
    $chartRevenue[] = (int)$d['rev'];
    $chartSales[] = (int)$d['cnt'];
}
$signupLabels = []; $signupData = [];
foreach ($dailySignups as $d) {
    $signupLabels[] = substr($d['d'], 5);
    $signupData[] = (int)$d['cnt'];
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>داشبورد تحلیلی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:18px;text-align:center}
.rx-stat .n{font-size:26px;font-weight:800;color:var(--accent)}
.rx-stat .l{color:var(--text-muted);font-size:12px;margin-top:4px}
.rx-stat .sub{font-size:10px;color:var(--text-muted);margin-top:2px}
.rx-chart-card{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:18px;margin-bottom:16px}
.rx-chart-card h2{color:var(--text-main);font-size:15px;margin:0 0 12px}
.rx-bar-chart{display:flex;align-items:flex-end;gap:2px;height:160px;margin-top:10px;overflow-x:auto;padding-bottom:4px}
.rx-bar-chart .bar{flex:1;min-width:8px;background:linear-gradient(180deg,var(--accent),var(--accent-mid));border-radius:4px 4px 0 0;min-height:2px;position:relative;cursor:pointer;transition:opacity .15s}
.rx-bar-chart .bar:hover{opacity:.75}
.rx-bar-chart .bar .tip{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:var(--surface-3);color:var(--text-main);padding:4px 8px;border-radius:6px;font-size:10px;white-space:nowrap;display:none;z-index:10;border:1px solid var(--border-mid)}
.rx-bar-chart .bar:hover .tip{display:block}
.rx-heatmap{display:grid;grid-template-columns:auto repeat(24,1fr);gap:2px;margin-top:10px;font-size:10px}
.rx-heatmap .h-cell{padding:2px;text-align:center;color:var(--text-muted);font-size:9px}
.rx-heatmap .hm{border-radius:3px;min-height:20px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:600}
.rx-days{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.rx-day{flex:1;min-width:80px;background:var(--surface-2);border-radius:8px;padding:8px;text-align:center}
.rx-day .dn{font-size:11px;color:var(--text-muted)}
.rx-day .dv{font-size:18px;font-weight:700;color:var(--text-main)}
.rx-days-bar{display:flex;align-items:flex-end;gap:6px;height:100px;margin-top:8px}
.rx-days-bar .db{flex:1;background:var(--accent);border-radius:6px 6px 0 0;min-height:3px;position:relative}
.rx-days-bar .db span{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-muted);margin-bottom:2px}
table{width:100%;border-collapse:collapse}th,td{padding:8px;text-align:right;font-size:13px;border-bottom:1px solid var(--border-soft);color:var(--text-main)}
th{color:var(--text-muted);font-size:12px}
.rx-period-btns{display:flex;gap:6px;margin-bottom:14px}
.rx-period-btns a{padding:8px 16px;border-radius:8px;background:var(--surface-1);border:1px solid var(--border-soft);color:var(--text-muted);font-size:13px;text-decoration:none;font-weight:600}
.rx-period-btns a.active{background:var(--accent);color:var(--accent-fg)}
.rx-retention-ring{display:flex;align-items:center;gap:14px}
.rx-ring{width:80px;height:80px;border-radius:50%;background:conic-gradient(var(--color-success) <?= $retentionRate ?>%, var(--surface-3) <?= $retentionRate ?>%);display:flex;align-items:center;justify-content:center;position:relative}
.rx-ring::after{content:'';position:absolute;width:60px;height:60px;border-radius:50%;background:var(--surface-1)}
.rx-ring span{position:relative;z-index:1;font-size:18px;font-weight:800;color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📊 داشبورد تحلیلی</h1>
<div class="page-head__sub">تحلیل پیشرفته‌ی فروش، کاربران و درآمد</div></div>

<div class="rx-period-btns">
<a href="?days=7" class="<?= $days===7?'active':'' ?>">۷ روز</a>
<a href="?days=14" class="<?= $days===14?'active':'' ?>">۱۴ روز</a>
<a href="?days=30" class="<?= $days===30?'active':'' ?>">۳۰ روز</a>
<a href="?days=90" class="<?= $days===90?'active':'' ?>">۹۰ روز</a>
</div>

<!-- آمار کلی -->
<div class="rx-grid">
<div class="rx-stat"><div class="n"><?= number_format($totalRevenue) ?></div><div class="l">کل درآمد (تومان)</div></div>
<div class="rx-stat"><div class="n"><?= $totalSales ?></div><div class="l">کل فروش</div></div>
<div class="rx-stat"><div class="n"><?= number_format($avgOrder) ?></div><div class="l">میانگین هر سفارش</div></div>
<div class="rx-stat"><div class="n"><?= $totalUsers ?></div><div class="l">کل کاربران</div><div class="sub"><?= $totalResellers ?> نماینده</div></div>
</div>

<!-- نمودار درآمد -->
<div class="rx-chart-card">
<h2>📈 درآمد روزانه (<?= count($dailyRevenue) ?> روز)</h2>
<?php if (empty($dailyRevenue)): ?>
<p style="color:var(--text-muted)">داده‌ای در این بازه نیست.</p>
<?php else:
$maxRev = max(1, max($chartRevenue));
?>
<div class="rx-bar-chart">
<?php foreach ($dailyRevenue as $i => $d): ?>
<div class="bar" style="height:<?= max(3, ($chartRevenue[$i]/$maxRev)*100) ?>%">
<div class="tip"><?= htmlspecialchars($d['d']) ?>: <?= number_format($chartRevenue[$i]) ?> ت (<?= $chartSales[$i] ?> فروش)</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- نمودار رشد کاربران -->
<div class="rx-chart-card">
<h2>👥 رشد کاربران (ثبت‌نام روزانه)</h2>
<?php if (empty($dailySignups)): ?>
<p style="color:var(--text-muted)">داده‌ای نیست.</p>
<?php else: $maxSignup = max(1, max($signupData)); ?>
<div class="rx-bar-chart">
<?php foreach ($dailySignups as $i => $d): ?>
<div class="bar" style="height:<?= max(3, ($signupData[$i]/$maxSignup)*100) ?>%;background:linear-gradient(180deg,#22c55e,#16a34a)">
<div class="tip"><?= htmlspecialchars($signupLabels[$i]) ?>: <?= $signupData[$i] ?> کاربر جدید</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<!-- نرخ بازگشت -->
<div class="rx-chart-card">
<h2>🔁 نرخ retaining (۳۰ روز)</h2>
<div class="rx-retention-ring">
<div class="rx-ring"><span><?= $retentionRate ?>%</span></div>
<div style="font-size:13px;color:var(--text-muted);line-height:2">
🟢 بازگشته: <b style="color:var(--text-main)"><?= $returningUsers ?></b><br>
🔵 جدید: <b style="color:var(--text-main)"><?= $newUsers ?></b><br>
📊 کل خریداران: <b style="color:var(--text-main)"><?= $returningUsers + $newUsers ?></b>
</div>
</div>
</div>

<!-- میانگین روزانه -->
<div class="rx-chart-card">
<h2>📋 خلاصه بازه‌ی <?= $days ?> روزه</h2>
<?php
$periodRev = array_sum($chartRevenue);
$periodSales = array_sum($chartSales);
$avgDailyRev = count($chartLabels) > 0 ? round($periodRev / count($chartLabels)) : 0;
?>
<div style="font-size:14px;color:var(--text-muted);line-height:2.2">
💰 درآمد بازه: <b style="color:var(--text-main)"><?= number_format($periodRev) ?></b> ت<br>
🛍 تعداد فروش: <b style="color:var(--text-main)"><?= $periodSales ?></b><br>
📅 میانگین روزانه: <b style="color:var(--text-main)"><?= number_format($avgDailyRev) ?></b> ت<br>
📦 میانگین هر سفارش: <b style="color:var(--text-main)"><?= $periodSales > 0 ? number_format(round($periodRev/$periodSales)) : 0 ?></b> ت
</div>
</div>
</div>

<!-- پرفروش‌ترین محصولات -->
<div class="rx-chart-card">
<h2>🛍 پرفروش‌ترین محصولات</h2>
<?php if (empty($topProducts)): ?>
<p style="color:var(--text-muted)">داده‌ای نیست.</p>
<?php else: ?>
<table><thead><tr><th>محصول</th><th>تعداد فروش</th><th>درآمد</th><th>سهم</th></tr></thead><tbody>
<?php $totalProdRev = array_sum(array_column($topProducts, 'rev'));
foreach ($topProducts as $p): $share = $totalProdRev > 0 ? round($p['rev']/$totalProdRev*100) : 0; ?>
<tr><td><?= htmlspecialchars((string)$p['name_product'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= (int)$p['cnt'] ?></td>
<td><?= number_format((int)$p['rev']) ?></td>
<td>
<div style="background:var(--surface-3);border-radius:4px;height:16px;overflow:hidden;width:120px">
<div style="background:var(--accent);height:100%;width:<?= $share ?>%;border-radius:4px"></div>
</div>
<span style="font-size:11px;color:var(--text-muted)"><?= $share ?>%</span>
</td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>

<!-- توزیع فروش بر اساس روز هفته -->
<div class="rx-chart-card">
<h2>📅 توزیع فروش بر اساس روز هفته</h2>
<?php if (empty($dayOfWeek)): ?>
<p style="color:var(--text-muted)">داده‌ای نیست.</p>
<?php else: $maxDow = max(array_column($dayOfWeek, 'cnt')); ?>
<div class="rx-days-bar">
<?php
$dowData = [];
foreach ($dayOfWeek as $d) { $dowData[(int)$d['dw']] = (int)$d['cnt']; }
for ($dow = 1; $dow <= 7; $dow++):
    $cnt = $dowData[$dow] ?? 0;
    $h = $maxDow > 0 ? max(4, ($cnt/$maxDow)*100) : 0;
?>
<div class="db" style="height:<?= $h ?>%"><span><?= $cnt ?></span></div>
<?php endfor; ?>
</div>
<div style="display:flex;gap:6px;margin-top:4px">
<?php for ($dow = 1; $dow <= 7; $dow++): ?>
<div style="flex:1;text-align:center;font-size:10px;color:var(--text-muted)"><?= $dayMap[$dow] ?? '' ?></div>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>

<!-- نقشه‌ی حرارتی فروش بر اساس ساعت -->
<div class="rx-chart-card">
<h2>🔥 نقشه‌ی حرارتی فروش (ساعتی)</h2>
<?php if (empty($hourlySales)): ?>
<p style="color:var(--text-muted)">داده‌ای نیست.</p>
<?php else:
$hourMap = [];
foreach ($hourlySales as $h) { $hourMap[(int)$h['h']] = (int)$h['cnt']; }
$maxHour = max(array_column($hourlySales, 'cnt'));
?>
<div style="display:grid;grid-template-columns:repeat(24,1fr);gap:3px;margin-top:8px">
<?php for ($hr = 0; $hr < 24; $hr++):
    $cnt = $hourMap[$hr] ?? 0;
    $intensity = $maxHour > 0 && $cnt > 0 ? $cnt / $maxHour : 0;
    $bg = $cnt > 0 ? "rgba(124,92,255,$intensity)" : 'var(--surface-2)';
    $color = $intensity > 0.5 ? '#fff' : 'var(--text-muted)';
?>
<div style="background:<?= $bg ?>;border-radius:4px;padding:6px 0;text-align:center;font-size:9px;color:<?= $color ?>" title="ساعت <?= $hr ?>: <?= $cnt ?> فروش">
<?= $cnt > 0 ? $cnt : '·' ?>
</div>
<?php endfor; ?>
</div>
<div style="display:flex;gap:3px;margin-top:4px">
<?php for ($hr = 0; $hr < 24; $hr++): ?>
<div style="flex:1;text-align:center;font-size:8px;color:var(--text-muted)"><?= $hr % 3 === 0 ? $hr : '' ?></div>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>

</div></section></section></body></html>
