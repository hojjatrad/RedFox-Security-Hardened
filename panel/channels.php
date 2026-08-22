<?php
/** Red Fox — مدیریت کانال‌های جوین اجباری (channels table) */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

$hasChannelId=false;$schemaWarning='';try{rx_require_schema($pdo,['channels'],['channels'=>['link','remark','linkjoin']]);$q=$pdo->query("SHOW COLUMNS FROM channels LIKE 'id'");$hasChannelId=$q&&$q->rowCount()>0;if(!$hasChannelId){$schemaWarning='جدول کانال قدیمی است؛ ثبت و نمایش در حالت سازگار انجام می‌شود. برای ID و حذف دقیق، Migration 011 را از مرکز مهاجرت اجرا کنید.';}}catch(Throwable$e){$schemaWarning='ساختار جدول channels ناقص است: '.redfox_public_exception($e, 'panel/channels.php');}

/**
 * استخراج @username کانال از لینک
 * مثال: t.me/mychannel → @mychannel | @mychannel → @mychannel
 */
function rx_normalize_channel($input): array {
    $input=trim((string)$input);if($input==='')throw new InvalidArgumentException('آیدی یا لینک کانال خالی است.');
    if(preg_match('/^(-100\d{5,})\s*\|\s*(https:\/\/t(?:elegram)?\.me\/[+A-Za-z0-9_-]+)$/i',$input,$m))return['link'=>$m[1],'join'=>$m[2]];
    if(preg_match('/^@([A-Za-z0-9_]{4,})$/',$input,$m))return['link'=>'@'.$m[1],'join'=>'https://t.me/'.$m[1]];
    if(preg_match('#^(?:https?://)?t(?:elegram)?\.me/([A-Za-z0-9_]{4,})/?$#i',$input,$m))return['link'=>'@'.$m[1],'join'=>'https://t.me/'.$m[1]];
    if(preg_match('/^[A-Za-z0-9_]{4,}$/',$input))return['link'=>'@'.$input,'join'=>'https://t.me/'.$input];
    if(preg_match('#t(?:elegram)?\.me/\+#i',$input))throw new InvalidArgumentException('برای کانال خصوصی فرمت «-100CHAT_ID | https://t.me/+INVITE» را وارد کنید.');
    throw new InvalidArgumentException('فرمت کانال معتبر نیست.');
}

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    try {
        if ($act==='add') {
            $rm = trim((string)($_POST['remark']??''));
            $lj = trim((string)($_POST['linkjoin']??''));
            if ($rm === '' || $lj === '') {
                $flash = 'نام کانال و لینک لازم است.'; $flashType = 'error';
            } else {
                $normalized = rx_normalize_channel($lj);
                $dup=$pdo->prepare('SELECT link FROM channels WHERE link=? LIMIT 1');$dup->execute([$normalized['link']]);
                if($dup->fetchColumn()) throw new RuntimeException('این کانال قبلاً ثبت شده است.');
                $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
                $stmt->execute([':link'=>$normalized['link'],':remark'=>$rm,':linkjoin'=>$normalized['join']]);
                $id=$hasChannelId?(int)$pdo->lastInsertId():0;
                if($hasChannelId&&$id<1){$check=$pdo->prepare('SELECT id FROM channels WHERE link=?');$check->execute([$normalized['link']]);$id=(int)$check->fetchColumn();}
                $verify=$pdo->prepare('SELECT COUNT(*) FROM channels WHERE link=?');$verify->execute([$normalized['link']]);if((int)$verify->fetchColumn()<1)throw new RuntimeException('رکورد کانال پس از INSERT قابل بازیابی نیست.');
                $flash = '✅ کانال «' . htmlspecialchars($rm, ENT_QUOTES, 'UTF-8') . '» '.($id>0?'با شناسه '.$id.' ':'').'ذخیره شد.'; $flashType = 'success';
            }
        } elseif ($act==='del') {
            $id = (int)($_POST['id'] ?? 0);$link=trim((string)($_POST['link']??''));
            if ($hasChannelId && $id > 0) {$pdo->prepare("DELETE FROM channels WHERE id = ?")->execute([$id]);}
            elseif($link!==''){$pdo->prepare("DELETE FROM channels WHERE link = ?")->execute([$link]);}
            else throw new RuntimeException('شناسه حذف نامعتبر است.');
            $flash = 'کانال حذف شد.'; $flashType = 'success';
        }
    } catch (Throwable $e) {
        $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/channels.php'), ENT_QUOTES);
        $flashType = 'error';
    }
}
$chans = [];
try { $order=$hasChannelId?'id ASC':'remark ASC';$chans = $pdo->query("SELECT * FROM channels ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $flash='خطا در خواندن لیست کانال‌ها: '.redfox_public_exception($e, 'panel/channels.php');$flashType='error'; }
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>کانال‌های جوین | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}
.rx-form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
.rx-field-mini{display:flex;flex-direction:column;gap:3px}
.rx-field-mini label{font-size:11px;color:var(--text-muted)}
.rx-field-mini input{padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box}
code{color:var(--text-main)}
.rx-info{background:var(--accent-soft);border-radius:10px;padding:14px;font-size:12px;color:var(--text-main);line-height:2;margin-top:12px}
.rx-warn{background:var(--color-warning-soft);border-radius:10px;padding:14px;font-size:12px;color:var(--text-main);line-height:2;margin-top:12px}
table{width:100%;border-collapse:collapse}th,td{padding:9px;text-align:right;font-size:13px;border-bottom:1px solid var(--border-soft);color:var(--text-main)}
th{color:var(--text-muted);font-size:12px}
</style></head><body><section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📢 کانال‌های جوین اجباری</h1>
<div class="page-head__sub">کاربران برای استفاده از ربات باید عضو این کانال‌ها باشند</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType, ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>
<?php if($schemaWarning!==''): ?><div class="rx-warn">⚠️ <?=htmlspecialchars($schemaWarning,ENT_QUOTES,'UTF-8')?> <a class="btn btn-sm btn-primary" href="migrations.php">مرکز مهاجرت دیتابیس</a></div><?php endif; ?>

<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">➕ افزودن کانال</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="add">
<div class="rx-form-row">
<div class="rx-field-mini">
<label>📝 نام نمایشی کانال</label>
<input type="text" name="remark" placeholder="مثلاً: کانال اصلی" required style="width:220px">
</div>
<div class="rx-field-mini">
<label>🔗 آیدی یا لینک کانال</label>
<input type="text" name="linkjoin" placeholder="@channel یا -100ID | invite-link" required style="direction:ltr;width:340px">
</div>
<button class="btn btn-sm btn-success" type="submit" style="height:38px">✅ افزودن کانال</button>
</div>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>

<div class="rx-warn">
⚠️ <b>نکته مهم:</b> ربات باید ادمین کانال باشد تا بتواند عضویت کاربران را بررسی کند.<br>
💡 کانال عمومی: <code>@channelname</code> یا <code>t.me/channelname</code> — کانال خصوصی: <code>-1001234567890 | https://t.me/+INVITE</code>
</div>
</div>

<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">📋 کانال‌های ثبت‌شده (<?= count($chans) ?>)</h2></div>
<?php if (empty($chans)): ?>
<p class="text-muted" style="text-align:center;padding:20px">📭 هنوز کانالی ثبت نشده است.</p>
<?php else: ?>
<div class="table-wrap">
<table><thead><tr><th>#</th><th>نام کانال</th><th>آیدی کانال</th><th>لینک عضویت</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach ($chans as $c): ?>
<tr>
<td><?= (int)($c['id'] ?? 0) ?></td>
<td><b><?= htmlspecialchars((string)$c['remark'], ENT_QUOTES, 'UTF-8') ?></b></td>
<td style="direction:ltr"><code><?= htmlspecialchars((string)($c['link'] ?? '—'), ENT_QUOTES) ?></code></td>
<td style="direction:ltr"><a href="<?= htmlspecialchars((string)$c['linkjoin'], ENT_QUOTES) ?>" target="_blank" style="color:var(--accent)"><?= htmlspecialchars((string)$c['linkjoin'], ENT_QUOTES, 'UTF-8') ?></a></td>
<td>
<?php if ($isMainAdmin): ?>
<form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
<input type="hidden" name="action" value="del">
<input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>"><input type="hidden" name="link" value="<?=htmlspecialchars((string)($c['link']??''),ENT_QUOTES)?>">
<button class="btn btn-sm btn-danger" type="submit">🗑 حذف</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>

<div class="rx-info">
💡 <b>نحوه‌ی کار:</b> قبل از هر عملی در ربات (خرید، تست، پشتیبانی)، ربات چک می‌کند که کاربر عضو همه‌ی این کانال‌ها باشد.<br>
👥 کاربرانی که عضو نیستند، پیام «عضویت در کانال» دریافت می‌کنند.<br>
✅ ادمین‌ها از این بررسی معاف هستند.
</div>

</div></section></section></body></html>
