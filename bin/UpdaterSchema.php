<?php
declare(strict_types=1);

/**
 * Minimal updater bootstrap.
 *
 * This is intentionally limited to the two updater tables. It lets an older
 * installation open the update centre directly; the normal signed migration
 * runner still applies and records all project migrations during installation.
 */
final class RedFoxUpdaterSchema
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `update_sources` (
            `id` TINYINT UNSIGNED PRIMARY KEY,
            `github_repo` VARCHAR(191) NULL,
            `asset_pattern` VARCHAR(191) NOT NULL DEFAULT 'RedFox*.zip',
            `allow_unsigned_local` TINYINT(1) NOT NULL DEFAULT 0,
            `auto_check` TINYINT(1) NOT NULL DEFAULT 1,
            `last_check_at` BIGINT UNSIGNED NULL,
            `last_version` VARCHAR(100) NULL,
            `last_url` VARCHAR(1000) NULL,
            `last_error` VARCHAR(1000) NULL,
            `last_source` VARCHAR(30) NULL,
            `last_file` VARCHAR(500) NULL,
            `updated_at` BIGINT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO `update_sources` (`id`) VALUES (1)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `update_jobs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `job_id` CHAR(32) NOT NULL,
            `version` VARCHAR(100) NOT NULL,
            `source` VARCHAR(30) NOT NULL,
            `signed_package` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(30) NOT NULL,
            `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `progress_stage` VARCHAR(100) NULL,
            `processed_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_action_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `changed_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `added_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `skipped_files` INT UNSIGNED NOT NULL DEFAULT 0,
            `file_backup_path` VARCHAR(1000) NULL,
            `database_backup_path` VARCHAR(1000) NULL,
            `error_message` VARCHAR(2000) NULL,
            `actor` VARCHAR(191) NULL,
            `created_at` BIGINT UNSIGNED NOT NULL,
            `started_at` BIGINT UNSIGNED NULL,
            `finished_at` BIGINT UNSIGNED NULL,
            UNIQUE KEY `uq_uj_job` (`job_id`),
            KEY `idx_uj_status_time` (`status`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::addMissingColumns($pdo, 'update_sources', [
            'last_source' => "VARCHAR(30) NULL",
            'last_file' => "VARCHAR(500) NULL",
            'github_token' => "VARCHAR(500) NULL",
        ]);
        self::addMissingColumns($pdo, 'update_jobs', [
            'progress_percent' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
            'progress_stage' => "VARCHAR(100) NULL",
            'processed_files' => "INT UNSIGNED NOT NULL DEFAULT 0",
            'total_action_files' => "INT UNSIGNED NOT NULL DEFAULT 0",
        ]);
    }

    private static function addMissingColumns(PDO $pdo, string $table, array $columns): void
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        foreach ($columns as $column => $definition) {
            $stmt->execute([$table, $column]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $stmt->closeCursor();
            if (!$exists) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
}
