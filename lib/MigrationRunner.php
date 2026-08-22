<?php
declare(strict_types=1);
require_once __DIR__ . '/SqlTools.php';

final class RedFoxMigrationRunner
{
    public static function run(PDO $pdo, string $dir, bool $dry = false, ?callable $progress = null): array
    {
        $result = [];
        $emit = static function (int $percent, string $stage, string $version = '', int $index = 0, int $total = 0) use ($progress): void {
            if ($progress) {
                try { $progress($percent, $stage, $version, $index, $total); } catch (Throwable $e) {}
            }
        };

        $lockStmt = $pdo->query("SELECT GET_LOCK('redfox_schema_migrations',30)");
        $lock = (int)$lockStmt->fetchColumn();
        $lockStmt->closeCursor();
        if ($lock !== 1) throw new RuntimeException('قفل Migration در دسترس نیست.');

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(191) PRIMARY KEY,checksum CHAR(64) NOT NULL,executed_at BIGINT UNSIGNED NOT NULL,execution_ms INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
            sort($files, SORT_NATURAL);
            $total = max(1, count($files));
            $index = 0;
            $emit(0, 'بررسی Migrationها', '', 0, $total);

            foreach ($files as $file) {
                $index++;
                $version = basename($file);
                $emit((int)floor(($index - 1) / $total * 100), 'بررسی ' . $version, $version, $index, $total);
                $body = (string)file_get_contents($file);
                $checksum = hash('sha256', $body);

                $query = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');
                $query->execute([$version]);
                $old = $query->fetchColumn();
                $query->closeCursor();
                if ($old !== false) {
                    // Historical migrations changed only for safe index prefixes/charsets.
                    $compatibleLegacyChecksums = [
                        '002_reseller_portal.sql' => ['25ba3c3c340a192c35bc36258cd5e43ce4ac4c2900215da7ff80bfb5a351b264','9167a170438c6f06e7dcfcfc7e9d435eb97b28bb06ce12f8b1ee1627302dbfab'],
                        '003_operations_and_migrations.sql' => ['88ab9865556beb367146b073ee65ece20efec58851b8bcd695730e61a9d52092'],
                        '005_production_operations.sql' => ['7a9b07b93e19f479b2d7d349bc81a0414c01018a7e710655ce24eeb90b5b83ef'],
                        '006_integrity_indexes.sql' => ['f2e41935306f8424c80639cdd5ac7af65e36ab9160ff8d4cae3e250389e5a342','e33aa59c824246f2537e42b3257e749d5bd14607923c9b6a1d11fc5182749bf9'],
                        '010_final_features_no_runtime_ddl.sql' => ['ee47b4b466af6e683c9c00f0f7e6abf9712adb393fc853399df9b6aa8b08b951'],
                    ];
                    $legacyAccepted = isset($compatibleLegacyChecksums[$version])
                        && in_array((string)$old, $compatibleLegacyChecksums[$version], true);
                    if (!hash_equals((string)$old, $checksum) && !$legacyAccepted) {
                        throw new RuntimeException("Migration checksum mismatch: {$version}");
                    }
                    $result[] = [$version, 'skipped'];
                    $emit((int)floor($index / $total * 100), 'قبلاً اجرا شده: ' . $version, $version, $index, $total);
                    continue;
                }
                if ($dry) {
                    $result[] = [$version, 'planned'];
                    $emit((int)floor($index / $total * 100), 'برنامه‌ریزی: ' . $version, $version, $index, $total);
                    continue;
                }

                $emit((int)floor(($index - 1) / $total * 100), 'در حال اجرای ' . $version, $version, $index, $total);
                $started = microtime(true);
                foreach (rx_sql_split($body) as $sql) {
                    // EXECUTE statements in idempotent migrations may resolve to
                    // SELECT 1. On shared hosts with unbuffered PDO queries that
                    // result MUST be consumed before the next statement, or
                    // MySQL raises error 2014. Always drain and close every
                    // statement, including all additional rowsets.
                    $statement = null;
                    try {
                        $statement = $pdo->prepare($sql);
                        $statement->execute();
                        do {
                            if ($statement->columnCount() > 0) {
                                $statement->fetchAll(PDO::FETCH_NUM);
                            }
                            try {
                                $hasMoreRows = $statement->nextRowset();
                            } catch (Throwable $rowsetError) {
                                $hasMoreRows = false;
                            }
                        } while ($hasMoreRows);
                    } catch (Throwable $sqlError) {
                        $preview = preg_replace('/\s+/', ' ', trim($sql));
                        $preview = function_exists('mb_substr') ? mb_substr($preview, 0, 180) : substr($preview, 0, 180);
                        throw new RuntimeException('خطا در ' . $version . ' هنگام اجرای «' . $preview . '»: ' . redfox_exception_fingerprint($sqlError), (int)$sqlError->getCode(), $sqlError);
                    } finally {
                        if ($statement instanceof PDOStatement) {
                            try { $statement->closeCursor(); } catch (Throwable $closeError) {}
                        }
                    }
                }
                $milliseconds = (int)round((microtime(true) - $started) * 1000);
                $record = $pdo->prepare('INSERT INTO schema_migrations(version,checksum,executed_at,execution_ms) VALUES(?,?,?,?)');
                $record->execute([$version, $checksum, time(), $milliseconds]);
                $record->closeCursor();
                $result[] = [$version, 'applied'];
                $emit((int)floor($index / $total * 100), 'اجرا شد: ' . $version, $version, $index, $total);
            }
            $emit(100, 'تمام Migrationها تکمیل شدند', '', count($files), count($files));
        } finally {
            try {
                $release = $pdo->query("SELECT RELEASE_LOCK('redfox_schema_migrations')");
                if ($release) { $release->fetchColumn(); $release->closeCursor(); }
            } catch (Throwable $e) {}
        }
        return $result;
    }
}
