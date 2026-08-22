<?php
/**
 * Red Fox — مدیریت دسترسی‌های سوپر نماینده.
 * ادمین می‌تواند برای هر نماینده، دسترسی‌های زیر را به‌صورت انتخابی فعال/غیرفعال کند:
 *  - products: تعریف محصول و قیمت اختصاصی
 *  - categories: ایجاد دسته‌بندی
 *  - extend_user: افزایش زمان/حجم کاربران خودش
 *  - charge_user: شارژ دستی کیف پول کاربران خودش
 *  - manage_users: مدیریت کاربران (بلاک، اطلاع‌رسانی و ...)
 *  - reports: مشاهده‌ی گزارش فروش خودش
 *  - set_prices: تعیین قیمت حجم/زمان اختصاصی
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

rx_require_schema($pdo,[],['user'=>['reseller_perms']]);

$permDefs = [
    'products'     => ['label' => '🛍 تعریف محصول و قیمت اختصاصی', 'desc' => 'نماینده می‌تواند محصول با نام و قیمت دلخواه در ربات خود اضافه/ویرایش/حذف کند'],
    'categories'   => ['label' => '📂 ایجاد دسته‌بندی',             'desc' => 'نماینده می‌تواند دسته‌بندی برای محصولات خود بسازد'],
    'extend_user'  => ['label' => '⏰ افزایش زمان/حجم کاربر',        'desc' => 'نماینده می‌تواند به کاربرانِ خود زمان یا حجم اضافه کند'],
    'charge_user'  => ['label' => '💰 شارژ دستی کیف پول کاربر',      'desc' => 'نماینده می‌تواند موجودی کاربرانِ خود را دستی تغییر دهد'],
    'manage_users' => ['label' => '👥 مدیریت کاربران',                'desc' => 'جستجو، مشاهده‌ی اطلاعات، بلاک/آنبلاک کاربران خود'],
    'reports'      => ['label' => '📊 مشاهده‌ی گزارش فروش',           'desc' => 'نماینده می‌تواند آمار فروش، درآمد و گزارش‌های ربات خود را ببیند'],
    'set_prices'   => ['label' => '💲 تعیین قیمت حجم/زمان',          'desc' => 'نماینده می‌تواند قیمت هر گیگ و هر روز را برای ربات خود تنظیم کند'],
    'broadcast'    => ['label' => '📣 پیام مستقیم و همگانی',           'desc' => 'ارسال صف‌بندی‌شده فقط به مشتریان Scope خود نماینده'],
    'api'          => ['label' => '🔑 API خواندنی نمایندگی',           'desc' => 'ساخت کلید منقضی‌شونده برای آمار، مشتری و سفارش‌های Scope‌شده'],
    'support'      => ['label' => '🎟 صندوق تیکت مشتریان',             'desc' => 'مشاهده و پاسخ فقط به تیکت مشتریان Scope خود نماینده'],
];

// POST: ذخیره دسترسی‌ها
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $rid = trim((string)($_POST['reseller_id'] ?? ''));
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'save' && $rid !== '' && ctype_digit($rid)) {
        $perms = [];
        foreach (array_keys($permDefs) as $pk) {
            $perms[$pk] = isset($_POST['perm_' . $pk]) ? 1 : 0;
        }
        try {
            $pdo->prepare("UPDATE user SET reseller_perms = ? WHERE id = ?")
                ->execute([json_encode($perms), $rid]);
            $flash = 'دسترسی‌ها ذخیره شد.'; $flashType = 'success';
        } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/reseller_permissions.php')); $flashType = 'error'; }
    } elseif ($act === 'reset' && $rid !== '' && ctype_digit($rid)) {
        $pdo->prepare("UPDATE user SET reseller_perms = '{}' WHERE id = ?")->execute([$rid]);
        $flash = 'همه‌ی دسترسی‌ها لغو شد.'; $flashType = 'success';
    }
}

// انتخاب نماینده
$selRid = trim((string)($_GET['id'] ?? $_POST['reseller_id'] ?? ''));
$resellers = [];
try { $resellers = $pdo->query("SELECT id, username, namecustom, agent FROM user WHERE agent IN ('n','n2') ORDER BY id DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$currentPerms = [];
if ($selRid !== '' && ctype_digit($selRid)) {
    try {
        $st = $pdo->prepare("SELECT reseller_perms FROM user WHERE id = ? LIMIT 1");
        $st->execute([$selRid]);
        $raw = (string)$st->fetchColumn();
        $currentPerms = json_decode($raw, true) ?: [];
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>دسترسی‌های سوپر نماینده | ربات رد فاکس</title>
<link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-card-title{color:var(--text-main)}
.rx-perm{display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--border-soft)}
.rx-perm:last-child{border-bottom:none}
.rx-perm .rx-perm-info{flex:1}
.rx-perm .rx-perm-info b{font-size:14px;color:var(--text-main);display:block}
.rx-perm .rx-perm-info small{font-size:12px;color:var(--text-muted);line-height:1.8;display:block;margin-top:3px}
.rx-perm input[type=checkbox]{width:22px;height:22px;cursor:pointer;margin-top:2px;flex-shrink:0}
.rx-sel{padding:10px 14px;border-radius:10px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:14px;font-family:inherit;width:100%;max-width:500px;box-sizing:border-box}
.rx-sel-wrap{margin-bottom:16px}
.rx-info{color:var(--text-muted);font-size:13px;line-height:2;padding:14px;background:var(--accent-soft);border-radius:10px;margin-bottom:16px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">⭐ دسترسی‌های سوپر نماینده</h1>
<div class="page-head__sub">فعال‌سازی دسترسی‌های انتخابی برای هر نماینده</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<div class="rx-info">
    💡 با فعال‌ کردن هر دسترسی، نماینده می‌تواند آن قابلیت را در <b>ربات خودش (vpnbot)</b> استفاده کند. دسترسی‌ها به‌صورت جداگانه و انتخابی فعال می‌شوند.
</div>

<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">انتخاب نماینده</h2></div>
<form method="get" class="rx-sel-wrap">
<select name="id" class="rx-sel" onchange="this.form.submit()">
<option value="">— یک نماینده انتخاب کنید —</option>
<?php foreach ($resellers as $r): ?>
<option value="<?= htmlspecialchars((string)$r['id'],ENT_QUOTES) ?>" <?= $selRid===(string)$r['id']?'selected':'' ?>>#<?= htmlspecialchars((string)$r['id']) ?> — <?= htmlspecialchars((string)($r['username'] ?: $r['namecustom'] ?: 'نامشخص'),ENT_QUOTES,'UTF-8') ?> (<?= $r['agent']==='n2'?'پیشرفته':'عادی' ?>)</option>
<?php endforeach; ?>
</select>
</form>
</div>

<?php if ($selRid !== '' && ctype_digit($selRid)): ?>
<div class="card">
<div class="card__head"><h2 class="card__title rx-card-title">دسترسی‌های نماینده #<?= htmlspecialchars($selRid,ENT_QUOTES) ?></h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save">
<input type="hidden" name="reseller_id" value="<?= htmlspecialchars($selRid,ENT_QUOTES) ?>">
<?php foreach ($permDefs as $pk => $pd): ?>
<div class="rx-perm">
<input type="checkbox" name="perm_<?= $pk ?>" id="perm_<?= $pk ?>" <?= !empty($currentPerms[$pk]) ? 'checked' : '' ?>>
<div class="rx-perm-info"><label for="perm_<?= $pk ?>" style="cursor:pointer"><b><?= $pd['label'] ?></b><small><?= $pd['desc'] ?></small></label></div>
</div>
<?php endforeach; ?>
<div style="display:flex;gap:10px;margin-top:16px">
<button class="btn btn-sm btn-primary" type="submit">💾 ذخیره دسترسی‌ها</button>
<button class="btn btn-sm btn-danger" type="submit" name="action" value="reset" formnovalidate onclick="return confirm('همه دسترسی‌ها لغو شود؟')">🚫 لغو همه</button>
</div>
</form>
<?php else: ?>
<p class="text-muted">فقط ادمین اصلی می‌تواند تغییر دهد.</p>
<?php endif; ?>
</div>
<?php endif; ?>

</div></section></section></body></html>
