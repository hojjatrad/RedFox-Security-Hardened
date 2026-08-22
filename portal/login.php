<?php
/** Red Fox — ورود امن نماینده با 2FA تلگرامی */
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start(); redfox_security_headers(); redfox_enforce_csrf();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/../panel/lib/icons.php';
$err='';$show2FA=!empty($_SESSION['portal_2fa_pending']);
function rxp_complete_login(PDO $pdo,array $r): void {
    session_regenerate_id(true);$now=time();$rid=(string)$r['id'];
    $_SESSION['portal_reseller_id']=$rid;$_SESSION['portal_reseller_name']=(string)($r['username']?:$r['namecustom']?:$rid);$_SESSION['portal_agent_type']=(string)$r['agent'];$_SESSION['portal_last_seen']=$now;$_SESSION['portal_ua_hash']=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''));
    unset($_SESSION['portal_2fa_pending'],$_SESSION['portal_2fa_hash'],$_SESSION['portal_2fa_expires'],$_SESSION['portal_2fa_attempts']);
    $hash=hash('sha256',session_id());$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ip=redfox_client_ip();$uaHash=hash('sha256',$ua);
    $known=$pdo->prepare('SELECT COUNT(*) FROM reseller_sessions WHERE reseller_id=? AND ip=? AND ua_hash=?');$known->execute([$rid,$ip,$uaHash]);$isNewDevice=(int)$known->fetchColumn()===0;
    $pdo->prepare("INSERT INTO reseller_sessions(session_hash,reseller_id,ip,ua_hash,ua_label,created_at,last_seen_at,expires_at,revoked_at) VALUES(?,?,?,?,?,?,?,?,NULL) ON DUPLICATE KEY UPDATE reseller_id=VALUES(reseller_id),ip=VALUES(ip),ua_hash=VALUES(ua_hash),ua_label=VALUES(ua_label),last_seen_at=VALUES(last_seen_at),expires_at=VALUES(expires_at),revoked_at=NULL")
        ->execute([$hash,$rid,$ip,$uaHash,mb_substr($ua,0,250),$now,$now,$now+86400]);
    if($isNewDevice){try{sendmessage($rid,"🔔 <b>ورود از دستگاه جدید</b>\n\nIP: <code>".htmlspecialchars($ip)."</code>\nزمان: ".date('Y/m/d H:i:s')."\nاگر این ورود متعلق به شما نیست، از بخش حساب و امنیت همه نشست‌ها را لغو و رمز را تغییر دهید.",null,'HTML');}catch(Throwable$e){}}
    header('Location:index.php');exit;
}
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 $action=(string)($_POST['action']??'login');
 if($action==='verify_2fa'){
    $pending=(string)($_SESSION['portal_2fa_pending']??'');$hash=(string)($_SESSION['portal_2fa_hash']??'');$exp=(int)($_SESSION['portal_2fa_expires']??0);$attempts=(int)($_SESSION['portal_2fa_attempts']??0);$code=trim((string)($_POST['code']??''));
    if($pending===''||$hash===''||time()>$exp||$attempts>=5){$err='کد منقضی یا تعداد تلاش بیش از حد است؛ دوباره وارد شوید.';unset($_SESSION['portal_2fa_pending'],$_SESSION['portal_2fa_hash']);$show2FA=false;}
    else{
        $verified=false;$usedRecovery=false;$normalized=strtoupper(preg_replace('/[^A-Z0-9]/i','',$code));
        if(preg_match('/^\d{6}$/',$code)&&password_verify($code,$hash))$verified=true;
        elseif(strlen($normalized)===10){$rc=$pdo->prepare('SELECT id,code_hash FROM reseller_recovery_codes WHERE reseller_id=? AND used_at IS NULL ORDER BY id LIMIT 20');$rc->execute([$pending]);foreach($rc->fetchAll(PDO::FETCH_ASSOC)as$row){if(password_verify($normalized,(string)$row['code_hash'])){$mark=$pdo->prepare('UPDATE reseller_recovery_codes SET used_at=? WHERE id=? AND reseller_id=? AND used_at IS NULL');$mark->execute([time(),$row['id'],$pending]);if($mark->rowCount()===1){$verified=true;$usedRecovery=true;}break;}}}
        if(!$verified){$_SESSION['portal_2fa_attempts']=$attempts+1;usleep(random_int(120000,300000));$err='کد تأیید یا بازیابی اشتباه است.';$show2FA=true;}
        else{$q=$pdo->prepare("SELECT * FROM user WHERE id=? AND agent IN ('n','n2') AND reseller_portal_status<>'disabled' LIMIT 1");$q->execute([$pending]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r){$err='حساب نمایندگی فعال نیست.';$show2FA=false;}else{if($usedRecovery){try{sendmessage($pending,"⚠️ با یکی از کدهای بازیابی وارد پنل شدید. اگر این ورود متعلق به شما نیست، فوراً رمز را تغییر دهید و نشست‌ها را لغو کنید.",null,'HTML');$pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$pending,(string)($r['reseller_role']??'agent'),'profile.recovery_login',$pending,'{}',redfox_client_ip(),time()]);}catch(Throwable$e){}}rxp_complete_login($pdo,$r);}}
    }
 } else {
    $rid=trim((string)($_POST['reseller_id']??''));$pass=(string)($_POST['password']??'');$rate=redfox_login_rate_check('portal',redfox_client_ip().'|'.$rid,8,900);
    if(!$rate['allowed']){$err='تعداد تلاش‌ها بیش از حد مجاز است؛ بعداً تلاش کنید.';http_response_code(429);}
    elseif($rid!==''&&ctype_digit($rid)&&$pass!==''){
      try{$q=$pdo->prepare("SELECT * FROM user WHERE id=? AND agent IN ('n','n2') LIMIT 1");$q->execute([$rid]);$r=$q->fetch(PDO::FETCH_ASSOC);$ok=$r&&!empty($r['panel_password'])&&password_verify($pass,(string)$r['panel_password']);if(!$ok){redfox_login_rate_fail($rate);usleep(random_int(150000,350000));$err='آیدی یا رمز عبور اشتباه است.';}elseif(($r['reseller_portal_status']??'active')==='disabled'){$err='دسترسی پورتال غیرفعال است.';}else{redfox_login_rate_clear($rate);$need2FA=(($r['reseller_role']??'agent')==='super')||!empty($r['portal_2fa_enabled']);if($need2FA){$sendRate=redfox_login_rate_check('portal_2fa_send',$rid,5,900);if(!$sendRate['allowed']){$err='تعداد درخواست کد بیش از حد است؛ ۱۵ دقیقه بعد تلاش کنید.';$show2FA=false;}else{redfox_login_rate_fail($sendRate);$code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$_SESSION['portal_2fa_pending']=$rid;$_SESSION['portal_2fa_hash']=password_hash($code,PASSWORD_DEFAULT);$_SESSION['portal_2fa_expires']=time()+300;$_SESSION['portal_2fa_attempts']=0;$resp=sendmessage($rid,"🔐 <b>کد ورود پنل نمایندگی</b>\n\nکد: <code>$code</code>\nاعتبار: ۵ دقیقه\nIP: ".htmlspecialchars(redfox_client_ip(), ENT_QUOTES, 'UTF-8'),null,'HTML');if(!is_array($resp)||empty($resp['ok'])){unset($_SESSION['portal_2fa_pending'],$_SESSION['portal_2fa_hash']);$err='ارسال کد تلگرام ناموفق بود؛ ابتدا ربات اصلی را Start کنید.';$show2FA=false;}else$show2FA=true;}}else rxp_complete_login($pdo,$r);}}
      catch(Throwable$e){$err='خطای سیستم.';redfox_log_exception($e, 'portal.login');}
    } else $err='هر دو فیلد را پر کنید.';
 }
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>پورتال نماینده | ربات رد فاکس</title><link rel="stylesheet" href="../panel/css/theme.css">
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body)}
.login-card{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:18px;padding:32px;max-width:380px;width:90%;text-align:center}
.login-card img{width:64px;height:64px;border-radius:14px;object-fit:cover;margin:0 auto 14px;display:block}
.login-card h2{color:var(--text-main);font-size:18px;margin:0 0 4px}
.login-card p{color:var(--text-muted);font-size:13px;margin:0 0 20px}
.login-card input{width:100%;box-sizing:border-box;padding:11px 14px;border-radius:10px;border:1px solid var(--border-mid);background:var(--surface-2,var(--surface-1));color:var(--text-main);font-size:14px;margin-bottom:10px}
.login-card button{width:100%;padding:11px;border:none;border-radius:10px;background:var(--accent);color:var(--accent-fg);font-size:14px;font-weight:700;cursor:pointer}
.err{color:var(--color-danger);font-size:12px;margin-bottom:10px;line-height:1.8}</style>
</head><body>
<div class="login-card">
<img src="../panel/img/redfox.png" alt="Red Fox">
<h2>پورتال نماینده</h2>
<p>برای ورود، آیدی عددی تلگرام و رمز عبور پورتال خود را وارد کنید.</p>
<?php if ($err !== ''): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if($show2FA): ?>
<form method="post"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="verify_2fa"><input type="text" name="code" maxlength="11" placeholder="کد ۶ رقمی یا کد بازیابی" required style="direction:ltr"><button type="submit">تأیید کد</button></form>
<?php else: ?>
<form method="post"><?= redfox_csrf_field() ?><input type="hidden" name="action" value="login"><input type="text" name="reseller_id" placeholder="آیدی عددی تلگرام" required style="direction:ltr"><input type="password" name="password" placeholder="رمز عبور پورتال" required style="direction:ltr"><button type="submit">ورود</button></form>
<?php endif; ?>
</div>
</body></html>
