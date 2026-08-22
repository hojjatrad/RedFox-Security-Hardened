<?php
/** Red Fox — مشاهده/سینک زنده‌ی سرویس کاربر + کانفیگ + QR Code */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) require __DIR__ . '/../vendor/autoload.php';

$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$flash = '';

$uid = trim((string)($_GET['uid'] ?? ''));
$iid = trim((string)($_GET['iid'] ?? ''));
if ($uid === '' || !ctype_digit($uid)) { header('Location: users.php'); exit; }

// اطلاعات کاربر
$user = []; try { $s=$pdo->prepare("SELECT * FROM user WHERE id=? LIMIT 1"); $s->execute([$uid]); $user=$s->fetch(PDO::FETCH_ASSOC); } catch(Throwable $e){}
if (!$user) { header('Location: users.php'); exit; }

// سرویس‌های کاربر
$invoices = []; try { $s=$pdo->prepare("SELECT * FROM invoice WHERE id_user=? ORDER BY time_sell DESC"); $s->execute([$uid]); $invoices=$s->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e){}

// اگر iid مشخص است، فقط آن سرویس را از پنل بخوان
$liveData = null; $panelInfo = null;
if ($iid !== '') {
    $inv = null;
    foreach ($invoices as $i) { if ((string)$i['id_invoice'] === $iid) { $inv = $i; break; } }
    if ($inv) {
        // اطلاعات پنل
        try { $ps=$pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel=? LIMIT 1"); $ps->execute([$inv['Service_location']]); $panelInfo=rx_secret_decrypt_panel_row($ps->fetch(PDO::FETCH_ASSOC) ?: []); } catch(Throwable $e){}
        if ($panelInfo && in_array($panelInfo['type'] ?? '', ['marzban','marzneshin'], true)) {
            $liveData = redfox_fetch_live_user($panelInfo, $inv['username']);
        }
    }
}

/**
 * فراخوانی API پنل Marzban/Marzneshin برای گرفتن داده‌ی زنده‌ی کاربر
 */
function redfox_fetch_live_user($panel, $panelUsername) {
    $url = rtrim((string)($panel['url_panel'] ?? ''), '/');
    $user = (string)($panel['username_panel'] ?? '');
    $pass = (string)($panel['password_panel'] ?? '');
    $type = (string)($panel['type'] ?? 'marzban');
    if ($url === '' || $user === '' || $panelUsername === '') return null;

    // Marzban: GET /api/user/{username} با توکن
    // ۱) لاگین برای گرفتن توکن
    $tokenUrl = $type === 'marzneshin' ? $url . '/api/admin/token' : $url . '/api/admin/token';
    $ch = curl_init($tokenUrl);
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch, 'panel');
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['username'=>$user,'password'=>$pass,'grant_type'=>'password']), CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $policy = redfox_apply_panel_curl_url_policy($ch, $tokenUrl);
    if (empty($policy['ok'])) { curl_close($ch); return ['error' => 'آدرس پنل طبق سیاست خروجی امن مجاز نیست']; }
    $resp = curl_exec($ch); curl_close($ch);
    $tj = json_decode((string)$resp, true);
    $token = $tj['access_token'] ?? '';
    if ($token === '') return ['error' => 'توکن پنل گرفته نشد — یوزر/پسورد پنل را بررسی کنید'];

    // ۲) گرفتن کاربر
    $userUrl = ($type === 'marzneshin' ? $url . '/api/users/' . urlencode($panelUsername) : $url . '/api/user/' . urlencode($panelUsername));
    $ch2 = curl_init($userUrl);
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch2, 'panel');
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token]]);
    $policy = redfox_apply_panel_curl_url_policy($ch2, $userUrl);
    if (empty($policy['ok'])) { curl_close($ch2); return ['error' => 'آدرس پنل طبق سیاست خروجی امن مجاز نیست']; }
    $resp2 = curl_exec($ch2); $code2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
    if ($code2 !== 200) return ['error' => 'کاربر در پنل یافت نشد (HTTP ' . $code2 . ')'];
    $uj = json_decode((string)$resp2, true);
    if (!is_array($uj)) return ['error' => 'پاسخ نامعتبر از پنل'];

    // محاسبه‌ی حجم
    $dataLimit = (int)($uj['data_limit'] ?? 0); // bytes (0 = unlimited)
    $usedTraffic = (int)($uj['used_traffic'] ?? ($uj['lifetime_used_traffic'] ?? 0));
    $remaining = $dataLimit > 0 ? max(0, $dataLimit - $usedTraffic) : -1; // -1 = unlimited
    $expireTs = (int)($uj['expire'] ?? 0);
    $status = (string)($uj['status'] ?? 'unknown');
    $enabled = (bool)($uj['enabled'] ?? true);

    // لینک‌های کانفیگ
    $subUrl = $uj['subscription_url'] ?? '';
    $configLinks = [];
    if (isset($uj['links']) && is_array($uj['links'])) $configLinks = $uj['links'];

    // ── Red Fox FIX: تایم‌استمپ انقضای نامعتبر (قبل از ۲۰۰۱) را نامحدود می‌کنیم ──
    $MIN_VALID_TS = 978307200; // 2001-01-01
    $expireValid = ($expireTs > 0 && $expireTs >= $MIN_VALID_TS);
    $expireStr = $expireValid ? date('Y/m/d H:i', $expireTs) : 'نامحدود';
    $isExpired = $expireValid && ($expireTs < time());

    // ── Red Fox FIX: آنلاین فقط اگر سرویس منقضی/غیرفعال نیست ──
    $isOnline = $enabled && !$isExpired && !empty($uj['online_at']);

    return [
        'used_gb'       => round($usedTraffic / 1073741824, 2),
        'total_gb'      => $dataLimit > 0 ? round($dataLimit / 1073741824, 2) : -1,
        'remaining_gb'  => $remaining > 0 ? round($remaining / 1073741824, 2) : ($dataLimit === 0 ? -1 : 0),
        'used_pct'      => $dataLimit > 0 ? min(100, round($usedTraffic / $dataLimit * 100, 1)) : 0,
        'expire_ts'     => $expireValid ? $expireTs : 0,
        'expire_str'    => $expireStr,
        'expired'       => $isExpired,
        'status'        => $status,
        'enabled'       => $enabled,
        'sub_url'       => $subUrl,
        'configs'       => $configLinks,
        'online'        => $isOnline,
    ];
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>سرویس کاربر | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:10px 14px;border-radius:8px;margin:0 0 14px;font-size:13px;line-height:1.8}.rx-flash.ok{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.err{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:12px;padding:16px;text-align:center}.rx-stat .n{font-size:22px;font-weight:800;color:var(--text-main)}.rx-stat .l{color:var(--text-muted);font-size:11px;margin-top:3px}
.rx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.rx-card{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:18px;margin-bottom:14px}
.rx-card h2{color:var(--text-main);font-size:15px;margin:0 0 12px}
.rx-pre{background:var(--surface-2,var(--surface-1));border:1px solid var(--border-mid);border-radius:8px;padding:10px;font-size:12px;color:var(--text-main);white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;direction:ltr;margin:6px 0}
.rx-copy{cursor:pointer;padding:4px 10px;border-radius:6px;background:var(--accent);color:var(--accent-fg);font-size:11px;border:none;font-weight:600}
.rx-qr{text-align:center;margin:10px 0}.rx-qr img{max-width:200px;border-radius:10px}
table{width:100%;border-collapse:collapse}th,td{padding:7px;border-bottom:1px solid var(--border-soft);text-align:right;font-size:13px;color:var(--text-main)}th{color:var(--text-muted);font-size:12px}
.badge{padding:3px 9px;border-radius:14px;font-size:11px;font-weight:700}.badge-ok{background:var(--color-success-soft);color:var(--color-success)}.badge-err{background:var(--color-danger-soft);color:var(--color-danger)}.badge-warn{background:var(--color-warning-soft);color:var(--color-warning)}
.bar-bg{background:var(--surface-2,var(--surface-1));border-radius:6px;height:20px;overflow:hidden}.bar-fg{height:100%;border-radius:6px;background:linear-gradient(90deg,var(--accent),var(--accent-mid))}
.rx-info{color:var(--text-muted);font-size:12px;line-height:2}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head">
<h1 class="page-head__title">🔌 سرویس کاربر #<?= htmlspecialchars($uid,ENT_QUOTES) ?></h1>
<div class="page-head__sub"><?= htmlspecialchars((string)($user['username'] ?: $user['namecustom'] ?: '—'),ENT_QUOTES,'UTF-8') ?> — <?= count($invoices) ?> سرویس</div>
</div>

<a class="btn btn-sm" href="user.php?id=<?= urlencode($uid) ?>" style="margin-bottom:14px">↩️ پروفایل کاربر</a>

<?php if ($liveData && isset($liveData['error'])): ?>
<div class="rx-flash err">⚠️ <?= htmlspecialchars($liveData['error'],ENT_QUOTES,'UTF-8') ?></div>
<?php elseif ($liveData): ?>
<div class="rx-flash ok">✅ داده‌ی زنده از پنل دریافت شد. برای به‌روزرسانی دوباره دکمه‌ی «سینک» را بزنید.</div>
<div class="rx-grid">
<div class="rx-stat"><div class="n"><?= $liveData['total_gb']<0?'∞':$liveData['total_gb'].' GB' ?></div><div class="l">حجم کل</div></div>
<div class="rx-stat"><div class="n"><?= $liveData['used_gb'] ?> GB</div><div class="l">مصرف شده</div></div>
<div class="rx-stat"><div class="n"><?= $liveData['remaining_gb']<0?'∞':$liveData['remaining_gb'].' GB' ?></div><div class="l">باقی‌مانده</div></div>
<div class="rx-stat"><div class="n"><?= $liveData['expire_str'] ?></div><div class="l">تاریخ انقضا</div></div>
<div class="rx-stat"><div class="n"><span class="badge <?= $liveData['enabled']?'badge-ok':'badge-err' ?>"><?= $liveData['status'] ?></span></div><div class="l">وضعیت</div></div>
<div class="rx-stat"><div class="n"><?= $liveData['online']?'🟢 آنلاین':'⚪ آفلاین' ?></div><div class="l">اتصال</div></div>
</div>

<?php if ($liveData['total_gb'] > 0): ?>
<div class="rx-card"><h2>📊 مصرف حجم</h2>
<div class="bar-bg"><div class="bar-fg" style="width:<?= $liveData['used_pct'] ?>%"></div></div>
<p class="rx-info" style="margin-top:6px"><?= $liveData['used_pct'] ?>% مصرف شده</p>
</div>
<?php endif; ?>

<?php if (!empty($liveData['sub_url'])): ?>
<div class="rx-card"><h2>📋 لینک اشتراک (Subscription)</h2>
<div class="rx-pre"><?= htmlspecialchars($liveData['sub_url'],ENT_QUOTES,'UTF-8') ?></div>
<button class="rx-copy" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($liveData['sub_url'],ENT_QUOTES) ?>');this.textContent='کپی شد'">📋 کپی</button>
<?php
// تولید QR از لینک اشتراک
try {
    $qr = (new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter(),
        data: (string)$liveData['sub_url'],
        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
        size: 200,
        margin: 2
    ))->build();
    $qrUri = 'data:image/png;base64,' . base64_encode($qr->getString());
    echo '<div class="rx-qr"><img src="' . $qrUri . '" alt="QR"></div>';
} catch (Throwable $e) {
    echo '<p class="rx-info">QR Code در دسترس نیست: ' . htmlspecialchars(redfox_public_exception($e, 'panel/user_service.php'),ENT_QUOTES) . '</p>';
}
?>
</div>
<?php endif; ?>

<?php if (!empty($liveData['configs'])): ?>
<div class="rx-card"><h2>📄 کانفیگ‌ها (<?= count($liveData['configs']) ?>)</h2>
<?php foreach ($liveData['configs'] as $ci => $cfg): ?>
<div class="rx-pre"><?= htmlspecialchars((string)$cfg,ENT_QUOTES,'UTF-8') ?></div>
<button class="rx-copy" onclick="navigator.clipboard.writeText(<?= json_encode((string)$cfg) ?>);this.textContent='کپی شد'">📋 کپی کانفیگ <?= (int)$ci+1 ?></button>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="rx-card"><h2>📋 سرویس‌های کاربر (<?= count($invoices) ?>)</h2>
<?php if (empty($invoices)): ?><p class="rx-info">سرویسی ثبت نشده.</p><?php else: ?>
<table><thead><tr><th>محصول</th><th>پنل</th><th>حجم</th><th>زمان</th><th>وضعیت</th><th>سینک زنده</th></tr></thead><tbody>
<?php foreach ($invoices as $i): ?>
<tr><td><?= htmlspecialchars((string)$i['name_product'],ENT_QUOTES,'UTF-8') ?></td>
<td class="rx-info"><?= htmlspecialchars((string)$i['Service_location'],ENT_QUOTES) ?></td>
<td><?= (int)$i['Volume'] ?: '—' ?> GB</td>
<td><?= (int)$i['Service_time'] ?: '—' ?> روز</td>
<td><span class="badge <?= $i['Status']==='active'?'badge-ok':($i['Status']==='end_of_time'||i['Status']==='end_of_volume'?'badge-err':'badge-warn') ?>"><?= htmlspecialchars((string)$i['Status'],ENT_QUOTES) ?></span></td>
<td><a class="btn btn-sm btn-primary" href="user_service.php?uid=<?= urlencode($uid) ?>&iid=<?= urlencode((string)$i['id_invoice']) ?>">🔄 سینک + کانفیگ</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="rx-info" style="margin-top:10px">💡 روی «🔄 سینک» کلیک کنید تا داده‌ی زنده (مصرف/باقی‌مانده/انقضا/کانفیگ/QR) از پنل VPN دریافت شود.</p>
</div><?php endif; ?>

</div></section></section></body></html>
