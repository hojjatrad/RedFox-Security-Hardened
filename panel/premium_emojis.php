<?php
/** Red Fox — مدیریت ایموجی‌های پرمیوم (premium_emojis table) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    try {
        if ($act==='add') {
            $em=trim((string)($_POST['emoji']??'')); $cid=trim((string)($_POST['custom_emoji_id']??'')); $lb=trim((string)($_POST['label']??''));
            if($em===''||$cid===''){$flash='ایموجی و شناسه لازم است.';$flashType='error';}
            else{$pdo->prepare("INSERT IGNORE INTO premium_emojis (emoji,custom_emoji_id,label,created_at,updated_at) VALUES (?,?,?,?,?)")->execute([$em,$cid,$lb,time(),time()]);$flash='ایموجی اضافه شد.';$flashType='success';}
        } elseif ($act==='del') { $id=(int)($_POST['id']??0); if($id>0){$pdo->prepare("DELETE FROM premium_emojis WHERE id=?")->execute([$id]);$flash='حذف شد.';$flashType='success';} }
    } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/premium_emojis.php'),ENT_QUOTES);$flashType='error';}
}
$emojis=[]; try{$emojis=$pdo->query("SELECT * FROM premium_emojis ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
$peStatus=''; try{$peStatus=(string)$pdo->query("SELECT premium_emoji_status FROM setting LIMIT 1")->fetchColumn();}catch(Throwable $e){}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ایموجی پرمیوم | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-inline{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}.rx-inline input{padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px}code{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🌟 ایموجی پرمیوم</h1><div class="page-head__sub">مدیریت ایموجی‌های اختصاصی پرمیوم برای استفاده در متون ربات</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<p class="rx-info">📋 وضعیت قابلیت: <b><?= $peStatus==='1'?'☑️ فعال':'🚫 غیرفعال' ?></b> — برای تغییر به «تنظیمات عمومی» بروید.<br>💡 ایموجی‌های پرمیوم فقط با اکانت پرمیوم مالک ربات در تلگرام قابل استفاده‌اند.</p>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">➕ افزودن ایموجی</h2></div>
<?php if($isMainAdmin): ?>
<form method="post" class="rx-inline"><input type="hidden" name="action" value="add">
<input type="text" name="emoji" placeholder="🌟" required style="width:80px;text-align:center;font-size:18px">
<input type="text" name="custom_emoji_id" placeholder="شناسه ایموجی (file_id)" required style="direction:ltr;width:300px">
<input type="text" name="label" placeholder="برچسب (اختیاری)" style="width:160px">
<button class="btn btn-sm btn-success" type="submit">افزودن</button></form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?></div>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📋 ایموجی‌ها (<?= count($emojis) ?>)</h2></div>
<?php if(empty($emojis)): ?><p class="text-muted">هنوز ثبت نشده.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%"><thead><tr><th>ایموجی</th><th>شناسه</th><th>برچسب</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach($emojis as $e): ?>
<tr><td style="font-size:20px;text-align:center"><?= htmlspecialchars((string)$e['emoji'],ENT_QUOTES,'UTF-8') ?></td>
<td class="text-muted" style="direction:ltr"><code><?= htmlspecialchars(mb_strimwidth((string)$e['custom_emoji_id'],0,30,'…'),ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)$e['label'],ENT_QUOTES,'UTF-8') ?></td>
<td><?php if($isMainAdmin): ?><form method="post" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">حذف</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div></section></section></body></html>
