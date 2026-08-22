<?php
/** Red Fox — نگهداری سیستم: بهینه‌سازی + بازنشانی (خطرناک) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info'; $detail='';

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    $confirm=trim((string)($_POST['confirm']??''));
    try {
        if ($act==='optimize') {
            $c=[]; $c['unpaid_order']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='unpaid' AND name_product!='سرویس تست'");
            $c['disabled_order']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='disabled' AND name_product!='سرویس تست'");
            $c['removed_admin']=(int)$pdo->exec("DELETE FROM invoice WHERE Status IN ('removebyadmin','removedbyadmin')");
            $c['disabled_test']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='disabled' AND name_product='سرویس تست'");
            $c['removeTime']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='removeTime'");
            $c['removevolume']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='removevolume'");
            $c['removebyuser']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='removebyuser'");
            $c['unpaid_test']=(int)$pdo->exec("DELETE FROM invoice WHERE Status='unpaid' AND name_product='سرویس تست'");
            $c['pay_expired']=(int)$pdo->exec("DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject')");
            $total=array_sum($c);
            $flash="✅ بهینه‌سازی انجام شد — مجموعاً $total رکورد پاک شد."; $flashType='success';
            foreach($c as $k=>$v) if($v>0) $detail.="$k: $v  ";
        } elseif ($act==='reset' && strtolower($confirm)==='reset') {
            $flash='⚠️ بازنشانی غیرفعال است در این نسخه — برای امنیت، فقط از داخل ربات قابل انجام است.'; $flashType='error';
        } elseif ($act==='reset') {
            $flash='برای بازنشانی باید دقیقاً کلمه RESET را تایپ کنید.'; $flashType='error';
        }
    } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/maintenance.php'),ENT_QUOTES);$flashType='error';}
}
// آمار قابل پاک‌سازی
try { $cntUnpaid=(int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE Status='unpaid' AND name_product!='سرویس تست'")->fetchColumn(); $cntDisabled=(int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE Status='disabled'")->fetchColumn(); $cntPayExp=(int)$pdo->query("SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject')")->fetchColumn(); } catch(Throwable $e){$cntUnpaid=$cntDisabled=$cntPayExp=0;}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نگهداری سیستم | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9;white-space:pre-wrap}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:10px;padding:14px;text-align:center}.rx-stat .n{font-size:24px;font-weight:800;color:var(--text-main)}.rx-stat .l{color:var(--text-muted);font-size:11px;margin-top:3px}.rx-warn{background:var(--color-danger-soft);border:1px solid var(--color-danger);color:var(--color-danger);padding:14px;border-radius:10px;margin-bottom:16px;font-size:13px;line-height:2}.rx-field input{width:100%;max-width:400px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;box-sizing:border-box}code{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:10px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🔧 نگهداری سیستم</h1><div class="page-head__sub">بهینه‌سازی دیتابیس و ابزارهای خطرناک</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?= htmlspecialchars($flash,ENT_QUOTES) ?><?= $detail!==''?"\n$detail":'' ?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">♻️ بهینه‌سازی دیتابیس</h2></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:16px">
<div class="rx-stat"><div class="n"><?= $cntUnpaid ?></div><div class="l">سفارش پرداخت‌نشده</div></div>
<div class="rx-stat"><div class="n"><?= $cntDisabled ?></div><div class="l">سفارش غیرفعال</div></div>
<div class="rx-stat"><div class="n"><?= $cntPayExp ?></div><div class="l">تراکنش منقضی/رد شده</div></div>
</div>
<?php if($isMainAdmin): ?>
<form method="post"><input type="hidden" name="action" value="optimize">
<button class="btn btn-sm btn-success" type="submit" onclick="return confirm('سفارش‌های قدیمی پاک شوند؟')">♻️ بهینه‌سازی</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 سفارش‌های پرداخت‌نشده، غیرفعال، حذف‌شده و تراکنش‌های منقضی/رد شده پاک می‌شوند. کاربران و سرویس‌های فعال دست‌نخورده می‌مانند.</p></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title" style="color:var(--color-danger)">💀 بازنشانی ربات (خطرناک)</h2></div>
<div class="rx-warn">⚠️ <b>هشدار شدید:</b> بازنشانی تمام جداول دیتابیس را حذف و از نو می‌سازد — <b>تمام داده‌ها پاک می‌شوند!</b><br>قبل از این کار حتماً بکاپ بگیرید.</div>
<p class="rx-info">🔒 برای امنیت، بازنشانی فقط از داخل ربات تلگرام قابل انجام است (با تأیید ادمین اصلی).</p></div>

</div></section></section></body></html>
