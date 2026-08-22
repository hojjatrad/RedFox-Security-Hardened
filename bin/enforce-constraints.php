<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(404);
require_once dirname(__DIR__) . '/config.php';

$apply = in_array('--apply', $argv, true);
$failed = false;

// id_order is VARCHAR(2000), so a direct utf8mb4 UNIQUE index is not portable.
// uq_payment_order_id therefore covers a deterministic full-value SHA-256 column;
// unlike a prefix index, distinct long IDs cannot silently collide by prefix.
$duplicateQuery = $pdo->query(
    "SELECT id_order,COUNT(*) c FROM Payment_report WHERE id_order IS NOT NULL AND id_order<>'' GROUP BY id_order HAVING c>1 LIMIT 20"
);
$paymentDuplicates = $duplicateQuery->fetchAll(PDO::FETCH_ASSOC);
if ($paymentDuplicates) {
    echo 'BLOCK Payment_report.id_order duplicate values=' . count($paymentDuplicates) . "\n";
    $failed = true;
} else {
    $indexQuery = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Payment_report' AND INDEX_NAME='uq_payment_order_id' AND NON_UNIQUE=0"
    );
    if ((int)$indexQuery->fetchColumn() > 0) {
        echo "OK uq_payment_order_id already exists\n";
    } else {
        echo ($apply ? 'APPLY ' : 'PLAN ') . "uq_payment_order_id (full SHA-256)\n";
        if ($apply) {
            $columnQuery = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Payment_report' AND COLUMN_NAME='id_order_hash'"
            );
            if ((int)$columnQuery->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE Payment_report ADD COLUMN id_order_hash BINARY(32) GENERATED ALWAYS AS (UNHEX(SHA2(id_order, 256))) STORED AFTER id_order'
                );
            }
            $pdo->exec('ALTER TABLE Payment_report ADD UNIQUE KEY uq_payment_order_id (id_order_hash)');
        }
    }
}

$targets = [
    ['table' => 'product', 'column' => 'code_product', 'index' => 'uq_product_code'],
    ['table' => 'botsaz', 'column' => 'bot_token', 'index' => 'uq_botsaz_token'],
];
foreach ($targets as $target) {
    $table = $target['table'];
    $column = $target['column'];
    $index = $target['index'];

    $duplicateQuery = $pdo->query(
        "SELECT `$column`,COUNT(*) c FROM `$table` WHERE `$column` IS NOT NULL AND `$column`<>'' GROUP BY `$column` HAVING c>1 LIMIT 20"
    );
    $duplicates = $duplicateQuery->fetchAll(PDO::FETCH_ASSOC);
    if ($duplicates) {
        echo "BLOCK $table.$column duplicate values=" . count($duplicates) . "\n";
        $failed = true;
        continue;
    }

    $indexQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
    );
    $indexQuery->execute([$table, $index]);
    if ((int)$indexQuery->fetchColumn() > 0) {
        echo "OK $index already exists\n";
        continue;
    }

    echo ($apply ? 'APPLY ' : 'PLAN ') . $index . "\n";
    if ($apply) $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY `$index` (`$column`)");
}

if ($failed) exit(2);
if (!$apply) echo "Dry run only; re-run with --apply after backup.\n";
