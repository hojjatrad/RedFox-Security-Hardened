<?php
/**
 * Red Fox — مدیریت اشتراک هوش مصنوعی برای ربات‌های نماینده.
 * ادمین می‌تواند:
 *  - قیمت و مدت پیش‌فرض تعیین کند
 *  - برای هر نماینده قابلیت AI را فعال/غیرفعال کند
 *  - تاریخ انقضا را تعیین، ویرایش یا تمدید کند
 *  - درخواست‌های نماینده‌ها را ببیند و تأیید کند
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

rx_require_schema($pdo,['reseller_ai_feature'],['setting'=>['reseller_ai_price','reseller_ai_days']]);

// ── پردازش POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_global') {
        try {
            $price = max(0, (int)str_replace([',', ' '], '', (string)($_POST['price'] ?? '0')));
            $days  = max(1, (int)($_POST['days'] ?? '30'));
            $pdo->prepare("UPDATE setting SET reseller_ai_price = :p")->execute([':p' => (string)$price]);
            $pdo->prepare("UPDATE setting SET reseller_ai_days  = :d")->execute([':d' => (string)$days]);
            $flash = 'تنظیمات قیمت و مدت پیش‌فرض ذخیره شد.'; $flashType = 'success';
        } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/reseller_ai.php')); $flashType = 'error'; }
    } elseif ($action === 'activate' || $action === 'extend') {
        $botToken = (string)($_POST['bot_token'] ?? '');
        $daysAdd  = max(1, (int)($_POST['days'] ?? '30'));
        $pricePaid = max(0, (int)str_replace([',', ' '], '', (string)($_POST['price_paid'] ?? '0')));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($botToken !== '') {
            try {
                // رکورد فعلی را بگیر
                $cur = $pdo->prepare("SELECT * FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
                $cur->execute([':t' => $botToken]);
                $row = $cur->fetch(PDO::FETCH_ASSOC);

                $nowTs = time();
                // اگر از قبل فعال و منقضی‌نشده، از روی expires_at تمدید کن؛ وگرنه از الان
                $baseTs = $nowTs;
                if (is_array($row) && !empty($row['expires_at'])) {
                    $expTs = strtotime((string)$row['expires_at']);
                    if ($expTs !== false && $expTs > $nowTs) {
                        $baseTs = $expTs; // تمدید از روی انقضای قبلی
                    }
                }
                $newExpTs = strtotime("+$daysAdd day", $baseTs);
                $newExp = date('Y-m-d H:i:s', $newExpTs);
                $nowStr = date('Y-m-d H:i:s');

                if (is_array($row)) {
                    $pdo->prepare("UPDATE reseller_ai_feature
                        SET status='active', expires_at=:e, activated_at=:a, days=:d, price_paid=:p,
                            notes=CONCAT(IFNULL(notes,''), :n), updated_at=:u
                        WHERE bot_token=:t")
                        ->execute([':e'=>$newExp, ':a'=>$nowStr, ':d'=>$daysAdd, ':p'=>$pricePaid,
                                   ':n'=>($note!==''? "\n[$nowStr] ".$note:''), ':u'=>$nowStr, ':t'=>$botToken]);
                } else {
                    // ساخت رکورد جدید — اطلاعات botsaz را هم بگیر
                    $botInfo = null;
                    try {
                        $bi = $pdo->prepare("SELECT id_user, username FROM botsaz WHERE bot_token = :t LIMIT 1");
                        $bi->execute([':t' => $botToken]);
                        $botInfo = $bi->fetch(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    $pdo->prepare("INSERT INTO reseller_ai_feature
                        (reseller_id, bot_token, bot_username, status, activated_at, expires_at, days, price_paid, notes, created_at, updated_at)
                        VALUES (:rid, :bt, :bu, 'active', :a, :e, :d, :p, :n, :c, :u)")
                        ->execute([
                            ':rid'=> (string)($botInfo['id_user'] ?? ''),
                            ':bt' => $botToken,
                            ':bu' => (string)($botInfo['username'] ?? ''),
                            ':a'  => $nowStr, ':e' => $newExp, ':d' => $daysAdd, ':p' => $pricePaid,
                            ':n'  => ($note !== '' ? "[$nowStr] ".$note : ''),
                            ':c'  => $nowStr, ':u' => $nowStr,
                        ]);
                }
                $flash = ($action === 'extend' ? '✅ اشتراک تمدید شد.' : '✅ هوش مصنوعی برای این ربات فعال شد.') . " انقضا: $newExp";
                $flashType = 'success';
            } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/reseller_ai.php')); $flashType = 'error'; }
        }
    } elseif ($action === 'deactivate') {
        $botToken = (string)($_POST['bot_token'] ?? '');
        if ($botToken !== '') {
            try {
                $pdo->prepare("UPDATE reseller_ai_feature SET status='disabled', updated_at=:u WHERE bot_token=:t")
                    ->execute([':u'=>date('Y-m-d H:i:s'), ':t'=>$botToken]);
                $flash = 'هوش مصنوعی برای این ربات غیرفعال شد.'; $flashType = 'success';
            } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/reseller_ai.php')); $flashType = 'error'; }
        }
    } elseif ($action === 'set_expiry') {
        $botToken = (string)($_POST['bot_token'] ?? '');
        $newExp   = trim((string)($_POST['expires_at'] ?? ''));
        if ($botToken !== '' && $newExp !== '') {
            try {
                $pdo->prepare("UPDATE reseller_ai_feature SET expires_at=:e, updated_at=:u WHERE bot_token=:t")
                    ->execute([':e'=>$newExp, ':u'=>date('Y-m-d H:i:s'), ':t'=>$botToken]);
                $flash = 'تاریخ انقضا ویرایش شد.'; $flashType = 'success';
            } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/reseller_ai.php')); $flashType = 'error'; }
        }
    }
}

// ── خواندن داده‌ها ──
$price = '0'; $days = '30';
try {
    $price = (string)$pdo->query("SELECT reseller_ai_price FROM setting LIMIT 1")->fetchColumn();
    $days  = (string)$pdo->query("SELECT reseller_ai_days FROM setting LIMIT 1")->fetchColumn();
} catch (Throwable $e) {}

// لیست ربات‌های نماینده از botsaz + وضعیت AI آن‌ها
$resellers = [];
$botsazExists = false;
try {
    $chk = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'botsaz'");
    $botsazExists = ((int)$chk->fetchColumn()) > 0;
} catch (Throwable $e) {}

if ($botsazExists) {
    try {
        $resellers = $pdo->query("
            SELECT b.id_user, b.bot_token, b.username, b.time,
                   r.status AS ai_status, r.expires_at, r.activated_at, r.days, r.price_paid, r.notes
            FROM botsaz b
            LEFT JOIN reseller_ai_feature r ON r.bot_token = b.bot_token
            ORDER BY (r.status='active') DESC, b.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // fallback بدون JOIN
        try { $resellers = $pdo->query("SELECT id_user, bot_token, username, time FROM botsaz ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e2) {}
    }
} else {
    $resellers = $pdo->query("SELECT reseller_id AS id_user, bot_token, bot_username AS username, status AS ai_status, expires_at, activated_at, days, price_paid, notes FROM reseller_ai_feature ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$nowTs = time();
function rxAiStatusBadge($row, $nowTs) {
    $st = (string)($row['ai_status'] ?? '');
    if ($st === '' || $st === 'disabled') return '<span class="badge badge-muted">غیرفعال</span>';
    if ($st === 'pending') return '<span class="badge badge-warning">در انتظار</span>';
    if ($st === 'active') {
        $exp = (string)($row['expires_at'] ?? '');
        $expTs = $exp !== '' ? strtotime($exp) : 0;
        if ($expTs && $expTs < $nowTs) return '<span class="badge badge-danger">منقضی</span>';
        return '<span class="badge badge-success">فعال</span>';
    }
    if ($st === 'expired') return '<span class="badge badge-danger">منقضی</span>';
    return '<span class="badge badge-muted">—</span>';
}
function rxTimeLeft($row, $nowTs) {
    $exp = (string)($row['expires_at'] ?? '');
    if ($exp === '') return '—';
    $expTs = strtotime($exp);
    if (!$expTs) return htmlspecialchars($exp);
    $diff = $expTs - $nowTs;
    if ($diff < 0) return 'منقضی (' . htmlspecialchars($exp) . ')';
    $d = floor($diff / 86400);
    $h = floor(($diff % 86400) / 3600);
    return htmlspecialchars($exp) . "<br><small style=\"color:var(--text-muted)\">" . (int)$d . " روز و " . (int)$h . " ساعت باقی</small>";
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>هوش مصنوعی نماینده‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-field{margin-bottom:10px}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin-bottom:3px}
.rx-field input,.rx-field select{width:100%;max-width:280px;padding:8px 10px;border-radius:7px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box;font-family:inherit}
.rx-inline{display:inline-flex;gap:6px;flex-wrap:wrap;align-items:end}
.rx-modal{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:999;padding:16px}
.rx-modal.open{display:flex}
.rx-modal-box{background:var(--surface-1);border-radius:14px;padding:20px;max-width:480px;width:100%;border:1px solid var(--border-mid)}
.rx-modal-box h3{margin:0 0 12px;color:var(--text-main)}
.rx-tbl{width:100%;border-collapse:collapse;font-size:13px}
.rx-tbl th,.rx-tbl td{padding:9px 8px;text-align:right;border-bottom:1px solid var(--border-mid);vertical-align:top}
.rx-tbl th{color:var(--text-muted);font-size:12px;font-weight:600}
.rx-tbl tr:hover{background:var(--surface-2)}
code{color:var(--text-main)}
.rx-note-row td{background:var(--surface-2)}
</style></head>
<body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🤖 هوش مصنوعی ربات‌های نماینده</h1>
<div class="page-head__sub">مدیریت اشتراک پاسخ‌گویی هوش مصنوعی برای مشتریانِ ربات نماینده</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<!-- تنظیمات سراسری -->
<div class="card"><div class="card__head"><h2 class="card__title">⚙️ تنظیمات قیمت و مدت پیش‌فرض</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" class="rx-inline">
<input type="hidden" name="action" value="save_global">
<div class="rx-field"><label>قیمت پیش‌فرض (تومان)</label><input type="number" name="price" value="<?= htmlspecialchars((string)$price) ?>" min="0"></div>
<div class="rx-field"><label>مدت پیش‌فرض (روز)</label><input type="number" name="days" value="<?= htmlspecialchars((string)$days) ?>" min="1"></div>
<button class="btn btn-sm btn-primary" type="submit" style="height:38px">ذخیره</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="text-muted" style="font-size:12px;margin-top:10px;line-height:2">
💡 این مقادیر به‌صورت پیش‌فرض پیشنهاد داده می‌شوند، اما برای هر نماینده می‌توانید عدد متفاوتی وارد کنید.<br>
📦 هوش مصنوعی از همان تنظیماتِ ربات اصلی (کلید API، مدل، پایگاه دانش) استفاده می‌کند — فقط یک کلید مدیریت می‌کنید.
</p>
</div>

<!-- لیست ربات‌های نماینده -->
<div class="card"><div class="card__head"><h2 class="card__title">📋 ربات‌های نماینده (<?= count($resellers) ?>)</h2></div>
<?php if (empty($resellers)): ?>
<p class="text-muted">هنوز هیچ ربات نماینده‌ای ساخته نشده است.</p>
<?php else: ?>
<div style="overflow-x:auto">
<table class="rx-tbl">
<thead><tr>
<th>نماینده</th><th>ربات</th><th>وضعیت AI</th><th>تاریخ انقضا</th><th>قیمت پرداختی</th><th>عملیات</th>
</tr></thead>
<tbody>
<?php foreach ($resellers as $rs): 
    $bt = htmlspecialchars((string)$rs['bot_token'], ENT_QUOTES);
    $uname = (string)($rs['username'] ?? '');
?>
<tr>
<td>
<code><?= htmlspecialchars((string)$rs['id_user']) ?></code><br>
<small class="text-muted">@<?= htmlspecialchars($uname ?: '—') ?></small>
</td>
<td>
<small class="text-muted" style="direction:ltr;display:inline-block"><?= htmlspecialchars(mb_substr((string)$rs['bot_token'],0,20)) ?>…</small><br>
<?php if ($uname !== ''): ?><small>🤖 @<?= htmlspecialchars($uname) ?></small><?php endif; ?>
</td>
<td><?= rxAiStatusBadge($rs, $nowTs) ?></td>
<td><?= rxTimeLeft($rs, $nowTs) ?></td>
<td><?= isset($rs['price_paid']) ? number_format((int)$rs['price_paid']) : '0' ?> ت</td>
<td>
<?php if ($isMainAdmin): ?>
<button class="btn btn-sm btn-primary" onclick="rxOpenModal('<?= $bt ?>','<?= htmlspecialchars($uname,ENT_QUOTES) ?>','<?= htmlspecialchars((string)($rs['expires_at'] ?? ''),ENT_QUOTES) ?>',<?= (int)$days ?>,<?= (int)$price ?>)">⚡ فعال / تمدید</button>
<?php if (($rs['ai_status'] ?? '') === 'active'): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="bot_token" value="<?= $bt ?>"><button class="btn btn-sm" type="submit" onclick="return confirm('غیرفعال شود؟')">🚫 خاموش</button></form>
<?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

</div></section></section>

<!-- Modal فعال‌سازی/تمدید -->
<div class="rx-modal" id="rxModal">
<div class="rx-modal-box">
<h3 id="rxModalTitle">⚡ فعال‌سازی / تمدید هوش مصنوعی</h3>
<form method="post">
<input type="hidden" name="action" value="activate">
<input type="hidden" name="bot_token" id="rxModalToken">
<p style="color:var(--text-muted);font-size:13px;margin-bottom:12px" id="rxModalBot"></p>
<div class="rx-field"><label>تعداد روز (برای تمدید/فعال‌سازی)</label><input type="number" name="days" id="rxModalDays" value="30" min="1"></div>
<div class="rx-field"><label>قیمت پرداختی (تومان — اختیاری)</label><input type="number" name="price_paid" id="rxModalPrice" value="0" min="0"></div>
<div class="rx-field"><label>توضیح (اختیاری)</label><input type="text" name="note" placeholder="مثلاً: پرداخت کارت‌به‌کارت"></div>
<div class="rx-field"><label>یا ویرایش دقیق تاریخ انقضا (اختیاری)</label><input type="datetime-local" name="expires_at" id="rxModalExp" style="direction:ltr"></div>
<div style="display:flex;gap:8px;margin-top:14px">
<button class="btn btn-sm btn-primary" type="submit" name="action" value="activate">✅ فعال/تمدید</button>
<button class="btn btn-sm" type="button" onclick="rxCloseModal()">انصراف</button>
</div>
</form>
</div>
</div>

<script>
function rxOpenModal(token, username, curExp, defDays, defPrice) {
    document.getElementById('rxModalToken').value = token;
    document.getElementById('rxModalBot').textContent = 'ربات: @' + (username || '—');
    var dEl = document.getElementById('rxModalDays');
    var pEl = document.getElementById('rxModalPrice');
    if (!dEl.value || dEl.value === '') dEl.value = defDays || 30;
    if (!pEl.value || pEl.value === '0') pEl.value = defPrice || 0;
    // پر کردن تاریخ فعلی
    var expEl = document.getElementById('rxModalExp');
    if (curExp && curExp.length >= 16) {
        expEl.value = curExp.substring(0,16);
    }
    // اگر تاریخ دقیق وارد شد، اکشن را به set_expiry تغییر بده
    expEl.onchange = function() {
        var btn = expEl.closest('form').querySelector('button[type=submit][value=activate]');
        var daysInp = document.getElementById('rxModalDays');
        if (expEl.value) {
            btn.textContent = '📅 ذخیره تاریخ دقیق';
            btn.value = 'set_expiry';
            daysInp.disabled = true;
        } else {
            btn.textContent = '✅ فعال/تمدید';
            btn.value = 'activate';
            daysInp.disabled = false;
        }
    };
    document.getElementById('rxModal').classList.add('open');
}
function rxCloseModal() {
    document.getElementById('rxModal').classList.remove('open');
}
document.getElementById('rxModal').addEventListener('click', function(e) {
    if (e.target === this) rxCloseModal();
});
</script>
</body></html>
