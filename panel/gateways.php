<?php
/** Red Fox — مرکز درگاه‌ها: کلیدهای API، ولت ارزها، Star Telegram */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../botapi.php'; require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule']==='administrator');
$flash=''; $flashType='info';

function rx_ps_get($k){ global $pdo; try{ $s=$pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay=? LIMIT 1"); $s->execute([$k]); $v=$s->fetchColumn(); return $v===false?'':(string)$v; }catch(Throwable $e){ return ''; } }
function rx_ps_set($k,$v){ global $pdo; try{ $c=$pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay=?"); $c->execute([$k]); if((int)$c->fetchColumn()>0){ $pdo->prepare("UPDATE PaySetting SET ValuePay=? WHERE NamePay=?")->execute([$v,$k]); } else { $pdo->prepare("INSERT INTO PaySetting (NamePay,ValuePay) VALUES (?,?)")->execute([$k,$v]); } return true; }catch(Throwable $e){ return false; } }

// کلیدهای API درگاه‌ها
$gatewayKeys = [
    'merchant_zarinpal'       => '🟡 زرین‌پال — مرچنت آیدی',
    'merchant_id_aqayepardakht'=> '🔵 آقای پرداخت — پین/مرچنت',
    'marchent_floypay'        => '🟢 ایران‌پی ۱ — API Key',
    'apiiranpay'              => '🟢 ارزی ریالی — API',
    'apiternado'              => '🟧 ترنادو — API Key',
    'urlpaymenttron'          => '🟧 ترنادو — آدرس API',
    'nowpayment_ipn_secret'   => '🪙 NowPayments — IPN Secret (الزامی)',
    'tronado_webhook_secret'  => '🟧 ترنادو — HMAC Webhook Secret (الزامی)',
];

// ارزهای پشتیبانی‌شده (crypto_wallets)
$curDefs = ['TRX'=>['net'=>'TRON','lbl'=>'ترون (TRX)'],'TON'=>['net'=>'TON','lbl'=>'تون (TON)'],'USDT_TRC20'=>['net'=>'TRON','lbl'=>'تتر روی ترون'],'USDT_TON'=>['net'=>'TON','lbl'=>'تتر روی تون']];

if (($_SERVER['REQUEST_METHOD']??'')==='POST' && $isMainAdmin) {
    $act=(string)($_POST['action']??'');
    try {
        if ($act==='save_gw') {
            foreach (array_keys($gatewayKeys) as $gk) { $v=trim((string)($_POST[$gk]??'')); rx_ps_set($gk,$v); }
            $star = (string)($_POST['statusstar']??'')==='on'?'1':'0'; rx_ps_set('statusstar',$star);
            $flash='تنظیمات درگاه‌ها ذخیره شد.'; $flashType='success';
        } elseif ($act==='save_wallet') {
            $cur=trim((string)($_POST['currency']??'')); $addr=trim((string)($_POST['address']??'')); $rate=trim((string)($_POST['rate']??''));
            if (isset($curDefs[$cur])) {
                $net=$curDefs[$cur]['net']; $lbl=$curDefs[$cur]['lbl'];
                $rateVal = ($rate!=='' && is_numeric($rate)) ? (float)$rate : null;
                try {
                    $pdo->prepare("INSERT INTO crypto_wallets (currency,network,wallet_address,label,enabled,rate_irt_override) VALUES (?,?,?,?,1,?) ON DUPLICATE KEY UPDATE wallet_address=VALUES(wallet_address),network=VALUES(network),rate_irt_override=VALUES(rate_irt_override),enabled=1")->execute([$cur,$net,$addr,$lbl,$rateVal]);
                    $flash='آدرس ولت ذخیره شد.'; $flashType='success';
                } catch(Throwable $e) { $flash='خطای ولت: '.htmlspecialchars(redfox_public_exception($e, 'panel/gateways.php'),ENT_QUOTES); $flashType='error'; }
            }
        }
    } catch(Throwable $e){$flash='خطا: '.htmlspecialchars(redfox_public_exception($e, 'panel/gateways.php'),ENT_QUOTES);$flashType='error';}
}

// مقادیر فعلی درگاه‌ها
$gwVals = []; foreach (array_keys($gatewayKeys) as $gk) $gwVals[$gk]=rx_ps_get($gk);
$starVal = rx_ps_get('statusstar');
// ولت‌های فعلی
$wallets=[]; try{$wallets=$pdo->query("SELECT * FROM crypto_wallets ORDER BY currency ASC")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مرکز درگاه‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}.rx-card-title{color:var(--text-main)}.rx-field{margin-bottom:10px}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin-bottom:3px}.rx-field input{width:100%;max-width:560px;padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;direction:ltr;box-sizing:border-box}.rx-toggle{display:inline-flex;align-items:center;gap:8px}code{color:var(--text-main)}.rx-info{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:10px}</style>
</head><body><section id="container"><?php include("header.php"); ?><section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">💳 مرکز درگاه‌های پرداخت</h1><div class="page-head__sub">کلیدهای API، آدرس ولت ارزها و درگاه استار</div></div>
<?php if($flash!==''): ?><div class="rx-flash <?=htmlspecialchars($flashType,ENT_QUOTES)?>"><?=$flash?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">🔑 کلیدها و مرچنت‌های درگاه‌ها</h2></div>
<?php if($isMainAdmin): ?>
<form method="post"><input type="hidden" name="action" value="save_gw">
<?php foreach ($gatewayKeys as $gk=>$gl): ?>
<div class="rx-field"><label><?= $gl ?> <code><?= htmlspecialchars($gk,ENT_QUOTES) ?></code></label><input type="text" name="<?= htmlspecialchars($gk,ENT_QUOTES) ?>" value="<?= htmlspecialchars($gwVals[$gk],ENT_QUOTES,'UTF-8') ?>" placeholder="—"></div>
<?php endforeach; ?>
<div class="rx-field rx-toggle"><input type="checkbox" name="statusstar" value="on" id="star" <?= $starVal==='1'?'checked':'' ?>><label for="star" style="font-size:13px;margin:0">💫 فعال‌سازی درگاه Telegram Star</label></div>
<button class="btn btn-sm btn-primary" type="submit">ذخیره همه</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 روشن/خاموش‌کردن هر درگاه و سقف مبلغ در بخش «مالی» است. اینجا فقط کلید/مرچنت تنظیم می‌شود.</p>
</div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">💼 آدرس ولت ارزها</h2></div>
<?php if($isMainAdmin): foreach ($curDefs as $cur=>$cd):
    $wRow=null; foreach($wallets as $w){ if($w['currency']===$cur){$wRow=$w;break;} }
    $wAddr=$wRow['wallet_address']??''; $wRate=$wRow['rate_irt_override']??''; $wEn=(int)($wRow['enabled']??0)===1;
?>
<form method="post" class="rx-field" style="border-bottom:1px solid var(--border-soft);padding-bottom:8px">
<input type="hidden" name="action" value="save_wallet"><input type="hidden" name="currency" value="<?= htmlspecialchars($cur,ENT_QUOTES) ?>">
<label><?= $cd['lbl'] ?> <?= $wEn?'✅':'🚫' ?></label>
<input type="text" name="address" value="<?= htmlspecialchars($wAddr,ENT_QUOTES,'UTF-8') ?>" placeholder="آدرس <?= $cd['net'] ?>" style="margin-bottom:4px">
<input type="text" name="rate" value="<?= htmlspecialchars((string)$wRate,ENT_QUOTES) ?>" placeholder="نرخ تومان (اختیاری — خالی=خودکار)">
<button class="btn btn-sm btn-primary" type="submit" style="margin-top:4px">ذخیره</button>
</form>
<?php endforeach; else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
<p class="rx-info">💡 نرخ خالی = خودکار از API. نرخ دستی فقط برای آن ارز اعمال می‌شود.</p>
</div>

</div></section></section></body></html>
