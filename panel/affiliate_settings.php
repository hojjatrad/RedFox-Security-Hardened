<?php
/** Red Fox — تنظیمات سیستم زیرمجموعه‌گیری و کمیسیون (#15) */
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
$affStatus='offaffiliates'; $affPercent='0';
try { $r=$pdo->query("SELECT affiliatesstatus, affiliatespercentage FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC); $affStatus=(string)($r['affiliatesstatus']??'offaffiliates'); $affPercent=(string)($r['affiliatespercentage']??'0'); } catch(Throwable $e){}
// جدول affiliates (یک ردیف config)
$affRow=null; try{$affRow=$pdo->query("SELECT * FROM affiliates LIMIT 1")->fetch(PDO::FETCH_ASSOC);}catch(Throwable $e){}

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin && (string)($_POST['action']??'')==='save') {
    try {
        $st=(string)($_POST['affiliatesstatus']??'')==='on'?'onaffiliates':'offaffiliates';
        $pc=trim((string)($_POST['affiliatespercentage']??'0')); if(!ctype_digit(ltrim($pc,'-'))) $pc='0';
        $pdo->prepare("UPDATE setting SET affiliatesstatus=?, affiliatespercentage=?")->execute([$st,$pc]);
        // affiliates table
        $sc=(string)($_POST['status_commission']??'')==='on'?'oncommission':'offcommission';
        $disc=(string)($_POST['Discount']??'')==='on'?'onDiscountaffiliates':'offDiscountaffiliates';
        $pd=trim((string)($_POST['price_Discount']??'0')); if(!ctype_digit(ltrim($pd,'-'))) $pd='0';
        $pob=(string)($_POST['porsant_one_buy']??'')==='on'?'on_buy_porsant':'off_buy_porsant';
        try { $pdo->prepare("UPDATE affiliates SET status_commission=?, Discount=?, price_Discount=?, porsant_one_buy=?")->execute([$sc,$disc,$pd,$pob]); } catch(Throwable $e){}
        $affStatus=$st; $affPercent=$pc;
        $flash='تنظیمات زیرمجموعه‌گیری ذخیره شد.'; $flashType='success';
    } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/affiliate_settings.php'),ENT_QUOTES);$flashType='error';}
}
$affOn = $affStatus==='onaffiliates';
$scOn = ($affRow['status_commission']??'')==='oncommission';
$discOn = ($affRow['Discount']??'')==='onDiscountaffiliates';
$pobOn = ($affRow['porsant_one_buy']??'')==='on_buy_porsant';
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>زیرمجموعه‌گیری | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-field{margin-bottom:12px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}.rx-field input{width:100%;max-width:320px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;box-sizing:border-box}.rx-toggle{display:inline-flex;align-items:center;gap:8px;margin:8px 0;font-size:14px;color:var(--text-main)}.rx-toggle input{width:auto}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🎁 زیرمجموعه‌گیری و کمیسیون</h1><div class="page-head__sub">مدیریت سیستم دعوت و پورسانت زیرمجموعه</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات</h2></div>
<?php if($isMainAdmin): ?>
<form method="post"><input type="hidden" name="action" value="save">
<div class="rx-toggle"><input type="checkbox" name="affiliatesstatus" value="on" id="afst" <?= $affOn?'checked':'' ?>><label for="afst" style="margin:0">🎁 فعال‌سازی سیستم زیرمجموعه‌گیری</label></div>
<div class="rx-field"><label>درصد پورسانت زیرمجموعه (٪)</label><input type="number" name="affiliatespercentage" value="<?= htmlspecialchars($affPercent,ENT_QUOTES) ?>" min="0" max="100"></div>
<div class="rx-toggle"><input type="checkbox" name="status_commission" value="on" id="afsc" <?= $scOn?'checked':'' ?>><label for="afsc" style="margin:0">💵 پورسانت در زمان تمدید هم داده شود</label></div>
<div class="rx-toggle"><input type="checkbox" name="Discount" value="on" id="afdi" <?= $discOn?'checked':'' ?>><label for="afdi" style="margin:0">🏷 کد تخفیف اختصاصی برای زیرمجموعه</label></div>
<div class="rx-field"><label>مبلغ کد تخفیف زیرمجموعه (تومان)</label><input type="number" name="price_Discount" value="<?= htmlspecialchars((string)($affRow['price_Discount']??'0'),ENT_QUOTES) ?>" min="0"></div>
<div class="rx-toggle"><input type="checkbox" name="porsant_one_buy" value="on" id="afpb" <?= $pobOn?'checked':'' ?>><label for="afpb" style="margin:0">🔄 پورسانت فقط از خرید اول</label></div>
<button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 سیستم زیرمجموعه: هر کاربر لینک دعوت اختصاصی دارد. وقتی زیرمجموعه‌اش خرید می‌کند، درصدی به‌عنوان پورسانت به کیف پولش واریز می‌شود.</p>
</div>
</div></section></section></body></html>
