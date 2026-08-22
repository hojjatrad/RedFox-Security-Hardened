<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/HostingSecrets.php';

if (($argv[1] ?? '') === 'worker') {
    if (count($argv) !== 5) exit(90);
    if (function_exists('usleep')) usleep(random_int(0, 80000));
    rx_update_hosting_secrets([$argv[3] => $argv[4]], $argv[2]);
    exit(0);
}

$fallback = in_array('--fallback', $argv, true);
$root = sys_get_temp_dir() . '/rx-hosting-concurrency-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Unable to create test root.');
$keys = rx_hosting_secret_allowed_keys();
$processes = [];
$failed = [];

try {
    foreach ($keys as $index => $key) {
        $command = [PHP_BINARY];
        if ($fallback) {
            $command[] = '-d';
            $command[] = 'disable_functions=flock';
        }
        $command = array_merge($command, [__FILE__, 'worker', $root, $key, 'value-' . ($index + 1)]);
        $process = proc_open($command, [
            ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start worker.');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2], $key];
    }

    foreach ($processes as [$process, $stdoutPipe, $stderrPipe, $key]) {
        $stdout = stream_get_contents($stdoutPipe);
        $stderr = stream_get_contents($stderrPipe);
        fclose($stdoutPipe);
        fclose($stderrPipe);
        $code = proc_close($process);
        if ($code !== 0) $failed[] = $key . ':' . $code . ':' . trim($stdout . ' ' . $stderr);
    }
    if ($failed) throw new RuntimeException('Worker failures: ' . implode(';', $failed));

    $stored = rx_load_hosting_secrets($root, true);
    if (count($stored) !== count($keys)) {
        throw new RuntimeException('Concurrent merge lost values: ' . count($stored) . '/' . count($keys));
    }
    foreach ($keys as $index => $key) {
        if (($stored[$key] ?? null) !== 'value-' . ($index + 1)) {
            throw new RuntimeException('Incorrect merged value for ' . $key);
        }
    }
    $secretPath = $root . '/storage/secure.env.php';
    $mode = fileperms($secretPath) & 0777;
    if (($mode & 0077) !== 0) throw new RuntimeException('Secret mode is too broad: ' . decoct($mode));
    if (is_dir($root . '/storage/.secure-env.mutex')) throw new RuntimeException('Fallback mutex was not released.');

    echo 'RUNTIME HOSTING SECRETS CONCURRENCY OK mode=' . ($fallback ? 'mkdir' : 'flock')
        . ' keys=' . count($stored) . ' file_mode=' . decoct($mode) . "\n";
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($root);
    }
}
