<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('default_charset', 'UTF-8');

$rootPath = dirname(__DIR__);
require_once $rootPath . '/lib/Security.php';
require_once $rootPath . '/lib/HostingSecrets.php';
require_once $rootPath . '/lib/TelegramWebhook.php';
redfox_secure_session_start();
redfox_security_headers();
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

$installMarker = $rootPath . '/storage/install.lock';
if (is_file($installMarker)) {
    http_response_code(410);
    exit('Installer is locked because installation has already completed.');
}
$configuredTokenFile = trim((string)(rx_env('REDFOX_INSTALL_TOKEN_FILE') ?: ''));
if ($configuredTokenFile !== '') {
    $tokenFile = $configuredTokenFile;
} else {
    $appRootReal = realpath($rootPath) ?: $rootPath;
    $documentRootRaw = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $documentRootReal = $documentRootRaw !== '' ? realpath($documentRootRaw) : false;
    $tokenFile = dirname($rootPath) . '/.redfox-install-token';
    if (is_string($documentRootReal)
        && $appRootReal !== $documentRootReal
        && str_starts_with($appRootReal, rtrim($documentRootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        // A subdirectory installation must not put its token in public_html.
        // Store it one level above the web root and namespace it per app path.
        $tokenFile = dirname($documentRootReal) . '/.redfox-install-token-' . substr(hash('sha256', $appRootReal), 0, 12);
    }
}
if (!is_file($tokenFile)) {
    $newToken = bin2hex(random_bytes(32));
    $fh = @fopen($tokenFile, 'x');
    if (is_resource($fh)) { fwrite($fh, $newToken . "\n"); fclose($fh); @chmod($tokenFile, 0600); }
}
$expectedSetupToken = trim((string)@file_get_contents($tokenFile));
if (!preg_match('/^[a-f0-9]{64}$/', $expectedSetupToken)) {
    error_log('[installer] secure token unavailable at configured token file');
    http_response_code(503);
    exit('Secure installer token is unavailable. Check the server-side installer token file.');
}
$installerMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$providedHeaderToken = trim((string)($_SERVER['HTTP_X_REDFOX_INSTALL_TOKEN'] ?? ''));
$providedPostToken = $installerMethod === 'POST' ? trim((string)($_POST['setup_token'] ?? '')) : '';

// Query-string credentials leak through browser history, access logs and Referer.
// Never authenticate with them; remove an accidentally supplied query immediately.
if (isset($_GET['setup_token'])) {
    header('Location: ' . (string)($_SERVER['SCRIPT_NAME'] ?? '/installer/'), true, 303);
    exit;
}
if (empty($_SESSION['redfox_installer_authorized']) && $providedHeaderToken !== ''
    && hash_equals($expectedSetupToken, $providedHeaderToken)) {
    session_regenerate_id(true);
    $_SESSION['redfox_installer_authorized'] = time();
}
if (empty($_SESSION['redfox_installer_authorized']) && $installerMethod === 'POST') {
    $csrf = trim((string)($_POST['rx_csrf_token'] ?? ''));
    if ($csrf !== '' && hash_equals(redfox_csrf_token(), $csrf)
        && $providedPostToken !== '' && hash_equals($expectedSetupToken, $providedPostToken)) {
        session_regenerate_id(true);
        $_SESSION['redfox_installer_authorized'] = time();
        header('Location: ' . (string)($_SERVER['SCRIPT_NAME'] ?? '/installer/'), true, 303);
        exit;
    }
}
if (empty($_SESSION['redfox_installer_authorized'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $csrfHtml = htmlspecialchars(redfox_csrf_token(), ENT_QUOTES, 'UTF-8');
    $tokenNameHtml = htmlspecialchars(basename($tokenFile), ENT_QUOTES, 'UTF-8');
    $invalid = $installerMethod === 'POST'
        ? '<div class="auth-alert" role="alert"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.5 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.5a2 2 0 0 0-3.4 0Z"/></svg><span><strong>ورود تأیید نشد</strong>توکن نامعتبر یا نشست منقضی است. مقدار فایل امن را دوباره و بدون فاصله وارد کنید.</span></div>'
        : '';
    echo <<<HTML
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="color-scheme" content="dark">
    <title>ورود امن به نصب‌کننده RedFox</title>
    <style>
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:400;src:url('fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2') format('woff2')}
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:400;src:url('fonts/vazirmatn/vazirmatn-latin-400-normal.woff2') format('woff2');unicode-range:U+0000-00FF,U+2000-206F}
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:600;src:url('fonts/vazirmatn/vazirmatn-arabic-600-normal.woff2') format('woff2')}
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:600;src:url('fonts/vazirmatn/vazirmatn-latin-600-normal.woff2') format('woff2');unicode-range:U+0000-00FF,U+2000-206F}
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:700;src:url('fonts/vazirmatn/vazirmatn-arabic-700-normal.woff2') format('woff2')}
        @font-face{font-family:Vazirmatn;font-style:normal;font-display:swap;font-weight:700;src:url('fonts/vazirmatn/vazirmatn-latin-700-normal.woff2') format('woff2');unicode-range:U+0000-00FF,U+2000-206F}
        :root{--bg:#060916;--panel:rgba(15,23,42,.82);--line:rgba(148,163,184,.18);--muted:#9aa8bd;--text:#f7f9fc;--blue:#5b8cff;--cyan:#2dd4bf;--danger:#fb7185}
        *{box-sizing:border-box}html{min-height:100%;background:var(--bg)}body{min-height:100vh;margin:0;color:var(--text);font-family:Vazirmatn,Tahoma,sans-serif;background:radial-gradient(circle at 15% 10%,rgba(45,212,191,.14),transparent 28rem),radial-gradient(circle at 85% 85%,rgba(91,140,255,.18),transparent 32rem),#060916;overflow-x:hidden}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.16;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(to bottom,black,transparent 80%)}
        .auth-shell{position:relative;z-index:1;min-height:100vh;display:grid;place-items:center;padding:32px 20px}.auth-wrap{width:min(100%,1040px);display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.82fr);border:1px solid var(--line);border-radius:28px;background:rgba(7,12,27,.7);box-shadow:0 28px 90px rgba(0,0,0,.48),inset 0 1px rgba(255,255,255,.04);backdrop-filter:blur(20px);overflow:hidden}
        .auth-visual{position:relative;min-height:620px;padding:58px;display:flex;flex-direction:column;justify-content:space-between;background:linear-gradient(145deg,rgba(30,41,59,.62),rgba(8,15,32,.28));border-left:1px solid var(--line)}.auth-visual:after{content:"";position:absolute;inset:auto -70px -110px auto;width:330px;height:330px;border-radius:50%;background:radial-gradient(circle,rgba(45,212,191,.2),transparent 68%);filter:blur(4px)}
        .brand{display:flex;align-items:center;gap:14px}.brand-mark{width:48px;height:48px;display:grid;place-items:center;border:1px solid rgba(91,140,255,.35);border-radius:15px;background:linear-gradient(145deg,rgba(91,140,255,.24),rgba(45,212,191,.12));box-shadow:0 12px 30px rgba(18,59,150,.22)}.brand-mark svg{width:27px;fill:none;stroke:#dbeafe;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.brand strong{font-size:18px}.brand small{display:block;color:var(--muted);direction:ltr;text-align:right;letter-spacing:.08em;font-size:11px}
        .visual-copy{max-width:530px}.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid rgba(45,212,191,.24);border-radius:999px;color:#9ff4e8;background:rgba(45,212,191,.07);font-size:12px;font-weight:600}.eyebrow:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--cyan);box-shadow:0 0 14px var(--cyan)}h1{margin:20px 0 14px;font-size:clamp(30px,4vw,48px);line-height:1.35;letter-spacing:-.035em}h1 span{background:linear-gradient(90deg,#a7c3ff,#75eadb);background-clip:text;-webkit-background-clip:text;color:transparent}.lead{margin:0;color:#b3bfd0;font-size:15px;line-height:2}.security-list{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:30px}.security-item{padding:13px;border:1px solid var(--line);border-radius:14px;background:rgba(15,23,42,.42);font-size:11px;color:#cbd5e1}.security-item svg{display:block;width:19px;height:19px;margin-bottom:8px;fill:none;stroke:#78dfd2;stroke-width:1.8}
        .visual-foot{display:flex;align-items:center;gap:10px;color:#738197;font-size:11px}.visual-foot svg{width:16px;fill:none;stroke:currentColor;stroke-width:1.8}
        .auth-form-pane{padding:58px 48px;display:flex;flex-direction:column;justify-content:center;background:rgba(9,14,30,.72)}.mobile-brand{display:none}.form-head h2{margin:0 0 8px;font-size:24px}.form-head p{margin:0 0 28px;color:var(--muted);font-size:13px;line-height:1.9}.auth-alert{display:flex;align-items:flex-start;gap:10px;margin:0 0 20px;padding:12px 14px;border:1px solid rgba(251,113,133,.28);border-radius:13px;background:rgba(251,113,133,.08);color:#fecdd3;font-size:12px;line-height:1.8}.auth-alert svg{flex:0 0 auto;width:19px;margin-top:2px;fill:none;stroke:var(--danger);stroke-width:1.8}.auth-alert strong{display:block;color:#fff}
        label{display:block;margin-bottom:9px;color:#dce4ef;font-size:13px;font-weight:600}.token-box{position:relative}.token-box input{width:100%;height:56px;padding:0 47px 0 15px;border:1px solid rgba(148,163,184,.23);border-radius:15px;outline:none;background:rgba(15,23,42,.72);color:#f8fafc;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;letter-spacing:.06em;direction:ltr;text-align:left;transition:.2s}.token-box input:hover{border-color:rgba(148,163,184,.4)}.token-box input:focus{border-color:rgba(91,140,255,.9);box-shadow:0 0 0 4px rgba(91,140,255,.13);background:rgba(18,28,51,.92)}.token-icon,.reveal{position:absolute;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:34px;height:34px;color:#8492a8}.token-icon{right:8px;pointer-events:none}.reveal{left:8px;border:0;border-radius:9px;background:transparent;cursor:pointer}.reveal:hover,.reveal:focus-visible{color:#dbeafe;background:rgba(148,163,184,.1);outline:none}.token-icon svg,.reveal svg{width:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.field-help{display:flex;align-items:flex-start;gap:7px;margin:10px 2px 25px;color:#718097;font-size:11px;line-height:1.8}.field-help svg{flex:0 0 auto;width:15px;margin-top:2px;fill:none;stroke:#8695aa;stroke-width:1.8}
        .submit-btn{position:relative;width:100%;height:54px;border:0;border-radius:15px;color:white;background:linear-gradient(90deg,#4e7df2,#557deb 48%,#26bba8);box-shadow:0 14px 28px rgba(34,94,211,.25);font-family:inherit;font-weight:700;font-size:14px;cursor:pointer;overflow:hidden;transition:transform .2s,filter .2s}.submit-btn:hover{transform:translateY(-1px);filter:brightness(1.08)}.submit-btn:active{transform:translateY(1px)}.submit-btn:focus-visible{outline:3px solid rgba(91,140,255,.35);outline-offset:3px}.submit-btn span{display:flex;align-items:center;justify-content:center;gap:8px}.submit-btn svg{width:18px;fill:none;stroke:currentColor;stroke-width:2}.form-note{margin:20px 0 0;text-align:center;color:#68758a;font-size:10px;line-height:1.8}.form-note code{color:#9dabc0;font-family:ui-monospace,monospace}
        @media(max-width:820px){.auth-wrap{grid-template-columns:1fr;max-width:500px}.auth-visual{display:none}.auth-form-pane{padding:40px 34px}.mobile-brand{display:flex;margin-bottom:38px}}
        @media(max-width:480px){.auth-shell{padding:16px}.auth-wrap{border-radius:22px}.auth-form-pane{padding:30px 22px}.token-box input{font-size:12px}.form-head h2{font-size:21px}}
        @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>
<main class="auth-shell">
    <section class="auth-wrap" aria-labelledby="auth-title">
        <aside class="auth-visual">
            <div class="brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="m4 4 4.2 2.3L12 4l3.8 2.3L20 4l-1.2 10.2L12 20l-6.8-5.8L4 4Z"/><path d="m8 11 2 1m6-1-2 1m-2 2v2"/></svg></span><span><strong>RedFox</strong><small>SECURE INSTALLER</small></span></div>
            <div class="visual-copy">
                <span class="eyebrow">دروازه امن راه‌اندازی</span>
                <h1>نصب حرفه‌ای، با <span>کنترل دسترسی یک‌بارمصرف</span></h1>
                <p class="lead">برای جلوگیری از اجرای نصب توسط افراد غیرمجاز، ورود به راه‌انداز تنها با توکن ۶۴ کاراکتری ذخیره‌شده بیرون از ریشه وب امکان‌پذیر است.</p>
                <div class="security-list">
                    <div class="security-item"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.8 7.8 7 10 4.2-2.2 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-5"/></svg>اعتبارسنجی ثابت‌زمان</div>
                    <div class="security-item"><svg viewBox="0 0 24 24"><rect x="4" y="7" width="16" height="13" rx="3"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>نشست رمزنگاری‌شده</div>
                    <div class="security-item"><svg viewBox="0 0 24 24"><path d="M12 2v4m0 12v4M4.9 4.9l2.8 2.8m8.6 8.6 2.8 2.8M2 12h4m12 0h4M4.9 19.1l2.8-2.8m8.6-8.6 2.8-2.8"/></svg>حذف خودکار پس از نصب</div>
                </div>
            </div>
            <div class="visual-foot"><svg viewBox="0 0 24 24"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/><path d="M12 16v-4m0-4h.01"/></svg>توکن را در URL، پیام‌رسان یا اسکرین‌شات قرار ندهید.</div>
        </aside>
        <div class="auth-form-pane">
            <div class="brand mobile-brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="m4 4 4.2 2.3L12 4l3.8 2.3L20 4l-1.2 10.2L12 20l-6.8-5.8L4 4Z"/><path d="m8 11 2 1m6-1-2 1m-2 2v2"/></svg></span><span><strong>RedFox</strong><small>SECURE INSTALLER</small></span></div>
            <div class="form-head"><h2 id="auth-title">احراز مجوز نصب</h2><p>محتوای فایل <code>$tokenNameHtml</code> را از File Manager سرور کپی و در کادر زیر وارد کنید.</p></div>
            $invalid
            <form method="post" autocomplete="off">
                <input type="hidden" name="rx_csrf_token" value="$csrfHtml">
                <label for="setup_token">توکن یک‌بارمصرف نصب</label>
                <div class="token-box">
                    <span class="token-icon"><svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="m11 12 8-8m-2 2 2 2m-5 1 2 2"/></svg></span>
                    <input type="password" id="setup_token" name="setup_token" required pattern="[a-f0-9]{64}" minlength="64" maxlength="64" inputmode="text" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" aria-describedby="token-help" autofocus>
                    <button class="reveal" type="button" aria-label="نمایش توکن" aria-pressed="false" title="نمایش یا پنهان‌سازی"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
                </div>
                <div class="field-help" id="token-help"><svg viewBox="0 0 24 24"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/><path d="M12 16v-4m0-4h.01"/></svg><span>توکن فقط برای همین نصب معتبر است و پس از تکمیل موفق، فایل آن حذف خواهد شد.</span></div>
                <button class="submit-btn" type="submit"><span>ورود به راه‌انداز <svg viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></button>
            </form>
            <p class="form-note">ارتباط را فقط روی <code>HTTPS</code> انجام دهید. هیچ بخشی از توکن در گزارش برنامه ذخیره نمی‌شود.</p>
        </div>
    </section>
</main>
<script>
(function(){var b=document.querySelector('.reveal'),i=document.getElementById('setup_token');if(!b||!i)return;b.addEventListener('click',function(){var show=i.type==='password';i.type=show?'text':'password';b.setAttribute('aria-pressed',show?'true':'false');b.setAttribute('aria-label',show?'پنهان‌کردن توکن':'نمایش توکن');i.focus();});})();
</script>
</body>
</html>
HTML;
    exit;
}

unset($expectedSetupToken, $providedHeaderToken, $providedPostToken);
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $csrf = trim((string)($_POST['rx_csrf_token'] ?? ''));
    if ($csrf === '' || !hash_equals(redfox_csrf_token(), $csrf)) { http_response_code(403); exit('CSRF validation failed.'); }
    $installLockPath = $rootPath . '/storage/installer-operation.lock';
    if (!is_dir(dirname($installLockPath))) @mkdir(dirname($installLockPath), 0750, true);
    $installLock = @fopen($installLockPath, 'c');
    if (!$installLock || !flock($installLock, LOCK_EX | LOCK_NB)) { http_response_code(409); exit('Another installation process is running.'); }
    register_shutdown_function(static function () use ($installLock): void { if (is_resource($installLock)) { flock($installLock, LOCK_UN); fclose($installLock); } });
}

$uPOST = sanitizeInput($_POST);
// Generic HTML sanitization must never mutate credentials. A database
// password may legitimately contain characters such as <, >, quotes or &.
// Preserve its exact submitted bytes after confirming it is a scalar; output
// fields never reflect this value back into the response.
if (isset($_POST['database_password']) && is_string($_POST['database_password'])) {
    $uPOST['database_password'] = $_POST['database_password'];
}
if (isset($_POST['tg_bot_token']) && is_string($_POST['tg_bot_token'])) {
    $uPOST['tg_bot_token'] = trim($_POST['tg_bot_token']);
}
$rootDirectory = $rootPath.'/';
$tablesDirectory = $rootDirectory.'table.php';
redfoxInstallerSelfHealGeneratedFiles(dirname(__DIR__));
$ERROR = [];
$SUCCESS = [];
$installerMissingFiles = redfoxInstallerMissingFiles(dirname(__DIR__));
if ($installerMissingFiles) {
    $ERROR[] = "فایل‌های بسته نصب ناقص هستند؛ نصب برای جلوگیری از خرابی متوقف شد.";
    $ERROR[] = "فایل‌های مفقود: <code>" . escapeHtml(implode(', ', $installerMissingFiles)) . "</code>";
    $ERROR[] = "ZIP کامل را دوباره در همان مسیر Extract کنید و مطمئن شوید File Manager همه فایل‌ها را منتقل کرده است.";
}
if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    $ERROR[] = "نسخه PHP شما باید حداقل 8.4 باشد.";
    $ERROR[] = "نسخه فعلی: " . PHP_VERSION;
    $ERROR[] = "لطفاً نسخه PHP دامنه را به 8.4 یا بالاتر ارتقا دهید.";
}

$installerScriptPath = (string)(parse_url((string)($_SERVER['SCRIPT_NAME'] ?? '/installer/index.php'), PHP_URL_PATH) ?: '/installer/index.php');
if (preg_match('#^(.*?)/installer(?:/index\.php)?/?$#i', $installerScriptPath, $installerPathMatch)) {
    $tempPath = (string)$installerPathMatch[1];
} else {
    $tempPath = dirname(dirname($installerScriptPath));
}
$tempPath = $tempPath === '' || $tempPath === '/' ? '' : '/' . trim(preg_replace('#/+#', '/', $tempPath), '/');
unset($installerScriptPath, $installerPathMatch);
$requestHost = redfoxInstallerRequestHost();
if ($requestHost === null) { http_response_code(400); exit('Invalid Host header.'); }
$webAddress = rtrim('https://' . $requestHost . $tempPath, '/');
$success = false;
$webhookPending = false;
$webhookPendingMessage = '';
$tgBot = [];
$botFirstMessage = '';
// Only the simple cPanel-host installation is supported. The migration
// (migrate_free_to_pro) and dedicated-server (VPS) install paths were removed,
// so these are hard-forced regardless of any posted value.
$installType = 'simple';
$serverType  = 'cpanel';
$hasDbBackup = 'no';
$currentStep = 1;
$installFieldTotal = 7;
$currentInstallField = isset($uPOST['current_install_field']) ? (int)$uPOST['current_install_field'] : 1;
$currentStep = 1;
$currentInstallField = max(1, min($installFieldTotal, $currentInstallField));


function isHttps(): bool { return redfox_is_https(); }
function redfoxInstallerRequestHost(): ?string {
    $host = redfox_normalize_request_host((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host === '' ? null : $host;
}
if(isset($uPOST['submit']) && $uPOST['submit']) {
    foreach (['admin_id','tg_bot_token','database_name','database_username','database_password','bot_address_webhook'] as $requiredField) {
        if (!isset($uPOST[$requiredField]) || !is_string($uPOST[$requiredField]) || trim($uPOST[$requiredField]) === '') $ERROR[] = 'فیلد الزامی ناقص است: ' . escapeHtml($requiredField);
    }
    if (strlen((string)($uPOST['database_name'] ?? '')) > 64 || !preg_match('/^[A-Za-z0-9_$-]+$/', (string)($uPOST['database_name'] ?? ''))) $ERROR[] = 'نام دیتابیس نامعتبر است.';
    if (strlen((string)($uPOST['database_username'] ?? '')) > 128 || !preg_match('/^[A-Za-z0-9_@.$-]+$/', (string)($uPOST['database_username'] ?? ''))) $ERROR[] = 'نام کاربری دیتابیس نامعتبر است.';
    $databasePassword = (string)($uPOST['database_password'] ?? '');
    if (strlen($databasePassword) > 1024) $ERROR[] = 'رمز دیتابیس بیش از حد طولانی است.';
    if (preg_match('/[\x00\r\n]/', $databasePassword)) $ERROR[] = 'رمز دیتابیس دارای نویسه کنترلی غیرمجاز است.';
    $installerMissingFiles = redfoxInstallerMissingFiles(dirname(__DIR__));
    if ($installerMissingFiles) {
        $ERROR[] = 'بسته نصب ناقص است و هیچ تغییری روی دیتابیس انجام نشد.';
        $ERROR[] = 'فایل‌های مفقود: <code>' . escapeHtml(implode(', ', $installerMissingFiles)) . '</code>';
    }
    $SUCCESS[] = "✅ ربات با موفقیت نصب شد !";
    $tgAdminId = (string)($uPOST['admin_id'] ?? '');
    $tgBotToken = (string)($uPOST['tg_bot_token'] ?? '');
    $dbInfo['host'] = (string)(rx_env('REDFOX_DB_HOST') ?: 'localhost');
    $dbInfo['name'] = (string)($uPOST['database_name'] ?? '');
    $dbInfo['username'] = (string)($uPOST['database_username'] ?? '');
    $dbInfo['password'] = (string)($uPOST['database_password'] ?? '');
    $inputUrl = $uPOST['bot_address_webhook'] ?? $webAddress . '/index.php';
    $document = normalizeDomainAddress($inputUrl);
    if ($document === null) {
        $ERROR[] = 'آدرس ارائه شده برای ربات نامعتبر است.';
    }
    if(!isHttps()) {
        $ERROR[] = 'برای فعال سازی ربات تلگرام نیازمند فعال بودن SSL (https) هستید';
        $ERROR[] = '<i>اگر از فعال بودن SSL مطمئن هستید، سرور پشت proxy/CDN (مثل Cloudflare) است – headers را در cPanel چک کنید یا با https مستقیم باز کنید.</i>';
        $sslLink = 'https://' . $requestHost . (string)($_SERVER['SCRIPT_NAME'] ?? '/installer/');
        $ERROR[] = '<a href="' . $sslLink . '">' . $sslLink . '</a>';
    }
    $isValidToken = isValidTelegramToken($tgBotToken);
    if(!$isValidToken) {
        $ERROR[] = "توکن ربات صحیح نیست؛ توکن را مستقیماً از <code>@BotFather</code> کپی کنید.";
        $currentInstallField = 2;
    }
    if (!isValidTelegramId($tgAdminId)) {
        $ERROR[] = "آیدی عددی ادمین نامعتبر است.";
        $currentInstallField = 1;
    }
    if($isValidToken) {
        $tgBot['details'] = telegramApiRequest($tgBotToken, 'getMe');
        if(empty($tgBot['details']['ok'])) {
            $ERROR[] = telegramInstallerErrorMessage($tgBot['details'], 'getMe');
            $currentInstallField = 2;
        }
        else {
            $botUsername = (string)($tgBot['details']['result']['username'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $botUsername)) {
                $ERROR[] = '<b>پاسخ تلگرام ناقص بود.</b> نام کاربری معتبر ربات در پاسخ وجود نداشت. <code>TG-RESPONSE</code>';
                $currentInstallField = 2;
            } else {
                $tgBot['recognition'] = telegramApiRequest($tgBotToken, 'getChat', ['chat_id' => $tgAdminId]);
                if(empty($tgBot['recognition']['ok'])) {
                    if (($tgBot['recognition']['failure'] ?? '') === 'telegram_api'
                        && in_array((int)($tgBot['recognition']['error_code'] ?? 0), [400, 403], true)) {
                        $ERROR[] = "<b>عدم شناسایی مدیر ربات:</b> ابتدا با حساب مدیر، ربات را Start کنید و دوباره ادامه دهید.";
                        $ERROR[] = "<a href='https://t.me/" . escapeHtml($botUsername) . "' target='_blank' rel='noopener noreferrer'>@" . escapeHtml($botUsername) . "</a>";
                    } else {
                        $ERROR[] = telegramInstallerErrorMessage($tgBot['recognition'], 'getChat');
                    }
                    $currentInstallField = 2;
                }
            }
        }
    }
    try {
        $dsn = "mysql:host=" . $dbInfo['host'] . ";dbname=" . $dbInfo['name'] . ";charset=utf8mb4";
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];
        $pdo = new PDO($dsn, $dbInfo['username'], $dbInfo['password'], $pdoOptions);
        $SUCCESS[] = "✅ اتصال به دیتابیس موفقیت آمیز بود!";
    }
    catch (\PDOException $e) {
        error_log('[installer] database connection failed: ' . redfox_exception_fingerprint($e));
        $ERROR[] = "❌ عدم اتصال به دیتابیس.";
        $ERROR[] = "نام کاربری، رمز، نام دیتابیس و دسترسی کاربر دیتابیس را در cPanel بررسی کنید.";
        if ($currentInstallField > 2) $currentInstallField = 3;
    }
    if(empty($ERROR)) {
        $installStage = 'ثبت امن تنظیمات نصب';
        try {
            $baseAddress = rtrim($document['address'], '/');
            ensureFreshInstallSecrets(dirname(__DIR__), [
                'REDFOX_DB_HOST' => $dbInfo['host'],
                'REDFOX_DB_NAME' => $dbInfo['name'],
                'REDFOX_DB_USER' => $dbInfo['username'],
                'REDFOX_DB_PASSWORD' => $dbInfo['password'],
                'REDFOX_BOT_TOKEN' => $tgBotToken,
                'REDFOX_ADMIN_ID' => $tgAdminId,
                'REDFOX_DOMAIN' => $baseAddress,
                'REDFOX_BOT_USERNAME' => (string)$tgBot['details']['result']['username'],
            ]);

            $installStage = 'سازگارکردن کلیدها و Indexهای دیتابیس قدیمی';
            require_once dirname(__DIR__) . '/bin/InstallerDatabaseRepair.php';
            $databaseRepairs = RedFoxInstallerDatabaseRepair::repair($pdo);
            if ($databaseRepairs) $SUCCESS[] = '✅ ' . count($databaseRepairs) . ' ساختار قدیمی بدون حذف اطلاعات اصلاح شد';

            $installStage = 'ساخت و تکمیل جداول پایه';
            if (!defined('REDFOX_INSTALLER_SCHEMA_CALL')) define('REDFOX_INSTALLER_SCHEMA_CALL', true);
            $rxOldCwd=getcwd(); chdir(dirname(__DIR__)); ob_start(); require dirname(__DIR__) . '/table.php'; ob_end_clean(); if($rxOldCwd) chdir($rxOldCwd);
            $SUCCESS[] = "✅ جداول پایه دیتابیس ایجاد شد";

            $installStage = 'اجرای Migrationهای حرفه‌ای دیتابیس';
            // Fresh installations must be immediately compatible with every
            // current panel/API feature; no separate Migration page is needed.
            require_once dirname(__DIR__) . '/lib/MigrationRunner.php';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
            $migrationResults = RedFoxMigrationRunner::run($pdo, dirname(__DIR__) . '/migrations');
            $migrationCount = count(array_filter($migrationResults, static fn($row) => ($row[1] ?? '') === 'applied'));
            $SUCCESS[] = "✅ ساختار حرفه‌ای دیتابیس و {$migrationCount} Migration تکمیل شد";

            $installStage = 'همگام‌سازی ربات‌های نمایندگان و سوپرنمایندگان';
            // این مرحله اختیاری است و نباید نصب اصلی را متوقف کند.
            try {
                require_once dirname(__DIR__).'/lib/ResellerBotManager.php';$botSync=(new RedFoxResellerBotManager($pdo,dirname(__DIR__)))->repairAll();$botSync['bots']=$botSync['ok']??0;
                if (($botSync['bots'] ?? 0) > 0) $SUCCESS[] = '✅ فایل‌های اجرایی ' . (int)$botSync['bots'] . ' ربات نماینده بروزرسانی شد';
                if (!empty($botSync['warning'])) $SUCCESS[] = '⚠️ ' . $botSync['warning'];
            } catch (Throwable $syncError) {
                error_log('[installer] optional reseller bot sync skipped: ' . redfox_exception_fingerprint($syncError));
                $SUCCESS[] = '⚠️ نصب اصلی کامل شد؛ همگام‌سازی ربات‌های نماینده بعداً از پنل انجام می‌شود.';
            }

            $installStage = 'ساخت کلیدهای امنیتی';
            ensureFreshInstallSecrets(dirname(__DIR__));
            $SUCCESS[] = "✅ کلیدهای امنیتی و بکاپ اختصاصی ساخته شد";
            $initialAdminPassword = ensureAdminRecord($pdo, $tgAdminId);

            $installStage = 'بررسی URL عمومی، DNS، TLS و تنظیم Webhook امن تلگرام';
            $webhookSecret = (string)$pdo->query('SELECT webhook_secret_token FROM setting LIMIT 1')->fetchColumn();
            if (!preg_match('/^[A-Za-z0-9_-]{32,64}$/', $webhookSecret)) {
                $webhookSecret = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE setting SET webhook_secret_token=?')->execute([$webhookSecret]);
            }
            $webhookUrl = 'https://' . $baseAddress . '/index.php';
            $webhookSetup = telegramInstallerConfigureWebhook($tgBotToken, $webhookUrl, $webhookSecret);
            if (!empty($webhookSetup['ok'])) {
                redfox_webhook_status_write($pdo, 'active', '');
                $SUCCESS[] = "✅ Webhook امن تنظیم و تأیید شد";
            } else {
                $webhookPending = true;
                $webhookError = is_array($webhookSetup['error'] ?? null)
                    ? $webhookSetup['error']
                    : redfox_telegram_webhook_error($webhookSetup['response'] ?? null);
                $safeWebhookCode = preg_match('/^TG-WH-[A-Z0-9_-]+$/', (string)($webhookError['code'] ?? ''))
                    ? (string)$webhookError['code'] : 'TG-WH-UNKNOWN';
                redfox_webhook_status_write($pdo, 'pending', $safeWebhookCode);
                $webhookPendingMessage = redfox_telegram_webhook_error_text($webhookError);
                $SUCCESS[] = '⚠️ هستهٔ نصب کامل شد؛ Webhook در وضعیت نیازمند تعمیر است. کد: ' . escapeHtml($safeWebhookCode);
                error_log('[installer] webhook deferred code=' . $safeWebhookCode . ' fingerprint=' . preg_replace('/[^a-f0-9]/', '', (string)($webhookError['fingerprint'] ?? '')));
            }
            $botFirstMessage = "\n[🤖] شما به عنوان ادمین معرفی شدید.";
            if ($webhookPending) {
                $botFirstMessage .= "\n⚠️ Webhook هنوز فعال نشده است. پس از ورود به پنل، بخش «تنظیمات ربات» و دکمه «تعمیر Webhook» را اجرا کنید.\n" . $webhookPendingMessage;
            }
            if (is_string($initialAdminPassword) && $initialAdminPassword !== '') {
                $botFirstMessage .= "\n👤 نام کاربری پنل: admin\n🔐 رمز موقت پنل: " . $initialAdminPassword . "\nپس از اولین ورود رمز را تغییر دهید.";
            }
            $replyMarkup = json_encode([
                'inline_keyboard' => [[['text' => '⚙️ شروع ربات ', 'callback_data' => 'start']]],
            ], JSON_UNESCAPED_UNICODE);
            $installStage = 'ارسال امن اطلاعات ورود مدیر';
            $welcomeResult = telegramApiRequest($tgBotToken, 'sendMessage', [
                'chat_id' => $tgAdminId,
                'text' => ' ' . $SUCCESS[0] . $botFirstMessage,
                'reply_markup' => $replyMarkup,
            ]);
            if (empty($welcomeResult['ok'])) {
                throw new RuntimeException('Telegram welcome message failed: ' . (string)($welcomeResult['failure'] ?? 'api'));
            }
            $SUCCESS[] = "✅ پیام راه‌اندازی و رمز موقت مدیر ارسال شد";
            $success = true;
            if (!is_dir(dirname($installMarker))) @mkdir(dirname($installMarker), 0750, true);
            if (file_put_contents($installMarker, json_encode([
                'installed_at' => date(DATE_ATOM),
                'webhook_status' => $webhookPending ? 'pending' : 'active',
            ], JSON_UNESCAPED_SLASHES), LOCK_EX) === false) throw new RuntimeException('Unable to create installation lock.');
            @chmod($installMarker, 0640);
            @unlink($tokenFile);
            unset($_SESSION['redfox_installer_authorized']);
            scheduleInstallerSelfDelete(__DIR__);
            } catch (Throwable $installError) {
                $success = false;
                $ERROR[] = '❌ تکمیل نصب در مرحله «' . escapeHtml($installStage ?? 'نامشخص') . '» ناموفق بود.';
                if ($installError instanceof RedFoxHostingSecretException) {
                    $safeDiagnostic = rx_hosting_secret_diagnostic($installError);
                    $ERROR[] = 'کد تشخیص امن: <b>' . escapeHtml((string)$safeDiagnostic['code']) . '</b>';
                    $ERROR[] = escapeHtml((string)$safeDiagnostic['message']);
                    error_log('[installer] secure settings failed code=' . preg_replace('/[^A-Z0-9_-]/', '', (string)$safeDiagnostic['code'])
                        . ' fingerprint=' . redfox_exception_fingerprint($installError));
                } else {
                    $trackingCode = 'RX-INSTALL-' . strtoupper(substr(redfox_exception_fingerprint($installError), 0, 12));
                    $ERROR[] = 'کد پیگیری امن: <b>' . escapeHtml($trackingCode) . '</b>';
                    $ERROR[] = 'فضای دیسک، مالکیت فایل‌ها و گزارش PHP هاست را بررسی و این کد را به پشتیبانی ارائه کنید.';
                    error_log('[installer] fresh installation failed tracking=' . $trackingCode
                        . ' fingerprint=' . redfox_exception_fingerprint($installError));
                }
            }
        }
}

function redfoxInstallerSelfHealGeneratedFiles(string $root): void {
    $root=rtrim($root,'/\\');
    $sets=[
        ['dir'=>'re/rx/index','parts'=>['bootstrap.php','user_flow.php','panel_dispatch.php','finalize.php']],
        ['dir'=>'re/rx/function','parts'=>['bootstrap.php','database_helpers_1.php','database_helpers_2.php','bot_api_helpers.php','business_logic_1.php','business_logic_2.php','business_logic_3.php','business_logic_4.php','business_logic_5.php','crypto_helpers.php']],
        ['dir'=>'re/rx/admin','parts'=>['bootstrap_1.php','bootstrap_2.php','finance.php','settings.php','maintenance.php']],
        ['dir'=>'re/rx/keyboard','parts'=>['layouts_1.php','layouts_2.php','admin_panels_1.php','admin_panels_2.php']],
    ];
    foreach($sets as$set){$dir=$root.'/'.$set['dir'];$body="<?php\n/** Generated from manifest.php at release build time. Do not edit directly. */\n";$ok=true;foreach($set['parts'] as$name){$file=$dir.'/'.$name;if(!is_file($file)){$ok=false;break;}$part=(string)file_get_contents($file);if(str_starts_with($part,'<?php'))$part=substr($part,5);$body.="\n/* ---- {$name} ---- */\n".ltrim($part,"\r\n");}if($ok&&is_dir($dir)&&is_writable($dir)){$target=$dir.'/compiled.php';$tmp=$target.'.heal-'.bin2hex(random_bytes(3));if(file_put_contents($tmp,$body,LOCK_EX)!==false){@chmod($tmp,0644);@rename($tmp,$target);}else @unlink($tmp);}}
    foreach(['index.php','func.php','keyboard.php','admin.php','botapi.php','.htaccess'] as$file){$a=$root.'/vpnbot/Default/'.$file;$b=$root.'/vpnbot/update/'.$file;if(!is_file($a)&&is_file($b)&&is_writable(dirname($a))){@copy($b,$a);@chmod($a,$file==='.htaccess'?0644:0640);}if(!is_file($b)&&is_file($a)&&is_writable(dirname($b))){@copy($a,$b);@chmod($b,$file==='.htaccess'?0644:0640);}}
    foreach(['storage','updates','Upload'] as$dir){$path=$root.'/'.$dir;if(!is_dir($path))@mkdir($path,0750,true);}
}

function redfoxInstallerRequiredFiles(): array {
    $manifest=dirname(__DIR__).'/release-required-files.json';
    if(is_file($manifest)){
        $decoded=json_decode((string)file_get_contents($manifest),true);
        if(is_array($decoded)&&$decoded)return array_values(array_filter($decoded,'is_string'));
    }
    return [
        'config.php','table.php','index.php','function.php','botapi.php','panels.php',
        'lib/Security.php','lib/HostingSecrets.php','lib/TelegramWebhook.php','lib/Secrets.php','lib/MigrationRunner.php','lib/SqlTools.php',
        'lib/DatabaseBackup.php','lib/PanelCapacity.php','lib/ResellerAI.php',
        'composer.json','composer.lock','vendor/autoload.php','vendor/composer/installed.json',
        'vendor/paragonie/sodium_compat/autoload.php','vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php','vendor/endroid/qr-code/src/QrCode.php',
        'bin/InstallerDatabaseRepair.php','re/rx/index/compiled.php','re/rx/function/compiled.php',
        'vpnbot/Default/index.php','vpnbot/Default/func.php','vpnbot/Default/.htaccess',
        'vpnbot/update/index.php','vpnbot/update/func.php','vpnbot/update/.htaccess',
        'migrations/019_reseller_ai_workflow.sql','migrations/020_bot_web_ai_consistency_audit.sql','migrations/055_webhook_recovery_status.sql','migrations/056_integrity_unique_constraints.sql',
        'panel/bot_settings.php','panel/agents.php','panel/hosting_setup.php','api/users.php','cron/cron.php','cron/diag.php','WEBHOOK-RECOVERY-FA.md',
    ];
}
function redfoxInstallerMissingFiles(string $root): array {
    $missing=[];
    foreach(redfoxInstallerRequiredFiles() as $relative){
        $path=rtrim($root,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if(!is_file($path)||!is_readable($path)||filesize($path)===0)$missing[]=$relative;
    }
    return $missing;
}
function redfoxInstallerSyncResellerBots(PDO $pdo,string $root):array {
    $root=rtrim($root,'/\\');$source=$root.'/vpnbot/update';
    if(!is_dir($source))$source=$root.'/vpnbot/Default';
    if(!is_dir($source))return['bots'=>0,'files'=>0,'warning'=>'قالب ربات نماینده موجود نیست؛ همگام‌سازی رد شد.'];
    $allowed=['index.php','func.php','keyboard.php','admin.php','botapi.php','.htaccess'];
    // تعمیر تنظیم پیش‌فرض اشتباه show_product در ربات‌های ساخته‌شده از نسخه‌های قدیمی
    try{$q=$pdo->query('SELECT id,setting FROM botsaz');$rows=$q->fetchAll(PDO::FETCH_ASSOC);$q->closeCursor();$u=$pdo->prepare('UPDATE botsaz SET setting=? WHERE id=?');foreach($rows as$row){$cfg=json_decode((string)($row['setting']??''),true);if(!is_array($cfg))$cfg=[];if(!isset($cfg['product_mode_version'])||(int)$cfg['product_mode_version']<2){$cfg['show_product']=false;$cfg['product_mode_version']=2;if(!array_key_exists('active_step_note',$cfg))$cfg['active_step_note']=false;$u->execute([json_encode($cfg,JSON_UNESCAPED_UNICODE),(int)$row['id']]);}}}catch(Throwable$settingsRepairError){error_log('[installer] reseller bot setting repair: '.redfox_exception_fingerprint($settingsRepairError));}
    $bots=0;$files=0;$skipped=0;$base=$root.'/vpnbot';$baseReal=realpath($base);
    foreach((scandir($base)?:[])as$entry){if(in_array($entry,['.','..','Default','update'],true))continue;$dest=$base.'/'.$entry;if(!is_dir($dest)||!is_file($dest.'/config.php'))continue;$real=realpath($dest);if(!$real||!$baseReal||strpos($real,$baseReal.DIRECTORY_SEPARATOR)!==0){$skipped++;continue;}$changed=false;foreach($allowed as$file){$src=$source.'/'.$file;if(!is_file($src))continue;$dst=$dest.'/'.$file;$tmp=$dst.'.sync-'.bin2hex(random_bytes(3));if(!@copy($src,$tmp)){@unlink($tmp);$skipped++;continue;}@chmod($tmp,0644);if(!@rename($tmp,$dst)){@unlink($tmp);$skipped++;continue;}if(function_exists('opcache_invalidate'))@opcache_invalidate($dst,true);$files++;$changed=true;}if($changed){@file_put_contents($dest.'/.redfox-template-version',trim((string)@file_get_contents($root.'/version')),LOCK_EX);$bots++;}}
    return['bots'=>$bots,'files'=>$files,'skipped'=>$skipped];
}

function ensureFreshInstallSecrets(string $root, array $operational = []): void {
    $storage = $root . '/storage';
    if (!is_dir($storage) && !mkdir($storage, 0750, true)) {
        throw new RuntimeException('پوشه امن storage قابل ساخت نیست.');
    }
    if (is_link($storage)) throw new RuntimeException('مسیر storage نمی‌تواند symlink باشد.');
    $secrets = rx_load_hosting_secrets($root);
    foreach (['REDFOX_MASTER_KEY','REDFOX_BACKUP_KEY','REDFOX_CRON_SECRET','REDFOX_DIAG_SECRET','REDFOX_HEALTH_TOKEN'] as $key) {
        if (empty($secrets[$key])) $secrets[$key] = bin2hex(random_bytes(32));
    }
    if (empty($secrets['REDFOX_UPDATE_CHANNEL'])) $secrets['REDFOX_UPDATE_CHANNEL'] = 'stable';
    $allowedOperational = ['REDFOX_DB_HOST','REDFOX_DB_NAME','REDFOX_DB_USER','REDFOX_DB_PASSWORD','REDFOX_BOT_TOKEN','REDFOX_ADMIN_ID','REDFOX_DOMAIN','REDFOX_BOT_USERNAME'];
    foreach ($operational as $key => $value) {
        if (!in_array($key, $allowedOperational, true) || !is_string($value)
            || strlen($value) > 65535 || preg_match('/[\x00\r\n]/', $value)) {
            throw new RuntimeException('تنظیم امن نامعتبر: ' . (string)$key);
        }
        $secrets[$key] = $value;
    }
    // The central accessor reads the refreshed in-request cache directly.
    // Never depend on putenv(), which is commonly disabled on shared hosting.
    rx_update_hosting_secrets($secrets, $root);
}

function ensureAdminRecord(PDO $pdo, string $adminNumber): string {
    if (!isValidTelegramId($adminNumber)) {
        throw new InvalidArgumentException('Invalid administrator identifier.');
    }
    $plainPassword = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Unable to hash the administrator password.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) $pdo->beginTransaction();
    try {
        $selectExact = $pdo->prepare('SELECT id_admin FROM admin WHERE id_admin = ? LIMIT 1 FOR UPDATE');
        $selectExact->execute([$adminNumber]);
        $existingId = $selectExact->fetchColumn();
        $selectExact->closeCursor();

        if ($existingId !== false) {
            $statement = $pdo->prepare("UPDATE admin SET username = 'admin', password = ?, rule = 'administrator' WHERE id_admin = ? LIMIT 1");
            $statement->execute([$passwordHash, $adminNumber]);
        } else {
            $selectFirst = $pdo->query('SELECT id_admin FROM admin ORDER BY id_admin LIMIT 1 FOR UPDATE');
            $oldId = $selectFirst->fetchColumn();
            $selectFirst->closeCursor();
            if ($oldId !== false) {
                $statement = $pdo->prepare("UPDATE admin SET id_admin = ?, username = 'admin', password = ?, rule = 'administrator' WHERE id_admin = ? LIMIT 1");
                $statement->execute([$adminNumber, $passwordHash, (string)$oldId]);
            } else {
                $statement = $pdo->prepare("INSERT INTO admin (id_admin, username, password, rule) VALUES (?, 'admin', ?, 'administrator')");
                $statement->execute([$adminNumber, $passwordHash]);
            }
        }
        if ($startedTransaction) $pdo->commit();
        return $plainPassword;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa" data-theme="red">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c0a09">
    <title>نصب خودکار ربات رد فاکس</title>
    <style>

        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 400;
            src: url('./fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2') format('woff2');
            unicode-range: U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08FF,U+200C-200F,U+FB50-FDFF,U+FE70-FEFC;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 400;
            src: url('./fonts/vazirmatn/vazirmatn-latin-400-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 500;
            src: url('./fonts/vazirmatn/vazirmatn-arabic-500-normal.woff2') format('woff2');
            unicode-range: U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08FF,U+200C-200F,U+FB50-FDFF,U+FE70-FEFC;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 500;
            src: url('./fonts/vazirmatn/vazirmatn-latin-500-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 600;
            src: url('./fonts/vazirmatn/vazirmatn-arabic-600-normal.woff2') format('woff2');
            unicode-range: U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08FF,U+200C-200F,U+FB50-FDFF,U+FE70-FEFC;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 600;
            src: url('./fonts/vazirmatn/vazirmatn-latin-600-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 700;
            src: url('./fonts/vazirmatn/vazirmatn-arabic-700-normal.woff2') format('woff2');
            unicode-range: U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08FF,U+200C-200F,U+FB50-FDFF,U+FE70-FEFC;
        }
        @font-face {
            font-family: 'Vazirmatn'; font-style: normal; font-display: swap; font-weight: 700;
            src: url('./fonts/vazirmatn/vazirmatn-latin-700-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
        }


        :root {
            --bg-base:        #0c0a09;
            --bg-surface:     #1c1917;
            --bg-elevated:    #292524;
            --bg-input:       #16130f;
            --border-subtle:  rgba(255, 255, 255, 0.08);
            --border-strong:  rgba(255, 255, 255, 0.18);
            --text-primary:   #fafaf9;
            --text-secondary: #d6d3d1;
            --text-muted:     #a8a29e;
            --shadow-glow:    0 0 0 1px rgba(255, 255, 255, 0.05), 0 20px 60px -20px rgba(0, 0, 0, 0.5);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="red"]    { --accent: #ef4444; --accent-soft: rgba(239,  68,  68, 0.15); --accent-strong: #dc2626; --accent-shadow: rgba(239,  68,  68, 0.45); }
        [data-theme="blue"]   { --accent: #3b82f6; --accent-soft: rgba( 59, 130, 246, 0.15); --accent-strong: #2563eb; --accent-shadow: rgba( 59, 130, 246, 0.45); }
        [data-theme="purple"] { --accent: #a855f7; --accent-soft: rgba(168,  85, 247, 0.15); --accent-strong: #9333ea; --accent-shadow: rgba(168,  85, 247, 0.45); }
        [data-theme="green"]  { --accent: #22c55e; --accent-soft: rgba( 34, 197,  94, 0.15); --accent-strong: #16a34a; --accent-shadow: rgba( 34, 197,  94, 0.45); }
        [data-theme="orange"] { --accent: #f97316; --accent-soft: rgba(249, 115,  22, 0.15); --accent-strong: #ea580c; --accent-shadow: rgba(249, 115,  22, 0.45); }


        *, *::before, *::after { box-sizing: border-box; }
        * { margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            font-family: 'Vazirmatn', Tahoma, system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            font-size: 15px;
            background-image:
                radial-gradient(at 20% 0%, var(--accent-soft) 0px, transparent 50%),
                radial-gradient(at 80% 100%, var(--accent-soft) 0px, transparent 50%);
            background-attachment: fixed;
        }
        code, kbd, pre { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 0.9em; }


        .app-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-header {
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(12, 10, 9, 0.7);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .app-header-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
            text-decoration: none;
        }
        .brand-mark {
            width: 36px; height: 36px;
            display: grid; place-items: center;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: var(--radius-sm);
            color: var(--accent);
        }
        .brand-mark svg { width: 18px; height: 18px; }
        .app-main {
            flex: 1;
            max-width: 880px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }
        .app-footer {
            border-top: 1px solid var(--border-subtle);
            padding: 20px 24px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        .app-footer a {
            color: var(--accent);
            text-decoration: none;
            transition: color var(--transition);
        }
        .app-footer a:hover { color: var(--accent-strong); }


        .theme-switcher {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
        }
        .theme-swatch {
            width: 22px; height: 22px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform var(--transition), border-color var(--transition);
            position: relative;
        }
        .theme-swatch:hover { transform: scale(1.15); }
        .theme-swatch.is-active { border-color: var(--text-primary); }
        .theme-swatch[data-theme="red"]    { background: #ef4444; }
        .theme-swatch[data-theme="blue"]   { background: #3b82f6; }
        .theme-swatch[data-theme="purple"] { background: #a855f7; }
        .theme-swatch[data-theme="green"]  { background: #22c55e; }
        .theme-swatch[data-theme="orange"] { background: #f97316; }


        .hero {
            text-align: center;
            margin-bottom: 40px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: 999px;
            color: var(--accent);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .hero-badge .pulse-dot {
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.6; transform: scale(0.85); }
        }
        .hero h1 {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .hero h1 .accent { color: var(--accent); }
        .hero p {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 560px;
            margin: 0 auto;
        }


        .alert {
            display: flex;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border: 1px solid;
            font-size: 14px;
            line-height: 1.7;
        }
        .alert svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; }
        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        .alert-danger svg { color: #ef4444; }
        .alert-success {
            background: rgba(34, 197, 94, 0.08);
            border-color: rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
        .alert-success svg { color: #22c55e; }
        .alert a { color: inherit; text-decoration: underline; font-weight: 600; }


        .stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            transition: all var(--transition);
        }
        .step-num {
            width: 24px; height: 24px;
            background: var(--bg-elevated);
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            transition: all var(--transition);
        }
        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            transition: color var(--transition);
        }
        .step.is-active {
            background: var(--accent-soft);
            border-color: var(--accent);
            box-shadow: 0 0 24px var(--accent-shadow);
        }
        .step.is-active .step-num {
            background: var(--accent);
            color: var(--bg-base);
        }
        .step.is-active .step-label { color: var(--accent); }
        .step.is-completed .step-num {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .step.is-completed .step-label { color: var(--text-secondary); }
        .step-divider {
            width: 24px;
            height: 1px;
            background: var(--border-subtle);
        }


        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 28px;
            margin-bottom: 20px;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        .card-title svg {
            width: 22px; height: 22px;
            color: var(--accent);
        }


        .step-section { display: none; }
        .step-section.is-active {
            display: block;
            animation: fadeSlide 280ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }


        .choice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }
        .choice-card {
            position: relative;
            padding: 20px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition);
        }
        .choice-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-2px);
        }
        .choice-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .choice-card.is-active {
            border-color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 0 0 1px var(--accent), 0 8px 24px var(--accent-shadow);
        }
        .choice-card-icon {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            background: var(--bg-base);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            color: var(--text-secondary);
            transition: all var(--transition);
        }
        .choice-card.is-active .choice-card-icon {
            background: var(--accent);
            color: var(--bg-base);
        }
        .choice-card-icon svg { width: 20px; height: 20px; }
        .choice-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-primary);
        }
        .choice-card p {
            font-size: 13px;
            color: var(--text-muted);
        }
        .choice-card .check-mark {
            position: absolute;
            top: 14px;
            left: 14px;
            opacity: 0;
            transition: opacity var(--transition);
            color: var(--accent);
        }
        .choice-card.is-active .check-mark { opacity: 1; }
        .choice-card .check-mark svg { width: 20px; height: 20px; }


        .sub-question { margin-top: 24px; }
        .sub-question h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sub-question h3 svg { width: 18px; height: 18px; color: var(--accent); }
        .pill-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            color: var(--text-secondary);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
        }
        .pill-btn svg { width: 16px; height: 16px; }
        .pill-btn:hover { border-color: var(--border-strong); color: var(--text-primary); }
        .pill-btn.is-active {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }
        .migration-section { margin-top: 24px; }


        .form-field {
            margin-bottom: 0;
        }
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .form-label svg { width: 16px; height: 16px; color: var(--accent); }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            direction: ltr;
            text-align: left;
            transition: all var(--transition);
        }
        .form-input::placeholder {
            color: var(--text-muted);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 13px;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--bg-base);
            box-shadow: 0 0 0 3px var(--accent-shadow);
        }
        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            line-height: 1.7;
        }
        .file-input {
            position: relative;
            display: block;
            padding: 24px;
            background: var(--bg-elevated);
            border: 2px dashed var(--border-strong);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
        }
        .file-input:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }
        .file-input input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .file-input-icon { color: var(--accent); margin-bottom: 8px; }
        .file-input-icon svg { width: 32px; height: 32px; }
        .file-input-text {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .file-input-hint { font-size: 12px; color: var(--text-muted); }


        .field-progress {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0 16px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .field-progress-bar {
            flex: 1;
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 999px;
            overflow: hidden;
        }
        .field-progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 999px;
            transition: width 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }


        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: 1px solid;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            min-height: 44px;
        }
        .btn svg { width: 16px; height: 16px; }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg-base);
        }
        .btn-primary:hover {
            background: var(--accent-strong);
            border-color: var(--accent-strong);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px var(--accent-shadow);
        }
        .btn-secondary {
            background: var(--bg-elevated);
            border-color: var(--border-strong);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: var(--bg-surface);
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-success {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
        .btn-success:hover {
            background: rgba(34, 197, 94, 0.25);
            color: #bbf7d0;
        }
        .btn-grow { flex: 1 1 auto; min-width: 140px; }
        .btn[disabled], .btn[hidden] { display: none; }
        .install-submit { margin-top: 20px; }
        .install-submit .btn { width: 100%; padding: 16px; font-size: 16px; }


        .field-step { display: none; }
        .field-step.is-active {
            display: block;
            animation: fadeSlide 240ms ease-out;
        }
        details {
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin-bottom: 12px;
        }
        details[open] { padding-bottom: 18px; }
        summary {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }
        summary::-webkit-details-marker { display: none; }
        summary svg { width: 16px; height: 16px; color: var(--accent); }
        details > label:not(summary) {
            display: block;
            margin-top: 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        details > input { margin-top: 8px; }
        .warn-block {
            display: flex;
            gap: 12px;
            padding: 16px;
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-md);
            color: #fcd34d;
            font-size: 13px;
            line-height: 1.7;
        }
        .warn-block svg {
            flex-shrink: 0;
            width: 20px; height: 20px;
            color: #f59e0b;
            margin-top: 2px;
        }


        .checkbox-block {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding: 14px 16px;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: var(--radius-md);
            cursor: pointer;
            user-select: none;
            transition: background var(--transition);
        }
        .checkbox-block:hover {
            background: rgba(239, 68, 68, 0.18);
        }
        [data-theme="blue"]   .checkbox-block:hover { background: rgba(91, 158, 255, 0.18); }
        [data-theme="purple"] .checkbox-block:hover { background: rgba(183, 148, 246, 0.18); }
        [data-theme="green"]  .checkbox-block:hover { background: rgba(104, 211, 145, 0.18); }
        [data-theme="orange"] .checkbox-block:hover { background: rgba(255, 146, 72, 0.18); }
        .checkbox-block input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-block span {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }


        .success-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
            padding: 16px 24px;
            background: var(--accent);
            color: var(--bg-base);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all var(--transition);
        }
        .success-cta:hover {
            background: var(--accent-strong);
            transform: translateY(-1px);
            box-shadow: 0 12px 28px var(--accent-shadow);
        }
        .success-cta svg { width: 22px; height: 22px; }
        .success-message {
            text-align: center;
            margin-top: 24px;
            color: var(--text-secondary);
        }
        .success-message p { margin-bottom: 4px; }


        @media (max-width: 640px) {
            .app-header-inner { padding: 12px 16px; }
            .app-main { padding: 24px 16px 60px; }
            .card { padding: 20px; }
            .step-divider { display: none; }
            .stepper { gap: 6px; }
            .step { padding: 6px 10px; }
            .step-label { display: none; }
            .step.is-active .step-label { display: inline; font-size: 12px; }
            .choice-grid { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column-reverse; }
            .btn-row .btn { width: 100%; }
            .theme-switcher { padding: 3px; gap: 4px; }
            .theme-swatch { width: 20px; height: 20px; }
        }


        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-cog" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </symbol>
            <symbol id="i-server" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2"/>
                <rect x="2" y="14" width="20" height="8" rx="2"/>
                <line x1="6" y1="6" x2="6.01" y2="6"/>
                <line x1="6" y1="18" x2="6.01" y2="18"/>
            </symbol>
            <symbol id="i-cloud" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>
            </symbol>
            <symbol id="i-cogs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 1v6m0 10v6"/>
                <path d="m4.22 4.22 4.24 4.24m7.08 7.08 4.24 4.24"/>
                <path d="M1 12h6m10 0h6"/>
                <path d="m4.22 19.78 4.24-4.24m7.08-7.08 4.24-4.24"/>
            </symbol>
            <symbol id="i-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </symbol>
            <symbol id="i-upgrade" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"/>
                <polyline points="5 12 12 5 19 12"/>
            </symbol>
            <symbol id="i-database" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </symbol>
            <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </symbol>
            <symbol id="i-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/>
            </symbol>
            <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </symbol>
            <symbol id="i-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </symbol>
            <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </symbol>
            <symbol id="i-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </symbol>
            <symbol id="i-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </symbol>
            <symbol id="i-archive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="21 8 21 21 3 21 3 8"/>
                <rect x="1" y="3" width="22" height="5"/>
                <line x1="10" y1="12" x2="14" y2="12"/>
            </symbol>
            <symbol id="i-help" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </symbol>
            <symbol id="i-rocket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
                <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
                <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/>
                <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
            </symbol>
            <symbol id="i-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/>
                <polyline points="12 5 19 12 12 19"/>
            </symbol>
            <symbol id="i-arrow-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </symbol>
            <symbol id="i-warn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </symbol>
            <symbol id="i-x-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </symbol>
            <symbol id="i-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>
                <circle cx="12" cy="5" r="2"/>
                <path d="M12 7v4"/>
                <line x1="8" y1="16" x2="8" y2="16"/>
                <line x1="16" y1="16" x2="16" y2="16"/>
            </symbol>
            <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </symbol>
            <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </symbol>
            <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </symbol>
        </defs>
    </svg>

    <div class="app-shell">
        
        <header class="app-header">
            <div class="app-header-inner">
                <a class="brand" href="#">
                    <span class="brand-mark"><svg><use href="#i-cog"/></svg></span>
                    <span>رد فاکس</span>
                </a>
                <div class="theme-switcher" role="radiogroup" aria-label="انتخاب رنگ پنل">
                    <button type="button" class="theme-swatch is-active" data-theme="red"    role="radio" aria-checked="true"  aria-label="قرمز"></button>
                    <button type="button" class="theme-swatch"           data-theme="blue"   role="radio" aria-checked="false" aria-label="آبی"></button>
                    <button type="button" class="theme-swatch"           data-theme="purple" role="radio" aria-checked="false" aria-label="بنفش"></button>
                    <button type="button" class="theme-swatch"           data-theme="green"  role="radio" aria-checked="false" aria-label="سبز"></button>
                    <button type="button" class="theme-swatch"           data-theme="orange" role="radio" aria-checked="false" aria-label="نارنجی"></button>
                </div>
            </div>
        </header>

        
        <main class="app-main">
            <div class="hero">
                <div class="hero-badge">
                    <span class="pulse-dot"></span>
                    <span>نصب کننده‌ی هوشمند</span>
                </div>
                <h1>نصب خودکار <span class="accent">ربات رد فاکس</span></h1>
                <p>تنها چند مرحله ساده تا راه‌اندازی کامل ربات شما — بدون نیاز به دانش فنی پیچیده.</p>
            </div>

            <?php if (!empty($ERROR)): ?>
                <div class="alert alert-danger">
                    <svg><use href="#i-x-circle"/></svg>
                    <div><?php echo implode("<br>", $ERROR); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="card">
                    <div class="alert alert-success">
                        <svg><use href="#i-check-circle"/></svg>
                        <div><?php echo implode("<br>", $SUCCESS); ?></div>
                    </div>
                    <?php if ($webhookPending): ?>
                        <div class="alert alert-warning">
                            <svg><use href="#i-warn"/></svg>
                            <div>
                                <strong>نصب پنل و حساب مدیر حفظ و تکمیل شد؛ Webhook نیازمند تعمیر است.</strong><br>
                                <?= nl2br(escapeHtml($webhookPendingMessage)) ?><br>
                                <a href="../panel/bot_settings.php">ورود به پنل و اجرای «تعمیر Webhook»</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a class="success-cta" href="https://t.me/<?php echo escapeHtml((string)$tgBot['details']['result']['username']); ?>">
                        <svg><use href="#i-bot"/></svg>
                        رفتن به ربات <?php echo "@" . $tgBot['details']['result']['username']; ?>
                        <svg><use href="#i-arrow-left"/></svg>
                    </a>

                    <div class="success-message">
                        <p><strong>نصب با موفقیت تکمیل شد!</strong></p>
                        <p style="color: var(--text-muted); font-size: 13px;">پوشه‌ی <code>installer</code> به‌صورت خودکار در حال حذف است — برای امنیت سرور.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form id="installer-form" <?php if ($success) echo 'style="display:none"'; ?> method="post" enctype="multipart/form-data">
                <?= redfox_csrf_field() ?>

                <!-- Only the simple cPanel-host install remains. Server type and
                     install type are fixed and submitted as hidden values. -->
                <input type="hidden" name="server_type" value="cpanel">
                <input type="hidden" name="install_type" value="simple">

                
                <section class="step-section is-active" id="step-1" aria-labelledby="step-3-title">
                    <div class="card">
                        <h2 class="card-title" id="step-3-title">
                            <svg><use href="#i-database"/></svg>
                            اطلاعات نصب
                        </h2>

                        <div class="field-step <?php echo $currentInstallField === 1 ? 'is-active' : ''; ?>" data-field-step="1">
                            <div class="form-field">
                                <label class="form-label" for="admin_id"><svg><use href="#i-user"/></svg> آیدی عددی ادمین</label>
                                <input class="form-input" type="text" id="admin_id" name="admin_id" placeholder="ADMIN TELEGRAM #ID" value="<?php echo escapeHtml($uPOST['admin_id'] ?? ''); ?>" required>
                                <p class="form-hint">می‌توانید آیدی عددی خود را از ربات <code>@userinfobot</code> بگیرید.</p>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 2 ? 'is-active' : ''; ?>" data-field-step="2">
                            <div class="form-field">
                                <label class="form-label" for="tg_bot_token"><svg><use href="#i-key"/></svg> توکن ربات تلگرام</label>
                                <input class="form-input" type="password" id="tg_bot_token" name="tg_bot_token" placeholder="BOT_TOKEN_FROM_BOTFATHER" value="" minlength="27" maxlength="128" autocomplete="new-password" autocapitalize="off" spellcheck="false" dir="ltr" required>
                                <p class="form-hint">توکن را مستقیماً از <code>@BotFather</code> کپی کنید. پس از خطا، برای امنیت دوباره واردش کنید.</p>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 3 ? 'is-active' : ''; ?>" data-field-step="3">
                            <div class="form-field">
                                <label class="form-label" for="database_username"><svg><use href="#i-user"/></svg> نام کاربری دیتابیس</label>
                                <input class="form-input" type="text" id="database_username" name="database_username" placeholder="DATABASE USERNAME" value="<?php echo escapeHtml($uPOST['database_username'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 4 ? 'is-active' : ''; ?>" data-field-step="4">
                            <div class="form-field">
                                <label class="form-label" for="database_password"><svg><use href="#i-lock"/></svg> رمز عبور دیتابیس</label>
                                <input class="form-input" type="password" id="database_password" name="database_password" placeholder="DATABASE PASSWORD" value="" maxlength="1024" autocomplete="new-password" dir="ltr" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 5 ? 'is-active' : ''; ?>" data-field-step="5">
                            <div class="form-field">
                                <label class="form-label" for="database_name"><svg><use href="#i-database"/></svg> نام دیتابیس</label>
                                <input class="form-input" type="text" id="database_name" name="database_name" placeholder="DATABASE NAME" value="<?php echo escapeHtml($uPOST['database_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 6 ? 'is-active' : ''; ?>" data-field-step="6">
                            <div class="form-field">
                                <details>
                                    <summary><svg><use href="#i-globe"/></svg> آدرس سورس ربات (پیشرفته)</summary>
                                    <label for="bot_address_webhook">آدرس صفحه‌ی سورس ربات (نه installer)</label>
                                    <input class="form-input" type="text" id="bot_address_webhook" name="bot_address_webhook" placeholder="https://yourdomain.com/path/index.php" value="<?php echo escapeHtml($uPOST['bot_address_webhook'] ?? ($webAddress . '/index.php')); ?>" required>
                                </details>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 7 ? 'is-active' : ''; ?>" data-field-step="7">
                            <div class="warn-block">
                                <svg><use href="#i-warn"/></svg>
                                <div>
                                    <strong>هشدار:</strong> پس از نصب موفقیت‌آمیز، پوشه‌ی <code>installer</code> به‌صورت <strong>خودکار حذف</strong> خواهد شد. این کار برای حفظ امنیت سرور انجام می‌شود.
                                </div>
                            </div>
                        </div>

                        <div class="field-progress">
                            <span>فیلد <span id="install-progress-text"><?php echo $currentInstallField; ?></span> از <?php echo $installFieldTotal; ?></span>
                            <div class="field-progress-bar">
                                <div class="field-progress-fill" id="field-progress-fill" style="width: <?php echo round($currentInstallField * 100 / $installFieldTotal); ?>%"></div>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn btn-secondary" id="install-prev-btn" <?php echo $currentInstallField <= 1 ? 'hidden' : ''; ?>>
                                <svg><use href="#i-arrow-right"/></svg>
                                فیلد قبل
                            </button>
                            <button type="button" class="btn btn-primary btn-grow" id="install-next-btn" <?php echo $currentInstallField >= $installFieldTotal ? 'hidden' : ''; ?>>
                                فیلد بعد
                                <svg><use href="#i-arrow-left"/></svg>
                            </button>
                        </div>
                        <div class="install-submit" id="install-submit" style="<?php echo $currentInstallField >= $installFieldTotal ? '' : 'display:none'; ?>">
                            <button type="submit" name="submit" value="submit" class="btn btn-primary">
                                <svg><use href="#i-rocket"/></svg>
                                شروع نصب ربات
                            </button>
                        </div>
                    </div>
                </section>

                
                <div class="btn-row" style="display:none;">
                    <button type="button" class="btn btn-secondary" id="prev-btn" hidden>
                        <svg><use href="#i-arrow-right"/></svg>
                        مرحله قبل
                    </button>
                    <button type="button" class="btn btn-primary btn-grow" id="next-btn" hidden>
                        مرحله بعد
                        <svg><use href="#i-arrow-left"/></svg>
                    </button>
                </div>

                <input type="hidden" name="current_step"          id="current_step"          value="<?php echo (int) $currentStep; ?>">
                <input type="hidden" name="current_install_field" id="current_install_field" value="<?php echo (int) $currentInstallField; ?>">
            </form>
        </main>

        
        <footer class="app-footer">
            <p>
                Red Fox Installer
                ·
                <a href="https://github.com/hojjatrad/RedFox-Security-Hardened" target="_blank" rel="noopener">گیت‌هاب</a>
                ·
                <a href="https://t.me/red fox" target="_blank" rel="noopener">تلگرام</a>
                ·
                &copy; <?php echo date('Y'); ?>
            </p>
        </footer>
    </div>

    <script>
    (function () {
        'use strict';


        const TOTAL_WIZARD_STEPS  = 1;
        const TOTAL_INSTALL_FIELDS_INITIAL = <?php echo (int) $installFieldTotal; ?>;
        const THEME_STORAGE_KEY   = 'redfox_installer_theme';

        let currentStep         = <?php echo (int) $currentStep; ?>;
        let currentInstallField = <?php echo (int) $currentInstallField; ?>;
        let installFieldSteps   = [];
        let totalInstallFields  = TOTAL_INSTALL_FIELDS_INITIAL;


        const $ = (sel, root) => (root || document).querySelector(sel);
        const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));


        function applyTheme(theme) {
            const validThemes = ['red', 'blue', 'purple', 'green', 'orange'];
            if (!validThemes.includes(theme)) theme = 'red';
            document.documentElement.setAttribute('data-theme', theme);
            try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {  }
            $$('.theme-swatch').forEach(swatch => {
                const isActive = swatch.dataset.theme === theme;
                swatch.classList.toggle('is-active', isActive);
                swatch.setAttribute('aria-checked', isActive ? 'true' : 'false');
            });
        }

        function initThemeSwitcher() {
            let saved = 'red';
            try { saved = localStorage.getItem(THEME_STORAGE_KEY) || 'red'; } catch (e) {}
            applyTheme(saved);
            $$('.theme-swatch').forEach(swatch => {
                swatch.addEventListener('click', () => applyTheme(swatch.dataset.theme));
            });
        }


        function changeInstallField(delta) {
            if (!installFieldSteps.length) return;
            if (delta > 0 && !validateInstallField(currentInstallField)) return;
            const next = currentInstallField + delta;
            if (next < 1 || next > totalInstallFields) return;
            currentInstallField = next;
            updateInstallFieldDisplay();
        }

        function validateInstallField(stepIndex) {
            const step = installFieldSteps[stepIndex - 1];
            if (!step) return true;
            const inputs = step.querySelectorAll('input[required], select[required], textarea[required]');
            for (const input of inputs) {
                const textTypes = ['text', 'password', 'tel', 'email', 'url', 'search', 'number'];
                if (textTypes.includes(input.type) && input.value.trim() === '') {
                    input.reportValidity();
                    return false;
                }
                if ((input.tagName === 'SELECT' || input.tagName === 'TEXTAREA') && input.value.trim() === '') {
                    input.reportValidity();
                    return false;
                }
                if (input.type === 'file' && !input.files.length) {
                    input.reportValidity();
                    return false;
                }
                if (input.type === 'checkbox' && !input.checked) {
                    input.reportValidity();
                    return false;
                }
            }
            return true;
        }

        function updateInstallFieldDisplay() {
            if (!installFieldSteps.length) return;
            currentInstallField = Math.max(1, Math.min(totalInstallFields, currentInstallField));
            installFieldSteps.forEach((step, idx) => {
                step.classList.toggle('is-active', idx === currentInstallField - 1);
            });
            const prevBtn      = $('#install-prev-btn');
            const nextBtn      = $('#install-next-btn');
            const submitBlock  = $('#install-submit');
            const progressText = $('#install-progress-text');
            const progressFill = $('#field-progress-fill');
            const hiddenField  = $('#current_install_field');
            if (prevBtn)     prevBtn.hidden     = currentInstallField === 1;
            if (nextBtn)     nextBtn.hidden     = currentInstallField === totalInstallFields;
            if (submitBlock) submitBlock.style.display = currentInstallField === totalInstallFields ? '' : 'none';
            if (progressText) progressText.textContent = currentInstallField;
            if (progressFill) progressFill.style.width = Math.round(currentInstallField * 100 / totalInstallFields) + '%';
            if (hiddenField) hiddenField.value = currentInstallField;
        }


        function updateWizardDisplay() {
            currentStep = Math.max(1, Math.min(TOTAL_WIZARD_STEPS, currentStep));
            $$('.step').forEach(step => {
                const stepNum = parseInt(step.dataset.step, 10);
                step.classList.remove('is-active', 'is-completed');
                if (stepNum === currentStep) step.classList.add('is-active');
                else if (stepNum < currentStep) step.classList.add('is-completed');
            });
            $$('.step-section').forEach(section => {
                section.classList.toggle('is-active', section.id === 'step-' + currentStep);
            });
            if (currentStep === TOTAL_WIZARD_STEPS) updateInstallFieldDisplay();
            const prevBtn = $('#prev-btn');
            const nextBtn = $('#next-btn');
            if (prevBtn) prevBtn.hidden = currentStep === 1;
            if (nextBtn) nextBtn.hidden = currentStep === TOTAL_WIZARD_STEPS;
            const hiddenStep = $('#current_step');
            if (hiddenStep) hiddenStep.value = currentStep;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }


        function initFileInputFeedback() {
            const fileInput   = $('#backup_file');
            const fileDisplay = $('#file-name-display');
            if (!fileInput || !fileDisplay) return;
            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files.length > 0) {
                    fileDisplay.textContent = fileInput.files[0].name;
                } else {
                    fileDisplay.textContent = 'انتخاب فایل بکاپ';
                }
            });
        }


        document.addEventListener('DOMContentLoaded', () => {
            initThemeSwitcher();
            initFileInputFeedback();

            installFieldSteps = $$('.field-step');
            if (installFieldSteps.length) totalInstallFields = installFieldSteps.length;

            updateInstallFieldDisplay();
            updateWizardDisplay();


            const installNextBtn = $('#install-next-btn');
            const installPrevBtn = $('#install-prev-btn');
            if (installNextBtn) installNextBtn.addEventListener('click', () => changeInstallField(1));
            if (installPrevBtn) installPrevBtn.addEventListener('click', () => changeInstallField(-1));

            const nextBtn = $('#next-btn');
            const prevBtn = $('#prev-btn');
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (currentStep < TOTAL_WIZARD_STEPS) {
                        currentStep++;
                        updateWizardDisplay();
                    }
                });
            }
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (currentStep > 1) {
                        currentStep--;
                        updateWizardDisplay();
                    }
                });
            }


            const installerForm = $('#installer-form');
            if (installerForm) {
                installerForm.addEventListener('submit', (event) => {
                    const stepField  = $('#current_step');
                    const fieldField = $('#current_install_field');
                    if (stepField)  stepField.value  = currentStep;
                    if (fieldField) fieldField.value = currentInstallField;
                });
            }
        });


    })();
</script>
</body>
</html>
<?php


function scheduleInstallerSelfDelete(string $installerDir): void {
    static $scheduled = false;
    if ($scheduled) {
        return;
    }
    $scheduled = true;

    @ignore_user_abort(true);

    register_shutdown_function(static function () use ($installerDir): void {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        @set_time_limit(60);

        if (!is_dir($installerDir) || !is_writable($installerDir)) {
            return;
        }

        $rrmdir = static function ($dir) use (&$rrmdir): void {
            if (!is_dir($dir)) {
                return;
            }
            $items = @scandir($dir);
            if ($items === false) {
                return;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path) && !is_link($path)) {
                    $rrmdir($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        };

        $rrmdir($installerDir);
    });
}

function telegramInstallerApiBase(): ?string {
    $base = trim((string)(rx_env('REDFOX_TELEGRAM_API_BASE', 'https://api.telegram.org') ?: 'https://api.telegram.org'));
    if ($base === '' || strlen($base) > 2048 || preg_match('/[\x00-\x20]/', $base)) return null;
    $parts = parse_url($base);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) return null;
    $host = strtolower(rtrim((string)$parts['host'], '.'));
    if (!filter_var($host, FILTER_VALIDATE_IP)
        && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) return null;
    $port = isset($parts['port']) ? (int)$parts['port'] : 443;
    if (!in_array($port, [443, 8443], true)) return null;
    $path = (string)($parts['path'] ?? '');
    if ($path !== '' && !preg_match('#^/[A-Za-z0-9._~/-]*$#', $path)) return null;
    $authority = str_contains($host, ':') ? '[' . $host . ']' : $host;
    if ($port !== 443) $authority .= ':' . $port;
    return 'https://' . $authority . rtrim($path, '/');
}

function telegramApiRequest(string $token, string $method, array $parameters = [], int $attemptLimit = 2): array {
    if (!isValidTelegramToken($token) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $method)) {
        return ['ok' => false, 'failure' => 'invalid_request', 'description' => 'invalid request'];
    }
    $apiBase = telegramInstallerApiBase();
    if ($apiBase === null) {
        return ['ok' => false, 'failure' => 'endpoint_blocked', 'description' => 'invalid Telegram API endpoint'];
    }
    $url = $apiBase . '/bot' . $token . '/' . $method;
    $body = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    $response = false;
    $httpCode = 0;
    $attempts = 0;
    $curlErrno = 0;
    $tooLarge = false;
    $maxResponseBytes = 1048576;

    if (function_exists('curl_init')) {
        $retryable = [5, 6, 7, 28, 35, 52, 55, 56];
        $maxAttempts = max(1, min(3, $attemptLimit));
        while ($attempts < $maxAttempts) {
            $attempts++;
            $captured = '';
            $tooLarge = false;
            $curl = curl_init($url);
            if ($curl === false) {
                return ['ok' => false, 'failure' => 'transport', 'description' => 'cURL initialization failed'];
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
                CURLOPT_USERAGENT => 'RedFox-Installer/2.4',
                CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$captured, &$tooLarge, $maxResponseBytes): int {
                    if (strlen($captured) + strlen($chunk) > $maxResponseBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                    $captured .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $policy = redfox_apply_curl_url_policy($curl, $url, false, false);
            if (empty($policy['ok'])) {
                curl_close($curl);
                error_log('[installer] Telegram endpoint rejected by outbound URL policy');
                return ['ok' => false, 'failure' => 'endpoint_blocked', 'description' => 'Telegram endpoint blocked by policy'];
            }

            $executed = curl_exec($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($curl);
            curl_close($curl);
            if ($tooLarge) {
                error_log('[installer] Telegram response exceeded safe size (method: ' . preg_replace('/[^A-Za-z0-9_]/', '_', $method) . ')');
                return ['ok' => false, 'failure' => 'response_too_large', 'description' => 'Telegram response too large'];
            }
            if ($executed !== false) {
                $response = $captured;
                break;
            }
            if (!in_array($curlErrno, $retryable, true) || $attempts >= $maxAttempts) break;
            usleep(250000);
        }

        if (!is_string($response)) {
            $failure = telegramInstallerCurlFailure($curlErrno);
            error_log(sprintf('[installer] Telegram transport failure (method: %s, failure: %s, errno: %d, attempts: %d)',
                preg_replace('/[^A-Za-z0-9_]/', '_', $method), $failure, $curlErrno, $attempts));
            return [
                'ok' => false,
                'failure' => $failure,
                'transport_code' => $curlErrno,
                'attempts' => $attempts,
                'description' => 'Telegram transport failed',
            ];
        }
    } else {
        // The stream fallback is intentionally restricted to Telegram's fixed
        // official endpoint because it cannot pin DNS like cURL can.
        if ($apiBase !== 'https://api.telegram.org') {
            return ['ok' => false, 'failure' => 'curl_unavailable', 'description' => 'cURL required for custom Telegram API endpoint'];
        }
        $policy = redfox_outbound_url_policy($url, false, false);
        if (empty($policy['ok'])) {
            return ['ok' => false, 'failure' => 'endpoint_blocked', 'description' => 'Telegram endpoint blocked by policy'];
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: RedFox-Installer/2.4\r\nConnection: close\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => 'api.telegram.org',
            ],
        ]);
        $response = @file_get_contents($url, false, $context, 0, $maxResponseBytes + 1);
        foreach (($http_response_header ?? []) as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string)$headerLine, $match)) $httpCode = (int)$match[1];
        }
        if (!is_string($response)) {
            error_log('[installer] Telegram HTTPS stream transport failed (method: ' . preg_replace('/[^A-Za-z0-9_]/', '_', $method) . ')');
            return ['ok' => false, 'failure' => 'stream_transport', 'description' => 'Telegram HTTPS stream failed'];
        }
        if (strlen($response) > $maxResponseBytes) {
            return ['ok' => false, 'failure' => 'response_too_large', 'description' => 'Telegram response too large'];
        }
    }

    if ($response === '') {
        return ['ok' => false, 'failure' => 'empty_response', 'http_status' => $httpCode, 'description' => 'empty Telegram response'];
    }
    $decoded = json_decode($response, true, 32, JSON_BIGINT_AS_STRING);
    if (!is_array($decoded) || !array_key_exists('ok', $decoded) || !is_bool($decoded['ok'])) {
        error_log(sprintf('[installer] Invalid Telegram response (method: %s, HTTP: %d, bytes: %d)',
            preg_replace('/[^A-Za-z0-9_]/', '_', $method), $httpCode, strlen($response)));
        return ['ok' => false, 'failure' => 'invalid_response', 'http_status' => $httpCode, 'description' => 'invalid Telegram response'];
    }
    if ($decoded['ok'] !== true) {
        $errorCode = isset($decoded['error_code']) && is_numeric($decoded['error_code']) ? (int)$decoded['error_code'] : $httpCode;
        $upstreamDescription = isset($decoded['description']) && is_string($decoded['description'])
            ? substr(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $decoded['description']) ?: '', 0, 1000)
            : '';
        $descriptionFingerprint = substr(hash('sha256', $upstreamDescription), 0, 16);
        error_log(sprintf('[installer] Telegram API rejected request (method: %s, API code: %d, HTTP: %d, reason_ref: %s)',
            preg_replace('/[^A-Za-z0-9_]/', '_', $method), $errorCode, $httpCode, $descriptionFingerprint));
        return [
            'ok' => false,
            'failure' => 'telegram_api',
            'error_code' => $errorCode,
            'http_status' => $httpCode,
            // Internal-only input for the strict allow-list classifier. Never
            // render or log this upstream text verbatim.
            'upstream_description' => $upstreamDescription,
            'description' => 'Telegram API rejected request',
        ];
    }
    return $decoded;
}

function telegramInstallerCurlFailure(int $errno): string {
    if (in_array($errno, [5, 6], true)) return 'dns';
    if ($errno === 7) return 'connect';
    if ($errno === 28) return 'timeout';
    if (in_array($errno, [35, 51, 53, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true)) return 'tls';
    if (in_array($errno, [52, 55, 56], true)) return 'connection_reset';
    return 'transport';
}

function telegramInstallerConfigureWebhook(string $token, string $url, string $secret): array {
    $validation = redfox_validate_telegram_webhook_url($url);
    if (empty($validation['ok'])) {
        $code = preg_match('/^TG-WH-[A-Z0-9_-]+$/', (string)($validation['code'] ?? ''))
            ? (string)$validation['code'] : 'TG-WH-URL';
        return [
            'ok' => false,
            'status' => 'pending',
            'error' => [
                'code' => $code,
                'message' => (string)($validation['message'] ?? 'URL وب‌هوک معتبر نیست.'),
                'action' => 'دامنه، مسیر نصب و پورت HTTPS را اصلاح و سپس از پنل تعمیر Webhook را اجرا کنید.',
                'api_code' => 0,
                'fingerprint' => substr(hash('sha256', $code . '|' . $url), 0, 16),
                'retryable' => false,
            ],
            'preflight' => $validation,
        ];
    }

    // This probe is diagnostic only. Some hosts cannot connect to their own
    // public IP (NAT loopback), while Telegram still can; setWebhook remains
    // the authoritative operation.
    $probe = redfox_probe_telegram_webhook_endpoint($url, 8);
    if (empty($probe['ok'])) {
        error_log('[installer] webhook endpoint preflight warning code=' . preg_replace('/[^A-Z0-9_-]/', '', (string)($probe['code'] ?? 'unknown')));
    }

    $primary = telegramApiRequest($token, 'setWebhook', [
        'url' => $url,
        'secret_token' => $secret,
        'drop_pending_updates' => 'false',
    ], 2);
    $accepted = !empty($primary['ok']);
    $finalResponse = $primary;

    // One bounded fallback sends only Telegram's mandatory security fields.
    // It covers proxies/API-compatible gateways that reject optional fields.
    if (!$accepted && (int)($primary['error_code'] ?? 0) !== 401) {
        $minimal = telegramApiRequest($token, 'setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
        ], 1);
        if (!empty($minimal['ok'])) {
            $accepted = true;
            $finalResponse = $minimal;
        } else {
            $finalResponse = $minimal;
        }
    }

    // A lost setWebhook response must not overwrite a setup Telegram actually
    // accepted. getWebhookInfo is an independent, idempotent confirmation.
    $info = telegramApiRequest($token, 'getWebhookInfo', [], 1);
    $reportedUrl = is_array($info['result'] ?? null) ? (string)($info['result']['url'] ?? '') : '';
    $verified = !empty($info['ok']) && $reportedUrl !== '' && hash_equals($url, $reportedUrl);
    if ($accepted || $verified) {
        return [
            'ok' => true,
            'status' => 'active',
            'verified' => $verified,
            'response' => $finalResponse,
            'info' => $info,
            'preflight' => $validation,
            'probe' => $probe,
        ];
    }

    $transport = (string)($finalResponse['failure'] ?? '');
    $error = redfox_telegram_webhook_error($finalResponse, $transport);
    return [
        'ok' => false,
        'status' => 'pending',
        'error' => $error,
        'response' => $finalResponse,
        'info' => $info,
        'preflight' => $validation,
        'probe' => $probe,
    ];
}

function telegramInstallerErrorMessage(array $result, string $operation): string {
    $failure = (string)($result['failure'] ?? 'unknown');
    $code = (int)($result['error_code'] ?? $result['http_status'] ?? 0);
    if ($failure === 'telegram_api' && $code === 401) {
        return '<b>توکن توسط تلگرام رد شد.</b> توکن لغو یا اشتباه است؛ از <code>@BotFather</code> توکن جدید بگیرید. <code>TG-AUTH</code>';
    }
    if ($failure === 'telegram_api' && $code === 429) {
        return '<b>محدودیت موقت تلگرام فعال شده است.</b> چند دقیقه صبر کنید و دوباره تلاش کنید. <code>TG-RATE</code>';
    }
    $messages = [
        'invalid_request' => '<b>قالب توکن یا درخواست نامعتبر است.</b> توکن را بدون فاصله اضافی وارد کنید. <code>TG-INPUT</code>',
        'endpoint_blocked' => '<b>مقصد Telegram API با سیاست شبکه سازگار نیست.</b> DNS سرور و مقدار <code>REDFOX_TELEGRAM_API_BASE</code> را بررسی کنید. <code>TG-POLICY</code>',
        'dns' => '<b>نام دامنه Telegram API resolve نشد.</b> DNS هاست را بررسی کنید یا از پشتیبانی هاست بخواهید دسترسی خروجی را فعال کند. <code>TG-DNS</code>',
        'connect' => '<b>اتصال خروجی به تلگرام برقرار نشد.</b> فایروال هاست، محدودیت کشور یا پراکسی خروجی را بررسی کنید. <code>TG-CONNECT</code>',
        'timeout' => '<b>ارتباط با تلگرام timeout شد.</b> دسترسی خروجی پورت 443 یا پراکسی هاست را بررسی و دوباره تلاش کنید. <code>TG-TIMEOUT</code>',
        'tls' => '<b>اعتبارسنجی TLS تلگرام ناموفق بود.</b> بسته CA و نسخه cURL/OpenSSL هاست باید به‌روز شود؛ TLS را غیرفعال نکنید. <code>TG-TLS</code>',
        'connection_reset' => '<b>ارتباط با تلگرام در میانه راه قطع شد.</b> مسیر شبکه یا پراکسی ناپایدار است. دوباره تلاش کنید. <code>TG-RESET</code>',
        'response_too_large' => '<b>پاسخ غیرعادی و بیش از حد بزرگ دریافت شد.</b> پراکسی یا مسیر شبکه را بررسی کنید. <code>TG-SIZE</code>',
        'invalid_response' => '<b>پاسخ معتبر JSON از تلگرام دریافت نشد.</b> احتمالاً پراکسی یا فایروال صفحه دیگری برگردانده است. <code>TG-RESPONSE</code>',
        'empty_response' => '<b>تلگرام پاسخ خالی برگرداند.</b> پراکسی، WAF یا مسیر خروجی هاست را بررسی کنید. <code>TG-EMPTY</code>',
        'curl_unavailable' => '<b>افزونه cURL برای این تنظیم لازم است.</b> PHP cURL را در هاست فعال کنید. <code>TG-CURL</code>',
        'stream_transport' => '<b>ارتباط HTTPS جایگزین با تلگرام برقرار نشد.</b> PHP cURL را فعال و دسترسی خروجی را بررسی کنید. <code>TG-STREAM</code>',
        'transport' => '<b>خطای شبکه در ارتباط با تلگرام رخ داد.</b> گزارش خطای امن سرور و تنظیمات cURL را بررسی کنید. <code>TG-NETWORK</code>',
    ];
    if (isset($messages[$failure])) return $messages[$failure];
    if ($failure === 'telegram_api') {
        return '<b>Telegram API درخواست را نپذیرفت.</b> کد پاسخ: <code>' . $code . '</code>. توکن و دسترسی ربات را بررسی کنید. <code>TG-API</code>';
    }
    $safeOperation = preg_replace('/[^A-Za-z0-9_]/', '', $operation);
    return '<b>اعتبارسنجی ربات کامل نشد.</b> گزارش امن سرور را با کد <code>TG-UNKNOWN-' . escapeHtml($safeOperation) . '</code> بررسی کنید.';
}
function isValidTelegramToken($token): bool {
    return is_string($token) && strlen($token) <= 128
        && preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,100}$/', $token) === 1;
}
function isValidTelegramId($id): bool {
    return is_string($id) && preg_match('/^[1-9]\d{4,19}$/', $id) === 1;
}
function sanitizeInput(&$INPUT, array $options = []) {
    $defaultOptions = [
        'allow_html' => false,
        'allowed_tags' => '',
        'remove_spaces' => false,
        'connection' => null,
        'max_length' => 0,
        'encoding' => 'UTF-8'
    ];
    $options = array_merge($defaultOptions, $options);
    if (is_array($INPUT)) {
        return array_map(function($item) use ($options) {
            return sanitizeInput($item, $options);
        }, $INPUT);
    }
    if ($INPUT === null || $INPUT === false) {
        return '';
    }
    $INPUT = trim((string)$INPUT);
    $INPUT = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $INPUT);
    if ($options['max_length'] > 0) {
        $INPUT = mb_substr($INPUT, 0, $options['max_length'], $options['encoding']);
    }
    if (!$options['allow_html']) {
        $INPUT = strip_tags($INPUT);
    } elseif (!empty($options['allowed_tags'])) {
        $INPUT = strip_tags($INPUT, $options['allowed_tags']);
    }
    if ($options['remove_spaces']) {
        $INPUT = preg_replace('/\s+/', ' ', trim($INPUT));
    }
    if ($options['connection'] instanceof mysqli) {
        $INPUT = $options['connection']->real_escape_string($INPUT);
    }
    return $INPUT;
}
function normalizeDomainAddress($url) {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20]/', $url) || str_contains($url, '\\')) return null;
    if (!preg_match('#^https://#i', $url)) {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) return null;
        $url = 'https://' . $url;
    }
    $parsedUrl = parse_url($url);
    if (!is_array($parsedUrl) || strtolower((string)($parsedUrl['scheme'] ?? '')) !== 'https'
        || empty($parsedUrl['host']) || isset($parsedUrl['user']) || isset($parsedUrl['pass'])
        || isset($parsedUrl['query']) || isset($parsedUrl['fragment'])) return null;
    $port = isset($parsedUrl['port']) ? (int)$parsedUrl['port'] : 443;
    if (!in_array($port, [80, 88, 443, 8443], true)) return null;
    $path = (string)($parsedUrl['path'] ?? '');
    $path = preg_replace('#/index\.php/?$#i', '', $path) ?? '';
    $path = preg_replace('#/installer/?$#i', '', $path) ?? '';
    $host = (string)$parsedUrl['host'];
    if (str_contains($host, ':')) $host = '[' . $host . ']';
    $candidate = $host . ($port !== 443 ? ':' . $port : '') . ($path !== '' ? '/' . trim($path, '/') : '');
    $address = redfox_normalize_domain($candidate);
    return $address === '' ? null : ['address' => $address];
}
function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
