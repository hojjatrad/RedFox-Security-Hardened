<?php
/** Red Fox — ویرایش متون پیام خوش‌آمدگویی و راهنمای نصب ادمین */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

function rx_text_get($field){ global $pdo; try{ $v=(string)$pdo->query("SELECT $field FROM setting LIMIT 1")->fetchColumn(); return $v; }catch(Throwable $e){ return ''; } }
function rx_text_set($field,$val){ global $pdo; try{ $pdo->prepare("UPDATE setting SET $field=?")->execute([$val]); return true; }catch(Throwable $e){ return false; } }

$welcomeText = rx_text_get('admin_welcome_text');
$setupText = rx_text_get('admin_setup_text');

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    if ($act==='save_welcome') {
        $txt=(string)($_POST['admin_welcome_text']??'');
        rx_text_set('admin_welcome_text',$txt); $welcomeText=$txt; $flash='پیام خوش‌آمدگویی ذخیره شد.'; $flashType='success';
    } elseif ($act==='save_setup') {
        $txt=(string)($_POST['admin_setup_text']??'');
        rx_text_set('admin_setup_text',$txt); $setupText=$txt; $flash='راهنمای نصب ذخیره شد.'; $flashType='success';
    } elseif ($act==='reset_welcome') {
        rx_text_set('admin_welcome_text',''); $welcomeText=''; $flash='پیام خوش‌آمدگویی به پیش‌فرض بازگشت.'; $flashType='success';
    } elseif ($act==='reset_setup') {
        rx_text_set('admin_setup_text',''); $setupText=''; $flash='راهنمای نصب به پیش‌فرض بازگشت.'; $flashType='success';
    }
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>متون پیام‌های ربات | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-field{margin-bottom:12px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}.rx-field textarea{width:100%;max-width:800px;padding:10px 12px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;font-family:inherit;box-sizing:border-box;min-height:180px;line-height:1.8}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:10px}.rx-btnrow{display:flex;gap:8px;margin-top:8px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">✏️ متون پیام‌های ربات</h1><div class="page-head__sub">ویرایش پیام خوش‌آمدگویی و راهنمای نصب که ادمین هنگام اولین ورود می‌بیند</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">💎 پیام خوش‌آمدگویی ادمین</h2></div>
<p class="rx-info">این پیام وقتی نمایش داده می‌شود که ادمین اولین بار دکمه‌ی مدیریت را بزند. خالی = پیش‌فرض.</p>
<?php if($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save_welcome">
<div class="rx-field"><textarea name="admin_welcome_text" placeholder="خالی بگذارید تا متن پیش‌فرض استفاده شود"><?= htmlspecialchars($welcomeText,ENT_QUOTES,'UTF-8') ?></textarea></div>
<div class="rx-btnrow">
<button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
<button class="btn btn-sm" type="submit" name="action" value="reset_welcome" formnovalidate>↩️ بازگشت به پیش‌فرض</button>
</div>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 از تگ‌های HTML استفاده کنید: <code>&lt;b&gt;</code> <code>&lt;code&gt;</code> <code>&lt;a href="..."&gt;</code> <code>&lt;blockquote&gt;</code></p>
</div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ راهنمای نصب (BotFather + کرون)</h2></div>
<p class="rx-info">این پیام راهنمای فعال‌سازی مینی‌اپ و تنظیم کرون را نشان می‌دهد. خالی = پیش‌فرض.<br>⚠️ در متن پیش‌فرض، آدرس دامنه به‌صورت خودکار درج می‌شود. اگر متن سفارشی می‌نویسید، خودتان آدرس را درج کنید.</p>
<?php if($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save_setup">
<div class="rx-field"><textarea name="admin_setup_text" placeholder="خالی بگذارید تا متن پیش‌فرض استفاده شود"><?= htmlspecialchars($setupText,ENT_QUOTES,'UTF-8') ?></textarea></div>
<div class="rx-btnrow">
<button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
<button class="btn btn-sm" type="submit" name="action" value="reset_setup" formnovalidate>↩️ بازگشت به پیش‌فرض</button>
</div>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
</div>

</div></section></section></body></html>
