<?php
declare(strict_types=1);
if(!function_exists('rx_load_hosting_secrets')){require_once __DIR__.'/HostingSecrets.php';}
function rx_cron_authorize(): void {
    if (PHP_SAPI === 'cli') return;
    // Even loopback requests require the secret: reverse proxies commonly make
    // every external request appear as 127.0.0.1, so IP/source headers alone
    // are not an authentication boundary.
    $expected=(string)(rx_env('REDFOX_CRON_SECRET')?:'');
    // Secrets in query strings leak through access logs, browser history and
    // referrers. HTTP cron callers must use the dedicated request header.
    $provided=(string)($_SERVER['HTTP_X_CRON_SECRET']??'');
    if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(404);exit;}
}
function rx_cron_telemetry_start(PDO $pdo,string $job): void {
    if(!empty($GLOBALS['rx_cron_telemetry_started']))return;$GLOBALS['rx_cron_telemetry_started']=true;$run=bin2hex(random_bytes(16));$GLOBALS['rx_cron_run_id']=$run;
    try{$pdo->prepare("INSERT INTO cron_job_runs(job_name,run_id,status,started_at,host_name) VALUES(?,?,'running',?,?)")->execute([$job,$run,time(),gethostname()?:'']);}catch(Throwable$e){return;}
    register_shutdown_function(static function()use($pdo,$run){$e=error_get_last();$fatal=$e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true);try{$pdo->prepare('UPDATE cron_job_runs SET status=?,finished_at=?,error_summary=? WHERE run_id=?')->execute([$fatal?'failed':'completed',time(),$fatal?('php-fatal:type-'.(int)($e['type']??0).':'.preg_replace('/[^A-Za-z0-9_.-]/','_',basename((string)($e['file']??'unknown'))).':'.max(0,(int)($e['line']??0))):null,$run]);}catch(Throwable$x){}});
}
