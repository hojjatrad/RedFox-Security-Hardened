<?php


if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
}

// Panel version (read from the project root `version` file). Displayed in the
// sidebar footer on every page. Always shown with a leading "v".
$__panelVersionRaw = trim((string)@file_get_contents(__DIR__ . '/../version'));
if ($__panelVersionRaw === '') $__panelVersionRaw = '2.4.12';
$__panelVersion = (stripos($__panelVersionRaw, 'v') === 0) ? $__panelVersionRaw : ('v' . $__panelVersionRaw);
// Header rendering is read-only. Notification synchronization, marking,
// reseller-bot repair, and update discovery must not run as GET side effects.
$__noticeCount = 0;
$__notices = [];
$__noticeCounts = [];
if (isset($pdo) && $pdo instanceof PDO && !empty($_SESSION['user'])) {
    try {
        require_once dirname(__DIR__) . '/lib/AdminNotifications.php';
        $__ns = new RedFoxAdminNotifications($pdo);
        $__noticeCount = $__ns->count();
        $__notices = $__ns->unread(8);
        $__noticeCounts = $__ns->counts();
    } catch (Throwable $__ne) {
        error_log('[header notifications] ' . redfox_exception_fingerprint($__ne));
    }
}
$__newUpdateVersion = '';
$__newUpdateSource = '';
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $__ur = $pdo->query('SELECT last_version,last_source FROM update_sources WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $__uv = (string)($__ur['last_version'] ?? '');
        if ($__uv !== '' && version_compare(ltrim($__uv, 'vV'), ltrim($__panelVersionRaw, 'vV'), '>')) {
            $__newUpdateVersion = $__uv;
            $__newUpdateSource = (string)($__ur['last_source'] ?? '');
        }
    } catch (Throwable $e) {}
}

if (isset($_SESSION["user"])) {
    $__can_check = false;
    $__ip_list   = [];
    $__iplogin_unlimited = false;
    if (isset($pdo) && $pdo instanceof PDO) {
        $__can_check   = true;
        $__stmt_ip     = $pdo->query("SELECT iplogin FROM setting LIMIT 1");
        $__raw_iplogin = $__stmt_ip ? (string)$__stmt_ip->fetchColumn() : '';
        if ($__raw_iplogin === '*' || $__raw_iplogin === 'all' || $__raw_iplogin === 'unlimited') {
            $__iplogin_unlimited = true;
        } elseif ($__raw_iplogin !== '' && $__raw_iplogin !== '0') {
            $__decoded = json_decode($__raw_iplogin, true);
            if (is_array($__decoded)) {
                if (in_array('*', $__decoded, true) || in_array('all', $__decoded, true) || in_array('unlimited', $__decoded, true)) {
                    $__iplogin_unlimited = true;
                } else {
                    $__ip_list = $__decoded;
                }
            } elseif (filter_var($__raw_iplogin, FILTER_VALIDATE_IP)) {
                $__ip_list = [$__raw_iplogin];
            }
        }
    }
    if ($__can_check) {
        $__current_ip = redfox_client_ip();
        $__allowed    = $__iplogin_unlimited || (!empty($__ip_list) && in_array($__current_ip, $__ip_list, true));
        if (!$__allowed) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        unset($__current_ip, $__allowed);
    }
    unset($__can_check, $__ip_list, $__raw_iplogin, $__decoded, $__stmt_ip, $__iplogin_unlimited);
}


if (!function_exists('icon')) {
    $__iconsLib = __DIR__ . '/lib/icons.php';
    if (is_file($__iconsLib) && is_readable($__iconsLib)) {
        @include_once $__iconsLib;
    }
}
if (!function_exists('icon')) {
    function icon(string $name, string $class = 'svg-icon'): string {
        static $paths = [
            'bars'        => '<line x1="4" y1="6"  x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
            'robot'       => '<rect x="3" y="11" width="18" height="10" rx="2" ry="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8"  y1="16" x2="8.01" y2="16"/><line x1="16" y1="16" x2="16.01" y2="16"/>',
            'chevron-down'=> '<polyline points="6 9 12 15 18 9"/>',
            'moon'        => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'arrow-right-from-bracket' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'home'        => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'dollar-sign' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'package'     => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            'grid'        => '<rect x="3"  y="3"  width="7" height="7"/><rect x="14" y="3"  width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3"  y="14" width="7" height="7"/>',
            'wallet'      => '<rect x="2" y="6" width="20" height="14" rx="2"/><polyline points="22 12 18 12 18 16 22 16"/><path d="M2 10V6a2 2 0 0 1 2-2h14"/>',
            'ban'         => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
            'keyboard'    => '<rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="6"  y1="8" x2="6.01" y2="8"/><line x1="10" y1="8" x2="10.01" y2="8"/><line x1="14" y1="8" x2="14.01" y2="8"/><line x1="18" y1="8" x2="18.01" y2="8"/><line x1="7"  y1="16" x2="17" y2="16"/>',
        ];
        $p = $paths[$name] ?? '<circle cx="12" cy="12" r="3"/>';
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}

$__user    = isset($_SESSION["user"]) ? htmlspecialchars($_SESSION["user"], ENT_QUOTES, 'UTF-8') : 'admin';
$__current = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$__avatar  = function_exists('mb_substr') ? mb_substr($__user, 0, 1, 'UTF-8') : substr($__user, 0, 1);


$__schemaLib = __DIR__ . '/lib/schema.php';
if (is_file($__schemaLib) && is_readable($__schemaLib)) {
    @include_once $__schemaLib;
    if (function_exists('redfox_schema_ready') && isset($pdo) && $pdo instanceof PDO) {
        try { redfox_schema_ready($pdo); } catch (\Throwable $e) { error_log('[header] schema_ready failed: ' . redfox_exception_fingerprint($e)); }
    }
}
?>

<script>
(function () {
    try {


        var color = localStorage.getItem('redfox_color') || 'blue';
        var html = document.documentElement;
        var s = html.style;
        var PRESET = { red:'#ef4444', blue:'#3b82f6', purple:'#a855f7', yellow:'#facc15', orange:'#f97316', green:'#22c55e' };
        function fg(r,g,b){ function lin(v){ v/=255; return v<=0.03928 ? v/12.92 : Math.pow((v+0.055)/1.055,2.4); } var L=0.2126*lin(r)+0.7152*lin(g)+0.0722*lin(b); return L>0.45?'#14121d':'#ffffff'; }
        var hex;
        if (PRESET[color]) { hex = PRESET[color]; html.setAttribute('data-color', color); }
        else {
            var m=/^#?([0-9a-f]{6})$/i.exec(color);
            if (m) { hex='#'+m[1].toLowerCase(); var n=parseInt(m[1],16),r=(n>>16)&255,g=(n>>8)&255,b=n&255;
                s.setProperty('--accent',hex); s.setProperty('--accent-soft','rgba('+r+','+g+','+b+',0.15)');
                s.setProperty('--accent-mid','rgba('+r+','+g+','+b+',0.35)'); s.setProperty('--accent-glow','rgba('+r+','+g+','+b+',0.5)');
                html.setAttribute('data-color','custom'); }
            else { hex=PRESET.blue; html.setAttribute('data-color','blue'); }
        }
        var pn=parseInt(hex.slice(1),16); s.setProperty('--accent-fg', fg((pn>>16)&255,(pn>>8)&255,pn&255));
        html.setAttribute('data-theme','dark');
    } catch (e) {  }
})();
</script>

<header class="app-header">
    <div class="app-header__left">
        <button class="btn-icon" id="sidebar-toggle" aria-label="منو">
            <?php echo icon('bars'); ?>
        </button>
        <a href="index.php" class="app-logo">
            <span class="app-logo__mark"><img src="img/redfox.png" alt="Red Fox" style="width:28px;height:28px;border-radius:6px;object-fit:cover"></span>
            ربات&nbsp;<span>رد فاکس</span>
        </a>
        <span class="app-status-pill">پنل آنلاین — اتصال برقرار</span>
        <?php if($__newUpdateVersion!==''): ?><a href="update.php" style="background:#f59e0b;color:#111827;padding:6px 10px;border-radius:9px;font-size:11px;font-weight:800;text-decoration:none">نسخه <?=htmlspecialchars($__newUpdateVersion)?> از <?=htmlspecialchars($__newUpdateSource?:'منبع بروزرسانی')?> موجود است — نصب</a><?php endif; ?>
    </div>

    <div class="rx-notice-wrap" style="position:relative;margin-right:auto;margin-left:12px"><button type="button" id="rxNoticeBtn" class="btn-icon" aria-label="اعلان‌ها" onclick="document.getElementById('rxNoticeMenu').classList.toggle('open')" style="position:relative">🔔<span id="rxNoticeCount" style="<?=$__noticeCount?'':'display:none;'?>position:absolute;top:-5px;left:-6px;background:#ef4444;color:#fff;border-radius:99px;min-width:19px;height:19px;line-height:19px;font-size:10px;font-weight:800"><?=$__noticeCount?></span></button><div id="rxNoticeMenu" style="display:none;position:absolute;left:0;top:44px;width:min(390px,90vw);background:var(--surface-1);border:1px solid var(--border-mid);border-radius:14px;box-shadow:0 22px 60px rgba(0,0,0,.45);padding:10px;z-index:9999"><div style="display:flex;justify-content:space-between;align-items:center;padding:6px 4px 10px"><b>اعلان‌های جدید</b><a href="notifications.php" style="font-size:11px">مشاهده همه</a></div><?php if(!$__notices):?><p class="text-muted" style="padding:12px">اعلان خوانده‌نشده‌ای وجود ندارد.</p><?php else:foreach($__notices as$__n):?><form method="post" action="notifications.php" style="display:block;margin:0"><input type="hidden" name="rx_csrf_token" value="<?=htmlspecialchars(rx_csrf_token(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="open"><input type="hidden" name="id" value="<?=(int)$__n['id']?>"><button type="submit" style="display:block;width:100%;padding:9px;border:0;border-top:1px solid var(--border-soft);background:transparent;text-align:right;cursor:pointer;color:var(--text-main)"><b style="display:block;font-size:12px"><?=htmlspecialchars($__n['title'])?></b><small style="color:var(--text-muted)"><?=htmlspecialchars(mb_strimwidth((string)($__n['body']??''),0,90,'…','UTF-8'))?></small></button></form><?php endforeach;endif;?></div></div><style>#rxNoticeMenu.open{display:block!important}.rx-nav-count{margin-right:auto;background:#ef4444;color:#fff;border-radius:99px;padding:1px 6px;font-size:9px;font-weight:800}</style>
    <div class="profile-wrap">
        <button class="profile-trigger" type="button" aria-label="حساب کاربری">
            
            <span class="profile-avatar"
                  style="background:transparent; border-color:rgba(255,255,255,0.18); border-radius:10px; overflow:hidden; padding:0;"
                  title="رد فاکس">
                <img src="img/redfox.png"
                     alt="Red Fox"
                     loading="lazy"
                     width="36" height="36"
                     style="width:100%; height:100%; object-fit:cover; display:block; border:0;">
            </span>
            <span class="profile-info">
                <b>حساب کاربری</b>
                <small><?php echo $__user; ?></small>
            </span>
            <?php echo icon('chevron-down', 'svg-icon svg-xs'); ?>
        </button>

        <div class="profile-menu">
            <div class="profile-menu__head">
                <b><?php echo $__user; ?></b>
                <small>مدیر کل</small>
            </div>

            <a href="appearance.php" class="menu-item">
                <svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>تنظیمات</span>
            </a>

            <hr>

            <form method="post" action="logout.php" style="margin:0">
                <?= redfox_csrf_field() ?>
                <button type="submit" class="menu-danger" style="width:100%;border:0;background:transparent;cursor:pointer;text-align:right">
                    <?php echo icon('arrow-right-from-bracket', 'svg-icon svg-sm'); ?>
                    <span>خروج از حساب</span>
                </button>
            </form>
        </div>
    </div>
</header>

<aside class="app-sidebar">
<style>
/* Red Fox — منوی درختی حرفه‌ای */
.rx-nav-group{margin-bottom:2px}
.rx-nav-group>summary{cursor:pointer;padding:11px 14px;font-size:13px;font-weight:700;color:var(--text-muted);list-style:none;display:flex;align-items:center;gap:8px;border-radius:10px;transition:background .15s}
.rx-nav-group>summary::-webkit-details-marker{display:none}
.rx-nav-group>summary:hover{background:var(--surface-1);color:var(--text-main)}
.rx-nav-group[open]>summary{color:var(--text-main)}
.rx-nav-group>summary .rx-chev{margin-right:auto;font-size:10px;opacity:.5;transition:transform .2s}
.rx-nav-group[open]>summary .rx-chev{transform:rotate(-90deg)}
.rx-nav-sub{list-style:none;padding:0;margin:0}
.rx-nav-sub li a{display:flex;align-items:center;gap:8px;padding:8px 14px 8px 30px;font-size:13px;color:var(--text-muted);border-radius:8px;margin:1px 0;transition:all .12s}
.rx-nav-sub li a:hover{color:var(--text-main);background:var(--surface-1)}
.rx-nav-sub li a.rx-active{color:var(--accent);background:var(--accent-soft);font-weight:600}
.rx-nav-sub li a .menu-symbol{width:18px;height:18px;flex-shrink:0;opacity:.8}
.rx-nav-sub li a.rx-active .menu-symbol{opacity:1}
</style>
<?php
// Helper: آیا صفحه‌ی فعلی در این دسته است؟
function rx_nav_open(...$pages) {
    global $__current;
    foreach ($pages as $p) { if ($__current === strtolower($p)) return 'open'; }
    return '';
}
?>
    <ul class="sidebar-menu">
        <li><a href="index.php"><span class="menu-symbol"><?php echo icon('home','svg-icon svg-sm'); ?></span><span>داشبورد</span></a></li>
        <li><a href="update.php" class="<?= $__current==='update.php'?'rx-active':'' ?>" style="border:1px solid var(--accent-mid);background:var(--accent-soft)"><span class="menu-symbol">⬆️</span><span>مرکز بروزرسانی</span><?php if($__newUpdateVersion!==''):?><span class="rx-nav-count" style="background:#f59e0b;color:#111">جدید</span><?php endif;?></a></li>
    </ul>

    <details class="rx-nav-group" <?= rx_nav_open('users.php','agents.php','reseller_report.php','reseller_permissions.php','invoice.php','service.php','service_operations.php','payment_effects.php','reseller_security.php','reseller_settlements.php','cancelService.php') ?>>
        <summary>👥 کاربران و فروش <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="users.php" class="<?= $__current==='users.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>کاربران</span></a></li>
            <li><a href="agents.php" class="<?= $__current==='agents.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>مدیریت نمایندگان</span><?php if(!empty($__noticeCounts['agent_request'])):?><span class="rx-nav-count"><?=$__noticeCounts['agent_request']?></span><?php endif;?></a></li>
            <li><a href="reseller_report.php" class="<?= $__current==='reseller_report.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>گزارش نماینده</span></a></li>
            <li><a href="reseller_permissions.php" class="<?= $__current==='reseller_permissions.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>دسترسی سوپر نماینده</span></a></li>
            <li><a href="invoice.php" class="<?= $__current==='invoice.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('dollar-sign','svg-icon svg-sm'); ?></span><span>سفارشات</span></a></li>
            <li><a href="service.php" class="<?= $__current==='service.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>سرویس‌ها</span></a></li>
            <li><a href="service_operations.php" class="<?= $__current==='service_operations.php'?'rx-active':'' ?>"><span class="menu-symbol">🔄</span><span>عملیات نمایندگان</span><?php if(!empty($__noticeCounts['service_reconcile'])):?><span class="rx-nav-count"><?=$__noticeCounts['service_reconcile']?></span><?php endif;?></a></li>
            <li><a href="payment_effects.php" class="<?= $__current==='payment_effects.php'?'rx-active':'' ?>"><span class="menu-symbol">💳</span><span>تطبیق پرداخت</span><?php if(!empty($__noticeCounts['payment_reconcile'])):?><span class="rx-nav-count"><?=$__noticeCounts['payment_reconcile']?></span><?php endif;?></a></li>
            <li><a href="reseller_security.php" class="<?= $__current==='reseller_security.php'?'rx-active':'' ?>"><span class="menu-symbol">🛡</span><span>پیام و API نمایندگان</span><?php if(!empty($__noticeCounts['message_reconcile'])):?><span class="rx-nav-count"><?=$__noticeCounts['message_reconcile']?></span><?php endif;?></a></li>
            <li><a href="reseller_settlements.php" class="<?= $__current==='reseller_settlements.php'?'rx-active':'' ?>"><span class="menu-symbol">🏦</span><span>تسویه نمایندگان</span><?php if(!empty($__noticeCounts['deposit'])):?><span class="rx-nav-count"><?=$__noticeCounts['deposit']?></span><?php endif;?></a></li>
            <li><a href="cancelService.php" class="<?= $__current==='cancelservice.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('list','svg-icon svg-sm'); ?></span><span>درخواست‌ها</span><?php if(!empty($__noticeCounts['cancel_service'])):?><span class="rx-nav-count"><?=$__noticeCounts['cancel_service']?></span><?php endif;?></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('product.php','categories.php','panels.php','panel_pricing.php','stock.php','discounts.php') ?>>
        <summary>🛒 محصولات و پنل‌ها <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="product.php" class="<?= $__current==='product.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>محصولات</span></a></li>
            <li><a href="categories.php" class="<?= $__current==='categories.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>دسته‌بندی‌ها</span></a></li>
            <li><a href="panels.php" class="<?= $__current==='panels.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('server','svg-icon svg-sm'); ?></span><span>پنل‌های VPN</span></a></li>
            <li><a href="panel_pricing.php" class="<?= $__current==='panel_pricing.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>قیمت‌گذاری پنل‌ها</span></a></li>
            <li><a href="stock.php" class="<?= $__current==='stock.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>انبار</span></a></li>
            <li><a href="discounts.php" class="<?= $__current==='discounts.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('ticket','svg-icon svg-sm'); ?></span><span>کدهای تخفیف</span></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('payment.php','finance.php','gateways.php','cashback.php','cards.php','affiliate_settings.php') ?>>
        <summary>💳 مالی و درگاه‌ها <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="payment.php" class="<?= $__current==='payment.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('wallet','svg-icon svg-sm'); ?></span><span>تراکنش‌ها</span><?php if(!empty($__noticeCounts['receipt'])):?><span class="rx-nav-count"><?=$__noticeCounts['receipt']?></span><?php endif;?></a></li>
            <li><a href="finance.php" class="<?= $__current==='finance.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('wallet','svg-icon svg-sm'); ?></span><span>تنظیمات درگاه‌ها</span></a></li>
            <li><a href="gateways.php" class="<?= $__current==='gateways.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('wallet','svg-icon svg-sm'); ?></span><span>کلیدها و ولت</span></a></li>
            <li><a href="cashback.php" class="<?= $__current==='cashback.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('wallet','svg-icon svg-sm'); ?></span><span>کش‌بک</span></a></li>
            <li><a href="cards.php" class="<?= $__current==='cards.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('wallet','svg-icon svg-sm'); ?></span><span>کارت‌های بانکی</span></a></li>
            <li><a href="affiliate_settings.php" class="<?= $__current==='affiliate_settings.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>زیرمجموعه‌گیری</span></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('support.php','support_inbox.php','help.php','apps.php','ai_support.php','ai_knowledge.php','reseller_ai.php') ?>>
        <summary>📞 پشتیبانی و آموزش <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="support_inbox.php" class="<?= $__current==='support_inbox.php'?'rx-active':'' ?>"><span class="menu-symbol">🎟</span><span>صندوق پیام‌های پشتیبانی</span><?php if(!empty($__noticeCounts['support'])):?><span class="rx-nav-count"><?=$__noticeCounts['support']?></span><?php endif;?></a></li>
            <li><a href="support.php" class="<?= $__current==='support.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>دپارتمان‌ها</span></a></li>
            <li><a href="help.php" class="<?= $__current==='help.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>آموزش‌ها</span></a></li>
            <li><a href="apps.php" class="<?= $__current==='apps.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>نرم‌افزارها</span></a></li>
            <li><a href="ai_support.php" class="<?= $__current==='ai_support.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('robot','svg-icon svg-sm'); ?></span><span>هوش مصنوعی</span></a></li>
            <li><a href="reseller_ai.php" class="<?= $__current==='reseller_ai.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('robot','svg-icon svg-sm'); ?></span><span>هوش مصنوعی نماینده‌ها</span></a></li>
            <li><a href="ai_knowledge.php" class="<?= $__current==='ai_knowledge.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>پایگاه دانش</span></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('stats.php','analytics.php','cohort.php','campaigns.php','lottery.php','wheel.php','broadcast.php') ?>>
        <summary>📊 گزارش و بازاریابی <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="stats.php" class="<?= $__current==='stats.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>آمار و گزارش‌ها</span></a></li>
            <li><a href="analytics.php" class="<?= $__current==='analytics.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('chart-line','svg-icon svg-sm'); ?></span><span>داشبورد تحلیلی</span></a></li>
            <li><a href="cohort.php" class="<?= $__current==='cohort.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('users','svg-icon svg-sm'); ?></span><span>تحلیل گروهی</span></a></li>
            <li><a href="lottery.php" class="<?= $__current==='lottery.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('ticket','svg-icon svg-sm'); ?></span><span>قرعه‌کشی</span></a></li>
            <li><a href="wheel.php" class="<?= $__current==='wheel.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('ticket','svg-icon svg-sm'); ?></span><span>گردونه شانس</span></a></li>
            <li><a href="broadcast.php" class="<?= $__current==='broadcast.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>پیام همگانی</span></a></li>
            <li><a href="campaigns.php" class="<?= $__current==='campaigns.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('dollar-sign','svg-icon svg-sm'); ?></span><span>مرکز کمپین‌ها</span></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('appearance.php','textbot.php','message_templates.php','keyboard.php','service_keyboard.php','qr_background.php','premium_emojis.php','applinks.php') ?>>
        <summary>🎨 ظاهر و برند <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="appearance.php" class="<?= $__current==='appearance.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('palette','svg-icon svg-sm'); ?></span><span>رنگ و ظاهر</span></a></li>
            <li><a href="textbot.php" class="<?= $__current==='textbot.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>متن‌های ربات</span></a></li>
            <li><a href="message_templates.php" class="<?= $__current==='message_templates.php'?'rx-active':'' ?>"><span class="menu-symbol">🧾</span><span>قالب گزارش‌های نماینده</span></a></li>
            <li><a href="keyboard.php" class="<?= $__current==='keyboard.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('keyboard','svg-icon svg-sm'); ?></span><span>چیدمان کیبورد</span></a></li>
            <li><a href="service_keyboard.php" class="<?= $__current==='service_keyboard.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('palette','svg-icon svg-sm'); ?></span><span>رنگ‌بندی دکمه‌ها</span></a></li>
            <li><a href="qr_background.php" class="<?= $__current==='qr_background.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>پس‌زمینه QR</span></a></li>
            <li><a href="premium_emojis.php" class="<?= $__current==='premium_emojis.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>ایموجی پرمیوم</span></a></li>
            <li><a href="applinks.php" class="<?= $__current==='applinks.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>لینک اپ</span></a></li>
        </ul>
    </details>

    <details class="rx-nav-group" <?= rx_nav_open('settings.php','shopsettings.php','bot_settings.php','backup.php','iplogin.php','channels.php','maintenance.php','restore.php','backup_export.php','license.php','update.php','audit_log.php','health_monitor.php','operations.php','migrations.php','hosting_setup.php','certification.php') ?>>
        <summary>🔧 سیستم <span class="rx-chev">◀</span></summary>
        <ul class="rx-nav-sub">
            <li><a href="settings.php" class="<?= $__current==='settings.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>تنظیمات عمومی</span></a></li>
            <li><a href="bot_settings.php" class="<?= $__current==='bot_settings.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('robot','svg-icon svg-sm'); ?></span><span>تنظیمات ربات (توکن)</span></a></li>
            <li><a href="shopsettings.php" class="<?= $__current==='shopsettings.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('grid','svg-icon svg-sm'); ?></span><span>تنظیمات فروشگاه</span></a></li>
            <li><a href="backup.php" class="<?= $__current==='backup.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>بکاپ و بازیابی</span></a></li>
            <li><a href="iplogin.php" class="<?= $__current==='iplogin.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('server','svg-icon svg-sm'); ?></span><span>آی‌پی ورود</span></a></li>
            <li><a href="channels.php" class="<?= $__current==='channels.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>کانال‌های جوین</span></a></li>
            <li><a href="maintenance.php" class="<?= $__current==='maintenance.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('server','svg-icon svg-sm'); ?></span><span>نگهداری سیستم</span></a></li>
            <li><a href="license.php" class="<?= $__current==='license.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('ban','svg-icon svg-sm'); ?></span><span>لایسنس و انقضا</span></a></li>
            <li><a href="update.php" class="<?= $__current==='update.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('package','svg-icon svg-sm'); ?></span><span>بروزرسانی</span></a></li>
            <li><a href="audit_log.php" class="<?= $__current==='audit_log.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>لاگ ممیزی</span></a></li>
            <li><a href="health_monitor.php" class="<?= $__current==='health_monitor.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('server','svg-icon svg-sm'); ?></span><span>مانیتور سلامت</span></a></li>
            <li><a href="operations.php" class="<?= $__current==='operations.php'?'rx-active':'' ?>"><span class="menu-symbol">🩺</span><span>مرکز عملیات</span></a></li>
            <li><a href="migrations.php" class="<?= $__current==='migrations.php'?'rx-active':'' ?>"><span class="menu-symbol">🗄</span><span>مهاجرت دیتابیس</span></a></li>
            <li><a href="hosting_setup.php" class="<?= $__current==='hosting_setup.php'?'rx-active':'' ?>"><span class="menu-symbol">🧰</span><span>Wizard هاست</span></a></li>
            <li><a href="certification.php" class="<?= $__current==='certification.php'?'rx-active':'' ?>"><span class="menu-symbol">✅</span><span>Pre-Certification</span></a></li>
            <li><a href="about_text.php" class="<?= $__current==='about_text.php'?'rx-active':'' ?>"><span class="menu-symbol"><?php echo icon('text','svg-icon svg-sm'); ?></span><span>متون پیام‌های ربات</span></a></li>
        </ul>
    </details>

<script>
// حفظ حالت باز/بسته‌ی دسته‌های منو در localStorage
(function(){
    var groups = document.querySelectorAll('.rx-nav-group');
    groups.forEach(function(g, i){
        var key = 'rx_nav_' + i;
        // PHP ممکن است خودکار open کرده باشد — اگر کاربر قبلاً بسته، احترام بگذار
        var saved = localStorage.getItem(key);
        if (saved === 'closed' && !g.querySelector('.rx-active')) g.open = false;
        if (saved === 'open') g.open = true;
        g.addEventListener('toggle', function(){
            localStorage.setItem(key, g.open ? 'open' : 'closed');
        });
    });
})();
</script>

    <div class="sidebar-version" style="margin-top:auto; padding:14px 16px; border-top:1px solid var(--border-soft,#2a2a35); display:flex; align-items:center; gap:8px; color:var(--text-muted,#8a8a9a); font-size:12px;">
        <span class="menu-symbol"><img src="img/redfox.png" alt="" style="width:18px;height:18px;border-radius:4px;object-fit:cover"></span>
        <span>نسخه</span>
        <span class="badge badge-info" style="direction:ltr; font-family:'JetBrains Mono',monospace;"><?php echo htmlspecialchars($__panelVersion, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
</aside>

<div class="sidebar-overlay"></div><script src="js/persian-date.js" defer></script>

<!-- Red Fox: CSRF auto-inject into all forms -->
<script>
(function() {
    var RX_CSRF_TOKEN = "<?php echo rx_csrf_token(); ?>";
    if (!RX_CSRF_TOKEN) return;
    function injectCSRF() {
        document.querySelectorAll('form').forEach(function(form) {
            if (form.querySelector('input[name="rx_csrf_token"]')) return;
            // فقط فرم‌های POST
            var method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'post') return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'rx_csrf_token';
            input.value = RX_CSRF_TOKEN;
            form.appendChild(input);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCSRF);
    } else {
        injectCSRF();
    }
    // MutationObserver برای فرم‌های دینامیک
    if (window.MutationObserver) {
        new MutationObserver(function() { injectCSRF(); }).observe(document.body, {childList: true, subtree: true});
    }
    // Attach the token to every same-origin state-changing fetch, including
    // relative panel URLs. Preserve Headers objects instead of treating them
    // as plain dictionaries.
    var origFetch = window.fetch;
    if (origFetch) {
        window.fetch = function(url, opts) {
            opts = opts || {};
            var method = String(opts.method || (url && url.method) || 'GET').toUpperCase();
            var stateChanging = ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1;
            var target = (url && url.url) ? url.url : String(url || '');
            var sameOrigin = false;
            try { sameOrigin = (new URL(target, location.href)).origin === location.origin; } catch (e) {}
            if (stateChanging && sameOrigin) {
                var headers = new Headers(opts.headers || (url && url.headers) || {});
                headers.set('X-CSRF-Token', RX_CSRF_TOKEN);
                opts.headers = headers;
            }
            return origFetch.call(this, url, opts);
        };
    }
})();
</script>


<script>
(function(){var last=parseInt(localStorage.getItem('rx_notice_count')||'0',10);async function check(){try{var r=await fetch('notifications.php?ajax=count',{credentials:'same-origin',cache:'no-store'});if(!r.ok)return;var j=await r.json(),n=parseInt(j.count||0,10),e=document.getElementById('rxNoticeCount');if(e){e.textContent=n;e.style.display=n?'block':'none';}if(n>last){var t=document.createElement('div');t.textContent='🔔 '+(n-last)+' اعلان جدید برای مدیریت دریافت شد';t.style.cssText='position:fixed;left:20px;bottom:20px;background:#ef4444;color:#fff;padding:13px 18px;border-radius:12px;z-index:10000;font:700 12px Arad,sans-serif;box-shadow:0 16px 45px rgba(0,0,0,.4)';document.body.appendChild(t);setTimeout(function(){t.remove()},6500);document.title='🔔 ('+n+') '+document.title.replace(/^🔔 \(\d+\) /,'');}last=n;localStorage.setItem('rx_notice_count',String(n));}catch(x){}}setInterval(check,15000);setTimeout(check,1200);document.addEventListener('click',function(e){var m=document.getElementById('rxNoticeMenu'),b=document.getElementById('rxNoticeBtn');if(m&&b&&!m.contains(e.target)&&!b.contains(e.target))m.classList.remove('open');});})();
</script>
