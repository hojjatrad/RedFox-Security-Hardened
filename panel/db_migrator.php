<?php
/**
 * RedFox+ Database Migrator — ابزار مهاجرت انتخابی از دیتابیس‌های دیگر
 *
 * مراحل:
 *   1) نمایش دیتابیس‌های موجود روی سرور + امکان وارد کردن دستی
 *   2) انتخاب دیتابیس مبدأ و اتصال + تحلیل جداول
 *   3) انتخاب نماینده یا مهاجرت همه
 *   4) پیش‌نمایش و تأیید نهایی
 *   5) اجرای import و نمایش نتیجه
 */
declare(strict_types=1);
ob_start();

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
redfox_security_headers();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

// ── Auth ──
if (empty($_SESSION['user'])) {
    if (isset($_GET['ajax'])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'جلسه منقضی شده — لطفاً صفحه را رفرش کنید']);
        exit;
    }
    header('Location: login.php');
    exit;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($_GET['ajax'])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'دیتابیس در دسترس نیست']);
        exit;
    }
    http_response_code(503);
    exit('Database not available');
}
$stmt = $pdo->prepare('SELECT * FROM admin WHERE username=? LIMIT 1');
$stmt->execute([(string)$_SESSION['user']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) { header('Location: login.php'); exit; }
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit; }

// ── Get DB credentials from globals (set by config.php) ──
$_dbhost = (string)($GLOBALS['dbhost'] ?? 'localhost');
$_dbuser = (string)($GLOBALS['usernamedb'] ?? '');
$_dbpass = (string)($GLOBALS['passworddb'] ?? '');
$_dbname = (string)($GLOBALS['dbname'] ?? '');

// ── Helpers ──
function mz_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function mz_connect_db(string $host, string $user, string $pass, string $db): ?PDO {
    try {
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        return new PDO($dsn, $user, $pass, $opts);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * لیست تمام دیتابیس‌ها — ابتدا SHOW DATABASES، سپس information_schema، سپس بررسی دایرکتوری
 */
function mz_list_all_databases(PDO $pdo, string $currentDb): array {
    $found = [];
    
    // روش 1: SHOW DATABASES
    try {
        $rows = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $d) {
            if (!in_array($d, ['information_schema','performance_schema','mysql','sys'], true)) {
                $found[$d] = true;
            }
        }
    } catch (Throwable $e) {}
    
    // روش 2: information_schema.SCHEMATA (ممکن است دیتابیس‌های بیشتری نشان دهد)
    try {
        $rows = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $d) {
            if (!in_array($d, ['information_schema','performance_schema','mysql','sys'], true)) {
                $found[$d] = true;
            }
        }
    } catch (Throwable $e) {}
    
    // روش 3: بررسی دایرکتوری data MySQL (اگر دسترسی باشد)
    try {
        $datadir = $pdo->query("SELECT @@datadir")->fetchColumn();
        if ($datadir && is_dir($datadir)) {
            $dirs = scandir($datadir);
            foreach ($dirs as $d) {
                if ($d === '.' || $d === '..' || in_array($d, ['information_schema','performance_schema','mysql','sys'], true)) continue;
                if (is_dir($datadir . '/' . $d)) {
                    $found[$d] = true;
                }
            }
        }
    } catch (Throwable $e) {}
    
    ksort($found);
    return array_keys($found);
}

function mz_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function mz_count_rows(PDO $pdo, string $table): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `" . preg_replace('/[^A-Za-z0-9_]/','',$table) . "`")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function mz_rows_to_sql(PDO $pdo, string $table, array $rows): string {
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if ($table === '' || empty($rows)) return '';
    $out = '';
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row)) continue;
        $cols = array_keys($row);
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
        $out .= "INSERT IGNORE INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
    }
    return $out;
}

// ── AJAX handler ──
if (isset($_GET['ajax'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)$_GET['ajax'];

    try {
        // ── لیست دیتابیس‌ها (با امکان credential سفارشی) ──
        if ($action === 'list_dbs') {
            $customHost = (string)($_GET['host'] ?? '');
            $customUser = (string)($_GET['user'] ?? '');
            $customPass = (string)($_GET['pass'] ?? '');
            $connectHost = $customHost !== '' ? $customHost : $_dbhost;
            $connectUser = $customUser !== '' ? $customUser : $_dbuser;
            $connectPass = $customPass !== '' ? $customPass : $_dbpass;
            $tmp = mz_connect_db($connectHost, $connectUser, $connectPass, $_dbname);
            if (!$tmp) {
                $tmp = mz_connect_db($connectHost, $connectUser, $connectPass, 'information_schema');
            }
            if (!$tmp) {
                echo json_encode(['ok'=>false,'error'=>'اتصال به MySQL ناموفق. میزبان: '.$connectHost.' کاربر: '.$connectUser]);
                exit;
            }
            $dbs = mz_list_all_databases($tmp, $_dbname);
            
            // اطلاعات هر دیتابیس
            $dbInfo = [];
            foreach ($dbs as $db) {
                $info = ['name'=>$db, 'tables'=>0, 'rows'=>0, 'is_current'=>($db === $_dbname)];
                try {
                    $tmpDb = mz_connect_db($connectHost, $connectUser, $connectPass, $db);
                    if ($tmpDb) {
                        $tables = $tmpDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        $info['tables'] = count($tables);
                        $totalRows = 0;
                        foreach ($tables as $t) {
                            $totalRows += mz_count_rows($tmpDb, $t);
                        }
                        $info['rows'] = $totalRows;
                    }
                } catch (Throwable $e) {}
                $dbInfo[] = $info;
            }
            
            echo json_encode(['ok'=>true,'dbs'=>$dbInfo,'current'=>$_dbname,'host'=>$_dbhost,'user'=>$_dbuser]);
            exit;
        }

        // ── تست اتصال به دیتابیس (با امکان credential سفارشی) ──
        if ($action === 'test_db') {
            $db = (string)($_GET['db'] ?? '');
            if ($db === '' || !preg_match('/^[A-Za-z0-9_.]+$/', $db)) {
                echo json_encode(['ok'=>false,'error'=>'نام دیتابیس نامعتبر — فقط حروف، اعداد، نقطه و زیرخط مجاز است']); exit;
            }
            // اگر credential سفارشی ارسال شده
            $customHost = (string)($_GET['host'] ?? '');
            $customUser = (string)($_GET['user'] ?? '');
            $customPass = (string)($_GET['pass'] ?? '');
            $connectHost = $customHost !== '' ? $customHost : $_dbhost;
            $connectUser = $customUser !== '' ? $customUser : $_dbuser;
            $connectPass = $customPass !== '' ? $customPass : $_dbpass;
            $testPdo = mz_connect_db($connectHost, $connectUser, $connectPass, $db);
            if (!$testPdo) {
                // تست اتصال بدون dbname برای بررسی credential
                $basePdo = mz_connect_db($connectHost, $connectUser, $connectPass, '');
                if (!$basePdo) {
                    echo json_encode(['ok'=>false,'error'=>'اتصال به MySQL ناموفق — میزبان: '.$connectHost.' کاربر: '.$connectUser.' — لطفاً میزبان، نام کاربری و رمز عبور را بررسی کنید']); exit;
                }
                echo json_encode(['ok'=>false,'error'=>'اتصال به دیتابیس `'.$db.'` ناموفق — دیتابیس وجود ندارد یا کاربر MySQL مجوز دسترسی ندارد.']); exit;
            }
            $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['ok'=>true,'db'=>$db,'tables'=>count($tables)]);
            exit;
        }

        // ── تحلیل دیتابیس (با امکان credential سفارشی) ──
        if ($action === 'analyze_db') {
            $src = (string)($_GET['db'] ?? '');
            if ($src === '' || !preg_match('/^[A-Za-z0-9_.]+$/', $src)) {
                echo json_encode(['ok'=>false,'error'=>'نام دیتابیس نامعتبر']); exit;
            }
            $customHost = (string)($_GET['host'] ?? '');
            $customUser = (string)($_GET['user'] ?? '');
            $customPass = (string)($_GET['pass'] ?? '');
            $connectHost = $customHost !== '' ? $customHost : $_dbhost;
            $connectUser = $customUser !== '' ? $customUser : $_dbuser;
            $connectPass = $customPass !== '' ? $customPass : $_dbpass;
            $srcPdo = mz_connect_db($connectHost, $connectUser, $connectPass, $src);
            if (!$srcPdo) {
                echo json_encode(['ok'=>false,'error'=>'اتصال به دیتابیس `'.$src.'` ناموفق']); exit;
            }

            $tables = [];
            $important = ['user','invoice','Payment_report','Requestagent','botsaz','product','category','setting',
                           'reseller_cards','reseller_ai_feature','reseller_categories','reseller_wallet_ledger',
                           'reseller_audit_log','reseller_broadcasts','reseller_service_operations','reseller_sessions',
                           'DiscountSell','Discount','cancel_service','service_other','card_number','channels','help'];
            $allTables = $srcPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allTables as $t) {
                $cnt = mz_count_rows($srcPdo, $t);
                $tables[] = ['name'=>$t, 'rows'=>$cnt, 'important'=>in_array($t, $important, true)];
            }

            // پیدا کردن نماینده‌ها
            $agents = [];
            $has_agent_col = false;
            try {
                $colCheck = $srcPdo->query("SHOW COLUMNS FROM `user` LIKE 'agent'")->fetch();
                $has_agent_col = $colCheck !== false;
            } catch (Throwable $e) {}

            if ($has_agent_col) {
                try {
                    $agentRows = $srcPdo->query("SELECT id, username, namecustom, agent, reseller_role, affiliates, reseller_parent_id FROM user WHERE agent IN ('n','n2') ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($agentRows as $a) {
                        $aid = (string)$a['id'];
                        $dlStmt = $srcPdo->prepare("SELECT COUNT(*) FROM user WHERE affiliates = ?");
                        $dlStmt->execute([$aid]);
                        $downline = (int)$dlStmt->fetchColumn();

                        $custStmt = $srcPdo->prepare("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE refral = ?");
                        $custStmt->execute([$aid]);
                        $customers = (int)$custStmt->fetchColumn();

                        $invStmt = $srcPdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? OR refral = ?");
                        $invStmt->execute([$aid, $aid]);
                        $invoices = (int)$invStmt->fetchColumn();

                        $revStmt = $srcPdo->prepare("SELECT COALESCE(SUM(CAST(amount AS DECIMAL(20,0))),0) FROM invoice WHERE (id_user = ? OR refral = ?) AND status_pay = 'paid'");
                        $revStmt->execute([$aid, $aid]);
                        $revenue = (string)$revStmt->fetchColumn();

                        $agents[] = [
                            'id' => $aid,
                            'username' => (string)($a['username'] ?? ''),
                            'namecustom' => (string)($a['namecustom'] ?? ''),
                            'agent' => (string)($a['agent'] ?? ''),
                            'role' => (string)($a['reseller_role'] ?? ''),
                            'downline' => $downline,
                            'customers' => $customers,
                            'invoices' => $invoices,
                            'revenue' => $revenue,
                        ];
                    }
                } catch (Throwable $e) {}
            }

            echo json_encode(['ok'=>true, 'tables'=>$tables, 'agents'=>$agents, 'has_agent_col'=>$has_agent_col, 'total_tables'=>count($tables)]);
            exit;
        }

        // ── پیش‌نمایش مهاجرت (با امکان credential سفارشی) ──
        if ($action === 'preview_import') {
            $mode = (string)($_GET['mode'] ?? '');
            $src = (string)($_GET['db'] ?? '');
            $agentId = (string)($_GET['agent_id'] ?? '');
            if ($src === '' || !preg_match('/^[A-Za-z0-9_.]+$/', $src)) {
                echo json_encode(['ok'=>false,'error'=>'نام دیتابیس نامعتبر']); exit;
            }
            $customHost = (string)($_GET['host'] ?? '');
            $customUser = (string)($_GET['user'] ?? '');
            $customPass = (string)($_GET['pass'] ?? '');
            $connectHost = $customHost !== '' ? $customHost : $_dbhost;
            $connectUser = $customUser !== '' ? $customUser : $_dbuser;
            $connectPass = $customPass !== '' ? $customPass : $_dbpass;
            $srcPdo = mz_connect_db($connectHost, $connectUser, $connectPass, $src);
            if (!$srcPdo) {
                echo json_encode(['ok'=>false,'error'=>'اتصال ناموفق']); exit;
            }

            $preview = [];
            $idsCount = 0;

            if ($mode === 'agent' && $agentId !== '') {
                $ids = [$agentId];
                $dl = $srcPdo->prepare("SELECT id FROM user WHERE affiliates = ?"); $dl->execute([$agentId]);
                foreach ($dl->fetchAll(PDO::FETCH_COLUMN) as $d) $ids[] = (string)$d;
                $ci = $srcPdo->prepare("SELECT DISTINCT id_user FROM invoice WHERE refral = ? AND id_user IS NOT NULL AND id_user <> ''");
                $ci->execute([$agentId]);
                foreach ($ci->fetchAll(PDO::FETCH_COLUMN) as $c) $ids[] = (string)$c;
                $ids = array_values(array_unique(array_filter($ids)));
                $idsCount = count($ids);
                $ph = implode(',', array_fill(0, count($ids), '?'));

                $uStmt = $srcPdo->prepare("SELECT COUNT(*) FROM user WHERE id IN ($ph)");
                $uStmt->execute($ids); $preview['user'] = (int)$uStmt->fetchColumn();

                foreach (['Requestagent','Payment_report','botsaz'] as $tbl) {
                    if (!mz_table_exists($srcPdo, $tbl)) continue;
                    if ($tbl === 'Requestagent') {
                        $s = $srcPdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE id = ?"); $s->execute([$agentId]);
                    } else {
                        $s = $srcPdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE id_user IN ($ph)"); $s->execute($ids);
                    }
                    $preview[$tbl] = (int)$s->fetchColumn();
                }

                $invStmt = $srcPdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user IN ($ph) OR refral = ?");
                $invStmt->execute(array_merge($ids, [$agentId]));
                $preview['invoice'] = (int)$invStmt->fetchColumn();

                foreach (['reseller_cards','reseller_ai_feature','reseller_categories'] as $tbl) {
                    if (!mz_table_exists($srcPdo, $tbl)) continue;
                    $s = $srcPdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE reseller_id = ?"); $s->execute([$agentId]);
                    $preview[$tbl] = (int)$s->fetchColumn();
                }

                foreach (['reseller_wallet_ledger','reseller_audit_log'] as $tbl) {
                    if (!mz_table_exists($srcPdo, $tbl)) continue;
                    $col = ($tbl === 'reseller_wallet_ledger') ? 'actor_reseller_id' : 'reseller_id';
                    $s = $srcPdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?"); $s->execute([$agentId]);
                    $preview[$tbl] = (int)$s->fetchColumn();
                }

                foreach (['DiscountSell','service_other','cancel_service'] as $tbl) {
                    if (!mz_table_exists($srcPdo, $tbl)) continue;
                    try {
                        $s = $srcPdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE id_user IN ($ph)"); $s->execute($ids);
                        $preview[$tbl] = (int)$s->fetchColumn();
                    } catch (Throwable $e) {}
                }
            } elseif ($mode === 'all') {
                $important = ['user','invoice','Payment_report','Requestagent','botsaz','product','category','setting',
                               'reseller_cards','reseller_ai_feature','reseller_categories','reseller_wallet_ledger',
                               'reseller_audit_log','DiscountSell','Discount','cancel_service','service_other',
                               'card_number','channels','help','textbot','departman','shopSetting','PaySetting'];
                foreach ($important as $t) {
                    if (mz_table_exists($srcPdo, $t)) {
                        $preview[$t] = mz_count_rows($srcPdo, $t);
                    }
                }
            }

            $totalRows = array_sum($preview);
            echo json_encode(['ok'=>true, 'preview'=>$preview, 'total_rows'=>$totalRows, 'ids_count'=>$idsCount]);
            exit;
        }

        echo json_encode(['ok'=>false,'error'=>'عملیات نامشخص: '.$action]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'خطای سرور: '.$e->getMessage()]);
        exit;
    }
}

// ── POST: Execute import ──
$importResult = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'execute_import') {
    redfox_enforce_csrf();
    $mode = (string)($_POST['mode'] ?? '');
    $src = (string)($_POST['source_db'] ?? '');
    $agentId = (string)($_POST['agent_id'] ?? '');
    $confirmText = trim((string)($_POST['confirm'] ?? ''));

    if ($confirmText !== 'YES IMPORT DATA') {
        $importResult = ['ok'=>false, 'error'=>'عبارت تأیید دقیق نیست.'];
    } elseif ($src === '' || !preg_match('/^[A-Za-z0-9_]+$/', $src)) {
        $importResult = ['ok'=>false, 'error'=>'نام دیتابیس نامعتبر.'];
    } else {
        $srcPdo = mz_connect_db($_dbhost, $_dbuser, $_dbpass, $src);
        if (!$srcPdo) {
            $importResult = ['ok'=>false, 'error'=>'اتصال به دیتابیس مبدأ ناموفق.'];
        } else {
            try {
                $imported = [];
                $errors = [];

                if ($mode === 'agent' && $agentId !== '') {
                    $ids = [$agentId];
                    $dl = $srcPdo->prepare("SELECT id FROM user WHERE affiliates = ?"); $dl->execute([$agentId]);
                    foreach ($dl->fetchAll(PDO::FETCH_COLUMN) as $d) $ids[] = (string)$d;
                    $ci = $srcPdo->prepare("SELECT DISTINCT id_user FROM invoice WHERE refral = ? AND id_user IS NOT NULL AND id_user <> ''");
                    $ci->execute([$agentId]);
                    foreach ($ci->fetchAll(PDO::FETCH_COLUMN) as $c) $ids[] = (string)$c;
                    $ids = array_values(array_unique(array_filter($ids)));
                    $ph = implode(',', array_fill(0, count($ids), '?'));

                    $table_configs = [
                        ['table'=>'user', 'query'=>"SELECT * FROM user WHERE id IN ($ph)", 'params'=>$ids],
                        ['table'=>'Requestagent', 'query'=>"SELECT * FROM Requestagent WHERE id = ?", 'params'=>[$agentId]],
                        ['table'=>'invoice', 'query'=>"SELECT * FROM invoice WHERE id_user IN ($ph) OR refral = ?", 'params'=>array_merge($ids, [$agentId])],
                        ['table'=>'Payment_report', 'query'=>"SELECT * FROM Payment_report WHERE id_user IN ($ph)", 'params'=>$ids],
                        ['table'=>'botsaz', 'query'=>"SELECT * FROM botsaz WHERE id_user IN ($ph)", 'params'=>$ids],
                        ['table'=>'reseller_cards', 'query'=>"SELECT * FROM reseller_cards WHERE reseller_id = ?", 'params'=>[$agentId]],
                        ['table'=>'reseller_ai_feature', 'query'=>"SELECT * FROM reseller_ai_feature WHERE reseller_id = ?", 'params'=>[$agentId]],
                        ['table'=>'reseller_categories', 'query'=>"SELECT * FROM reseller_categories WHERE reseller_id = ?", 'params'=>[$agentId]],
                        ['table'=>'reseller_wallet_ledger', 'query'=>"SELECT * FROM reseller_wallet_ledger WHERE actor_reseller_id = ?", 'params'=>[$agentId]],
                        ['table'=>'reseller_audit_log', 'query'=>"SELECT * FROM reseller_audit_log WHERE reseller_id = ?", 'params'=>[$agentId]],
                        ['table'=>'DiscountSell', 'query'=>"SELECT * FROM DiscountSell WHERE id_user IN ($ph)", 'params'=>$ids],
                        ['table'=>'service_other', 'query'=>"SELECT * FROM service_other WHERE id_user IN ($ph)", 'params'=>$ids],
                        ['table'=>'cancel_service', 'query'=>"SELECT * FROM cancel_service WHERE id_user IN ($ph)", 'params'=>$ids],
                    ];

                    foreach ($table_configs as $tc) {
                        $tbl = $tc['table'];
                        if (!mz_table_exists($srcPdo, $tbl)) { $errors[] = "جدول `{$tbl}` در دیتابیس مبدأ وجود ندارد — رد شد."; continue; }
                        if (!mz_table_exists($pdo, $tbl)) { $errors[] = "جدول `{$tbl}` در دیتابیس فعلی وجود ندارد — رد شد."; continue; }
                        try {
                            $stmt = $srcPdo->prepare($tc['query']);
                            $stmt->execute($tc['params']);
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($rows)) {
                                foreach (array_chunk($rows, 100) as $chunk) {
                                    $sqlChunk = mz_rows_to_sql($srcPdo, $tbl, $chunk);
                                    if ($sqlChunk !== '') $pdo->exec($sqlChunk);
                                }
                                $imported[$tbl] = count($rows);
                            }
                        } catch (Throwable $e) {
                            $errors[] = "خطا در `{$tbl}`: " . $e->getMessage();
                        }
                    }

                } elseif ($mode === 'all') {
                    $important = ['user','invoice','Payment_report','Requestagent','botsaz','product','category',
                                   'reseller_cards','reseller_ai_feature','reseller_categories','reseller_wallet_ledger',
                                   'reseller_audit_log','DiscountSell','Discount','cancel_service','service_other',
                                   'card_number','channels','help','textbot','departman','shopSetting','PaySetting',
                                   'setting'];
                    foreach ($important as $tbl) {
                        if (!mz_table_exists($srcPdo, $tbl)) continue;
                        if (!mz_table_exists($pdo, $tbl)) { $errors[] = "جدول `{$tbl}` در دیتابیس فعلی وجود ندارد — رد شد."; continue; }
                        try {
                            $rows = $srcPdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($rows)) {
                                foreach (array_chunk($rows, 100) as $chunk) {
                                    $sqlChunk = mz_rows_to_sql($srcPdo, $tbl, $chunk);
                                    if ($sqlChunk !== '') $pdo->exec($sqlChunk);
                                }
                                $imported[$tbl] = count($rows);
                            }
                        } catch (Throwable $e) {
                            $errors[] = "خطا در `{$tbl}`: " . $e->getMessage();
                        }
                    }
                }

                $importResult = ['ok'=>true, 'imported'=>$imported, 'errors'=>$errors, 'mode'=>$mode, 'source'=>$src, 'agent_id'=>$agentId, 'total'=>array_sum($imported)];
            } catch (Throwable $e) {
                $importResult = ['ok'=>false, 'error'=>'خطای غیرمنتظره: '.$e->getMessage()];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ابزار مهاجرت دیتابیس | RedFox+</title><link rel="stylesheet" href="css/theme.css">
<style>
:root{--rx-bg:#0f1117;--rx-card:#1a1d27;--rx-border:#2a2d3a;--rx-text:#e4e6ef;--rx-muted:#8b8fa3;--rx-accent:#6366f1;--rx-green:#22c55e;--rx-red:#ef4444;--rx-orange:#f59e0b;--rx-blue:#3b82f6}
body{background:var(--rx-bg);color:var(--rx-text);font-family:'Segoe UI',Tahoma,sans-serif;margin:0;padding:0}
.rx-wrap{max-width:1200px;margin:0 auto;padding:20px}
.rx-title{font-size:1.6em;font-weight:700;margin:0 0 6px}.rx-sub{color:var(--rx-muted);font-size:.9em;margin-bottom:20px}
.rx-card{background:var(--rx-card);border:1px solid var(--rx-border);border-radius:14px;padding:20px;margin-bottom:16px}
.rx-card h2{margin:0 0 14px;font-size:1.15em;display:flex;align-items:center;gap:8px}
.rx-step{display:flex;gap:12px;margin-bottom:14px}.rx-step-item{flex:1;background:var(--rx-card);border:2px solid var(--rx-border);border-radius:12px;padding:12px;text-align:center;transition:.3s}
.rx-step-item.active{border-color:var(--rx-accent);box-shadow:0 0 20px rgba(99,102,241,.15)}
.rx-step-item.done{border-color:var(--rx-green);opacity:.7}.rx-step-num{font-size:1.5em;font-weight:700;color:var(--rx-accent)}
.rx-step-label{font-size:.8em;color:var(--rx-muted);margin-top:4px}
.rx-btn{display:inline-block;padding:10px 20px;border-radius:10px;border:none;font-size:.95em;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none}
.rx-btn:hover{transform:translateY(-1px)}.rx-btn:active{transform:translateY(0)}
.rx-btn-primary{background:var(--rx-accent);color:#fff}.rx-btn-green{background:var(--rx-green);color:#fff}
.rx-btn-red{background:var(--rx-red);color:#fff}.rx-btn-orange{background:var(--rx-orange);color:#000}
.rx-btn-ghost{background:transparent;color:var(--rx-accent);border:1px solid var(--rx-accent)}
.rx-btn-sm{padding:6px 14px;font-size:.85em}
.db-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;max-height:500px;overflow-y:auto;padding:4px}
.db-item{background:var(--rx-bg);border:2px solid var(--rx-border);border-radius:10px;padding:14px;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:12px}
.db-item:hover{border-color:var(--rx-accent);background:rgba(99,102,241,.06)}.db-item.selected{border-color:var(--rx-green);background:rgba(34,197,94,.08)}
.db-item.current{border-color:var(--rx-orange);opacity:.6;cursor:default}.db-item.current::after{content:'📍 فعلی';font-size:.7em;background:var(--rx-orange);color:#000;padding:2px 6px;border-radius:4px;margin-right:auto}
.db-icon{font-size:1.6em}.db-name{font-weight:600;font-size:.95em}.db-name small{display:block;color:var(--rx-muted);font-weight:400;font-size:.75em;margin-top:2px}
.db-meta{display:flex;gap:12px;margin-top:4px}.db-meta span{font-size:.75em;color:var(--rx-muted)}
.agent-card{background:var(--rx-bg);border:2px solid var(--rx-border);border-radius:12px;padding:14px;cursor:pointer;transition:.2s;margin-bottom:8px}
.agent-card:hover{border-color:var(--rx-accent)}.agent-card.selected{border-color:var(--rx-green);background:rgba(34,197,94,.06)}
.agent-head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.agent-badge{font-size:.7em;padding:2px 8px;border-radius:6px;font-weight:600}
.agent-badge.n{background:var(--rx-blue);color:#fff}.agent-badge.n2{background:var(--rx-accent);color:#fff}
.agent-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px}
.agent-stat{text-align:center;padding:6px;background:var(--rx-card);border-radius:8px}
.agent-stat-val{font-size:1.2em;font-weight:700;color:var(--rx-accent)}.agent-stat-lbl{font-size:.7em;color:var(--rx-muted)}
.table-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px}
.table-item{background:var(--rx-bg);border:1px solid var(--rx-border);border-radius:8px;padding:10px;display:flex;justify-content:space-between;align-items:center}
.table-name{font-family:monospace;font-size:.85em}.table-rows{font-weight:700;color:var(--rx-accent)}
.preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.preview-item{background:var(--rx-bg);border:1px solid var(--rx-border);border-radius:8px;padding:10px;text-align:center}
.preview-num{font-size:1.5em;font-weight:700;color:var(--rx-green)}.preview-tbl{font-size:.8em;color:var(--rx-muted);font-family:monospace}
.rx-spinner{display:inline-block;width:20px;height:20px;border:3px solid var(--rx-border);border-top-color:var(--rx-accent);border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.rx-alert{padding:14px;border-radius:12px;margin:10px 0;white-space:pre-wrap}
.rx-alert.success{background:rgba(34,197,94,.12);color:var(--rx-green);border:1px solid rgba(34,197,94,.3)}
.rx-alert.error{background:rgba(239,68,68,.12);color:var(--rx-red);border:1px solid rgba(239,68,68,.3)}
.rx-alert.warn{background:rgba(245,158,11,.12);color:var(--rx-orange);border:1px solid rgba(245,158,11,.3)}
.rx-alert.info{background:rgba(99,102,241,.12);color:var(--rx-accent);border:1px solid rgba(99,102,241,.3)}
.result-table{width:100%;border-collapse:collapse;margin:10px 0;font-size:.9em}
.result-table th,.result-table td{padding:8px 12px;text-align:right;border-bottom:1px solid var(--rx-border)}
.result-table th{color:var(--rx-muted);font-size:.8em;font-weight:600}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--rx-muted);text-decoration:none;font-size:.9em;margin-bottom:16px}
.back-link:hover{color:var(--rx-text)}
.input-field{width:100%;max-width:400px;padding:10px 12px;border-radius:10px;border:2px solid var(--rx-border);background:var(--rx-bg);color:var(--rx-text);font-size:.95em;box-sizing:border-box;direction:ltr}
.input-field:focus{outline:none;border-color:var(--rx-accent)}
.search-box{position:relative;margin-bottom:14px}
.search-box input{padding-left:36px}
.search-box::before{content:'🔍';position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:.9em}
.manual-db{background:var(--rx-bg);border:2px dashed var(--rx-border);border-radius:12px;padding:16px;margin-top:14px;text-align:center}
.manual-db input{margin:0 8px}
</style>
</head><body>
<div class="rx-wrap">
<a class="back-link" href="index.php">← بازگشت به پنل</a>
<h1 class="rx-title">🔄 ابزار مهاجرت دیتابیس</h1>
<p class="rx-sub">مهاجرت انتخابی نماینده، زیرمجموعه‌ها و تاریخچه مالی از دیتابیس‌های دیگر روی همین سرور</p>

<div class="rx-step">
    <div class="rx-step-item" id="step-ind-1"><div class="rx-step-num">۱</div><div class="rx-step-label">انتخاب دیتابیس</div></div>
    <div class="rx-step-item" id="step-ind-2"><div class="rx-step-num">۲</div><div class="rx-step-label">تحلیل و بررسی</div></div>
    <div class="rx-step-item" id="step-ind-3"><div class="rx-step-num">۳</div><div class="rx-step-label">انتخاب نماینده</div></div>
    <div class="rx-step-item" id="step-ind-4"><div class="rx-step-num">۴</div><div class="rx-step-label">پیش‌نمایش</div></div>
    <div class="rx-step-item" id="step-ind-5"><div class="rx-step-num">۵</div><div class="rx-step-label">اجرای مهاجرت</div></div>
</div>

<!-- ═══════ مرحله ۱: انتخاب دیتابیس ═══════ -->
<div class="rx-card" id="step1">
    <h2>🗄️ مرحله ۱: انتخاب دیتابیس مبدأ</h2>
    <p style="color:var(--rx-muted);font-size:.85em;margin-bottom:14px">
        دیتابیسی که می‌خواهید اطلاعات آن را به دیتابیس فعلی ربات منتقل کنید انتخاب کنید.
        دیتابیس فعلی با رنگ نارنجی مشخص شده و قابل انتخاب نیست.
    </p>
    <div id="db-loading" style="text-align:center;padding:30px"><div class="rx-spinner"></div><br><br>در حال جستجوی دیتابیس‌ها...</div>
    <div id="db-error" style="display:none"></div>
    <div id="db-list-wrap" style="display:none">
        <div class="search-box"><input type="text" id="db-search" placeholder="جستجوی نام دیتابیس..." class="input-field" oninput="filterDbs()"></div>
        <div class="db-list" id="db-list"></div>
        <div class="manual-db">
            <p style="color:var(--rx-muted);font-size:.85em;margin:0 0 10px">اگر دیتابیس مورد نظر در لیست نیست یا دیتابیس دیگری روی سرور هست، اطلاعات زیر را وارد کنید:</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:520px;margin:0 auto 10px;text-align:right">
                <div><label style="font-size:.8em;color:var(--rx-muted)">میزبان (اختیاری)</label><input type="text" id="custom-host" placeholder="<?= mz_h($_dbhost) ?>" class="input-field" style="max-width:100%"></div>
                <div><label style="font-size:.8em;color:var(--rx-muted)">نام کاربری MySQL</label><input type="text" id="custom-user" placeholder="<?= mz_h($_dbuser) ?>" class="input-field" style="max-width:100%"></div>
                <div><label style="font-size:.8em;color:var(--rx-muted)">رمز عبور MySQL</label><input type="password" id="custom-pass" placeholder="رمز عبور" class="input-field" style="max-width:100%"></div>
                <div><label style="font-size:.8em;color:var(--rx-muted)">نام دیتابیس</label><input type="text" id="manual-db-name" placeholder="نام دیتابیس" class="input-field" style="max-width:100%" oninput="onManualInput()"></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                <button class="rx-btn rx-btn-primary rx-btn-sm" id="btn-manual-test" onclick="testManualDb()">🔌 تست اتصال</button>
                <button class="rx-btn rx-btn-ghost rx-btn-sm" onclick="refreshDbList()">🔄 بارگذاری مجدد لیست با credential جدید</button>
            </div>
            <div id="manual-db-status" style="margin-top:8px;font-size:.85em"></div>
        </div>
        <div style="margin-top:14px;text-align:center"><button class="rx-btn rx-btn-primary" id="btn-analyze" disabled onclick="goStep2()">🔍 تحلیل دیتابیس انتخاب‌شده</button></div>
    </div>
</div>

<!-- ═══════ مرحله ۲: تحلیل ═══════ -->
<div class="rx-card" id="step2" style="display:none">
    <h2>📊 مرحله ۲: تحلیل دیتابیس <span id="src-db-name" style="color:var(--rx-accent)"></span></h2>
    <div id="analyze-loading" style="text-align:center;padding:30px"><div class="rx-spinner"></div><br><br>در حال تحلیل جداول و نماینده‌ها...</div>
    <div id="analyze-error" style="display:none"></div>
    <div id="analyze-result" style="display:none">
        <h3 style="margin:0 0 10px;color:var(--rx-muted);font-size:.9em">📋 جداول دیتابیس (<span id="total-tables"></span> جدول)</h3>
        <div class="table-grid" id="table-grid"></div>
        <div style="margin-top:16px" id="agents-section">
            <h3 style="margin:0 0 10px;color:var(--rx-muted);font-size:.9em">👥 نماینده‌ها و آمار</h3>
            <div id="agents-list"></div>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
            <button class="rx-btn rx-btn-primary" id="btn-select-agent" disabled onclick="goStep3('agent')">👤 انتخاب نماینده خاص</button>
            <button class="rx-btn rx-btn-orange" onclick="goStep3('all')">📦 مهاجرت همه</button>
            <button class="rx-btn rx-btn-ghost" onclick="goStep1()">← بازگشت</button>
        </div>
    </div>
</div>

<!-- ═══════ مرحله ۳: انتخاب نماینده ═══════ -->
<div class="rx-card" id="step3" style="display:none">
    <h2>👤 مرحله ۳: انتخاب نماینده</h2>
    <div class="search-box"><input type="text" id="agent-search" placeholder="جستجوی نام کاربری یا شناسه..." class="input-field" oninput="filterAgents()"></div>
    <div id="agent-select-list"></div>
    <div style="margin-top:14px;display:flex;gap:10px;justify-content:center">
        <button class="rx-btn rx-btn-primary" id="btn-preview" disabled onclick="goStep4()">👁️ پیش‌نمایش مهاجرت</button>
        <button class="rx-btn rx-btn-ghost" onclick="goStep2Back()">← بازگشت</button>
    </div>
</div>

<!-- ═══════ مرحله ۴: پیش‌نمایش ═══════ -->
<div class="rx-card" id="step4" style="display:none">
    <h2>👁️ مرحله ۴: پیش‌نمایش مهاجرت</h2>
    <div id="preview-loading" style="text-align:center;padding:30px"><div class="rx-spinner"></div><br><br>در حال محاسبه...</div>
    <div id="preview-error" style="display:none"></div>
    <div id="preview-result" style="display:none">
        <div class="rx-alert info" id="preview-summary"></div>
        <div class="preview-grid" id="preview-grid"></div>
        <div class="rx-alert warn" style="margin-top:16px">⚠️ توجه: دستورات <code>INSERT IGNORE</code> استفاده می‌شوند — رکوردهای موجود بازنویسی نمی‌شوند.</div>
        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
            <button class="rx-btn rx-btn-green" onclick="goStep5()">✅ تأیید و اجرای مهاجرت</button>
            <button class="rx-btn rx-btn-ghost" onclick="goStep3Back()">← بازگشت</button>
        </div>
    </div>
</div>

<!-- ═══════ مرحله ۵: اجرا ═══════ -->
<div class="rx-card" id="step5" style="display:none">
    <h2>🚀 مرحله ۵: اجرای مهاجرت</h2>
    <?php if ($importResult): ?>
        <?php if ($importResult['ok']): ?>
            <div class="rx-alert success">✅ مهاجرت با موفقیت انجام شد!</div>
            <table class="result-table">
                <tr><th>جدول</th><th>رکوردهای import شده</th></tr>
                <?php foreach ($importResult['imported'] as $tbl => $cnt): ?>
                <tr><td><code><?= mz_h($tbl) ?></code></td><td style="font-weight:700;color:var(--rx-green)"><?= number_format($cnt) ?></td></tr>
                <?php endforeach; ?>
                <tr style="border-top:2px solid var(--rx-border)"><td><b>جمع کل</b></td><td style="font-weight:700;color:var(--rx-accent);font-size:1.1em"><?= number_format($importResult['total']) ?></td></tr>
            </table>
            <?php if (!empty($importResult['errors'])): ?>
            <div class="rx-alert error" style="margin-top:10px">⚠️ خطاها:<br><?= implode('<br>', array_map('mz_h', $importResult['errors'])) ?></div>
            <?php endif; ?>
            <div style="margin-top:16px"><a class="rx-btn rx-btn-primary" href="index.php">🏠 بازگشت به پنل</a></div>
        <?php else: ?>
            <div class="rx-alert error">❌ <?= mz_h($importResult['error']) ?></div>
            <div style="margin-top:16px"><a class="rx-btn rx-btn-ghost" href="db_migrator.php">🔄 تلاش مجدد</a></div>
        <?php endif; ?>
    <?php else: ?>
        <div id="exec-loading" style="text-align:center;padding:30px"><div class="rx-spinner"></div><br><br>در حال اجرای مهاجرت...</div>
        <form method="post" id="exec-form" style="display:none">
            <input type="hidden" name="action" value="execute_import">
            <input type="hidden" name="mode" id="exec-mode">
            <input type="hidden" name="source_db" id="exec-db">
            <input type="hidden" name="agent_id" id="exec-agent">
            <input type="hidden" name="custom_host" id="exec-custom-host">
            <input type="hidden" name="custom_user" id="exec-custom-user">
            <input type="hidden" name="custom_pass" id="exec-custom-pass">
            <input type="hidden" name="confirm" value="YES IMPORT DATA">
            <?= redfox_csrf_field() ?>
        </form>
    <?php endif; ?>
</div>

<script>
const CSRF = '<?= mz_h(redfox_csrf_token()) ?>';
let selectedDb = '';
let selectedAgentId = '';
let migrationMode = '';
let allDbs = [];
let allAgents = [];

function setActiveStep(n) {
    for (let i = 1; i <= 5; i++) {
        const el = document.getElementById('step-ind-' + i);
        el.className = 'rx-step-item' + (i < n ? ' done' : i === n ? ' active' : '');
    }
}

function showErr(id, msg) {
    const el = document.getElementById(id);
    if (el) { el.innerHTML = '<div class="rx-alert error">❌ ' + msg + '</div>'; el.style.display = 'block'; }
}

function filterDbs() {
    const q = document.getElementById('db-search').value.toLowerCase();
    document.querySelectorAll('.db-item').forEach(el => { el.style.display = el.dataset.name.includes(q) ? '' : 'none'; });
}

function filterAgents() {
    const q = document.getElementById('agent-search').value.toLowerCase();
    document.querySelectorAll('.agent-card').forEach(el => { el.style.display = el.dataset.search.includes(q) ? '' : 'none'; });
}

function getCustomCreds() {
    const h = document.getElementById('custom-host').value.trim();
    const u = document.getElementById('custom-user').value.trim();
    const p = document.getElementById('custom-pass').value;
    let qs = '';
    if (h) qs += '&host=' + encodeURIComponent(h);
    if (u) qs += '&user=' + encodeURIComponent(u);
    if (p) qs += '&pass=' + encodeURIComponent(p);
    return qs;
}

function onManualInput() {
    const val = document.getElementById('manual-db-name').value.trim();
    if (val) {
        document.querySelectorAll('.db-item').forEach(e => e.classList.remove('selected'));
        selectedDb = val;
        document.getElementById('btn-analyze').disabled = false;
    }
    document.getElementById('manual-db-status').innerHTML = '';
}

async function testManualDb() {
    const db = document.getElementById('manual-db-name').value.trim();
    if (!db) { document.getElementById('manual-db-status').innerHTML = '<span style="color:var(--rx-orange)">نام دیتابیس را وارد کنید</span>'; return; }
    document.getElementById('manual-db-status').innerHTML = '<div class="rx-spinner"></div> در حال تست...';
    try {
        const d = await fetchJSON('db_migrator.php?ajax=test_db&db=' + encodeURIComponent(db) + getCustomCreds());
        if (d.ok) {
            document.getElementById('manual-db-status').innerHTML = '<span style="color:var(--rx-green)">✅ اتصال موفق — ' + d.tables + ' جدول یافت شد</span>';
            selectedDb = db;
            document.getElementById('btn-analyze').disabled = false;
        } else {
            document.getElementById('manual-db-status').innerHTML = '<span style="color:var(--rx-red)">❌ ' + d.error + '</span>';
        }
    } catch(e) {
        document.getElementById('manual-db-status').innerHTML = '<span style="color:var(--rx-red)">خطا: ' + e.message + '</span>';
    }
}

async function refreshDbList() {
    document.getElementById('db-loading').style.display = 'block';
    document.getElementById('db-list-wrap').style.display = 'none';
    await loadDatabases();
}

async function fetchJSON(url) {
    const r = await fetch(url, {credentials:'same-origin'});
    const text = await r.text();
    try { return JSON.parse(text); }
    catch(e) { return {ok:false, error:'پاسخ سرور نامعتبر است.\n\n'+text.substring(0,300)}; }
}

async function loadDatabases() {
    try {
        const d = await fetchJSON('db_migrator.php?ajax=list_dbs' + getCustomCreds());
        document.getElementById('db-loading').style.display = 'none';
        if (!d.ok) { showErr('db-error', d.error); return; }
        if (!d.dbs || d.dbs.length === 0) { showErr('db-error', 'هیچ دیتابیسی یافت نشد.'); return; }
        allDbs = d.dbs;
        const list = document.getElementById('db-list');
        list.innerHTML = '';
        d.dbs.forEach(db => {
            const isCurrent = db.is_current;
            const el = document.createElement('div');
            el.className = 'db-item' + (isCurrent ? ' current' : '');
            el.dataset.name = db.name.toLowerCase();
            el.innerHTML = '<div class="db-icon">' + (isCurrent ? '📍' : '🗄️') + '</div><div class="db-name">' + db.name +
                (isCurrent ? '<small>دیتابیس فعلی ربات</small>' : '') +
                '<div class="db-meta"><span>📋 ' + db.tables + ' جدول</span><span>📊 ' + db.rows.toLocaleString('fa') + ' رکورد</span></div></div>';
            if (!isCurrent) {
                el.onclick = () => {
                    document.querySelectorAll('.db-item').forEach(e => e.classList.remove('selected'));
                    el.classList.add('selected');
                    selectedDb = db.name;
                    document.getElementById('manual-db-name').value = '';
                    document.getElementById('btn-analyze').disabled = false;
                };
            }
            list.appendChild(el);
        });
        document.getElementById('db-list-wrap').style.display = 'block';
    } catch (e) {
        document.getElementById('db-loading').innerHTML = '<div class="rx-alert error">خطای شبکه: ' + e.message + '<br>لطفاً صفحه را رفرش کنید.</div>';
    }
}

function goStep1() {
    ['step1','step2','step3','step4','step5'].forEach(id => document.getElementById(id).style.display = 'none');
    document.getElementById('step1').style.display = 'block';
    setActiveStep(1);
}

async function goStep2() {
    if (!selectedDb) return;
    // اگر دیتابیس فعلی انتخاب شده
    if (selectedDb === '<?= mz_h($_dbname) ?>') {
        alert('دیتابیس فعلی ربات قابل انتخاب نیست. لطفاً یک دیتابیس دیگر انتخاب کنید.');
        return;
    }
    document.getElementById('step2').style.display = 'block';
    document.getElementById('step2').scrollIntoView({behavior:'smooth'});
    document.getElementById('src-db-name').textContent = selectedDb;
    document.getElementById('analyze-loading').style.display = 'block';
    document.getElementById('analyze-result').style.display = 'none';
    document.getElementById('analyze-error').style.display = 'none';
    setActiveStep(2);
    try {
        const d = await fetchJSON('db_migrator.php?ajax=analyze_db&db=' + encodeURIComponent(selectedDb) + getCustomCreds());
        document.getElementById('analyze-loading').style.display = 'none';
        if (!d.ok) { showErr('analyze-error', d.error); return; }
        document.getElementById('total-tables').textContent = d.total_tables;
        const grid = document.getElementById('table-grid');
        grid.innerHTML = '';
        d.tables.filter(t => t.rows > 0).sort((a,b) => b.rows - a.rows).forEach(t => {
            const el = document.createElement('div');
            el.className = 'table-item';
            el.innerHTML = '<span class="table-name' + (t.important ? '" style="color:var(--rx-green)' : '') + '">' + t.name + '</span><span class="table-rows">' + t.rows.toLocaleString('fa') + '</span>';
            grid.appendChild(el);
        });
        const agentsDiv = document.getElementById('agents-section');
        if (d.agents && d.agents.length > 0) {
            agentsDiv.style.display = 'block';
            allAgents = d.agents;
            const list = document.getElementById('agents-list');
            list.innerHTML = '';
            d.agents.forEach(a => {
                const el = document.createElement('div');
                el.className = 'agent-card';
                el.dataset.search = (a.id + ' ' + a.username + ' ' + a.namecustom).toLowerCase();
                el.innerHTML = '<div class="agent-head"><span class="agent-badge ' + a.agent + '">' + (a.agent === 'n2' ? 'پیشرفته' : 'عادی') + '</span><b>#' + a.id + '</b> — ' + (a.username || a.namecustom || 'بدون نام') + (a.role ? ' (' + a.role + ')' : '') + '</div><div class="agent-stats"><div class="agent-stat"><div class="agent-stat-val">' + a.downline + '</div><div class="agent-stat-lbl">زیرمجموعه</div></div><div class="agent-stat"><div class="agent-stat-val">' + a.customers + '</div><div class="agent-stat-lbl">مشتری</div></div><div class="agent-stat"><div class="agent-stat-val">' + a.invoices + '</div><div class="agent-stat-lbl">فاکتور</div></div><div class="agent-stat"><div class="agent-stat-val">' + parseInt(a.revenue||0).toLocaleString('fa') + '</div><div class="agent-stat-lbl">درآمد</div></div></div>';
                el.onclick = () => { document.querySelectorAll('.agent-card').forEach(e => e.classList.remove('selected')); el.classList.add('selected'); selectedAgentId = a.id; document.getElementById('btn-select-agent').disabled = false; };
                list.appendChild(el);
            });
        } else {
            agentsDiv.style.display = 'none';
            // اگر نماینده‌ای نیست، فقط گزینه مهاجرت همه فعال باشد
        }
        document.getElementById('analyze-result').style.display = 'block';
    } catch (e) {
        document.getElementById('analyze-loading').innerHTML = '<div class="rx-alert error">خطا: ' + e.message + '</div>';
    }
}

function goStep2Back() { document.getElementById('step3').style.display = 'none'; document.getElementById('step2').style.display = 'block'; setActiveStep(2); }

function goStep3(mode) {
    migrationMode = mode;
    if (mode === 'all') { goStep4(); return; }
    document.getElementById('step3').style.display = 'block';
    document.getElementById('step3').scrollIntoView({behavior:'smooth'});
    setActiveStep(3);
    const list = document.getElementById('agent-select-list');
    list.innerHTML = '';
    allAgents.forEach(a => {
        const el = document.createElement('div');
        el.className = 'agent-card';
        el.dataset.search = (a.id + ' ' + a.username + ' ' + a.namecustom).toLowerCase();
        el.innerHTML = '<div class="agent-head"><input type="radio" name="sel-agent" value="' + a.id + '" style="margin-left:8px"><span class="agent-badge ' + a.agent + '">' + (a.agent==='n2'?'پیشرفته':'عادی') + '</span><b>#' + a.id + '</b> — ' + (a.username||a.namecustom||'بدون نام') + '</div><div class="agent-stats"><div class="agent-stat"><div class="agent-stat-val">' + a.downline + '</div><div class="agent-stat-lbl">زیرمجموعه</div></div><div class="agent-stat"><div class="agent-stat-val">' + a.customers + '</div><div class="agent-stat-lbl">مشتری</div></div><div class="agent-stat"><div class="agent-stat-val">' + a.invoices + '</div><div class="agent-stat-lbl">فاکتور</div></div><div class="agent-stat"><div class="agent-stat-val">' + parseInt(a.revenue||0).toLocaleString('fa') + '</div><div class="agent-stat-lbl">درآمد</div></div></div>';
        el.onclick = () => { el.querySelector('input[type=radio]').checked = true; selectedAgentId = a.id; document.getElementById('btn-preview').disabled = false; document.querySelectorAll('#agent-select-list .agent-card').forEach(e => e.classList.remove('selected')); el.classList.add('selected'); };
        list.appendChild(el);
    });
}

function goStep3Back() { document.getElementById('step4').style.display = 'none'; if (migrationMode === 'agent') { document.getElementById('step3').style.display = 'block'; setActiveStep(3); } else { document.getElementById('step2').style.display = 'block'; setActiveStep(2); } }

async function goStep4() {
    document.getElementById('step4').style.display = 'block';
    document.getElementById('step4').scrollIntoView({behavior:'smooth'});
    document.getElementById('preview-loading').style.display = 'block';
    document.getElementById('preview-result').style.display = 'none';
    document.getElementById('preview-error').style.display = 'none';
    setActiveStep(4);
    try {
        let url = 'db_migrator.php?ajax=preview_import&db=' + encodeURIComponent(selectedDb) + '&mode=' + migrationMode + getCustomCreds();
        if (migrationMode === 'agent' && selectedAgentId) url += '&agent_id=' + selectedAgentId;
        const d = await fetchJSON(url);
        document.getElementById('preview-loading').style.display = 'none';
        if (!d.ok) { showErr('preview-error', d.error); return; }
        const summary = document.getElementById('preview-summary');
        if (migrationMode === 'agent') {
            const agent = allAgents.find(a => a.id === selectedAgentId);
            summary.innerHTML = '👤 نماینده: <b>#' + selectedAgentId + '</b> — ' + (agent ? (agent.username||agent.namecustom) : '') + '<br>📊 مجموع رکوردها: <b>' + d.total_rows.toLocaleString('fa') + '</b>' + (d.ids_count ? '<br>👥 کاربران مرتبط: <b>' + d.ids_count.toLocaleString('fa') + '</b>' : '');
        } else {
            summary.innerHTML = '📦 مهاجرت کامل از دیتابیس <b>' + selectedDb + '</b><br>📊 مجموع رکوردها: <b>' + d.total_rows.toLocaleString('fa') + '</b>';
        }
        const grid = document.getElementById('preview-grid');
        grid.innerHTML = '';
        Object.entries(d.preview).forEach(([tbl, cnt]) => { if (cnt === 0) return; const el = document.createElement('div'); el.className = 'preview-item'; el.innerHTML = '<div class="preview-num">' + cnt.toLocaleString('fa') + '</div><div class="preview-tbl">' + tbl + '</div>'; grid.appendChild(el); });
        document.getElementById('preview-result').style.display = 'block';
    } catch (e) {
        document.getElementById('preview-loading').innerHTML = '<div class="rx-alert error">خطا: ' + e.message + '</div>';
    }
}

function goStep5() {
    document.getElementById('step5').style.display = 'block';
    document.getElementById('step5').scrollIntoView({behavior:'smooth'});
    setActiveStep(5);
    document.getElementById('exec-mode').value = migrationMode;
    document.getElementById('exec-db').value = selectedDb;
    document.getElementById('exec-agent').value = selectedAgentId;
    document.getElementById('exec-custom-host').value = document.getElementById('custom-host').value.trim();
    document.getElementById('exec-custom-user').value = document.getElementById('custom-user').value.trim();
    document.getElementById('exec-custom-pass').value = document.getElementById('custom-pass').value;
    document.getElementById('exec-form').style.display = 'none';
    document.getElementById('exec-loading').style.display = 'block';
    setTimeout(() => { document.getElementById('exec-form').submit(); }, 500);
}

setActiveStep(1);
loadDatabases();
</script>
</div>
</body></html>
