<?php
/** Resume only payment stages whose side effects are unambiguous. */
require_once __DIR__ . '/_init.php';
rx_cron_boot('payment_reconcile', 240);

if (!rx_cron_require_or_skip('payment_reconcile', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
    __DIR__ . '/../keyboard.php',
    __DIR__ . '/../jdf.php',
    __DIR__ . '/../lib/PaymentConfirm.php',
])) return;
if (!rx_cron_db_ready('payment_reconcile')) return;
if (is_file(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();
$setting = select('setting', '*');
$textbotlang = languagechange(__DIR__ . '/../text.json');
$keyboard = null;
$keyboardextendfnished = null;
$Confirm_pay = null;
$from_id = null;
$message_id = null;
$datatextbot = [];

$result = rx_payment_reconcile_once($pdo, 25, 900);
if (PHP_SAPI === 'cli') {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
