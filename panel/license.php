<?php
/**
 * Red Fox — مدیریت لایسنس پروژه + انقضای ربات‌های نماینده.
 *  - لایسنس پروژه: تولید/بررسی کلید لایسنس برای محافظت از کد
 *  - انقضای ربات نماینده: تعیین تاریخ انقضا، تمدید، غیرفعال‌سازی، شارژ
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

rx_require_schema($pdo,[],['botsaz'=>['bot_expires_at','bot_status','bot_charge_amount']]);

// فایل لایسنس
$licenseFile = dirname(__DIR__) . '/license.json';
$licenseData = ['license_key' => '', 'licensed_to' => '', 'expires_at' => '', 'max_bots' => 0, 'enabled' => false];
if (is_file($licenseFile)) {
    $ld = json_decode((string)file_get_contents($licenseFile), true);
    if (is_array($licenseData)) $licenseData = array_merge($licenseData, $ld);
}

// POST handling
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $act = (string)($_POST['action'] ?? '');
    try {
        if ($act === 'save_license') {
            $licenseData['license_key']   = trim((string)($_POST['license_key'] ?? ''));
            $licenseData['licensed_to']   = trim((string)($_POST['licensed_to'] ?? ''));
            $licenseData['expires_at']    = trim((string)($_POST['expires_at'] ?? ''));
            $licenseData['max_bots']      = (int)($_POST['max_bots'] ?? 0);
            $licenseData['enabled']       = isset($_POST['license_enabled']);
            file_put_contents($licenseFile, json_encode($licenseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $flash = 'لایسنس ذخیره شد.'; $flashType = 'success';
        } elseif ($act === 'gen_license') {
            $licenseData['license_key'] = 'RF-' . strtoupper(bin2hex(random_bytes(12)));
            $licenseData['enabled'] = true;
            file_put_contents($licenseFile, json_encode($licenseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $flash = 'کلید لایسنس جدید تولید شد: ' . $licenseData['license_key']; $flashType = 'success';
        } elseif ($act === 'set_bot_expiry') {
            $bt = trim((string)($_POST['bot_token'] ?? ''));
            $exp = trim((string)($_POST['expires_at'] ?? ''));
            $charge = trim((string)($_POST['charge_amount'] ?? '0'));
            if ($bt !== '' && $exp !== '') {
                $pdo->prepare("UPDATE botsaz SET bot_expires_at=?, bot_charge_amount=?, bot_status='active' WHERE bot_token=?")
                    ->execute([$exp, $charge, $bt]);
                $flash = "انقضای ربات تنظیم شد: $exp"; $flashType = 'success';
            }
        } elseif ($act === 'extend_bot') {
            $bt = trim((string)($_POST['bot_token'] ?? ''));
            $days = max(1, (int)($_POST['days'] ?? 30));
            $cur = $pdo->prepare("SELECT bot_expires_at FROM botsaz WHERE bot_token=? LIMIT 1");
            $cur->execute([$bt]); $curExp = (string)$cur->fetchColumn();
            $baseTs = time();
            if ($curExp !== '') { $t = strtotime($curExp); if ($t && $t > time()) $baseTs = $t; }
            $newExp = date('Y-m-d H:i:s', strtotime("+$days day", $baseTs));
            $pdo->prepare("UPDATE botsaz SET bot_expires_at=?, bot_status='active' WHERE bot_token=?")->execute([$newExp, $bt]);
            $flash = "ربات $days روز تمدید شد. انقضا: $newExp"; $flashType = 'success';
        } elseif ($act === 'disable_bot') {
            $bt = trim((string)($_POST['bot_token'] ?? ''));
            $pdo->prepare("UPDATE botsaz SET bot_status='disabled' WHERE bot_token=?")->execute([$bt]);
            $flash = 'ربات غیرفعال شد.'; $flashType = 'success';
        } elseif ($act === 'enable_bot') {
            $bt = trim((string)($_POST['bot_token'] ?? ''));
            $pdo->prepare("UPDATE botsaz SET bot_status='active' WHERE bot_token=?")->execute([$bt]);
            $flash = 'ربات فعال شد.'; $flashType = 'success';
        }
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/license.php')); $flashType = 'error'; }
}

// لیست ربات‌های نماینده
$bots = [];
$botsazExists = false;
try {
    $chk = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'botsaz'");
    $botsazExists = ((int)$chk->fetchColumn()) > 0;
} catch (Throwable $e) {}
if ($botsazExists) {
    try {
        $bots = $pdo->query("SELECT b.id_user, b.bot_token, b.username, b.time, b.bot_expires_at, b.bot_status, b.bot_charge_amount,
                                    u.username AS owner_username, u.namecustom
                             FROM botsaz b LEFT JOIN user u ON u.id = b.id_user ORDER BY b.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try { $bots = $pdo->query("SELECT id_user, bot_token, username, time, bot_expires_at, bot_status, bot_charge_amount FROM botsaz ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e2) {}
    }
}
$nowTs = time();
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>لایسنس و انقضای ربات‌ها | ربات رد فاکس</title>
<link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-card-title{color:var(--text-main)}
.rx-field{margin-bottom:10px}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin-bottom:3px}
.rx-field input,.rx-field select{width:100%;max-width:400px;padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box;font-family:inherit}
.rx-inline{display:inline-flex;gap:6px;align-items:end;flex-wrap:wrap}
.badge{padding:3px 9px;border-radius:14px;font-size:11px;font-weight:700}
.badge-ok{background:var(--color-success-soft);color:var(--color-success)}
.badge-exp{background:var(--color-danger-soft);color:var(--color-danger)}
.badge-off{background:var(--surface-2);color:var(--text-muted)}
.badge-warn{background:var(--color-warning-soft);color:var(--color-warning)}
.rx-modal{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:999;padding:16px}
.rx-modal.open{display:flex}
.rx-modal-box{background:var(--surface-1);border-radius:14px;padding:20px;max-width:460px;width:100%;border:1px solid var(--border-mid)}
.rx-modal-box h3{margin:0 0 12px;color:var(--text-main)}
.rx-info{color:var(--text-muted);font-size:12px;line-height:2}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🔐 لایسنس و انقضای ربات‌ها</h1>
<div class="page-head__sub">مدیریت لایسنس پروژه + تاریخ انقضا و شارژ ربات‌های نماینده</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<!-- لایسنس پروژه -->
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🔐 لایسنس پروژه</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" style="display:grid;gap:10px;max-width:600px">
<input type="hidden" name="action" value="save_license">
<div class="rx-field"><label>کلید لایسنس</label>
<div class="rx-inline"><input type="text" name="license_key" value="<?= htmlspecialchars($licenseData['license_key'],ENT_QUOTES) ?>" style="direction:ltr;flex:1" placeholder="RF-XXXXXXXX..." readonly>
<button class="btn btn-sm btn-primary" type="submit" name="action" value="gen_license" formnovalidate>🔑 تولید کلید جدید</button>
</div></div>
<div class="rx-field"><label>نام/دامنه‌ی دارای لایسنس</label><input type="text" name="licensed_to" value="<?= htmlspecialchars($licenseData['licensed_to'],ENT_QUOTES,'UTF-8') ?>" placeholder="مثلاً vpbotn.ir"></div>
<div class="rx-field"><label>تاریخ انقضای لایسنس (خالی = نامحدود)</label><input type="date" name="expires_at" value="<?= htmlspecialchars($licenseData['expires_at'],ENT_QUOTES) ?>"></div>
<div class="rx-field"><label>حداکثر تعداد ربات نماینده مجاز (۰ = نامحدود)</label><input type="number" name="max_bots" value="<?= (int)$licenseData['max_bots'] ?>" min="0"></div>
<div class="rx-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="license_enabled" <?= !empty($licenseData['enabled']) ? 'checked' : '' ?>> فعال‌سازی بررسی لایسنس</label></div>
<button class="btn btn-sm btn-primary" type="submit">💾 ذخیره لایسنس</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 لایسنس برای محافظت از پروژه و فروش/انتقال به اپراتورهای دیگر است. اگر فعال باشد، ربات بدون لایسنس معتبر کار نخواهد کرد.</p>
</div>

<!-- انقضای ربات‌های نماینده -->
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⏰ ربات‌های نماینده (<?= count($bots) ?>)</h2></div>
<?php if (empty($bots)): ?><p class="text-muted">ربات نماینده‌ای ساخته نشده.</p><?php else: ?>
<div style="overflow-x:auto"><table class="app-table" style="width:100%">
<thead><tr><th>نماینده</th><th>ربات</th><th>وضعیت</th><th>تاریخ انقضا</th><th>شارژ</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach ($bots as $b):
    $bt = htmlspecialchars((string)$b['bot_token'], ENT_QUOTES);
    $status = (string)($b['bot_status'] ?? 'active');
    $exp = (string)($b['bot_expires_at'] ?? '');
    $expTs = $exp !== '' ? strtotime($exp) : 0;
    $isExpired = $expTs && $expTs < $nowTs;
    if ($status === 'disabled') { $badge = '<span class="badge badge-off">غیرفعال</span>'; }
    elseif ($isExpired) { $badge = '<span class="badge badge-exp">منقضی</span>'; }
    elseif ($expTs && ($expTs - $nowTs) < 3 * 86400) { $badge = '<span class="badge badge-warn">رو به انقضا</span>'; }
    else { $badge = '<span class="badge badge-ok">' . ($expTs ? 'فعال' : 'بدون انقضا') . '</span>'; }
    $daysLeft = $expTs ? max(0, floor(($expTs - $nowTs) / 86400)) : -1;
?>
<tr>
<td><code><?= htmlspecialchars((string)$b['id_user']) ?></code><br><small class="text-muted"><?= htmlspecialchars((string)($b['owner_username'] ?? $b['namecustom'] ?? ''),ENT_QUOTES,'UTF-8') ?></small></td>
<td><small>🤖 @<?= htmlspecialchars((string)$b['username'],ENT_QUOTES,'UTF-8') ?></small></td>
<td><?= $badge ?></td>
<td><?= $exp !== '' ? htmlspecialchars($exp) . '<br><small class="text-muted">' . ($isExpired ? 'منقضی' : "$daysLeft روز باقی") . '</small>' : '—' ?></td>
<td><?= number_format((int)($b['bot_charge_amount'] ?? 0)) ?> ت</td>
<td>
<button class="btn btn-sm btn-primary" onclick="rxOpenBotModal('<?= $bt ?>','<?= htmlspecialchars((string)$b['username'],ENT_QUOTES) ?>','<?= htmlspecialchars($exp,ENT_QUOTES) ?>')">⏰ تنظیم/تمدید</button>
<?php if ($status === 'disabled'): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="enable_bot"><input type="hidden" name="bot_token" value="<?= $bt ?>"><button class="btn btn-sm btn-success" type="submit">✅ فعال</button></form>
<?php else: ?>
<form method="post" style="display:inline" onsubmit="return confirm('غیرفعال شود؟')"><input type="hidden" name="action" value="disable_bot"><input type="hidden" name="bot_token" value="<?= $bt ?>"><button class="btn btn-sm" type="submit">🚫 خاموش</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>

</div></section></section>

<!-- Modal تنظیم انقضا/تمدید -->
<div class="rx-modal" id="rxBotModal"><div class="rx-modal-box">
<h3>⏰ تنظیم/تمدید ربات</h3>
<form method="post">
<input type="hidden" name="bot_token" id="rxBotToken">
<p style="color:var(--text-muted);font-size:13px" id="rxBotName"></p>
<div class="rx-field"><label>تنظیم تاریخ دقیق انقضا</label><input type="datetime-local" name="expires_at" id="rxBotExp" style="direction:ltr"></div>
<div class="rx-field"><label>شارژ (قیمت تمدید — تومان)</label><input type="number" name="charge_amount" value="0" min="0"></div>
<button class="btn btn-sm btn-primary" type="submit" name="action" value="set_bot_expiry">📅 ذخیره</button>
<hr style="border-color:var(--border-soft);margin:14px 0">
<div class="rx-field"><label>یا تمدید سریع (روز)</label><input type="number" name="days" value="30" min="1"></div>
<button class="btn btn-sm btn-success" type="submit" name="action" value="extend_bot">⚡ تمدید سریع</button>
<button class="btn btn-sm" type="button" onclick="document.getElementById('rxBotModal').classList.remove('open')" style="margin-right:8px">انصراف</button>
</form>
</div></div>

<script>
function rxOpenBotModal(token, username, curExp) {
    document.getElementById('rxBotToken').value = token;
    document.getElementById('rxBotName').textContent = 'ربات: @' + username;
    var expEl = document.getElementById('rxBotExp');
    if (curExp && curExp.length >= 16) expEl.value = curExp.substring(0,16); else expEl.value = '';
    document.getElementById('rxBotModal').classList.add('open');
}
document.getElementById('rxBotModal').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
</script>
</body></html>
