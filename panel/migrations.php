<?php
declare(strict_types=1);

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/MigrationRunner.php';
require_once __DIR__ . '/../lib/DatabaseBackup.php';
require_once __DIR__ . '/lib/icons.php';

$q = $pdo->prepare('SELECT * FROM admin WHERE username=? LIMIT 1');
$q->execute([(string)($_SESSION['user'] ?? '')]);
$admin = $q->fetch(PDO::FETCH_ASSOC);
if (!is_array($admin)) {
    header('Location:login.php', true, 303);
    exit;
}
if ((string)($admin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit;
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$root = dirname(__DIR__);
$progressFile = $root . '/storage/migration-progress.json';
$genericFailure = 'مهاجرت ناموفق بود؛ جزئیات در لاگ امن سرور ثبت شد.';

/* Never return arbitrary/stale exception details from the progress file. */
$publicProgress = static function ($raw) use ($genericFailure): ?array {
    if (!is_array($raw)) {
        return null;
    }
    $status = in_array((string)($raw['status'] ?? ''), ['running', 'completed', 'failed'], true)
        ? (string)$raw['status']
        : 'failed';
    $cleanText = static function ($value, int $max = 220): string {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    };
    $result = [
        'status' => $status,
        'percent' => max(0, min(100, (int)($raw['percent'] ?? 0))),
        'stage' => $status === 'failed' ? 'مهاجرت متوقف شد' : $cleanText($raw['stage'] ?? ''),
        'current' => basename($cleanText($raw['current'] ?? '', 191)),
        'index' => max(0, (int)($raw['index'] ?? 0)),
        'total' => max(0, (int)($raw['total'] ?? 0)),
        'updated_at' => max(0, (int)($raw['updated_at'] ?? time())),
    ];
    if ($status === 'failed') {
        $result['error'] = $genericFailure;
    }
    if ($status === 'completed') {
        $result['applied'] = max(0, (int)($raw['applied'] ?? 0));
        $result['backup'] = basename($cleanText($raw['backup'] ?? '', 191));
    }
    return $result;
};

$writeProgress = static function (array $data) use ($progressFile, $publicProgress): void {
    $data['updated_at'] = time();
    $data = $publicProgress($data) ?? [
        'status' => 'failed',
        'percent' => 0,
        'stage' => 'مهاجرت متوقف شد',
        'error' => 'مهاجرت ناموفق بود؛ جزئیات در لاگ امن سرور ثبت شد.',
        'current' => '',
        'index' => 0,
        'total' => 0,
        'updated_at' => time(),
    ];
    $tmp = $progressFile . '.tmp.' . bin2hex(random_bytes(4));
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($tmp, $encoded, LOCK_EX) === false || !@rename($tmp, $progressFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to update migration progress state.');
    }
    @chmod($progressFile, 0600);
};

if ($requestMethod === 'GET' && (string)($_GET['ajax'] ?? '') === 'progress') {
    header('Content-Type:application/json; charset=utf-8');
    header('Cache-Control:no-store, private');
    $raw = null;
    if (is_file($progressFile)) {
        $decoded = json_decode((string)file_get_contents($progressFile), true);
        $raw = $publicProgress($decoded);
    }
    echo json_encode(['ok' => is_array($raw), 'progress' => $raw], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($requestMethod === 'POST') {
    header('Content-Type:application/json; charset=utf-8');
    header('Cache-Control:no-store, private');
    if ((string)($_POST['action'] ?? '') !== 'run_ajax') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_action'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_write_close();
    $marker = $root . '/storage/maintenance.flag';
    $migrationLockPath = $root . '/storage/migration-run.lock';
    $migrationLock = null;
    $ownsMigrationLock = false;
    $ownsMarker = false;
    try {
        $migrationLock = @fopen($migrationLockPath, 'c+');
        if (!is_resource($migrationLock) || !flock($migrationLock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('A migration is already running.');
        }
        $ownsMigrationLock = true;
        @chmod($migrationLockPath, 0600);

        $key = rx_key_from_env('REDFOX_BACKUP_KEY');
        if ($key === null) {
            throw new RuntimeException('Backup key is unavailable.');
        }
        ignore_user_abort(true);
        @set_time_limit(900);
        $writeProgress(['status' => 'running', 'percent' => 2, 'stage' => 'آماده‌سازی و قفل‌کردن عملیات', 'current' => '', 'index' => 0, 'total' => 0]);
        if (file_put_contents($marker, json_encode(['type' => 'migration', 'started_at' => time()]), LOCK_EX) === false) {
            throw new RuntimeException('Unable to activate maintenance mode.');
        }
        @chmod($marker, 0600);
        $ownsMarker = true;

        register_shutdown_function(static function () use ($marker, $progressFile): void {
            $fatal = error_get_last();
            if ($fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                error_log('[migration-fatal] type ' . (int)($fatal['type'] ?? 0) . ' in ' . basename((string)($fatal['file'] ?? 'unknown')) . ':' . (int)($fatal['line'] ?? 0));
                @unlink($marker);
                @file_put_contents($progressFile, json_encode([
                    'status' => 'failed',
                    'percent' => 0,
                    'stage' => 'مهاجرت متوقف شد',
                    'error' => 'مهاجرت ناموفق بود؛ جزئیات در لاگ امن سرور ثبت شد.',
                    'current' => '',
                    'index' => 0,
                    'total' => 0,
                    'updated_at' => time(),
                ], JSON_UNESCAPED_UNICODE), LOCK_EX);
                @chmod($progressFile, 0600);
            }
        });

        $writeProgress(['status' => 'running', 'percent' => 8, 'stage' => 'تهیه بکاپ رمزنگاری‌شده دیتابیس', 'current' => 'Backup', 'index' => 0, 'total' => 0]);
        $backup = RedFoxDatabaseBackup::create($pdo, $root . '/storage/migration-backups', $key);
        $writeProgress(['status' => 'running', 'percent' => 30, 'stage' => 'بکاپ تکمیل شد؛ بررسی Migrationها', 'current' => basename($backup), 'index' => 0, 'total' => 0]);
        $rows = RedFoxMigrationRunner::run($pdo, $root . '/migrations', false, static function ($pct, $stage, $version, $idx, $total) use ($writeProgress): void {
            $writeProgress(['status' => 'running', 'percent' => 30 + (int)floor($pct * .68), 'stage' => $stage, 'current' => $version, 'index' => $idx, 'total' => $total]);
        });
        try {
            $pdo->prepare('INSERT INTO audit_log(admin_user,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?)')->execute([
                $admin['username'],
                'database.migrate',
                'schema',
                json_encode(['backup' => basename($backup), 'results' => $rows]),
                redfox_client_ip(),
                date('c'),
            ]);
        } catch (Throwable $auditError) {
            error_log('[migration-audit] ' . get_class($auditError) . ': ' . redfox_exception_fingerprint($auditError));
        }
        $appliedCount = count(array_filter($rows, static fn($r): bool => ($r[1] ?? '') === 'applied'));
        $writeProgress(['status' => 'completed', 'percent' => 100, 'stage' => 'مهاجرت با موفقیت تکمیل شد', 'current' => '', 'index' => count($rows), 'total' => count($rows), 'applied' => $appliedCount, 'backup' => basename($backup)]);
        @unlink($marker);
        $ownsMarker = false;
        flock($migrationLock, LOCK_UN);
        fclose($migrationLock);
        $migrationLock = null;
        echo json_encode(['ok' => true, 'applied' => $appliedCount, 'backup' => basename($backup)], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if ($ownsMarker) {
            @unlink($marker);
        }
        if (is_resource($migrationLock)) {
            @flock($migrationLock, LOCK_UN);
            @fclose($migrationLock);
        }
        error_log('[migration] ' . get_class($e) . ': ' . redfox_exception_fingerprint($e));
        if (!$ownsMigrationLock) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'migration_busy', 'message' => 'یک عملیات مهاجرت دیگر در حال اجرا است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $writeProgress(['status' => 'failed', 'percent' => 0, 'stage' => 'مهاجرت متوقف شد', 'error' => $genericFailure, 'current' => '', 'index' => 0, 'total' => 0]);
        } catch (Throwable $progressError) {
            error_log('[migration-progress] ' . get_class($progressError) . ': ' . redfox_exception_fingerprint($progressError));
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'migration_failed', 'message' => $genericFailure], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$files=glob($root.'/migrations/*.sql')?:[];sort($files,SORT_NATURAL);$applied=[];try{foreach($pdo->query('SELECT version,checksum,executed_at,execution_ms FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC)as$r)$applied[$r['version']]=$r;}catch(Throwable$e){}$pending=0;$list=[];foreach($files as$f){$v=basename($f);$sum=hash_file('sha256',$f);$state='pending';if(isset($applied[$v]))$state=hash_equals((string)$applied[$v]['checksum'],$sum)?'applied':'checksum_mismatch';else$pending++;$list[]=['version'=>$v,'checksum'=>$sum,'state'=>$state,'row'=>$applied[$v]??null];}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مرکز مهاجرت دیتابیس</title><link rel="stylesheet" href="css/theme.css"><style>.ok{color:var(--color-success)}.bad{color:var(--color-danger)}.warn{color:#f59e0b}.mig-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}.mig-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:17px}.mig-stat b{font-size:25px;display:block}.migration-overlay{display:none;position:fixed;inset:0;background:rgba(2,6,12,.88);backdrop-filter:blur(10px);z-index:9999;align-items:center;justify-content:center}.migration-overlay.show{display:flex}.migration-panel{width:min(650px,92vw);padding:28px;border:1px solid rgba(255,255,255,.14);border-radius:22px;background:linear-gradient(145deg,#171b28,#0d111a);box-shadow:0 35px 100px rgba(0,0,0,.65);text-align:center}.migration-track{height:19px;background:#252b3b;border-radius:99px;overflow:hidden;margin:22px 0 10px}.migration-bar{height:100%;width:0;background:linear-gradient(90deg,#2563eb,#06b6d4,#22c55e);transition:width .4s;border-radius:99px}.migration-meta{display:flex;justify-content:space-between}.migration-result{margin-top:15px;padding:12px;border-radius:10px;display:none}.migration-result.ok{display:block;background:rgba(34,197,94,.15);color:#86efac}.migration-result.fail{display:block;background:rgba(239,68,68,.15);color:#fca5a5}</style></head><body><div id="migrationOverlay" class="migration-overlay"><div class="migration-panel"><h2 id="migrationTitle">آماده‌سازی مهاجرت دیتابیس</h2><p id="migrationStage">در حال شروع بکاپ خودکار...</p><div class="migration-track"><div id="migrationBar" class="migration-bar"></div></div><div class="migration-meta"><b id="migrationPercent">0٪</b><span id="migrationFiles">0 / 0 Migration</span></div><div id="migrationResult" class="migration-result"></div><p class="text-muted" style="margin-top:15px">ابتدا بکاپ رمزنگاری‌شده گرفته می‌شود؛ سپس فقط Migrationهای اجرا‌نشده اعمال می‌شوند.</p></div></div><section id="container"><?php include'header.php';?><section id="main-content"><div class="wrapper"><div class="page-head"><h1 class="page-head__title">مرکز مهاجرت دیتابیس</h1><div class="page-head__sub">بدون کادر اجباری — بکاپ خودکار، Checksum، Lock و Progress واقعی</div></div><div class="mig-grid"><div class="mig-stat"><b><?=count($files)?></b>کل Migration</div><div class="mig-stat"><b><?=count($applied)?></b>اجراشده</div><div class="mig-stat"><b><?=$pending?></b>در انتظار</div><div class="mig-stat"><b><?=rx_key_from_env('REDFOX_BACKUP_KEY')!==null?'✅':'❌'?></b>Backup Key</div></div><div class="card"><h2>اجرای خودکار Migrationهای در انتظار</h2><div class="page-guide">با زدن دکمه، سیستم به‌صورت خودکار بکاپ رمزنگاری‌شده می‌گیرد، Migrationهای لازم را اجرا می‌کند و درصد پیشرفت را نشان می‌دهد. نیازی به تایپ هیچ عبارت انگلیسی نیست.</div><?php if(rx_key_from_env('REDFOX_BACKUP_KEY')===null):?><a class="btn btn-warning" href="hosting_setup.php">ابتدا Secret بکاپ را در Wizard بسازید</a><?php else:?><form id="migrationForm" method="post"><input type="hidden" name="rx_csrf_token" value="<?=htmlspecialchars(redfox_csrf_token(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="run_ajax"><button id="migrateBtn" class="btn btn-primary">🗄 بکاپ و بروزرسانی ساختار دیتابیس</button></form><?php endif;?></div><div class="card"><h2>وضعیت Migrationها</h2><div class="table-wrap"><table class="app-table"><tr><th>Migration</th><th>وضعیت</th><th>Checksum</th><th>زمان اجرا</th></tr><?php foreach($list as$m):?><tr><td><code><?=htmlspecialchars($m['version'])?></code></td><td class="<?=$m['state']==='applied'?'ok':($m['state']==='pending'?'warn':'bad')?>"><?=$m['state']==='applied'?'اجرا شده':($m['state']==='pending'?'در انتظار':'Checksum نامعتبر')?></td><td><code><?=substr($m['checksum'],0,16)?>…</code></td><td><?=$m['row']?date('Y/m/d H:i',(int)$m['row']['executed_at']).' / '.$m['row']['execution_ms'].'ms':'—'?></td></tr><?php endforeach;?></table></div></div></div></section></section><script>
(function(){const form=document.getElementById('migrationForm');if(!form)return;const overlay=document.getElementById('migrationOverlay'),bar=document.getElementById('migrationBar'),pct=document.getElementById('migrationPercent'),stage=document.getElementById('migrationStage'),files=document.getElementById('migrationFiles'),res=document.getElementById('migrationResult'),title=document.getElementById('migrationTitle');form.addEventListener('submit',async function(e){e.preventDefault();if(!confirm('آیا بکاپ خودکار گرفته شود و Migrationهای در انتظار اجرا شوند؟'))return;overlay.classList.add('show');let done=false;const poll=setInterval(async()=>{try{const r=await fetch('migrations.php?ajax=progress',{credentials:'same-origin',cache:'no-store'});if(r.ok){const j=await r.json(),x=j.progress;if(!x)return;const p=Math.max(0,Math.min(100,parseInt(x.percent||0)));bar.style.width=p+'%';pct.textContent=p+'٪';stage.textContent=x.stage||'در حال اجرا...';files.textContent=(x.index||0)+' / '+(x.total||0)+' Migration';if(x.status==='completed'||x.status==='failed'){done=true;clearInterval(poll);res.className='migration-result '+(x.status==='completed'?'ok':'fail');res.textContent=x.status==='completed'?'مهاجرت با موفقیت تکمیل شد. بکاپ: '+(x.backup||''):(x.error||'مهاجرت ناموفق بود');title.textContent=x.status==='completed'?'عملیات تکمیل شد':'عملیات متوقف شد';if(x.status==='completed')setTimeout(()=>location.reload(),2200);}}}catch(err){}},650);try{const r=await fetch('migrations.php',{method:'POST',body:new FormData(form),credentials:'same-origin'});const j=await r.json();if(!j.ok)throw new Error(j.error||'خطا در مهاجرت');if(!done){bar.style.width='100%';pct.textContent='100٪';stage.textContent='تکمیل';res.className='migration-result ok';res.textContent='بکاپ و Migration با موفقیت انجام شد.';clearInterval(poll);setTimeout(()=>location.reload(),1800);}}catch(err){clearInterval(poll);res.className='migration-result fail';res.textContent=err.message;title.textContent='مهاجرت ناموفق';}});})();
</script></body></html>
