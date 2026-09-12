<?php

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}


register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;

    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    error_log('[panel-login fatal] type ' . (int)($err['type'] ?? 0) . ' @ ' . basename((string)($err['file'] ?? 'unknown')) . ':' . (int)($err['line'] ?? 0));
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8">'
       . '<title>خطای سرور</title>'
       . '<body style="font-family:sans-serif;background:#0a0a0f;color:#f1f3f8;padding:32px;">'
       . '<h2>خطای داخلی سرور</h2><p>جزئیات خطا در گزارش امن سرور ثبت شد.</p>'
       . '</body></html>';
});

ini_set('session.cookie_httponly', '1');
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$allowed_ips = select("setting","*",null,null,"select");
$user_ip = redfox_client_ip();
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");

$_raw_iplogin = $allowed_ips['iplogin'] ?? '';
$_ip_list = [];
$_iplogin_unlimited = false;
if ($_raw_iplogin === '*' || $_raw_iplogin === 'all' || $_raw_iplogin === 'unlimited') {
    $_iplogin_unlimited = true;
} elseif (!empty($_raw_iplogin) && $_raw_iplogin !== '0') {
    $_decoded = json_decode($_raw_iplogin, true);
    if (is_array($_decoded)) {
        if (in_array('*', $_decoded, true) || in_array('all', $_decoded, true) || in_array('unlimited', $_decoded, true)) {
            $_iplogin_unlimited = true;
        } else {
            $_ip_list = $_decoded;
        }
    } elseif (filter_var($_raw_iplogin, FILTER_VALIDATE_IP)) {
        $_ip_list = [$_raw_iplogin];
    }
}
$check_ip = $_iplogin_unlimited || (!empty($_ip_list) && in_array($user_ip, $_ip_list, true));
$texterrr = "";

if (isset($_POST['login'])) {

    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $rateIdentity = $user_ip . '|' . mb_strtolower($username);
    $loginRate = redfox_login_rate_check('admin', $rateIdentity, 8, 900);
    if (!$loginRate['allowed']) {
        http_response_code(429);
        $texterrr = 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً حدود ' . max(1, (int)ceil($loginRate['retry_after'] / 60)) . ' دقیقه دیگر تلاش کنید.';
    } elseif ($username !== '' && $password !== '') {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $query->bindValue(':username', $username, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        $storedPassword = (string)($result['password'] ?? '');
        $passwordInfo = $storedPassword !== '' ? password_get_info($storedPassword) : ['algo' => null];
        $isHash = !empty($passwordInfo['algo']);
        $passwordOk = $result && ($isHash
            ? password_verify($password, $storedPassword)
            : hash_equals($storedPassword, $password));

        if (!$passwordOk) {
            // یک پیام یکسان، برای جلوگیری از username enumeration
            redfox_login_rate_fail($loginRate);
            usleep(random_int(150000, 350000));
            $texterrr = 'نام کاربری یا رمز عبور اشتباه است.';
        } else {
            redfox_login_rate_clear($loginRate);
            // مهاجرت بدون وقفه رمزهای قدیمی plaintext در اولین ورود موفق
            if (!$isHash || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $pdo->prepare('UPDATE admin SET password = :new_hash WHERE id_admin = :id AND password = :old_value');
                $upgrade->execute([':new_hash' => $newHash, ':id' => $result['id_admin'], ':old_value' => $storedPassword]);
                $result['password'] = $newHash;
            }
            // ── Red Fox: بررسی 2FA ──
            $twofaEnabled = false;
            try {
                $twofaRow = $pdo->query("SELECT twofa_status, twofa_admin_id FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $twofaEnabled = ($twofaRow['twofa_status'] ?? 'off') === 'on';
            } catch (Throwable $e) {}

            if ($twofaEnabled) {
                // تولید کد ۶ رقمی
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['rx_2fa_pending'] = (string)$result['username'];
                $_SESSION['rx_2fa_pending_id'] = (string)$result['id_admin'];
                $_SESSION['rx_2fa_admin_version'] = redfox_admin_session_version($result);
                $_SESSION['rx_2fa_code_hash'] = hash('sha256', $code);
                $_SESSION['rx_2fa_expires'] = time() + 300; // ۵ دقیقه اعتبار
                $_SESSION['rx_2fa_attempts'] = 0;
                // ارسال کد به ادمین‌ها از طریق تلگرام
                $adminIdsFor2FA = $admin_ids;
                try {
                    foreach ($adminIdsFor2FA as $aid) {
                        @sendmessage($aid,
                            "🔐 <b>کد تأیید ورود به پنل</b>\n\n" .
                            "کد: <code>$code</code>\n\n" .
                            "👤 کاربر: $username\n" .
                            "🌐 IP: " . $user_ip . "\n" .
                            "⏰ اعتبار: ۵ دقیقه",
                            null, 'HTML');
                    }
                } catch (Throwable $e) {}
                $texterrr = ''; // پاک کردن خطا
                $show2FA = true; // نمایش فرم 2FA
            } else {
                // بدون 2FA → ورود مستقیم
                session_regenerate_id(true);
                redfox_bind_admin_session($result);
                session_write_close();
                header('Location: index.php', true, 302);
                ignore_user_abort(true);
                if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
                else { if (!headers_sent()) { @header('Connection: close'); @header('Content-Length: 0'); } while (ob_get_level() > 0) { @ob_end_flush(); } @flush(); }
                try {
                    if (is_array($admin_ids)) { foreach ($admin_ids as $admin) { @sendmessage($admin, "کاربر با نام کاربری " . $username . " وارد پنل تحت وب شد", null, 'html'); } }
                } catch (\Throwable $e) { @error_log('Login notify failed: ' . redfox_exception_fingerprint($e)); }
                exit;
            }
        }
    } else {
        $texterrr = 'نام کاربری یا رمز عبور خالی است.';
    }
}

// ── Red Fox: بررسی کد 2FA ──
if (isset($_POST['verify_2fa'])) {
    $inputCode = trim((string)($_POST['code'] ?? ''));
    $sessionCodeHash = (string)($_SESSION['rx_2fa_code_hash'] ?? '');
    $expires = (int)($_SESSION['rx_2fa_expires'] ?? 0);
    $pendingUser = (string)($_SESSION['rx_2fa_pending'] ?? '');
    $pendingAdminId = (string)($_SESSION['rx_2fa_pending_id'] ?? '');
    $pendingAdminVersion = (string)($_SESSION['rx_2fa_admin_version'] ?? '');
    $twoFaAttempts = (int)($_SESSION['rx_2fa_attempts'] ?? 0);

    if ($twoFaAttempts >= 5) {
        $texterrr = 'تعداد تلاش‌های کد تأیید بیش از حد مجاز است. دوباره وارد شوید.';
        unset($_SESSION['rx_2fa_code_hash'], $_SESSION['rx_2fa_pending'], $_SESSION['rx_2fa_pending_id'], $_SESSION['rx_2fa_admin_version'], $_SESSION['rx_2fa_expires'], $_SESSION['rx_2fa_attempts']);
        $show2FA = false;
    } elseif (time() > $expires) {
        $texterrr = 'کد منقضی شده است. دوباره وارد کنید.';
        unset($_SESSION['rx_2fa_code_hash'], $_SESSION['rx_2fa_pending'], $_SESSION['rx_2fa_pending_id'], $_SESSION['rx_2fa_admin_version'], $_SESSION['rx_2fa_expires'], $_SESSION['rx_2fa_attempts']);
    } elseif (strlen($inputCode) === 6 && preg_match('/^[0-9]{6}$/', $inputCode)
        && strlen($sessionCodeHash) === 64 && hash_equals($sessionCodeHash, hash('sha256', $inputCode))
        && $pendingUser !== '' && $pendingAdminId !== '' && strlen($pendingAdminVersion) === 64) {
        // Re-read the administrator after 2FA: deletion, role changes, or a
        // password reset during the challenge must invalidate the pending login.
        $pendingStmt = $pdo->prepare('SELECT id_admin,username,password,rule FROM admin WHERE username=? AND id_admin=? LIMIT 1');
        $pendingStmt->execute([$pendingUser, $pendingAdminId]);
        $pendingAdmin = $pendingStmt->fetch(PDO::FETCH_ASSOC);
        $pendingStmt->closeCursor();
        if (!is_array($pendingAdmin) || !hash_equals($pendingAdminVersion, redfox_admin_session_version($pendingAdmin))) {
            unset($_SESSION['rx_2fa_code_hash'], $_SESSION['rx_2fa_pending'], $_SESSION['rx_2fa_pending_id'], $_SESSION['rx_2fa_admin_version'], $_SESSION['rx_2fa_expires'], $_SESSION['rx_2fa_attempts']);
            $texterrr = 'نشست ورود دیگر معتبر نیست. دوباره وارد شوید.';
            $show2FA = false;
        } else {
            session_regenerate_id(true);
            redfox_bind_admin_session($pendingAdmin);
            unset($_SESSION['rx_2fa_code_hash'], $_SESSION['rx_2fa_pending'], $_SESSION['rx_2fa_pending_id'], $_SESSION['rx_2fa_admin_version'], $_SESSION['rx_2fa_expires'], $_SESSION['rx_2fa_attempts']);
            session_write_close();
            header('Location: index.php', true, 302);
            exit;
        }
    } else {
        $_SESSION['rx_2fa_attempts'] = $twoFaAttempts + 1;
        usleep(random_int(100000, 250000));
        $texterrr = 'کد تأیید اشتباه است.';
        $show2FA = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ورود به پنل مدیریت | رد فاکس</title>
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer>

</script>
</head>
<body class="login-page">

<?php if (!$check_ip): ?>
    <div class="ip-card">
        <span style="font-size:48px; color: var(--accent);"><?php echo icon('shield-halved', 'svg-icon'); ?></span>
        <h2>دسترسی محدود شده</h2>
        <p>برای ورود به سیستم، آی‌پی زیر را در تنظیمات ربات ثبت کنید.</p>
        <div class="ip-box"><?php echo htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php else: ?>

    <div class="login-card">
        <div class="login-terminal-bar">
            <span class="terminal__lights"><i></i><i></i><i></i></span>
        </div>

        <div class="login-body">
            <img src="img/redfox.png" alt="Red Fox" style="width:64px;height:64px;border-radius:14px;object-fit:cover;margin:0 auto 12px;display:block">
            <h2>پنل مدیریت رد فاکس</h2>
            <p>برای ادامه، اطلاعات حساب خود را وارد کنید.</p>

            <?php if (!empty($texterrr)): ?>
                <div class="alert alert-error">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($texterrr, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($show2FA)): ?>
                <!-- Red Fox: فرم کد 2FA -->
                <div class="alert" style="background:var(--accent-soft,rgba(124,92,255,0.12));border:1px solid var(--accent-mid,rgba(124,92,255,0.3));border-radius:10px;padding:14px;margin-bottom:14px;font-size:13px;color:var(--text-main);line-height:1.8">
                    🔐 کد ۶ رقمی از طریق تلگرام برای شما ارسال شد.<br>
                    لطفاً کد را وارد کنید:
                </div>
                <form method="post" action="login.php">
                    <?= redfox_csrf_field() ?>
                    <div class="form-group">
                        <div class="input-icon-wrap">
                            <?php echo icon('shield-halved', 'svg-icon'); ?>
                            <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus style="text-align:center;letter-spacing:8px;font-size:20px;direction:ltr">
                        </div>
                    </div>
                    <button type="submit" name="verify_2fa" class="btn btn-primary btn-block mt-2">
                        ✅ تأیید و ورود
                    </button>
                    <a href="login.php" style="display:block;text-align:center;margin-top:10px;color:var(--text-muted);font-size:12px">← بازگشت</a>
                </form>
            <?php else: ?>
            <form method="post" action="login.php">
                <?= redfox_csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">نام کاربری</label>
                    <div class="input-icon-wrap">
                        <?php echo icon('user', 'svg-icon'); ?>
                        <input type="text" name="username" class="form-control" placeholder="نام کاربری..." required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <div class="input-icon-wrap">
                        <?php echo icon('lock', 'svg-icon'); ?>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="نمایش رمز">
                            <span id="eye-icon"><?php echo icon('eye', 'svg-icon'); ?></span>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-block mt-2">
                    <?php echo icon('arrow-left', 'svg-icon'); ?>
                    ورود به پنل
                </button>
            </form>
            <?php endif; /* show2FA else */ ?>

            <p class="text-muted mt-2" style="font-size:11px;">
                <?php echo icon('circle-info', 'svg-icon'); ?>
                IP شما: <span style="direction:ltr;"><?php echo htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8'); ?></span>
            </p>

            
            <div class="login-social" style="display:flex; gap:10px; justify-content:center; margin-top:18px; padding-top:14px; border-top:1px solid var(--border, rgba(255,255,255,0.08));">
                <a href="https://t.me/red fox" target="_blank" rel="noopener noreferrer"
                   aria-label="کانال تلگرام رد فاکس"
                   title="کانال تلگرام رد فاکس"
                   style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; background:var(--accent-soft, rgba(59,130,246,0.12)); color:var(--accent, #3b82f6); transition:transform .15s ease, background .15s ease;"
                   onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='translateY(0)';">
                    <?php echo icon('telegram', 'svg-icon'); ?>
                </a>
                <a href="https://github.com/hojjatrad/RedFox-Security-Hardened" target="_blank" rel="noopener noreferrer"
                   aria-label="مخزن گیت‌هاب رد فاکس"
                   title="مخزن گیت‌هاب رد فاکس"
                   style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; background:var(--accent-soft, rgba(59,130,246,0.12)); color:var(--accent, #3b82f6); transition:transform .15s ease, background .15s ease;"
                   onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='translateY(0)';">
                    <?php echo icon('github', 'svg-icon'); ?>
                </a>
            </div>

            <p class="text-muted" style="text-align:center; font-size:11px; margin-top:14px; direction:ltr; font-family:'JetBrains Mono',monospace;">
                <?php
                    $__loginVer = trim((string)@file_get_contents(__DIR__ . '/../version'));
                    if ($__loginVer === '') $__loginVer = '2.4.12';
                    echo 'v' . htmlspecialchars(ltrim($__loginVer, 'vV'), ENT_QUOTES, 'UTF-8');
                ?>
            </p>
        </div>
    </div>

<?php endif; ?>

<script>
    var SVG_EYE       = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var SVG_EYE_SLASH = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    function togglePass() {
        var inp = document.getElementById('passwordInput');
        var icn = document.getElementById('eye-icon');
        if (!inp || !icn) return;
        if (inp.type === 'password') {
            inp.type = 'text';
            icn.innerHTML = SVG_EYE_SLASH;
        } else {
            inp.type = 'password';
            icn.innerHTML = SVG_EYE;
        }
    }
</script>
</body>
</html>


