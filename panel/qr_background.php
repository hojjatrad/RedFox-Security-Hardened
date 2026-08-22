<?php
/** Red Fox — آپلود پس‌زمینه‌ی QR Code (ذخیره در custom.jpg/images.jpg/images.jpeg) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';
$projectRoot = dirname(__DIR__, 1);

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin && (string)($_POST['action']??'')==='upload') {
    if (!isset($_FILES['qrfile']) || (int)($_FILES['qrfile']['error']??1)!==UPLOAD_ERR_OK) {
        $flash='فایلی آپلود نشد یا خطا.'; $flashType='error';
    } elseif ((int)($_FILES['qrfile']['size']??0) > 3*1024*1024) {
        $flash='حجم بیش از ۳ مگابایت.'; $flashType='error';
    } else {
        $content = file_get_contents((string)$_FILES['qrfile']['tmp_name']);
        $written = 0;
        $written += (int)@file_put_contents($projectRoot.'/custom.jpg', $content);
        $written += (int)@file_put_contents($projectRoot.'/images.jpg', $content);
        $written += (int)@file_put_contents($projectRoot.'/images.jpeg', $content);
        if ($written > 0) { $flash='✅ پس‌زمینه‌ی QR با موفقیت در همه‌ی فایل‌ها ذخیره شد.'; $flashType='success'; }
        else { $flash='ذخیره‌سازی ناموفق بود — دسترسی نوشتن پوشه‌ی روت را بررسی کنید.'; $flashType='error'; }
    }
}
$currentExists = is_file($projectRoot.'/images.jpeg') || is_file($projectRoot.'/images.jpg') || is_file($projectRoot.'/custom.jpg');
$currentSize = max(
    is_file($projectRoot.'/images.jpeg') ? filesize($projectRoot.'/images.jpeg') : 0,
    is_file($projectRoot.'/images.jpg') ? filesize($projectRoot.'/images.jpg') : 0,
    is_file($projectRoot.'/custom.jpg') ? filesize($projectRoot.'/custom.jpg') : 0
);
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>پس‌زمینه QR | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}code{color:var(--text-main)}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🖼 پس‌زمینه‌ی QR Code</h1><div class="page-head__sub">آپلود تصویر پس‌زمینه‌ی بارکد (به‌جای همه‌ی فایل‌ها اعمال می‌شود)</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📤 آپلود تصویر جدید</h2></div>
<?php if($isMainAdmin): ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload">
<input type="file" name="qrfile" accept="image/jpeg,image/jpg,image/png" required style="color:var(--text-main)">
<button class="btn btn-sm btn-success" type="submit" style="margin-top:8px">آپلود و اعمال</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">
📌 وضعیت فعلی: <b><?= $currentExists ? 'تصویر موجود ('.number_format($currentSize/1024,1).' کیلوبایت)' : 'تصویر پیش‌فرض' ?></b><br>
💡 تصویر باید فرمت JPG یا PNG و حداکثر ۳ مگابایت باشد. تصویر آپلودشده همزمان در <code>custom.jpg</code>، <code>images.jpg</code> و <code>images.jpeg</code> ذخیره می‌شود.<br>
⚠️ مرکز تصویر توسط QR پوشانده می‌شود؛ برند/متن را در گوشه‌ها قرار دهید.
</p></div>
</div></section></section></body></html>
