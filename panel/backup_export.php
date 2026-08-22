<?php
/**
 * Red Fox — خروجی انتخابی (Selective Backup/Export).
 *
 * ۱) بسته‌ی یک نماینده: خودِ نماینده + زیرمجموعه‌ها (affiliates) + مشتریانی که به آن‌ها سرویس داده
 *    (invoice.refral) + فاکتورها + پرداخت‌ها + رکورد Requestagent آن نماینده.
 * ۲) بسته‌ی همه‌ی مشتریان + نماینده‌ها + تاریخچه‌ی فروش: همه‌ی user/invoice/Payment_report/Requestagent
 *    (بدون تنظیمات/ادمین/پنل — یعنی فقط داده‌ی کاربری/مالی).
 *
 * خروجی به‌صورت .sql با INSERT IGNORE → در ربات جدید قابل ایمپورت (از طریق restore.php حالت انتخابی
 * یا phpMyAdmin) بدون بازنویسی‌ی داده‌ی موجود.
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

// تبدیل ردیف‌ها به INSERT IGNORE SQL
function redfox_rows_to_sql($pdo, $table, array $rows): string {
    $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
    if ($table === '' || empty($rows)) return '';
    $out = '';
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row)) continue;
        $cols = array_keys($row);
        $vals = [];
        foreach (array_values($row) as $v) {
            $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
        }
        $out .= "INSERT IGNORE INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
    }
    return $out;
}

// لیست نماینده‌ها برای dropdown
$resellers = [];
try { $resellers = $pdo->query("SELECT id, username, namecustom, agent FROM user WHERE agent IN ('n','n2') ORDER BY id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

/* ---------- تولید و دانلود خروجی ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin && (string)($_POST['action'] ?? '') === 'export') {
    $mode = (string)($_POST['mode'] ?? '');
    $filename = 'redfox_export_' . date('Ymd_His') . '.sql';
    $sql = "-- Red Fox selective export\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Mode: {$mode}\n-- All statements use INSERT IGNORE (safe to import on top of existing data).\n\n";
    $stats = [];

    try {
        if ($mode === 'reseller') {
            $rid = trim((string)($_POST['reseller_id'] ?? ''));
            if ($rid === '' || !ctype_digit($rid)) {
                $sql = "-- ERROR: invalid reseller id\n";
            } else {
                // ۱) خود نماینده
                $ru = $pdo->prepare("SELECT * FROM user WHERE id = ?"); $ru->execute([$rid]);
                $resellerRows = $ru->fetchAll(PDO::FETCH_ASSOC);
                // ۲) زیرمجموعه‌ها (affiliates = rid)
                $dl = $pdo->prepare("SELECT * FROM user WHERE affiliates = ?"); $dl->execute([$rid]);
                $downlineRows = $dl->fetchAll(PDO::FETCH_ASSOC);
                // ۳) خریدارانِ فروش‌های ارجاعی این نماینده (invoice.refral = rid)
                $ci = $pdo->prepare("SELECT DISTINCT id_user FROM invoice WHERE refral = ? AND id_user IS NOT NULL AND id_user <> ''"); $ci->execute([$rid]);
                $custIds = $ci->fetchAll(PDO::FETCH_COLUMN);
                // جمع‌آوری همه‌ی آیدی‌ها
                $ids = [$rid];
                foreach ($downlineRows as $d) if (!empty($d['id'])) $ids[] = (string)$d['id'];
                foreach ($custIds as $c) $ids[] = (string)$c;
                $ids = array_values(array_unique(array_filter($ids, fn($x) => $x !== '')));
                // ۴) ردیفِ user همه‌ی آیدی‌ها
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $allUsersStmt = $pdo->prepare("SELECT * FROM user WHERE id IN ($placeholders)");
                $allUsersStmt->execute($ids);
                $allUserRows = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                // ۵) فاکتورها
                $invStmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user IN ($placeholders) OR refral = ?");
                $paramsInv = array_merge($ids, [$rid]);
                $invStmt->execute($paramsInv);
                $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
                // ۶) پرداخت‌ها
                $payStmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user IN ($placeholders)");
                $payStmt->execute($ids);
                $payRows = $payStmt->fetchAll(PDO::FETCH_ASSOC);
                // ۷) Requestagent نماینده
                $raStmt = $pdo->prepare("SELECT * FROM Requestagent WHERE id = ?"); $raStmt->execute([$rid]);
                $raRows = $raStmt->fetchAll(PDO::FETCH_ASSOC);

                $sql .= "-- === Reseller #{$rid} bundle ===\n";
                $sql .= "-- reseller user, downline, customers, invoices, payments, requestagent\n";
                $sql .= redfox_rows_to_sql($pdo, 'user', $allUserRows);
                $sql .= redfox_rows_to_sql($pdo, 'Requestagent', $raRows);
                $sql .= redfox_rows_to_sql($pdo, 'invoice', $invRows);
                $sql .= redfox_rows_to_sql($pdo, 'Payment_report', $payRows);
                $sql .= "\n-- Summary: users=" . count($allUserRows) . ", invoices=" . count($invRows) . ", payments=" . count($payRows) . "\n";
                $stats = ['users'=>count($allUserRows), 'invoices'=>count($invRows), 'payments'=>count($payRows), 'downline'=>count($downlineRows)];
            }
        } elseif ($mode === 'all_users_sales') {
            $uR = $pdo->query("SELECT * FROM user")->fetchAll(PDO::FETCH_ASSOC);
            $iR = $pdo->query("SELECT * FROM invoice")->fetchAll(PDO::FETCH_ASSOC);
            $pR = $pdo->query("SELECT * FROM Payment_report")->fetchAll(PDO::FETCH_ASSOC);
            $rR = $pdo->query("SELECT * FROM Requestagent")->fetchAll(PDO::FETCH_ASSOC);
            $bsR = []; try { $bsR = $pdo->query("SELECT * FROM botsaz")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
            $sql .= "-- === All customers + resellers + sales history ===\n";
            $sql .= "-- user, Requestagent, botsaz, invoice, Payment_report\n\n";
            $sql .= redfox_rows_to_sql($pdo, 'user', $uR);
            $sql .= redfox_rows_to_sql($pdo, 'Requestagent', $rR);
            $sql .= redfox_rows_to_sql($pdo, 'botsaz', $bsR);
            $sql .= redfox_rows_to_sql($pdo, 'invoice', $iR);
            $sql .= redfox_rows_to_sql($pdo, 'Payment_report', $pR);
            $sql .= "\n-- Summary: users=" . count($uR) . ", bots=" . count($bsR) . ", invoices=" . count($iR) . ", payments=" . count($pR) . "\n";
            $stats = ['users'=>count($uR), 'invoices'=>count($iR), 'payments'=>count($pR)];
        } elseif ($mode === 'users_only') {
            $uR = $pdo->query("SELECT * FROM user")->fetchAll(PDO::FETCH_ASSOC);
            $sql .= "-- === All users only ===\n"; $sql .= redfox_rows_to_sql($pdo, 'user', $uR);
            $sql .= "\n-- Summary: users=" . count($uR) . "\n";
        } elseif ($mode === 'resellers_only') {
            $uR = $pdo->query("SELECT * FROM user WHERE agent IN ('n','n2')")->fetchAll(PDO::FETCH_ASSOC);
            $rR = $pdo->query("SELECT * FROM Requestagent")->fetchAll(PDO::FETCH_ASSOC);
            $bsR = []; try { $bsR = $pdo->query("SELECT * FROM botsaz")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
            $sql .= "-- === Resellers + their bots only ===\n";
            $sql .= redfox_rows_to_sql($pdo, 'user', $uR);
            $sql .= redfox_rows_to_sql($pdo, 'Requestagent', $rR);
            $sql .= redfox_rows_to_sql($pdo, 'botsaz', $bsR);
            $sql .= "\n-- Summary: resellers=" . count($uR) . ", bots=" . count($bsR) . "\n";
        } elseif ($mode === 'invoices_only') {
            $iR = $pdo->query("SELECT * FROM invoice")->fetchAll(PDO::FETCH_ASSOC);
            $sql .= "-- === All invoices only ===\n"; $sql .= redfox_rows_to_sql($pdo, 'invoice', $iR);
            $sql .= "\n-- Summary: invoices=" . count($iR) . "\n";
        } elseif ($mode === 'payments_only') {
            $pR = $pdo->query("SELECT * FROM Payment_report")->fetchAll(PDO::FETCH_ASSOC);
            $sql .= "-- === All payments only ===\n"; $sql .= redfox_rows_to_sql($pdo, 'Payment_report', $pR);
            $sql .= "\n-- Summary: payments=" . count($pR) . "\n";
        } elseif ($mode === 'settings_only') {
            $sR = $pdo->query("SELECT * FROM setting")->fetchAll(PDO::FETCH_ASSOC);
            $psR = $pdo->query("SELECT * FROM PaySetting")->fetchAll(PDO::FETCH_ASSOC);
            $shR = []; try { $shR = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
            $tbR = []; try { $tbR = $pdo->query("SELECT * FROM textbot")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
            $dpR = []; try { $dpR = $pdo->query("SELECT * FROM departman")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
            $sql .= "-- === Settings + config only ===\n";
            $sql .= redfox_rows_to_sql($pdo, 'setting', $sR);
            $sql .= redfox_rows_to_sql($pdo, 'PaySetting', $psR);
            $sql .= redfox_rows_to_sql($pdo, 'shopSetting', $shR);
            $sql .= redfox_rows_to_sql($pdo, 'textbot', $tbR);
            $sql .= redfox_rows_to_sql($pdo, 'departman', $dpR);
            $sql .= "\n-- Settings backup complete\n";
        } elseif ($mode === 'custom' && $isMainAdmin) {
            $allowedTables = ['user','invoice','Payment_report','Requestagent','botsaz','setting','PaySetting','shopSetting','textbot','departman','product','marzban_panel','category','card_number','channels','help','ai_knowledge','ai_support_log','support_message','wheel_list','cancel_service','service_other','reseller_cards','reseller_ai_feature','DiscountSell','Discount'];
            $selectedTables = array_intersect($allowedTables, (array)($_POST['tables'] ?? []));
            $sql .= "-- === Custom table selection ===\n";
            foreach ($selectedTables as $tbl) {
                try {
                    $tblEsc = preg_replace('/[^A-Za-z0-9_]/', '', $tbl);
                    $rows = $pdo->query("SELECT * FROM `{$tblEsc}`")->fetchAll(PDO::FETCH_ASSOC);
                    $sql .= redfox_rows_to_sql($pdo, $tbl, $rows);
                    $sql .= "-- $tbl: " . count($rows) . " rows\n";
                } catch (Throwable $e) { $sql .= "-- ERROR $tbl: " . redfox_public_exception($e, 'panel/backup_export.php') . "\n"; }
            }
        } else {
            $sql = "-- ERROR: unknown mode\n";
        }
    } catch (Throwable $e) {
        $sql = "-- ERROR: " . redfox_public_exception($e, 'panel/backup_export.php') . "\n";
    }

    // دانلود
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql;
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>خروجی انتخابی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-card-title{color:var(--text-main)}.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
.rx-field{margin-bottom:12px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}
.rx-field select{width:100%;max-width:560px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box}
.rx-radio{display:flex;align-items:flex-start;gap:8px;margin:8px 0;font-size:13px;color:var(--text-main)}
.rx-radio input{margin-top:3px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📤 خروجی انتخابی (بکاپ تخصصی)</h1>
<div class="page-head__sub">خروجی جداگانه‌ی یک نماینده یا همه‌ی مشتریان/فروش‌ها — قابل ایمپورت در ربات جدید</div></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">انتخاب نوع خروجی</h2></div>
<?php if (!$isMainAdmin): ?><p class="text-muted">فقط ادمین اصلی.</p><?php else: ?>
<form method="post">
<input type="hidden" name="action" value="export">

<div class="rx-radio"><input type="radio" name="mode" value="reseller" id="m_res" checked onclick="document.getElementById('resSel').style.display='block'">
<label for="m_res"><b>۱) بسته‌ی یک نماینده</b>: خودِ نماینده + زیرمجموعه‌ها (affiliates) + مشتریانی که به آن‌ها سرویس داده (invoice.refral) + فاکتورها + پرداخت‌ها + رکورد نمایندگی. هر نماینده یک فایل جدا.</label></div>
<div class="rx-field" id="resSel"><label>انتخاب نماینده</label>
<select name="reseller_id">
<?php foreach ($resellers as $r): ?>
<option value="<?= htmlspecialchars((string)$r['id'],ENT_QUOTES,'UTF-8') ?>">#<?= htmlspecialchars((string)$r['id'],ENT_QUOTES) ?> — <?= htmlspecialchars((string)($r['username'] ?: $r['namecustom'] ?: 'نامشخص'),ENT_QUOTES,'UTF-8') ?> (<?= $r['agent'] === 'n2' ? 'پیشرفته' : 'عادی' ?>)</option>
<?php endforeach; ?>
</select></div>

<div class="rx-radio"><input type="radio" name="mode" value="all_users_sales" id="m_all" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_all"><b>۲) همه‌ی مشتریان + نماینده‌ها + تاریخچه‌ی فروش</b>: همه‌ی <code>user</code>، <code>Requestagent</code>، <code>invoice</code> و <code>Payment_report</code> (بدون تنظیمات/ادمین/پنل).</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="users_only" id="m_u" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_u"><b>۳) فقط همه‌ی کاربران</b>: فقط جدول <code>user</code> (همه‌ی کاربران عادی + نماینده‌ها).</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="resellers_only" id="m_r" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_r"><b>۴) فقط نماینده‌ها + ربات‌هایشان</b>: کاربران نماینده (<code>agent = n/n2</code>) + <code>Requestagent</code> + <code>botsaz</code>.</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="invoices_only" id="m_i" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_i"><b>۵) فقط فاکتورها</b>: همه‌ی <code>invoice</code> (تاریخچه‌ی کامل فروش).</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="payments_only" id="m_p" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_p"><b>۶) فقط پرداخت‌ها</b>: همه‌ی <code>Payment_report</code>.</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="settings_only" id="m_s" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='none'">
<label for="m_s"><b>۷) فقط تنظیمات و پیکربندی</b>: <code>setting</code> + <code>PaySetting</code> + <code>shopSetting</code> + <code>textbot</code> + <code>departman</code> (بدون داده‌ی کاربری).</label></div>

<div class="rx-radio"><input type="radio" name="mode" value="custom" id="m_c" onclick="document.getElementById('resSel').style.display='none';document.getElementById('customSel').style.display='block'">
<label for="m_c"><b>۸) انتخاب سفارشی جداول</b>: جداول دلخواه را انتخاب کنید.</label></div>
<div class="rx-field" id="customSel" style="display:none">
<label>انتخاب جداول:</label>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;max-width:700px">
<?php foreach (['user','invoice','Payment_report','Requestagent','botsaz','setting','PaySetting','shopSetting','textbot','departman','product','marzban_panel','category','card_number','channels','help','ai_knowledge','ai_support_log','support_message','wheel_list','cancel_service','service_other','reseller_cards','reseller_ai_feature','DiscountSell','Discount'] as $tbl): ?>
<label style="font-size:12px;display:flex;gap:4px;align-items:center"><input type="checkbox" name="tables[]" value="<?= $tbl ?>"> <code><?= $tbl ?></code></label>
<?php endforeach; ?>
</div>
</div>

<button class="btn btn-sm btn-success" type="submit">⬇️ دانلود فایل .sql</button>
</form>
<?php endif; ?>
<p class="rx-help">
💡 خروجی به‌صورت <code>.sql</code> با دستورات <code>INSERT IGNORE</code> است → ایمپورت در ربات جدید داده‌ی موجود را بازنویسی نمی‌کند.<br>
📥 برای برگرداندن در ربات جدید: از بخش «بازیابی بکاپ» (حالت انتخابی) یا phpMyAdmin فایل را ایمپورت کنید.<br>
🔒 داده‌ی حساس مثل تنظیمات ربات، ادمین‌ها و پنل‌ها در این خروجی <b>نیست</b> (امنیت).
</p></div>

</div></section></section></body></html>
