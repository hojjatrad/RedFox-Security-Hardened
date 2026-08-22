<?php
/**
 * Red Fox — تنظیمات قرعه‌کشی در پنل وب.
 * Lottery_prize (JSON در setting) با کلیدها: one / tow / theree (جایزه نفر ۱/۲/۳).
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

// خواندن Lottery_prize فعلی
$prizes = ['one' => 0, 'tow' => 0, 'theree' => 0];
try {
    $s = $pdo->prepare("SELECT Lottery_prize AS v FROM setting LIMIT 1"); $s->execute();
    $raw = $s->fetchColumn();
    $dec = json_decode((string)$raw, true);
    if (is_array($dec)) { foreach (['one','tow','theree'] as $k) $prizes[$k] = isset($dec[$k]) ? (int)$dec[$k] : 0; }
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin && (string)($_POST['action'] ?? '') === 'save') {
    try {
        $new = [
            'one' => (int)($_POST['one'] ?? 0),
            'tow' => (int)($_POST['tow'] ?? 0),
            'theree' => (int)($_POST['theree'] ?? 0),
        ];
        $pdo->prepare("UPDATE setting SET Lottery_prize = ?")->execute([json_encode($new, JSON_UNESCAPED_UNICODE)]);
        $prizes = $new;
        $flash = 'جایزه‌های قرعه‌کشی ذخیره شد.'; $flashType = 'success';
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/lottery.php'), ENT_QUOTES); $flashType = 'error'; error_log('[lottery.php] '.redfox_exception_fingerprint($e)); }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>قرعه‌کشی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
.rx-field{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;max-width:360px}
.rx-field label{color:var(--text-muted);font-size:13px}
.rx-field input{padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🎁 قرعه‌کشی</h1>
<div class="page-head__sub">تنظیم مبلغ جایزه نفرات اول تا سوم (به تومان)</div></div>
<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🏆 جایزه‌ها</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save">
<div class="rx-field"><label>جایزه نفر اول (one)</label><input type="number" name="one" value="<?= (int)$prizes['one'] ?>" min="0"></div>
<div class="rx-field"><label>جایزه نفر دوم (tow)</label><input type="number" name="tow" value="<?= (int)$prizes['tow'] ?>" min="0"></div>
<div class="rx-field"><label>جایزه نفر سوم (theree)</label><input type="number" name="theree" value="<?= (int)$prizes['theree'] ?>" min="0"></div>
<button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-help">💡 این مبالغ هنگام برنده‌شدن کاربران به کیف پولشان واریز می‌شود. صفر = بدون جایزه.<br>⚠️ روشن/خاموش‌کردن خود قرعه‌کشی و چرخ‌وشرکت از داخل ربات انجام می‌شود.</p>
</div>
</div></section></section></body></html>
