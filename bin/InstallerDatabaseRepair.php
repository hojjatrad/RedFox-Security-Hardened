<?php
declare(strict_types=1);

/**
 * Non-destructive compatibility repair used before the legacy table bootstrap.
 * Old Red Fox databases sometimes declare short identifier values as
 * VARCHAR(500/600/2000). Rebuilding a primary/index under utf8mb4 can then exceed
 * InnoDB's 3072-byte key limit. We only shrink declarations after proving that
 * every stored value fits, so existing data is never truncated.
 */
final class RedFoxInstallerDatabaseRepair
{
    public static function repair(PDO $pdo): array
    {
        $changes = [];
        $targets = [
            ['user', 'id', 191],
            ['admin', 'id_admin', 191],
            ['invoice', 'id_invoice', 191],
            ['textbot', 'id_text', 191],
            ['PaySetting', 'NamePay', 191],
            ['shopSetting', 'Namevalue', 191],
            ['card_number', 'cardnumber', 191],
            ['Requestagent', 'id', 191],
            ['topicid', 'report', 191],
        ];

        foreach ($targets as [$table, $column, $safeLength]) {
            if (!self::columnExists($pdo, $table, $column)) continue;
            $meta = self::columnMeta($pdo, $table, $column);
            $declared = (int)($meta['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            if ($declared <= $safeLength || $declared === 0) continue;

            $sql = 'SELECT MAX(CHAR_LENGTH(`' . $column . '`)) FROM `' . $table . '`';
            $stmt = $pdo->query($sql);
            $used = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($used > $safeLength) {
                throw new RuntimeException("ستون {$table}.{$column} مقدار {$used} کاراکتری دارد و بدون حذف اطلاعات قابل کاهش به {$safeLength} نیست.");
            }

            $nullable = (($meta['IS_NULLABLE'] ?? 'NO') === 'YES') ? ' NULL' : ' NOT NULL';
            $pdo->exec('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` VARCHAR(' . $safeLength . ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' . $nullable);
            $changes[] = $table . '.' . $column . ': ' . $declared . '→' . $safeLength;
        }

        return $changes;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        $exists = (int)$stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        return $exists;
    }

    private static function columnMeta(PDO $pdo, string $table, string $column): array
    {
        $stmt = $pdo->prepare('SELECT CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stmt->closeCursor();
        return $row;
    }

}
