<?php
/**
 * Red Fox — تحلیل گروهی (Cohort Analysis).
 * ردیابی retaining کاربران بر اساس ماه ثبت‌نام.
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }

// محاسبه‌ی cohort: گروه‌بندی کاربران بر اساس ماه ثبت‌نام
$cohorts = [];
try {
    // ماه ثبت‌نام هر کاربر + خریدهای بعدی
    $stmt = $pdo->query("SELECT u.id, FROM_UNIXTIME(CAST(u.register AS UNSIGNED), '%Y-%m') signup_month,
                                COUNT(DISTINCT i.id_invoice) total_purchases,
                                MAX(i.time_sell) last_purchase
                         FROM user u
                         LEFT JOIN invoice i ON i.id_user = u.id AND i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                         WHERE u.register REGEXP '^[0-9]+$' AND CAST(u.register AS UNSIGNED) > 1000000000
                         GROUP BY u.id, signup_month
                         ORDER BY signup_month DESC");
    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // گروه‌بندی بر اساس ماه ثبت‌نام
    $cohortData = [];
    foreach ($userData as $u) {
        $month = $u['signup_month'];
        if (!isset($cohortData[$month])) $cohortData[$month] = ['users' => 0, 'purchasers' => 0, 'repeat' => 0, 'revenue' => 0];
        $cohortData[$month]['users']++;
        if ((int)$u['total_purchases'] > 0) {
            $cohortData[$month]['purchasers']++;
            if ((int)$u['total_purchases'] > 1) $cohortData[$month]['repeat']++;
        }
    }
    // محاسبه‌ی درصدها
    foreach ($cohortData as $month => $d) {
        $conversionRate = $d['users'] > 0 ? round($d['purchasers'] / $d['users'] * 100, 1) : 0;
        $repeatRate = $d['purchasers'] > 0 ? round($d['repeat'] / $d['purchasers'] * 100, 1) : 0;
        $cohorts[] = [
            'month' => $month,
            'users' => $d['users'],
            'purchasers' => $d['purchasers'],
            'conversion' => $conversionRate,
            'repeat' => $d['repeat'],
            'repeat_rate' => $repeatRate,
        ];
    }
    // مرتب‌سازی نزولی
    usort($cohorts, function($a, $b) { return strcmp($b['month'], $a['month']); });
} catch (Throwable $e) {}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تحلیل گروهی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:center;font-size:13px;border-bottom:1px solid var(--border-soft);color:var(--text-main)}
th{color:var(--text-muted);font-size:12px}
.rx-bar-cell{background:var(--surface-2);border-radius:4px;height:20px;overflow:hidden;position:relative;min-width:60px}
.rx-bar-fill{height:100%;border-radius:4px;background:var(--accent);position:absolute;left:0;top:0}
.rx-bar-text{position:relative;z-index:1;font-size:10px;color:var(--text-main);line-height:20px;text-align:center}
.rx-info{background:var(--accent-soft);border-radius:10px;padding:14px;font-size:12px;color:var(--text-main);line-height:2;margin-bottom:14px}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">👥 تحلیل گروهی (Cohort)</h1>
<div class="page-head__sub">ردیابی کیفیت کاربران بر اساس ماه ثبت‌نام</div></div>

<div class="rx-info">
💡 این جدول نشان می‌دهد کاربرانی که در هر ماه ثبت‌نام کرده‌اند، چه درصدی واقعاً خرید کرده‌اند (نرخ تبدیل) و چه درصدی دوباره خرید کرده‌اند (نرخ بازگشت).
<br>هرچه نرخ تبدیل بالاتر باشد، یعنی کاربران آن ماه کیفیت بهتری داشته‌اند.
</div>

<div class="card">
<div class="table-wrap"><table><thead><tr>
<th>ماه ثبت‌نام</th><th>ثبت‌نام‌کنندگان</th><th>خریداران</th><th>نرخ تبدیل</th><th>خرید مجدد</th><th>نرخ بازگشت</th>
</tr></thead><tbody>
<?php
$maxConversion = 0;
foreach ($cohorts as $c) { $maxConversion = max($maxConversion, $c['conversion']); }
foreach ($cohorts as $c):
    $convW = $maxConversion > 0 ? ($c['conversion']/$maxConversion)*100 : 0;
?>
<tr>
<td><b><?= htmlspecialchars($c['month'],ENT_QUOTES) ?></b></td>
<td><?= $c['users'] ?></td>
<td><?= $c['purchasers'] ?></td>
<td>
<div class="rx-bar-cell"><div class="rx-bar-fill" style="width:<?= $convW ?>%"></div>
<div class="rx-bar-text"><?= $c['conversion'] ?>%</div></div>
</td>
<td><?= $c['repeat'] ?></td>
<td>
<div class="rx-bar-cell"><div class="rx-bar-fill" style="width:<?= min(100,$c['repeat_rate']) ?>%;background:linear-gradient(90deg,#22c55e,#16a34a)"></div>
<div class="rx-bar-text"><?= $c['repeat_rate'] ?>%</div></div>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($cohorts)): ?><tr><td colspan="6" style="color:var(--text-muted);text-align:center;padding:20px">داده‌ای موجود نیست.</td></tr><?php endif; ?>
</tbody></table></div>
</div>

</div></section></section></body></html>
