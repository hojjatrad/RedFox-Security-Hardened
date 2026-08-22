<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/HostingSecrets.php';

function hs_fail(string $message): never { fwrite(STDERR, "FAIL {$message}\n"); exit(1); }
function hs_assert(bool $condition, string $message): void { if (!$condition) hs_fail($message); }
function hs_root(string $suffix): string {
    $root = sys_get_temp_dir() . '/redfox-hosting-secrets-' . getmypid() . '-' . $suffix . '-' . bin2hex(random_bytes(3));
    if (!mkdir($root, 0700, true)) hs_fail('cannot create test root');
    return $root;
}
function hs_remove(string $path): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) hs_remove($path . '/' . $entry);
    @rmdir($path);
}

$roots = [];
try {
    $root = hs_root('roundtrip'); $roots[] = $root;
    $first = rx_update_hosting_secrets([
        'REDFOX_DB_HOST' => 'localhost',
        'REDFOX_DB_NAME' => "db_'quoted",
        'REDFOX_DB_PASSWORD' => 'p@ss\\word',
        'REDFOX_MASTER_KEY' => str_repeat('ab', 32),
        'REDFOX_UPDATE_CHANNEL' => 'stable',
    ], $root);
    hs_assert(($first['REDFOX_DB_NAME'] ?? '') === "db_'quoted", 'initial write changed value');
    $file = $root . '/storage/secure.env.php';
    hs_assert(is_file($file) && !is_link($file), 'secure file missing/not regular');
    clearstatcache(true, $file);
    $mode = fileperms($file);
    hs_assert(is_int($mode) && (($mode & 0077) === 0), 'secure file permissions are broad');
    hs_assert(rx_env('REDFOX_DB_PASSWORD', '', $root) === 'p@ss\\word', 'rx_env did not read same-request cache');

    $second = rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'db.internal', 'REDFOX_HEALTH_TOKEN' => 'health'], $root);
    hs_assert(($second['REDFOX_DB_NAME'] ?? '') === "db_'quoted", 'merge lost existing key');
    hs_assert(($second['REDFOX_DB_HOST'] ?? '') === 'db.internal', 'merge did not update key');
    $third = rx_update_hosting_secrets(['REDFOX_HEALTH_TOKEN' => null], $root);
    hs_assert(!isset($third['REDFOX_HEALTH_TOKEN']), 'null did not remove key');
    $payload = file_get_contents($file);
    hs_assert(is_string($payload) && !str_contains($payload, 'putenv'), 'generated file unexpectedly mutates environment');

    // A fresh process/cache must load the generated PHP array correctly.
    unset($GLOBALS['rx_hosting_secrets_cache']);
    $loaded = rx_load_hosting_secrets($root);
    hs_assert(($loaded['REDFOX_DB_NAME'] ?? '') === "db_'quoted", 'fresh load failed');

    // secure.env.php may never be a symlink.
    $symlinkRoot = hs_root('file-link'); $roots[] = $symlinkRoot;
    mkdir($symlinkRoot . '/storage', 0700);
    $outside = $symlinkRoot . '/outside.php';
    file_put_contents($outside, "<?php return [];\n");
    if (function_exists('symlink') && @symlink($outside, $symlinkRoot . '/storage/secure.env.php')) {
        try { rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'x'], $symlinkRoot); hs_fail('file symlink accepted'); }
        catch (RedFoxHostingSecretException $e) { hs_assert($e->safeCode === 'RXSEC-FILE-TYPE', 'wrong file symlink diagnostic'); }
    }

    // storage itself may never redirect writes outside the application root.
    $storageLinkRoot = hs_root('storage-link'); $roots[] = $storageLinkRoot;
    $outsideDir = hs_root('outside'); $roots[] = $outsideDir;
    if (function_exists('symlink') && @symlink($outsideDir, $storageLinkRoot . '/storage')) {
        try { rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'x'], $storageLinkRoot); hs_fail('storage symlink accepted'); }
        catch (RedFoxHostingSecretException $e) { hs_assert($e->safeCode === 'RXSEC-STORAGE-TYPE', 'wrong storage symlink diagnostic'); }
    }

    // When flock is disabled, the atomic directory fallback must reject links
    // and safely reap only a genuinely stale empty mutex.
    if (!function_exists('flock')) {
        $mutexLinkRoot = hs_root('mutex-link'); $roots[] = $mutexLinkRoot;
        mkdir($mutexLinkRoot . '/storage', 0700);
        $mutexOutside = hs_root('mutex-outside'); $roots[] = $mutexOutside;
        if (function_exists('symlink') && @symlink($mutexOutside, $mutexLinkRoot . '/storage/.secure-env.mutex')) {
            try { rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'x'], $mutexLinkRoot); hs_fail('mutex symlink accepted'); }
            catch (RedFoxHostingSecretException $e) { hs_assert($e->safeCode === 'RXSEC-LOCK-TYPE', 'wrong mutex symlink diagnostic'); }
        }

        $staleRoot = hs_root('stale-mutex'); $roots[] = $staleRoot;
        mkdir($staleRoot . '/storage', 0700);
        mkdir($staleRoot . '/storage/.secure-env.mutex', 0700);
        touch($staleRoot . '/storage/.secure-env.mutex', time() - 360);
        $staleResult = rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'stale-recovered'], $staleRoot);
        hs_assert(($staleResult['REDFOX_DB_HOST'] ?? '') === 'stale-recovered', 'stale mutex was not recovered');
        hs_assert(!is_dir($staleRoot . '/storage/.secure-env.mutex'), 'stale mutex was not released');
    }

    // Corrupt/truncated files produce a stable non-secret code and guidance.
    $badRoot = hs_root('bad'); $roots[] = $badRoot;
    mkdir($badRoot . '/storage', 0700);
    file_put_contents($badRoot . '/storage/secure.env.php', "<?php return [\n");
    try { rx_update_hosting_secrets(['REDFOX_DB_HOST' => 'x'], $badRoot); hs_fail('malformed file accepted'); }
    catch (RedFoxHostingSecretException $e) {
        hs_assert($e->safeCode === 'RXSEC-FILE-PARSE', 'wrong parse diagnostic');
        $diagnostic = rx_hosting_secret_diagnostic($e);
        hs_assert(($diagnostic['code'] ?? '') === 'RXSEC-FILE-PARSE' && !empty($diagnostic['message']), 'missing safe diagnostic');
    }

    echo "RUNTIME HOSTING SECRETS OK\n";
} finally {
    foreach (array_reverse($roots) as $path) hs_remove($path);
}
