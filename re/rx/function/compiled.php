<?php
/** Generated from manifest.php at release build time. Do not edit directly. */

/* ---- bootstrap.php ---- */
if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', REFACTORED_LEGACY_ROOT);
}


$composerAutoload = APP_ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
    unset($composerAutoload);
} else {
    error_log('Composer autoloader not found. Optional dependencies may be unavailable.');
    unset($composerAutoload);
}
require_once APP_ROOT_PATH . '/config.php';

ini_set('error_log', APP_ROOT_PATH . '/error_log');

function getDatabaseConnection()
{
    static $cachedPdo = null;

    if ($cachedPdo instanceof PDO) {
        return $cachedPdo;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $cachedPdo = $GLOBALS['pdo'];
        return $cachedPdo;
    }

    $dsn = $GLOBALS['dsn'] ?? null;
    $username = $GLOBALS['usernamedb'] ?? null;
    $password = $GLOBALS['passworddb'] ?? null;
    $options = $GLOBALS['options'] ?? [];

    if (!is_string($dsn) || trim($dsn) === '') {
        // config.php already logs once-per-hour about empty credentials when the
        // installer is gone; logging here too just duplicates that signal. We
        // suppress this branch unless DB creds are filled (meaning the DSN
        // string itself was somehow corrupted at runtime — a real anomaly).
        $rxDbname = $GLOBALS['dbname'] ?? '';
        $rxUser   = $GLOBALS['usernamedb'] ?? '';
        if (is_string($rxDbname) && $rxDbname !== '' && is_string($rxUser) && $rxUser !== '') {
            $rxDsnMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_dsn_missing.flag';
            if (!is_file($rxDsnMarker) || (time() - (int) @filemtime($rxDsnMarker)) > 3600) {
                error_log('getDatabaseConnection: DSN is not configured (creds present — check config.php).');
                @touch($rxDsnMarker);
            }
        }
        return null;
    }

    try {
        $newPdo = new PDO($dsn, (string) $username, (string) $password, is_array($options) ? $options : []);
        $GLOBALS['pdo'] = $newPdo;
        $cachedPdo = $newPdo;
        return $cachedPdo;
    } catch (PDOException $e) {
        error_log('getDatabaseConnection: Unable to create PDO instance. ' . redfox_exception_fingerprint($e));
        return null;
    }
}

if (!defined('TRONADO_API_CONFIGURATION')) {
    $tronadoApiConfiguration = [
        'base_url' => 'https://bot.tronado.cloud',
        'order_token_path' => '/Order/GetOrderToken',
        'versions' => [
            'api/v1',
            'api/v2',
            'api/v3',
            'api',
            null,
        ],
    ];

    define('TRONADO_API_CONFIGURATION', $tronadoApiConfiguration);
    unset($tronadoApiConfiguration);
}

if (!defined('TRONADO_ORDER_TOKEN_ENDPOINTS')) {
    $tronadoConfig = TRONADO_API_CONFIGURATION;
    $baseUrl = rtrim((string) ($tronadoConfig['base_url'] ?? ''), '/');
    $path = '/' . ltrim((string) ($tronadoConfig['order_token_path'] ?? ''), '/');
    $versions = is_array($tronadoConfig['versions'] ?? null) ? $tronadoConfig['versions'] : [];

    $computedEndpoints = [];
    foreach ($versions as $version) {
        if ($baseUrl === '') {
            continue;
        }

        $versionSegment = $version !== null ? '/' . trim((string) $version, '/') : '';
        $computedEndpoints[] = $baseUrl . $versionSegment . $path;
    }

    if (!in_array(null, $versions, true)) {
        $computedEndpoints[] = $baseUrl . $path;
    }

    $computedEndpoints = array_values(array_unique(array_filter($computedEndpoints)));
    define('TRONADO_ORDER_TOKEN_ENDPOINTS', $computedEndpoints);
    unset($computedEndpoints, $baseUrl, $path, $versions, $tronadoConfig);
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


function isShellExecAvailable()
{
    static $isAvailable;

    if ($isAvailable !== null) {
        return $isAvailable;
    }

    if (!function_exists('shell_exec')) {
        $isAvailable = false;
        return $isAvailable;
    }

    $disabledFunctions = ini_get('disable_functions');
    if (!empty($disabledFunctions) && stripos($disabledFunctions, 'shell_exec') !== false) {
        $isAvailable = false;
        return $isAvailable;
    }

    $isAvailable = true;
    return $isAvailable;
}

if (!function_exists('safe_divide')) {


    function safe_divide($numerator, $denominator, $fallback = 0)
    {
        if (!is_numeric($numerator) || !is_numeric($denominator)) {
            return $fallback;
        }

        $denominator = (float) $denominator;
        if ($denominator == 0.0) {
            return $fallback;
        }

        $result = (float) $numerator / $denominator;

        if (!is_finite($result)) {
            return $fallback;
        }

        return $result;
    }
}


function generateReferralCode($length = 12)
{
    $length = max(1, (int) $length);
    $bytes = (int) ceil($length / 2);

    if (function_exists('random_bytes')) {
        try {
            $code = bin2hex(random_bytes($bytes));
            return substr($code, 0, $length);
        } catch (Exception $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . redfox_exception_fingerprint($exception));
        } catch (Error $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . redfox_exception_fingerprint($exception));
        }
    }

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $code = '';

    for ($i = 0; $i < $length; ++$i) {
        if (function_exists('random_int')) {
            try {
                $index = random_int(0, $maxIndex);
            } catch (Exception $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . redfox_exception_fingerprint($exception));
                $index = mt_rand(0, $maxIndex);
            } catch (Error $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . redfox_exception_fingerprint($exception));
                $index = mt_rand(0, $maxIndex);
            }
        } else {
            $index = mt_rand(0, $maxIndex);
        }

        $code .= $characters[$index];
    }

    return $code;
}


function ensureUserInvitationCode($userId, $currentCode = null, $length = 12)
{
    if (!is_scalar($userId) || (string) $userId === '') {
        return null;
    }

    $currentCode = is_string($currentCode) ? trim($currentCode) : '';
    if ($currentCode !== '') {
        return $currentCode;
    }

    $newCode = generateReferralCode($length);
    update('user', 'codeInvitation', $newCode, 'id', (string) $userId);

    return $newCode;
}

if (!function_exists('applyConnectionPlaceholders')) {


    function applyConnectionPlaceholders($template, $subscriptionLink, $configList)
    {
        $trimmedSubscription = trim((string) $subscriptionLink);
        $trimmedConfigList = trim((string) $configList);

        $connectionSections = [];
        $configSection = '';
        $linksSection = '';

        if ($trimmedSubscription !== '') {
            $configSection = "🔗 لینک اتصال:\n\n<code>{$trimmedSubscription}</code>";
            $connectionSections['config'] = $configSection;
        }

        if ($trimmedConfigList !== '') {
            $linksSection = "🔐 کانفیگ اشتراک :\n\n<code>{$trimmedConfigList}</code>";
            $connectionSections['links'] = $linksSection;
        }

        $connectionLinksBlock = implode("\n\n", array_values($connectionSections));
        if ($connectionLinksBlock !== '') {
            $connectionLinksBlock .= "\n";
        }

        $hasConnectionLinksPlaceholder = strpos($template, '{connection_links}') !== false;
        $hasConfigPlaceholder = strpos($template, '{config}') !== false;
        $hasLinksPlaceholder = strpos($template, '{links}') !== false;

        $placeholderLabels = [
            '{config}' => [
                '🔗 لینک اتصال:',
                '🔗 لینک اتصال :',
                'لینک اتصال:',
                'لینک اتصال :',
            ],
            '{links}' => [
                '🔐 کانفیگ اشتراک:',
                '🔐 کانفیگ اشتراک :',
                'لینک اشتراک:',
                'لینک اشتراک :',
            ],
        ];

        $replacePlaceholder = function ($templateValue, $placeholder, $replacement) use ($placeholderLabels) {
            $wrappedPlaceholder = "<code>{$placeholder}</code>";
            $labels = $placeholderLabels[$placeholder] ?? [];
            $placeholderPattern = '(?:' . preg_quote($placeholder, '/') . '|' . preg_quote($wrappedPlaceholder, '/') . ')';

            foreach ($labels as $label) {
                $labelPattern = preg_quote($label, '/');
                $pattern = '/(^|\R)[^\S\r\n]*' . $labelPattern . '[^\S\r\n]*(?:\r?\n)?[^\S\r\n]*' . $placeholderPattern . '/u';
                $updatedTemplate = preg_replace($pattern, '$1' . $replacement, $templateValue, 1, $count);
                if ($count > 0) {
                    return $updatedTemplate;
                }
            }

            if (strpos($templateValue, $wrappedPlaceholder) !== false) {
                return str_replace($wrappedPlaceholder, $replacement, $templateValue);
            }

            return str_replace($placeholder, $replacement, $templateValue);
        };

        if ($hasConnectionLinksPlaceholder) {
            $template = str_replace('{connection_links}', $connectionLinksBlock, $template);

            if ($hasConfigPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }
                $template = $replacePlaceholder($template, '{config}', $configReplacement);
            }

            if ($hasLinksPlaceholder) {
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            }
        } elseif ($hasConfigPlaceholder || $hasLinksPlaceholder) {
            if ($hasConfigPlaceholder && $hasLinksPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }

                $template = $replacePlaceholder($template, '{config}', $configReplacement);
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            } elseif ($hasConfigPlaceholder) {
                $template = $replacePlaceholder($template, '{config}', $connectionLinksBlock);
            } else {
                $template = $replacePlaceholder($template, '{links}', $connectionLinksBlock);
            }
        }

        if (strpos($template, '{links2}') !== false) {
            $template = str_replace('{links2}', $trimmedSubscription, $template);
        }

        return $template;
    }
}

function getCrontabBinary()
{
    static $resolvedPath;

    if ($resolvedPath !== null) {
        return $resolvedPath ?: null;
    }

    $candidateDirectories = [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/sbin',
        '/sbin',
    ];

    $environmentPath = rx_process_environment_value('PATH');
    if (is_string($environmentPath) && $environmentPath !== '') {
        foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
            $pathDirectory = trim($pathDirectory);
            if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                $candidateDirectories[] = $pathDirectory;
            }
        }
    }

    foreach ($candidateDirectories as $directory) {
        $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
        if (@is_file($executablePath) && @is_executable($executablePath)) {
            $resolvedPath = $executablePath;
            return $resolvedPath;
        }
    }

    if (isShellExecAvailable()) {
        $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
        if (is_string($whichOutput)) {
            $whichOutput = trim($whichOutput);
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                $resolvedPath = $whichOutput;
                return $resolvedPath;
            }
        }
    }

    $resolvedPath = '';
    error_log('Unable to locate the crontab executable on this system.');

    return null;
}

function runShellCommand($command)
{
    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to run command: ' . $command);
        return null;
    }

    if ((string)rx_process_environment_value('PATH') === '') {
        if (function_exists('putenv')) putenv('PATH=/usr/local/bin:/usr/bin:/bin');
    }

    return shell_exec($command);
}

function deleteDirectory($directory)
{
    if (!file_exists($directory)) {
        return true;
    }

    if (is_link($directory) || !is_dir($directory)) {
        return @unlink($directory);
    }

    $items = scandir($directory);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_link($path)) {
            if (!@unlink($path)) return false;
        } elseif (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }

    return @rmdir($directory);
}

function ensureTableUtf8mb4($table)
{
    global $pdo;if(!($pdo instanceof PDO))return false;$q=$pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([(string)$table]);return stripos((string)$q->fetchColumn(),'utf8mb4')===0;
}
function ensureCardNumberTableSupportsUnicode()
{
    global $pdo,$connect;if($connect instanceof mysqli)@$connect->set_charset('utf8mb4');if($pdo instanceof PDO)rx_require_schema($pdo,['card_number']);
}

function normaliseUpdateValue($value)
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $value;
}

function copyDirectoryContents($source, $destination)
{
    if (!is_dir($source) || is_link($source)) {
        return false;
    }

    if ((!is_dir($destination) && !mkdir($destination, 0750, true)) || is_link($destination)) {
        return false;
    }
    @chmod($destination, 0750);

    $items = scandir($source);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;
        if (is_link($sourcePath)) return false;

        if (is_dir($sourcePath)) {
            if (!copyDirectoryContents($sourcePath, $destinationPath)) {
                return false;
            }
        } else {
            if (!is_file($sourcePath) || !@copy($sourcePath, $destinationPath)) {
                return false;
            }
            @chmod($destinationPath, $item === '.htaccess' ? 0644 : 0640);
        }
    }

    return true;
}


function step($step, $from_id)
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);
    clearSelectCache('user');
}
function determineColumnTypeFromValue($value)
{
    if (is_bool($value)) {
        return 'TINYINT(1)';
    }

    if (is_int($value)) {
        return 'INT(11)';
    }

    if (is_float($value)) {
        return 'DOUBLE';
    }

    if ($value === null) {
        return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    if (is_string($value)) {
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($value, 'UTF-8');
        } else {
            $length = strlen($value);
        }

        if ($length <= 191) {
            return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        if ($length <= 500) {
            return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
}
function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
{
    global $pdo;

    static $checkedColumns = [];

    $cacheKey = $tableName . '.' . $fieldName;
    if (isset($checkedColumns[$cacheKey])) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$tableName, $fieldName]);
        if ((int) $stmt->fetchColumn() > 0) {
            $checkedColumns[$cacheKey] = true;
            return;
        }

        $datatype = determineColumnTypeFromValue($valueSample);

        $defaultValue = null;
        if (is_bool($valueSample)) {
            $defaultValue = $valueSample ? '1' : '0';
        } elseif (is_scalar($valueSample) && $valueSample !== null) {
            $defaultValue = (string) $valueSample;
        }

        addFieldToTable($tableName, $fieldName, $defaultValue, $datatype);
        $checkedColumns[$cacheKey] = true;
    } catch (PDOException $e) {
        error_log('Failed to ensure column exists: ' . redfox_exception_fingerprint($e));
        $checkedColumns[$cacheKey] = true;
    }
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null)
{
    global $pdo, $user;

    $valueToStore = normaliseUpdateValue($newValue);
    if ((string)$table === 'marzban_panel' && in_array((string)$field, ['password_panel','api_key','xui_api_token','secret_code'], true) && function_exists('rx_secret_encrypt')) {
        $valueToStore = rx_secret_encrypt((string)$valueToStore);
    }
    $whereValueToStore = $whereField !== null ? normaliseUpdateValue($whereValue) : null;

    ensureColumnExistsForUpdate($table, $field, $valueToStore);
    if ($whereField !== null) {
        ensureColumnExistsForUpdate($table, $whereField, $whereValueToStore);
    }

    $executeUpdate = function ($value) use ($pdo, $table, $field, $whereField, $whereValueToStore) {
        if ($whereField !== null) {
            $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
            $stmt->execute([$value, $whereValueToStore]);
        } else {
            $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
            $stmt->execute([$value]);
        }

        return isset($stmt) ? $stmt->rowCount() : 0;
    };

    $affectedRows = 0;

    try {
        $affectedRows = $executeUpdate($valueToStore);
    } catch (PDOException $e) {
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1366) {
            $tableConverted = ensureTableUtf8mb4($table);
            if ($tableConverted) {
                try {
                    $affectedRows = $executeUpdate($valueToStore);
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . redfox_exception_fingerprint($retryException));
                    throw $retryException;
                }
            } else {
                $fallbackValue = is_string($valueToStore) ? @iconv('UTF-8', 'UTF-8//IGNORE', $valueToStore) : $valueToStore;
                if ($fallbackValue === false) {
                    $fallbackValue = '';
                }
                $affectedRows = $executeUpdate($fallbackValue);
            }
        } else {
            throw $e;
        }
    }

    if ($whereField !== null && $affectedRows === 0) {
        if ($whereValueToStore === null) {
            $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField IS NULL LIMIT 1");
            $existsStmt->execute();
        } else {
            $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField = ? LIMIT 1");
            $existsStmt->execute([$whereValueToStore]);
        }

        $rowExists = $existsStmt->fetchColumn();

        if ($rowExists === false) {
            $columns = [$field];
            $values = [$valueToStore];

            if ($field !== $whereField) {
                $columns[] = $whereField;
                $values[] = $whereValueToStore;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnList = implode(', ', array_map(function ($column) {
                return "`$column`";
            }, $columns));

            try {
                $insertStmt = $pdo->prepare("INSERT INTO $table ($columnList) VALUES ($placeholders)");
                $insertStmt->execute($values);
            } catch (PDOException $insertException) {
                error_log('Failed to insert missing row during update fallback: ' . redfox_exception_fingerprint($insertException));
            }
        }
    }


    if (defined('RX_UPDATE_LOG_ENABLED') && RX_UPDATE_LOG_ENABLED === true) {
        static $rx_update_log_skip = [
            'message_count'     => true,
            'last_message_time' => true,
            'step'              => true,
            'pagenumber'        => true,
            'Processing_value'  => true,
        ];
        if (!isset($rx_update_log_skip[$field])) {
            $date = date("Y-m-d H:i:s");
            if (!isset($user['step'])) {
                $user['step'] = '';
            }
            $logValue = is_scalar($valueToStore)
                ? $valueToStore
                : json_encode($valueToStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logLine = "\n{$table}_{$field}_{$logValue}_{$whereField}_{$whereValue}_{$user['step']}_$date";
            $logPath = defined('RX_UPDATE_LOG_PATH') ? RX_UPDATE_LOG_PATH : 'log.txt';
            @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
        }
    }


    if ($whereField !== null && $whereValueToStore !== null
        && function_exists('clearSelectCacheRow')) {
        clearSelectCacheRow($table, $whereField, $whereValueToStore);
    } else {
        clearSelectCache($table);
    }
}
function &getSelectCacheStore()
{
    static $store = [
        'results' => [],
        'tableIndex' => [],
        'rowIndex' => [],
    ];

    return $store;
}

function clearSelectCache($table = null)
{
    $store =& getSelectCacheStore();

    if ($table === null) {
        $store['results'] = [];
        $store['tableIndex'] = [];
        $store['rowIndex'] = [];
        return;
    }

    if (!isset($store['tableIndex'][$table])) {
        return;
    }

    foreach (array_keys($store['tableIndex'][$table]) as $cacheKey) {
        unset($store['results'][$cacheKey]);
    }

    unset($store['tableIndex'][$table]);


    if (isset($store['rowIndex']) && is_array($store['rowIndex'])) {
        foreach (array_keys($store['rowIndex']) as $rowKey) {
            if (strpos($rowKey, $table . '|') === 0) {
                unset($store['rowIndex'][$rowKey]);
            }
        }
    }
}

function select($table, $field, $whereField = null, $whereValue = null, $type = "select", $options = [])
{
    $pdo = getDatabaseConnection();

    if (!($pdo instanceof PDO)) {
        $rxDbMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_db_unavailable.flag';
        if (!is_file($rxDbMarker) || (time() - (int) @filemtime($rxDbMarker)) > 3600) {
            error_log('select: Database connection is unavailable.');
            @touch($rxDbMarker);
        }

        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
        }
    }

    $useCache = true;
    if (is_array($options) && array_key_exists('cache', $options)) {
        $useCache = (bool) $options['cache'];
    }

    $cacheKey = null;
    if ($useCache) {
        $cacheKey = hash('sha256', json_encode([
            $table,
            $field,
            $whereField,
            $whereValue,
            $type,
        ], JSON_UNESCAPED_UNICODE));

        $store =& getSelectCacheStore();
        if (isset($store['results'][$cacheKey])) {
            return $store['results'][$cacheKey];
        }
    }

    $query = "SELECT $field FROM $table";

    if ($whereField !== null) {
        $query .= " WHERE $whereField = :whereValue";
    }

    try {
        $stmt = $pdo->prepare($query);
        if ($whereField !== null) {
            $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_STR);
        }

        $stmt->execute();
        if ($type == "count") {
            $result = $stmt->rowCount();
        } elseif ($type == "FETCH_COLUMN") {
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($table === 'admin' && $field === 'id_admin') {
                global $adminnumber;
                if (!is_array($results)) {
                    $results = [];
                }

                $results = array_values(array_unique(array_filter($results, function ($value) {
                    return $value !== null && $value !== '';
                })));

                if (empty($results) && isset($adminnumber) && $adminnumber !== '') {
                    $results[] = (string) $adminnumber;
                }
            }
            $result = $results;
        } elseif ($type == "fetchAll") {
            $result = $stmt->fetchAll();
        } else {
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $fetched === false ? null : $fetched;
        }
    } catch (PDOException $e) {


        error_log('select() PDOException on table=' . $table . ': ' . redfox_exception_fingerprint($e));
        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
        }
    }

    if (function_exists('rx_secret_decrypt_db_result')) {
        $result = rx_secret_decrypt_db_result((string)$table, (string)$field, $result);
    }

    if ($useCache && $cacheKey !== null) {


        static $rx_select_cache_hot_tables = [
            'user'           => true,
            'invoice'        => true,
            'Payment_report' => true,
            'reagent_report' => true,
            'cron_runtime_state' => true,
        ];
        $skipCache = false;
        if (isset($rx_select_cache_hot_tables[$table])) {
            $skipCache = true;
        }
        if (!$skipCache && is_array($result) && count($result) > 1000) {
            $skipCache = true;
        }
        if (!$skipCache) {
            $store =& getSelectCacheStore();


            $rxCacheMaxEntries = 500;
            if (count($store['results']) >= $rxCacheMaxEntries) {
                $oldestKey = array_key_first($store['results']);
                if ($oldestKey !== null) {
                    unset($store['results'][$oldestKey]);
                    foreach ($store['tableIndex'] as $tIdx => &$keys) {
                        if (isset($keys[$oldestKey])) {
                            unset($keys[$oldestKey]);
                        }
                    }
                    unset($keys);
                }
            }
            $store['results'][$cacheKey] = $result;
            if (!isset($store['tableIndex'][$table])) {
                $store['tableIndex'][$table] = [];
            }
            $store['tableIndex'][$table][$cacheKey] = true;

            $rxRowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
            if (!isset($store['rowIndex'][$rxRowIdx])) {
                $store['rowIndex'][$rxRowIdx] = [];
            }
            $store['rowIndex'][$rxRowIdx][$cacheKey] = true;
        }
    }

    return $result;
}


if (!function_exists('clearSelectCacheRow')) {
    function clearSelectCacheRow($table, $whereField, $whereValue)
    {
        $store =& getSelectCacheStore();
        if (!isset($store['tableIndex'][$table])) {
            return;
        }


        $rowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
        if (isset($store['rowIndex'][$rowIdx]) && is_array($store['rowIndex'][$rowIdx])) {
            foreach (array_keys($store['rowIndex'][$rowIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$rowIdx]);
        }


        $allRowsIdx = $table . '||';
        if (isset($store['rowIndex'][$allRowsIdx]) && is_array($store['rowIndex'][$allRowsIdx])) {
            foreach (array_keys($store['rowIndex'][$allRowsIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$allRowsIdx]);
        }
    }
}

function getPaySettingValue($name, $default = null)
{
    $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if (!is_array($result) || !array_key_exists('ValuePay', $result)) {
        return $default;
    }

    return $result['ValuePay'];
}


function userExists($userId)
{
    static $existenceCache = [];

    if (is_array($userId) || is_object($userId)) {
        return false;
    }

    $normalizedId = trim((string) $userId);
    if ($normalizedId === '' || !ctype_digit($normalizedId)) {
        return false;
    }

    if (array_key_exists($normalizedId, $existenceCache)) {
        return $existenceCache[$normalizedId];
    }

    $pdo = getDatabaseConnection();
    if (!($pdo instanceof PDO)) {
        $rxDbMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_db_unavailable.flag';
        if (!is_file($rxDbMarker) || (time() - (int) @filemtime($rxDbMarker)) > 3600) {
            error_log('userExists: Database connection is unavailable.');
            @touch($rxDbMarker);
        }
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT 1 FROM user WHERE id = :user_id LIMIT 1');
        $stmt->bindParam(':user_id', $normalizedId, PDO::PARAM_STR);
        $stmt->execute();
        $exists = $stmt->fetchColumn() !== false;
    } catch (PDOException $exception) {
        error_log('userExists: ' . redfox_exception_fingerprint($exception));
        $exists = false;
    }

    $existenceCache[$normalizedId] = $exists;

    return $exists;
}

function formatPaymentReportNote($rawNote)
{
    if ($rawNote === null) {
        return '';
    }

    if (is_array($rawNote)) {
        return json_encode($rawNote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_scalar($rawNote)) {
        return '';
    }

    $rawNote = trim((string) $rawNote);
    if ($rawNote === '') {
        return '';
    }

    $decoded = json_decode($rawNote, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (($decoded['gateway'] ?? '') === 'zarinpay') {
            $lines = ['زرین‌پی'];
            $fieldMap = [
                'payment_id' => 'شناسه پرداخت',
                'reference_id' => 'شماره پیگیری',
                'authority' => 'کد اعتبار',
                'order_id' => 'کد سفارش',
                'code' => 'کد تأیید',
            ];

            foreach ($fieldMap as $key => $label) {
                $value = $decoded[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $lines[] = sprintf('%s: %s', $label, $value);
                }
            }

            if (!empty($decoded['amount'])) {
                $lines[] = 'مبلغ تراکنش (ریال): ' . number_format((int) $decoded['amount']);
            }

            if (!empty($decoded['card_pan'])) {
                $lines[] = 'کارت پرداخت‌کننده: ' . $decoded['card_pan'];
            }

            if (!empty($decoded['paid_at'])) {
                $lines[] = 'زمان پرداخت: ' . $decoded['paid_at'];
            }

            return implode("\n", array_filter($lines));
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $rawNote;
}
function generateUUID()
{
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
if (!function_exists('isShellExecAvailable')) {
    function isShellExecAvailable()
    {
        static $isAvailable = null;
        if ($isAvailable !== null) {
            return $isAvailable;
        }
        if (!function_exists('shell_exec')) {
            return $isAvailable = false;
        }
        $disabledFunctions = (string) ini_get('disable_functions');
        if ($disabledFunctions !== '' && preg_match('/(^|,)\s*shell_exec\s*(,|$)/i', $disabledFunctions)) {
            return $isAvailable = false;
        }
        return $isAvailable = true;
    }
}

if (!function_exists('runShellCommand')) {
    function runShellCommand($command)
    {
        if (!isShellExecAvailable()) {
            error_log('shell_exec is not available; unable to run command: ' . $command);
            return null;
        }
        if ((string)rx_process_environment_value('PATH') === '') {
            if (function_exists('putenv')) putenv('PATH=/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin');
        }
        return shell_exec($command);
    }
}

if (!function_exists('getCrontabBinary')) {
    function getCrontabBinary()
    {
        static $resolvedPath = null;
        if ($resolvedPath !== null) {
            return $resolvedPath ?: null;
        }
        $candidateDirectories = ['/usr/local/bin', '/usr/bin', '/bin', '/usr/sbin', '/sbin'];
        $environmentPath = rx_process_environment_value('PATH');
        if (is_string($environmentPath) && $environmentPath !== '') {
            foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
                $pathDirectory = trim($pathDirectory);
                if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                    $candidateDirectories[] = $pathDirectory;
                }
            }
        }
        foreach ($candidateDirectories as $directory) {
            $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
            if (@is_file($executablePath) && @is_executable($executablePath)) {
                return $resolvedPath = $executablePath;
            }
        }
        if (isShellExecAvailable()) {
            $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
            $whichOutput = is_string($whichOutput) ? trim($whichOutput) : '';
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                return $resolvedPath = $whichOutput;
            }
        }
        $resolvedPath = '';
        error_log('Unable to locate the crontab executable on this system.');
        return null;
    }
}
/* ---- database_helpers_1.php ---- */
if (!function_exists('safe_divide')) {
    function safe_divide($numerator, $denominator, $fallback = 0)
    {
        if (!is_numeric($numerator) || !is_numeric($denominator)) {
            return $fallback;
        }
        $denominator = (float) $denominator;
        if ($denominator == 0.0) {
            return $fallback;
        }
        $result = (float) $numerator / $denominator;
        return is_finite($result) ? $result : $fallback;
    }
}

if (!function_exists('clearSelectCache')) {
    function clearSelectCache($table = null)
    {
        if (!isset($GLOBALS['rx_select_cache']) || !is_array($GLOBALS['rx_select_cache'])) {
            $GLOBALS['rx_select_cache'] = [];
            return;
        }
        if ($table === null) {
            $GLOBALS['rx_select_cache'] = [];
            return;
        }
        foreach (array_keys($GLOBALS['rx_select_cache']) as $key) {
            if (strpos($key, (string) $table . '|') === 0) {
                unset($GLOBALS['rx_select_cache'][$key]);
            }
        }
    }
}

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection()
    {
        global $pdo, $servername, $username, $password, $dbname;
        if (isset($pdo) && $pdo instanceof PDO) {
            return $pdo;
        }
        if (!isset($servername, $username, $password, $dbname)) {
            return null;
        }
        try {
            $pdo = new PDO("mysql:host={$servername};dbname={$dbname};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            return $pdo;
        } catch (Throwable $e) {
            error_log('getDatabaseConnection: ' . redfox_exception_fingerprint($e));
            return null;
        }
    }
}

if (!function_exists('determineColumnTypeFromValue')) {
    function determineColumnTypeFromValue($value)
    {
        if (is_bool($value)) return 'TINYINT(1)';
        if (is_int($value)) return 'INT(11)';
        if (is_float($value)) return 'DOUBLE';
        if ($value === null) return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        if (is_string($value)) {
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length <= 191) return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            if ($length <= 500) return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

if (!function_exists('normaliseUpdateValue')) {
    function normaliseUpdateValue($value)
    {
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($value === null) return null;
        return (string) $value;
    }
}

if (!function_exists('ensureColumnExistsForUpdate')) {
    function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $tableName === null || $fieldName === null) return;
        static $checkedColumns = [];
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $fieldName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $fieldName);
        if ($tableName === '' || $fieldName === '') return;
        $cacheKey = $tableName . '.' . $fieldName;
        if (isset($checkedColumns[$cacheKey])) return;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute([$tableName, $fieldName]);
            if ((int) $stmt->fetchColumn() === 0 && function_exists('addFieldToTable')) {
                $defaultValue = null;
                if (is_bool($valueSample)) $defaultValue = $valueSample ? '1' : '0';
                elseif (is_scalar($valueSample) && $valueSample !== null) $defaultValue = (string) $valueSample;
                addFieldToTable($tableName, $fieldName, $defaultValue, determineColumnTypeFromValue($valueSample));
            }
        } catch (Throwable $e) {
            error_log('ensureColumnExistsForUpdate: ' . redfox_exception_fingerprint($e));
        }
        $checkedColumns[$cacheKey] = true;
    }
}

if (!function_exists('ensureTableUtf8mb4')) {
    function ensureTableUtf8mb4($tableName)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        if ($tableName === '') return false;
        try {
            $q=$pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$tableName]);$coll=(string)$q->fetchColumn();
            return stripos($coll,'utf8mb4')===0;
        } catch (Throwable $e) { return false; }
    }
}

if (!function_exists('ensureCardNumberTableSupportsUnicode')) {
    function ensureCardNumberTableSupportsUnicode()
    {
        return ensureTableUtf8mb4('card_number');
    }
}

if (!function_exists('update')) {
    function update($table, $field, $newValue, $whereField = null, $whereValue = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return 0;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        $field = preg_replace('/[^A-Za-z0-9_]/', '', (string) $field);
        if ($table === '' || $field === '') return 0;
        $valueToStore = normaliseUpdateValue($newValue);
        if ($table === 'marzban_panel' && in_array($field, ['password_panel','api_key','xui_api_token','secret_code'], true) && function_exists('rx_secret_encrypt')) {
            $valueToStore = rx_secret_encrypt((string)$valueToStore);
        }
        ensureColumnExistsForUpdate($table, $field, $valueToStore);
        $params = [$valueToStore];
        if ($whereField !== null) {
            $whereField = preg_replace('/[^A-Za-z0-9_]/', '', (string) $whereField);
            ensureColumnExistsForUpdate($table, $whereField, $whereValue);
            $sql = "UPDATE `{$table}` SET `{$field}` = ? WHERE `{$whereField}` = ?";
            $params[] = normaliseUpdateValue($whereValue);
        } else {
            $sql = "UPDATE `{$table}` SET `{$field}` = ?";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            clearSelectCache($table);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1366 && ensureTableUtf8mb4($table)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                clearSelectCache($table);
                return $stmt->rowCount();
            }
            throw $e;
        }
    }
}

if (!function_exists('select')) {
    function select($table, $field, $whereField = null, $whereValue = null, $type = 'select', $options = [])
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        if ($table === '') return false;
        $fieldSql = ($field === '*') ? '*' : '`' . str_replace('`', '', (string) $field) . '`';
        $type = strtolower((string) $type);
        $useCache = !isset($options['cache']) || $options['cache'] !== false;
        $cacheKey = $table . '|' . $fieldSql . '|' . (string) $whereField . '|' . serialize($whereValue) . '|' . $type;
        if ($useCache && isset($GLOBALS['rx_select_cache'][$cacheKey])) return $GLOBALS['rx_select_cache'][$cacheKey];
        $params = [];
        if ($type === 'count') {
            $sql = "SELECT COUNT(*) FROM `{$table}`";
        } else {
            $sql = "SELECT {$fieldSql} FROM `{$table}`";
        }
        if ($whereField !== null) {
            $whereField = preg_replace('/[^A-Za-z0-9_]/', '', (string) $whereField);
            $sql .= " WHERE `{$whereField}` = ?";
            $params[] = normaliseUpdateValue($whereValue);
        }
        if ($type !== 'count') {
            $sql .= ' LIMIT 1';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($type === 'count') {
            $result = (int) $stmt->fetchColumn();
        } else {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) $result = false;
            elseif ($field === '*' || strpos((string) $field, ',') !== false) $result = $row;
            else $result = $row[(string) $field] ?? $row;
        }
        if (function_exists('rx_secret_decrypt_db_result')) {
            $result = rx_secret_decrypt_db_result((string)$table, (string)$field, $result);
        }
        if ($useCache) $GLOBALS['rx_select_cache'][$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('step')) {
    function step($step, $from_id)
    {
        update('user', 'step', $step, 'id', $from_id);
    }
}

if (!function_exists('generateUUID')) {
    function generateUUID()
    {
        $data = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('generateReferralCode')) {
    function generateReferralCode($length = 12)
    {
        $length = max(1, (int) $length);
        try {
            return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
        } catch (Throwable $e) {
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) $code .= $chars[mt_rand(0, strlen($chars) - 1)];
            return $code;
        }
    }
}

if (!function_exists('ensureUserInvitationCode')) {
    function ensureUserInvitationCode($userId)
    {
        $user = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (is_array($user) && !empty($user['codeInvitation'])) return $user['codeInvitation'];
        $code = generateReferralCode(12);
        update('user', 'codeInvitation', $code, 'id', $userId);
        return $code;
    }
}

if (!function_exists('deleteDirectory')) {
    function deleteDirectory($directory)
    {
        if (!file_exists($directory) && !is_link($directory)) return true;
        if (is_link($directory) || !is_dir($directory)) return @unlink($directory);
        $items = scandir($directory);
        if ($items === false) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                if (!@unlink($path)) return false;
            } elseif (is_dir($path)) {
                if (!deleteDirectory($path)) return false;
            } elseif (!@unlink($path)) return false;
        }
        return @rmdir($directory);
    }
}

if (!function_exists('copyDirectoryContents')) {
    function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source) || is_link($source) || is_link($destination)) return false;
        if (!is_dir($destination) && !@mkdir($destination, 0750, true)) return false;
        @chmod($destination, 0750);
        $items = scandir($source);
        if ($items === false) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $src = $source . DIRECTORY_SEPARATOR . $item;
            $dst = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_link($src)) return false;
            if (is_dir($src)) {
                if (!copyDirectoryContents($src, $dst)) return false;
            } elseif (!is_file($src) || !@copy($src, $dst)) {
                return false;
            } else {
                @chmod($dst, $item === '.htaccess' ? 0644 : 0640);
            }
        }
        return true;
    }
}

if (!function_exists('deleteInvoiceFromList')) {
    function deleteInvoiceFromList($invoiceId, $userId = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $sql = 'DELETE FROM invoice WHERE id_invoice = :invoice_id';
        $params = [':invoice_id' => $invoiceId];
        if ($userId !== null) {
            $sql .= ' AND id_user = :user_id';
            $params[':user_id'] = $userId;
        }
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        clearSelectCache('invoice');
        return $result;
    }
}

if (!function_exists('formatPaymentReportNote')) {
    function formatPaymentReportNote($rawNote)
    {
        if ($rawNote === null || $rawNote === '') return '';
        $decoded = is_string($rawNote) ? json_decode($rawNote, true) : null;
        if (is_array($decoded)) {
            $parts = [];
            foreach ($decoded as $key => $value) {
                if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $parts[] = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ': ' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            return implode("\n", $parts);
        }
        return htmlspecialchars((string) $rawNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}


if (!function_exists('getCronJobDefinitions')) {
    function getCronJobDefinitions(): array
    {
        return [
            'statusday' => ['script' => 'statusday.php', 'admin_label' => 'کرون وضعیت روزانه', 'instruction' => '🕚 بررسی وضعیت روزانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'croncard' => ['script' => 'croncard.php', 'admin_label' => 'کرون کارت‌به‌کارت', 'instruction' => '💳 بررسی کارت‌به‌کارت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'notifications' => ['script' => 'NoticationsService.php', 'admin_label' => 'کرون اعلان‌ها', 'instruction' => '🔔 ارسال اعلان‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'admin_notifications' => ['script' => 'admin_notifications.php', 'admin_label' => 'همگام‌سازی اعلان‌های مدیریت', 'instruction' => '🔔 همگام‌سازی مرکز اعلان مدیریت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'payment_expire' => ['script' => 'payment_expire.php', 'admin_label' => 'کرون انقضای پرداخت', 'instruction' => '⏳ بررسی انقضای پرداخت‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'payment_reconcile' => ['script' => 'payment_reconcile.php', 'admin_label' => 'کرون تطبیق امن پرداخت', 'instruction' => '🧾 ادامه مراحل قطعی پرداخت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'sendmessage' => ['script' => 'sendmessage.php', 'admin_label' => 'کرون ارسال پیام', 'instruction' => '📨 ارسال پیام زمان‌بندی‌شده — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'plisio' => ['script' => 'plisio.php', 'admin_label' => 'کرون Plisio', 'instruction' => '💰 بررسی پرداخت Plisio — %s', 'default' => ['unit' => 'minute', 'value' => 3]],
            'activeconfig' => ['script' => 'activeconfig.php', 'admin_label' => 'کرون فعال‌سازی تنظیمات', 'instruction' => '✅ فعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'disableconfig' => ['script' => 'disableconfig.php', 'admin_label' => 'کرون غیرفعال‌سازی تنظیمات', 'instruction' => '⛔ غیرفعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'iranpay1' => ['script' => 'iranpay1.php', 'admin_label' => 'کرون ایران‌پی', 'instruction' => '🇮🇷 بررسی پرداخت ایران‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'backupbot' => ['script' => 'backupbot.php', 'admin_label' => 'کرون بکاپ', 'instruction' => '📦 بکاپ‌گیری — %s', 'default' => ['unit' => 'hour', 'value' => 5]],
            'gift' => ['script' => 'gift.php', 'admin_label' => 'کرون هدایا', 'instruction' => '🎁 ارسال هدایا — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'discount_expire' => ['script' => 'discount_expire.php', 'admin_label' => 'کرون انقضای تخفیف', 'instruction' => '🏷 بررسی انقضای تخفیف‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'lottery' => ['script' => 'lottery.php', 'admin_label' => 'قرعه‌کشی شبانه', 'instruction' => '🎁 قرعه‌کشی شبانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'expireagent' => ['script' => 'expireagent.php', 'admin_label' => 'کرون انقضای نمایندگان', 'instruction' => '👥 بررسی انقضای نمایندگان — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'on_hold' => ['script' => 'on_hold.php', 'admin_label' => 'کرون سرویس‌های معلق', 'instruction' => '⏸ بررسی سفارش‌های معلق — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'configtest' => ['script' => 'configtest.php', 'admin_label' => 'کرون تست تنظیمات', 'instruction' => '🧪 تست تنظیمات سیستم — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'uptime_node' => ['script' => 'uptime_node.php', 'admin_label' => 'کرون Uptime نود', 'instruction' => '🌐 بررسی Uptime نودها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'uptime_panel' => ['script' => 'uptime_panel.php', 'admin_label' => 'کرون Uptime پنل', 'instruction' => '🖥 بررسی Uptime پنل‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'cryptocheck' => ['script' => 'cryptocheck.php', 'admin_label' => 'کرون چک هش کریپتو', 'instruction' => '🪙 بررسی پرداخت‌های کریپتو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'nowpaymentcheck' => ['script' => 'nowpaymentcheck.php', 'admin_label' => 'کرون پولر NowPayments', 'instruction' => '💎 بررسی پرداخت‌های NowPayments — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'update_check' => ['script' => 'update_check.php', 'admin_label' => 'بررسی بروزرسانی GitHub', 'instruction' => '🔄 بررسی نسخه جدید — %s', 'default' => ['unit' => 'hour', 'value' => 1]],
        ];
    }
}

if (!function_exists('getDefaultCronSchedules')) {
    function getDefaultCronSchedules(): array
    {
        $defaults = [];
        foreach (getCronJobDefinitions() as $key => $definition) $defaults[$key] = $definition['default'];
        return $defaults;
    }
}

if (!function_exists('normalizeCronScheduleConfig')) {
    function normalizeCronScheduleConfig(array $config, array $default): array
    {
        $unit = strtolower((string) ($config['unit'] ?? $default['unit'] ?? 'minute'));
        if (!in_array($unit, ['minute', 'hour', 'day', 'disabled'], true)) $unit = $default['unit'] ?? 'minute';
        $value = (int) ($config['value'] ?? $default['value'] ?? 1);
        if ($unit === 'disabled') $value = 1;
        elseif ($value < 1) $value = (int) ($default['value'] ?? 1);
        return ['unit' => $unit, 'value' => max(1, $value)];
    }
}

if (!function_exists('ensureCronRuntimeStateTable')) {
    function ensureCronRuntimeStateTable(PDO $pdo): void { rx_require_schema($pdo,['cron_runtime_state']); }
}

if (!function_exists('loadCronRuntimeState')) {
    function loadCronRuntimeState(PDO $pdo): array
    {
        ensureCronRuntimeStateTable($pdo);
        $state = [];
        $stmt = $pdo->query('SELECT job_key, last_run FROM cron_runtime_state');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $state[(string) $row['job_key']] = (int) $row['last_run'];
        return $state;
    }
}

if (!function_exists('setCronJobLastRun')) {
    function setCronJobLastRun(PDO $pdo, string $jobKey, int $timestamp): void
    {
        if (trim($jobKey) === '') return;
        ensureCronRuntimeStateTable($pdo);
        $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run) VALUES (:job_key, :last_run) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)');
        $stmt->execute([':job_key' => $jobKey, ':last_run' => $timestamp]);
    }
}

if (!function_exists('loadCronSchedules')) {
    function loadCronSchedules(): array
    {
        $definitions = getCronJobDefinitions();
        $schedules = getDefaultCronSchedules();
        $pdo = getDatabaseConnection();
        if (!($pdo instanceof PDO)) return $schedules;
        try {
            ensureCronRuntimeStateTable($pdo);
            foreach ($definitions as $key => $definition) {
                $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
                $stmt = $pdo->prepare('INSERT IGNORE INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, 1)');
                $stmt->execute([':job_key' => $key, ':unit' => $default['unit'], ':value' => (int) $default['value']]);
            }
            $stmt = $pdo->query('SELECT job_key, unit, value, enabled FROM cron_runtime_state');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobKey = trim((string) ($row['job_key'] ?? ''));
                if ($jobKey === '' || !isset($definitions[$jobKey])) continue;
                $config = ['unit' => (string) $row['unit'], 'value' => (int) $row['value']];
                if (isset($row['enabled']) && (int) $row['enabled'] === 0) $config['unit'] = 'disabled';
                $schedules[$jobKey] = normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
            }
        } catch (Throwable $e) {
            error_log('loadCronSchedules: ' . redfox_exception_fingerprint($e));
        }
        return $schedules;
    }
}

if (!function_exists('updateCronSchedule')) {
    function updateCronSchedule(string $jobKey, array $config): bool
    {
        $definitions = getCronJobDefinitions();
        if (!isset($definitions[$jobKey])) return false;
        $pdo = getDatabaseConnection();
        if (!($pdo instanceof PDO)) return false;
        $normalized = normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
        $enabled = $normalized['unit'] === 'disabled' ? 0 : 1;
        try {
            ensureCronRuntimeStateTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE unit = VALUES(unit), value = VALUES(value), enabled = VALUES(enabled)');
            return $stmt->execute([':job_key' => $jobKey, ':unit' => $normalized['unit'], ':value' => $normalized['value'], ':enabled' => $enabled]);
        } catch (Throwable $e) {
            error_log('updateCronSchedule: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('describeCronSchedule')) {
    function describeCronSchedule(array $config): string
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return 'غیرفعال';
        $labels = ['minute' => 'دقیقه', 'hour' => 'ساعت', 'day' => 'روز'];
        return sprintf('هر %d %s', $value, $labels[$unit] ?? 'دقیقه');
    }
}

if (!function_exists('shouldRunCronJob')) {


    function shouldRunCronJob(array $config, $minuteOrJobKey = null, ?int $hour = null, ?int $dayOfYear = null): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;


        if (is_string($minuteOrJobKey) && $minuteOrJobKey !== '') {
            $jobKey = $minuteOrJobKey;
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            if (!($pdo instanceof PDO)) return true;

            try {
                $state = function_exists('loadCronRuntimeState') ? loadCronRuntimeState($pdo) : [];
            } catch (\Throwable $e) {
                $state = [];
            }
            $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
            $now = time();

            $intervalSeconds = match ($unit) {
                'minute' => $value * 60,
                'hour'   => $value * 3600,
                'day'    => $value * 86400,
                default  => $value * 60,
            };


            return ($now - $lastRun) >= ($intervalSeconds - 5);
        }


        $minute = (int) $minuteOrJobKey;
        $hour = (int) $hour;
        $dayOfYear = (int) $dayOfYear;
        if ($unit === 'minute') return $minute % $value === 0;
        if ($unit === 'hour')   return $minute === 0 && $hour % $value === 0;
        if ($unit === 'day')    return $minute === 0 && $hour === 0 && $dayOfYear % $value === 0;
        return false;
    }
}

if (!function_exists('shouldRunCronJobNow')) {


    function shouldRunCronJobNow(string $jobKey, array $config): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;

        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return true;

        try {
            $state = function_exists('loadCronRuntimeState') ? loadCronRuntimeState($pdo) : [];
        } catch (\Throwable $e) {
            $state = [];
        }

        $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
        $now = time();
        $intervalSeconds = match ($unit) {
            'minute' => $value * 60,
            'hour'   => $value * 3600,
            'day'    => $value * 86400,
            default  => $value * 60,
        };

        if (($now - $lastRun) < ($intervalSeconds - 5)) {
            return false;
        }


        try {
            if (function_exists('setCronJobLastRun')) {
                setCronJobLastRun($pdo, $jobKey, $now);
            }
        } catch (\Throwable $e) {

        }
        return true;
    }
}

if (!function_exists('buildCronScriptUrlByHost')) {
    function buildCronScriptUrlByHost(string $domainHost, string $script): string
    {
        $domainHost = preg_replace('#^https?://#i', '', trim($domainHost));
        return 'https://' . rtrim($domainHost, '/') . '/cronbot/' . ltrim($script, '/');
    }
}

if (!function_exists('buildCronInstructionDetails')) {
    function buildCronInstructionDetails(string $domainHost): string
    {
        $schedules = loadCronSchedules();
        $parts = [];
        foreach (getCronJobDefinitions() as $key => $definition) {
            $description = describeCronSchedule($schedules[$key] ?? $definition['default']);
            $title = sprintf($definition['instruction'], $description);
            $endpoint = buildCronScriptUrlByHost($domainHost, $definition['script']);
            $parts[] = "<b>{$title}</b>\n<code>curl " . htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
        }
        return implode("\n\n", $parts);
    }
}

if (!function_exists('buildCronJobsKeyboard')) {
    function buildCronJobsKeyboard(): string
    {
        $definitions = getCronJobDefinitions();
        $schedules = loadCronSchedules();
        $rows = [];
        foreach ($definitions as $key => $definition) {
            $schedule = $schedules[$key] ?? $definition['default'];
            $rows[] = [
                ['text' => '⚙️ تنظیمات', 'callback_data' => "cronjob_config-{$key}"],
                ['text' => describeCronSchedule($schedule), 'callback_data' => 'cronjob_display'],
                ['text' => $definition['admin_label'], 'callback_data' => 'cronjob_display'],
            ];
        }
        $rows[] = [['text' => '🔙 بازگشت به تنظیمات کرون', 'callback_data' => 'cronjobs_back_settings']];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}


if (!function_exists('buildXuiSingleBaseUrl')) {
    function buildXuiSingleBaseUrl($url, $dropLastSegment = false)
    {
        $url = trim((string) $url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) return rtrim($url, '/');
        $path = $parts['path'] ?? '';
        if ($dropLastSegment && $path !== '') $path = preg_replace('#/[^/]*$#', '', $path);
        $base = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($path, '/');
        return rtrim($base, '/');
    }
}

if (!function_exists('normalizeXuiSingleSubscriptionBaseUrl')) {
    function normalizeXuiSingleSubscriptionBaseUrl($url)
    {
        return buildXuiSingleBaseUrl($url, true);
    }
}

if (!function_exists('hasLikelyXuiSubscriptionId')) {
    function hasLikelyXuiSubscriptionId($url)
    {
        return (bool) preg_match('#/(sub|sub/|xui|proxy|link)/?[A-Za-z0-9_-]{8,}#i', (string) $url);
    }
}

if (!function_exists('applyConnectionPlaceholders')) {
    function applyConnectionPlaceholders($template, $subscription = '', $configs = [])
    {
        $links = is_array($configs) ? implode("\n", array_filter(array_map('strval', $configs))) : (string) $configs;
        return str_replace(['{sub}', '{subscription}', '{linksub}', '{links}', '{links2}'], [(string) $subscription, (string) $subscription, (string) $subscription, $links, trim((string) $subscription)], (string) $template);
    }
}
function tronratee(array $requiredKeys = [])
{
    $normalizedKeys = [];
    foreach ($requiredKeys as $key) {
        $normalized = strtoupper(trim((string) $key));
        if ($normalized === '') {
            continue;
        }
        $normalizedKeys[$normalized] = true;
    }

    if (empty($normalizedKeys)) {
        $normalizedKeys = ['TRX' => true, 'TON' => true, 'USD' => true];
    }

    $needsTrx = isset($normalizedKeys['TRX']);
    $needsTon = isset($normalizedKeys['TON']);
    $needsUsd = isset($normalizedKeys['USD']);

    $result = [];
    $missingKeys = [];

    if (!$needsTrx && !$needsTon && !$needsUsd) {
        return ['ok' => true, 'result' => $result];
    }

    $endpoint = 'https://swapwallet.app/api/v1/market/prices';
    $fetch = redfox_fetch_public_https($endpoint, 1048576, 5, ['Accept: application/json']);
    $response = !empty($fetch['ok']) ? (string)$fetch['body'] : false;

    if ($response === false) {
        error_log('Failed to fetch market prices from SwapWallet API');
        if ($needsTrx) $missingKeys[] = 'TRX';
        if ($needsTon) $missingKeys[] = 'Ton';
        if ($needsUsd) $missingKeys[] = 'USD';
        return ['ok' => empty($missingKeys), 'result' => $result];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? null) !== 'OK' || !isset($data['result']) || !is_array($data['result'])) {
        error_log('Invalid response received from SwapWallet API');
        if ($needsTrx) $missingKeys[] = 'TRX';
        if ($needsTon) $missingKeys[] = 'Ton';
        if ($needsUsd) $missingKeys[] = 'USD';
        return ['ok' => empty($missingKeys), 'result' => $result];
    }

    $prices = $data['result'];

    $getPair = static function (array $prices, string $base, string $quote) {
        $target = strtoupper($base . '/' . $quote);
        foreach ($prices as $k => $v) {
            if (strtoupper((string) $k) !== $target) {
                continue;
            }

            if (is_string($v)) {
                $v = preg_replace('/[^\d\.\-]/u', '', $v);
            }

            if (!is_numeric($v)) {
                return null;
            }

            $num = (float) $v;
            if ($num <= 0.0 || !is_finite($num)) {
                return null;
            }

            return $num;
        }
        return null;
    };

    if ($needsTrx) {
        $trxIrt = $getPair($prices, 'TRX', 'IRT');
        if ($trxIrt === null) {
            error_log('Missing or invalid TRX/IRT price from SwapWallet');
            $missingKeys[] = 'TRX';
        } else {
            $result['TRX'] = round($trxIrt, 2);
        }
    }

    if ($needsTon) {
        $tonIrt = $getPair($prices, 'TON', 'IRT');
        if ($tonIrt === null) {
            $tonIrt = $getPair($prices, 'Ton', 'IRT');
        }

        if ($tonIrt === null) {
            error_log('Missing or invalid TON/IRT price from SwapWallet');
            $missingKeys[] = 'Ton';
        } else {
            $result['Ton'] = round($tonIrt, 2);
        }
    }

    if ($needsUsd) {
        $usdtIrt = $getPair($prices, 'USDT', 'IRT');
        if ($usdtIrt === null) {
            error_log('Missing or invalid USDT/IRT price from SwapWallet');
            $missingKeys[] = 'USD';
        } else {
            $result['USD'] = round($usdtIrt, 2);
        }
    }

    return ['ok' => empty($missingKeys), 'result' => $result];
}

function requireTronRates(array $keys = [])
{
    $normalizedKeys = [];
    foreach ($keys as $key) {
        $upper = strtoupper(trim((string) $key));
        if ($upper === '') {
            continue;
        }
        $normalizedKeys[$upper] = true;
    }

    $requestedKeys = array_keys($normalizedKeys);
    $rates = tronratee($requestedKeys);

    if (!is_array($rates) || !isset($rates['result']) || !is_array($rates['result'])) {
        return null;
    }

    $result = $rates['result'];

    if (isset($result['USD']) && is_numeric($result['USD'])) {
        $result['USD'] = round(abs((float) $result['USD']), 2);
    }

    $validationKeys = [];
    if (empty($requestedKeys)) {
        $validationKeys = ['TRX', 'Ton', 'USD'];
    } else {
        foreach ($requestedKeys as $requestedKey) {
            if ($requestedKey === 'TON') {
                $validationKeys[] = 'Ton';
            } elseif ($requestedKey === 'TRX' || $requestedKey === 'USD') {
                $validationKeys[] = $requestedKey;
            } else {
                $validationKeys[] = $requestedKey;
            }
        }
    }

    foreach ($validationKeys as $key) {
        if (!isset($result[$key]) || (is_numeric($result[$key]) && (float) $result[$key] == 0.0)) {
            return null;
        }
    }

    return $result;
}

function updatePaymentMessageId($response, $orderId)
{
    if (!is_array($response)) {
        error_log("Failed to send payment message for order {$orderId}: unexpected response");
        return false;
    }

    if (empty($response['ok'])) {
        error_log("Failed to send payment message for order {$orderId}: " . redfox_remote_error_summary($response));
        return false;
    }

    if (!isset($response['result']['message_id'])) {
        error_log("Missing message_id for order {$orderId}: invalid_telegram_response");
        return false;
    }

    update("Payment_report", "message_id", intval($response['result']['message_id']), "id_order", $orderId);
    return true;
}
function nowPayments($payment, $price_amount, $order_id, $order_description)
{
    global $domainhosts;
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
    $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    if ($apinowpayments === '' || $apinowpayments === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'order_id' => $order_id,
        'order_description' => $order_description,
        'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayment.php"
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
/* ---- database_helpers_2.php ---- */
if (!function_exists('rx_release_unpaid_discount')) {

    function rx_release_unpaid_discount($userId, $discountCode = null, $referenceTime = null) {
        global $pdo;
        $userId = trim((string)$userId);
        if ($userId === '' || !isset($pdo)) return false;
        try {
            if ($referenceTime !== null && (int)$referenceTime > 0) {
                $low = (string)((int)$referenceTime - 900);
                $high = (string)((int)$referenceTime + 900);
            } else {
                $low = (string)(time() - 1800);
                $high = (string)(time() + 60);
            }
            $params = [':u' => $userId, ':lo' => $low, ':hi' => $high];
            $codeClause = '';
            if ($discountCode !== null && trim((string)$discountCode) !== '') {
                $codeClause = ' AND code = :c';
                $params[':c'] = trim((string)$discountCode);
            }
            $stmt = $pdo->prepare(
                "SELECT id, code FROM Giftcodeconsumed
                  WHERE id_user = :u
                    AND kind = 'sell'
                    AND (released IS NULL OR released = 0)
                    AND consumed_at <> ''
                    AND CAST(consumed_at AS UNSIGNED) BETWEEN :lo AND :hi" . $codeClause . "
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || empty($row['code'])) return false;

            $code = (string)$row['code'];
            $rowId = (int)$row['id'];

            $marked = $pdo->prepare('UPDATE Giftcodeconsumed SET released = 1 WHERE id = :id AND (released IS NULL OR released = 0)');
            $marked->execute([':id' => $rowId]);
            if ($marked->rowCount() < 1) return false;

            $ds = $pdo->prepare('SELECT usedDiscount FROM DiscountSell WHERE codeDiscount = :c LIMIT 1');
            $ds->execute([':c' => $code]);
            $dsRow = $ds->fetch(PDO::FETCH_ASSOC);
            if (is_array($dsRow)) {
                $used = (int)($dsRow['usedDiscount'] ?? 0) - 1;
                if ($used < 0) $used = 0;
                $pdo->prepare('UPDATE DiscountSell SET usedDiscount = :v WHERE codeDiscount = :c')
                    ->execute([':v' => (string)$used, ':c' => $code]);
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}


if (!function_exists('balance_atomic_charge')) {

    function balance_atomic_charge($userId, $delta, $allowNegativeUpTo = 0) {
        global $pdo;
        $delta = (float) $delta;
        if ($delta <= 0) return ['ok' => false, 'reason' => 'invalid-delta', 'new_balance' => null];
        $allowNegativeUpTo = max(0.0, (float) $allowNegativeUpTo);


        $minBalance = $delta - $allowNegativeUpTo;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance - :d WHERE id = :u AND Balance >= :m");
            $stmt->execute([':d' => $delta, ':u' => $userId, ':m' => $minBalance]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'reason' => 'insufficient-or-stale', 'new_balance' => null];
            }
            $sel = $pdo->prepare("SELECT Balance FROM user WHERE id = :u");
            $sel->execute([':u' => $userId]);
            $newBal = $sel->fetchColumn();
            return ['ok' => true, 'reason' => 'charged', 'new_balance' => (float) $newBal];
        } catch (Throwable $e) {
            error_log('balance_atomic_charge failed: ' . redfox_exception_fingerprint($e));
            return ['ok' => false, 'reason' => 'db-error', 'new_balance' => null];
        }
    }
}


if (!function_exists('nm_validateSellDiscount')) {
    function nm_validateSellDiscount($code, $section, $codeProduct, $codePanel, $user, $from_id)
    {
        global $pdo;
        $code = trim((string)$code);
        $sections = ['buy', 'extend', 'volume', 'time', 'charge', 'all'];
        $section = in_array($section, $sections, true) ? $section : 'all';
        $res = ['ok' => false, 'reason' => '', 'row' => null, 'value_type' => 'percent', 'value' => 0.0, 'label' => ''];

        if ($code === '') {
            $res['reason'] = '❌ کد تخفیف را وارد کنید.';
            return $res;
        }

        if ($section !== 'charge' && intval($user['pricediscount'] ?? 0) != 0) {
            $res['reason'] = '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.';
            return $res;
        }

        $agent       = (string)($user['agent'] ?? 'f');
        $codeProduct = ($codeProduct === '' || $codeProduct === null) ? 'all' : $codeProduct;
        $codePanel   = ($codePanel === '' || $codePanel === null) ? '/all' : $codePanel;

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM DiscountSell
                  WHERE codeDiscount = :code
                    AND (code_product = :cp OR code_product = 'all')
                    AND (code_panel = :cpan OR code_panel = '/all')
                    AND (agent = :agent OR agent = 'allusers' OR agent = 'all')
                    AND (COALESCE(NULLIF(section, ''), type, 'all') = :section
                         OR COALESCE(NULLIF(section, ''), type, 'all') = 'all')
                    AND (status IS NULL OR status = '' OR status = 'active')
                    AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                  LIMIT 1"
            );
            $stmt->execute([
                ':code'   => $code,
                ':cp'     => $codeProduct,
                ':cpan'   => $codePanel,
                ':agent'  => $agent,
                ':section' => $section,
                ':uid'    => (string)$from_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('nm_validateSellDiscount query failed: ' . redfox_exception_fingerprint($e));
            $res['reason'] = '❌ خطا در بررسی کد تخفیف.';
            return $res;
        }

        if (!$row) {
            $res['reason'] = '❌ کد تخفیف نامعتبر است یا برای این بخش فعال نیست.';
            return $res;
        }

        if (intval($row['time']) != 0 && time() >= intval($row['time'])) {
            $res['reason'] = '❌ زمان کد تخفیف به پایان رسیده است.';
            return $res;
        }

        if (intval($row['limitDiscount']) > 0 && intval($row['usedDiscount']) >= intval($row['limitDiscount'])) {
            $res['reason'] = '❌ ظرفیت استفاده از این کد تخفیف به پایان رسیده است.';
            return $res;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c");
            $stmt->execute([':u' => (string)$from_id, ':c' => $code]);
            $usedByUser = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $usedByUser = 0;
        }
        $useUser = intval($row['useuser']);
        if ($useUser > 0 && $usedByUser >= $useUser) {
            $res['reason'] = '⭕️ سقف استفاده شما از این کد تخفیف پر شده است.';
            return $res;
        }

        if ((string)($row['usefirst'] ?? '') === '1') {
            $invoiceCount = select("invoice", "*", "id_user", $from_id, "count");
            if (intval($invoiceCount) != 0) {
                $res['reason'] = '❌ این کد تخفیف فقط برای اولین خرید قابل استفاده است.';
                return $res;
            }
        }

        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)$row['price'];
        if ($vt === 'percent' && ($val <= 0 || $val > 100)) {
            $res['reason'] = '❌ درصد کد تخفیف نامعتبر است.';
            return $res;
        }
        if ($vt === 'amount' && $val <= 0) {
            $res['reason'] = '❌ مبلغ کد تخفیف نامعتبر است.';
            return $res;
        }

        $label = $vt === 'free'
            ? 'رایگان'
            : ($vt === 'amount' ? number_format($val) . ' تومان' : (string)$row['price'] . ' درصد');

        $res['ok']         = true;
        $res['row']        = $row;
        $res['value_type'] = $vt;
        $res['value']      = $val;
        $res['label']      = $label;
        return $res;
    }
}

if (!function_exists('nm_applySellDiscountToPrice')) {
    function nm_applySellDiscountToPrice($row, $price)
    {
        $price = (float)$price;
        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'free')   return 0.0;
        if ($vt === 'amount') return max(0.0, $price - $val);
        return max(0.0, $price - ($price * $val / 100));
    }
}

if (!function_exists('nm_markSellDiscountUsed')) {
    function nm_markSellDiscountUsed($code, $from_id, $username = '', $reportContext = '')
    {
        global $connect, $setting, $otherreport;
        $code = trim((string)$code);
        if ($code === '') return;

        try {
            $row = select("DiscountSell", "*", "codeDiscount", $code, "select");
            if ($row != false) {
                $value = intval($row['usedDiscount']) + 1;
                update("DiscountSell", "usedDiscount", $value, "codeDiscount", $code);
            }
        } catch (Throwable $e) {
            error_log('nm_markSellDiscountUsed update failed: ' . redfox_exception_fingerprint($e));
        }

        try {
            $now  = (string)time();
            $kind = 'sell';
            $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $from_id, $code, $kind, $now);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            try {
                $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)");
                $stmt->bind_param("ss", $from_id, $code);
                $stmt->execute();
                $stmt->close();
            } catch (Throwable $e2) {
            }
        }

        if (isset($setting['Channel_Report']) && strlen((string)$setting['Channel_Report']) > 0) {
            $uname = $username !== '' ? "@{$username} " : '';
            $ctx   = $reportContext !== '' ? " (بخش: {$reportContext})" : '';
            $text_report = "⭕️ کاربر {$uname}با آیدی عددی {$from_id} از کد تخفیف {$code}{$ctx} استفاده کرد.";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport ?? null,
                'text' => $text_report,
                'parse_mode' => "HTML",
            ]);
        }
    }
}

if (!function_exists('nm_pending_charge_bonus')) {
    function nm_pending_charge_bonus($user)
    {
        $pv4 = (string)($user['Processing_value_four'] ?? '');
        if (strpos($pv4, 'chg|') !== 0) return 0;
        $parts = explode('|', $pv4);
        $bonus = isset($parts[1]) ? intval($parts[1]) : 0;
        return max(0, $bonus);
    }
}

if (!function_exists('balance_atomic_credit')) {

    function balance_atomic_credit($userId, $delta) {
        global $pdo;
        $delta = (float) $delta;
        if ($delta <= 0) return false;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :u");
            $stmt->execute([':d' => $delta, ':u' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('balance_atomic_credit failed: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}


function StatusPayment($paymentid)
{
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
    $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    if ($apinowpayments === '' || $apinowpayments === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
}
function channel(array $id_channel)
{
    global $from_id;
    $channel_link = array();
    foreach ($id_channel as $channel) {
        $response = telegram('getChatMember', [
            'chat_id' => $channel,
            'user_id' => $from_id
        ]);
        if ($response['ok']) {
            if (!in_array($response['result']['status'], ['member', 'creator', 'administrator'])) {
                $channel_link[] = $channel;
            }
        }
    }
    if (count($channel_link) == 0) {
        return [];
    } else {
        return $channel_link;
    }
}
function isValidDate($date)
{
    return (strtotime($date) != false);
}
function rxGatewayTruthy($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'success', 'successful', 'ok', 'yes'], true);
    }
    return false;
}

function tronadoExtractPaymentToken($payment)
{
    if (!is_array($payment)) {
        return '';
    }
    $candidates = [
        $payment['Data']['Token'] ?? null,
        $payment['data']['token'] ?? null,
        $payment['Data']['token'] ?? null,
        $payment['Token'] ?? null,
        $payment['token'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }
    return '';
}

function trnado($order_id, $price)
{
    global $domainhosts;

    $apitronseller = select("PaySetting", "*", "NamePay", "apiternado", "select")['ValuePay'];
    $walletSetting = select("PaySetting", "*", "NamePay", "walletaddress", "select");
    $walletaddress = trim((string) ($walletSetting['ValuePay'] ?? ''));
    $configuredUrl = trim((string) (select("PaySetting", "*", "NamePay", "urlpaymenttron", "select")['ValuePay'] ?? ''));

    $defaultEndpoints = defined('TRONADO_ORDER_TOKEN_ENDPOINTS') ? TRONADO_ORDER_TOKEN_ENDPOINTS : [];

    if (empty($defaultEndpoints)) {
        $defaultEndpoints = ['https://bot.tronado.cloud/api/v1/Order/GetOrderToken'];
    }

    $endpoints = $defaultEndpoints;
    if ($configuredUrl !== '') {
        array_unshift($endpoints, $configuredUrl);
        $endpoints = array_values(array_unique($endpoints));
    }

    $callbackUrl = 'https://' . $domainhosts . '/payment/tronado.php';
    $requestPayload = [
        'PaymentID' => (string) $order_id,
        'Amount' => is_numeric($price) ? (float) $price : $price,
        'Wallet' => $walletaddress,
        'CallbackUrl' => $callbackUrl,
        'OrderId' => (string) $order_id,
        'Metadata' => [
            'PaymentID' => (string) $order_id,
        ],
    ];

    if (trim((string) $apitronseller) === '') {
        return [
            'success' => false,
            'error' => 'کلید API ترنادو تنظیم نشده است',
        ];
    }

    if ($walletaddress === '') {
        return [
            'success' => false,
            'error' => 'آدرس کیف پول تنظیم نشده است',
        ];
    }

    $payloadJson = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $lastErrorPayload = [
        'success' => false,
        'error' => 'Failed to contact Tronado gateway',
    ];

    foreach ($endpoints as $endpoint) {
        if ($endpoint === '') {
            continue;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' . $apitronseller,
                'Content-Type: application/json',
            ),
        ));

        $policy = redfox_apply_curl_url_policy($curl, $endpoint, false, false);
        if (empty($policy['ok'])) { curl_close($curl); continue; }
        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlInfo = curl_getinfo($curl);
        $statusCode = $curlInfo['http_code'] ?? null;

        error_log('Tronado request attempt HTTP=' . (int)$statusCode . ' errno=' . (int)$curlErrno);

        if ($response === false) {
            $lastErrorPayload = [
                'success' => false,
                'error' => 'Gateway transport failed',
                'status_code' => $statusCode,
                'errno' => $curlErrno,
            ];
            curl_close($curl);
            continue;
        }

        if ($statusCode !== null && $statusCode >= 400) {
            $lastErrorPayload = [
                'success' => false,
                'error' => 'Unexpected HTTP status code returned',
                'status_code' => $statusCode,
            ];

            curl_close($curl);

            if ($statusCode === 404) {
                continue;
            }

            error_log('Tronado payment request failed HTTP=' . (int)$statusCode);

            return $lastErrorPayload;
        }

        $decodedResponse = json_decode($response, true);
        if (!is_array($decodedResponse) || !array_key_exists('IsSuccessful', $decodedResponse) || !array_key_exists('Data', $decodedResponse)) {
            $errorPayload = [
                'success' => false,
                'error' => 'Invalid response structure received from Tronado gateway',
                'status_code' => $statusCode,
            ];

            error_log('Tronado payment returned an invalid response HTTP=' . (int)$statusCode);

            curl_close($curl);

            return $errorPayload;
        }

        curl_close($curl);

        return $decodedResponse;
    }

    error_log('Tronado payment request failed after all configured endpoints');

    return $lastErrorPayload;
}
function formatBytes($bytes, $precision = 2): string
{
    $base = log($bytes, 1024);
    $power = $bytes > 0 ? floor($base) : 0;
    $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
function formatOnlineAtLabel($onlineAt, $isOnline = null)
{
    if ($isOnline === true && (empty($onlineAt) || $onlineAt === null)) {
        return "Online (بدون زمان)";
    }

    if ($onlineAt === null || $onlineAt === '') {
        return "—";
    }

    if (is_string($onlineAt)) {
        $onlineAt = trim($onlineAt);
        if ($onlineAt === '') {
            return "—";
        }
        $lowered = strtolower($onlineAt);
        if ($lowered === 'online') {
            return 'آنلاین';
        }
        if ($lowered === 'offline') {
            return 'آفلاین';
        }
    }

    try {
        if (is_numeric($onlineAt)) {
            $dateTime = new DateTime('@' . intval($onlineAt));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        } else {
            $dateTime = new DateTime((string) $onlineAt, new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        }
        return jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    } catch (Exception $e) {
        return (string) $onlineAt;
    }
}
function generateUsername($from_id, $Metode, $username, $randomString, $text, $namecustome, $usernamecustom)
{
    $setting = select("setting", "*", null, null, "select");
    $user = select("user", "*", "id", $from_id, "select");
    if ($user == false) {
        $user = array();
        $user = array(
            'number_username' => '',
        );
    }
    if ($Metode == "آیدی عددی + حروف و عدد رندوم") {
        return $from_id . "_" . $randomString;
    } elseif ($Metode == "نام کاربری + عدد به ترتیب") {
        if ($username == "NOT_USERNAME") {
            if (preg_match('/^\w{3,32}$/', $namecustome)) {
                $username = $namecustome;
            }
        }
        return $username . "_" . $user['number_username'];
    } elseif ($Metode == "نام کاربری دلخواه")
        return $text;
    elseif ($Metode == "نام کاربری دلخواه + عدد رندوم") {
        $random_number = rand(1000000, 9999999);
        return $text . "_" . $random_number;
    } elseif ($Metode == "متن دلخواه + عدد رندوم") {
        return $namecustome . "_" . $randomString;
    } elseif ($Metode == "متن دلخواه + عدد ترتیبی") {
        return $namecustome . "_" . $setting['numbercount'];
    } elseif ($Metode == "آیدی عددی+عدد ترتیبی") {
        return $from_id . "_" . $user['number_username'];
    } elseif ($Metode == "متن دلخواه نماینده + عدد ترتیبی") {
        if ($usernamecustom == "none") {
            return $namecustome . "_" . $setting['numbercount'];
        }
        return $usernamecustom . "_" . $user['number_username'];
    }
}
function outputlunk($text)
{
    $url = trim((string)$text);
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    $body = '';
    $limit = 2 * 1024 * 1024;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT_MS => 6000,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'RedFox/2.4 subscription fetcher',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
            if (strlen($body) + strlen($chunk) > $limit) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
    if (empty($policy['ok'])) { curl_close($ch); return null; }
    $ok = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = (int)curl_errno($ch);
    curl_close($ch);
    if ($ok === false || $errno !== 0 || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }
    return $body;
}
function outputlunksub($url)
{
    $result = outputlunk(rtrim((string)$url, '/') . '/info');
    return $result === null ? false : $result;
}
function normalizeServiceConfigs($configs, $subscriptionUrl = null)
{
    $normalized = [];

    if (is_array($configs)) {
        foreach ($configs as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $normalized[] = $item;
        }
    } elseif (is_string($configs)) {
        $parts = preg_split("/\r\n|\n|\r/", $configs);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $normalized[] = $part;
            }
        }
    }

    $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
    if (empty($normalized) && $subscriptionUrl !== '') {
        if (preg_match('/^https?:/i', $subscriptionUrl)) {
            $fetched = outputlunk($subscriptionUrl);
            if (is_string($fetched) && $fetched !== '') {
                if (isBase64($fetched)) {
                    $fetched = base64_decode($fetched);
                }
                $parts = preg_split("/\r\n|\n|\r/", $fetched);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part === '') {
                            continue;
                        }
                        $normalized[] = $part;
                    }
                }
            }
        } else {
            $normalized[] = $subscriptionUrl;
        }
    }

    return array_values($normalized);
}
function DirectPayment($order_id, $image = 'images.jpg'): bool
{
    global $pdo, $ManagePanel, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot;
    $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
    $admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
    $otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
    $errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
    $porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $paymentNote = formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null);
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $steppay = explode("|", $Payment_report['id_invoice']);
    update("user", "Processing_value", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
    if ($steppay[0] == "getconfigafterpay") {
        // [invoice lookup with fallbacks] گاهی به‌خاطر race/cleanup/timing بین crypto-pay و DirectPayment،
        // فاکتور با username + Status='unpaid' پیدا نمیشه. چندتا fallback می‌گذاریم تا قبل از refund همه گزینه‌ها تست بشن.
        $__invUsername = isset($steppay[1]) ? trim((string)$steppay[1]) : '';
        $get_invoice = false;
        if ($__invUsername !== '') {
            try {
                // 1) دقیقا مثل قبل: username + Status='unpaid'
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :u AND Status = 'unpaid' ORDER BY id_invoice DESC LIMIT 1");
                $stmt->execute([':u' => $__invUsername]);
                $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $__e) { $get_invoice = false; }
            if (!$get_invoice) {
                try {
                    // 2) بدون فیلتر Status (در صورت تفاوت case یا تغییر status توسط cron دیگه)
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :u ORDER BY id_invoice DESC LIMIT 1");
                    $stmt->execute([':u' => $__invUsername]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $__e) { $get_invoice = false; }
            }
            if (!$get_invoice) {
                try {
                    // 3) case-insensitive روی username — اگه collation داره فرق می‌کنه
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE LOWER(username) = LOWER(:u) ORDER BY id_invoice DESC LIMIT 1");
                    $stmt->execute([':u' => $__invUsername]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $__e) { $get_invoice = false; }
            }
        }
        if (!$get_invoice) {
            try {
                // 4) آخرین چاره: آخرین فاکتور unpaid این کاربر که usernameش با user_id شروع میشه (پترن مرسوم)
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND (Status = 'unpaid' OR Status = 'Unpaid') AND username LIKE :prefix ORDER BY time_sell DESC LIMIT 1");
                $stmt->execute([':uid' => (string)$Balance_id['id'], ':prefix' => $Balance_id['id'] . '_%']);
                $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$get_invoice) {
                    // اگه پیدا نشد، بدون prefix filter
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND (Status = 'unpaid' OR Status = 'Unpaid') ORDER BY time_sell DESC LIMIT 1");
                    $stmt->execute([':uid' => (string)$Balance_id['id']]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                // [NOTE] قبلاً اینجا username رو به مقدار اصلی (`$__invUsername`) برمی‌گردوندیم،
                // ولی این باعث می‌شد cycle شکست-retry بی‌نهایت بشه: هر retry با username اصلی duplicate می‌خورد،
                // یوزرنیم تازه می‌ساخت تو پنل (zombie)، بعد restore دوباره به اصلی، دوباره duplicate، الی آخر.
                // الان username رو همون که DB داره نگه می‌داریم. اگه retry قبلی یوزر تو پنل ساخته، zombie-rescue
                // اون رو پیدا می‌کنه و استفاده می‌کنه؛ اگه نساخته، duplicate-retry با random جدید موفق میشه.
            } catch (Throwable $__e) { $get_invoice = false; }
        }
        // اگه با هیچ روشی پیدا نشد، قبل از اینکه به refund برسیم به ادمین گزارش بدیم و مستقیم برگردیم
        if (!$get_invoice) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] invoice NOT FOUND for order={$order_id} user={$Balance_id['id']} steppay[1]={$__invUsername} — aborting WITHOUT refund (so cryptocheck stuck-refund can handle it cleanly)");
            }
            $__setting = function_exists('select') ? select('setting', '*', null, null, 'select') : [];
            $__errReport = function_exists('select') ? (select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt = "⚠️ <b>فاکتور پیدا نشد برای ساخت سرویس</b>\n"
                   . "🛒 کد سفارش: <code>{$order_id}</code>\n"
                   . "👤 کاربر: <code>{$Balance_id['id']}</code>\n"
                   . "🔎 username موردنظر: <code>" . htmlspecialchars($__invUsername) . "</code>\n"
                   . "ℹ️ DirectPayment بدون refund برگشت — لطفاً دستی بررسی کنید.";
            if (!empty($__setting['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting['Channel_Report'],
                    'message_thread_id' => $__errReport,
                    'text' => $__txt,
                    'parse_mode' => 'HTML',
                ]);
            }
            return false;
        }
        $userAgent = $Balance_id['agent'] ?? 'f';
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (Location = :loc OR Location = '/all') AND (agent = :agent OR agent = 'all')");
        $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($get_invoice['name_product'] == "🛍 حجم دلخواه" || $get_invoice['name_product'] == "⚙️ سرویس دلخواه") {
            $info_product['data_limit_reset'] = "no_reset";
            $info_product['Volume_constraint'] = $get_invoice['Volume'];
            $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
            $info_product['code_product'] = "customvolume";
            $info_product['Service_time'] = $get_invoice['Service_time'];
            $info_product['price_product'] = $get_invoice['price_product'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (Location = :loc OR Location = '/all') AND (agent = :agent OR agent = 'all')");
            $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $username_ac = $get_invoice['username'];
        $randomString = bin2hex(random_bytes(2));
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");

        // [panel-missing guard] اگه پنل پیدا نشد، اصلاً نباید بریم سراغ createUser چون قطعاً "Panel Not Found" برمی‌گردونه.
        // قبل از refund، به ادمین گزارش بدیم تا دلیل واقعی (مثلاً پنل حذف شده، نام عوض شده، Service_location خراب) مشخص بشه.
        if (!is_array($marzban_list_get) || empty($marzban_list_get['name_panel'])) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] panel missing for order={$order_id} user={$Balance_id['id']} location='" . (string)($get_invoice['Service_location'] ?? '') . "' invoice={$get_invoice['id_invoice']} — aborting WITHOUT refund");
            }
            $__setting2 = function_exists('select') ? select('setting', '*', null, null, 'select') : [];
            $__errReport2 = function_exists('select') ? (select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt2 = "⚠️ <b>پنل برای ساخت سرویس پیدا نشد</b>\n"
                    . "🛒 کد سفارش: <code>{$order_id}</code>\n"
                    . "👤 کاربر: <code>{$Balance_id['id']}</code>\n"
                    . "📍 لوکیشن ذخیره‌شده در فاکتور: <code>" . htmlspecialchars((string)($get_invoice['Service_location'] ?? '')) . "</code>\n"
                    . "🧾 شناسه فاکتور: <code>{$get_invoice['id_invoice']}</code>\n"
                    . "ℹ️ DirectPayment بدون refund برگشت — لطفاً پنل را در دیتابیس بررسی کنید.";
            if (!empty($__setting2['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting2['Channel_Report'],
                    'message_thread_id' => $__errReport2,
                    'text' => $__txt2,
                    'parse_mode' => 'HTML',
                ]);
            }
            return false;
        }

        // [idempotent-refund guard] اگر این فاکتور قبلاً refund خورده (نشانه auto-refund تو dec_not_confirmed)،
        // دیگه نباید دوباره کیف پول رو شارژ کنیم — همینجا برمی‌گردیم و فقط لاگ می‌زنیم.
        // این جلوی double/triple-credit موقع retry گیر کردن سرویس رو می‌گیره.
        $__alreadyRefunded = false;
        try {
            $__pr = select("Payment_report", "dec_not_confirmed", "id_order", $order_id, "select");
            $__decNote = is_array($__pr) ? (string)($__pr['dec_not_confirmed'] ?? '') : '';
            if ($__decNote !== '' && stripos($__decNote, 'auto-refund') !== false) {
                $__alreadyRefunded = true;
            }
        } catch (Throwable $__e) { $__alreadyRefunded = false; }
        if ($__alreadyRefunded) {
            return true;
        }

        // [username normalize] اگر یوزرنیم فاکتور خالی/کوتاه‌تر از 3 کاراکتر بود، یکی معتبر بساز.
        // این از خطای پنل "Username must be at least 3 characters long" جلوگیری می‌کنه.
        if (!is_string($username_ac) || trim($username_ac) === '' || strlen(trim($username_ac)) < 3) {
            $username_ac = preg_replace('/[^A-Za-z0-9_]/', '', (string)$Balance_id['id']) . '_' . bin2hex(random_bytes(4));
            if (strlen($username_ac) < 3) $username_ac = 'u' . bin2hex(random_bytes(4));
            // فقط وقتی id_invoice معتبره update کن (جلوی خطای "Column id_invoice cannot be null" گرفته میشه)
            if (!empty($get_invoice['id_invoice'])) {
                try { update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']); } catch (Throwable $__e) { /* fail-open */ }
            }
        }

        // [duplicate-username guard - REMOVED]
        // پچ قبلی یک حلقه pre-check با DataUser داشت که باعث می‌شد:
        //   - چندین API call به panel قبل از createUser → کند و گاهی timeout
        //   - اگر createUser قبلی نیمه‌کاره موفق بوده (yوزر ساخته شده ولی bot جواب نگرفته)، DataUser می‌گفت "exists" و یوزرنیم عوض می‌شد
        //   - هر retry یوزرنیم جدید → چندین یوزر زامبی توی پنل + سرویس نهایی هیچ‌وقت تحویل نشد
        // الان فقط روی duplicate-error واقعی از createUser (در پایین) retry می‌کنیم، که هم سریع‌تر و هم دقیق‌تره.
        // این رفتار همون چیزیه که در wallet-payment موفق عمل می‌کنه.
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
            'from_id' => $Balance_id['id'],
            'username' => $Balance_id['username'],
            'type' => 'buy'
        );
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            if (nmStockCompleteBuyFromInventory($Balance_id['id'], $Balance_id, $marzban_list_get, $info_product, $get_invoice['id_invoice'], $username_ac, false, 'paid_national_buy')) {
                sendmessage($Balance_id['id'], $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                return true;
            }
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            // [refund-marker] برای جلوگیری از double-refund توسط retry
            try {
                $__nationalNote = '[auto-refund: national stock empty at ' . date('Y-m-d H:i:s') . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__nationalNote, ':n2' => $__nationalNote, ':o' => $order_id]);
            } catch (Throwable $__e) { /* fail-open */ }
            sendmessage($Balance_id['id'], "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. مبلغ پرداختی به کیف پول برگشت خورد.", $keyboard, 'HTML');
            return true;
        }
        // [zombie-rescue] قبل از تلاش جدید، اگه قبلاً تو panel یوزری برای این کاربر ساخته شده (zombie)،
        // اول بررسی کن: شاید createUser تو call قبلی موفق بوده فقط response نرسیده. اگه پیدا کردیم،
        // از همون استفاده کن (بدون ساختن یوزر جدید) — این جلوی تولید بیشتر zombie رو می‌گیره.
        $__isRetryCall = false;
        try {
            $__pr2 = select("Payment_report", "crypto_check_count", "id_order", $order_id, "select");
            $__cnt = is_array($__pr2) ? (int)($__pr2['crypto_check_count'] ?? 0) : 0;
            if ($__cnt >= 1) $__isRetryCall = true;
        } catch (Throwable $__e) { /* ignore */ }

        $dataoutput = null;
        if ($__isRetryCall) {
            // در حالت retry، اول چک کن یوزر در پنل وجود داره یا نه (با همون username فاکتور)
            try {
                $__zombieCheck = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
                if (is_array($__zombieCheck) && !empty($__zombieCheck['username']) && (string)$__zombieCheck['username'] === (string)$username_ac) {
                    // یوزر تو پنل هست! یعنی createUser قبلی واقعاً موفق بوده، فقط bot جواب نگرفته.
                    // از همون استفاده کن.
                    $dataoutput = $__zombieCheck;
                    $dataoutput['status'] = 'successful';
                    if (empty($dataoutput['configs']) && !empty($dataoutput['links'])) {
                        $dataoutput['configs'] = is_array($dataoutput['links']) ? $dataoutput['links'] : explode("\n", (string)$dataoutput['links']);
                    }
                }
            } catch (Throwable $__e) { /* fail-open */ }
        }

        if (empty($dataoutput) || empty($dataoutput['username'])) {
            $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
        }

        // [duplicate retry — حداکثر 1 بار] اگه createUser duplicate برگردوند، فقط یک بار با random جدید retry می‌کنیم.
        try {
            if (empty($dataoutput['username'])) {
                $__msgRaw = is_array($dataoutput) ? ($dataoutput['msg'] ?? '') : '';
                $__msgStr = is_string($__msgRaw) ? $__msgRaw : json_encode($__msgRaw);
                if (stripos($__msgStr, 'duplicate') !== false || stripos($__msgStr, 'already exist') !== false || stripos($__msgStr, 'exists') !== false) {
                    $username_ac = preg_replace('/[^A-Za-z0-9_]/', '', (string)$Balance_id['id']) . '_' . bin2hex(random_bytes(4));
                    if (strlen($username_ac) < 3) $username_ac = 'u' . bin2hex(random_bytes(4));
                    if (!empty($get_invoice['id_invoice'])) {
                        try { update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']); } catch (Throwable $__e2) { /* fail-open */ }
                    }
                    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
                }
            }
        } catch (Throwable $__e) { /* fail-open */ }

        // [CRITICAL: mark invoice active EARLY]
        // اگه createUser موفق بوده، همین الان قبل از هر sendmessage/QR code generation/الخ که ممکنه hang کنه،
        // invoice رو active علامت بزن. این جلوی retry بعدی توسط cron رو می‌گیره حتی اگه پیام تلگرام hang کنه.
        if (!empty($dataoutput['username']) && !empty($get_invoice['id_invoice'])) {
            try {
                $__early = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                $__early->execute([':i' => $get_invoice['id_invoice']]);
            } catch (Throwable $__e) { /* fail-open */ }
            // و dec_not_confirmed را با علامت موفقیت بگذار تا retry cron این رو پیدا نکنه
            try {
                $__doneNote = '[service-created at ' . date('Y-m-d H:i:s') . ' username=' . $dataoutput['username'] . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__doneNote, ':n2' => $__doneNote, ':o' => $order_id]);
            } catch (Throwable $__e) { /* fail-open */ }
        }
        if ($dataoutput['username'] == null && function_exists('nmPanelEmergencyPanel')) {
            $emergencyPanel = nmPanelEmergencyPanel($marzban_list_get);
            if ($emergencyPanel) {
                $emergencyProduct = function_exists('nmEmergencyProductFor') ? nmEmergencyProductFor($info_product, $emergencyPanel) : $info_product;
                $datac['data_limit'] = ($emergencyProduct['Volume_constraint'] ?? $info_product['Volume_constraint']) * pow(1024, 3);
                $datac['expire'] = strtotime('+' . (int)($emergencyProduct['Service_time'] ?? $info_product['Service_time']) . ' day');
                $emergencyOut = $ManagePanel->createUser($emergencyPanel['name_panel'], $emergencyProduct['code_product'], $username_ac, $datac);
                if (($emergencyOut['username'] ?? null) != null) {
                    $dataoutput = $emergencyOut;
                    $marzban_list_get = $emergencyPanel;
                }
            }
        }
        if ($dataoutput['username'] == null && function_exists('nmPanelEmergencyEnabled') && nmPanelEmergencyEnabled($marzban_list_get)) {
            if (nmStockCompleteBuyFromInventory($Balance_id['id'], $Balance_id, $marzban_list_get, $info_product, $get_invoice['id_invoice'], $username_ac, false, 'paid_emergency_stock')) {
                sendmessage($Balance_id['id'], $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                return true;
            }
        }
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            // [refund-marker] برای جلوگیری از double-refund توسط retry — حتماً قبل از sendmessageها مارک کن
            try {
                $__failNote = '[auto-refund: service creation failed at ' . date('Y-m-d H:i:s') . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__failNote, ':n2' => $__failNote, ':o' => $order_id]);
            } catch (Throwable $__e) { /* fail-open */ }
            // پیام UI fallback اگه textbotlang در دسترس نباشه (مثلاً وقتی از cron صدا زده میشه)
            $__uiErr = isset($textbotlang['users']['sell']['ErrorConfig']) && is_string($textbotlang['users']['sell']['ErrorConfig']) && trim($textbotlang['users']['sell']['ErrorConfig']) !== ''
                ? $textbotlang['users']['sell']['ErrorConfig']
                : "❌ متاسفانه ساخت سرویس با خطا مواجه شد. مبلغ پرداختی به کیف پول شما برگشت داده شد.";
            sendmessage($Balance_id['id'], $__uiErr, $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت کانفیگ
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : {$Balance_id['id']}
نام کاربری کاربر : @{$Balance_id['username']}
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }
        $Shoppinginfo = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"],
                ]
            ]
        ]);
        $output_config_link = "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($get_invoice['Service_time']) == 0)
            $get_invoice['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $get_invoice['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $get_invoice['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $get_invoice['Volume'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $get_invoice['id_invoice']);
        }
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $get_invoice['id_invoice'], $get_invoice['id_user'], $image);
        $partsdic = explode("_", $Balance_id['Processing_value_four'], $get_invoice['id_user']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $countinvoice = $stmt->rowCount();
        if ($affiliatescommission['status_commission'] == "oncommission" && ($Balance_id['affiliates'] != null && intval($Balance_id['affiliates']) != 0)) {
            if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
                if ($countinvoice <= 1) {
                    $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                    $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                    if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                        sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                        $scorenew = $user_Balance['score'] + 2;
                        update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                    }
                    $Balance_prim = $user_Balance['Balance'] + $result;
                    $dateacc = date('Y/m/d H:i:s');
                    update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                    $result = number_format($result);
                    $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                    $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید
تایم : $dateacc";
                    if (strlen($setting['Channel_Report']) > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $porsantreport,
                            'text' => $textreportport,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
                }
            } else {

                $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                    sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                }
                $Balance_prim = $user_Balance['Balance'] + $result;
                $dateacc = date('Y/m/d H:i:s');
                update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                $result = number_format($result);
                $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید
تایم : $dateacc";
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
            }
        }
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($setting['numbercount']) + 1;
                update("setting", "numbercount", $value);
            }
        }
        $__walletPortion = (float)$get_invoice['price_product'] - (float)($Payment_report['price'] ?? 0);
        if ($__walletPortion < 0) {
            $__walletPortion = 0;
        }
        $Balance_prims = (float)$Balance_id['Balance'] - $__walletPortion;
        if ($Balance_prims <= 0) {
            $Balance_prims = 0;
        }
        update("user", "Balance", $Balance_prims, "id", $Balance_id['id']);
        $balanceformatsell = select("user", "Balance", "id", $get_invoice['id_user'], "select")['Balance'];
        $balanceformatsell = number_format($balanceformatsell, 0);
        $balancebefore = number_format($Balance_id['Balance'], 0);
        $timejalali = jdate('Y/m/d H:i:s');
        $textonebuy = "";
        if ($countinvoice == 1) {
            $textonebuy = "📌 خرید اول کاربر";
        }
        // [fallback] اگه textbotlang در cron context کامل لود نشده، text رو با مقدار default پر کن تا تلگرام reject نکنه
        $__mngBtnText = '👤 مدیریت کاربر';
        if (isset($textbotlang['Admin']['ManageUser']['mangebtnuser']) && is_string($textbotlang['Admin']['ManageUser']['mangebtnuser']) && trim($textbotlang['Admin']['ManageUser']['mangebtnuser']) !== '') {
            $__mngBtnText = $textbotlang['Admin']['ManageUser']['mangebtnuser'];
        }
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $__mngBtnText, 'callback_data' => 'manageuser_' . $Balance_id['id']],
                ],
            ]
        ]);
        $text_report = "📣 جزئیات ساخت اکانت در ربات بعد پرداخت ثبت شد .

$textonebuy
▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر :@{$Balance_id['username']}
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
▫️زمان خریداری شده :{$get_invoice['Service_time']} روز
▫️نام محصول خریداری شده :{$get_invoice['name_product']}
▫️حجم خریداری شده : {$get_invoice['Volume']} GB
▫️موجودی قبل خرید : $balancebefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: {$get_invoice['id_invoice']}
▫️نوع کاربر : {$Balance_id['agent']}
▫️شماره تلفن کاربر : {$Balance_id['number']}
▫️قیمت محصول : {$get_invoice['price_product']} تومان
▫️قیمت نهایی : {$Payment_report['price']} تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $text_report,
                'parse_mode' => "HTML",
                'reply_markup' => $Response
            ]);
        }
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        update("invoice", "Status", "active", "username", $get_invoice['username']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
            $textconfrom = "✅ پرداخت تایید شده
🛍خرید سرویس
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل خرید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextenduser") {
        $balanceformatsell = number_format(select("user", "Balance", "id", $Balance_id['id'], "select")['Balance'], 0);
        $partsdic = explode("%", $steppay[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $data_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $service_other = $data_order;
        if ($service_other == false) {
            sendmessage($Balance_id['id'], '❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید', $keyboard, 'HTML');
            return false;
        }
        $service_other = json_decode($service_other['value'], true);
        $codeproduct = $service_other['code_product'];
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = $data_order['price'];
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = '{$nameloc['Service_location']}' OR Location = '/all') AND (agent = '{$Balance_id['agent']}' OR agent = 'all') AND code_product = '$codeproduct'");
            $stmt->execute();
            $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($nameloc['name_product'] == "سرویس تست") {
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        }
        if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $Balance_id['id'], $keyboard)) {
            $balance = (float)($Balance_id['Balance'] ?? 0) + (float)($Payment_report['price'] ?? 0);
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "💎 مبلغ پرداختی به دلیل فعال بودن وضعیت اینترنت ملی/پنل اضطراری به کیف پول شما برگشت خورد.", $keyboard, 'HTML');
            return true;
        }
        $dateacc = date('Y/m/d H:i:s');
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
        $Balance_Low_user = 0;
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
        if ($extend['status'] == false && function_exists('nmPanelEmergencyPanel')) {
            $emergencyPanel = nmPanelEmergencyPanel($marzban_list_get);
            if ($emergencyPanel) {
                $emergencyProduct = function_exists('nmEmergencyProductFor') ? nmEmergencyProductFor($prodcut, $emergencyPanel) : $prodcut;
                $extendEmergency = $ManagePanel->extend($emergencyPanel['Methodextend'], $emergencyProduct['Volume_constraint'], $emergencyProduct['Service_time'], $nameloc['username'], $emergencyProduct['code_product'], $emergencyPanel['code_panel']);
                if (($extendEmergency['status'] ?? false) != false) {
                    $extend = $extendEmergency;
                    $marzban_list_get = $emergencyPanel;
                }
            }
        }
        if ($extend['status'] == false) {
            $fallbackStock = nmStockFallbackForInvoice($nameloc, $prodcut, 'extend_panel_fallback');
            if ($fallbackStock) {
                update("service_other", "output", json_encode(['status' => true, 'source' => 'nm_stock', 'stock_id' => $fallbackStock['id'], 'tier' => $fallbackStock['tier']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "id", $data_order['id']);
                update("service_other", "status", "paid", "id", $data_order['id']);
                sendmessage($Balance_id['id'], "✅ پنل اصلی در دسترس نبود؛ تمدید شما با کانفیگ جایگزین از انبار شبکه‌ملی تکمیل شد.", $keyboard, 'HTML');
                return true;
            }
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $extend['msg'] = redfox_remote_error_summary($extend);
            $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }

        update("service_other", "output", json_encode(['status' => true], JSON_UNESCAPED_SLASHES), "id", $data_order['id']);
        update("service_other", "status", "paid", "id", $data_order['id']);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $keyboardextendfnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        if ($Balance_id['agent'] == "f") {
            $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
        } else {
            $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$Balance_id['agenr']];
        }
        if (intval($valurcashbackextend) != 0) {
            $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
            $pricelastextend = $result;
            update("user", "Balance", $pricelastextend, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "تبریک 🎉
📌 به عنوان هدیه تمدید مبلغ $result تومان حساب شما شارژ گردید", null, 'HTML');
        }
        $priceproductformat = number_format($prodcut['price_product']);
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس : $usernamepanel
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        sendmessage($Balance_id['id'], $textextend, $keyboardextendfnished, 'HTML');
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 2;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $timejalali = jdate('Y/m/d H:i:s');
        $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .
    
▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر : @{$Balance_id['username']}
▫️نام کاربری کانفیگ :$usernamepanel
▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}
▫️نام محصول : {$prodcut['name_product']}
▫️حجم محصول : {$prodcut['Volume_constraint']}
▫️زمان محصول : {$prodcut['Service_time']}
▫️مبلغ تمدید : $priceproductformat تومان
▫️موجودی قبل از خرید : $balanceformatsell تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {

            $textconfrom = "✅ پرداخت تایید شده
🔋 تمدید سرویس
🪪 نام کاربری کانفیگ : $usernamepanel
🛍 نام محصول : {$prodcut['name_product']}
🌏 نام لوکیشن : {$nameloc['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل تمدید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextravolumeuser") {
        $steppay = explode("%", $steppay[1]);
        $volume = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != null) {
            $inboundid = $nameloc['inboundid'];
        }
        if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $Balance_id['id'], $keyboard)) {
            $balance = (float)($Balance_id['Balance'] ?? 0) + (float)($Payment_report['price'] ?? 0);
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "💎 مبلغ پرداختی به دلیل فعال بودن وضعیت اینترنت ملی/پنل اضطراری به کیف پول شما برگشت خورد.", $keyboard, 'HTML');
            return true;
        }
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'volume_value' => $volume,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_user";
        $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $volume);
        if ($extra_volume['status'] == false) {
            $extra_volume['msg'] = redfox_remote_error_summary($extra_volume);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_volume['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return false;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode(['status' => true], JSON_UNESCAPED_SLASHES));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price'], 0);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس  : {$steppay[0]}
▫️حجم اضافه : $volume گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textvolume, $keyboardextrafnished, 'HTML');
        $volumes = $volume;
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید حجم اضافه
🛍 حجم خریداری شده  : $volumes گیگ
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر حجم اضافه خریده است
        
اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 حجم خریداری شده  : $volumes گیگ
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}
موجودی کاربر قبل خرید : {$Balance_id['Balance']}
";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    } elseif ($steppay[0] == "getextratimeuser") {
        $steppay = explode("%", $steppay[1]);
        $tmieextra = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != false) {
            $inboundid = $nameloc['inboundid'];
        }
        if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $Balance_id['id'], $keyboard)) {
            $balance = (float)($Balance_id['Balance'] ?? 0) + (float)($Payment_report['price'] ?? 0);
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "💎 مبلغ پرداختی به دلیل فعال بودن وضعیت اینترنت ملی/پنل اضطراری به کیف پول شما برگشت خورد.", $keyboard, 'HTML');
            return true;
        }
        update("user", "Balance", $Balance_Low_user, "id", $nameloc['id_user']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'day' => $tmieextra,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_time_user";
        $timeservice = $DataUserOut['expire'] - time();
        $day = floor($timeservice / 86400);
        $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $tmieextra);
        if ($extra_time['status'] == false) {
            $extra_time['msg'] = redfox_remote_error_summary($extra_time);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_time['msg']}";
            sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return false;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode(['status' => true], JSON_UNESCAPED_SLASHES));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس : {$steppay[0]}
▫️زمان اضافه : $tmieextra روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textextratime, $keyboardextrafnished, 'HTML');
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $volumes = $tmieextra;
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید زمان اضافه
🛍 زمان خریداری شده  : $volumes روز
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر زمان اضافه خریده است
        
اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 زمان خریداری شده  : $volumes روز
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
            ]);
        }
    } else {
        $__chargeBonus = isset($Payment_report['charge_bonus']) ? intval($Payment_report['charge_bonus']) : 0;
        $__paidAmount = intval($Payment_report['price']);
        $__creditAmount = $__paidAmount + $__chargeBonus;
        $Balance_confrim = intval($Balance_id['Balance']) + $__creditAmount;
        update("user", "Balance", $Balance_confrim, "id", $Payment_report['id_user']);
        update("user", "Processing_value_four", "", "id", $Payment_report['id_user']);
        $format_price_cart = number_format($__paidAmount, 0);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
✍️ توضیحات : {$paymentNote}";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        $__creditFmt = number_format($__creditAmount, 0);
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$__creditFmt} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.
                
🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
    }
    return true;
}
/* ---- bot_api_helpers.php ---- */
function plisio($order_id, $price)
{
    global $domainhosts;
    $rowPlisio = select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select");
    $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
    if ($api_key === '' || $api_key === '0') {
        $rowLegacy = select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select");
        $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
    }

    $callbackUrl = '';
    $successUrl  = '';
    $failUrl     = '';
    if (isset($domainhosts) && is_string($domainhosts) && $domainhosts !== '') {
        $host = rtrim(preg_replace('#^https?://#', '', $domainhosts), '/');
        if ($host !== '') {
            $callbackUrl = 'https://' . $host . '/payment/plisio.php?json=true';
            $successUrl  = 'https://' . $host . '/payment/plisio_return.php?kind=success&order=' . urlencode($order_id);
            $failUrl     = 'https://' . $host . '/payment/plisio_return.php?kind=fail&order=' . urlencode($order_id);
        }
    }

    $url = 'https://api.plisio.net/api/v1/invoices/new';
    $url .= '?currency=TRX';
    $url .= '&amount=' . urlencode($price);
    $url .= '&order_number=' . urlencode($order_id);
    $url .= '&email=customer@plisio.net';
    $url .= '&order_name=plisio';
    $url .= '&language=fa';
    if ($callbackUrl !== '') {
        $url .= '&callback_url=' . urlencode($callbackUrl);
    }
    if ($successUrl !== '') {
        $url .= '&success_callback_url=' . urlencode($successUrl);
        $url .= '&success_invoice_url='  . urlencode($successUrl);
    }
    if ($failUrl !== '') {
        $url .= '&fail_callback_url=' . urlencode($failUrl);
        $url .= '&fail_invoice_url='  . urlencode($failUrl);
    }
    $url .= '&api_key=' . urlencode($api_key);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response['data'] ?? null;
}
function checkConnection($address, $port)
{
    $address = trim((string)$address);
    $port = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($address === '' || $port === false) return false;
    $urlHost = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $address . ']' : $address;
    $allowPrivate = redfox_private_panel_endpoints_allowed();
    $policy = redfox_outbound_url_policy('https://' . $urlHost . ':' . $port . '/', $allowPrivate, false);
    if (empty($policy['ok'])) return false;
    foreach ($policy['addresses'] as $resolved) {
        $socketHost = str_contains((string)$resolved, ':') ? '[' . $resolved . ']' : $resolved;
        $socket = @stream_socket_client('tcp://' . $socketHost . ':' . $port, $errno, $errstr, 5);
        if (is_resource($socket)) { fclose($socket); return true; }
    }
    return false;
}
function savedata($type, $namefiled, $valuefiled)
{
    global $from_id;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser, JSON_UNESCAPED_UNICODE);
        update("user", "Processing_value", $data, "id", $from_id);
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $raw = $userdata['Processing_value'] ?? null;

        $dataperevieos = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
        if (!is_array($dataperevieos)) {
            $dataperevieos = [];
        }
        $dataperevieos[$namefiled] = $valuefiled;
        update("user", "Processing_value", json_encode($dataperevieos, JSON_UNESCAPED_UNICODE), "id", $from_id);
    }
}
function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
{
    global $pdo;
    if (!function_exists('rx_schema_migration_mode') || !rx_schema_migration_mode()) {
        rx_require_schema($pdo, [], [(string)$tableName => [(string)$fieldName]]);
        return;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName"
    );
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tableExists['count'] == 0)
        return;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
    $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($filedExists['count'] != 0)
        return;
    $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
    $statement = $pdo->prepare($query);
    $statement->execute();
    if ($defaultValue != null) {
        $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
        $stmt->bindParam(1, $defaultValue);
        $stmt->execute();
    }
    echo "The $fieldName field was added ✅";
}
function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionGuard, $optionX_ui_single, $optionhiddfy, $optionalireza, $optionalireza_single, $optionmarzneshin, $option_mikrotik, $optionwg, $options_ui, $optioneylanpanel, $optionibsng;
    if ($typepanel == "marzban") {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    } elseif ($typepanel == "guard") {
        sendmessage($from_id, $message, $optionGuard, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $message, $optionhiddfy, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $message, $optionalireza_single, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $message, $optionmarzneshin, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $message, $optionwg, 'HTML');
    } elseif ($typepanel == "s_ui") {
        sendmessage($from_id, $message, $options_ui, 'HTML');
    } elseif ($typepanel == "ibsng") {
        sendmessage($from_id, $message, $optionibsng, 'HTML');
    } elseif ($typepanel == "mikrotik") {
        sendmessage($from_id, $message, $option_mikrotik, 'HTML');
    } elseif ($typepanel == "eylanpanel") {
        sendmessage($from_id, $message, $optioneylanpanel, 'HTML');
    }
}
function addBackgroundImage($urlimage, $qrCodeResult, $backgroundPath)
{
    if (!is_object($qrCodeResult) || !method_exists($qrCodeResult, 'getString')) {
        error_log('Invalid QR code data provided to addBackgroundImage.');
        return false;
    }

    $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);

    $basename = is_string($backgroundPath) && $backgroundPath !== ''
        ? basename($backgroundPath)
        : 'images.jpeg';
    $basenameNoExt = pathinfo($basename, PATHINFO_FILENAME) ?: 'images';

    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpeg',
        $projectRoot . DIRECTORY_SEPARATOR . $basenameNoExt . '.jpg',
        $projectRoot . DIRECTORY_SEPARATOR . 'images.jpeg',
        $projectRoot . DIRECTORY_SEPARATOR . 'images.jpg',
    ];

    if (is_string($backgroundPath) && $backgroundPath !== '') {
        $candidates[] = $backgroundPath;
        if ($backgroundPath[0] !== DIRECTORY_SEPARATOR && $backgroundPath[0] !== '/') {
            $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . ltrim($backgroundPath, '.\\/' . DIRECTORY_SEPARATOR);
        }
    }

    $resolvedPath = null;
    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $resolvedPath = $candidate;
            break;
        }
    }

    if ($resolvedPath === null) {
        return false;
    }

    $qrCodeImage = @imagecreatefromstring($qrCodeResult->getString());
    if ($qrCodeImage === false) {
        error_log('Unable to create QR code image resource.');
        return false;
    }

    $backgroundData = @file_get_contents($resolvedPath);
    if ($backgroundData === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to read background image: {$resolvedPath}");
        return false;
    }

    $backgroundImage = @imagecreatefromstring($backgroundData);
    if ($backgroundImage === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to create background image resource from file: {$resolvedPath}");
        return false;
    }

    $qrCodeWidth = imagesx($qrCodeImage);
    $qrCodeHeight = imagesy($qrCodeImage);
    $backgroundWidth = imagesx($backgroundImage);
    $backgroundHeight = imagesy($backgroundImage);

    $targetRatio  = 0.55;
    $shorterSide  = min($backgroundWidth, $backgroundHeight);
    $targetQrSize = (int) round($shorterSide * $targetRatio);
    if ($targetQrSize < 120) {
        $targetQrSize = min(120, $shorterSide);
    }

    if ($qrCodeWidth !== $targetQrSize || $qrCodeHeight !== $targetQrSize) {
        $resizedQr = imagecreatetruecolor($targetQrSize, $targetQrSize);
        if ($resizedQr !== false) {
            $white = imagecolorallocate($resizedQr, 255, 255, 255);
            imagefill($resizedQr, 0, 0, $white);
            imagecopyresampled(
                $resizedQr, $qrCodeImage,
                0, 0, 0, 0,
                $targetQrSize, $targetQrSize,
                $qrCodeWidth, $qrCodeHeight
            );
            imagedestroy($qrCodeImage);
            $qrCodeImage  = $resizedQr;
            $qrCodeWidth  = $targetQrSize;
            $qrCodeHeight = $targetQrSize;
        }
    }

    $padding    = (int) round($targetQrSize * 0.10);
    $boxSize    = $targetQrSize + ($padding * 2);
    $boxX       = (int) (($backgroundWidth  - $boxSize) / 2);
    $boxY       = (int) (($backgroundHeight - $boxSize) / 2);

    $boxClipX  = max(0, $boxX);
    $boxClipY  = max(0, $boxY);
    $boxClipW  = min($boxSize, $backgroundWidth  - $boxClipX);
    $boxClipH  = min($boxSize, $backgroundHeight - $boxClipY);

    $bgBackup = imagecreatetruecolor($boxClipW, $boxClipH);
    if ($bgBackup !== false) {
        imagecopy($bgBackup, $backgroundImage, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);
    }

    $glass = imagecreatetruecolor($boxClipW, $boxClipH);
    if ($glass !== false) {

        imagecopy($glass, $backgroundImage, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);

        for ($i = 0; $i < 22; $i++) {
            @imagefilter($glass, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $totalLum = 0;
        $samples  = 5;
        for ($sx = 0; $sx < $samples; $sx++) {
            for ($sy = 0; $sy < $samples; $sy++) {
                $px = imagecolorat(
                    $glass,
                    (int) ($boxClipW * ($sx + 0.5) / $samples),
                    (int) ($boxClipH * ($sy + 0.5) / $samples)
                );
                $r = ($px >> 16) & 0xFF;
                $g = ($px >>  8) & 0xFF;
                $b =  $px        & 0xFF;
                $totalLum += (0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }
        $avgLum = $totalLum / ($samples * $samples);

        if     ($avgLum < 70)  { $opacity = 55; $bBoost = 16; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 130) { $opacity = 45; $bBoost = 12; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 190) { $opacity = 38; $bBoost = 8;  $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
        elseif ($avgLum < 230) { $opacity = 30; $bBoost = 4;  $tintR = 245; $tintG = 248; $tintB = 252; $needInnerLine = true;  }
        else                   { $opacity = 55; $bBoost = -2; $tintR = 220; $tintG = 230; $tintB = 245; $needInnerLine = true;  }

        if ($bBoost !== 0) {
            @imagefilter($glass, IMG_FILTER_BRIGHTNESS, $bBoost);
        }

        $overlay = imagecreatetruecolor($boxClipW, $boxClipH);
        $oTint   = imagecolorallocate($overlay, $tintR, $tintG, $tintB);
        imagefill($overlay, 0, 0, $oTint);
        imagecopymerge($glass, $overlay, 0, 0, 0, 0, $boxClipW, $boxClipH, $opacity);
        imagedestroy($overlay);

        if ($needInnerLine) {

            $inner = imagecolorallocatealpha($glass, 80, 100, 130, 95);
            if ($inner !== false) {
                imagerectangle($glass, 2, 2, $boxClipW - 3, $boxClipH - 3, $inner);
            }
        }

        $radius = (int) round(min($boxClipW, $boxClipH) * 0.10);
        if ($radius > 4 && $bgBackup !== false) {
            $r2 = $radius * $radius;
            $corners = [
                ['cx' => $radius - 1,         'cy' => $radius - 1,         'sx' => 0,                  'sy' => 0,                  'ex' => $radius,    'ey' => $radius],
                ['cx' => $boxClipW - $radius, 'cy' => $radius - 1,         'sx' => $boxClipW - $radius, 'sy' => 0,                  'ex' => $boxClipW,  'ey' => $radius],
                ['cx' => $radius - 1,         'cy' => $boxClipH - $radius, 'sx' => 0,                  'sy' => $boxClipH - $radius, 'ex' => $radius,    'ey' => $boxClipH],
                ['cx' => $boxClipW - $radius, 'cy' => $boxClipH - $radius, 'sx' => $boxClipW - $radius, 'sy' => $boxClipH - $radius, 'ex' => $boxClipW,  'ey' => $boxClipH],
            ];
            foreach ($corners as $c) {
                for ($x = $c['sx']; $x < $c['ex']; $x++) {
                    for ($y = $c['sy']; $y < $c['ey']; $y++) {
                        $dx = $x - $c['cx'];
                        $dy = $y - $c['cy'];
                        if ($dx * $dx + $dy * $dy > $r2) {
                            imagesetpixel($glass, $x, $y, imagecolorat($bgBackup, $x, $y));
                        }
                    }
                }
            }
        }

        imagecopy($backgroundImage, $glass, $boxClipX, $boxClipY, 0, 0, $boxClipW, $boxClipH);
        imagedestroy($glass);
    } else {

        $whiteBox = imagecolorallocate($backgroundImage, 255, 255, 255);
        imagefilledrectangle(
            $backgroundImage,
            $boxX, $boxY,
            $boxX + $boxSize, $boxY + $boxSize,
            $whiteBox
        );
    }
    if ($bgBackup !== false) { imagedestroy($bgBackup); }

    $x = (int) (($backgroundWidth  - $qrCodeWidth)  / 2);
    $y = (int) (($backgroundHeight - $qrCodeHeight) / 2);

    imagecopy($backgroundImage, $qrCodeImage, $x, $y, 0, 0, $qrCodeWidth, $qrCodeHeight);

    $result = imagepng($backgroundImage, $urlimage);

    imagedestroy($qrCodeImage);
    imagedestroy($backgroundImage);

    if ($result === false) {
        error_log("Failed to save QR code with background to {$urlimage}");
    }

    return $result !== false;
}
function checktelegramip()
{
    global $telegramStrictIpValidation;

    $strictValidation = $telegramStrictIpValidation;
    if (!is_bool($strictValidation)) {
        $strictValidation = true;
    }

    if ($strictValidation === false) {
        return true;
    }

    $clientIp = getClientIpConsideringProxies();
    if ($clientIp === null) {
        return false;
    }

    $telegramIpRanges = [
        ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
        ['lower' => '91.108.4.0', 'upper' => '91.108.7.255'],
        ['lower' => '2001:67c:4e8::', 'upper' => '2001:67c:4e8:ffff:ffff:ffff:ffff:ffff'],
    ];

    foreach ($telegramIpRanges as $range) {
        if (isClientIpInRange($clientIp, $range['lower'], $range['upper'])) {
            return true;
        }
    }

    return false;
}

function getClientIpConsideringProxies()
{
    // Proxy headers are only trusted through Security.php and its explicit
    // REDFOX_TRUSTED_PROXIES CIDR allowlist. Never consume them directly here.
    if (function_exists('redfox_client_ip')) {
        $clientIp = redfox_client_ip();
        return filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : null;
    }

    $remoteAddr = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
    return is_string($remoteAddr) ? $remoteAddr : null;
}

function isClientIpInRange($clientIp, $lowerBound, $upperBound)
{
    $clientPacked = inet_pton($clientIp);
    $lowerPacked = inet_pton($lowerBound);
    $upperPacked = inet_pton($upperBound);

    if ($clientPacked === false || $lowerPacked === false || $upperPacked === false) {
        return false;
    }

    $length = strlen($clientPacked);
    if ($length !== strlen($lowerPacked) || $length !== strlen($upperPacked)) {
        return false;
    }

    return strcmp($clientPacked, $lowerPacked) >= 0 && strcmp($clientPacked, $upperPacked) <= 0;
}
function defaultCronStatusMap()
{
    return [
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ];
}

function normalizeCronStatus($rawCronStatus = null, $persist = false)
{
    $defaults = defaultCronStatusMap();
    if (is_string($rawCronStatus)) {
        $decoded = json_decode($rawCronStatus, true);
    } elseif (is_array($rawCronStatus)) {
        $decoded = $rawCronStatus;
    } else {
        $decoded = [];
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $normalized = array_merge($defaults, array_intersect_key($decoded, $defaults));
    foreach ($defaults as $key => $defaultValue) {
        $value = $normalized[$key];
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            $normalized[$key] = in_array($lower, ['1', 'true', 'on', 'yes'], true);
        } else {
            $normalized[$key] = (bool) $value;
        }
    }
    if ($persist && function_exists('update')) {
        update('setting', 'cron_status', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }
    return $normalized;
}

function addCronIfNotExists($cronCommand)
{
    $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
    $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
        return $command !== '';
    }));

    if (empty($commands)) {
        return true;
    }

    $logContext = implode('; ', $commands);

    if (!isShellExecAvailable()) {
        return true;
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        return true;
    }

    $existingCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
    $existingCronJobs = trim((string) $existingCronJobs);
    $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
    $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
        return $line !== '' && strpos($line, '#') !== 0;
    }));

    $newLineAdded = false;
    foreach ($commands as $command) {
        if (!in_array($command, $cronLines, true)) {
            $cronLines[] = $command;
            $newLineAdded = true;
        }
    }

    if (!$newLineAdded) {
        return true;
    }

    $cronLines = array_values(array_unique($cronLines));
    $cronContent = implode(PHP_EOL, $cronLines) . PHP_EOL;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
    if ($temporaryFile === false) {
        error_log('Unable to create temporary file for cron job registration.');
        return false;
    }

    if (file_put_contents($temporaryFile, $cronContent) === false) {
        error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
        unlink($temporaryFile);
        return false;
    }

    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    return true;
}

function activecron()
{
    global $domainhosts;

    $domainhosts = function_exists('redfox_normalize_domain')
        ? redfox_normalize_domain((string)$domainhosts)
        : '';
    if ($domainhosts === '') {
        error_log('activecron: REDFOX_DOMAIN is missing or invalid; cron setup skipped.');
        return false;
    }

    $cronSecret = trim((string)(rx_env('REDFOX_CRON_SECRET') ?: ''));
    if ($cronSecret === '') {
        error_log('activecron: REDFOX_CRON_SECRET is missing; cron setup skipped.');
        return false;
    }
    $cronCommands = [
        "*/1 * * * * curl --fail --silent --show-error -H "
        . escapeshellarg('X-Cron-Secret: ' . $cronSecret) . ' '
        . escapeshellarg("https://{$domainhosts}/cron/cron.php") . " > /dev/null 2>&1",
    ];

    return addCronIfNotExists($cronCommands);
}
function inlineFixer($str, int $count_button = 1)
{
    $str = trim($str);
    if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $str)) {
        if ($count_button >= 1) {
            switch ($count_button) {
                case 1:
                    $maxLength = 56;
                    break;
                case 2:
                    $maxLength = 24;
                    break;
                case 3:
                    $maxLength = 14;
                    break;
                default:
                    $maxLength = 2;
            }
            $visualLength = 2;
            $trimmedString = '';
            foreach (mb_str_split($str) as $char) {
                if (preg_match('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{1F1E6}-\x{1F1FF}]/u', $char)) {
                    $visualLength += 2;
                } else
                    $visualLength++;

                if ($visualLength > $maxLength)
                    break;

                $trimmedString .= $char;
            }
            if ($visualLength > $maxLength) {
                return trim($trimmedString) . '..';
            }
        }
    }
    return trim($str);
}
function createInvoice($amount)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('amount' => $amount, 'address' => $walletaddress, 'base' => 'trx'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return json_decode($response, true);
}
function verifpay($id)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/status?id=' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
}
function createInvoiceiranpay1($amount, $id_invoice)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "marchent_floypay", "select")['ValuePay'];
    $curl = curl_init();
    $amount = intval($amount);
    $data = [
        "ApiKey" => $PaySetting,
        "Hash_id" => $id_invoice,
        "Amount" => $amount . "0",
        "CallbackURL" => "https://$domainhosts/payment/iranpay1.php"
    ];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://tetra98.com/api/create_order",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function verifyxvoocher($code)
{
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://bot.donatekon.com/api/transaction/verify/" . $code,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);
    return json_decode($response, true);

    curl_close($curl);
}
function sanitizeUserName($userName)
{
    $forbiddenCharacters = [
        "'",
        "\"",
        "<",
        ">",
        "--",
        "#",
        ";",
        "\\",
        "%",
        "(",
        ")"
    ];

    foreach ($forbiddenCharacters as $char) {
        $userName = str_replace($char, "", $userName);
    }
    return $userName;
}
function publickey()
{
    $randomBytes = static function (int $length) {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($length);
            } catch (Throwable $exception) {
                error_log('random_bytes failed: ' . redfox_exception_fingerprint($exception));
            }
        }

        if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'randombytes_buf')) {
            try {
                return \ParagonIE_Sodium_Compat::randombytes_buf($length);
            } catch (Throwable $exception) {
                error_log('sodium_compat randombytes_buf failed: ' . redfox_exception_fingerprint($exception));
            }
        }

        return null;
    };

    if (function_exists('sodium_crypto_box_keypair')) {
        try {
            $privateKey = sodium_crypto_box_keypair();
            $privateKeyEncoded = base64_encode(sodium_crypto_box_secretkey($privateKey));
            $publicKey = sodium_crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('libsodium key generation failed: ' . redfox_exception_fingerprint($exception));
        }
    }

    if (!class_exists('\\ParagonIE_Sodium_Compat')) {
        $sodiumCompatAutoloaders = [
            APP_ROOT_PATH . '/vendor/autoload.php',
            APP_ROOT_PATH . '/vendor/paragonie/sodium_compat/autoload.php'
        ];

        foreach ($sodiumCompatAutoloaders as $autoloadPath) {
            if (is_readable($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
        unset($sodiumCompatAutoloaders, $autoloadPath);
    }

    if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'crypto_box_keypair')) {
        try {
            $privateKey = \ParagonIE_Sodium_Compat::crypto_box_keypair();
            $privateKeyEncoded = base64_encode(\ParagonIE_Sodium_Compat::crypto_box_secretkey($privateKey));
            $publicKey = \ParagonIE_Sodium_Compat::crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('sodium_compat key generation failed: ' . redfox_exception_fingerprint($exception));
        }
    }

    return [
        'status' => false,
        'msg' => 'Libsodium not available'
    ];
}
function languagechange($path_dir)
{

    $rx_candidates = [];
    if (is_string($path_dir) && $path_dir !== '') {
        $rx_candidates[] = $path_dir;
    }
    if (defined('REFACTORED_LEGACY_ROOT')) {
        $rx_candidates[] = REFACTORED_LEGACY_ROOT . DIRECTORY_SEPARATOR . 'text.json';
    }
    $rx_candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'text.json';
    $rx_candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'text.json';

    $rx_raw = null;
    foreach ($rx_candidates as $rx_candidate) {
        if (!is_string($rx_candidate) || $rx_candidate === '') continue;
        if (!@file_exists($rx_candidate)) continue;
        $rx_attempt = @file_get_contents($rx_candidate);
        if ($rx_attempt !== false && $rx_attempt !== '') {
            $rx_raw = $rx_attempt;
            break;
        }
    }
    if ($rx_raw === null) {
        return [];
    }

    $rx_decoded = json_decode($rx_raw, true);
    if (!is_array($rx_decoded)) {
        return [];
    }

    $rx_setting = null;
    if (function_exists('select')) {
        try {
            $rx_setting = select("setting", "*");
        } catch (\Throwable $rx_setting_err) {
            $rx_setting = null;
        }
    }
    $rx_lang_key = 'fa';
    if (is_array($rx_setting)) {
        if (isset($rx_setting['languageen']) && intval($rx_setting['languageen']) === 1) {
            $rx_lang_key = 'en';
        } elseif (isset($rx_setting['languageru']) && intval($rx_setting['languageru']) === 1) {
            $rx_lang_key = 'ru';
        }
    }

    if (isset($rx_decoded[$rx_lang_key]) && is_array($rx_decoded[$rx_lang_key])) {
        return $rx_decoded[$rx_lang_key];
    }
    if (isset($rx_decoded['fa']) && is_array($rx_decoded['fa'])) {
        return $rx_decoded['fa'];
    }
    return [];
}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
function createqrcode($contents)
{
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: $contents,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 500,
        margin: 2,
    );

    $result = $builder->build();
    return $result;
}
function sanitize_recursive(array $data): array
{
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        $sanitized_key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            $sanitized_data[$sanitized_key] = sanitize_recursive($value);
        } elseif (is_string($value)) {
            $sanitized_data[$sanitized_key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } elseif (is_int($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        } elseif (is_float($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } elseif (is_bool($value) || is_null($value)) {
            $sanitized_data[$sanitized_key] = $value;
        } else {
            $sanitized_data[$sanitized_key] = $value;
        }
    }
    return $sanitized_data;
}

function check_active_btn($keyboard, $text_var)
{
    $trace_keyboard = json_decode($keyboard, true)['keyboard'];
    $status = false;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == $text_var) {
                $status = true;
                break;
            }
        }
    }
    return $status;
}

function rx_usertest_panel_active()
{
    $count = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    return is_numeric($count) && (int)$count > 0;
}
function CreatePaymentNv($invoice_id, $amount)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchentpaynotverify", "select")['ValuePay'];
    $data = [
        'api_key' => $PaySetting,
        'amount' => $amount,
        'callback_url' => "https://" . $domainhosts . "/payment/paymentnv/back.php",
        'desc' => $invoice_id
    ];
    $data = json_encode($data);
    $ch = curl_init("https://donatekon.com/pay/api/dargah/create");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        )
    );
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
/* ---- business_logic_1.php ---- */
if (!function_exists('rx_auth_skip_user')) {
    function rx_auth_skip_user($user)
    {
        return false;
    }
}

function deleteFolder($folderPath)
{
    if (!is_dir($folderPath))
        return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($folderPath);
}
function isBase64($string)
{
    if (base64_encode(base64_decode($string, true)) === $string) {
        return true;
    }
    return false;
}
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg')
{
    global $setting, $from_id;
    $config = normalizeServiceConfigs($config);
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;

    $rxHasSubLink = ((($panel_info['sublink'] ?? '') == "onsublink") && is_string($sub_link) && trim($sub_link) !== '');
    $STATUS_SEND_MESSAGE_PHOTO = (!$rxHasSubLink && $panel_info['config'] == "onconfig" && count($config) != 1) ? false : true;
    $out_put_qrcode = "";
    if ($panel_info['type'] == "Manualsale" || $panel_info['type'] == "ibsng" || $panel_info['type'] == "mikrotik") {
    }
    if ($panel_info['sublink'] == "onsublink" && $panel_info['config']) {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['sublink'] == "onsublink") {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['config'] == "onconfig") {
        $out_put_qrcode = $config[0];
    }
    if ($STATUS_SEND_MESSAGE_PHOTO) {

        $infoCardSent = false;
        if (function_exists('getInfoCardStatus') && getInfoCardStatus()) {
            $cardPath = nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id);
            if ($cardPath !== null) {

                $cardKeyboard = nm_appendInfoCardQrButton($reply_markup, $invoice_id);
                telegram('sendphoto', [
                    'chat_id' => $user_id,
                    'photo' => new CURLFile($cardPath),
                    'reply_markup' => $cardKeyboard,
                    'caption' => $caption,
                    'parse_mode' => "HTML",
                ]);
                @unlink($cardPath);
                $infoCardSent = true;
            }
        }
        if (!$infoCardSent) {

            $urlimage = "$user_id$invoice_id.png";
            $qrCode = createqrcode($out_put_qrcode);
            file_put_contents($urlimage, $qrCode->getString());
            if (!addBackgroundImage($urlimage, $qrCode, $image)) {
                error_log("Unable to apply background image for QR code using path '{$image}'");
            }
            telegram('sendphoto', [
                'chat_id' => $user_id,
                'photo' => new CURLFile($urlimage),
                'reply_markup' => $reply_markup,
                'caption' => $caption,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        }
        if ($panel_info['type'] == "WGDashboard") {
            $urlimage = "{$panel_info['inboundid']}_{$username_service}.conf";
            file_put_contents($urlimage, $sub_link);
            sendDocument($user_id, $urlimage, "⚙️ کانفیگ شما");
            unlink($urlimage);
        }
    } else {
        sendmessage($user_id, $caption, $reply_markup, 'HTML');
    }
    if ($panel_info['config'] == "onconfig" && $setting['status_keyboard_config'] == "1" && function_exists('keyboard_config')) {
        if (is_array($config)) {
            $validConfigs = array_values(array_filter($config, function ($item) {
                return is_string($item) && trim($item) !== '';
            }));

            if (!empty($validConfigs)) {
                $keyboardPayload = keyboard_config($validConfigs, $invoice_id, false);
                $configButtonCount = 0;
                $keyboardData = json_decode($keyboardPayload, true);

                if (is_array($keyboardData) && isset($keyboardData['inline_keyboard']) && is_array($keyboardData['inline_keyboard'])) {
                    foreach ($keyboardData['inline_keyboard'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        foreach ($row as $button) {
                            if (!is_array($button)) {
                                continue;
                            }

                            $buttonText = $button['text'] ?? '';
                            $callbackData = $button['callback_data'] ?? '';

                            if ($buttonText === 'دریافت کانفیگ' && is_string($callbackData) && strpos($callbackData, 'configget_') === 0) {
                                ++$configButtonCount;
                            }
                        }
                    }
                } else {
                    error_log('Failed to decode keyboard payload for configuration prompt');
                }

                if ($configButtonCount > 1) {
                    sendmessage($user_id, "📌 جهت دریافت کانفیگ روی دکمه دریافت کانفیگ کلیک کنید", $keyboardPayload, 'HTML');
                }
            }
        }
    }
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{

    if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
        sendmessage($fromId, "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
        update("user", "verify", "1", "id", $fromId);
        update("user", "cardpayment", "1", "id", $fromId);
    }
}
function createPayZarinpal($price, $order_id)
{
    global $domainhosts;
    $marchent_zarinpal = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        "merchant_id" => $marchent_zarinpal,
        "currency" => "IRT",
        "amount" => $price,
        "callback_url" => "https://$domainhosts/payment/zarinpal.php",
        "description" => $order_id,
        "metadata" => array(
            "order_id" => $order_id
        )
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function createPayZarinpey($price, $order_id, $userId)
{
    global $domainhosts;

    $token = getPaySettingValue('token_zarinpey');
    if (empty($token) || $token === '0') {
        return [
            'success' => false,
            'message' => 'توکن زرین پی تنظیم نشده است.',
        ];
    }

    $normalizedPrice = filter_var($price, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);

    if ($normalizedPrice === false) {
        return [
            'success' => false,
            'message' => 'مبلغ تراکنش نامعتبر است.',
        ];
    }

    $amountRial = $normalizedPrice * 10;

    $callbackBase = function_exists('redfox_configured_base_url') ? redfox_configured_base_url() : '';
    if ($callbackBase === '') {
        return [
            'success' => false,
            'message' => 'دامنه امن برنامه تنظیم نشده است.',
        ];
    }

    $payload = [
        'amount' => $amountRial,
        'order_id' => $order_id,
        'callback_url' => rtrim($callbackBase, '/') . '/payment/ZarinPay/successful.php',
        'type' => 'card',
        'customer_user_id' => $userId,
        'description' => sprintf('پرداخت فاکتور %s', $order_id),
    ];

    $ch = curl_init('https://zarinpay.me/api/create-payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $errno = (int)curl_errno($ch);
        curl_close($ch);

        return [
            'success' => false,
            'message' => 'gateway_transport_error_' . $errno,
        ];
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    if (empty($result['success'])) {
        return [
            'success' => false,
            'message' => $result['message'] ?? 'خطا در ایجاد پرداخت',
            'http_code' => $httpCode,
        ];
    }

    $data = $result['data'] ?? [];
    $authority = $result['authority'] ?? ($data['authority'] ?? null);
    $paymentLink = $result['payment_link']
        ?? ($result['payment_url'] ?? ($data['payment_link'] ?? ($data['payment_url'] ?? null)));

    if (empty($authority) || empty($paymentLink)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    return [
        'success' => true,
        'authority' => $authority,
        'payment_link' => $paymentLink,
        'amount_rial' => $amountRial,
        'raw_response' => $result,
    ];
}
function createPayaqayepardakht($price, $order_id)
{
    global $domainhosts;
    $merchant_aqayepardakht = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://panel.aqayepardakht.ir/api/v2/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'pin' => $merchant_aqayepardakht,
        'amount' => $price,
        'callback' => "https://" . $domainhosts . "/payment/aqayepardakht.php",
        'invoice_id' => $order_id,
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function nmStockEnsureSchema()
{
    global $pdo; static $ready=false;if($ready)return true;if(!($pdo instanceof PDO))return false;
    try{rx_require_schema($pdo,['nm_config_stock','nm_stock_shelves','nm_config_stock_log','nm_stock_product_map'],['marzban_panel'=>['emergency_panel_status','national_net_status','emergency_source_panel','stock_source_panel'],'invoice'=>['source_panel_code']]);$ready=true;}catch(Throwable$e){error_log('nmStock schema migration required: '.redfox_exception_fingerprint($e));}
    return $ready;
}

if (!function_exists('nm_replyOrEdit')) {

function nm_replyOrEdit($chatId, $text, $keyboard = null, $parseMode = 'HTML')
{
    global $message_id, $callback_query_id;
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    if ($isCallback && function_exists('Editmessagetext')) {

        $isInlineKbd = false;
        if (is_string($keyboard) && $keyboard !== '') {
            $decoded = json_decode($keyboard, true);
            if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
        } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
            $isInlineKbd = true;
        } elseif ($keyboard === null) {
            $isInlineKbd = true;
        }
        if ($isInlineKbd) {
            try {
                $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);

                if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {
                    return true;
                }
                if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                    error_log('nm_replyOrEdit Editmessagetext not ok: ' . redfox_remote_error_summary($rx_edit_result));
                }
            } catch (Throwable $e) {
                error_log('nm_replyOrEdit Editmessagetext failed: ' . redfox_exception_fingerprint($e));
            }
        }
    }
    sendmessage($chatId, $text, $keyboard, $parseMode);
    return false;
}}

if (!function_exists('rx_resolveAgentGroup')) {
    function rx_resolveAgentGroup($raw, array $allowed)
    {
        $t = trim((string) $raw);
        $stripped = preg_replace('/^(?:\s|\x{FE0E}|\x{FE0F}|\x{200D}|\p{So}|\p{Cf})+/u', '', $t);
        $t = is_string($stripped) ? trim($stripped) : $t;
        $low = strtolower($t);

        $map = [
            'f' => 'f', 'کاربر عادی' => 'f', 'عادی' => 'f',
            'n' => 'n', 'نماینده عادی' => 'n',
            'n2' => 'n2', 'نماینده پیشرفته' => 'n2', 'نماینده با قابلیت های بیشتر' => 'n2',
        ];

        $token = null;
        if (isset($map[$low])) {
            $token = $map[$low];
        } elseif (isset($map[$t])) {
            $token = $map[$t];
        } else {
            $allWords = ['all', 'allusers', 'همه', 'همه گروه‌ها', 'همه گروه ها', 'همه کاربران'];
            if (in_array($low, $allWords, true) || in_array($t, $allWords, true)) {
                if (in_array('all', $allowed, true)) {
                    $token = 'all';
                } elseif (in_array('allusers', $allowed, true)) {
                    $token = 'allusers';
                }
            }
        }

        if ($token !== null && in_array($token, $allowed, true)) {
            return $token;
        }
        return null;
    }
}

if (!function_exists('rx_agentGroupKeyboard')) {
    function rx_agentGroupKeyboard($allowAll = false)
    {
        global $textbotlang;
        $back = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        $rows = [
            [['text' => '👤 کاربر عادی'], ['text' => '🤝 نماینده عادی']],
            [['text' => '💎 نماینده پیشرفته']],
        ];
        if ($allowAll) {
            $rows[1][] = ['text' => '📊 همه گروه‌ها'];
        }
        $rows[] = [['text' => $back]];

        return json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('nm_adminInstantReply')) {

function nm_adminInstantReply($chatId, $text, $keyboard = null, $parseMode = 'HTML')
{
    global $message_id, $callback_query_id;

    $alreadyHandled = !empty($message_id)
                      && isset($GLOBALS['rx_admin_instant_deleted'])
                      && $GLOBALS['rx_admin_instant_deleted'] === $message_id;

    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $isInlineKbd = false;
    if (is_string($keyboard) && $keyboard !== '') {
        $decoded = json_decode($keyboard, true);
        if (is_array($decoded) && isset($decoded['inline_keyboard'])) $isInlineKbd = true;
    } elseif (is_array($keyboard) && isset($keyboard['inline_keyboard'])) {
        $isInlineKbd = true;
    }
    $rx_edit_failed = false;

    if ($isCallback && $isInlineKbd && !$alreadyHandled && function_exists('Editmessagetext')) {
        try {
            $rx_edit_result = Editmessagetext($chatId, $message_id, $text, $keyboard, $parseMode);

            if (is_array($rx_edit_result) && !empty($rx_edit_result['ok'])) {

                $GLOBALS['rx_admin_instant_deleted'] = $message_id;

                if (!empty($callback_query_id) && function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
                return true;
            }
            $rx_edit_failed = true;
            if (is_array($rx_edit_result) && isset($rx_edit_result['description'])) {
                $rx_edit_desc = (string) $rx_edit_result['description'];
                $rx_edit_benign = ['message to edit not found','message to delete not found','message is not modified','query is too old','MESSAGE_ID_INVALID'];
                $rx_edit_skip_log = false;
                foreach ($rx_edit_benign as $rx_edit_n) {
                    if (stripos($rx_edit_desc, $rx_edit_n) !== false) { $rx_edit_skip_log = true; break; }
                }
                if (!$rx_edit_skip_log) {
                    error_log('nm_adminInstantReply Editmessagetext not ok: ' . $rx_edit_desc);
                }
            }
        } catch (Throwable $e) {
            $rx_edit_failed = true;
            error_log('nm_adminInstantReply Editmessagetext failed: ' . redfox_exception_fingerprint($e));
        }
    }

    if ($rx_edit_failed) {
        if (!empty($callback_query_id) && function_exists('telegram')) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (Throwable $e) {}
        }
        return sendmessage($chatId, $text, $keyboard, $parseMode);
    }

    if ($isCallback) {
        if (!empty($message_id) && !$alreadyHandled && function_exists('deletemessage')) {
            try { @deletemessage($chatId, $message_id); } catch (Throwable $e) {}
            $GLOBALS['rx_admin_instant_deleted'] = $message_id;
        }
        if (!empty($callback_query_id) && function_exists('telegram')) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 1,
                ]);
            } catch (Throwable $e) {}
        }
        return sendmessage($chatId, $text, $keyboard, $parseMode);
    }

    return sendmessage($chatId, $text, $keyboard, $parseMode);
}}

function nmStockTierFromVolume($volume)
{
    $volume = (int)ceil((float)$volume);
    if ($volume <= 0) return 'auto';
    if ($volume <= 10) return '10-10';
    $lower = (int)(floor(($volume - 1) / 10) * 10);
    return $lower . '-' . ($lower + 10);
}

function nmStockDetectFormat($content)
{
    $content = trim((string)$content);
    if (preg_match('/^https?:\/\//i', $content)) return 'subscription';
    if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic|wireguard):\/\//i', $content)) return 'single';
    if (stripos($content, '[Interface]') !== false || stripos($content, 'PrivateKey') !== false) return 'wireguard';
    return 'text';
}

function nmStockDetectTier($content, $fallbackVolume = null)
{
    $content = (string)$content;
    if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*(?:gb|g|گیگ|گیگابایت)(?:[^0-9]|$)/iu', $content, $m)) {
        return nmStockTierFromVolume((int)$m[1]);
    }
    if (preg_match('/(?:^|[^0-9])([1-9][0-9]{0,2})\s*[-_]\s*([1-9][0-9]{0,2})(?:[^0-9]|$)/u', $content, $m)) {
        return ((int)$m[1]) . '-' . ((int)$m[2]);
    }
    return nmStockTierFromVolume($fallbackVolume);
}

function nmStockLog($stockId, $userId, $invoiceId, $action, $payload = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO nm_config_stock_log (stock_id,id_user,id_invoice,action,payload,created_at) VALUES (:stock_id,:id_user,:id_invoice,:action,:payload,:created_at)");
        $payloadText = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([':stock_id' => $stockId, ':id_user' => (string)$userId, ':id_invoice' => (string)$invoiceId, ':action' => (string)$action, ':payload' => $payloadText, ':created_at' => time()]);
    } catch (Throwable $e) { error_log('nmStockLog failed: ' . redfox_exception_fingerprint($e)); }
}

function nmStockImportLinkOnly($panelCode, $productCode, $rawText, $fallbackVolume = null, $shelfId = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return ['ok' => 0, 'duplicate' => 0, 'bad' => 0];
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $ok = $duplicate = $bad = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string)$rawText) as $line) {
        $link = trim($line);
        if ($link === '' || mb_substr($link, 0, 1, 'UTF-8') === '#') continue;
        if (!preg_match('#^https?://\S+$#i', $link)) { $bad++; continue; }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO nm_config_stock (shelf_id,codepanel,codeproduct,tier,format,content,sub_link,status,created_at) VALUES (:shelf_id,:codepanel,:codeproduct,:tier,:format,:content,:sub_link,'active',:created_at)");
            $stmt->execute([':shelf_id' => $shelfId, ':codepanel' => $panelCode, ':codeproduct' => $productCode, ':tier' => nmStockDetectTier($link, $fallbackVolume), ':format' => 'subscription', ':content' => $link, ':sub_link' => $link, ':created_at' => time()]);
            $stmt->rowCount() > 0 ? $ok++ : $duplicate++;
        } catch (Throwable $e) { $bad++; error_log('nmStockImportLinkOnly item failed: ' . redfox_exception_fingerprint($e)); }
    }
    return ['ok' => $ok, 'duplicate' => $duplicate, 'bad' => $bad];
}

function nmStockImportBatch($panelCode, $productCode, $rawText, $fallbackVolume = null, $shelfId = null, $subLinks = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return ['ok' => 0, 'duplicate' => 0, 'bad' => 0];
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $ok = $duplicate = $bad = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string)$rawText) as $line) {
        $content = trim($line);
        if ($content === '' || mb_substr($content, 0, 1, 'UTF-8') === '#') continue;
        if (mb_strlen($content, 'UTF-8') < 8) { $bad++; continue; }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO nm_config_stock (shelf_id,codepanel,codeproduct,tier,format,content,sub_link,status,created_at) VALUES (:shelf_id,:codepanel,:codeproduct,:tier,:format,:content,:sub_link,'active',:created_at)");
            $stmt->execute([':shelf_id' => $shelfId, ':codepanel' => $panelCode, ':codeproduct' => $productCode, ':tier' => nmStockDetectTier($content, $fallbackVolume), ':format' => nmStockDetectFormat($content), ':content' => $content, ':sub_link' => is_string($subLinks) ? $subLinks : null, ':created_at' => time()]);
            $stmt->rowCount() > 0 ? $ok++ : $duplicate++;
        } catch (Throwable $e) { $bad++; error_log('nmStockImportBatch item failed: ' . redfox_exception_fingerprint($e)); }
    }
    return ['ok' => $ok, 'duplicate' => $duplicate, 'bad' => $bad];
}

function nmStockCounts($panelCode = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return [];
    $where = "status = 'active'";
    $params = [];
    if ($panelCode !== null && trim((string)$panelCode) !== '') { $where .= " AND (codepanel = :codepanel OR codepanel = 'auto')"; $params[':codepanel'] = (string)$panelCode; }
    $stmt = $pdo->prepare("SELECT tier, format, COUNT(*) AS cnt FROM nm_config_stock WHERE $where GROUP BY tier, format ORDER BY tier, format");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nmStockReserveOne($panelCode, $productCode, $volume, $userId, $invoiceId, $mode = 'fallback')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $panelCode = trim((string)$panelCode) !== '' ? trim((string)$panelCode) : 'auto';
    $productCode = trim((string)$productCode) !== '' ? trim((string)$productCode) : 'auto';
    $tier = nmStockTierFromVolume($volume);
    try {
        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s LEFT JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND s.tier=:tier AND (s.codepanel=:codepanel_where OR s.codepanel='auto') AND (s.codeproduct=:codeproduct_where OR s.codeproduct='auto') AND (sh.id IS NULL OR sh.status='active') ORDER BY (s.codeproduct=:codeproduct_order) DESC, (s.codepanel=:codepanel_order) DESC, s.shelf_id IS NULL ASC, s.id ASC LIMIT 1");
        $stmt->execute([
            ':tier' => $tier,
            ':codepanel_where' => $panelCode,
            ':codepanel_order' => $panelCode,
            ':codeproduct_where' => $productCode,
            ':codeproduct_order' => $productCode,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
        $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
        if ($upd->rowCount() < 1) return false;
        nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveOne failed: ' . redfox_exception_fingerprint($e)); return false; }
}

function nmStockReserveByShelfMatch(array $panel, array $product, $userId, $invoiceId, $mode = 'fallback')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = function_exists('nmPanelResolveStockCode') ? nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
        return trim((string)$v) !== '';
    })));
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = nmNormalizeText($product['name_product'] ?? '');
    $volume = (float)($product['Volume_constraint'] ?? 0);
    $days = (int)($product['Service_time'] ?? 0);
    $tier = nmStockTierFromVolume($volume);

    $panelWhereSql = [];
    $panelOrderSql = [];
    $params = [
        ':product_code_stock_where' => $productCode,
        ':product_code_shelf_where' => $productCode,
        ':product_code_stock_order' => $productCode,
        ':product_code_shelf_order' => $productCode,
        ':product_name_where' => $productName,
        ':product_name_order' => $productName,
        ':volume_where' => $volume,
        ':volume_order' => $volume,
        ':days_where' => $days,
        ':days_order' => $days,
    ];
    foreach ($panelCandidates as $i => $candidate) {
        $whereKey = ':panel_where_' . $i;
        $orderKey = ':panel_order_' . $i;
        $panelWhereSql[] = $whereKey;
        $panelOrderSql[] = $orderKey;
        $params[$whereKey] = (string)$candidate;
        $params[$orderKey] = (string)$candidate;
    }

    try {

        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND s.codepanel IN (" . implode(',', $panelWhereSql) . ") AND (s.codeproduct=:product_code_stock_where OR sh.codeproduct=:product_code_shelf_where OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY (s.codeproduct=:product_code_stock_order OR sh.codeproduct=:product_code_shelf_order) DESC, (sh.product_name=:product_name_order) DESC, (ABS(sh.volume_gb - :volume_order) < 0.001 AND sh.service_days=:days_order) DESC, FIELD(s.codepanel, " . implode(',', $panelOrderSql) . "), s.id ASC LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
        $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
        if ($upd->rowCount() < 1) return false;
        nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'tier' => $tier, 'product' => $productCode, 'shelf_match' => true]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveByShelfMatch failed: ' . redfox_exception_fingerprint($e)); return false; }
}

function nmStockMarkDelivered($stockId, $userId, $invoiceId, $payload = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return;
    try {
        $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='delivered', assigned_user=COALESCE(NULLIF(assigned_user,''), :user), assigned_invoice=COALESCE(NULLIF(assigned_invoice,''), :invoice), delivered_at=:delivered_at WHERE id=:id AND status IN ('active','reserved','delivered')");
        $stmt->execute([
            ':user' => (string)$userId,
            ':invoice' => (string)$invoiceId,
            ':delivered_at' => time(),
            ':id' => $stockId,
        ]);
        if ($stmt->rowCount() < 1) {
            $fallback = $pdo->prepare("UPDATE nm_config_stock SET status='disabled', assigned_user=COALESCE(NULLIF(assigned_user,''), :user), assigned_invoice=COALESCE(NULLIF(assigned_invoice,''), :invoice), delivered_at=:delivered_at WHERE id=:id AND status='active'");
            $fallback->execute([
                ':user' => (string)$userId,
                ':invoice' => (string)$invoiceId,
                ':delivered_at' => time(),
                ':id' => $stockId,
            ]);
        }
        nmStockLog($stockId, $userId, $invoiceId, 'delivered', $payload);
    } catch (Throwable $e) {
        error_log('nmStockMarkDelivered failed: ' . redfox_exception_fingerprint($e));
        try {
            $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled', assigned_user=:user, assigned_invoice=:invoice, delivered_at=:delivered_at WHERE id=:id AND status='active'");
            $stmt->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':delivered_at' => time(), ':id' => $stockId]);
            nmStockLog($stockId, $userId, $invoiceId, 'delivered_disabled_fallback', $payload);
        } catch (Throwable $inner) {
            error_log('nmStockMarkDelivered fallback failed: ' . redfox_exception_fingerprint($inner));
        }
    }
}

function nmStockProductForInvoice(array $invoice)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product=:name AND (Location=:loc OR Location='/all') LIMIT 1");
        $stmt->execute([':name' => $invoice['name_product'] ?? '', ':loc' => $invoice['Service_location'] ?? '']);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) return $product;
    } catch (Throwable $e) { error_log('nmStockProductForInvoice failed: ' . redfox_exception_fingerprint($e)); }
    return ['code_product' => 'auto', 'name_product' => $invoice['name_product'] ?? '', 'Volume_constraint' => $invoice['Volume'] ?? 0, 'Service_time' => $invoice['Service_time'] ?? 0, 'price_product' => $invoice['price_product'] ?? 0];
}

function nmStockPanelForInvoice(array $invoice)
{
    $sourceCode = trim((string)($invoice['source_panel_code'] ?? ''));
    if ($sourceCode !== '') {
        try { $panel = select('marzban_panel', '*', 'code_panel', $sourceCode, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice source lookup failed: ' . redfox_exception_fingerprint($e)); }
    }
    $location = trim((string)($invoice['Service_location'] ?? ''));
    if ($location !== '') {
        try { $panel = select('marzban_panel', '*', 'name_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { error_log('nmStockPanelForInvoice name lookup failed: ' . redfox_exception_fingerprint($e)); }
        try { $panel = select('marzban_panel', '*', 'code_panel', $location, 'select'); if (is_array($panel)) return $panel; } catch (Throwable $e) { }
    }
    return false;
}

function nmStockHasAvailableForProduct(array $panel, array $product)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = nmPanelResolveStockCode($panel);
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = function_exists('nmNormalizeText') ? nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
    $volume = (float)($product['Volume_constraint'] ?? 0);
    $days = (int)($product['Service_time'] ?? 0);
    $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function($v){ return trim((string)$v) !== ''; })));
    $panelParamsS = [];
    $panelParamsSrc = [];
    $panelParamsSt = [];

    $params = [
        ':code_stock' => $productCode,
        ':code_shelf' => $productCode,
        ':product_name_where' => $productName,
        ':vol' => $volume,
        ':days' => $days,
    ];
    foreach ($panelCandidates as $idx => $candidate) {
        $kS = ':panels_' . $idx;
        $kSrc = ':panelsrc_' . $idx;
        $kSt = ':panelst_' . $idx;
        $panelParamsS[] = $kS;
        $panelParamsSrc[] = $kSrc;
        $panelParamsSt[] = $kSt;
        $params[$kS] = (string)$candidate;
        $params[$kSrc] = (string)$candidate;
        $params[$kSt] = (string)$candidate;
    }
    try {
        $sql = "SELECT COUNT(*) FROM nm_config_stock s LEFT JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND (s.codepanel IN (" . implode(',', $panelParamsS) . ") OR sh.source_codepanel IN (" . implode(',', $panelParamsSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelParamsSt) . ")) AND (s.codeproduct=:code_stock OR s.codeproduct='auto' OR sh.codeproduct=:code_shelf OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :vol) < 0.001 AND sh.service_days=:days)) AND (sh.id IS NULL OR sh.status='active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { error_log('nmStockHasAvailableForProduct failed: ' . redfox_exception_fingerprint($e)); return false; }
}
/* ---- business_logic_2.php ---- */
function nmStockProductsForExtend(array $panel, $agent = 'all', $onlyAvailable = false)
{
    global $pdo;
    $rows = [];
    $params = [':loc' => $panel['name_panel'] ?? ''];
    $sql = "SELECT * FROM product WHERE (Location=:loc OR Location='/all')";
    $agent = trim((string)$agent);
    if ($agent !== '' && $agent !== 'all') { $sql .= " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL)"; $params[':agent'] = $agent; }
    $sql .= " ORDER BY CAST(price_product AS UNSIGNED) ASC, id ASC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($onlyAvailable && !nmStockHasAvailableForProduct($panel, $row)) continue;
            $rows[] = $row;
        }
    } catch (Throwable $e) { error_log('nmStockProductsForExtend failed: ' . redfox_exception_fingerprint($e)); }
    return $rows;
}

function nmStockResolveProductToken($token, array $panel, $agent = 'all')
{
    global $pdo;
    $token = trim((string)$token);
    $params = [':loc' => $panel['name_panel'] ?? ''];
    if (preg_match('/^pid_([0-9]+)$/', $token, $m)) { $sql = "SELECT * FROM product WHERE id=:id AND (Location=:loc OR Location='/all') LIMIT 1"; $params[':id'] = (int)$m[1]; }
    else { $sql = "SELECT * FROM product WHERE code_product=:code AND (Location=:loc OR Location='/all') LIMIT 1"; $params[':code'] = $token; }
    $agent = trim((string)$agent);
    if ($agent !== '' && $agent !== 'all') { $sql = str_replace(' LIMIT 1', " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL) LIMIT 1", $sql); $params[':agent'] = $agent; }
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: false; }
    catch (Throwable $e) { error_log('nmStockResolveProductToken failed: ' . redfox_exception_fingerprint($e)); return false; }
}

function nmStockExtendProductKeyboard(array $invoice, array $userRow, array $panel)
{
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($invoice['id_invoice'] ?? ''));
    $isNational = nmPanelNationalEnabled($panel);
    $products = nmStockProductsForExtend($panel, $userRow['agent'] ?? 'all', $isNational);
    if (!$products) return false;
    $rows = [];
    foreach ($products as $product) {
        $code = trim((string)($product['code_product'] ?? ''));
        $token = ($code !== '' && strlen('nmstocksel_' . $code . '_' . $invoiceId) <= 64) ? $code : 'pid_' . (string)($product['id'] ?? '0');
        $name = trim((string)($product['name_product'] ?? 'محصول'));
        $price = number_format((float)($product['price_product'] ?? 0));
        $vol = trim((string)($product['Volume_constraint'] ?? ''));
        $days = trim((string)($product['Service_time'] ?? ''));
        $label = $name . " - {$price} تومان";
        if ($vol !== '' || $days !== '') $label .= " ({$vol}GB / {$days} روز)";
        $rows[] = [['text' => $label, 'callback_data' => 'nmstocksel_' . $token . '_' . $invoiceId]];
    }
    $rows[] = [['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmStockSafeUsername($userId, $current = '')
{
    $base = preg_replace('/[^A-Za-z0-9_]/', '', (string)$current);
    if ($base === '' || strlen($base) < 3) $base = preg_replace('/[^A-Za-z0-9_]/', '', (string)$userId) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    if (strlen($base) > 28) $base = substr($base, 0, 28);
    if (strlen($base) < 3) $base = 'u' . substr(bin2hex(random_bytes(6)), 0, 10);
    return $base;
}

function nmStockConvertInvoiceToPanelService($chatId, array $userRow, array $invoice, array $panel, array $product)
{
    global $ManagePanel, $setting, $errorreport, $keyboard;
    $price = (float)($product['price_product'] ?? 0);
    if ((float)($userRow['Balance'] ?? 0) < $price && ($userRow['agent'] ?? '') !== 'n2') { sendmessage($chatId, '❌ موجودی کیف پول برای تمدید کافی نیست.', null, 'HTML'); return false; }
    $username = nmStockSafeUsername($chatId, $invoice['username'] ?? '');
    try { $check = $ManagePanel->DataUser($panel['name_panel'], $username); if (is_array($check) && isset($check['username'])) $username = nmStockSafeUsername($chatId, $chatId . '_' . substr(bin2hex(random_bytes(4)), 0, 8)); } catch (Throwable $e) { }
    $days = (int)($product['Service_time'] ?? 0);
    $expire = $days > 0 ? strtotime('+' . $days . ' days') : 0;
    $datac = ['expire' => $expire, 'data_limit' => (float)($product['Volume_constraint'] ?? 0) * pow(1024, 3), 'from_id' => $chatId, 'username' => '', 'type' => 'buy'];
    $dataoutput = $ManagePanel->createUser($panel['name_panel'], $product['code_product'], $username, $datac);
    if (!is_array($dataoutput) || empty($dataoutput['username'])) {
        $msg = redfox_remote_error_summary($dataoutput);
        error_log('nmStockConvertInvoiceToPanelService createUser failed: ' . $msg);
        sendmessage($chatId, '❌ تمدید از پنل اصلی انجام نشد. لطفاً گزارش خطا را بررسی کنید.', null, 'HTML');
        if (!empty($setting['Channel_Report'])) telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $errorreport ?? null, 'text' => "خطا در تبدیل سرویس انبار به پنل اصلی
پنل: {$panel['name_panel']}
کاربر: {$chatId}
خطا: {$msg}", 'parse_mode' => 'HTML']);
        return false;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegBl = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
        balance_atomic_charge($chatId, (float)$price, $__allowNegBl);
    } else {
        update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $price, 'id', $chatId);
    }
    update('invoice', 'username', $dataoutput['username'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Service_location', $panel['name_panel'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'name_product', $product['name_product'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Volume', $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Service_time', $product['Service_time'], 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
    update('invoice', 'time_sell', time(), 'id_invoice', $invoice['id_invoice']);
    try { update('invoice', 'user_info', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
    try { update('invoice', 'source_panel_code', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
    $subLink = $dataoutput['subscription_url'] ?? '';
    $configs = $dataoutput['configs'] ?? [];


    $caption = "✅ وضعیت نت ملی خاموش است؛ سرویس شما از پنل اصلی تمدید و جایگزین شد.

👤 نام سرویس: <code>{$dataoutput['username']}</code>
🛍 محصول: {$product['name_product']}
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$product['Volume_constraint']} گیگ";
    if (trim((string)$subLink) !== '') {
        $caption .= "\n\n🔗 لینک اشتراک:\n<code>" . htmlspecialchars((string)$subLink, ENT_QUOTES, 'UTF-8') . "</code>";
    }
    if (is_array($configs) && count($configs) > 0) {
        $cfgClean = array_values(array_filter($configs, static function ($v) { return is_string($v) && trim($v) !== ''; }));
        if (!empty($cfgClean)) {
            $caption .= "\n\n⚙️ کانفیگ‌ها:";
            foreach ($cfgClean as $idx => $cfg) {
                $caption .= "\n\n<b>" . ($idx + 1) . ".</b>\n<code>" . htmlspecialchars($cfg, ENT_QUOTES, 'UTF-8') . "</code>";
            }
        }
    }
    if (function_exists('sendMessageService')) sendMessageService($panel, $configs, $subLink, $dataoutput['username'], null, $caption, $invoice['id_invoice'], $chatId); else sendmessage($chatId, $caption, $keyboard ?? null, 'HTML');

    nmStockNotifyExtend($chatId, $userRow, $invoice, $product, $panel, 'panel', $dataoutput['username'] ?? '', $price);
    return true;
}

if (!function_exists('nmStockNotifyExtend')) {


function nmStockNotifyExtend($chatId, array $userRow, array $invoice, array $product, array $panel, $mode = 'stock', $newUsername = '', $price = 0, $action = 'extend')
{
    global $admin_ids, $setting, $buyreport, $otherreport;
    try {
        $modeLabel = $mode === 'panel' ? '📡 از پنل اصلی' : ($mode === 'emergency' ? '🚨 از پنل اضطراری' : '📦 از انبار شبکه‌ملی');
        $usernameTg = trim((string)($userRow['username'] ?? ''));
        if ($usernameTg === '') $usernameTg = '---';
        $price = (float)$price;
        $title = ($action === 'buy') ? '🛒 خرید سرویس انجام شد' : '♻️ تمدید سرویس انجام شد';
        $msg = "{$title} ({$modeLabel})\n\n"
            . "👤 آیدی کاربر: <code>{$chatId}</code>\n"
            . "🔖 یوزرنیم تلگرام: @" . htmlspecialchars($usernameTg, ENT_QUOTES, 'UTF-8') . "\n"
            . "🪪 یوزرنیم سرویس: <code>" . htmlspecialchars((string)($newUsername !== '' ? $newUsername : ($invoice['username'] ?? '')), ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "🛍 محصول: " . htmlspecialchars((string)($product['name_product'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n"
            . "📍 پنل: " . htmlspecialchars((string)($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n"
            . "🗜 حجم: " . htmlspecialchars((string)($product['Volume_constraint'] ?? ''), ENT_QUOTES, 'UTF-8') . " گیگ\n"
            . "⏳ مدت: " . htmlspecialchars((string)($product['Service_time'] ?? ''), ENT_QUOTES, 'UTF-8') . " روز\n"
            . "💰 مبلغ: " . number_format($price) . " تومان\n"
            . "🧾 کد سرویس: <code>" . htmlspecialchars((string)($invoice['id_invoice'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code>";
        if (!empty($setting['Channel_Report'])) {
            $payload = ['chat_id' => $setting['Channel_Report'], 'text' => $msg, 'parse_mode' => 'HTML'];

            $threadId = !empty($buyreport) ? $buyreport : (!empty($otherreport) ? $otherreport : null);
            if ($threadId) $payload['message_thread_id'] = $threadId;
            telegram('sendmessage', $payload);
        }
        if (is_array($admin_ids)) {
            foreach ($admin_ids as $adminId) {
                if ((string)$adminId === (string)$chatId) continue;
                telegram('sendmessage', ['chat_id' => $adminId, 'text' => $msg, 'parse_mode' => 'HTML']);
            }
        }
    } catch (Throwable $e) { error_log('nmStockNotifyExtend failed: ' . redfox_exception_fingerprint($e)); }
}}


if (!function_exists('nmJalaliDate')) {
function nmJalaliDate($format='Y/m/d',$ts=null){
    if($ts===null||$ts===''||$ts===false)$ts=time();
    if(!is_numeric($ts)){ $x=strtotime((string)$ts); $ts=$x!==false?$x:time(); }
    if(function_exists('jdate')){ try{return jdate($format,(int)$ts);}catch(Throwable $e){} }
    return date($format,(int)$ts);
}}
if (!function_exists('nmInvoiceTimestamp')) {
function nmInvoiceTimestamp(array $invoice){
    $raw=$invoice['time_sell']??null;
    if(is_numeric($raw)) return (int)$raw;
    if(is_string($raw)&&trim($raw)!==''){ $x=strtotime($raw); if($x!==false) return $x; }
    return time();
}}
if (!function_exists('nmStockForInvoice')) {
function nmStockForInvoice(array $invoice){
    global $pdo;
    if(!nmStockEnsureSchema()) return false;
    $iid=trim((string)($invoice['id_invoice']??''));
    $content=trim((string)($invoice['user_info']??''));
    $sourcePanel=trim((string)($invoice['source_panel_code']??''));


    if($content==='' && $sourcePanel==='') return false;
    try{
        if($iid!=='' && ($content!=='' || $sourcePanel!=='')){
            $st=$pdo->prepare("SELECT * FROM nm_config_stock WHERE assigned_invoice=:i ORDER BY CASE status WHEN 'delivered' THEN 0 WHEN 'reserved' THEN 1 WHEN 'disabled' THEN 2 ELSE 3 END, COALESCE(delivered_at,reserved_at,created_at,0) DESC, id DESC LIMIT 1");
            $st->execute([':i'=>$iid]); $r=$st->fetch(PDO::FETCH_ASSOC); if($r) return $r;
        }
        if($content!==''){
            $st=$pdo->prepare("SELECT * FROM nm_config_stock WHERE content=:c ORDER BY CASE status WHEN 'delivered' THEN 0 WHEN 'reserved' THEN 1 WHEN 'disabled' THEN 2 ELSE 3 END, COALESCE(delivered_at,reserved_at,created_at,0) DESC, id DESC LIMIT 1");
            $st->execute([':c'=>$content]); $r=$st->fetch(PDO::FETCH_ASSOC); if($r) return $r;
        }
    }catch(Throwable $e){ error_log('nmStockForInvoice failed: '.redfox_exception_fingerprint($e)); }
    if($content!=='' && $sourcePanel!==''){
        return ['id'=>null,'content'=>$content,'sub_link'=>'','tier'=>nmStockTierFromVolume($invoice['Volume']??0),'format'=>nmStockDetectFormat($content),'status'=>'delivered','assigned_invoice'=>$iid];
    }
    return false;
}}
if (!function_exists('nmStockSubLinkForInvoice')) {
function nmStockSubLinkForInvoice(array $invoice,array $stock=null){
    $stock=$stock?:nmStockForInvoice($invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=is_array($stock)?trim((string)($stock['sub_link']??'')):'';
    if($sub!=='') return $sub;
    return preg_match('/^https?:\/\//i',$content)?$content:'';
}}
if (!function_exists('nmStockInvoiceText')) {
function nmStockInvoiceText(array $invoice,array $stock=null){
    $stock=$stock?:nmStockForInvoice($invoice);
    $content=is_array($stock)?trim((string)($stock['content']??'')):trim((string)($invoice['user_info']??''));
    $sub=nmStockSubLinkForInvoice($invoice,is_array($stock)?$stock:null);
    $startTs=nmInvoiceTimestamp($invoice); $days=(int)($invoice['Service_time']??0);
    $vol=trim((string)($invoice['Volume']??'')); if($vol!==''&&is_numeric($vol))$vol=rtrim(rtrim(number_format((float)$vol,2,'.',''),'0'),'.').'GB';


    $shelfName='';
    if(is_array($stock) && !empty($stock['shelf_id']) && function_exists('nmStockShelfById')){
        $shelf=nmStockShelfById($stock['shelf_id']);
        if(is_array($shelf)) $shelfName=(string)($shelf['name']??'');
    }
    $shelfNameSafe=htmlspecialchars($shelfName!==''?$shelfName:'انبار شبکه‌ملی',ENT_QUOTES,'UTF-8');
    $txt="✅ وضعیت نت ملی فعال است؛ این سرویس از انبار شبکه‌ملی تحویل شده است.\n\n";
    $txt.="👤 نام کاربری: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="🛍 محصول: <code>".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📍 پنل: <code>".htmlspecialchars((string)($invoice['Service_location']??''),ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📦 انبار: <code>{$shelfNameSafe}</code>\n";
    if($vol!=='')$txt.="🗜 حجم سرویس: <code>".htmlspecialchars($vol,ENT_QUOTES,'UTF-8')."</code>\n";
    $txt.="📅 شروع: <code>".nmJalaliDate('Y/m/d',$startTs)."</code>\n";
    $txt.="⏳ پایان: <code>".($days>0?nmJalaliDate('Y/m/d',$startTs+$days*86400):'نامحدود')."</code>\n";
    $txt.="📌 وضعیت: <code>".htmlspecialchars((string)($invoice['Status']??'active'),ENT_QUOTES,'UTF-8')."</code>\n\n";
    $txt.="⚠️ در حالت انبار، حجم باقی‌مانده از داخل ربات نمایش داده نمی‌شود؛ حجم را از لینک اشتراک مشاهده کنید.\n\n";
    if($content!=='')$txt.="🔗 کانفیگ/اشتراک:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>";
    if($sub!==''&&$sub!==$content)$txt.="\n\n🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>";
    return $txt;
}}
if (!function_exists('nmMaybeShowStockInvoiceDetails')) {
function nmMaybeShowStockInvoiceDetails($chatId,$messageId,$invoice){
    if(!is_array($invoice))return false; $stock=nmStockForInvoice($invoice); if(!$stock)return false;
    $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=nmStockSubLinkForInvoice($invoice,$stock);
    $kbd=nmStockDeliveryKeyboard($content,$invoice['id_invoice']??'',$sub); $txt=nmStockInvoiceText($invoice,$stock);
    try{ if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML'); }catch(Throwable $e){ sendmessage($chatId,$txt,$kbd,'HTML'); }
    return true;
}}
if (!function_exists('nmSendLongStockMessage')) {
function nmSendLongStockMessage($chatId,$text,$keyboard=null){
    $text=(string)$text; if(mb_strlen($text,'UTF-8')<=3900){sendmessage($chatId,$text,$keyboard,'HTML');return;}
    foreach(str_split($text,3800) as $part) sendmessage($chatId,$part,null,'HTML');
}}
if (!function_exists('nmMaybeHandleStockCallback')) {
function nmMaybeHandleStockCallback($datain,$chatId,$messageId=null,$callbackQueryId=null){
    global $user, $keyboard;
    $datain=(string)$datain; $action=null; $iid=null; $token=null;
    if(preg_match('/^nmstockcfg_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='config'; $iid=$m[1]; }
    elseif(preg_match('/^nmstocksub_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='sub'; $iid=$m[1]; }
    elseif(preg_match('/^nmstockextend_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='extend'; $iid=$m[1]; }
    elseif(preg_match('/^nmstockrefund_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='refund'; $iid=$m[1]; }
    elseif(preg_match('/^nmstocksel_([A-Za-z0-9_\-]+)_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='select_extend'; $token=$m[1]; $iid=$m[2]; }
    elseif(preg_match('/^nmstockok_([A-Za-z0-9_\-]+)_([A-Za-z0-9_\-]+)/',$datain,$m)){ $action='confirm_extend'; $token=$m[1]; $iid=$m[2]; }
    elseif(preg_match('/^configget_([A-Za-z0-9_\-]+)_(1520|sub)$/',$datain,$m)){ $action=$m[2]==='sub'?'sub':'config'; $iid=$m[1]; }
    else return false;

    try{$invoice=select('invoice','*','id_invoice',$iid,'select');}catch(Throwable $e){$invoice=false;}
    if(!is_array($invoice)||(string)($invoice['id_user']??'')!==(string)$chatId){
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'سرویس مورد نظر یافت نشد.','show_alert'=>true,'cache_time'=>3]);
        return true;
    }
    $stock=nmStockForInvoice($invoice);

    if($action==='config' || $action==='sub'){
        if(!$stock)return false;
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'درخواست دریافت شد.','show_alert'=>false,'cache_time'=>2]);
        $content=trim((string)($stock['content']??($invoice['user_info']??''))); $sub=nmStockSubLinkForInvoice($invoice,$stock);
        if($action==='sub'){
            if($sub===''){
                sendmessage($chatId,'⚠️ برای این سرویس لینک اشتراک جداگانه ثبت نشده است؛ کانفیگ تکی ارسال می‌شود.',null,'HTML');
                nmStockSendQr($chatId, $content, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>", '📥 کیو‌آر کد کانفیگ');
            } else {
                nmStockSendQr($chatId, $sub, "🔗 لینک اشتراک:\n<code>".htmlspecialchars($sub,ENT_QUOTES,'UTF-8')."</code>", '🔗 کیو‌آر کد لینک اشتراک');
            }
        } else {
            // "📥 دریافت کانفیگ": deliver the single config together with its QR. (Image-1 request.)
            nmStockSendQr($chatId, $content, "📥 کانفیگ تکی:\n<code>".htmlspecialchars($content,ENT_QUOTES,'UTF-8')."</code>", '📥 کیو‌آر کد کانفیگ');
        }
        return true;
    }

    if($action==='refund'){

        if(!$stock || (string)($invoice['Status']??'')==='removebyuser' || (string)($invoice['Status']??'')==='nm_refund_pending' || (string)($invoice['Status']??'')==='removedbyadmin'){
            sendmessage($chatId,'❌ امکان بازگشت وجه برای این سرویس وجود ندارد یا قبلاً درخواست داده‌اید.',$keyboard??null,'HTML');
            if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'قابل انجام نیست','show_alert'=>false,'cache_time'=>2]);
            return true;
        }
        $defaultRefund=(float)($invoice['price_product']??0);

        update('invoice','Status','nm_refund_pending','id_invoice',$invoice['id_invoice']);
        try{ nmStockLog($stock['id']??null,$chatId,$invoice['id_invoice'],'refund_request_by_user',['default_refund'=>$defaultRefund]); }catch(Throwable $e){}
        sendmessage($chatId,'⏳ درخواست بازگشت وجه شما برای ادمین ارسال شد. پس از بررسی و تأیید ادمین، مبلغ به کیف‌پول شما اضافه خواهد شد.',$keyboard??null,'HTML');

        global $admin_ids, $username, $setting, $otherreport;
        $usernameAdminMsg = isset($username) ? $username : '';
        $invoiceId = (string)($invoice['id_invoice']??'');
        $adminText = "📌 درخواست بازگشت وجه سرویس انبار شبکه ملی\n\n".
            "👤 آیدی عددی کاربر: <code>{$chatId}</code>\n".
            "🔖 یوزرنیم تلگرام: @".htmlspecialchars((string)$usernameAdminMsg,ENT_QUOTES,'UTF-8')."\n".
            "🪪 یوزرنیم سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>\n".
            "🛍 محصول: ".htmlspecialchars((string)($invoice['name_product']??''),ENT_QUOTES,'UTF-8')."\n".
            "🔋 حجم: ".htmlspecialchars((string)($invoice['Volume']??0),ENT_QUOTES,'UTF-8')." گیگ\n".
            "⏳ مدت: ".htmlspecialchars((string)($invoice['Service_time']??0),ENT_QUOTES,'UTF-8')." روز\n".
            "💰 مبلغ پرداختی: ".number_format($defaultRefund)." تومان\n".
            "🧾 کد سرویس: <code>{$invoiceId}</code>\n\n".
            "ادمین گرامی: می‌توانید مبلغ پیش‌فرض را تأیید کنید یا با زدن «✏️ ورود مبلغ دلخواه» مبلغ دیگری وارد نمایید.";
        $adminKbd = json_encode(['inline_keyboard'=>[
            [
                ['text'=>'✅ تأیید مبلغ پیش‌فرض','callback_data'=>'nmrefokdef_'.$invoiceId],
                ['text'=>'✏️ ورود مبلغ دلخواه','callback_data'=>'nmrefcustom_'.$invoiceId],
            ],
            [
                ['text'=>'❌ رد درخواست','callback_data'=>'nmrefreject_'.$invoiceId],
            ],
        ]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(is_array($admin_ids)) foreach($admin_ids as $admin) sendmessage($admin,$adminText,$adminKbd,'HTML');
        if(!empty($setting['Channel_Report'])){
            $payload=['chat_id'=>$setting['Channel_Report'],'text'=>$adminText,'parse_mode'=>'HTML','reply_markup'=>$adminKbd];
            if(!empty($otherreport)) $payload['message_thread_id']=$otherreport;
            telegram('sendmessage',$payload);
        }
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'درخواست ارسال شد','show_alert'=>false,'cache_time'=>2]);
        return true;
    }

    $panel=nmStockPanelForInvoice($invoice);
    if(!is_array($panel)){ sendmessage($chatId,'❌ پنل اصلی این سرویس پیدا نشد.',$keyboard??null,'HTML'); return true; }

    if($action==='extend'){
        $userRow=is_array($user)?$user:select('user','*','id',$chatId,'select');

        // Always let the user pick a product for renewal (no silent auto-renew on
        // the previous product) — for both national-net and main-panel services.
        $kbd=nmStockExtendProductKeyboard($invoice,$userRow,$panel);
        if(!$kbd){
            sendmessage($chatId,'❌ محصولی برای تمدید این سرویس پیدا نشد.',$keyboard??null,'HTML');
            if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصولی پیدا نشد','show_alert'=>true,'cache_time'=>2]);
            return true;
        }
        $title = nmPanelNationalEnabled($panel)
            ? '📦 وضعیت نت ملی فعال است؛ محصول موردنظر برای تمدید را از لیست زیر انتخاب کنید:'
            : '🛍 محصول موردنظر برای تمدید را از لیست محصولات موجود انتخاب کنید:';
        if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$title,$kbd,'HTML'); else sendmessage($chatId,$title,$kbd,'HTML');
        if($callbackQueryId&&function_exists('telegram'))telegram('answerCallbackQuery',['callback_query_id'=>$callbackQueryId,'text'=>'محصول تمدید را انتخاب کنید','show_alert'=>false,'cache_time'=>2]);
        return true;
    }

    if($action==='select_extend' || $action==='confirm_extend'){
        $userRow=is_array($user)?$user:select('user','*','id',$chatId,'select');
        $product=nmStockResolveProductToken($token,$panel,$userRow['agent']??'all');
        if(!$product){ sendmessage($chatId,'❌ محصول تمدید پیدا نشد.',$keyboard??null,'HTML'); return true; }
        if($action==='select_extend'){
            $price=number_format((float)($product['price_product']??0));
            $txt="📜 فاکتور تمدید سرویس

👤 سرویس: <code>".htmlspecialchars((string)($invoice['username']??''),ENT_QUOTES,'UTF-8')."</code>
🛍 محصول: {$product['name_product']}
💰 مبلغ: {$price} تومان
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$product['Volume_constraint']} گیگ";
            $kbd=json_encode(['inline_keyboard'=>[[['text'=>'✅ تایید تمدید','callback_data'=>'nmstockok_'.$token.'_'.$iid]],[['text'=>'🏠 بازگشت به لیست سرویس ها','callback_data'=>'backorder']]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if(!empty($messageId)&&function_exists('Editmessagetext')) Editmessagetext($chatId,$messageId,$txt,$kbd,'HTML'); else sendmessage($chatId,$txt,$kbd,'HTML');
            return true;
        }
        $price=(float)($product['price_product']??0);
        if((float)($userRow['Balance']??0)<$price && ($userRow['agent']??'')!=='n2'){ sendmessage($chatId,'❌ موجودی کیف پول برای تمدید کافی نیست.',$keyboard??null,'HTML'); return true; }
        if(nmPanelNationalEnabled($panel)){
            $stockNew=nmStockReserveForProduct($panel,$product,$chatId,$invoice['id_invoice'],'stock_service_extend');
            if(!$stockNew){ sendmessage($chatId,'❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.',$keyboard??null,'HTML'); return true; }
            if (function_exists('balance_atomic_charge')) {
                $__allowNegRb=(($userRow['agent']??'')==='n2')?(int)($userRow['maxbuyagent']??0):0;
                balance_atomic_charge($chatId,(float)$price,$__allowNegRb);
            } else {
                update('user','Balance',(float)($userRow['Balance']??0)-$price,'id',$chatId);
            }
            update('invoice','name_product',$product['name_product'],'id_invoice',$invoice['id_invoice']);
            update('invoice','price_product',$product['price_product'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Volume',$product['Volume_constraint'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Service_time',$product['Service_time'],'id_invoice',$invoice['id_invoice']);
            update('invoice','Status','active','id_invoice',$invoice['id_invoice']);
            update('invoice','time_sell',time(),'id_invoice',$invoice['id_invoice']);
            update('invoice','user_info',$stockNew['content'],'id_invoice',$invoice['id_invoice']);
            try{ update('invoice','source_panel_code',$panel['code_panel']??'','id_invoice',$invoice['id_invoice']); }catch(Throwable $e){}
            $invoiceNew=array_merge($invoice,['name_product'=>$product['name_product'],'price_product'=>$product['price_product'],'Volume'=>$product['Volume_constraint'],'Service_time'=>$product['Service_time'],'time_sell'=>time(),'user_info'=>$stockNew['content'],'source_panel_code'=>$panel['code_panel']??'']);
            nmStockDeliverConfig($stockNew,$invoiceNew,'✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
            sendmessage($chatId,'✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.',$keyboard??null,'HTML');

            if(function_exists('nmStockNotifyExtend')) nmStockNotifyExtend($chatId,$userRow,$invoice,$product,$panel,'stock',(string)($invoice['username']??''),$price);
        }else{
            nmStockConvertInvoiceToPanelService($chatId,$userRow,$invoice,$panel,$product);
        }
        return true;
    }
    return false;
}}

function nmStockDeliverConfig(array $stock, array $invoice, $captionPrefix = '✅ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد')
{
    global $from_id;
    $userId = $invoice['id_user'] ?? $from_id;
    $invoiceId = $invoice['id_invoice'] ?? '';
    $content = trim((string)($stock['content'] ?? ''));
    $subLink = trim((string)($stock['sub_link'] ?? ''));
    if ($subLink === '' && preg_match('/^https?:\/\//i', $content)) $subLink = $content;
    $days = (int)($invoice['Service_time'] ?? 0);
    $startTs = nmInvoiceTimestamp($invoice);
    $started = nmJalaliDate('Y/m/d', $startTs);
    $expires = $days > 0 ? nmJalaliDate('Y/m/d', $startTs + ($days * 86400)) : 'نامحدود';

    $shelfName = '';
    if (!empty($stock['shelf_id']) && function_exists('nmStockShelfById')) {
        $shelf = nmStockShelfById($stock['shelf_id']);
        if (is_array($shelf)) $shelfName = (string)($shelf['name'] ?? '');
    }
    $shelfNameSafe = htmlspecialchars($shelfName !== '' ? $shelfName : 'انبار شبکه‌ملی', ENT_QUOTES, 'UTF-8');
    $caption = $captionPrefix . "\n\n📦 انبار: <code>{$shelfNameSafe}</code>\n📅 شروع: <code>{$started}</code>\n⏳ پایان: <code>{$expires}</code>\n\n⚠️ در حالت انبار، حجم باقی‌مانده از داخل ربات نمایش داده نمی‌شود؛ حجم را از لینک اشتراک مشاهده کنید.\n\n🔗 کانفیگ/اشتراک:\n<code>" . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "</code>";
    if ($subLink !== '' && $subLink !== $content) $caption .= "\n\n🔗 لینک اشتراک برای مشاهده حجم:\n<code>" . htmlspecialchars($subLink, ENT_QUOTES, 'UTF-8') . "</code>";
    $deliveryKeyboard = nmStockDeliveryKeyboard($content, $invoiceId, $subLink);
    try {
        if ($content !== '' && function_exists('createqrcode') && function_exists('telegram')) {
            $urlimage = $userId . bin2hex(random_bytes(3)) . '.png';
            // QR encodes the subscription link when the admin provided one; otherwise
            // the single config link. (Image-1 request 1/2.)
            $qrPayload = ($subLink !== '') ? $subLink : $content;
            $qrCode = createqrcode($qrPayload);
            file_put_contents($urlimage, $qrCode->getString());
            if (function_exists('addBackgroundImage')) @addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', ['chat_id' => $userId, 'photo' => new CURLFile($urlimage), 'caption' => $caption, 'parse_mode' => 'HTML', 'reply_markup' => $deliveryKeyboard]);
            @unlink($urlimage);
        } else {
            sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
        }
        nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['caption' => $captionPrefix]);
    } catch (Throwable $e) {
        error_log('nmStockDeliverConfig failed: ' . redfox_exception_fingerprint($e));
        sendmessage($userId, $caption, $deliveryKeyboard, 'HTML');
        nmStockMarkDelivered($stock['id'], $userId, $invoiceId, ['fallback_send' => true]);
    }
}

function nmStockFallbackForInvoice(array $invoice, array $product = null, $mode = 'panel_fallback')
{
    $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'] ?? '', 'select');
    if (!$product) $product = nmStockProductForInvoice($invoice);
    if (is_array($panel) && $panel) {
        $stock = nmStockReserveForProduct($panel, $product, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
    } else {
        $volume = $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0);
        $productCode = $product['code_product'] ?? 'auto';
        $stock = nmStockReserveOne('auto', $productCode, $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
        if (!$stock && $productCode !== 'auto') $stock = nmStockReserveOne('auto', 'auto', $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
    }
    if (!$stock) return false;
    nmStockDeliverConfig($stock, $invoice, $mode === 'config_button' ? '✅ کانفیگ مطابق حجم سرویس از انبار شبکه‌ملی تحویل شد' : '✅ تمدید از پنل انجام نشد؛ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد');
    try { update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']); update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) { error_log('nmStockFallbackForInvoice invoice update failed: ' . redfox_exception_fingerprint($e)); }
    return $stock;
}

function nmStockStatusText($panelCode = null)
{
    $rows = nmStockCounts($panelCode);
    if (!$rows) return "📦 انبار شبکه‌ملی\n\n❌ موجودی فعالی ثبت نشده است.";
    $lines = ["📦 وضعیت موجودی انبار شبکه‌ملی", "", "سطح / فرمت / تعداد:"];
    foreach ($rows as $row) $lines[] = "• {$row['tier']} / {$row['format']} : {$row['cnt']} عدد";
    return implode("\n", $lines);
}

function nmStockCompleteExtendFallback($userId, array $userRow, array $invoice, array $product, $priceToCharge = 0, $mode = 'extend_fallback')
{
    global $pdo;
    $stock = nmStockFallbackForInvoice($invoice, $product, $mode);
    if (!$stock) return false;
    try {
        $priceToCharge = (float)$priceToCharge;
        if ($priceToCharge > 0 && isset($userRow['Balance'])) {
            if (function_exists('balance_atomic_charge')) {
                $__allowNegFb = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                balance_atomic_charge($userId, $priceToCharge, $__allowNegFb);
            } else {
                $newBalance = (float)$userRow['Balance'] - $priceToCharge;
                update('user', 'Balance', $newBalance, 'id', $userId);
            }
        }
        $value = json_encode([
            'volumebuy' => $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0),
            'Service_time' => $product['Service_time'] ?? ($invoice['Service_time'] ?? 0),
            'code_product' => $product['code_product'] ?? 'auto',
            'source' => 'nm_stock',
            'stock_id' => $stock['id'],
            'tier' => $stock['tier']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output = json_encode(['status' => true, 'source' => 'nm_stock', 'stock_id' => $stock['id'], 'tier' => $stock['tier']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (:id_user,:username,:value,'extend_user',:time,:price,:output,'paid')");
        $stmt->execute([
            ':id_user' => $userId,
            ':username' => $invoice['username'] ?? '',
            ':value' => $value,
            ':time' => date('Y/m/d H:i:s'),
            ':price' => $priceToCharge,
            ':output' => $output,
        ]);
        update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
    } catch (Throwable $e) {
        error_log('nmStockCompleteExtendFallback failed: ' . redfox_exception_fingerprint($e));
    }
    sendmessage($userId, "✅ پنل در دسترس نبود؛ تمدید با کانفیگ جایگزین انبار شبکه‌ملی تکمیل شد.", null, 'HTML');
    return $stock;
}
/* ---- business_logic_3.php ---- */
if (!function_exists('nmStockSendQr')) {
/**
 * Send a QR-code photo (of $qrPayload) with a short caption, then the full
 * text separately so long configs/links never hit Telegram's 1024-char caption
 * limit. Falls back to a plain text message if QR generation isn't available.
 */
function nmStockSendQr($chatId, $qrPayload, $fullText, $shortCaption = '📥 کیو‌آر کد')
{
    $qrPayload = trim((string)$qrPayload);
    $sent = false;
    if ($qrPayload !== '' && function_exists('createqrcode') && function_exists('telegram')) {
        try {
            $urlimage = $chatId . bin2hex(random_bytes(3)) . '.png';
            $qrCode = createqrcode($qrPayload);
            file_put_contents($urlimage, $qrCode->getString());
            if (function_exists('addBackgroundImage')) @addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', ['chat_id' => $chatId, 'photo' => new CURLFile($urlimage), 'caption' => $shortCaption, 'parse_mode' => 'HTML']);
            @unlink($urlimage);
            $sent = true;
        } catch (Throwable $e) {
            error_log('nmStockSendQr failed: ' . redfox_exception_fingerprint($e));
        }
    }
    if (trim((string)$fullText) !== '') {
        if (function_exists('nmSendLongStockMessage')) nmSendLongStockMessage($chatId, $fullText);
        else sendmessage($chatId, $fullText, null, 'HTML');
    }
    return $sent;
}
}

function nmStockDeliveryKeyboard($content, $invoiceId = '', $subLink = '')
{
    $buttons = [];
    $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$invoiceId);
    if ($invoiceId !== '') {
        $buttons[] = [
            ['text' => '🔄 تمدید سرویس', 'callback_data' => 'nmstockextend_' . $invoiceId],
            ['text' => '💎 بازگشت وجه', 'callback_data' => 'nmstockrefund_' . $invoiceId],
        ];
        $buttons[] = [
            ['text' => '📥 دریافت کانفیگ', 'callback_data' => 'nmstockcfg_' . $invoiceId],
            ['text' => '🔗 لینک اشتراک', 'callback_data' => 'nmstocksub_' . $invoiceId],
        ];
    } else {
        $buttons[] = [['text' => '📥 دریافت کانفیگ', 'callback_data' => 'none']];
        $link = trim((string)$subLink) !== '' ? trim((string)$subLink) : trim((string)$content);
        if (preg_match('/^https?:\/\//i', $link)) $buttons[] = [['text' => '🔗 لینک اشتراک', 'url' => $link]];
        else $buttons[] = [['text' => '🔗 لینک اشتراک', 'callback_data' => 'none']];
    }
    $buttons[] = [['text' => '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmPanelBool(array $panel = null, $field = 'national_net_status', $onValue = 'on_national_net')
{
    if (!$panel) return false;
    return (($panel[$field] ?? '') === $onValue || ($panel[$field] ?? '') === 'on' || ($panel[$field] ?? '') === 'active');
}
function nmPanelNationalEnabled(array $panel = null) { return nmPanelBool($panel, 'national_net_status', 'on_national_net'); }
function nmPanelEmergencyEnabled(array $panel = null) { return nmPanelBool($panel, 'emergency_panel_status', 'on_emergency_panel'); }
function nmPanelResolveStockCode(array $panel = null)
{
    if (!$panel) return 'auto';
    $code = trim((string)($panel['stock_source_panel'] ?? ''));
    if ($code !== '') return $code;
    return trim((string)($panel['code_panel'] ?? '')) !== '' ? $panel['code_panel'] : 'auto';
}
function nmPanelEmergencyPanel(array $panel = null)
{
    if (!$panel || !nmPanelEmergencyEnabled($panel)) return false;
    $code = trim((string)($panel['emergency_source_panel'] ?? ''));
    if ($code === '') return false;
    try { return select('marzban_panel', '*', 'code_panel', $code, 'select'); } catch (Throwable $e) { return false; }
}


function nmEmergencyReplacementMap()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = ['by_code' => [], 'by_name' => []];
    try {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE emergency_panel_status = 'on_emergency_panel' AND status = 'active' AND emergency_source_panel IS NOT NULL AND emergency_source_panel <> ''");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $srcCode = trim((string)($row['emergency_source_panel'] ?? ''));
                if ($srcCode === '') continue;
                $cache['by_code'][$srcCode] = $row;
                try {
                    $srcRow = select('marzban_panel', '*', 'code_panel', $srcCode, 'select');
                    if (is_array($srcRow) && !empty($srcRow['name_panel'])) {
                        $cache['by_name'][(string)$srcRow['name_panel']] = $row;
                    }
                } catch (Throwable $_) {}
            }
        }
    } catch (Throwable $e) {
        error_log('nmEmergencyReplacementMap failed: ' . redfox_exception_fingerprint($e));
    }
    return $cache;
}


function nmEmergencyResetCache()
{
    static $reset = false;
    $reset = !$reset;
}


function nmFilterPanelsForUser(array $rows)
{
    $map = nmEmergencyReplacementMap();
    $replacedCodes = array_keys($map['by_code'] ?? []);
    if (empty($replacedCodes)) return $rows;
    $replacedSet = array_flip($replacedCodes);
    $out = [];
    foreach ($rows as $row) {
        $code = trim((string)($row['code_panel'] ?? ''));
        if ($code !== '' && isset($replacedSet[$code])) continue;
        $out[] = $row;
    }
    return $out;
}


function nmResolveActivePanelByCode($code)
{
    $code = trim((string)$code);
    if ($code === '') return null;
    $map = nmEmergencyReplacementMap();
    if (isset($map['by_code'][$code])) return $map['by_code'][$code];
    try { return select('marzban_panel', '*', 'code_panel', $code, 'select') ?: null; } catch (Throwable $e) { return null; }
}


function nmEmergencyHidesPanel(array $row)
{
    $code = trim((string)($row['code_panel'] ?? ''));
    if ($code === '') return false;
    $map = nmEmergencyReplacementMap();
    return isset($map['by_code'][$code]);
}


function nmResolveActivePanelByName($name)
{
    $name = trim((string)$name);
    if ($name === '') return null;
    $map = nmEmergencyReplacementMap();
    if (isset($map['by_name'][$name])) return $map['by_name'][$name];
    try { return select('marzban_panel', '*', 'name_panel', $name, 'select') ?: null; } catch (Throwable $e) { return null; }
}
function nmStockPanelProducts($panelCode, $agent = 'all')
{
    global $pdo;
    try {
        $panel = select('marzban_panel', '*', 'code_panel', $panelCode, 'select');
        if (!$panel) return [];
        $agent = trim((string)($agent ?? ''));
        $params = [':loc_where' => $panel['name_panel']];
        $sql = "SELECT * FROM product WHERE (Location = :loc_where OR Location = '/all')";
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY CAST(price_product AS UNSIGNED) ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { error_log('nmStockPanelProducts failed: ' . redfox_exception_fingerprint($e)); return []; }
}
function nmStockSyncProductMap($sourcePanelCode, $stockPanelCode = null, $agent = 'all')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return 0;
    $stockPanelCode = $stockPanelCode ?: $sourcePanelCode;
    $ok = 0;
    foreach (nmStockPanelProducts($sourcePanelCode, $agent) as $product) {
        try {
            $cat = $product['category'] ?? null;
            $catName = null; $catId = null;
            if ($cat !== null && trim((string)$cat) !== '') {
                $vals = nmCategoryLookupValues($cat);
                $catName = $vals ? end($vals) : $cat;
                $catId = is_numeric($cat) ? (string)$cat : null;
            }
            $stmt = $pdo->prepare("INSERT INTO nm_stock_product_map (source_codepanel,stock_codepanel,codeproduct,category,category_id,category_name,volume_gb,service_days,price,status,created_at,updated_at) VALUES (:source,:stock,:code,:cat,:catid,:catname,:vol,:days,:price,'active',:created_at,:updated_at) ON DUPLICATE KEY UPDATE category=VALUES(category), category_id=VALUES(category_id), category_name=VALUES(category_name), volume_gb=VALUES(volume_gb), service_days=VALUES(service_days), price=VALUES(price), status='active', updated_at=VALUES(updated_at)");
            $stmt->execute([':source'=>$sourcePanelCode, ':stock'=>$stockPanelCode, ':code'=>$product['code_product'] ?? 'auto', ':cat'=>$cat, ':catid'=>$catId, ':catname'=>$catName, ':vol'=>(float)($product['Volume_constraint'] ?? 0), ':days'=>(int)($product['Service_time'] ?? 0), ':price'=>(int)($product['price_product'] ?? 0), ':created_at'=>time(), ':updated_at'=>time()]);
            $ok++;
        } catch (Throwable $e) { error_log('nmStockSyncProductMap item failed: ' . redfox_exception_fingerprint($e)); }
    }
    return $ok;
}
function nmStockReserveForProduct(array $panel, array $product, $userId, $invoiceId, $mode = 'national_buy')
{
    $stockPanel = nmPanelResolveStockCode($panel);
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $volume = $product['Volume_constraint'] ?? 0;
    $stock = nmStockReserveOne($stockPanel, $productCode, $volume, $userId, $invoiceId, $mode);
    if (!$stock && $sourcePanel !== $stockPanel) $stock = nmStockReserveOne($sourcePanel, $productCode, $volume, $userId, $invoiceId, $mode);
    if (!$stock) $stock = nmStockReserveByShelfMatch($panel, $product, $userId, $invoiceId, $mode);
    if (!$stock) $stock = nmStockReserveOne($stockPanel, 'auto', $volume, $userId, $invoiceId, $mode);
    if (!$stock && $sourcePanel !== $stockPanel) $stock = nmStockReserveOne($sourcePanel, 'auto', $volume, $userId, $invoiceId, $mode);


    if (!$stock) $stock = nmStockReserveByShelfLoose($panel, $product, $userId, $invoiceId, $mode);
    return $stock;
}

if (!function_exists('nmStockReserveByShelfLoose')) {
function nmStockReserveByShelfLoose(array $panel, array $product, $userId, $invoiceId, $mode = 'fallback_loose')
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $stockPanel = function_exists('nmPanelResolveStockCode') ? nmPanelResolveStockCode($panel) : (trim((string)($panel['code_panel'] ?? '')) ?: 'auto');
    $sourcePanel = trim((string)($panel['code_panel'] ?? '')) ?: $stockPanel;
    $panelCandidates = array_values(array_unique(array_filter([$stockPanel, $sourcePanel, 'auto'], static function ($v) {
        return trim((string)$v) !== '';
    })));
    $productCode = trim((string)($product['code_product'] ?? 'auto')) ?: 'auto';
    $productName = function_exists('nmNormalizeText') ? nmNormalizeText($product['name_product'] ?? '') : (string)($product['name_product'] ?? '');
    $volume = (float)($product['Volume_constraint'] ?? 0);
    $days = (int)($product['Service_time'] ?? 0);
    $panelWhereSqlS = [];
    $panelWhereSqlSrc = [];
    $panelWhereSqlSt = [];
    $params = [
        ':product_code_stock_where' => $productCode,
        ':product_code_shelf_where' => $productCode,
        ':product_name_where' => $productName,
        ':volume_where' => $volume,
        ':days_where' => $days,
    ];
    foreach ($panelCandidates as $i => $candidate) {
        $kS = ':panel_where_s_' . $i;
        $kSrc = ':panel_where_src_' . $i;
        $kSt = ':panel_where_st_' . $i;
        $panelWhereSqlS[] = $kS;
        $panelWhereSqlSrc[] = $kSrc;
        $panelWhereSqlSt[] = $kSt;
        $params[$kS] = (string)$candidate;
        $params[$kSrc] = (string)$candidate;
        $params[$kSt] = (string)$candidate;
    }
    try {

        $stmt = $pdo->prepare("SELECT s.* FROM nm_config_stock s INNER JOIN nm_stock_shelves sh ON sh.id=s.shelf_id WHERE s.status='active' AND sh.status='active' AND (s.codepanel IN (" . implode(',', $panelWhereSqlS) . ") OR sh.source_codepanel IN (" . implode(',', $panelWhereSqlSrc) . ") OR sh.stock_codepanel IN (" . implode(',', $panelWhereSqlSt) . ")) AND (sh.codeproduct=:product_code_shelf_where OR s.codeproduct=:product_code_stock_where OR sh.product_name=:product_name_where OR (ABS(sh.volume_gb - :volume_where) < 0.001 AND sh.service_days=:days_where)) ORDER BY s.id ASC LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $upd = $pdo->prepare("UPDATE nm_config_stock SET status='reserved', assigned_user=:user, assigned_invoice=:invoice, assigned_mode=:mode, reserved_at=:reserved_at WHERE id=:id AND status='active'");
        $upd->execute([':user' => (string)$userId, ':invoice' => (string)$invoiceId, ':mode' => (string)$mode, ':reserved_at' => time(), ':id' => $row['id']]);
        if ($upd->rowCount() < 1) return false;
        if (function_exists('nmStockLog')) nmStockLog($row['id'], $userId, $invoiceId, 'reserved', ['mode' => $mode, 'product' => $productCode, 'shelf_loose' => true]);
        return $row;
    } catch (Throwable $e) { error_log('nmStockReserveByShelfLoose failed: ' . redfox_exception_fingerprint($e)); return false; }
}}
function nmStockCompleteBuyFromInventory($userId, array $userRow, array $panel, array $product, $invoiceId, $usernameAc = '', $chargeBalance = true, $mode = 'national_buy')
{
    $stock = nmStockReserveForProduct($panel, $product, $userId, $invoiceId, $mode);
    if (!$stock) return false;
    try {
        if ($chargeBalance) {
            $__pp3 = (float)($product['price_product'] ?? 0);
            if ($__pp3 > 0) {
                if (function_exists('balance_atomic_charge')) {
                    $__allowNeg3 = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                    balance_atomic_charge($userId, $__pp3, $__allowNeg3);
                } else {
                    update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $__pp3, 'id', $userId);
                }
            }
        }
        update('invoice', 'Status', 'active', 'id_invoice', $invoiceId);
        update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoiceId);
        try { update('invoice', 'source_panel_code', $panel['code_panel'] ?? '', 'id_invoice', $invoiceId); } catch (Throwable $e) {}
    } catch (Throwable $e) { error_log('nmStockCompleteBuyFromInventory update failed: ' . redfox_exception_fingerprint($e)); }
    $invoice = ['id_user'=>$userId, 'id_invoice'=>$invoiceId, 'username'=>$usernameAc, 'Service_location'=>$panel['name_panel'] ?? '', 'name_product'=>$product['name_product'] ?? '', 'Volume'=>$product['Volume_constraint'] ?? 0, 'Service_time'=>$product['Service_time'] ?? 0];
    nmStockDeliverConfig($stock, $invoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار پشتیبان تحویل شد');

    if (function_exists('nmStockNotifyExtend')) {
        $notifyMode = (strpos((string)$mode, 'emergency') !== false) ? 'emergency' : 'stock';
        nmStockNotifyExtend($userId, $userRow, $invoice, $product, $panel, $notifyMode, (string)$usernameAc, (float)($product['price_product'] ?? 0), 'buy');
    }
    return $stock;
}

function nmStockShelves($panelCode = null, $onlyActive = true)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return [];
    $where = $onlyActive ? "status='active'" : "1=1";
    $params = [];
    if ($panelCode !== null && trim((string)$panelCode) !== '') { $where .= " AND (source_codepanel=:panel_source OR stock_codepanel=:panel_stock OR source_codepanel='auto')"; $params[':panel_source'] = (string)$panelCode; $params[':panel_stock'] = (string)$panelCode; }
    try { $stmt = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE $where ORDER BY id DESC"); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { error_log('nmStockShelves failed: '.redfox_exception_fingerprint($e)); return []; }
}
function nmStockShelfById($id)
{
    global $pdo;
    if (!nmStockEnsureSchema()) { error_log('[STOCK_SHELF_BY_ID] schema_not_ready id=' . var_export($id, true)); return false; }
    try {
        $stmt = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            error_log(sprintf('[STOCK_SHELF_BY_ID] not_found requested_id=%s casted_int=%d', var_export($id, true), (int)$id));
            return false;
        }
        return $row;
    } catch (Throwable $e) {
        error_log('[STOCK_SHELF_BY_ID] exception id=' . var_export($id, true) . ' err=' . redfox_exception_fingerprint($e));
        return false;
    }
}

/**
 * --- Durable stock-import session -------------------------------------------
 * The bulk/single import flow used to carry its whole state (shelf id, mode,
 * has_sub flag, pending config, imported count) ONLY inside the shared
 * "Processing_value" JSON. That field is reset by many unrelated handlers —
 * to "0" (/start, back button, Add_Balance, rcc_cancel, recheckcrypto, ...)
 * and to the bare panel name (e.g. "fox") by the "بازگشت به منوی مدیریت"
 * handler. When that happened mid-import the session was wiped, producing:
 *   - "❌ انبار انتخابی معتبر نیست."  (shelf id lost)
 *   - "❌ کانفیگ pending پیدا نشد."   (pending config lost)
 *
 * To make the session immune to those resets we keep it in a dedicated user
 * column ("nm_stock_session") that NO other handler touches. Every stock step
 * reads/writes the session through these helpers. We still mirror the shelf id
 * into "nm_shelf_active" + Processing_value for backward compatibility / migration.
 */
function nmStockEnsureColumn($column, $ddlType)
{
    static $ensured=[];if(isset($ensured[$column]))return;$ensured[$column]=true;global$pdo;
    if(!($pdo instanceof PDO))return;try{rx_require_schema($pdo,[],['user'=>[(string)$column]]);}catch(Throwable$e){error_log("nmStock schema migration required for user.$column");}
}
function nmStockEnsureActiveShelfColumn()
{
    nmStockEnsureColumn('nm_shelf_active', 'VARCHAR(32)');
    nmStockEnsureColumn('nm_stock_session', 'TEXT');
}

/**
 * Read the durable import session as an array.
 * Falls back to the legacy Processing_value keys / nm_shelf_active so sessions
 * started under the old code keep working after this update is deployed.
 */
function nmStockSessionGet($user)
{
    nmStockEnsureActiveShelfColumn();
    $session = [];
    $raw = is_array($user) ? ($user['nm_stock_session'] ?? '') : '';
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $session = $decoded;
    }
    // Migration / fallback from the old Processing_value-based state.
    if (is_array($user)) {
        $pv = $user['Processing_value'] ?? '';
        $legacy = (is_string($pv) && $pv !== '') ? json_decode($pv, true) : null;
        if (is_array($legacy)) {
            $map = [
                'nm_shelf_id'          => 'shelf_id',
                'nm_stock_import_mode' => 'mode',
                'nm_stock_has_sub'     => 'has_sub',
                'nm_stock_pending_cfg' => 'pending_cfg',
                'nm_stock_imported'    => 'imported',
            ];
            foreach ($map as $oldKey => $newKey) {
                if (!array_key_exists($newKey, $session) && array_key_exists($oldKey, $legacy)) {
                    $session[$newKey] = $legacy[$oldKey];
                }
            }
        }
        if (empty($session['shelf_id']) && !empty($user['nm_shelf_active'])) {
            $session['shelf_id'] = (int)$user['nm_shelf_active'];
        }
    }
    return $session;
}

/**
 * Merge key/values into the durable session. Always reads the freshest copy
 * from the DB first (cache disabled) so multiple patches within one request
 * accumulate instead of clobbering each other.
 */
function nmStockSessionPatch($fromId, array $kv)
{
    nmStockEnsureActiveShelfColumn();
    $session = [];
    if (function_exists('select')) {
        $row = select("user", "nm_stock_session", "id", $fromId, "select", ['cache' => false]);
        $raw = is_array($row) ? ($row['nm_stock_session'] ?? '') : '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $session = $decoded;
        }
    }
    foreach ($kv as $k => $v) { $session[$k] = $v; }
    if (function_exists('update')) {
        update("user", "nm_stock_session", json_encode($session, JSON_UNESCAPED_UNICODE), "id", $fromId);
    }
    return $session;
}

function nmStockSessionClear($fromId)
{
    nmStockEnsureActiveShelfColumn();
    if (function_exists('update')) {
        update("user", "nm_stock_session", "", "id", $fromId);
        update("user", "nm_shelf_active", "0", "id", $fromId);
    }
}

/**
 * Persist the chosen shelf into the durable session (+ legacy mirrors).
 */
function nmStockSetActiveShelf($fromId, $shelfId)
{
    $shelfId = (int)$shelfId;
    if ($shelfId <= 0) return;
    if (function_exists('savedata')) {
        savedata("save", "nm_shelf_id", $shelfId); // legacy mirror
    }
    nmStockEnsureActiveShelfColumn();
    if (function_exists('update')) {
        update("user", "nm_shelf_active", (string)$shelfId, "id", $fromId);
    }
    nmStockSessionPatch($fromId, ['shelf_id' => $shelfId]);
}

/**
 * Backward-compatible alias kept for the session-end clears.
 */
function nmStockClearActiveShelf($fromId)
{
    nmStockSessionClear($fromId);
}

/**
 * Resolve the shelf currently being imported into.
 * Priority: explicit callback id -> durable session -> legacy
 * Processing_value['nm_shelf_id'] -> nm_shelf_active column.
 * Returns the shelf row, or false if none can be resolved.
 */
function nmStockActiveShelf($user, array $data = [], $callbackShelfId = null)
{
    $candidates = [];
    if ($callbackShelfId !== null && (int)$callbackShelfId > 0) {
        $candidates[] = (int)$callbackShelfId;
    }
    $session = nmStockSessionGet($user);
    if (!empty($session['shelf_id'])) {
        $candidates[] = (int)$session['shelf_id'];
    }
    if (!empty($data['nm_shelf_id'])) {
        $candidates[] = (int)$data['nm_shelf_id'];
    }
    if (is_array($user) && !empty($user['nm_shelf_active'])) {
        $candidates[] = (int)$user['nm_shelf_active'];
    }
    foreach (array_values(array_unique($candidates)) as $cid) {
        if ($cid <= 0) continue;
        $shelf = nmStockShelfById($cid);
        if ($shelf) return $shelf;
    }
    return false;
}

/**
 * Re-show the shelf-selection list and step back into the import flow.
 * Used instead of bouncing to the admin menu when a shelf can't be resolved,
 * so the admin stays inside the stock flow and can simply re-pick.
 */
function nmStockRepromptShelf($fromId, $user)
{
    global $setting;
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : false;
    $kb = nmStockShelfKeyboard(is_array($panel) ? ($panel['code_panel'] ?? null) : null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decoded = json_decode($kb, true);
        if (isset($decoded['keyboard'])) $kb = rx_keyboardToInline($decoded);
    }
    $msg = "❌ انبار انتخابی پیدا نشد یا اطلاعات آن از بین رفته است.\n📌 لطفاً دوباره از لیست زیر انبار موردنظر را انتخاب کنید.";
    if (function_exists('nm_replyOrEdit')) {
        nm_replyOrEdit($fromId, $msg, $kb, 'HTML');
    } else {
        sendmessage($fromId, $msg, $kb, 'HTML');
    }
    if (function_exists('step')) step('nm_stock_select_shelf_import', $fromId);
}

/**
 * True when at least one product at this location/agent belongs to a category
 * that actually exists in the `category` table — i.e. the shop is set up to
 * sell via categories. Used to decide whether the national-net / emergency
 * flow should show the category picker or fall back to a direct product list.
 */
/**
 * Return the category rows that actually have at least one sellable product for
 * the given panel + agent.
 *
 * This deliberately matches in PHP (not via a SQL JOIN) because:
 *   - product.category is utf8mb4_unicode_ci while category.remark is utf8mb4_bin,
 *     so a direct "p.category = c.remark" JOIN throws "illegal mix of collations"
 *     and silently returned no categories (the national-net "empty category list"
 *     bug). Comparing in PHP avoids the collation clash entirely.
 *   - products may reference a category by its remark OR its id OR name/title, and
 *     with stray whitespace/case differences. We normalise both sides so they match.
 */
if (!function_exists('nmSellableCategoryRows')) {
function nmSellableCategoryRows($location, $agent)
{
    global $pdo;
    $out = [];
    if (!($pdo instanceof PDO)) return $out;
    $norm = static function ($v) {
        return function_exists('nmNormalizeText') ? nmNormalizeText($v) : trim((string)$v);
    };
    // 1) Collect the (normalised) category tokens that products for this panel use.
    $prodSet = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT category FROM product "
            . "WHERE (Location = :loc OR Location = '/all') "
            . "AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL) "
            . "AND category IS NOT NULL AND TRIM(category) <> ''"
        );
        $stmt->execute([':loc' => $location, ':agent' => $agent]);
        foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $pc) {
            $n = $norm($pc);
            if ($n !== '' && $n !== '0') $prodSet[$n] = true;
        }
    } catch (Throwable $e) {
        error_log('nmSellableCategoryRows products failed: ' . redfox_exception_fingerprint($e));
        return $out;
    }
    if (!$prodSet) return $out;
    // 2) Keep the category rows whose remark/id/name/title matches a product token.
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'id', 'name', 'title'] as $k) {
                if (!isset($row[$k]) || trim((string)$row[$k]) === '') continue;
                if (isset($prodSet[$norm($row[$k])])) { $out[] = $row; break; }
            }
        }
    } catch (Throwable $e) {
        error_log('nmSellableCategoryRows categories failed: ' . redfox_exception_fingerprint($e));
    }
    return $out;
}
}

function nmHasSellableCategories($location, $agent)
{
    return count(nmSellableCategoryRows($location, $agent)) > 0;
}

function nmStockShelfCreate($name, array $sourcePanel, array $product, $categoryId = null, $categoryName = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return false;
    $now=time();
    $sourceCode = trim((string)($sourcePanel['code_panel'] ?? '')) ?: 'auto';
    $stockCode = nmPanelResolveStockCode($sourcePanel);
    if ($stockCode === 'auto') $stockCode = $sourceCode;
    if (($categoryName === null || $categoryName === '') && !empty($product['category'])) $categoryName = $product['category'];
    try {
        $stmt=$pdo->prepare("INSERT INTO nm_stock_shelves (name,source_codepanel,stock_codepanel,category_id,category_name,codeproduct,product_name,volume_gb,service_days,price,status,created_at,updated_at) VALUES (:name,:source,:stock,:catid,:catname,:code,:pname,:vol,:days,:price,'active',:created_at,:updated_at) ON DUPLICATE KEY UPDATE stock_codepanel=VALUES(stock_codepanel), category_id=VALUES(category_id), category_name=VALUES(category_name), codeproduct=VALUES(codeproduct), product_name=VALUES(product_name), volume_gb=VALUES(volume_gb), service_days=VALUES(service_days), price=VALUES(price), status='active', updated_at=VALUES(updated_at)");
        $stmt->execute([':name'=>(string)$name, ':source'=>$sourceCode, ':stock'=>$stockCode, ':catid'=>$categoryId, ':catname'=>$categoryName, ':code'=>$product['code_product'] ?? 'auto', ':pname'=>$product['name_product'] ?? '', ':vol'=>(float)($product['Volume_constraint'] ?? 0), ':days'=>(int)($product['Service_time'] ?? 0), ':price'=>(int)($product['price_product'] ?? 0), ':created_at'=>$now, ':updated_at'=>$now]);
        return true;
    } catch (Throwable $e) { error_log('nmStockShelfCreate failed: '.redfox_exception_fingerprint($e)); return false; }
}
function nmStockShelfKeyboard($panelCode, $callbackPrefix = null)
{
    $rows=[];
    foreach (nmStockShelves($panelCode) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName === '' || $shelfName[0] === '{' || $shelfName[0] === '[') continue;
        $label = '📦 '.$shelfName.' - '.$shelf['product_name'].' ('.$shelf['volume_gb'].'GB/'.$shelf['service_days'].'روز)';
        $rows[] = [['text'=>$label]];
    }
    $rows[] = [['text'=>'➕ افزودن انبار مدنظر']];
    $rows[] = [['text'=>'🔙 بازگشت به انبار']];
    return json_encode(['keyboard'=>$rows,'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Stock management keyboard (main "📦 انبار شبکه ملی" menu).
 * Centralized so back-navigation handlers can rebuild it.
 * Buttons carry per-button "style" hints (success/primary/danger/default)
 * which rx_kb_encode picks up under inline mode for coloured glass buttons.
 */
function nmStockManageKeyboard()
{
    global $setting;
    $stylesAll = [];
    if (function_exists('select')) {
        $row = select('setting', 'keyboard_styles_all', null, null, 'select');
        if (is_array($row) && !empty($row['keyboard_styles_all'])) {
            $decoded = json_decode($row['keyboard_styles_all'], true);
            if (is_array($decoded)) $stylesAll = $decoded;
        }
    }
    $sectionStyles = is_array($stylesAll['admin_panel_stock_manage'] ?? null) ? $stylesAll['admin_panel_stock_manage'] : [];
    if (function_exists('rx_getKeyboardDefaultStyles')) {
        $defaults = rx_getKeyboardDefaultStyles('admin_panel_stock_manage');
        if (is_array($defaults)) $sectionStyles = $sectionStyles + $defaults;
    }
    $useInline = isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline';
    $mk = function(string $text) use ($sectionStyles, $useInline): array {
        $btn = ['text' => $text];
        // Inline mode requires callback_data on every button; reply mode does not.
        if ($useInline) {
            if (function_exists('rx_makeAdminPanelCallback')) {
                $btn['callback_data'] = rx_makeAdminPanelCallback($text);
            } elseif (function_exists('rx_safeCallbackData')) {
                $btn['callback_data'] = rx_safeCallbackData($text, $text);
            } else {
                $btn['callback_data'] = strlen($text) <= 64 ? $text : substr(md5($text), 0, 32);
            }
        }
        if (function_exists('rx_kb_style')) {
            $btn = rx_kb_style($btn, $text, $sectionStyles);
        } elseif (!empty($sectionStyles[$text]) && $sectionStyles[$text] !== 'default') {
            $btn['style'] = $sectionStyles[$text];
        }
        return $btn;
    };
    $backText = $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $backBtn  = ['text' => $backText];
    if ($useInline) {
        // backadmin has its own callback handled in bootstrap; mirror admin_panels.php.
        $backBtn['callback_data'] = 'admin';
    }
    $rows = [
        [$mk("➕ افزودن انبار مدنظر")],
        [$mk("➕ وارد کردن دسته‌ای انبار"), $mk("➕ افزودن کانفیگ تکی انبار")],
        [$mk("✏️ ویرایش انبار"), $mk("❌ حذف کانفیگ انبار")],
        [$mk("🗑 حذف کامل انبار")],
        [$mk("📊 گزارش موجودی انبار")],
        [$mk("🔄 همگام‌سازی محصولات انبار")],
        [$mk("🚨 پنل اضطراری"), $mk("🌐 وضعیت نت ملی")],
        [$backBtn],
    ];
    if (function_exists('rx_kb_encode')) {
        return rx_kb_encode($rows);
    }
    $arr = ['keyboard' => $rows, 'resize_keyboard' => true];
    if ($useInline && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($arr);
    }
    return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Slim national-net / emergency keyboard. Shown after a user toggles
 * "🌐 وضعیت نت ملی" or "🚨 پنل اضطراری" — only the buttons that make
 * sense in that context.
 */
function nmNationalEmergencyMenuKeyboard()
{
    global $setting;
    $arr = [
        'keyboard' => [
            [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
            [['text' => "💡 روش ساخت نام کاربری"]],
            [['text' => "📦 انبار شبکه ملی"]],
            [['text' => "📌 ثبت پنل اضطراری"]],
            [['text' => "🚨 پنل اضطراری"], ['text' => "🌐 وضعیت نت ملی"]],
            [['text' => $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت'], ['text' => $GLOBALS['textbotlang']['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل']],
        ],
        'resize_keyboard' => true,
    ];
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($arr);
    }
    return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Paginated inline list of configs for a given shelf.
 * Used by the new "نمایش لیست کانفیگ‌ها" delete flow.
 */
function nmStockConfigInlineList($shelfId, $page = 0, $perPage = 10, $callbackPrefix = 'nm_cfg')
{
    global $pdo;
    $shelfId = (int)$shelfId;
    $page = max(0, (int)$page);
    $perPage = max(1, (int)$perPage);
    $offset = $page * $perPage;
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT id, content, status, sub_link FROM nm_config_stock WHERE shelf_id = :sid AND status = 'active' ORDER BY id ASC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':sid', $shelfId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage + 1, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('nmStockConfigInlineList failed: ' . redfox_exception_fingerprint($e));
        return ['keyboard' => json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE), 'count' => 0, 'has_next' => false];
    }
    $hasNext = count($rows) > $perPage;
    if ($hasNext) array_pop($rows);

    $inline = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $content = (string)$r['content'];
        $info = nmStockExtractConfigInfo($content);
        $label = '#' . $id . ' • ' . ($info['username'] !== '' ? $info['username'] : '—') . ($info['port'] !== '' ? ' :' . $info['port'] : '');
        if (mb_strlen($label, 'UTF-8') > 60) $label = mb_substr($label, 0, 60, 'UTF-8') . '…';
        $inline[] = [['text' => $label, 'callback_data' => $callbackPrefix . ':view:' . $id]];
    }

    $nav = [];
    if ($page > 0) $nav[] = ['text' => '⬅️ قبلی', 'callback_data' => $callbackPrefix . ':page:' . ($page - 1)];
    if ($hasNext) $nav[] = ['text' => 'بعدی ➡️', 'callback_data' => $callbackPrefix . ':page:' . ($page + 1)];
    if (!empty($nav)) $inline[] = $nav;
    $inline[] = [['text' => '❌ بستن', 'callback_data' => $callbackPrefix . ':close']];

    return [
        'keyboard' => json_encode(['inline_keyboard' => $inline], JSON_UNESCAPED_UNICODE),
        'count'    => count($rows),
        'has_next' => $hasNext,
        'page'     => $page,
    ];
}

/**
 * Best-effort extraction of username/port/host from a config payload
 * (vmess / vless / trojan / ss / wireguard json or text).
 */
function nmStockExtractConfigInfo($raw)
{
    $out = ['username' => '', 'port' => '', 'host' => '', 'type' => ''];
    $s = trim((string)$raw);
    if ($s === '') return $out;

    if (stripos($s, 'vmess://') === 0) {
        $out['type'] = 'vmess';
        $b64 = substr($s, 8);
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $decoded = @base64_decode($b64, true);
        if ($decoded !== false) {
            $j = json_decode($decoded, true);
            if (is_array($j)) {
                $out['username'] = (string)($j['ps'] ?? $j['remarks'] ?? $j['id'] ?? '');
                $out['host']     = (string)($j['add'] ?? '');
                $out['port']     = (string)($j['port'] ?? '');
            }
        }
        return $out;
    }

    if (preg_match('#^(?:vless|trojan|ss)://([^@\s]+)@([^:/?#]+):(\d+)(.*)$#i', $s, $m)) {
        $out['type']     = strtolower(strstr($s, ':', true));
        $out['username'] = $m[1];
        $out['host']     = $m[2];
        $out['port']     = $m[3];
        if (preg_match('/#([^#\s]+)$/u', $m[4], $mm)) {
            $out['username'] = rawurldecode($mm[1]);
        }
        return $out;
    }

    if ($s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) {
            $out['username'] = (string)($j['username'] ?? $j['name'] ?? $j['remarks'] ?? '');
            $out['host']     = (string)($j['host']     ?? $j['server'] ?? $j['endpoint'] ?? '');
            $out['port']     = (string)($j['port']     ?? '');
        }
    }
    return $out;
}
function nmStockShelfStatusText($panelCode = null)
{
    global $pdo;
    if (!nmStockEnsureSchema()) return "❌ خطا در آماده‌سازی انبار";
    $lines=["📦 انبارداری شبکه‌ملی", "", "هر انبار به یک دسته/محصول وصل می‌شود؛ بنابراین ربات می‌فهمد کانفیگ واردشده مربوط به محصول ۱۰ گیگ، ۲۰ گیگ و ... است.", ""];
    $shelves = nmStockShelves($panelCode, false);
    if (!$shelves) return implode("\n", $lines)."❌ هنوز انباری تعریف نشده است.";
    foreach ($shelves as $shelf) {
        $cnt=0; $reserved=0; $delivered=0;
        try {
            $st=$pdo->prepare("SELECT status, COUNT(*) cnt FROM nm_config_stock WHERE shelf_id=:sid GROUP BY status");
            $st->execute([':sid'=>$shelf['id']]);
            while ($row=$st->fetch(PDO::FETCH_ASSOC)) {
                if (($row['status'] ?? '') === 'active') $cnt=(int)$row['cnt'];
                elseif (($row['status'] ?? '') === 'reserved') $reserved=(int)$row['cnt'];
                elseif (($row['status'] ?? '') === 'delivered') $delivered=(int)$row['cnt'];
            }
        } catch (Throwable $e) {}
        $lines[] = "• {$shelf['name']} | {$shelf['product_name']} | {$shelf['volume_gb']}GB | {$shelf['service_days']} روز | موجودی آماده: {$cnt} | تحویل‌شده: {$delivered} | رزرو: {$reserved}";
    }
    return implode("\n", $lines);
}
/* ---- business_logic_4.php ---- */
function nmEmergencyProductFor(array $sourceProduct, array $emergencyPanel)
{
    global $pdo;
    $loc = $emergencyPanel['name_panel'] ?? '';
    try {
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND code_product=:code LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':code'=>$sourceProduct['code_product'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND name_product=:name LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':name'=>$sourceProduct['name_product'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND Volume_constraint=:vol AND Service_time=:days ORDER BY CAST(price_product AS UNSIGNED) ASC LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':vol'=>$sourceProduct['Volume_constraint'] ?? '', ':days'=>$sourceProduct['Service_time'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
    } catch (Throwable $e) { error_log('nmEmergencyProductFor failed: '.redfox_exception_fingerprint($e)); }
    return $sourceProduct;
}
function nmEmergencyUserNotice()
{
    return "🚨 حالت پنل اضطراری روشن است. برای سرویس‌های پنل اصلی فعلاً امکان تمدید مستقیم همان کانفیگ وجود ندارد؛ در صورت نیاز می‌توانید سرویس جدید تهیه کنید یا تمدید را از مسیر پنل اضطراری/انبار انجام دهید.";
}


function nmServiceRestrictedNotice()
{
    return 'این سرویس در حال حاضر به دلیل شرایط اینترنت ملی در دسترس نیست !';
}

function nmRestrictedServiceKeyboard(array $invoice = null)
{
    global $textbotlang;
    $buttons = [];
    $invoiceId = is_array($invoice) ? trim((string)($invoice['id_invoice'] ?? '')) : '';
    if ($invoiceId !== '') {
        $buttons[] = [[
            'text' => '🛒 خرید سرویس اینترنت ملی',
            'callback_data' => 'nm_buy_service_' . $invoiceId,
        ]];
    }
    $buttons[] = [[
        'text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها',
        'callback_data' => 'backorder',
    ]];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmNationalBuyPanelKeyboard(array $oldInvoice, array $userRow = null)
{
    global $pdo, $textbotlang;
    $invoiceId = trim((string)($oldInvoice['id_invoice'] ?? ''));
    $agent = is_array($userRow) ? trim((string)($userRow['agent'] ?? '')) : '';
    if ($agent === '') $agent = 'all';
    $buttons = [];
    try {
        $where = "status='active'";
        $params = [];
        if ($agent !== 'all') {
            $where .= " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $stmt = $pdo->prepare("SELECT id, name_panel, code_panel FROM marzban_panel WHERE {$where} ORDER BY id ASC, name_panel ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $panel) {
            $panelId = trim((string)($panel['id'] ?? ''));
            $name = trim((string)($panel['name_panel'] ?? ''));
            if ($invoiceId === '' || $panelId === '' || $name === '') continue;
            $buttons[] = [[
                'text' => $name,
                'callback_data' => 'nm_buy_panel_' . $invoiceId . '_' . $panelId,
            ]];
        }
    } catch (Throwable $e) {
        error_log('nmNationalBuyPanelKeyboard failed: ' . redfox_exception_fingerprint($e));
    }
    if (empty($buttons)) {
        return false;
    }
    $buttons[] = [[
        'text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها',
        'callback_data' => 'backorder',
    ]];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmPanelById($panelId)
{
    $panelId = trim((string)$panelId);
    if ($panelId === '') return false;
    try {
        return select('marzban_panel', '*', 'id', $panelId, 'select');
    } catch (Throwable $e) {
        error_log('nmPanelById failed: ' . redfox_exception_fingerprint($e));
        return false;
    }
}

function nmGenerateNationalUsername($userId)
{
    try {
        $suffix = bin2hex(random_bytes(3));
    } catch (Throwable $e) {
        $suffix = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
    }
    return preg_replace('/[^A-Za-z0-9_]/', '', (string)$userId) . '_nm_' . $suffix;
}

function nmCreateNationalServiceFromInvoice(array $oldInvoice, array $userRow, array $selectedPanel = null)
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return ['status' => false, 'message' => '❌ اتصال دیتابیس در دسترس نیست.'];
    }
    if (!nmStockEnsureSchema()) {
        return ['status' => false, 'message' => '❌ جدول‌های انبار شبکه ملی آماده نیستند. table.php را یک‌بار اجرا کنید.'];
    }

    $userId = $userRow['id'] ?? ($oldInvoice['id_user'] ?? null);
    if ($userId === null || (string)$userId === '') {
        return ['status' => false, 'message' => '❌ اطلاعات کاربر پیدا نشد.'];
    }

    $sourcePanel = false;
    try {
        $sourcePanel = select('marzban_panel', '*', 'name_panel', $oldInvoice['Service_location'] ?? '', 'select');
    } catch (Throwable $e) {}
    if (!$sourcePanel) {
        return ['status' => false, 'message' => '❌ پنل سرویس قبلی پیدا نشد.'];
    }

    $targetPanel = is_array($selectedPanel) && !empty($selectedPanel) ? $selectedPanel : $sourcePanel;

    $sourceProduct = nmStockProductForInvoice($oldInvoice);
    $product = $sourceProduct;
    if ($targetPanel !== $sourcePanel && function_exists('nmEmergencyProductFor')) {
        $mapped = nmEmergencyProductFor($sourceProduct, $targetPanel);
        if (is_array($mapped) && $mapped) {
            $product = $mapped;
        }
    }
    if (!is_array($product) || empty($product)) {
        return ['status' => false, 'message' => '❌ محصول متناظر برای خرید اینترنت ملی پیدا نشد.'];
    }

    $price = (float)($product['price_product'] ?? ($oldInvoice['price_product'] ?? 0));
    $balance = (float)($userRow['Balance'] ?? 0);
    if ($price > 0 && $balance < $price && (($userRow['agent'] ?? '') !== 'n2')) {
        return ['status' => false, 'message' => '❌ موجودی کیف پول شما برای خرید سرویس اینترنت ملی کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'];
    }

    $invoiceId = bin2hex(random_bytes(4));
    $usernameAc = nmGenerateNationalUsername($userId);
    $exists = true;
    for ($i = 0; $i < 5 && $exists; $i++) {
        try {
            $exists = select('invoice', '*', 'username', $usernameAc, 'select');
        } catch (Throwable $e) {
            $exists = false;
        }
        if ($exists) $usernameAc = nmGenerateNationalUsername($userId);
    }

    $notifctions = json_encode(['volume' => false, 'time' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        $stmt = $pdo->prepare("INSERT INTO invoice (id_user,id_invoice,username,time_sell,Service_location,name_product,price_product,Volume,Service_time,Status,notifctions,source_panel_code) VALUES (:id_user,:id_invoice,:username,:time_sell,:service_location,:name_product,:price_product,:volume,:service_time,'Unpaid',:notifctions,:source_panel_code)");
        $stmt->execute([
            ':id_user' => (string)$userId,
            ':id_invoice' => $invoiceId,
            ':username' => $usernameAc,
            ':time_sell' => time(),
            ':service_location' => (string)($targetPanel['name_panel'] ?? ($sourcePanel['name_panel'] ?? '')),
            ':name_product' => (string)($product['name_product'] ?? ($oldInvoice['name_product'] ?? '')),
            ':price_product' => (string)$price,
            ':volume' => (string)($product['Volume_constraint'] ?? ($oldInvoice['Volume'] ?? 0)),
            ':service_time' => (string)($product['Service_time'] ?? ($oldInvoice['Service_time'] ?? 0)),
            ':notifctions' => $notifctions,
            ':source_panel_code' => (string)($sourcePanel['code_panel'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('nmCreateNationalServiceFromInvoice insert invoice failed: ' . redfox_exception_fingerprint($e));
        return ['status' => false, 'message' => '❌ ثبت فاکتور سرویس اینترنت ملی با خطا مواجه شد.'];
    }

    $stockPanelForReserve = $targetPanel;

    $chargeBalance = ($price > 0 && (($userRow['agent'] ?? '') !== 'n2'));
    $stock = nmStockCompleteBuyFromInventory($userId, $userRow, $stockPanelForReserve, $product, $invoiceId, $usernameAc, $chargeBalance, 'national_button_buy');
    if (!$stock && $targetPanel !== $sourcePanel) {
        $stock = nmStockCompleteBuyFromInventory($userId, $userRow, $sourcePanel, $product, $invoiceId, $usernameAc, $chargeBalance, 'national_button_buy');
    }
    if (!$stock) {
        try { update('invoice', 'Status', 'Unpaid', 'id_invoice', $invoiceId); } catch (Throwable $e) {}
        return ['status' => false, 'message' => '❌ موجودی انبار برای محصول متناظر این سرویس تمام شده است.'];
    }

    return [
        'status' => true,
        'message' => "✅ سرویس اینترنت ملی جدید با موفقیت از انبار تحویل شد.\n\n🗂 محصول: " . ($product['name_product'] ?? '') . "\n🔋 حجم: " . ($product['Volume_constraint'] ?? ($oldInvoice['Volume'] ?? 0)) . " گیگ\n⏳ مدت: " . ($product['Service_time'] ?? ($oldInvoice['Service_time'] ?? 0)) . " روز\n🧾 کد پیگیری: <code>{$invoiceId}</code>",
        'invoice_id' => $invoiceId,
    ];
}

function nmDecodeState($raw)
{
    if (is_array($raw)) return $raw;
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function nmResolvePanelFromUserState(array $user = null, $fallbackPanelName = null)
{
    $candidates = [];
    if ($user) {
        foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
            if (!array_key_exists($stateField, $user)) {
                continue;
            }
            $state = nmDecodeState($user[$stateField] ?? '');
            foreach (['namepanel', 'name_panel', 'panel', 'panel_name', 'code_panel', 'codepanel', 'stock_codepanel', 'source_codepanel'] as $key) {
                if (!empty($state[$key])) {
                    $candidates[] = trim((string)$state[$key]);
                }
            }

            if (!is_array($user[$stateField])) {
                $raw = trim((string)$user[$stateField]);
                if ($raw !== '' && $raw !== '0' && $raw !== 'none' && $raw[0] !== '{' && $raw[0] !== '[') {
                    $candidates[] = $raw;
                }
            }
        }
    }
    if ($fallbackPanelName !== null && trim((string)$fallbackPanelName) !== '') {
        $candidates[] = trim((string)$fallbackPanelName);
    }
    foreach (array_unique(array_filter($candidates)) as $candidate) {
        try {
            $panel = select('marzban_panel', '*', 'name_panel', $candidate, 'select');
            if ($panel) return $panel;
            $panel = select('marzban_panel', '*', 'code_panel', $candidate, 'select');
            if ($panel) return $panel;
            if (ctype_digit((string)$candidate)) {
                $panel = select('marzban_panel', '*', 'id', $candidate, 'select');
                if ($panel) return $panel;
            }
        } catch (Throwable $e) {}
    }
    return false;
}

function nmResolvePanelNameForUser(array $user = null, $fallback = null)
{
    if ($user) {
        foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
            if (!array_key_exists($stateField, $user)) continue;
            $raw = $user[$stateField];
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[' && $trim !== '0' && $trim !== 'none') {
                    return $trim;
                }
                $state = nmDecodeState($trim);
                foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                    if (!empty($state[$key])) return trim((string)$state[$key]);
                }
            } elseif (is_array($raw)) {
                foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                    if (!empty($raw[$key])) return trim((string)$raw[$key]);
                }
            }
        }
    }
    if ($fallback !== null) {
        $fallback = trim((string)$fallback);
        if ($fallback !== '') return $fallback;
    }
    $resolved = nmResolvePanelFromUserState($user, $fallback);
    if (is_array($resolved) && !empty($resolved['name_panel'])) return (string)$resolved['name_panel'];
    return '';
}


function nmNormalizeText($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\x{202a}-\x{202e}]/u', '', $value);
    $value = str_replace(["ي", "ك", "ة", "ۀ"], ["ی", "ک", "ه", "ه"], $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function nmProductButtonName($text)
{
    $text = nmNormalizeText($text);
    $text = preg_replace('/\s*-\s*[0-9۰-۹,،.]+\s*(?:تومان|ریال)?\s*$/u', '', $text);
    return nmNormalizeText($text);
}

function nmTableHasColumn($table, $column)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $stmt->execute([':t' => $table, ':c' => $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

function nmCategoryLookupValues($category)
{
    $values = [];
    $category = nmNormalizeText($category);
    if ($category === '' || $category === 'بدون دسته‌بندی') {
        return [];
    }

    $values[] = $category;
    $lookupColumns = [];
    foreach (['id', 'remark', 'name', 'title'] as $column) {
        if (function_exists('nmTableHasColumn') && nmTableHasColumn('category', $column)) {
            $lookupColumns[] = $column;
        }
    }

    foreach ($lookupColumns as $column) {
        if ($column === 'id' && !ctype_digit((string)$category)) {
            continue;
        }
        try {
            $row = select('category', '*', $column, $category, 'select');
            if (is_array($row) && $row) {
                foreach (['id', 'remark', 'name', 'title'] as $k) {
                    if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
                        $values[] = nmNormalizeText($row[$k]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('nmCategoryLookupValues lookup failed on category.' . $column . ': ' . redfox_exception_fingerprint($e));
        }
    }

    if (in_array('remark', $lookupColumns, true) && in_array('id', $lookupColumns, true)) {
        try {
            $row = select('category', '*', 'remark', $category, 'select');
            if (is_array($row) && isset($row['id'])) {
                $values[] = nmNormalizeText($row['id']);
            }
        } catch (Throwable $e) {}
    }

    return array_values(array_unique(array_filter($values, static function($v) { return trim((string)$v) !== ''; })));
}
function nmProductsForPanelCategory(array $panel, $agent = 'all', $category = null)
{
    global $pdo;
    $loc = trim((string)($panel['name_panel'] ?? ''));
    if ($loc === '' || !isset($pdo)) return [];

    $agent = trim((string)($agent ?? ''));
    $filterAgent = !in_array($agent, ['', 'all', '*', 'any'], true);
    $params = [
        ':loc_where' => $loc,
        ':loc_order' => $loc,
    ];
    $sql = "SELECT * FROM product WHERE (Location = :loc_where OR Location = '/all')";
    if ($filterAgent) {
        $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
        $params[':agent'] = $agent;
    }

    $catValues = nmCategoryLookupValues($category);
    if ($catValues) {
        $in = [];
        foreach ($catValues as $i => $value) {
            $key = ':cat' . $i;
            $in[] = $key;
            $params[$key] = $value;
        }
        $sql .= ' AND (category IN (' . implode(',', $in) . ')';
        if (nmTableHasColumn('product', 'category_id')) {
                $inId = [];
                foreach ($catValues as $j => $value) {
                    $key = ':cat_id' . $j;
                    $inId[] = $key;
                    $params[$key] = $value;
                }
                $sql .= ' OR category_id IN (' . implode(',', $inId) . ')';
            }
        $sql .= ')';
    }
    $sql .= " ORDER BY CASE WHEN Location = :loc_order THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows && $catValues) {
            $fallbackParams = [
                ':fallback_loc_where' => $loc,
                ':fallback_loc_order' => $loc,
            ];
            $fallbackSql = "SELECT * FROM product WHERE (Location = :fallback_loc_where OR Location = '/all')";
            if ($filterAgent) {
                $fallbackSql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
                $fallbackParams[':agent'] = $agent;
            }
            $fallbackSql .= " ORDER BY CASE WHEN Location = :fallback_loc_order THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute($fallbackParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return $rows;
    } catch (Throwable $e) {
        error_log('nmProductsForPanelCategory failed: ' . redfox_exception_fingerprint($e));
        return [];
    }
}

function nmProductByNameForShelf($productName, array $panel = null, $agent = null, $category = null)
{
    if (!$panel) return false;
    $wanted = nmProductButtonName($productName);
    if ($wanted === '' || $wanted === '❌ محصولی پیدا نشد') return false;
    $products = nmProductsForPanelCategory($panel, $agent ?: 'all', $category);
    foreach ($products as $product) {
        if (nmNormalizeText($product['name_product'] ?? '') === $wanted) return $product;
    }
    foreach ($products as $product) {
        $name = nmNormalizeText($product['name_product'] ?? '');
        if ($name !== '' && (mb_strpos($wanted, $name) !== false || mb_strpos($name, $wanted) !== false)) return $product;
    }
    $found = nmProductByNameForPanel($wanted, $panel['name_panel'] ?? '', $agent, $category);
    if ($found) return $found;
    $found = nmProductByNameForPanel($wanted, $panel['name_panel'] ?? '', $agent, null);
    if ($found) return $found;
    if (count($products) === 1) return $products[0];
    return false;
}

function nmServicePanelAccessBlocked(array $invoice = null){
    if (!$invoice) return false;
    $serviceLocation = trim((string)($invoice['Service_location'] ?? ''));
    if ($serviceLocation === '') return false;
    try {
        $panel = select('marzban_panel', '*', 'name_panel', $serviceLocation, 'select');
        if (!$panel && !empty($invoice['source_panel_code'])) $panel = select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select');
        if (!$panel) return false;
        return nmPanelNationalEnabled($panel) || nmPanelEmergencyEnabled($panel);
    } catch (Throwable $e) {
        error_log('nmServicePanelAccessBlocked failed: ' . redfox_exception_fingerprint($e));
        return false;
    }
}

function nmStopIfServicePanelBlocked($invoice, $userId = null, $keyboard = null)
{
    if (!is_array($invoice) || !nmServicePanelAccessBlocked($invoice)) return false;
    if ($keyboard === null && function_exists('nmRestrictedServiceKeyboard')) {
        $keyboard = nmRestrictedServiceKeyboard($invoice);
    }
    if (function_exists('sendmessage') && $userId !== null) {
        sendmessage($userId, nmServiceRestrictedNotice(), $keyboard, 'HTML');
    }
    return true;
}

function nmProductByCodeForPanel($codeProduct, $panelName, $agent = null)
{
    global $pdo;

    $codeProduct = trim((string) $codeProduct);
    $panelName = trim((string) $panelName);
    $agent = $agent === null ? null : trim((string) $agent);

    if (!($pdo instanceof PDO) || $codeProduct === '' || $panelName === '') {
        return false;
    }

    try {
        $sql = "SELECT * FROM product
                WHERE code_product = :code_product
                  AND (Location = :location_where OR Location = '/all')";
        $params = [
            ':code_product' => $codeProduct,
            ':location_where' => $panelName,
            ':location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :agent_where OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent_where'] = $agent;
            $params[':agent_order'] = $agent;
            $sql .= " ORDER BY
                        CASE WHEN Location = :location_order THEN 0 ELSE 1 END,
                        CASE WHEN agent = :agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END,
                        id ASC
                      LIMIT 1";
        } else {
            $sql .= " ORDER BY CASE WHEN Location = :location_order THEN 0 ELSE 1 END, id ASC LIMIT 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (Throwable $e) {
        error_log('nmProductByCodeForPanel failed: ' . redfox_exception_fingerprint($e));
        return false;
    }
}

function nmProductByNameForPanel($productName, $panelName, $agent = null, $category = null)
{
    global $pdo;
    try {
        $productName = nmProductButtonName($productName);
        $panelName = trim((string)$panelName);
        $params = [
            ':name_product' => $productName,
            ':location_where' => $panelName,
            ':location_order' => $panelName,
        ];
        $sql = "SELECT * FROM product WHERE name_product = :name_product AND (Location = :location_where OR Location = '/all')";
        $catValues = nmCategoryLookupValues($category);
        if ($catValues) {
            $in = [];
            foreach ($catValues as $i => $value) { $key=':cat'.$i; $in[]=$key; $params[$key]=$value; }
            $sql .= ' AND (category IN (' . implode(',', $in) . ')';
            if (nmTableHasColumn('product', 'category_id')) {
                $inId = [];
                foreach ($catValues as $j => $value) {
                    $key = ':cat_id' . $j;
                    $inId[] = $key;
                    $params[$key] = $value;
                }
                $sql .= ' OR category_id IN (' . implode(',', $inId) . ')';
            }
            $sql .= ')';
        }
        $agent = trim((string)($agent ?? ''));
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY CASE WHEN Location = :location_order THEN 0 ELSE 1 END LIMIT 1";
        $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;

        $rows = nmProductsForPanelCategory(['name_panel' => $panelName], $agent ?: 'all', $category);
        foreach ($rows as $r) if (nmNormalizeText($r['name_product'] ?? '') === $productName) return $r;
        foreach ($rows as $r) { $name = nmNormalizeText($r['name_product'] ?? ''); if ($name !== '' && (mb_strpos($productName, $name) !== false || mb_strpos($name, $productName) !== false)) return $r; }
        if (count($rows) === 1) return $rows[0];
        return false;
    } catch (Throwable $e) {
        error_log('nmProductByNameForPanel failed: ' . redfox_exception_fingerprint($e));
        return false;
    }
}

function nmAnyNationalNetEnabled()
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active' AND (national_net_status = 'on_national_net' OR emergency_panel_status = 'on_emergency_panel')");
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

if (!function_exists('nmAdminMaybeInlineKeyboard')) {
function nmAdminMaybeInlineKeyboard(array $keyboard)
{
    global $setting;
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == 'oninline' && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($keyboard);
    }
    return json_encode($keyboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
}

if (!function_exists('nmNationalAdminPanelKeyboard')) {
function nmNationalAdminPanelKeyboard($includeInactive = false)
{
    global $pdo;
    $rows = [];
    try {
        $where = $includeInactive ? '1=1' : "status = 'active'";
        $stmt = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE {$where} ORDER BY id ASC, name_panel ASC");
        $stmt->execute();
        while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim((string)($panel['name_panel'] ?? ''));
            if ($name !== '') {
                $rows[] = [['text' => $name]];
            }
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminPanelKeyboard failed: ' . redfox_exception_fingerprint($e));
    }
    if (!$rows) {
        $rows[] = [['text' => '❌ پنلی پیدا نشد']];
    }
    $rows[] = [['text' => '🏠 بازگشت به منوی مدیریت']];
    return nmAdminMaybeInlineKeyboard(['keyboard' => $rows, 'resize_keyboard' => true]);
}
}

if (!function_exists('nmNationalAdminCategoryKeyboard')) {
function nmNationalAdminCategoryKeyboard(array $panel = null, $agent = 'all')
{
    global $pdo;
    $labels = [];
    $backAdminText = $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $backMenuText  = $GLOBALS['textbotlang']['Admin']['backmenu']  ?? '▶️ بازگشت به منوی قبل';
    $reservedLabels = [
        'بدون دسته‌بندی',
        $backAdminText,
        $backMenuText,
        '🏠 بازگشت به منوی مدیریت',
        '▶️ بازگشت به منوی قبل',
        '🔙 بازگشت به انبار',
    ];
    $addLabel = static function ($value) use (&$labels, $reservedLabels) {
        $label = function_exists('nmNormalizeText') ? nmNormalizeText($value) : trim((string)$value);
        if ($label === '' || $label === '0') return;
        // Filter out anything that looks like a back/navigation button so it
        // doesn't get rendered as a fake category at the top of the list.
        foreach ($reservedLabels as $reserved) {
            if ($label === $reserved) return;
        }
        if (mb_strpos($label, 'بازگشت', 0, 'UTF-8') !== false) return;
        $labels[$label] = $label;
    };

    try {
        $categoryColumns = [];
        foreach (['remark', 'name', 'title', 'id'] as $column) {
            if (!function_exists('nmTableHasColumn') || nmTableHasColumn('category', $column)) {
                $categoryColumns[] = $column;
            }
        }
        $selectColumns = $categoryColumns ? implode(',', array_map(static function ($column) { return '`' . str_replace('`', '', $column) . '`'; }, $categoryColumns)) : '*';
        $stmt = $pdo->prepare("SELECT {$selectColumns} FROM category ORDER BY id ASC");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'name', 'title', 'id'] as $column) {
                if (!empty($row[$column])) {
                    $addLabel($row[$column]);
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminCategoryKeyboard category scan failed: ' . redfox_exception_fingerprint($e));
    }

    try {
        $params = [];
        $sql = "SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND TRIM(category) <> ''";
        if ($panel && !empty($panel['name_panel'])) {
            $sql .= " AND (Location = :loc_where OR Location = '/all')";
            $params[':loc_where'] = $panel['name_panel'];
        }
        $agent = trim((string)($agent ?? ''));
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY category ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($category = $stmt->fetchColumn()) {
            $values = nmCategoryLookupValues($category);
            $addLabel($values ? end($values) : $category);
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminCategoryKeyboard product scan failed: ' . redfox_exception_fingerprint($e));
    }

    $rows = [];
    foreach (array_values($labels) as $label) {
        $rows[] = [['text' => $label]];
    }
    $rows[] = [['text' => 'بدون دسته‌بندی']];
    $rows[] = [['text' => '🏠 بازگشت به منوی مدیریت']];
    return nmAdminMaybeInlineKeyboard(['keyboard' => $rows, 'resize_keyboard' => true]);
}
}

/**
 * Robustly resolve an active panel from a (possibly decorated) button label.
 * Tries: exact name -> style-emoji-stripped name -> normalized fuzzy match
 * against all active panels. Returns the panel row or false.
 * Prevents the national-net shelf flow from getting stuck on panel selection
 * (which would stop the admin from ever reaching the category step).
 */
if (!function_exists('nmResolveActivePanelByName')) {
function nmResolveActivePanelByName($name)
{
    global $pdo;
    $name = trim((string)$name);
    if ($name === '') return false;
    $p = select("marzban_panel", "*", "name_panel", $name, "select");
    if ($p) return $p;
    if (function_exists('stripReplyStyleEmoji')) {
        $alt = trim((string)stripReplyStyleEmoji($name));
        if ($alt !== '' && $alt !== $name) {
            $p = select("marzban_panel", "*", "name_panel", $alt, "select");
            if ($p) return $p;
        }
    }
    if (!($pdo instanceof PDO)) return false;
    try {
        $needle = function_exists('nmNormalizeText') ? nmNormalizeText($name) : $name;
        $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE status = 'active'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cand = function_exists('nmNormalizeText') ? nmNormalizeText($row['name_panel'] ?? '') : trim((string)($row['name_panel'] ?? ''));
            if ($cand === '') continue;
            if ($cand === $needle || mb_strpos($needle, $cand) !== false || mb_strpos($cand, $needle) !== false) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        error_log('nmResolveActivePanelByName failed: ' . redfox_exception_fingerprint($e));
    }
    return false;
}
}

/**
 * Count the real (non-navigation) categories that the admin category keyboard
 * would show for a given panel scope. Used for diagnostics so we can tell
 * whether "no categories" is a data problem or a flow problem.
 */
if (!function_exists('nmAdminCategoryCount')) {
function nmAdminCategoryCount(array $panel = null)
{
    global $pdo;
    if (!($pdo instanceof PDO)) return 0;
    $labels = [];
    $add = static function ($v) use (&$labels) {
        $l = function_exists('nmNormalizeText') ? nmNormalizeText($v) : trim((string)$v);
        if ($l === '' || $l === '0' || $l === 'بدون دسته‌بندی') return;
        if (mb_strpos($l, 'بازگشت', 0, 'UTF-8') !== false) return;
        $labels[$l] = true;
    };
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'name', 'title', 'id'] as $c) {
                if (!empty($row[$c])) { $add($row[$c]); break; }
            }
        }
    } catch (Throwable $e) {}
    try {
        $sql = "SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND TRIM(category) <> ''";
        $params = [];
        if ($panel && !empty($panel['name_panel'])) { $sql .= " AND (Location = :l OR Location = '/all')"; $params[':l'] = $panel['name_panel']; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($c = $stmt->fetchColumn()) { $add($c); }
    } catch (Throwable $e) {}
    return count($labels);
}
}

function nmNationalBuyCategoryKeyboard(array $panel, $agent, $back = 'buybacktow')
{
    if (function_exists('KeyboardCategory')) {
        return KeyboardCategory($panel['name_panel'] ?? '', $agent, $back);
    }
    return json_encode(['inline_keyboard' => [[['text' => '▶️ بازگشت به منوی قبل', 'callback_data' => $back]]]], JSON_UNESCAPED_UNICODE);
}

function nmStockAdminPanelStatusText(array $panel)
{
    $emergency = nmPanelEmergencyEnabled($panel) ? '✅ روشن' : '❌ خاموش';
    $national = nmPanelNationalEnabled($panel) ? '✅ روشن' : '❌ خاموش';
    $stockCode = nmPanelResolveStockCode($panel);
    $syncCount = count(nmStockPanelProducts($stockCode, 'all'));
    $text = '⚙️ وضعیت اضطراری و نت ملی پنل' . "\n\n";
    $text .= '🚨 پنل اضطراری: ' . $emergency . "\n";
    $text .= '🌐 وضعیت نت ملی: ' . $national . "\n";
    $emPanel = nmPanelEmergencyPanel($panel);
    $text .= '📌 پنل اضطراری متصل: ' . ($emPanel ? htmlspecialchars($emPanel['name_panel'], ENT_QUOTES, 'UTF-8') : 'تنظیم نشده') . "\n";
    $text .= '📦 کد انبار متصل: <code>' . htmlspecialchars((string)$stockCode, ENT_QUOTES, 'UTF-8') . '</code>' . "\n";
    $text .= '🛒 محصولات قابل همگام‌سازی: ' . (string)$syncCount . "\n\n";
    $text .= 'وقتی نت ملی روشن باشد، خرید جدید از انبار تحویل می‌شود. وقتی پنل اصلی قطع شود و پنل اضطراری روشن باشد، تمدید از پنل اضطراری یا انبار پشتیبان انجام می‌شود.';
    return $text;
}


function nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id)
{
    if (!function_exists('createServiceInfoCard')) {
        return null;
    }
    global $ManagePanel, $setting;
    try {
        if (!isset($ManagePanel) || !is_object($ManagePanel) || !method_exists($ManagePanel, 'DataUser')) {
            return null;
        }
        $name_panel = is_array($panel_info) ? ($panel_info['name_panel'] ?? '') : '';
        if ($name_panel === '') {
            return null;
        }
        $data = @$ManagePanel->DataUser($name_panel, $username_service);
        if (!is_array($data) || (isset($data['status']) && $data['status'] === 'Unsuccessful')) {
            return null;
        }
        $used  = (float)($data['used_traffic'] ?? 0);
        $total = (float)($data['data_limit']   ?? 0);
        $expire = $data['expire'] ?? 0;
        $unlimitedTime = empty($expire);
        $daysLeft = 0;
        if (!$unlimitedTime) {
            $diff = (int)$expire - time();
            $daysLeft = max(0, (int) floor($diff / 86400));
        }
        $statusVal = (string)($data['status'] ?? 'active');
        $isActive = in_array($statusVal, ['active', 'on_hold'], true);


        $botUsername = '';
        if (is_array($setting ?? null)) {
            foreach (['bot_username', 'username_bot', 'usernamebot', 'BotUsername', 'bot_user'] as $k) {
                if (isset($setting[$k]) && is_string($setting[$k]) && trim($setting[$k]) !== '') {
                    $botUsername = ltrim((string)$setting[$k], '@');
                    break;
                }
            }
        }
        if ($botUsername === '' && function_exists('telegram')) {
            $me = @telegram('getMe', []);
            if (is_array($me) && isset($me['result']['username'])) {
                $botUsername = (string)$me['result']['username'];
            }
        }

        $color = function_exists('getInfoCardColor') ? getInfoCardColor() : 'yellow';
        $params = [
            'config_name'    => (string)$username_service,
            'bot_username'   => $botUsername,
            'user_id'        => (string)$user_id,
            'active'         => $isActive,
            'used_bytes'     => $used,
            'total_bytes'    => $total,
            'days_left'      => $daysLeft,
            'unlimited_time' => $unlimitedTime,
        ];
        $outPath = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
            . DIRECTORY_SEPARATOR . 'infocard_' . $user_id . '_' . bin2hex(random_bytes(3)) . '.png';
        $written = createServiceInfoCard($params, $color, $outPath);
        if ($written === false) {
            return null;
        }
        return $written;
    } catch (\Throwable $e) {
        error_log('nm_renderInfoCardForInvoice failed: ' . redfox_exception_fingerprint($e));
        return null;
    }
}
/* ---- business_logic_5.php ---- */
function nm_appendInfoCardQrButton($existing, $invoice_id)
{
    $kb = ['inline_keyboard' => []];
    if (is_string($existing) && $existing !== '') {
        $decoded = json_decode($existing, true);
        if (is_array($decoded) && isset($decoded['inline_keyboard']) && is_array($decoded['inline_keyboard'])) {
            $kb = $decoded;
        }
    } elseif (is_array($existing) && isset($existing['inline_keyboard'])) {
        $kb = $existing;
    }
    $qrButton = ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $invoice_id];
    array_unshift($kb['inline_keyboard'], [$qrButton]);
    return json_encode($kb, JSON_UNESCAPED_UNICODE);
}


function nm_sendInfoCardsForServiceList($from_id, array $services)
{
    if (!function_exists('nm_renderInfoCardForInvoice') || !function_exists('telegram')) {
        return;
    }
    if (empty($services)) {
        return;
    }


    $cap = defined('INFOCARD_LIST_MAX') ? (int) INFOCARD_LIST_MAX : 8;
    $sent = 0;
    foreach ($services as $row) {
        if ($sent >= $cap) {
            break;
        }
        if (!is_array($row) || empty($row['username']) || empty($row['id_invoice'])) {
            continue;
        }
        $panel = select("marzban_panel", "*", "name_panel", $row['Service_location'] ?? '', "select");
        if (!is_array($panel)) {
            continue;
        }
        $cardPath = nm_renderInfoCardForInvoice($panel, $row['username'], $row['id_invoice'], $from_id);
        if ($cardPath === null) {
            continue;
        }
        $note = isset($row['note']) && $row['note'] !== '' ? ' | ' . $row['note'] : '';
        $caption = '✨ <b>' . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . '</b>'
            . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        $kb = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $row['id_invoice']],
                    ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $row['id_invoice']],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        try {
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($cardPath),
                'reply_markup' => $kb,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
            $sent++;
        } catch (\Throwable $e) {
            error_log('nm_sendInfoCardsForServiceList send failed: ' . redfox_exception_fingerprint($e));
        }
        @unlink($cardPath);
    }
}


function rxRenderPremiumEmojiPanel($from_id, $page = 1) {
    global $pdo, $setting;
    $pageSize = 10;
    $page = max(1, (int)$page);

    $rows = [];
    try {
        $stmt = $pdo->query("SELECT id, emoji, custom_emoji_id FROM premium_emojis ORDER BY id ASC");
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $rows[] = $r; }
        }
    } catch (Throwable $e) { $rows = []; }

    $total = count($rows);
    $totalPages = max(1, (int)ceil($total / $pageSize));
    if ($page > $totalPages) { $page = $totalPages; }
    $sliceStart = ($page - 1) * $pageSize;
    $pageItems = array_slice($rows, $sliceStart, $pageSize);

    $statusVal = (string)($setting['premium_emoji_status'] ?? '0');
    $statusText = ($statusVal === '1') ? "☑️ فعال" : "🚫 غیرفعال";

    $msg  = "🌟 <b>تنظیمات ایموجی پرمیوم</b>\n\n";
    $msg .= "وضعیت کلی: <b>{$statusText}</b>\n";
    $msg .= "تعداد ایموجی‌های ثبت‌شده: <b>{$total}</b>";
    if ($totalPages > 1) {
        $msg .= "  ·  📄 صفحه <b>{$page}</b> از <b>{$totalPages}</b>";
    }
    $msg .= "\n\n";
    if (empty($rows)) {
        $msg .= "📭 هنوز هیچ ایموجی‌ای ثبت نشده است.\n\n";
        $msg .= "برای افزودن، روی دکمه «➕ افزودن ایموجی جدید» بزنید.";
    } else {
        $msg .= "📋 <b>لیست ایموجی‌ها</b>\n\n";
        foreach ($pageItems as $it) {
            $em = (string)$it['emoji'];
            $cid = (string)$it['custom_emoji_id'];
            $cidShort = mb_strlen($cid) > 14 ? mb_substr($cid, 0, 14) . '…' : $cid;


            $msg .= "{$em} ↦ <code>{$cidShort}</code>\n";
        }
        $msg .= "\nبرای ویرایش/حذف، از دکمه‌های زیر استفاده کنید.";
    }

    $kb = ['inline_keyboard' => []];
    foreach ($pageItems as $it) {
        $em = (string)$it['emoji'];
        $id = (int)$it['id'];


        $kb['inline_keyboard'][] = [
            ['text' => "{$em}", 'callback_data' => "premium_emoji_noop"],
            ['text' => "✏️ ویرایش", 'callback_data' => "premium_emoji_edit_{$id}"],
            ['text' => "🗑 حذف", 'callback_data' => "premium_emoji_del_{$id}"],
        ];
    }
    if ($totalPages > 1) {
        $prevCb = ($page > 1) ? "premium_emoji_settings_" . ($page - 1) : "premium_emoji_noop";
        $prevTxt = ($page > 1) ? "« قبل" : "▫️";
        $nextCb = ($page < $totalPages) ? "premium_emoji_settings_" . ($page + 1) : "premium_emoji_noop";
        $nextTxt = ($page < $totalPages) ? "بعد »" : "▫️";
        $kb['inline_keyboard'][] = [
            ['text' => $prevTxt, 'callback_data' => $prevCb],
            ['text' => "📄 {$page} / {$totalPages}", 'callback_data' => "premium_emoji_noop"],
            ['text' => $nextTxt, 'callback_data' => $nextCb],
        ];
    }
    $kb['inline_keyboard'][] = [
        ['text' => "➕ افزودن ایموجی جدید", 'callback_data' => "premium_emoji_add"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => ($statusVal === '1' ? "🚫 خاموش کردن قابلیت" : "☑️ روشن کردن قابلیت"),
         'callback_data' => "editstsuts-premiumemoji-{$statusVal}"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت", 'callback_data' => "featcat_main"],
        ['text' => "🔄 رفرش", 'callback_data' => "premium_emoji_settings_{$page}"],
        ['text' => "❌ بستن", 'callback_data' => "close_stat"],
    ];


    global $callback_query_id, $message_id;
    $kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $delivered = false;

    if ($isCallback && function_exists('Editmessagetext')) {
        try {
            $editResult = Editmessagetext($from_id, $message_id, $msg, $kbJson, 'HTML');
            if (is_array($editResult) && !empty($editResult['ok'])) {
                $delivered = true;
                if (function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {
            error_log('rxRenderPremiumEmojiPanel Editmessagetext failed: ' . redfox_exception_fingerprint($e));
        }
    }


    if (!$delivered && function_exists('telegram')) {
        if ($isCallback) {
            try {
                $rawEdit = telegram('editmessagetext', [
                    'chat_id'      => $from_id,
                    'message_id'   => $message_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
                if (is_array($rawEdit) && !empty($rawEdit['ok'])) {
                    $delivered = true;
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 1,
                        ]);
                    } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        }
        if (!$delivered) {
            if ($isCallback && function_exists('deletemessage')) {
                try { @deletemessage($from_id, $message_id); } catch (Throwable $e) {}
            }
            try {
                telegram('sendmessage', [
                    'chat_id'      => $from_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
            } catch (Throwable $e) {
                error_log('rxRenderPremiumEmojiPanel send failed: ' . redfox_exception_fingerprint($e));
            }
        }
    }
}
if (!function_exists('crypto_supported_currencies')) {
    function crypto_supported_currencies(): array
    {
        return [
            'TRX'        => ['network' => 'TRON', 'decimals' => 6, 'label' => 'ترون (TRX)',           'fa_short' => 'ترون'],
            'TON'        => ['network' => 'TON',  'decimals' => 9, 'label' => 'تون (TON)',            'fa_short' => 'تون'],
            'USDT_TRC20' => ['network' => 'TRON', 'decimals' => 6, 'label' => 'تتر روی شبکه ترون',     'fa_short' => 'تتر-ترون'],
            'USDT_TON'   => ['network' => 'TON',  'decimals' => 6, 'label' => 'تتر روی شبکه تون',      'fa_short' => 'تتر-تون'],
        ];
    }
}

if (!function_exists('crypto_pay_setting')) {
    function crypto_pay_setting(string $name, string $default = ''): string
    {
        $row = function_exists('select') ? select('PaySetting', 'ValuePay', 'NamePay', $name, 'select') : null;
        if (is_array($row) && isset($row['ValuePay'])) {
            $v = trim((string) $row['ValuePay']);
            if ($v !== '') return $v;
        }
        return $default;
    }
}

if (!function_exists('crypto_extract_hash')) {


    function crypto_extract_hash($input): ?string
    {
        if (!is_string($input)) return null;
        $input = trim($input);
        if ($input === '') return null;

        $input = preg_replace('/\s+/u', ' ', $input);
        $input = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', (string) $input);
        $input = trim((string) $input);

        if (preg_match('~(?:tronscan\.org|tonscan\.org|tonviewer\.com|tonapi\.io)[^\s]*?/(?:tx|transaction|transactions|events)/([0-9a-fA-F]{64})~i', $input, $m)) {
            return strtolower($m[1]);
        }

        if (preg_match('/[0-9a-fA-F]{64}/', $input, $m)) {
            return strtolower($m[0]);
        }

        if (preg_match('~(?:tronscan\.org|tonscan\.org|tonviewer\.com|tonapi\.io)[^\s]*?/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{43,44}=?)~i', $input, $m)) {
            return $m[1];
        }
        if (preg_match('~/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{43,44}=?)(?:[/?#]|$)~', $input, $m)) {
            return $m[1];
        }

        if (preg_match('/^[A-Za-z0-9_\-+\/]{43}=?$/', $input)) {
            return $input;
        }

        return null;
    }
}

if (!function_exists('crypto_active_wallet')) {
    function crypto_active_wallet(string $currency): ?array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return null;
        try {
            $stmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE currency = :c AND enabled = 1 LIMIT 1");
            $stmt->execute([':c' => $currency]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            $row['wallet_address'] = trim((string) ($row['wallet_address'] ?? ''));
            if ($row['wallet_address'] === '') return null;
            return $row;
        } catch (Throwable $e) {
            error_log('[crypto] active_wallet: ' . redfox_exception_fingerprint($e));
            return null;
        }
    }
}

if (!function_exists('crypto_active_wallets')) {
    function crypto_active_wallets(): array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query("SELECT currency, network, wallet_address, label
                                   FROM crypto_wallets
                                  WHERE enabled = 1 AND wallet_address <> ''
                                  ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crypto_validate_address')) {
    function crypto_validate_address(string $address, string $network): bool
    {
        $address = trim($address);
        if ($network === 'TRON') {
            return (bool) preg_match('/^T[A-Za-z0-9]{33}$/', $address);
        }
        if ($network === 'TON') {


            if (preg_match('/^(?:EQ|UQ|kQ|Ef|Uf|0Q)[A-Za-z0-9_\-]{46}$/', $address)) return true;
            if (preg_match('/^-?\d+:[0-9a-fA-F]{64}$/', $address)) return true;
            return false;
        }
        return mb_strlen($address) >= 20 && mb_strlen($address) <= 200;
    }
}

if (!function_exists('crypto_save_wallet')) {
    function crypto_save_wallet(string $currency, string $address): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $network = $supported[$currency]['network'];
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled)
                                  VALUES (:c, :n, :a, :l, 1)
                                  ON DUPLICATE KEY UPDATE wallet_address = VALUES(wallet_address),
                                                          network        = VALUES(network),
                                                          enabled        = 1");
            $up->execute([
                ':c' => $currency,
                ':n' => $network,
                ':a' => $address,
                ':l' => $supported[$currency]['label'],
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('crypto_delete_wallet')) {

    function crypto_delete_wallet(string $currency): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets
                                    SET wallet_address = '', wallet_memo = '', enabled = 0
                                  WHERE currency = :c");
            $up->execute([':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] delete_wallet: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('crypto_invoice_button_style')) {
    function crypto_invoice_button_style(string $key): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['invoice_copy_buttons']) && is_array($all['invoice_copy_buttons'])) {
                            $cache = $all['invoice_copy_buttons'];
                        }
                    }
                } catch (Throwable $e) {  }
            }
        }
        $style = $cache[$key] ?? null;
        if ($style === null || $style === '' || $style === 'default') return null;
        return (string) $style;
    }
}

if (!function_exists('cm_apply_payment')) {
    function cm_apply_payment(string $orderId, int $finalIrr, array $payment, $adminId, $adminUsername, array $setting, $paymentreports, $messageId, $callbackQueryId, string $textInline = ''): void
    {
        $userRow = function_exists('select') ? select('user', '*', 'id', $payment['id_user'], 'select') : null;
        $oldBalance = is_array($userRow) ? (int) ($userRow['Balance'] ?? 0) : 0;
        $newBalance = $oldBalance + $finalIrr;
        if (function_exists('update')) {
            update('user', 'Balance', $newBalance, 'id', $payment['id_user']);
            update('Payment_report', 'payment_Status', 'paid', 'id_order', $orderId);
            update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
        }
        if (function_exists('crypto_record_verified_hash')) {
            crypto_record_verified_hash($orderId, 'manual_admin');
        }
        $coinAmt = rtrim(rtrim(number_format((float) ($payment['crypto_amount'] ?? 0), 9, '.', ''), '0'), '.');
        $explorerUrl = function_exists('crypto_explorer_url')
            ? crypto_explorer_url((string) ($payment['crypto_currency'] ?? ''), (string) ($payment['crypto_tx_hash'] ?? ''))
            : (string) ($payment['crypto_tx_hash'] ?? '');
        if (function_exists('sendmessage')) {
            sendmessage(
                (string) $payment['id_user'],
                "✅ <b>درخواست بررسی دستی شما تایید شد و کیف پول‌تان شارژ گردید.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "</b>\n"
                . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                . "💵 مبلغ شارژ شده: " . number_format($finalIrr) . " تومان\n"
                . "💰 موجودی جدید: " . number_format($newBalance) . " تومان\n"
                . "🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده تراکنش</a>",
                null,
                'HTML'
            );
        }
        if ($callbackQueryId && function_exists('telegram')) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => '✅ تایید شد و کیف پول شارژ شد.',
                'cache_time' => 1,
            ]);
        }
        if ($messageId && function_exists('Editmessagetext')) {
            $doneNote = "\n\n━━━━━━━━━━━━\n✅ <b>تایید شده</b> (مبلغ: " . number_format($finalIrr) . " تومان)\n👨‍💼 توسط ادمین: <code>{$adminId}</code>\n⏰ " . date('Y/m/d H:i:s');
            @Editmessagetext($adminId, $messageId, $textInline . $doneNote, null);
        }
        if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text'    => "✅ بررسی دستی پرداخت کریپتو تایید شد\n\n"
                    . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                    . "👤 کاربر: <code>{$payment['id_user']}</code>\n"
                    . "💎 ارز: " . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "\n"
                    . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                    . "💵 مبلغ شارژ: " . number_format($finalIrr) . " تومان\n"
                    . "👨‍💼 ادمین تاییدکننده: <code>{$adminId}</code> (@{$adminUsername})",
                'parse_mode' => 'HTML',
            ];
            if (!empty($paymentreports)) {
                $payload['message_thread_id'] = $paymentreports;
            }
            telegram('sendmessage', $payload);
        }
    }
}

if (!function_exists('crypto_save_wallet_memo')) {
    function crypto_save_wallet_memo(string $currency, string $memo): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets SET wallet_memo = :m WHERE currency = :c");
            $up->execute([':m' => $memo, ':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet_memo: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('crypto_get_irt_rates')) {


    function crypto_get_irt_rates(array $wantKeys = []): array
    {
        $providerSet = [];
        foreach ($wantKeys ?: ['TRX', 'TON', 'USDT'] as $k) {
            $u = strtoupper(trim((string) $k));
            if ($u === '') continue;
            if ($u === 'USDT')      $providerSet['USD'] = true;
            else                    $providerSet[$u] = true;
        }
        $providerKeys = array_keys($providerSet);

        $rates = [];
        if (!empty($providerKeys) && function_exists('requireTronRates')) {


            foreach ($providerKeys as $pk) {
                $r = requireTronRates([$pk]);
                if (!is_array($r)) continue;
                if ($pk === 'TRX' && isset($r['TRX']) && is_numeric($r['TRX']) && (float) $r['TRX'] > 0) {
                    $rates['TRX'] = (float) $r['TRX'];
                } elseif ($pk === 'TON' && isset($r['Ton']) && is_numeric($r['Ton']) && (float) $r['Ton'] > 0) {
                    $rates['TON'] = (float) $r['Ton'];
                } elseif ($pk === 'USD' && isset($r['USD']) && is_numeric($r['USD']) && (float) $r['USD'] > 0) {
                    $rates['USDT'] = (float) $r['USD'];
                }
            }
        }


        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT currency, rate_irt_override FROM crypto_wallets WHERE rate_irt_override IS NOT NULL AND rate_irt_override > 0");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cur = strtoupper((string) $row['currency']);
                    $key = ($cur === 'USDT_TRC20' || $cur === 'USDT_TON') ? 'USDT' : $cur;
                    if (!isset($rates[$key])) {
                        $rates[$key] = (float) $row['rate_irt_override'];
                    }
                }
            } catch (Throwable $e) {  }
        }
        return $rates;
    }
}

if (!function_exists('crypto_irt_rate_for')) {
    function crypto_irt_rate_for(string $currency): ?float
    {


        if ($currency === 'TRX') {
            $rates = crypto_get_irt_rates(['TRX']);
            return $rates['TRX'] ?? null;
        }
        if ($currency === 'TON') {
            $rates = crypto_get_irt_rates(['TON']);
            return $rates['TON'] ?? null;
        }
        if ($currency === 'USDT_TRC20' || $currency === 'USDT_TON') {
            $rates = crypto_get_irt_rates(['USDT']);
            return $rates['USDT'] ?? null;
        }
        return null;
    }
}

if (!function_exists('crypto_display_decimals')) {

    function crypto_display_decimals(string $currency): int
    {
        $defaults = [
            'TRX'        => 2,
            'USDT_TRC20' => 2,
            'USDT_TON'   => 2,
            'TON'        => 2,
        ];
        $cfg = crypto_pay_setting('cryptocheck_display_decimals_' . $currency, '');
        if ($cfg !== '' && ctype_digit($cfg)) {
            return max(2, min(6, (int) $cfg));
        }
        return $defaults[$currency] ?? 2;
    }
}

if (!function_exists('crypto_unique_amount')) {


    function crypto_unique_amount(float $baseCoinAmount, string $currency, ?int $extraDecimalsOverride = null, bool $iranianMode = false): float
    {
        $displayDecimals = crypto_display_decimals($currency);
        $scale = (int) pow(10, $displayDecimals);
        $maxNoiseCfg = crypto_pay_setting('cryptocheck_max_cent_noise', '');
        $maxNoise = ($maxNoiseCfg !== '' && ctype_digit($maxNoiseCfg)) ? (int) $maxNoiseCfg : 24;
        $maxNoise = max(1, min($maxNoise, $scale - 1));

        $baseUnits = (int) ceil($baseCoinAmount * $scale - 1e-9);
        if ($baseUnits < 1) $baseUnits = 1;

        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        $existing = [];
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT crypto_amount FROM Payment_report
                      WHERE crypto_currency = :c
                        AND payment_Status IN ('Unpaid','AwaitingHash')
                        AND crypto_amount IS NOT NULL"
                );
                $stmt->execute([':c' => $currency]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $units = (int) round((float) $row['crypto_amount'] * $scale);
                    $existing[$units] = true;
                }
            } catch (Throwable $e) {  }
        }

        for ($attempt = 0; $attempt < 64; $attempt++) {
            try { $rand = random_int(1, $maxNoise); }
            catch (Throwable $e) { $rand = mt_rand(1, $maxNoise); }
            $candidate = $baseUnits + $rand;
            if (!isset($existing[$candidate])) {
                return $candidate / $scale;
            }
        }

        $bump = $maxNoise + ((int) (microtime(true) * 1000) % max(1, $maxNoise)) + 1;
        return ($baseUnits + $bump) / $scale;
    }
}

if (!function_exists('crypto_create_invoice')) {
    function crypto_create_invoice($userId, int $amountIrt, string $currency, string $invoiceMeta = '', bool $iranianMode = false, ?string $source = null): array
    {
        $currencies = crypto_supported_currencies();
        if (!isset($currencies[$currency])) {
            return ['ok' => false, 'error' => 'currency-not-supported'];
        }
        $wallet = crypto_active_wallet($currency);
        if (!$wallet) {
            return ['ok' => false, 'error' => 'wallet-not-configured'];
        }
        $minIrt = (int) ($wallet['min_irt'] ?? 0);
        $maxIrt = (int) ($wallet['max_irt'] ?? 0);
        if ($minIrt > 0 && $amountIrt < $minIrt) {
            return ['ok' => false, 'error' => 'below-min', 'min' => $minIrt];
        }
        if ($maxIrt > 0 && $amountIrt > $maxIrt) {
            return ['ok' => false, 'error' => 'above-max', 'max' => $maxIrt];
        }

        $rate = crypto_irt_rate_for($currency);
        if ($rate === null || $rate <= 0) {
            return ['ok' => false, 'error' => 'rate-unavailable'];
        }
        $rawCoin = $amountIrt / $rate;
        $finalCoin = crypto_unique_amount($rawCoin, $currency, null, $iranianMode);

        global $connect;
        $orderId = bin2hex(random_bytes(6));
        $now = date('Y/m/d H:i:s');
        $statusUnpaid = 'Unpaid';


        $methodLabel = 'arze digital offline';
        $network = $currencies[$currency]['network'];

        try {
            $stmt = $connect->prepare(
                "INSERT INTO Payment_report
                    (id_user, id_order, time, price, payment_Status, Payment_Method,
                     id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_wallet_to, crypto_iranian_mode, source)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $userIdStr = (string) $userId;
            $amountIrtStr = (string) $amountIrt;
            $coinStr = number_format($finalCoin, $currencies[$currency]['decimals'], '.', '');
            $iranianModeStr = '0';
            $sourceStr = ($source !== null && $source !== '') ? $source : null;
            $stmt->bind_param(
                'sssssssssssss',
                $userIdStr, $orderId, $now, $amountIrtStr, $statusUnpaid, $methodLabel,
                $invoiceMeta, $currency, $network, $coinStr, $wallet['wallet_address'], $iranianModeStr, $sourceStr
            );
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[crypto] create_invoice: ' . redfox_exception_fingerprint($e));
            return ['ok' => false, 'error' => 'db-write-failed'];
        }

        $ttl = 1800;
        return [
            'ok' => true,
            'order_id'    => $orderId,
            'amount_coin' => $finalCoin,
            'wallet'      => $wallet['wallet_address'],
            'wallet_memo' => trim((string) ($wallet['wallet_memo'] ?? '')),
            'currency'    => $currency,
            'network'     => $network,
            'rate'        => $rate,
            'expires_at'  => time() + $ttl,
        ];
    }
}

if (!function_exists('crypto_check_tx_timestamp_after_invoice')) {
    function crypto_check_tx_timestamp_after_invoice(int $txTimestamp, int $invoiceCreatedAt, int $toleranceSec = 120): bool
    {
        if ($txTimestamp <= 0 || $invoiceCreatedAt <= 0) return true;
        return $txTimestamp >= ($invoiceCreatedAt - $toleranceSec);
    }
}

if (!function_exists('crypto_check_sender_lock')) {
    function crypto_check_sender_lock(string $senderAddress, string $currency, string $telegramUserId): array
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        try {
            $q = $pdo->prepare(
                "SELECT telegram_user_id FROM crypto_sender_locks
                  WHERE sender_address = :s AND currency = :c
                  LIMIT 1"
            );
            $q->execute([':s' => $senderAddress, ':c' => $currency]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['ok' => true, 'first_use' => true, 'locked_to' => null];
            }
            $lockedTo = (string) ($row['telegram_user_id'] ?? '');
            if ($lockedTo === $telegramUserId) {
                return ['ok' => true, 'first_use' => false, 'locked_to' => $lockedTo];
            }
            return ['ok' => false, 'first_use' => false, 'locked_to' => $lockedTo];
        } catch (Throwable $e) {
            error_log('[crypto] check_sender_lock: ' . redfox_exception_fingerprint($e));
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
    }
}

if (!function_exists('crypto_record_sender_lock')) {
    function crypto_record_sender_lock(string $senderAddress, string $currency, string $telegramUserId): bool
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $ins = $pdo->prepare(
                "INSERT INTO crypto_sender_locks (sender_address, currency, telegram_user_id, last_used_at, use_count)
                 VALUES (:s, :c, :u, CURRENT_TIMESTAMP, 1)
                 ON DUPLICATE KEY UPDATE
                    last_used_at = CURRENT_TIMESTAMP,
                    use_count = use_count + 1"
            );
            $ins->execute([':s' => $senderAddress, ':c' => $currency, ':u' => $telegramUserId]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] record_sender_lock: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('crypto_record_verified_hash')) {
    function crypto_record_verified_hash(string $orderId, string $source = 'auto_cron'): bool
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $row = function_exists('select') ? select('Payment_report', '*', 'id_order', $orderId, 'select') : null;
            if (!is_array($row)) return false;
            $hash = trim((string)($row['crypto_tx_hash'] ?? ''));
            if ($hash === '') return false;
            $ins = $pdo->prepare(
                "INSERT IGNORE INTO crypto_verified_hashes
                 (tx_hash, currency, network, wallet_to, sender_address, amount_coin, amount_irr, order_id, user_id, verification_source)
                 VALUES (:h, :c, :n, :w, :s, :ac, :ai, :o, :u, :src)"
            );
            $walletTo = (string)($row['crypto_wallet_to'] ?? '');
            $senderAddr = (string)($row['crypto_sender_address'] ?? '');
            $userIdStr = (string)($row['id_user'] ?? '');
            $ins->execute([
                ':h' => $hash,
                ':c' => (string)($row['crypto_currency'] ?? ''),
                ':n' => (string)($row['crypto_network'] ?? ''),
                ':w' => $walletTo !== '' ? $walletTo : null,
                ':s' => $senderAddr !== '' ? $senderAddr : null,
                ':ac' => $row['crypto_amount'] ?? null,
                ':ai' => (int)($row['price'] ?? 0),
                ':o' => $orderId,
                ':u' => $userIdStr !== '' ? $userIdStr : null,
                ':src' => $source,
            ]);
            return $ins->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[crypto] record_verified_hash: ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('crypto_lookup_verified_hash')) {
    function crypto_lookup_verified_hash(string $hash): ?array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO) || trim($hash) === '') return null;
        try {
            $q = $pdo->prepare("SELECT * FROM crypto_verified_hashes WHERE tx_hash = :h LIMIT 1");
            $q->execute([':h' => trim($hash)]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('crypto_attach_hash')) {
    function crypto_attach_hash(string $orderId, string $hashOrUrl, string $userId): array
    {
        $hash = crypto_extract_hash($hashOrUrl);
        if ($hash === null) {
            return ['ok' => false, 'error' => 'invalid-hash'];
        }
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'error' => 'no-db'];
        }
        try {
            $dup = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h AND id_order <> :o LIMIT 1");
            $dup->execute([':h' => $hash, ':o' => $orderId]);
            if ($dup->fetch()) {
                return ['ok' => false, 'error' => 'hash-already-used'];
            }
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                 SET crypto_tx_hash = :h, crypto_hash_at = :t, payment_Status = 'AwaitingHash'
                 WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','AwaitingHash')"
            );
            $stmt->execute([':h' => $hash, ':t' => time(), ':o' => $orderId, ':u' => $userId]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'error' => 'order-not-pending'];
            }
            return ['ok' => true, 'hash' => $hash];
        } catch (Throwable $e) {
            error_log('[crypto] attach_hash: ' . redfox_exception_fingerprint($e));
            return ['ok' => false, 'error' => 'db-update-failed'];
        }
    }
}

if (!function_exists('crypto_http_get_json')) {
    function crypto_http_get_json(string $url, array $headers = [], int $timeoutMs = 7000): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => 4000,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'User-Agent: CryptoHashChecker/1.0',
            ], $headers),
        ]);
        $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
        if (empty($policy['ok'])) { curl_close($ch); return null; }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = (int)curl_errno($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            error_log("[crypto] provider request failed HTTP={$code} errno={$errno}");
            return null;
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) return null;
        return $json;
    }
}

if (!function_exists('crypto_amount_within_tolerance')) {


    function crypto_amount_within_tolerance(float $expected, float $observed, bool $iranianMode = false): bool
    {
        if ($expected <= 0) return false;

        $shortPct = (float) crypto_pay_setting('cryptocheck_amount_tolerance', '0');
        $shortPct = max(0.0, min($shortPct, 5.0));

        $overPct = (float) crypto_pay_setting('cryptocheck_overpay_tolerance', '0');
        $overPct = max(0.0, min($overPct, 100.0));

        $shortTol = $expected * ($shortPct / 100.0);
        $overTol  = $expected * ($overPct / 100.0);


        $eps = max(abs($expected), abs($observed)) * 1e-9;

        $diff = $observed - $expected;
        if ($diff >= -($shortTol + $eps) && $diff <= ($overTol + $eps)) {
            return true;
        }
        return false;
    }
}


if (!function_exists('crypto_check_tron_tx')) {
    function crypto_check_tron_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $tokenContract = null, bool $iranianMode = false): array
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
            return ['ok' => false, 'reason' => 'bad-hash-format'];
        }
        $expectedTo = trim($expectedTo);
        if ($expectedTo === '') {
            return ['ok' => false, 'reason' => 'no-expected-recipient'];
        }

        $url = 'https://apilist.tronscanapi.com/api/transaction-info?hash=' . urlencode($hash);
        $key = crypto_pay_setting('cryptocheck_trongrid_key', '');
        $headers = [];
        if ($key !== '') $headers[] = 'TRON-PRO-API-KEY: ' . $key;

        $tx = crypto_http_get_json($url, $headers);
        if ($tx === null || empty($tx)) {
            return ['ok' => false, 'reason' => 'api-unreachable'];
        }
        if (empty($tx['hash'])) {
            return ['ok' => false, 'reason' => 'tx-not-found'];
        }
        $confirmed = (int) ($tx['confirmed'] ?? 0) === 1;
        if (!$confirmed && empty($tx['confirmations'])) {
            return ['ok' => false, 'reason' => 'tx-not-confirmed'];
        }
        if (isset($tx['contractRet']) && $tx['contractRet'] !== 'SUCCESS') {
            return ['ok' => false, 'reason' => 'tx-failed', 'detail' => $tx];
        }

        $txTimestampMs = (int) ($tx['timestamp'] ?? 0);
        $txTimestampSec = $txTimestampMs > 0 ? (int) floor($txTimestampMs / 1000) : 0;

        if ($tokenContract === null) {
            $contractType = (int) ($tx['contractType'] ?? 1);
            if ($contractType !== 1) {
                return ['ok' => false, 'reason' => 'not-trx-transfer'];
            }
            $to = trim((string) ($tx['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $amountSun = (float) ($tx['contractData']['amount'] ?? 0);
            $amountTrx = $amountSun / 1000000.0;
            if (!crypto_amount_within_tolerance($expectedAmount, $amountTrx, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amountTrx, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($tx['ownerAddress'] ?? $tx['contractData']['owner_address'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTrx, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
        }


        $transfers = $tx['tokenTransferInfo'] ?? null;
        if (!is_array($transfers)) {
            $transfers = $tx['trc20TransferInfo'] ?? [];
            if (!is_array($transfers)) $transfers = [];
            if (isset($transfers['contract_address'])) {
                $transfers = [$transfers];
            }
        } else {
            $transfers = [$transfers];
        }
        foreach ($transfers as $t) {
            if (!is_array($t)) continue;
            $contract = trim((string) ($t['contract_address'] ?? $t['contractAddress'] ?? ''));
            if ($contract === '' || strcasecmp($contract, $tokenContract) !== 0) continue;
            $to = trim((string) ($t['to_address'] ?? $t['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $decimals = (int) ($t['decimals'] ?? 6);
            $raw = (string) ($t['amount_str'] ?? $t['amount'] ?? '0');
            $amount = (float) $raw / pow(10, $decimals);
            if (!crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amount, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($t['from_address'] ?? $t['fromAddress'] ?? $tx['ownerAddress'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
        }
        return ['ok' => false, 'reason' => 'no-matching-trc20-transfer'];
    }
}

/* ════════════════════════════════════════════════════════════════════
   Red Fox — پشتیبانی هوش مصنوعی (فاز ۱)
   رابط OpenAI-compatible: با OpenAI/Gemini/Claude/Grok/Qwen/Kimi/OpenRouter/Ollama کار می‌کند.
   ════════════════════════════════════════════════════════════════════ */

if (!function_exists('redfox_ai_config')) {
    function redfox_ai_config() {
        global $pdo;
        $defaults = [
            'status'                => 'off',
            'api_url'               => 'https://api.openai.com/v1/chat/completions',
            'api_key'               => '',
            'model'                 => 'gpt-4o-mini',
            'system_prompt'         => "تو دستیار پشتیبانی یک فروشگاه اشتراک VPN هستی و به فارسی محترمانه و کوتاه پاسخ می‌دهی.\nقوانین:\n۱) فقط سؤالات عمومی (نحوه‌ی اتصال، تفاوت پلن‌ها، روش پرداخت، سؤالات متداول) را جواب بده.\n۲) اگر سؤال مربوط به حسابِ شخصی کاربر (موجودی، وضعیت سرویس، رسید، بازگشت وجه، شارژ) است یا مطمئن نیستی، در ابتدای پاسخ دقیقاً بنویس «[ESCALATE]» و سپس فقط یک جمله کوتاه بنویس که کاربر را به ادمین ارجاع می‌دهی.\n۳) در غیر این صورت [ESCALATE] را هرگز ننویس.\n۴) هرگز مبلغ یا قیمتی را اگر مطمئن نیستی نگو.\n۵) هرگز قول بازگشت وجه یا تغییر حساب نده.",
            'escalate_keywords'     => "ادمین,انسانی,اپراتور,بشر,شارژ کن,موجودی,رسید,بازگشت وجه,پرداخت,شارژکیف پول,گزارش",
            'max_history'           => 4,
            'time_window'           => 6,
            'confidence_escalation' => 'on',
        ];
        try {
            $s = $pdo->prepare("SELECT ai_support AS v FROM setting LIMIT 1");
            $s->execute();
            $raw = $s->fetchColumn();
            $raw = (string)$raw;
            if ($raw === '' || $raw === '0') {
                error_log('[redfox_ai_config] ai_support is empty in DB');
                return $defaults;
            }
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $result = array_merge($defaults, $dec);
                error_log('[redfox_ai_config] configuration loaded');
                return $result;
            }
            error_log('[redfox_ai_config] stored JSON is invalid');
        } catch (Throwable $e) {
            error_log('[redfox_ai_config] EXCEPTION: ' . redfox_exception_fingerprint($e));
        }
        return $defaults;
    }
}

if (!function_exists('redfox_ai_should_escalate')) {
    function redfox_ai_should_escalate($text, $cfg) {
        $kws = array_filter(array_map('trim', explode(',', (string)($cfg['escalate_keywords'] ?? ''))));
        foreach ($kws as $kw) {
            if ($kw !== '' && function_exists('mb_stripos') && mb_stripos((string)$text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('redfox_ai_log')) {
    function redfox_ai_log($userId, $role, $message, $escalated = 0) {
        global $pdo;
        try {
            $pdo->prepare("INSERT INTO ai_support_log (id_user, role, message, created_at, escalated) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $role, mb_strimwidth((string)$message, 0, 4000), date('Y-m-d H:i:s'), $escalated ? 1 : 0]);
        } catch (Throwable $e) {
            error_log('[redfox_ai_log] ' . redfox_exception_fingerprint($e));
        }
    }
}

if (!function_exists('redfox_ai_learn')) {
    // فاز ۳: ذخیره‌ی یک جفت سؤال/پاسخ در پایگاه دانش (یادگیری هوش مصنوعی)
    function redfox_ai_learn($question, $answer, $source = 'admin') {
        global $pdo;
        $question = trim((string)$question);
        $answer = trim((string)$answer);
        if ($question === '' || $answer === '') return false;
        try {
            $pdo->prepare("INSERT INTO ai_knowledge (question, answer, source, enabled, created_at) VALUES (?, ?, ?, 1, ?)")
                ->execute([$question, $answer, $source, date('Y-m-d H:i:s')]);
            return true;
        } catch (Throwable $e) {
            error_log('[redfox_ai_learn] ' . redfox_exception_fingerprint($e));
            return false;
        }
    }
}

if (!function_exists('redfox_ai_relevant_kb')) {
    // فاز ۳: بازیابی مرتبط‌ترین دانش بر اساس هم‌پوشانی کلمات (RAG-lite، بدون embedding)
    // بازیابی مرتبط‌ترین دانش — روش ترکیبی: LIKE + هم‌پوشانی کلمات
    function redfox_ai_relevant_kb($userMessage, $limit = 5) {
        global $pdo;
        $limit = max(1, min(10, (int)$limit));

        // مرحله ۱: جستجوی LIKE در دیتابیس (بسیار سریع‌تر و دقیق‌تر از PHP)
        $results = [];
        try {
            // تبدیل پیام به کلمات کلیدی برای LIKE
            $msgClean = preg_replace('/[\p{P}\p{S}\d]+/u', ' ', $userMessage);
            $msgClean = preg_replace('/\s+/', ' ', trim($msgClean));
            $words = explode(' ', $msgClean);
            $stop = ['و','در','به','از','که','این','است','را','با','برای','یا','ها','های','شد','میشه','کنید','بود','آن','یک','تا','هم','اما','اگر','چه','می','کرد','هست','ما','شما','من','روی','نه','بله','خب','پس','لذا','بنابراین'];
            $needles = [];
            foreach ($words as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 2 && !in_array($w, $stop, true)) {
                    $needles[] = mb_strtolower($w, 'UTF-8');
                }
            }

            if (!empty($needles)) {
                // ساخت کوئری LIKE با OR
                $likeParts = [];
                $likeParams = [];
                foreach ($needles as $n) {
                    $likeParts[] = "LOWER(question) LIKE ? OR LOWER(answer) LIKE ?";
                    $likeParams[] = '%' . $n . '%';
                    $likeParams[] = '%' . $n . '%';
                }
                $likeSql = "SELECT id, question, answer FROM ai_knowledge WHERE enabled = 1 AND (" . implode(' OR ', $likeParts) . ") ORDER BY id DESC LIMIT " . ($limit * 3);
                $st = $pdo->prepare($likeSql);
                $st->execute($likeParams);
                $likeResults = $st->fetchAll(PDO::FETCH_ASSOC);

                // امتیازدهی بر اساس تعداد کلمات مطابق
                $scored = [];
                foreach ($likeResults as $r) {
                    $hay = mb_strtolower((string)$r['question'] . ' ' . (string)$r['answer'], 'UTF-8');
                    $score = 0;
                    foreach ($needles as $n) {
                        if (mb_strpos($hay, $n) !== false) $score++;
                    }
                    $scored[] = ['score' => $score, 'row' => $r];
                }
                usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });
                foreach (array_slice($scored, 0, $limit) as $s) $results[] = $s['row'];
            }
        } catch (Throwable $e) {
            error_log('[redfox_ai_relevant_kb] LIKE search failed: ' . redfox_exception_fingerprint($e));
        }

        // مرحله ۲: اگر LIKE چیزی پیدا نکرد، ۳ مورد اخیر را برگردان
        if (empty($results)) {
            try {
                $st2 = $pdo->query("SELECT id, question, answer FROM ai_knowledge WHERE enabled = 1 ORDER BY id DESC LIMIT " . $limit);
                $results = $st2->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }

        return $results;
    }
}

if (!function_exists('redfox_ai_ask')) {
    function redfox_ai_ask($userMessage, $userId, $cfg) {
        global $pdo;
        $sys = (string)($cfg['system_prompt'] ?? '');

        // ── Red Fox: همیشه تمام دانش را به AI بده ──
        // مدل خودش بهترین موتور جستجوی معنایی است — نیازی به LIKE در PHP نیست
        $allKb = [];
        try {
            $st = $pdo->query("SELECT question, answer FROM ai_knowledge WHERE enabled = 1 ORDER BY id DESC LIMIT 40");
            $allKb = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        if (!empty($allKb)) {
            $kbText = "\n\n📚 پایگاه دانش شما (مجموعه‌ای از سؤالات و پاسخ‌ها):";
            $kbText .= "\n⚠️ دستورالعمل مهم: کاربر ممکن است سؤالش را با کلمات متفاوت بپرسد.";
            $kbText .= "\nشما باید معنای سؤال کاربر را بفهمید و در میان تمام موارد زیر بهترین پاسخ مرتبط را پیدا کنید.";
            $kbText .= "\nحتی اگر کلمات کاربر دقیقاً با کلمات پایگاه دانش یکی نباشد، اگر موضوع و معنا مرتبط است، همان پاسخ را بدهید.";
            $kbText .= "\nمثال: اگر کاربر می‌پرسد «سرویسم وصل نمیشه» و در پایگاه دانش «سرویس منقضی نشده ولی وصل نمیشه» وجود دارد، باید همان پاسخ را بدهید.";
            $kbText .= "\nفقط در صورتی [ESCALATE] بنویسید که هیچ مورد مرتبطی در پایگاه دانش نباشد و سؤال واقعاً نیاز به بررسی ادمین داشته باشد.\n";
            foreach ($allKb as $i => $k) {
                $q = trim((string)($k['question'] ?? ''));
                $a = mb_strimwidth(trim((string)($k['answer'] ?? '')), 0, 1000, '…', 'UTF-8');
                $kbText .= "\n[" . ($i + 1) . "] سؤال/موضوع: " . $q . "\nپاسخ: " . $a;
            }
            $sys .= $kbText;
        }

        $messages = [['role' => 'system', 'content' => $sys]];
        try {
            $maxh = max(1, min(10, (int)($cfg['max_history'] ?? 4)));
            $tw = (int)($cfg['time_window'] ?? 6);
            if ($tw > 0) {
                $cutoff = date('Y-m-d H:i:s', time() - $tw * 3600);
                $h = $pdo->prepare("SELECT role, message FROM ai_support_log WHERE id_user = ? AND role IN ('user','assistant') AND created_at >= ? ORDER BY id DESC LIMIT " . $maxh);
                $h->execute([$userId, $cutoff]);
            } else {
                $h = $pdo->prepare("SELECT role, message FROM ai_support_log WHERE id_user = ? AND role IN ('user','assistant') ORDER BY id DESC LIMIT " . $maxh);
                $h->execute([$userId]);
            }
            $rows = array_reverse($h->fetchAll(PDO::FETCH_ASSOC));
            foreach ($rows as $r) {
                $messages[] = ['role' => $r['role'], 'content' => (string)$r['message']];
            }
        } catch (Throwable $e) {}

        $messages[] = ['role' => 'user', 'content' => (string)$userMessage];
        $payload = json_encode([
            'model'       => (string)($cfg['model'] ?? 'gpt-4o-mini'),
            'messages'    => $messages,
            'temperature' => 0.3,
            'max_tokens'  => 600,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init((string)$cfg['api_url']);
        if (function_exists('redfox_apply_curl_proxy')) {
            redfox_apply_curl_proxy($ch, 'panel');
        }
        // هدرهای OpenRouter
        $rxHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string)$cfg['api_key'],
        ];
        // OpenRouter نیاز به این هدرها دارد
        if (strpos((string)$cfg['api_url'], 'openrouter.ai') !== false) {
            $rxHeaders[] = 'HTTP-Referer: https://vpbotn.ir';
            $rxHeaders[] = 'X-Title: Red Fox VPN Bot';
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $rxHeaders,
        ]);
        $policy = redfox_apply_curl_url_policy($ch, (string)$cfg['api_url'], false, false);
        if (empty($policy['ok'])) { curl_close($ch); return null; }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$resp) {
            error_log('[redfox_ai_ask] provider request failed HTTP=' . (int)$code);
            return null;
        }
        $j = json_decode((string)$resp, true);
        if (!is_array($j)) {
            return null;
        }
        // رابط OpenAI-compatible
        $reply = $j['choices'][0]['message']['content'] ?? null;
        // برخی providerها فرمت متفاوت دارند (مثلاً message.content در ریشه)
        if (!is_string($reply)) {
            $reply = $j['message']['content'] ?? ($j['response'] ?? null);
        }
        return (is_string($reply) && trim($reply) !== '') ? trim($reply) : null;
    }
}

if (!function_exists('redfox_ai_forward_to_admin')) {
    // ارجاع یک تیکت به ادمینِ دپارتمان، با دکمه‌ی «پاسخ به کاربر». (فاز ۲)
    function redfox_ai_forward_to_admin($tracking, $note = '') {
        $trakingdetail = function_exists('select') ? select('support_message', '*', 'Tracking', $tracking, 'select') : null;
        if (!is_array($trakingdetail)) {
            return false;
        }
        if (function_exists('update')) {
            update('support_message', 'status', 'Unseen', 'Tracking', $tracking);
        }
        $noteLine = ($note !== '') ? "\n🔖 دلیل: $note" : '';
        $textfwd = "📣 ارجاع به ادمین (پس از تعامل با هوش مصنوعی){$noteLine}\n\n🪪 آیدی کاربر: <a href=\"tg://user?id={$trakingdetail['iduser']}\">{$trakingdetail['iduser']}</a>\n📁 دپارتمان: {$trakingdetail['name_departman']}\n\n📝 سؤال اولیه:\n{$trakingdetail['text']}\n\n🤖 پاسخ هوش مصنوعی:\n" . (string)($trakingdetail['result'] ?? '—');
        $kb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به کاربر', 'callback_data' => 'Responsesupport_' . $tracking]]]]);
        if (function_exists('sendmessage')) {
            sendmessage($trakingdetail['idsupport'], $textfwd, $kb, 'HTML');
        }
        return true;
    }
}

if (!function_exists('redfox_ai_handle_support')) {
    // اگر هوش مصنوعی جواب داد → true برمی‌گرداند (و خودش به کاربر پاسخ می‌دهد).
    // در غیر این صورت (خاموش/کلمه‌ی ارجاع/خطا) → false (جریان عادیِ ارسال به ادمین اجرا می‌شود).
    function redfox_ai_handle_support($from_id, $username, $text, $departeman, $tracking) {
        $cfg = redfox_ai_config();
        // Red Fox: دیباگ لاگ
        error_log('[redfox_ai] support request received');
        
        if (($cfg['status'] ?? 'off') !== 'on') {
            error_log('[redfox_ai] SKIPPED: status is not "on"');
            return false;
        }
        if ($cfg['api_key'] === '' || $cfg['api_url'] === '') {
            error_log('[redfox_ai] SKIPPED: api_key or api_url empty');
            return false;
        }
        // کلمات ارجاع → مستقیم به ادمین
        if (redfox_ai_should_escalate($text, $cfg)) {
            redfox_ai_log($from_id, 'user', $text, 1);
            return false;
        }
        redfox_ai_log($from_id, 'user', $text, 0);

        $reply = redfox_ai_ask($text, $from_id, $cfg);
        if ($reply === null) {
            // هوش مصنوعی جواب نداد → ارجاع به ادمین
            error_log('[redfox_ai] API returned null — escalating to admin');
            return false;
        }
        error_log('[redfox_ai] provider reply accepted');

        // ── فاز ۲: ارجاع هوشمند بر اساس اطمینان (نشانگر [ESCALATE]) ──
        $isMarkerEsc = false;
        if (($cfg['confidence_escalation'] ?? 'on') === 'on' && stripos($reply, '[ESCALATE]') !== false) {
            $isMarkerEsc = true;
            $reply = trim(str_ireplace('[ESCALATE]', '', $reply));
        }
        if ($isMarkerEsc) {
            redfox_ai_log($from_id, 'assistant', '[ارجاع خودکار] ' . $reply, 1);
            global $pdo;
            try {
                $pdo->prepare("UPDATE support_message SET status = 'Unseen', result = ? WHERE Tracking = ?")
                    ->execute(['[ارجاع خودکار هوش مصنوعی] ' . $reply, $tracking]);
            } catch (Throwable $e) {}
            redfox_ai_forward_to_admin($tracking, 'هوش مصنوعی نامطمئن بود');
            if (function_exists('sendmessage')) {
                sendmessage($from_id, "🤖 این سؤال نیاز به بررسی دقیق‌تر توسط ادمین دارد.\n📨 درخواست شما ارجاع داده شد؛ به‌زودی پاسخ می‌گیرید.", null, 'HTML');
            }
            return true;
        }

        redfox_ai_log($from_id, 'assistant', $reply, 0);
        global $pdo;
        try {
            $pdo->prepare("UPDATE support_message SET status = 'AI Answered', result = ? WHERE Tracking = ?")
                ->execute([$reply, $tracking]);
        } catch (Throwable $e) {}

        // ── فاز ۲: دکمه‌ی رضایت (👍 مفید / 👎 ادمین) ──
        $aiText = "🤖 <i>پاسخ خودکار هوش مصنوعی:</i>\n\n" . $reply . "\n\n💬 آیا این پاسخ مفید بود؟";
        $kb = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '👍 مفید بود', 'callback_data' => 'aifb_1_' . $tracking],
                    ['text' => '👎 پاسخ خوب نبود — ادمین', 'callback_data' => 'aifb_0_' . $tracking],
                ],
            ],
        ]);
        if (function_exists('sendmessage')) {
            sendmessage($from_id, $aiText, $kb, 'HTML');
        }
        return true;
    }
}
/* ---- crypto_helpers.php ---- */
if (!function_exists('crypto_ton_address_hash')) {


    function crypto_ton_address_hash(string $addr): ?string
    {
        $addr = trim($addr);
        if ($addr === '') return null;


        if (preg_match('/^-?\d+:([0-9a-fA-F]{64})$/', $addr, $m)) {
            return strtolower($m[1]);
        }


        if (preg_match('~^[A-Za-z0-9_\-+/]{47,48}={0,2}$~', $addr)) {
            $b64 = strtr($addr, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad > 0) $b64 .= str_repeat('=', 4 - $pad);
            $bytes = @base64_decode($b64, true);
            if ($bytes === false || strlen($bytes) !== 36) return null;


            return strtolower(bin2hex(substr($bytes, 2, 32)));
        }
        return null;
    }
}

if (!function_exists('crypto_addresses_match_ton')) {
    function crypto_addresses_match_ton(string $a, string $b): bool
    {
        $ha = crypto_ton_address_hash($a);
        $hb = crypto_ton_address_hash($b);
        if ($ha === null || $hb === null) return false;
        return $ha === $hb;
    }
}

if (!function_exists('crypto_ton_extract_dest')) {


    function crypto_ton_extract_dest($field): string
    {
        if (is_string($field)) return $field;
        if (is_array($field)) {
            if (isset($field['address']) && is_string($field['address'])) {
                return $field['address'];
            }
        }
        return '';
    }
}

if (!function_exists('crypto_extract_ton_comment')) {
    function crypto_extract_ton_comment($msg): string
    {
        if (!is_array($msg)) return '';
        if (isset($msg['comment']) && is_string($msg['comment']) && $msg['comment'] !== '') {
            return trim($msg['comment']);
        }
        if (isset($msg['decoded_op_name']) && (string) $msg['decoded_op_name'] === 'text_comment') {
            $body = $msg['decoded_body'] ?? [];
            if (is_array($body) && isset($body['text']) && is_string($body['text'])) {
                return trim($body['text']);
            }
        }
        if (isset($msg['message']) && is_string($msg['message']) && $msg['message'] !== '') {
            return trim($msg['message']);
        }
        if (isset($msg['payload']) && is_string($msg['payload']) && $msg['payload'] !== '') {
            return trim($msg['payload']);
        }
        return '';
    }
}

if (!function_exists('crypto_memo_matches')) {
    function crypto_memo_matches(string $expected, string $observed): bool
    {
        $e = trim($expected);
        $o = trim($observed);
        if ($e === '') return true;
        if ($o === '') return false;
        if (strcasecmp($e, $o) === 0) return true;
        $eNorm = preg_replace('/\s+/u', '', $e);
        $oNorm = preg_replace('/\s+/u', '', $o);
        return $eNorm !== '' && strcasecmp((string) $eNorm, (string) $oNorm) === 0;
    }
}

if (!function_exists('crypto_check_ton_tx')) {
    function crypto_check_ton_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $jettonMaster = null, bool $iranianMode = false, string $expectedMemo = ''): array
    {
        $hash = trim($hash);
        if ($hash === '') return ['ok' => false, 'reason' => 'bad-hash-format'];

        $apiKey = crypto_pay_setting('cryptocheck_tonapi_key', '');
        $headers = ['Accept: application/json'];
        if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;


        $event = crypto_http_get_json('https://tonapi.io/v2/events/' . urlencode($hash), $headers);
        $eventActions = is_array($event) ? ($event['actions'] ?? []) : [];
        if (!is_array($eventActions)) $eventActions = [];
        $tonTxTsSec = is_array($event) ? (int) ($event['timestamp'] ?? $event['utime'] ?? 0) : 0;

        $expectedHash = crypto_ton_address_hash($expectedTo);
        $diagDests = [];


        if ($jettonMaster === null) {
            $foundDestMatch = false;
            $observedAtMatch = null;
            foreach ($eventActions as $a) {
                if (!is_array($a)) continue;
                $type = (string) ($a['type'] ?? '');
                if ($type !== 'TonTransfer') continue;
                $tt = $a['TonTransfer'] ?? $a['ton_transfer'] ?? null;
                if (!is_array($tt)) continue;
                $rcpt = crypto_ton_extract_dest($tt['recipient'] ?? null);
                if ($rcpt === '') continue;
                $diagDests[] = $rcpt;
                $rcptHash = crypto_ton_address_hash($rcpt);
                if ($rcptHash === null || $expectedHash === null || $rcptHash !== $expectedHash) continue;
                $foundDestMatch = true;
                $valueNano = (float) ($tt['amount'] ?? 0);
                $amountTon = $valueNano / 1000000000.0;
                $observedAtMatch = $amountTon;
                if (crypto_amount_within_tolerance($expectedAmount, $amountTon, $iranianMode)) {
                    if ($expectedMemo !== '') {
                        $observedComment = crypto_extract_ton_comment($tt);
                        if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                            return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                        }
                    }
                    $sender = crypto_ton_extract_dest($tt['sender'] ?? null);
                    return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTon, 'to' => $rcpt, 'via' => 'events', 'sender' => $sender, 'tx_timestamp' => $tonTxTsSec]];
                }
            }
            if ($foundDestMatch) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
            }


            $tx = crypto_http_get_json('https://tonapi.io/v2/blockchain/transactions/' . urlencode($hash), $headers);
            if (!is_array($tx)) {
                if (empty($eventActions)) {
                    return ['ok' => false, 'reason' => 'tx-not-found'];
                }
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['want' => $expectedTo, 'seen' => $diagDests]];
            }
            if ($tonTxTsSec === 0) {
                $tonTxTsSec = (int) ($tx['utime'] ?? $tx['timestamp'] ?? 0);
            }
            if (!empty($tx['error'])) {
                return ['ok' => false, 'reason' => 'tx-not-found', 'detail' => $tx];
            }

            $candidates = [];
            if (!empty($tx['in_msg']) && is_array($tx['in_msg'])) {
                $im = $tx['in_msg'];
                $value = (float) ($im['value'] ?? 0);
                if ($value > 0) $candidates[] = $im;
            }
            if (!empty($tx['out_msgs']) && is_array($tx['out_msgs'])) {
                foreach ($tx['out_msgs'] as $om) {
                    if (is_array($om) && (float) ($om['value'] ?? 0) > 0) $candidates[] = $om;
                }
            }
            foreach ($candidates as $msg) {
                $dest = crypto_ton_extract_dest($msg['destination'] ?? null);
                if ($dest === '') continue;
                $diagDests[] = $dest;
                $destHash = crypto_ton_address_hash($dest);
                if ($destHash === null || $expectedHash === null || $destHash !== $expectedHash) continue;
                $foundDestMatch = true;
                $valueNano = (float) ($msg['value'] ?? 0);
                $amountTon = $valueNano / 1000000000.0;
                $observedAtMatch = $amountTon;
                if (crypto_amount_within_tolerance($expectedAmount, $amountTon, $iranianMode)) {
                    if ($expectedMemo !== '') {
                        $observedComment = crypto_extract_ton_comment($msg);
                        if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                            return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                        }
                    }
                    $sender = crypto_ton_extract_dest($msg['source'] ?? null);
                    return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTon, 'to' => $dest, 'via' => 'tx', 'sender' => $sender, 'tx_timestamp' => $tonTxTsSec]];
                }
            }
            if ($foundDestMatch) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
            }
            return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['want' => $expectedTo, 'expectedHash' => $expectedHash, 'seenDests' => $diagDests]];
        }


        $foundDestMatch = false;
        $observedAtMatch = null;
        $expectedJettonHash = crypto_ton_address_hash($jettonMaster);
        foreach ($eventActions as $a) {
            if (!is_array($a)) continue;
            if (($a['type'] ?? '') !== 'JettonTransfer') continue;
            $jt = $a['JettonTransfer'] ?? $a['jetton_transfer'] ?? null;
            if (!is_array($jt)) continue;
            $jettonAddr = crypto_ton_extract_dest($jt['jetton']['address'] ?? ($jt['jetton'] ?? null));
            $jettonAddrHash = crypto_ton_address_hash($jettonAddr);
            if ($jettonAddrHash === null || $expectedJettonHash === null || $jettonAddrHash !== $expectedJettonHash) continue;
            $recipient = crypto_ton_extract_dest($jt['recipient'] ?? null);
            $diagDests[] = $recipient;
            $rcptHash = crypto_ton_address_hash($recipient);
            if ($rcptHash === null || $expectedHash === null || $rcptHash !== $expectedHash) continue;
            $foundDestMatch = true;
            $decimals = (int) ($jt['jetton']['decimals'] ?? 6);
            $raw = (string) ($jt['amount'] ?? '0');
            $amount = (float) $raw / pow(10, $decimals);
            $observedAtMatch = $amount;
            if (crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                if ($expectedMemo !== '') {
                    $observedComment = crypto_extract_ton_comment($jt);
                    if ($observedComment === '' && isset($jt['payload'])) {
                        $observedComment = crypto_extract_ton_comment(['comment' => is_string($jt['payload']) ? $jt['payload'] : '']);
                    }
                    if (!crypto_memo_matches($expectedMemo, $observedComment)) {
                        return ['ok' => false, 'reason' => 'memo-mismatch', 'detail' => ['observed' => $observedComment, 'want' => $expectedMemo]];
                    }
                }
                $sender = crypto_ton_extract_dest($jt['sender'] ?? null);
                return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $recipient, 'sender' => $sender, 'tx_timestamp' => $tonTxTsSec]];
            }
        }
        if ($foundDestMatch) {
            return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $observedAtMatch, 'want' => $expectedAmount]];
        }
        if (empty($eventActions)) {
            return ['ok' => false, 'reason' => 'tx-not-found'];
        }
        return ['ok' => false, 'reason' => 'no-matching-jetton-transfer', 'detail' => ['want' => $expectedTo, 'seenDests' => $diagDests]];
    }
}

if (!function_exists('crypto_check_payment')) {
    function crypto_check_payment(array $row): array
    {
        $currency = (string) ($row['crypto_currency'] ?? '');
        if ($currency === '') return ['ok' => false, 'reason' => 'no-currency'];
        $hash = (string) ($row['crypto_tx_hash'] ?? '');
        if ($hash === '') return ['ok' => false, 'reason' => 'no-hash'];
        $expectedTo = (string) ($row['crypto_wallet_to'] ?? '');
        if ($expectedTo === '') return ['ok' => false, 'reason' => 'no-recipient-on-row'];
        $expectedAmount = (float) ($row['crypto_amount'] ?? 0);
        if ($expectedAmount <= 0) return ['ok' => false, 'reason' => 'no-expected-amount'];

        $usdtTrc20 = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        $usdtJettonMaster = 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs';

        $expectedMemo = '';
        if (in_array($currency, ['TON', 'USDT_TON'], true)) {
            $wallet = function_exists('crypto_active_wallet') ? crypto_active_wallet($currency) : null;
            if (is_array($wallet)) {
                $expectedMemo = trim((string) ($wallet['wallet_memo'] ?? ''));
            }
        }

        switch ($currency) {
            case 'TRX':        $verify = crypto_check_tron_tx($hash, $expectedTo, $expectedAmount, null, false); break;
            case 'USDT_TRC20': $verify = crypto_check_tron_tx($hash, $expectedTo, $expectedAmount, $usdtTrc20, false); break;
            case 'TON':        $verify = crypto_check_ton_tx($hash, $expectedTo, $expectedAmount, null, false, $expectedMemo); break;
            case 'USDT_TON':   $verify = crypto_check_ton_tx($hash, $expectedTo, $expectedAmount, $usdtJettonMaster, false, $expectedMemo); break;
            default:           return ['ok' => false, 'reason' => 'unsupported-currency'];
        }

        if (!is_array($verify) || empty($verify['ok'])) {
            return is_array($verify) ? $verify : ['ok' => false, 'reason' => 'verify-failed'];
        }

        $sender = trim((string) ($verify['detail']['sender'] ?? ''));

        if (!isset($verify['detail']) || !is_array($verify['detail'])) {
            $verify['detail'] = [];
        }
        $verify['detail']['sender'] = $sender;
        return $verify;
    }
}

if (!function_exists('crypto_explorer_url')) {
    function crypto_explorer_url(string $currency, string $hash): string
    {
        if ($currency === 'TRX' || $currency === 'USDT_TRC20') {
            return 'https://tronscan.org/#/transaction/' . $hash;
        }
        if ($currency === 'TON' || $currency === 'USDT_TON') {
            return 'https://tonviewer.com/transaction/' . $hash;
        }
        return $hash;
    }
}