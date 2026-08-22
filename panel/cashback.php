<?php
/**
 * Red Fox — کش‌بک (بازگشت وجه) جداگانه برای هر درگاه، در پنل وب.
 * مقادیر در جدول PaySetting (NamePay, ValuePay) ذخیره می‌شوند (درصد).
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

// نگاشت کلید PaySetting → برچسب درگاه
$gateways = [
    'chashbackcart'         => '💳 کارت به کارت',
    'chashbackplisio'       => '🪙 Plisio',
    'cashbacknowpayment'    => '🪙 NowPayments',
    'chashbackaqaypardokht' => '🔵 آقای پرداخت',
    'chashbackzarinpal'     => '🟡 زرین‌پال',
    'chashbackiranpay1'     => '🟢 ارزی ریالی ۱',
    'chashbackiranpay2'     => '🟢 ارزی ریالی ۲',
    'chashbackiranpay3'     => '🟢 ارزی ریالی ۳',
];

function redfox_pay_get($key) {
    global $pdo;
    try { $s = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1"); $s->execute([$key]); $v = $s->fetchColumn(); return $v === false ? '0' : (string)$v; }
    catch (Throwable $e) { return '0'; }
}
function redfox_pay_set($key, $value) {
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?"); $s->execute([$key]);
        if ((int)$s->fetchColumn() > 0) {
            $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?")->execute([$value, $key]);
        } else {
            $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$key, $value]);
        }
        return true;
    } catch (Throwable $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin && (string)($_POST['action'] ?? '') === 'save') {
    try {
        foreach (array_keys($gateways) as $key) {
            $val = (string)($_POST[$key] ?? '0');
            if (!ctype_digit(ltrim($val, '-')) && $val !== '0') $val = '0';
            redfox_pay_set($key, $val);
        }
        $flash = 'کش‌بک درگاه‌ها ذخیره شد.'; $flashType = 'success';
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/cashback.php'), ENT_QUOTES); $flashType = 'error'; error_log('[cashback.php] '.redfox_exception_fingerprint($e)); }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>کش‌بک درگاه‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
.rx-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-soft);max-width:520px}
.rx-row label{color:var(--text-main);font-size:14px}
.rx-row input{width:110px;padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;text-align:center}
.rx-row small{color:var(--text-muted)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">💰 کش‌بک درگاه‌ها</h1>
<div class="page-head__sub">درصد بازگشت وجه جداگانه برای هر درگاه پرداخت</div></div>
<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ درصد کش‌بک</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post"><input type="hidden" name="action" value="save">
<?php foreach ($gateways as $key => $label): $cur = redfox_pay_get($key); ?>
<div class="rx-row">
<label><?= $label ?></label>
<span><input type="number" name="<?= htmlspecialchars($key,ENT_QUOTES) ?>" value="<?= htmlspecialchars($cur,ENT_QUOTES) ?>" min="0" max="100"> <small>%</small></span>
</div>
<?php endforeach; ?>
<button class="btn btn-sm btn-primary" type="submit" style="margin-top:12px">ذخیره</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-help">💡 درصدی از مبلغ پرداختی کاربر، بعد از تأیید، به‌عنوان هدیه به کیف پولش برمی‌گردد. صفر = بدون کش‌بک.</p>
</div>
</div></section></section></body></html>
