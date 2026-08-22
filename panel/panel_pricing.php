<?php
/** Red Fox — قیمت‌گذاری حجم/زمان اضافه per پنل per گروه agent (#10) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

$panels=[]; try{$panels=$pdo->query("SELECT id, name_panel, type, priceextravolume, pricecustomvolume, priceextratime, pricecustomtime FROM marzban_panel ORDER BY name_panel ASC")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
$agentGroups=['f'=>'کاربر عادی','n'=>'نماینده عادی','n2'=>'نماینده پیشرفته','all'=>'همه'];
$priceTypes=['priceextravolume'=>'قیمت حجم اضافه (per گیگ)','pricecustomvolume'=>'قیمت حجم دلخواه (per گیگ)','priceextratime'=>'قیمت زمان اضافه (per روز)','pricecustomtime'=>'قیمت زمان دلخواه (per روز)'];

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin && (string)($_POST['action']??'')==='save') {
    $pid=(int)($_POST['panel_id']??0);
    if ($pid>0) {
        try {
            foreach (array_keys($priceTypes) as $pt) {
                $data=[];
                foreach (array_keys($agentGroups) as $ag) {
                    $val=trim((string)($_POST[$pt.'_'.$ag]??''));
                    if($val!=='' && ctype_digit($val)) $data[$ag]=(int)$val;
                }
                $pdo->prepare("UPDATE marzban_panel SET {$pt}=? WHERE id=?")->execute([json_encode($data,JSON_UNESCAPED_UNICODE),$pid]);
            }
            $flash='قیمت‌ها ذخیره شد.'; $flashType='success';
        } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/panel_pricing.php'),ENT_QUOTES);$flashType='error';}
    }
    // رفرش پنل
    try{$panels=$pdo->query("SELECT id, name_panel, type, priceextravolume, pricecustomvolume, priceextratime, pricecustomtime FROM marzban_panel ORDER BY name_panel ASC")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>قیمت‌گذاری پنل‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin:3px 0}.rx-field input{width:90px;padding:6px 8px;border-radius:6px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:12px;direction:ltr}.rx-grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:10px}code{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:8px}.rx-panel-block{border:1px solid var(--border-soft);border-radius:10px;padding:12px;margin-bottom:14px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">💲 قیمت‌گذاری حجم/زمان</h1><div class="page-head__sub">قیمت حجم و زمان اضافه برای هر پنل و هر گروه کاربری</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<?php if($isMainAdmin && !empty($panels)): foreach($panels as $p):
    $pid=(int)$p['id']; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🖥 <?= htmlspecialchars((string)$p['name_panel'],ENT_QUOTES,'UTF-8') ?> <small class="text-muted">(<?= htmlspecialchars((string)$p['type'],ENT_QUOTES) ?>)</small></h2></div>
<form method="post" class="rx-panel-block"><input type="hidden" name="action" value="save"><input type="hidden" name="panel_id" value="<?= $pid ?>">
<?php foreach ($priceTypes as $pt=>$pl): $j=json_decode((string)($p[$pt]??'{}'),true)?:[]; ?>
<div style="margin-bottom:8px"><b style="font-size:13px;color:var(--text-main)"><?= $pl ?></b>
<div class="rx-grid4">
<?php foreach ($agentGroups as $ag=>$al): ?>
<div class="rx-field"><label><?= $al ?></label><input type="text" name="<?= $pt ?>_<?= $ag ?>" value="<?= htmlspecialchars((string)($j[$ag]??''),ENT_QUOTES) ?>" placeholder="0"></div>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<button class="btn btn-sm btn-primary" type="submit">ذخیره قیمت‌های این پنل</button>
</form></div>
<?php endforeach; elseif(empty($panels)): ?>
<div class="card"><p class="text-muted">پنلی ثبت نشده است.</p></div>
<?php endif; ?>
<p class="rx-info">💡 مقادیر خالی یا صفر = از قیمت پیش‌فرض محصول استفاده می‌شود. «همه» = برای همه‌ی گروه‌ها اعمال می‌شود.</p>
</div></section></section></body></html>
