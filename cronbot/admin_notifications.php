<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
rx_cron_boot('admin_notifications', 120);

if (!rx_cron_require_or_skip('admin_notifications', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../lib/AdminNotifications.php',
])) {
    return;
}
if (!rx_cron_db_ready('admin_notifications')) {
    return;
}

try {
    (new RedFoxAdminNotifications($pdo))->sync();
    if (PHP_SAPI === 'cli') {
        echo "OK\n";
    }
} catch (Throwable $e) {
    error_log('[cron:admin_notifications] ' . redfox_exception_fingerprint($e));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "FAILED\n");
    }
}
