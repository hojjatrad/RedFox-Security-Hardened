<?php
/** Red Fox — مدیریت آی‌پی‌های مجاز ورود به پنل (setting.iplogin) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

function rx_ipload(){ global $pdo; try{ $v=(string)$pdo->query("SELECT iplogin FROM setting LIMIT 1")->fetchColumn(); if($v==='*'||$v==='all'||$v==='unlimited') return ['unlimited'=>true,'ips'=>[]]; $d=json_decode($v,true); if(is_array($d)){ if(in_array('*',$d,true)) return ['unlimited'=>true,'ips'=>[]]; return ['unlimited'=>false,'ips'=>array_values($d)]; } if(filter_var($v,FILTER_VALIDATE_IP)) return ['unlimited'=>false,'ips'=>[$v]]; }catch(Throwable $e){} return ['unlimited'=>false,'ips'=>[]]; }
function rx_ipsave($data){ global $pdo; $val = $data['unlimited'] ? '*' : json_encode(array_values(array_unique(array_filter($data['ips'],'fn_ipclean')))); try{ $pdo->prepare("UPDATE setting SET iplogin=?")->execute([$val]); return true; }catch(Throwable $e){ return false; } }
function fn_ipclean($ip){ return filter_var(trim((string)$ip),FILTER_VALIDATE_IP)!==false; }

$ipData = rx_ipload();
if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    if ($act==='add') { $nip=trim((string)($_POST['ip']??'')); if(fn_ipclean($nip)){ $ipData['ips'][]=$nip; rx_ipsave($ipData); $flash='آی‌پی اضافه شد.'; $flashType='success'; } else { $flash='آی‌پی نامعتبر.'; $flashType='error'; } }
    elseif ($act==='del') { $dip=(string)($_POST['ip']??''); $ipData['ips']=array_values(array_filter($ipData['ips'],fn($x)=>$x!==$dip)); rx_ipsave($ipData); $flash='حذف شد.'; $flashType='success'; }
    elseif ($act==='unlim') { $ipData['unlimited']=(string)($_POST['val']??'')==='/on'; rx_ipsave($ipData); $flash=$ipData['unlimited']?'حالت نامحدود فعال شد.':'حالت نامحدود غیرفعال شد.'; $flashType='success'; }
    $ipData = rx_ipload();
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>آی‌پی ورود | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-inline{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}.rx-inline input{padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;direction:ltr}code{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🔒 آی‌پی‌های مجاز ورود</h1><div class="page-head__sub">محدود کردن ورود به پنل وب به آی‌پی‌های خاص</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات</h2></div>
<?php if($isMainAdmin): ?>
<?php if($ipData['unlimited']): ?>
<div class="rx-inline"><form method="post"><input type="hidden" name="action" value="unlim"><input type="hidden" name="val" value="/off"><button class="btn btn-sm btn-danger" type="submit">🔒 غیرفعال‌سازی حالت نامحدود</button></form></div>
<?php else: ?>
<div class="rx-inline"><form method="post"><input type="hidden" name="action" value="unlim"><input type="hidden" name="val" value="/on"><button class="btn btn-sm btn-success" type="submit">♾️ فعال‌سازی حالت نامحدود (هر آی‌پی)</button></form></div>
<?php endif; ?>
<p class="rx-info">💬 آی‌پی فعلی شما: <code><?= htmlspecialchars(redfox_client_ip(),ENT_QUOTES) ?></code></p>
<?php endif; ?>
</div>
<?php if(!$ipData['unlimited']): ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📋 آی‌پی‌های مجاز (<?= count($ipData['ips']) ?>)</h2></div>
<?php if($isMainAdmin): ?>
<form method="post" class="rx-inline" style="margin-bottom:12px"><input type="hidden" name="action" value="add"><input type="text" name="ip" placeholder="1.2.3.4" required style="direction:ltr;width:200px"><button class="btn btn-sm btn-success" type="submit">افزودن</button></form>
<?php endif; ?>
<?php if(empty($ipData['ips'])): ?><p class="text-muted">هیچ آی‌پی ثبت نشده. ⚠️ در این حالت ورود به پنل برای همه مسدود است.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>آی‌پی</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach($ipData['ips'] as $ip): ?><tr><td style="direction:ltr"><code><?= htmlspecialchars($ip,ENT_QUOTES) ?></code></td><td><?php if($isMainAdmin): ?><form method="post" class="rx-inline" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="del"><input type="hidden" name="ip" value="<?=htmlspecialchars($ip,ENT_QUOTES)?>"><button class="btn btn-sm btn-danger" type="submit">حذف</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</div><?php endif; ?>
</div></section></section></body></html>
