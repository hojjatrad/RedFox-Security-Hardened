<?php

require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/HostingSecrets.php';
require_once __DIR__ . '/lib/Secrets.php';
require_once __DIR__ . '/lib/Observability.php';
require_once __DIR__ . '/lib/SchemaGuard.php';

// Keep PHP diagnostics out of every web-accessible execution directory.  Some
// legacy modules used a relative "error_log" path, which could expose logs as
// static files under Apache/LiteSpeed depending on the current working dir.
$rxPrivateLogDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
$rxPrivateLogFile = $rxPrivateLogDir . DIRECTORY_SEPARATOR . 'php-error.log';
if (!is_link($rxPrivateLogDir)
    && !is_link($rxPrivateLogFile)
    && (is_dir($rxPrivateLogDir) || @mkdir($rxPrivateLogDir, 0750, true))) {
    @chmod($rxPrivateLogDir, 0750);
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    ini_set('error_log', $rxPrivateLogFile);
}
unset($rxPrivateLogDir, $rxPrivateLogFile);

// TLS certificate and hostname validation is mandatory in this hardened build.
if (PHP_SAPI !== 'cli') {
    $rxMaintenanceFile=__DIR__.'/storage/maintenance.flag';
    $rxScript=basename((string)($_SERVER['SCRIPT_NAME']??''));
    $rxMaintenanceAllowed=in_array($rxScript,['health.php','ready.php'],true)||($rxScript==='update.php'&&($_GET['ajax']??'')==='progress')||($rxScript==='migrations.php'&&($_GET['ajax']??'')==='progress');
    if(is_file($rxMaintenanceFile)&&!$rxMaintenanceAllowed){
        if(!headers_sent()){http_response_code(503);header('Retry-After: 120');header('Content-Type:text/html; charset=utf-8');}
        $msg=trim((string)@file_get_contents($rxMaintenanceFile));
        exit('<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="background:#0b0c10;color:#fff;font-family:sans-serif;text-align:center;padding:15vh 20px"><h1>در حال بروزرسانی</h1><p>'.htmlspecialchars($msg?:'لطفاً چند دقیقه دیگر تلاش کنید.',ENT_QUOTES,'UTF-8').'</p></body></html>');
    }
    unset($rxMaintenanceFile,$rxScript,$rxMaintenanceAllowed);
}
if (!defined('BOT_PANEL_VERIFY_TLS')) define('BOT_PANEL_VERIFY_TLS', true);
if (!defined('BOT_CURL_VERIFY_TLS')) define('BOT_CURL_VERIFY_TLS', true);

$dbname     = '';
$usernamedb = '';
$passworddb = '';
$dbhost     = (string)(rx_env('REDFOX_DB_HOST') ?: 'localhost');
if ($dbname === '')     $dbname     = (string)(rx_env('REDFOX_DB_NAME') ?: '');
if ($usernamedb === '') $usernamedb = (string)(rx_env('REDFOX_DB_USER') ?: '');
if ($passworddb === '') $passworddb = (string)(rx_env('REDFOX_DB_PASSWORD') ?: '');

$connect = null;
$pdo     = null;
$dsn     = '';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

if ($dbname !== '' && $usernamedb !== '') {
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    try {
        $connect = @mysqli_connect($dbhost, $usernamedb, $passworddb, $dbname);
    } catch (\Throwable $rxMysqliConnectError) {
        $connect = null;
        error_log('config.php mysqli_connect failed: ' . redfox_exception_fingerprint($rxMysqliConnectError));
    }
    if ($connect instanceof mysqli) {
        @mysqli_set_charset($connect, 'utf8mb4');
    } else {
        $connect = null;
    }

    $dsn = 'mysql:host=' . $dbhost . ';dbname=' . $dbname . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $usernamedb, $passworddb, $options);
    } catch (\PDOException $rxPdoError) {
        $pdo = null;
        error_log('config.php PDO connection failed: ' . redfox_exception_fingerprint($rxPdoError));
    }
} else {
    $rxInstallerPending = is_file(__DIR__ . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'index.php');
    if (!$rxInstallerPending) {
        $rxConfigEmptyMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_config_empty.flag';
        if (!is_file($rxConfigEmptyMarker) || (time() - (int) @filemtime($rxConfigEmptyMarker)) > 3600) {
            error_log('config.php: database credentials are empty — fill $dbname/$usernamedb/$passworddb to enable DB-backed features.');
            @touch($rxConfigEmptyMarker);
        }
        unset($rxConfigEmptyMarker);
    }
    unset($rxInstallerPending);
}

$APIKEY                     = '';
$adminnumber                = '';
$domainhosts                = '';
$usernamebot                = '';
if ($APIKEY === '')      $APIKEY      = (string)(rx_env('REDFOX_BOT_TOKEN') ?: '');
if ($adminnumber === '') $adminnumber = (string)(rx_env('REDFOX_ADMIN_ID') ?: '');
if ($domainhosts === '') $domainhosts = (string)(rx_env('REDFOX_DOMAIN') ?: '');
if ($usernamebot === '') $usernamebot = (string)(rx_env('REDFOX_BOT_USERNAME') ?: '');
$telegramCurlTimeout        = 10;
$telegramStrictIpValidation = true;
$domainhosts                = redfox_normalize_domain($domainhosts);


if (!defined('APP_ORIGIN') && $domainhosts !== '') {
    $rxConfiguredOrigin = redfox_configured_origin();
    if ($rxConfiguredOrigin !== '') define('APP_ORIGIN', $rxConfiguredOrigin);
    unset($rxConfiguredOrigin);
}


$GLOBALS['dbhost']                     = $dbhost;
$GLOBALS['dbname']                     = $dbname;
$GLOBALS['usernamedb']                 = $usernamedb;
$GLOBALS['passworddb']                 = $passworddb;
$GLOBALS['dsn']                        = $dsn;
$GLOBALS['options']                    = $options;
$GLOBALS['pdo']                        = $pdo;
$GLOBALS['connect']                    = $connect;
$GLOBALS['APIKEY']                     = $APIKEY;
$GLOBALS['adminnumber']                = $adminnumber;
$GLOBALS['domainhosts']                = $domainhosts;
$GLOBALS['usernamebot']                = $usernamebot;
$GLOBALS['telegramCurlTimeout']        = $telegramCurlTimeout;
$GLOBALS['telegramStrictIpValidation'] = $telegramStrictIpValidation;

// Central security policy for the administrator panel. Use real paths rather
// than a caller-controlled URL fragment when deciding whether this is a panel request.
$__scriptFile = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
$__panelRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'panel');
$__isPanelPage = is_string($__scriptFile) && is_string($__panelRoot)
    && ($__scriptFile === $__panelRoot || str_starts_with($__scriptFile, $__panelRoot . DIRECTORY_SEPARATOR));
if (!$__isPanelPage && !is_string($__scriptFile)) {
    $__scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $__isPanelPage = preg_match('#(?:^|/)panel/[^/]+\.php$#', $__scriptName) === 1;
}
if ($__isPanelPage) {
    redfox_secure_session_start();
    redfox_security_headers();
    $__panelScriptBase = is_string($__scriptFile) ? basename($__scriptFile) : basename((string)($__scriptName ?? ''));
    if ($__panelScriptBase !== 'login.php') {
        if (!($pdo instanceof PDO)) {
            http_response_code(503);
            exit('Administrator authentication is temporarily unavailable.');
        }
        redfox_require_current_admin($pdo, 'login.php');
    }
    redfox_enforce_csrf();
}
unset($__scriptFile, $__panelRoot, $__scriptName, $__panelScriptBase, $__isPanelPage);

// Backward-compatible names used by existing templates.
if (!function_exists('rx_csrf_token')) { function rx_csrf_token() { return redfox_csrf_token(); } }
if (!function_exists('rx_csrf_field')) { function rx_csrf_field() { return redfox_csrf_field(); } }

require_once __DIR__ . '/proxy.php';
