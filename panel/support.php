<?php
/**
 * Red Fox — مدیریت پشتیبانی در پنل وب.
 *  - دپارتمان‌ها: جدول departman (idsupport, name_departman)
 *  - آیدی پشتیبانی و کانال گزارش: setting (id_support, Channel_Report)
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$adminRow = null;
if (!empty($_SESSION['user'])) {
    $q = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
    $q->bindValue(':u', $_SESSION['user'], PDO::PARAM_STR); $q->execute();
    $adminRow = $q->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');
$flash = ''; $flashType = 'info';

// خواندن یک فیلد از setting
function redfox_setting_get($field) {
    global $pdo;
    try { $s = $pdo->prepare("SELECT `$field` AS v FROM setting LIMIT 1"); $s->execute(); $r = $s->fetchColumn(); return $r === false ? '' : (string)$r; }
    catch (Throwable $e) { return ''; }
}
// whitelist فیلدهای مجاز برای نوشتن در setting
function redfox_setting_set($field, $value) {
    global $pdo;
    $allowed = ['id_support', 'Channel_Report'];
    if (!in_array($field, $allowed, true)) return false;
    try { $pdo->prepare("UPDATE setting SET `$field` = ?")->execute([$value]); return true; }
    catch (Throwable $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add_dep') {
            $id = trim((string)($_POST['idsupport'] ?? ''));
            $name = trim((string)($_POST['name_departman'] ?? ''));
            if ($id === '' || !ctype_digit($id) || $name === '' || mb_strlen($name) > 590) { $flash = 'اطلاعات نامعتبر (آیدی باید عدد باشد).'; $flashType = 'error'; }
            else { $pdo->prepare("INSERT IGNORE INTO departman (idsupport, name_departman) VALUES (?, ?)")->execute([$id, $name]); $flash = 'دپارتمان اضافه شد.'; $flashType = 'success'; }
        } elseif ($action === 'del_dep') {
            $name = (string)($_POST['name_departman'] ?? '');
            $pdo->prepare("DELETE FROM departman WHERE name_departman = ?")->execute([$name]);
            $flash = 'دپارتمان حذف شد.'; $flashType = 'success';
        } elseif ($action === 'save_settings') {
            $idSup = trim((string)($_POST['id_support'] ?? ''));
            $chan = trim((string)($_POST['Channel_Report'] ?? ''));
            redfox_setting_set('id_support', $idSup);
            redfox_setting_set('Channel_Report', $chan);
            $flash = 'تنظیمات پشتیبانی ذخیره شد.'; $flashType = 'success';
        }
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/support.php'), ENT_QUOTES); $flashType = 'error'; error_log('[support.php] '.redfox_exception_fingerprint($e)); }
}

$deps = [];
try { $deps = $pdo->query("SELECT * FROM departman ORDER BY name_departman ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$idSupport = redfox_setting_get('id_support');
$channelReport = redfox_setting_get('Channel_Report');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پشتیبانی و دپارتمان‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-inline{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}
.rx-inline input{padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px}
.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">☎️ پشتیبانی و دپارتمان‌ها</h1>
<div class="page-head__sub">دپارتمان‌های پشتیبانی، آیدی پشتیبانی و کانال گزارش</div></div>
<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" style="display:flex;flex-direction:column;gap:10px;max-width:560px">
<input type="hidden" name="action" value="save_settings">
<label class="text-muted" style="font-size:13px">آیدی پشتیبانی (بدون @)</label>
<input class="rx-inline" name="id_support" value="<?= htmlspecialchars($idSupport,ENT_QUOTES,'UTF-8') ?>" placeholder="مثلاً: 123456789" style="padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main)">
<label class="text-muted" style="font-size:13px">کانال گزارش (Channel_Report) — آیدی/شناسه کانال</label>
<input name="Channel_Report" value="<?= htmlspecialchars($channelReport,ENT_QUOTES,'UTF-8') ?>" placeholder="مثلاً: -1001234567890" style="padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main)">
<button class="btn btn-sm btn-primary" type="submit" style="align-self:flex-start">ذخیره تنظیمات</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-help">💡 پیام‌های گزارش ربات (فروش، خطا، ...) به «کانال گزارش» ارسال می‌شود.</p></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">➕ دپارتمان جدید</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" class="rx-inline">
<input type="hidden" name="action" value="add_dep">
<input type="text" name="idsupport" placeholder="آیدی عددی ادمین پشتیبانی" required style="direction:ltr;width:220px">
<input type="text" name="name_departman" placeholder="نام دپارتمان" required style="width:240px">
<button class="btn btn-sm btn-success" type="submit">افزودن</button>
</form><?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📋 دپارتمان‌ها (<?= count($deps) ?>)</h2></div>
<?php if (empty($deps)): ?><p class="text-muted">دپارتمانی ثبت نشده است.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%">
<thead><tr><th>نام دپارتمان</th><th>آیدی ادمین</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach ($deps as $d): ?>
<tr><td><?= htmlspecialchars((string)$d['name_departman'],ENT_QUOTES,'UTF-8') ?></td>
<td><code><?= htmlspecialchars((string)$d['idsupport'],ENT_QUOTES,'UTF-8') ?></code></td>
<td><?php if ($isMainAdmin): ?><form method="post" class="rx-inline" onsubmit="return confirm('حذف شود؟')">
<input type="hidden" name="action" value="del_dep"><input type="hidden" name="name_departman" value="<?= htmlspecialchars((string)$d['name_departman'],ENT_QUOTES,'UTF-8') ?>">
<button class="btn btn-sm btn-danger" type="submit">حذف</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div></section></section></body></html>
