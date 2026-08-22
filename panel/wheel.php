<?php
/** Red Fox — تنظیمات گردونه شانس (statusfirstwheel on/off + مبلغ برنده) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

// خواندن تنظیمات فعلی
$wheelStatus = '0'; $wheelPrice = '0';
try { $s=$pdo->query("SELECT statusfirstwheel, wheel_luck_price FROM setting LIMIT 1"); $r=$s->fetch(PDO::FETCH_ASSOC); $wheelStatus=(string)($r['statusfirstwheel']??'0'); $wheelPrice=(string)($r['wheel_luck_price']??'0'); } catch(Throwable $e) {
    // fallback: ستون با نام قدیمی
    try { $s2=$pdo->query("SELECT statusfirstwheel FROM setting LIMIT 1"); $r2=$s2->fetch(PDO::FETCH_ASSOC); $wheelStatus=(string)($r2['statusfirstwheel']??'0'); } catch(Throwable $e2){}
}

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin && (string)($_POST['action']??'')==='save') {
    try {
        $ws = (string)($_POST['statusfirstwheel']??'')==='on'?'1':'0';
        $wp = (string)trim((string)($_POST['wheel_price']??'0')); if(!ctype_digit(ltrim($wp,'-'))) $wp='0';
        $pdo->prepare("UPDATE setting SET statusfirstwheel=?")->execute([$ws]);
        // ستون wheel price با نام متغیر — تست می‌کنیم اگر ستون هست
        try { $pdo->prepare("UPDATE setting SET wheel_luck_price=?")->execute([$wp]); } catch(Throwable $e) {}
        $wheelStatus=$ws; $wheelPrice=$wp;
        $flash='تنظیمات گردونه ذخیره شد.'; $flashType='success';
    } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/wheel.php'),ENT_QUOTES);$flashType='error';}
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>گردونه شانس | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-field{margin-bottom:12px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}.rx-field input{width:100%;max-width:360px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;box-sizing:border-box}.rx-toggle{display:inline-flex;align-items:center;gap:8px}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🎡 گردونه شانس</h1><div class="page-head__sub">فعال‌سازی و مبلغ جایزه</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات</h2></div>
<?php if($isMainAdmin): ?>
<form method="post"><input type="hidden" name="action" value="save">
<div class="rx-field rx-toggle"><input type="checkbox" name="statusfirstwheel" value="on" id="wst" <?= $wheelStatus==='1'?'checked':'' ?>><label for="wst" style="margin:0;font-size:14px">🎡 فعال‌سازی گردونه شانس برای کاربران</label></div>
<div class="rx-field"><label>مبلغ جایزه برنده (تومان)</label><input type="number" name="wheel_price" value="<?= htmlspecialchars($wheelPrice,ENT_QUOTES) ?>" min="0"></div>
<button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 وقتی فعال باشد، کاربران می‌توانند روزانه گردونه بچرخانند. مبلغ به کیف پول برنده واریز می‌شود.<br>🎯 جایزه‌های قرعه‌کشی شبانه از بخش «قرعه‌کشی» جداگانه تنظیم می‌شود.</p>
</div>
</div></section></section></body></html>
