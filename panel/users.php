<?php
/**
 * Red Fox — لیست کاربران (نسخه‌ی حرفه‌ای با سینک گروهی)
 * - داده‌ی زنده از کش خوانده می‌شود (سریع — بدون API call در هر بارگذاری)
 * - دکمه‌ی «سینک همه» داده‌ی زنده همه‌ی کاربران را از پنل دریافت و کش می‌کند
 * - نمایش صحیح تاریخ انقضا و وضعیت آنلاین/آفلاین
 * - پشتیبانی از AJAX برای سینک گروهی و انفرادی
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$__sessUser = isset($_SESSION["user"]) ? (string)$_SESSION["user"] : '';
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $__sessUser, PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);
if ($__sessUser === '' || !$result) { header('Location: login.php'); exit; }

$rxUserCacheReady = true;
try {
    rx_require_schema($pdo,['rx_user_cache']);
} catch (Throwable $__schemaErr) {
    // Try to create the table automatically
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `rx_user_cache` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(64) NOT NULL,
            `invoice_id` VARCHAR(64) NOT NULL,
            `username` VARCHAR(191) NOT NULL DEFAULT '',
            `panel_name` VARCHAR(191) NOT NULL DEFAULT '',
            `panel_type` VARCHAR(30) NOT NULL DEFAULT '',
            `expire_ts` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `used_traffic` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `data_limit` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(30) NOT NULL DEFAULT 'unknown',
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `online` TINYINT(1) NOT NULL DEFAULT 0,
            `sub_url` VARCHAR(1000) NOT NULL DEFAULT '',
            `synced_at` DATETIME NULL,
            UNIQUE KEY `uq_cache_invoice` (`invoice_id`),
            KEY `idx_cache_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        rx_require_schema($pdo,['rx_user_cache']);
    } catch (Throwable $__createErr) {
        error_log('[panel/users] rx_user_cache schema error: ' . redfox_exception_fingerprint($__createErr));
        $rxUserCacheReady = false;
    }
}

// ════════════════════════════════════════════════════════════════════
//  AJAX endpoints: reads stay on GET; synchronization is POST-only.
// ═════════════════════════════════════════════════════════════════════
$ajaxGet = (string)($_GET['ajax'] ?? '');
$ajaxPost = (string)($_POST['ajax'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $ajaxGet === 'sync_count') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $stmt = $pdo->query("SELECT COUNT(*)
                             FROM invoice i
                             LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location
                             WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                               AND i.username IS NOT NULL AND i.username != ''
                               AND mp.type IN ('marzban','marzneshin','pasargard')");
        echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
    } catch (Throwable $e) {
        error_log('[panel/users] sync_count error: ' . redfox_exception_fingerprint($e));
        echo json_encode(['ok' => false, 'error' => 'count_failed', 'detail' => $e->getMessage()]);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $ajaxPost !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($ajaxPost === 'sync_one') {
        $invoiceId = trim((string)($_POST['iid'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $invoiceId)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'invalid_iid']);
            exit;
        }
        echo json_encode(redfox_sync_one_invoice($pdo, $invoiceId));
        exit;
    }

    if ($ajaxPost === 'sync_batch') {
        $offsetRaw = trim((string)($_POST['offset'] ?? '0'));
        $batchRaw = trim((string)($_POST['batch'] ?? '3'));
        if (!preg_match('/^[0-9]{1,9}$/', $offsetRaw) || !preg_match('/^[1-5]$/', $batchRaw)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'invalid_pagination']);
            exit;
        }
        $offset = min(100000000, (int)$offsetRaw);
        $batch = (int)$batchRaw;

        // Check if required tables exist
        try {
            $pdo->query("SELECT 1 FROM `marzban_panel` LIMIT 0");
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'table_missing', 'detail' => 'جدول `marzban_panel` وجود ندارد. لطفاً ابتدا از بخش «انتقال از دیتابیس» جدول‌های لازم را منتقل کنید.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location,
                                          mp.url_panel, mp.username_panel, mp.password_panel, mp.type
                                   FROM invoice i
                                   LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location
                                   WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                                     AND i.username IS NOT NULL AND i.username != ''
                                     AND mp.type IN ('marzban','marzneshin','pasargard')
                                   ORDER BY i.time_sell DESC
                                   LIMIT :lim OFFSET :off");
            $stmt->bindValue(':lim', $batch, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[panel/users] sync_batch query error: ' . redfox_exception_fingerprint($e));
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'query_failed', 'detail' => $e->getMessage()]);
            exit;
        }

        $synced = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $row) {
            $syncResult = redfox_sync_invoice_row($row);
            if (!empty($syncResult['ok'])) { $synced++; } else { $failed++; $errors[] = (string)($syncResult['error'] ?? 'unknown'); }
        }
        $errSummary = $errors ? array_count_values($errors) : [];
        echo json_encode([
            'ok' => true,
            'synced' => $synced,
            'failed' => $failed,
            'batch_count' => count($rows),
            'done' => count($rows) < $batch,
            'errors' => $errSummary,
        ]);
        exit;
    }

    // ── Debug: نمایش دلیل عدم نتیجه sync ──
    if ($ajaxGet === 'sync_debug') {
        $debug = ['ok' => true];
        try { $debug['invoice_count'] = (int)$pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn(); } catch (Throwable $e) { $debug['invoice_count'] = 'error: ' . $e->getMessage(); }
        try { $debug['active_invoices'] = (int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn(); } catch (Throwable $e) { $debug['active_invoices'] = 'error'; }
        try { $debug['marzban_panel_exists'] = true; $debug['panel_count'] = (int)$pdo->query("SELECT COUNT(*) FROM marzban_panel")->fetchColumn(); } catch (Throwable $e) { $debug['marzban_panel_exists'] = false; $debug['panel_count'] = 0; }
        try { $debug['user_count'] = (int)$pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn(); } catch (Throwable $e) { $debug['user_count'] = 'error: ' . $e->getMessage(); }
        // Test the actual sync query
        try {
            $testStmt = $pdo->query("SELECT COUNT(*) FROM invoice i LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND i.username IS NOT NULL AND i.username != '' AND mp.type IN ('marzban','marzneshin','pasargard')");
            $debug['sync_match_count'] = (int)$testStmt->fetchColumn();
        } catch (Throwable $e) { $debug['sync_match_count'] = 'error: ' . $e->getMessage(); }
        // Check Service_location values
        try {
            $sl = $pdo->query("SELECT DISTINCT Service_location FROM invoice WHERE Service_location IS NOT NULL AND Service_location != '' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
            $debug['service_locations'] = $sl;
        } catch (Throwable $e) { $debug['service_locations'] = []; }
        // Check marzban_panel name_panel values
        try {
            $np = $pdo->query("SELECT name_panel, type FROM marzban_panel LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            $debug['panels'] = $np;
        } catch (Throwable $e) { $debug['panels'] = []; }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown_ajax']);
    exit;
}

if ($ajaxGet !== '' || $ajaxPost !== '') {
    http_response_code(405);
    header('Allow: GET, POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// ── بارگذاری لیست کاربران ──
try {
    $stmt = $pdo->prepare("SELECT * FROM user ORDER BY id DESC");
    $stmt->execute();
    $listusers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $listusers = []; }

// ── بارگذاری سرویس فعال + کش هر کاربر (در یک کوئری) ──
$svcMap = []; // user_id => [invoice + cache]
try {
    $stmt = $pdo->query("SELECT i.*, c.expire_ts, c.used_traffic, c.data_limit, c.status AS live_status,
                                c.enabled AS live_enabled, c.online AS live_online, c.sub_url AS live_sub,
                                c.synced_at
                         FROM invoice i
                         LEFT JOIN rx_user_cache c ON c.invoice_id = i.id_invoice
                         WHERE i.Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                         ORDER BY i.time_sell DESC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (string)$row['id_user'];
        if (!isset($svcMap[$uid])) $svcMap[$uid] = $row; // اولین (جدیدترین) سرویس
    }
} catch (Throwable $e) {}

/**
 * محاسبه‌ی نمایش انقضا — تایم‌استمپ نامعتبر (قبل از ۲۰۰۱) را نامحدود می‌کند.
 */
function redfox_expire_display($expireTs) {
    $expireTs = (int)$expireTs;
    $MIN_VALID = 978307200; // 2001-01-01 — هرچیز قبل از این نامعتبر است
    if ($expireTs <= 0 || $expireTs < $MIN_VALID) {
        return ['str' => 'نامحدود', 'ts' => 0, 'valid' => false, 'expired' => false, 'days_left' => -1];
    }
    $now = time();
    $expired = $expireTs < $now;
    $daysLeft = max(0, (int)ceil(($expireTs - $now) / 86400));
    return ['str' => date('Y/m/d H:i', $expireTs), 'ts' => $expireTs, 'valid' => true, 'expired' => $expired, 'days_left' => $daysLeft];
}

/**
 * سینک یک فاکتور از پنل — اطلاعات زنده را در rx_user_cache ذخیره می‌کند.
 */
function redfox_sync_one_invoice($pdo, $invoiceId) {
    try {
        $stmt = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location,
                                      mp.url_panel, mp.username_panel, mp.password_panel, mp.type
                               FROM invoice i
                               LEFT JOIN marzban_panel mp ON mp.name_panel = i.Service_location
                               WHERE i.id_invoice = :iid LIMIT 1");
        $stmt->execute([':iid' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok'=>false,'error'=>'invoice_not_found'];
        return redfox_sync_invoice_row($row);
    } catch (Throwable $e) {
        error_log('[panel/users] invoice sync failed: ' . redfox_exception_fingerprint($e));
        return ['ok'=>false,'error'=>'sync_failed'];
    }
}

/**
 * گرفتن داده‌ی زنده از پنل Marzban/Marzneshin و ذخیره در کش.
 */
function redfox_sync_invoice_row($row) {
    global $pdo;
    $url = rtrim((string)($row['url_panel'] ?? ''), '/');
    $panelUser = (string)($row['username_panel'] ?? '');
    $panelPass = '';
    $rawPass = isset($row['password_panel']) ? (string)$row['password_panel'] : '';
    if ($rawPass !== '') {
        if (rx_secret_is_encrypted($rawPass)) {
            // رمز عبور رمزنگاری شده — تلاش برای رمزگشایی
            try {
                $panelPass = (string)rx_secret_decrypt($rawPass);
            } catch (Throwable $__de) {
                // رمزگشایی ناموفق — کلید MASTER_KEY متفاوت از کلید قبلی است
                return ['ok'=>false,'error'=>'key_mismatch','detail'=>'رمز عبور پنل با کلید فعلی قابل رمزگشایی نیست — لطفاً رمز عبور پنل را در بخش «مدیریت پنل‌ها» دوباره وارد کنید'];
            }
        } else {
            // رمز عبور plain text (از دیتابیس قدیمی)
            $panelPass = $rawPass;
        }
    }
    $type = (string)($row['type'] ?? '');
    if ($type === '' || $type === 'pasargard') $type = 'marzban'; // fallback
    $panelUsername = (string)($row['username'] ?? '');
    if ($url === '' || $panelUser === '' || $panelUsername === '') return ['ok'=>false,'error'=>'no_panel_info','detail'=>'آدرس/نام‌کاربری پنل خالی است'];
    if (($row['url_panel'] ?? '') === '') return ['ok'=>false,'error'=>'no_panel_url','detail'=>'آدرس پنل در marzban_panel ثبت نشده'];

    // ۱) توکن
    $ch = curl_init($url . '/api/admin/token');
    if (!$ch) return ['ok'=>false,'error'=>'curl_init_failed'];
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch, 'panel');
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['username'=>$panelUser,'password'=>$panelPass,'grant_type'=>'password']), CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $tokenUrl = $url . '/api/admin/token';
    $policy = redfox_apply_panel_curl_url_policy($ch, $tokenUrl);
    if (empty($policy['ok'])) { curl_close($ch); return ['ok'=>false,'error'=>'panel_endpoint_blocked','detail'=>'آدرس پنل مسدود شده: '.$url.' — تنظیم REDFOX_ALLOW_PRIVATE_PANEL_ENDPOINTS بررسی شود']; }
    $resp = curl_exec($ch);
    $curlErr = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlErr !== 0) return ['ok'=>false,'error'=>'curl_error','detail'=>'خطای اتصال به پنل: curl-'.$curlErr.' ('.$url.')'];
    if ($httpCode === 0) return ['ok'=>false,'error'=>'no_http_response','detail'=>'پاسخی از پنل دریافت نشد: '.$url];
    $tj = json_decode((string)$resp, true);
    $token = $tj['access_token'] ?? '';
    if ($token === '') {
        $errClass = redfox_remote_error_class($tj);
        return ['ok'=>false,'error'=>'no_token','detail'=>'توکن پنل دریافت نشد (HTTP '.$httpCode.'). نوع خطا: '.$errClass];
    }

    // ۲) کاربر
    $apiPath = $type === 'marzneshin' ? '/api/users/' : '/api/user/';
    $ch2 = curl_init($url . $apiPath . urlencode($panelUsername));
    if (!$ch2) return ['ok'=>false,'error'=>'curl_init_failed'];
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch2, 'panel');
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token]]);
    $userUrl = $url . $apiPath . urlencode($panelUsername);
    $policy = redfox_apply_panel_curl_url_policy($ch2, $userUrl);
    if (empty($policy['ok'])) { curl_close($ch2); return ['ok'=>false,'error'=>'panel_endpoint_blocked','detail'=>'آدرس کاربر پنل مسدود شده']; }
    $resp2 = curl_exec($ch2);
    $curlErr2 = curl_errno($ch2);
    $httpCode2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    if ($curlErr2 !== 0) return ['ok'=>false,'error'=>'curl_error','detail'=>'خطای دریافت کاربر: curl-'.$curlErr2];
    $uj = json_decode((string)$resp2, true);
    if (!is_array($uj)) return ['ok'=>false,'error'=>'bad_response','detail'=>'پاسخ پنل نامعتبر (HTTP '.$httpCode2.'): '.substr((string)$resp2,0,200)];

    $dataLimit = (int)($uj['data_limit'] ?? 0);
    $usedTraffic = (int)($uj['used_traffic'] ?? ($uj['lifetime_used_traffic'] ?? 0));
    $expireTs = (int)($uj['expire'] ?? 0);
    $status = (string)($uj['status'] ?? 'unknown');
    $enabled = (int)($uj['enabled'] ?? 1);
    $online = !empty($uj['online_at']) ? 1 : 0;
    $subUrl = (string)($uj['subscription_url'] ?? '');

    $now = date('Y-m-d H:i:s');
    try {
        $pdo->prepare("INSERT INTO rx_user_cache
            (user_id, invoice_id, username, panel_name, panel_type, expire_ts, used_traffic, data_limit, status, enabled, online, sub_url, synced_at)
            VALUES (:uid, :iid, :un, :pn, :pt, :et, :ut, :dl, :st, :en, :on, :su, :sa)
            ON DUPLICATE KEY UPDATE expire_ts=VALUES(expire_ts), used_traffic=VALUES(used_traffic),
            data_limit=VALUES(data_limit), status=VALUES(status), enabled=VALUES(enabled),
            online=VALUES(online), sub_url=VALUES(sub_url), synced_at=VALUES(synced_at)")
            ->execute([
                ':uid'=>(string)$row['id_user'], ':iid'=>(string)$row['id_invoice'],
                ':un'=>$panelUsername, ':pn'=>(string)($row['Service_location'] ?? ''),
                ':pt'=>$type, ':et'=>$expireTs, ':ut'=>$usedTraffic, ':dl'=>$dataLimit,
                ':st'=>$status, ':en'=>$enabled, ':on'=>$online, ':su'=>$subUrl, ':sa'=>$now,
            ]);
    } catch (Throwable $e) {
        error_log('[users sync] cache save: ' . redfox_exception_fingerprint($e));
    }
    return ['ok'=>true];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>کاربران | پنل رد فاکس</title>
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer></script>
<style>
.rx-svc-card{background:var(--surface-2,var(--surface-1));border:1px solid var(--border-soft);border-radius:10px;padding:10px;margin:4px 0;font-size:12px}
.rx-svc-bar{height:6px;border-radius:3px;background:var(--surface-1);overflow:hidden;margin:4px 0}
.rx-svc-bar>div{height:100%;border-radius:3px;transition:width .3s}
.rx-svc-bar>div.green{background:linear-gradient(90deg,#22c55e,#16a34a)}
.rx-svc-bar>div.yellow{background:linear-gradient(90deg,#f59e0b,#d97706)}
.rx-svc-bar>div.red{background:linear-gradient(90deg,#ef4444,#dc2626)}
.rx-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700}
.rx-pill.green{background:rgba(34,197,94,.15);color:#22c55e}
.rx-pill.yellow{background:rgba(245,158,11,.15);color:#f59e0b}
.rx-pill.red{background:rgba(239,68,68,.15);color:#ef4444}
.rx-pill.gray{background:var(--surface-1);color:var(--text-muted)}
.rx-copy-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border:none;border-radius:6px;background:var(--accent);color:var(--accent-fg);font-size:10px;cursor:pointer;font-weight:600;transition:.15s}
.rx-copy-btn:hover{opacity:.85}
.rx-copy-btn.ok{background:var(--color-success);color:#fff}
.rx-info-row{display:flex;justify-content:space-between;align-items:center;gap:6px;margin:2px 0}
.rx-info-row .k{color:var(--text-muted);font-size:10px;white-space:nowrap}
.rx-info-row .v{color:var(--text-main);font-weight:600;font-size:11px}
.rx-sync-btn{background:var(--accent);color:var(--accent-fg);border:none;padding:8px 18px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s}
.rx-sync-btn:hover{opacity:.88;transform:translateY(-1px)}
.rx-sync-btn:disabled{opacity:.5;cursor:wait;transform:none}
.rx-sync-wrap{margin-bottom:16px}
.rx-progress{display:none;margin-top:10px}
.rx-progress.active{display:block}
.rx-progress .bar{height:10px;background:var(--surface-3);border-radius:999px;overflow:hidden;border:1px solid var(--border-mid)}
.rx-progress .fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent-mid));width:0%;transition:width .3s}
.rx-sync-mini{padding:3px 8px;border:1px solid var(--border-mid);border-radius:6px;background:var(--surface-1);color:var(--text-main);font-size:10px;cursor:pointer}
.rx-sync-mini:hover{border-color:var(--accent)}
.rx-stale{color:var(--color-warning);font-size:9px}
</style>
</head>
<body>
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content"><div class="wrapper">
    <div class="page-head">
        <div>
            <div class="page-head__title"><?php echo icon('users', 'svg-icon svg-lg'); ?> لیست کاربران</div>
            <div class="page-head__sub">مدیریت کاربران — داده‌ی زنده از کش نمایش داده می‌شود</div>
        </div>
    </div>

    <!-- دکمه سینک گروهی -->
    <div class="rx-sync-wrap">
        <button class="rx-sync-btn" id="rxSyncAll" onclick="rxSyncAllUsers()">
            🔄 سینک همه کاربران از پنل
        </button>
        <span style="font-size:11px;color:var(--text-muted);margin-right:8px">داده‌های مصرف، انقضا و وضعیت همه‌ی کاربران از پنل VPN به‌روز می‌شود</span>
        <div class="rx-progress" id="rxSyncProgress">
            <div class="bar"><div class="fill" id="rxSyncFill"></div></div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:var(--text-muted)">
                <span><span id="rxSyncDone">۰</span> از <span id="rxSyncTotal">۰</span> سرویس</span>
                <span>✓ موفق: <b id="rxSyncOk">۰</b> &nbsp; ✗ ناموفق: <b id="rxSyncFail">۰</b></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table id="usersTable" class="display app-table" style="width:100%">
                <thead><tr>
                    <th>شناسه</th><th>کاربر</th><th>موجودی</th><th>خلاصه سرویس</th><th>وضعیت سرویس</th><th>عملیات</th>
                </tr></thead>
                <tbody>
                <?php foreach ($listusers as $list):
                    $statusClass = 'badge-active'; $statusText = 'فعال';
                    if (strtolower((string)$list['User_Status']) == 'block') { $statusClass = 'badge-block'; $statusText = 'مسدود'; }
                    $uid = (string)$list['id'];
                    $svc = $svcMap[$uid] ?? null;
                    $hasCache = $svc && !empty($svc['synced_at']);

                    // محاسبه از کش یا از فاکتور
                    $rxColor = 'gray'; $rxPillText = 'بدون سرویس';
                    $expireInfo = null; $usedGb = 0; $totalGb = 0; $usedPct = 0; $subUrl = '';
                    $liveStatus = ''; $liveEnabled = true;

                    if ($svc) {
                        if ($hasCache) {
                            $expireInfo = redfox_expire_display($svc['expire_ts'] ?? 0);
                            $totalGb = ($svc['data_limit'] ?? 0) > 0 ? round($svc['data_limit']/1073741824, 2) : -1;
                            $usedGb = round(($svc['used_traffic'] ?? 0)/1073741824, 2);
                            $usedPct = $totalGb > 0 ? min(100, round($usedGb/$totalGb*100,1)) : 0;
                            $subUrl = (string)($svc['live_sub'] ?? '');
                            $liveStatus = (string)($svc['live_status'] ?? '');
                            $liveEnabled = (int)($svc['live_enabled'] ?? 1) === 1;

                            // تعیین رنگ/وضعیت — انقضا اولویت دارد
                            if ($expireInfo['valid'] && $expireInfo['expired']) { $rxColor='red'; $rxPillText='🔴 منقضی'; }
                            elseif (!$liveEnabled) { $rxColor='red'; $rxPillText='⚫ غیرفعال'; }
                            elseif ($totalGb > 0 && $usedPct >= 90) { $rxColor='red'; $rxPillText='⚠️ حجم رو به اتمام'; }
                            elseif ($totalGb > 0 && $usedPct >= 70) { $rxColor='yellow'; $rxPillText='🟡 در حال مصرف'; }
                            else { $rxColor='green'; $rxPillText='🟢 فعال'; }
                        } else {
                            // کش موجود نیست — از فاکتور
                            $expireInfo = ['str'=>'—','days_left'=>-1,'valid'=>false];
                            $totalGb = (int)($svc['Volume'] ?? 0);
                            $rxPillText = '⚪ سینک نشده';
                        }
                    }
                ?>
                    <tr>
                        <td data-label="شناسه"><?php echo htmlspecialchars($uid); ?></td>
                        <td data-label="کاربر" style="direction:ltr;text-align:right">
                            <div><?php echo htmlspecialchars($list['username'] !== 'none' ? $list['username'] : '—', ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (!empty($list['tg_name'])): ?><small style="color:var(--text-muted)"><?php echo htmlspecialchars($list['tg_name'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                        </td>
                        <td data-label="موجودی"><?php echo number_format((int)$list['Balance']); ?> <small class="text-muted">ت</small></td>
                        <td data-label="خلاصه سرویس">
                            <?php if ($svc): ?>
                            <div class="rx-svc-card" id="svc-<?= htmlspecialchars((string)$svc['id_invoice']) ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                                    <b style="font-size:11px"><?php echo htmlspecialchars((string)($svc['name_product'] ?? 'سرویس'), ENT_QUOTES, 'UTF-8'); ?></b>
                                    <span class="rx-pill <?= $rxColor ?>"><?= $rxPillText ?></span>
                                </div>
                                <?php if ($hasCache): ?>
                                    <?php if ($totalGb > 0): ?>
                                    <div class="rx-info-row"><span class="k">📊 حجم</span><span class="v"><?= $usedGb ?> / <?= $totalGb ?> GB</span></div>
                                    <div class="rx-svc-bar"><div class="<?= $rxColor ?>" style="width:<?= $usedPct ?>%"></div></div>
                                    <?php else: ?>
                                    <div class="rx-info-row"><span class="k">📊 حجم</span><span class="v">∞ نامحدود</span></div>
                                    <?php endif; ?>
                                    <div class="rx-info-row">
                                        <span class="k">⏰ انقضا</span>
                                        <span class="v"><?= htmlspecialchars($expireInfo['str']) ?><?= $expireInfo['valid'] && $expireInfo['days_left'] >= 0 ? " ({$expireInfo['days_left']} روز)" : '' ?></span>
                                    </div>
                                    <div class="rx-info-row">
                                        <span class="k">🔗 اتصال</span>
                                        <?php
                                            // نمایش آنلاین فقط اگر سرویس منقضی/غیرفعال نیست
                                            $showOnline = $hasCache && !($expireInfo['valid'] && $expireInfo['expired']) && $liveEnabled;
                                        ?>
                                        <span class="v"><?= $showOnline ? ((int)$svc['live_online'] ? '🟢 آنلاین' : '⚪ آفلاین') : '⚪ آفلاین' ?></span>
                                    </div>
                                    <?php if ($subUrl): ?>
                                    <button class="rx-copy-btn" data-sub="<?= htmlspecialchars($subUrl, ENT_QUOTES) ?>" onclick="rxCopySub(this)">📋 کپی ساب</button>
                                    <?php endif; ?>
                                    <button class="rx-sync-mini" onclick="rxSyncOne('<?= htmlspecialchars((string)$svc['id_invoice']) ?>', this)">🔄 سینک</button>
                                    <div class="rx-stale">آخرین سینک: <?= htmlspecialchars((string)($svc['synced_at'] ?? '—')) ?></div>
                                <?php else: ?>
                                    <div class="rx-info-row"><span class="k">📦 حجم</span><span class="v"><?= $totalGb ?: '—' ?> GB</span></div>
                                    <div class="rx-info-row"><span class="k">📅 مدت</span><span class="v"><?= (int)($svc['Service_time'] ?? 0) ?: '—' ?> روز</span></div>
                                    <button class="rx-sync-mini" onclick="rxSyncOne('<?= htmlspecialchars((string)$svc['id_invoice']) ?>', this)">🔄 سینک از پنل</button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="rx-pill gray">— بدون سرویس —</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="وضعیت"><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td data-label="عملیات" class="cell-actions">
                            <a href="user.php?id=<?= urlencode($uid) ?>" class="btn btn-sm btn-primary">👤 مدیریت</a>
                            <?php if ($svc): ?>
                            <a href="user_service.php?uid=<?= urlencode($uid) ?>&iid=<?= urlencode((string)$svc['id_invoice']) ?>" class="btn btn-sm btn-soft-purple" style="font-size:11px;margin-top:3px">🔌 سرویس</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div></section>
</section>

<script>
function pn(n){ return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[+d];}); }

function rxCopySub(btn) {
    var sub = btn.getAttribute('data-sub');
    navigator.clipboard.writeText(sub).then(function(){btn.classList.add('ok');btn.textContent='✓ کپی شد';setTimeout(function(){btn.classList.remove('ok');btn.textContent='📋 کپی ساب';},2000);}).catch(function(){var ta=document.createElement('textarea');ta.value=sub;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');btn.classList.add('ok');btn.textContent='✓ کپی شد';setTimeout(function(){btn.classList.remove('ok');btn.textContent='📋 کپی ساب';},2000);}catch(e){}document.body.removeChild(ta);});
}

// ── سینک گروهی همه کاربران ──
function rxSyncAllUsers() {
    var btn = document.getElementById('rxSyncAll');
    var prog = document.getElementById('rxSyncProgress');
    if (!confirm('سینک همه کاربران از پنل انجام شود؟ این عملیات ممکن است چند دقیقه طول بکشد.')) return;
    btn.disabled = true; btn.textContent = '⏳ در حال سینک...';
    prog.classList.add('active');

    // گرفتن تعداد کل
    fetch('users.php?ajax=sync_count', {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(j){
            if(!j.ok) throw new Error(j.error||'count failed');
            var total = j.count;
            document.getElementById('rxSyncTotal').textContent = pn(total);
            document.getElementById('rxSyncDone').textContent = pn(0);
            document.getElementById('rxSyncOk').textContent = pn(0);
            document.getElementById('rxSyncFail').textContent = pn(0);
            if(total === 0){ btn.textContent='✓ سروisi برای سینک یافت نشد'; setTimeout(function(){btn.disabled=false;btn.textContent='🔄 سینک همه کاربران از پنل';prog.classList.remove('active');},2000); return; }
            runBatchSync(0, total, 0, 0);
        })
        .catch(function(err){ alert('خطا: '+err.message); resetSyncBtn(); });
}

function runBatchSync(offset, total, okCount, failCount) {
    var syncData = new FormData();
    syncData.append('ajax', 'sync_batch');
    syncData.append('offset', String(offset));
    syncData.append('batch', '3');
    fetch('users.php', {method:'POST', body:syncData, credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(j){
            if(!j.ok) throw new Error(j.error||'batch failed');
            okCount += j.synced; failCount += j.failed;
            offset += j.batch_count;
            var pct = total > 0 ? Math.min(100, Math.round(offset/total*100)) : 100;
            document.getElementById('rxSyncFill').style.width = pct+'%';
            document.getElementById('rxSyncDone').textContent = pn(offset);
            document.getElementById('rxSyncOk').textContent = pn(okCount);
            document.getElementById('rxSyncFail').textContent = pn(failCount);
            if(!j.done && offset < total){
                setTimeout(function(){ runBatchSync(offset, total, okCount, failCount); }, 300);
            } else {
                var btn = document.getElementById('rxSyncAll');
                btn.textContent = '✓ سینک کامل شد';
                var errTxt = '';
                if (j.errors) {
                    var parts = [];
                    for (var k in j.errors) parts.push(k + ' (' + j.errors[k] + 'x)');
                    errTxt = '\nخطاها: ' + parts.join(', ');
                }
                alert('سینک کامل شد!\nموفق: '+okCount+'\nناموفق: '+failCount+errTxt+'\n\nبرای دیدن داده‌های به‌روز، صفحه را رفرش کنید.');
                setTimeout(function(){ location.reload(); }, 1500);
            }
        })
        .catch(function(err){ alert('خطا در سینک: '+err.message); resetSyncBtn(); });
}

function resetSyncBtn() {
    var btn = document.getElementById('rxSyncAll');
    btn.disabled = false; btn.textContent = '🔄 سینک همه کاربران از پنل';
    document.getElementById('rxSyncProgress').classList.remove('active');
}

// ── سینک یک کاربر ──
function rxSyncOne(invoiceId, btn) {
    btn.disabled = true; var orig = btn.textContent; btn.textContent = '⏳...';
    var syncData = new FormData();
    syncData.append('ajax', 'sync_one');
    syncData.append('iid', String(invoiceId));
    fetch('users.php', {method:'POST', body:syncData, credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(j){
            if(j.ok){
                btn.textContent='✓'; btn.style.color='var(--color-success)';
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                btn.textContent='✗'; btn.style.color='var(--color-danger)';
                var errMsg = j.error || 'نامشخص';
                if (j.detail) errMsg += '\n' + j.detail;
                alert('خطا در سینک: ' + errMsg);
                setTimeout(function(){ btn.disabled=false; btn.textContent=orig; btn.style.color=''; }, 2000);
            }
        })
        .catch(function(err){ btn.textContent='✗'; alert('خطا: '+err.message); setTimeout(function(){btn.disabled=false;btn.textContent=orig;},2000); });
}
</script>
<script src="js/datatable.js" defer></script>
<script>document.addEventListener("DOMContentLoaded",function(){RedFoxDT.init("#usersTable");});</script>
</body></html>
