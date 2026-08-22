<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(404);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/SecureUpdater.php';

$root = dirname(__DIR__);
$checks = [];
$add = static function (string $name, bool $ok, string $level, string $message = '') use (&$checks): void {
    $checks[] = ['name' => $name, 'ok' => $ok, 'level' => $level, 'message' => $message];
};

$add('php_version', version_compare(PHP_VERSION, '8.4.0', '>='), 'critical', PHP_VERSION);
foreach (['pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'] as $extension) {
    $add('ext_' . $extension, extension_loaded($extension), 'critical');
}
$add('crypto_backend', function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt'), 'critical');
$add('database', $pdo instanceof PDO, 'critical');

try {
    $migrations = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    $add('migration_013', in_array('013_update_progress_ui.sql', $migrations, true), 'critical', implode(',', $migrations));
} catch (Throwable $e) {
    $add('migrations', false, 'critical', 'table unavailable');
}

$securePath = function_exists('rx_hosting_secrets_path') ? rx_hosting_secrets_path($root) : $root . '/storage/secure.env.php';
$secureMode = is_file($securePath) ? (fileperms($securePath) & 0777) : 0;
$add(
    'secure_env_private_mode',
    is_file($securePath) && ($secureMode & 0077) === 0,
    'critical',
    is_file($securePath) ? substr(sprintf('%o', $secureMode), -4) : 'missing'
);
$add('logs_protected', is_file($root . '/logs/.htaccess'), 'critical');
$add('root_htaccess', is_file($root . '/.htaccess'), 'critical');

$uniqueContracts = [
    ['name' => 'payment_orders', 'table' => 'Payment_report', 'column' => 'id_order', 'index' => 'uq_payment_order_id'],
    ['name' => 'product_codes', 'table' => 'product', 'column' => 'code_product', 'index' => 'uq_product_code'],
    ['name' => 'reseller_bot_tokens', 'table' => 'botsaz', 'column' => 'bot_token', 'index' => 'uq_botsaz_token'],
];
foreach ($uniqueContracts as $contract) {
    try {
        $table = $contract['table'];
        $column = $contract['column'];
        $index = $contract['index'];
        $duplicateGroups = (int)$pdo->query(
            "SELECT COUNT(*) FROM (SELECT `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column`<>'' GROUP BY `$column` HAVING COUNT(*)>1) duplicate_values"
        )->fetchColumn();
        $add('unique_' . $contract['name'], $duplicateGroups === 0, 'critical', $duplicateGroups . ' duplicate groups');

        $uniqueIndex = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($table) . " AND INDEX_NAME=" . $pdo->quote($index) . " AND NON_UNIQUE=0"
        )->fetchColumn();
        $add($contract['name'] . '_constraint', $uniqueIndex === 1, 'warning', 'run bin/enforce-constraints.php --apply after reconciling duplicates');
    } catch (Throwable $e) {
        $add('unique_' . $contract['name'], false, 'critical', 'check failed');
    }
}

$add('installer_removed', !is_dir($root . '/installer'), 'warning', 'installer should be removed after installation');
$add('cron_secret', (string)rx_env('REDFOX_CRON_SECRET') !== '', 'warning');
$add('health_token', (string)rx_env('REDFOX_HEALTH_TOKEN') !== '', 'warning');

try {
    $keyIds = RedFoxSecureUpdater::trustedKeyIds();
    $add('update_public_keys', count($keyIds) > 0, 'warning', count($keyIds) > 0 ? implode(',', $keyIds) : 'updater remains disabled until a trusted key is configured');
} catch (Throwable $e) {
    $add('update_public_keys', false, 'warning', 'updater remains disabled until a trusted key is configured');
}

$add('backup_key', rx_key_from_env('REDFOX_BACKUP_KEY') !== null, 'critical');
try {
    $releaseIntegrity = RedFoxSecureUpdater::verifyRelease($root);
    $add(
        'release_integrity',
        !empty($releaseIntegrity['ok']),
        !empty($releaseIntegrity['unsigned']) ? 'warning' : 'critical',
        implode(';', array_slice($releaseIntegrity['errors'] ?? [], 0, 5))
    );
} catch (Throwable $e) {
    $add('release_integrity', false, 'critical', redfox_exception_fingerprint($e));
}

$updateChannel = (string)(rx_env('REDFOX_UPDATE_CHANNEL') ?: 'stable');
$add('update_channel', in_array($updateChannel, ['stable', 'beta'], true), 'critical', $updateChannel);

try {
    $encryptedRows = (int)$pdo->query(
        "SELECT COUNT(*) FROM marzban_panel WHERE password_panel LIKE 'rxenc:v1:%' OR api_key LIKE 'rxenc:v1:%'"
    )->fetchColumn();
    $add('master_key', $encryptedRows === 0 || rx_secret_master_key() !== null, 'critical', $encryptedRows . ' encrypted rows');
} catch (Throwable $e) {
    $add('master_key_check', false, 'warning', redfox_exception_fingerprint($e));
}

foreach (['logs', 'storage', 'storage/cache'] as $directory) {
    $add('writable_' . $directory, is_dir($root . '/' . $directory) && is_writable($root . '/' . $directory), 'critical');
}

$critical = false;
foreach ($checks as $check) {
    $icon = $check['ok'] ? 'OK' : strtoupper($check['level']);
    echo sprintf("%-10s %-30s %s\n", $icon, $check['name'], $check['message']);
    if (!$check['ok'] && $check['level'] === 'critical') $critical = true;
}
exit($critical ? 1 : 0);
