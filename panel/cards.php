<?php
/**
 * Red Fox — مدیریت شماره کارت‌های کارت‌به‌کارت (جدول card_number).
 * card_number: cardnumber (PK), namecard (نام صاحب/بانک). چندین کارت قابل ثبت است.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $num = preg_replace('/\s+/', '', (string)($_POST['cardnumber'] ?? ''));
            $name = trim((string)($_POST['namecard'] ?? ''));
            if ($num === '' || mb_strlen($name) > 990) { $flash = 'شماره کارت نامعتبر است.'; $flashType = 'error'; }
            else {
                $pdo->prepare("INSERT IGNORE INTO card_number (cardnumber, namecard) VALUES (?, ?)")->execute([$num, $name]);
                $flash = ($pdo->prepare("SELECT COUNT(*) FROM card_number WHERE cardnumber=?")->execute([$num])) ? 'شماره کارت اضافه شد.' : 'خطا';
                $flashType = 'success';
            }
        } elseif ($action === 'delete') {
            $num = (string)($_POST['cardnumber'] ?? '');
            $pdo->prepare("DELETE FROM card_number WHERE cardnumber = ?")->execute([$num]);
            $flash = 'شماره کارت حذف شد.'; $flashType = 'success';
        }
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/cards.php'), ENT_QUOTES); $flashType = 'error'; error_log('[cards.php] '.redfox_exception_fingerprint($e)); }
}

$cards = [];
try { $cards = $pdo->query("SELECT * FROM card_number ORDER BY cardnumber ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>شماره کارت‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-inline{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}
.rx-inline input{padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">💳 شماره کارت‌های کارت‌به‌کارت</h1>
<div class="page-head__sub">مدیریت چندین شماره کارت برای درگاه کارت‌به‌کارت</div></div>
<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">➕ شماره کارت جدید</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" class="rx-inline">
<input type="hidden" name="action" value="add">
<input type="text" name="cardnumber" placeholder="شماره کارت (۶۰۳۷...)" required style="direction:ltr;width:240px">
<input type="text" name="namecard" placeholder="نام صاحب کارت / بانک" required style="width:260px">
<button class="btn btn-sm btn-success" type="submit">افزودن</button>
</form><?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?></div>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📋 کارت‌ها (<?= count($cards) ?>)</h2></div>
<?php if (empty($cards)): ?><p class="text-muted">کارت ثبت نشده است.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%">
<thead><tr><th>شماره کارت</th><th>نام</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach ($cards as $c): ?>
<tr><td style="direction:ltr"><code><?= htmlspecialchars((string)$c['cardnumber'],ENT_QUOTES,'UTF-8') ?></code></td>
<td><?= htmlspecialchars((string)$c['namecard'],ENT_QUOTES,'UTF-8') ?></td>
<td><?php if ($isMainAdmin): ?><form method="post" class="rx-inline" onsubmit="return confirm('حذف شود؟')">
<input type="hidden" name="action" value="delete"><input type="hidden" name="cardnumber" value="<?= htmlspecialchars((string)$c['cardnumber'],ENT_QUOTES,'UTF-8') ?>">
<button class="btn btn-sm btn-danger" type="submit">حذف</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div></section></section></body></html>
