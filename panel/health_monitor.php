<?php
/**
 * Red Fox — مانیتور سلامت پنل‌ها (Health Monitor Dashboard).
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

rx_require_schema($pdo,[],['marzban_panel'=>['last_health_check','health_status']]);

$panels = [];
try { $panels = $pdo->query("SELECT code_panel, name_panel, url_panel, type, status, health_status, last_health_check FROM marzban_panel ORDER BY status DESC, name_panel ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$healthyCount = 0; $downCount = 0; $unknownCount = 0;
foreach ($panels as $p) {
    $st = (string)($p['health_status'] ?? 'unknown');
    if (strpos($st, 'healthy') !== false) $healthyCount++;
    elseif (strpos($st, 'down') !== false) $downCount++;
    else $unknownCount++;
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مانیتور سلامت | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:18px;text-align:center}
.rx-stat .n{font-size:26px;font-weight:800}.rx-stat .l{color:var(--text-muted);font-size:12px;margin-top:4px}
.rx-stat.ok .n{color:var(--color-success)}.rx-stat.bad .n{color:var(--color-danger)}.rx-stat.unk .n{color:var(--text-muted)}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:right;font-size:13px;border-bottom:1px solid var(--border-soft);color:var(--text-main)}
th{color:var(--text-muted);font-size:12px}
.badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700}
.badge-ok{background:var(--color-success-soft);color:var(--color-success)}
.badge-bad{background:var(--color-danger-soft);color:var(--color-danger)}
.badge-unk{background:var(--surface-3);color:var(--text-muted)}
.rx-info{background:var(--accent-soft);border-radius:10px;padding:14px;font-size:12px;color:var(--text-main);line-height:2;margin-top:14px}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🏥 مانیتور سلامت پنل‌ها</h1>
<div class="page-head__sub">وضعیت لحظه‌ای پنل‌های VPN</div></div>

<div class="rx-grid">
<div class="rx-stat ok"><div class="n"><?= $healthyCount ?></div><div class="l">✅ سالم</div></div>
<div class="rx-stat bad"><div class="n"><?= $downCount ?></div><div class="l">❌ قطع</div></div>
<div class="rx-stat unk"><div class="n"><?= $unknownCount ?></div><div class="l">❓ نامشخص</div></div>
</div>

<div class="card">
<div class="table-wrap"><table><thead><tr><th>پنل</th><th>نوع</th><th>وضعیت</th><th>سلامت</th><th>آخرین بررسی</th></tr></thead><tbody>
<?php foreach ($panels as $p):
    $hs = (string)($p['health_status'] ?? 'unknown');
    $isHealthy = strpos($hs, 'healthy') !== false;
    $isDown = strpos($hs, 'down') !== false;
    $badge = $isHealthy ? 'badge-ok' : ($isDown ? 'badge-bad' : 'badge-unk');
    $icon = $isHealthy ? '✅' : ($isDown ? '❌' : '❓');
?>
<tr>
<td><b><?= htmlspecialchars((string)$p['name_panel'],ENT_QUOTES,'UTF-8') ?></b></td>
<td class="text-muted"><?= htmlspecialchars((string)$p['type'],ENT_QUOTES) ?></td>
<td><span class="badge <?= $p['status']==='active'?'badge-ok':'badge-bad' ?>"><?= htmlspecialchars((string)$p['status'],ENT_QUOTES) ?></span></td>
<td><span class="badge <?= $badge ?>"><?= $icon ?> <?= htmlspecialchars($hs,ENT_QUOTES) ?></span></td>
<td class="text-muted" style="font-size:11px"><?= htmlspecialchars((string)($p['last_health_check'] ?? '—'),ENT_QUOTES) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<div class="rx-info">
💡 مانیتور سلامت به‌صورت خودکار توسط <code>cron_campaigns.php</code> هر ساعت به‌روزرسانی می‌شود.<br>
⚙️ برای بررسی فوری، یک بار <code>cron_campaigns.php</code> را در مرورگر باز کنید.<br>
🔄 در صورت قطعی پنل، سیستم «پنل اضطراری» می‌تواند خودکار به پنل پشتیبان سوییچ کند (در تنظیمات پنل فعال کنید).
</div>

</div></section></section></body></html>
