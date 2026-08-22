<?php
declare(strict_types=1);

if (!class_exists('RedFoxApiUserNotFound', false)) {
    final class RedFoxApiUserNotFound extends RuntimeException {}
}
/**
 * Red Fox — REST API عمومی برای یکپارچه‌سازی شخص ثالث.
 *
 * احراز هویت: Bearer Token (از پنل → تنظیمات → کلید API)
 * فرمت: JSON
 *
 * Endpoints:
 *  GET  api/index.php?ep=stats          → آمار کلی
 *  GET  api/index.php?ep=users&p=1      → لیست کاربران
 *  GET  api/index.php?ep=user&id=123    → اطلاعات یک کاربر
 *  GET  api/index.php?ep=invoices&p=1   → لیست فاکتورها
 *  GET  api/index.php?ep=products       → لیست محصولات
 *  POST api/index.php?ep=charge         → شارژ کیف پول کاربر (id, amount)
 *  GET  api/index.php?ep=health         → وضعیت سلامت سیستم
 */

error_reporting(E_ERROR);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

define('REFACTORED_LEGACY_ROOT', dirname(__DIR__));
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib/IntegrationAuth.php';
require_once dirname(__DIR__) . '/botapi.php';

// Atomic per-IP limiter; malformed and unauthenticated traffic is bounded too.
$__rate = redfox_login_rate_check('rest-integration-api', redfox_client_ip(), 60, 60);
if (empty($__rate['allowed'])) {
    http_response_code(429);
    header('Retry-After: ' . max(1, (int)($__rate['retry_after'] ?? 60)));
    echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded']);
    exit;
}

// ── احراز هویت مشترک و fail-closed ──
$integrationCredential = rx_require_integration_auth($pdo);
$integrationCredentialHash = hash('sha256', $integrationCredential);
unset($integrationCredential);

// ── Router ──
$ep = (string)($_GET['ep'] ?? '');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
$readEndpoints = ['health', 'stats', 'users', 'user', 'invoices', 'products'];
if ((in_array($ep, $readEndpoints, true) && $method !== 'GET') || ($ep === 'charge' && $method !== 'POST')) {
    header('Allow: ' . ($ep === 'charge' ? 'POST' : 'GET'));
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (strlen((string)($_SERVER['QUERY_STRING'] ?? '')) > 8192) {
    http_response_code(414);
    echo json_encode(['ok' => false, 'error' => 'Request URI too long']);
    exit;
}
$done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";

try {
    switch ($ep) {
        case 'health':
            $dbOk = true;
            try { $pdo->query("SELECT 1"); } catch (Throwable $e) { $dbOk = false; }
            $userCount = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
            echo json_encode(['ok' => true, 'status' => 'healthy', 'db' => $dbOk, 'users' => $userCount, 'time' => date('c')]);
            break;

        case 'stats':
            $users = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
            $sales = (int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE Status IN ($done)")->fetchColumn();
            $revenue = (int)$pdo->query("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE Status IN ($done)")->fetchColumn();
            $resellers = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')")->fetchColumn();
            $panels = 0; try { $panels = (int)$pdo->query("SELECT COUNT(*) FROM marzban_panel WHERE status='active'")->fetchColumn(); } catch (Throwable $e) {}
            echo json_encode(['ok' => true, 'data' => ['users' => $users, 'sales' => $sales, 'revenue' => $revenue, 'resellers' => $resellers, 'active_panels' => $panels]]);
            break;

        case 'users':
            $page = min(100000, max(1, (int)($_GET['p'] ?? 1)));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            $stmt = $pdo->prepare("SELECT id, username, User_Status, Balance, agent, register, affiliatescount FROM user ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
            echo json_encode(['ok' => true, 'data' => $rows, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);
            break;

        case 'user':
            $id = trim((string)($_GET['id'] ?? ''));
            if (!preg_match('/^[0-9]{1,20}$/', $id)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'valid id required']);
                break;
            }
            $st = $pdo->prepare("SELECT id, username, User_Status, Balance, agent, register, affiliatescount, affiliates FROM user WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            // فاکتورهای کاربر
            $st2 = $pdo->prepare("SELECT id_invoice, name_product, price_product, Volume, Service_time, Status, Service_location, time_sell FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 50");
            $st2->execute([$id]);
            $invs = $st2->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'user' => $u ?: null, 'invoices' => $invs]);
            break;

        case 'invoices':
            $page = min(100000, max(1, (int)($_GET['p'] ?? 1)));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            $stmt = $pdo->prepare("SELECT id_invoice, id_user, username, name_product, price_product, Volume, Service_time, Status, Service_location, time_sell FROM invoice ORDER BY time_sell DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = (int)$pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
            echo json_encode(['ok' => true, 'data' => $rows, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);
            break;

        case 'products':
            $rows = $pdo->query("SELECT id, code_product, name_product, price_product, Volume_constraint, Service_time, agent, Location, category FROM product ORDER BY CAST(price_product AS UNSIGNED) ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
            break;

        case 'charge':
            $declaredLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
            if ($declaredLength === false || $declaredLength < 2 || $declaredLength > 65536) {
                http_response_code(413);
                echo json_encode(['ok'=>false,'error'=>'invalid body size']);
                break;
            }
            $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
            if ($contentType !== 'application/json') {
                http_response_code(415);
                echo json_encode(['ok'=>false,'error'=>'JSON body required']);
                break;
            }
            $rawInput = file_get_contents('php://input', false, null, 0, 65537);
            if (!is_string($rawInput) || $rawInput === '' || strlen($rawInput) > 65536) {
                http_response_code(413);
                echo json_encode(['ok'=>false,'error'=>'payload too large']);
                break;
            }
            try {
                $input = json_decode($rawInput, true, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (Throwable $jsonError) {
                $input = null;
            }
            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'invalid JSON']);
                break;
            }
            $uid = trim((string)($input['id'] ?? ''));
            $amountRaw = $input['amount'] ?? null;
            $amount = filter_var($amountRaw, FILTER_VALIDATE_INT);
            if (!preg_match('/^[0-9]{1,20}$/', $uid) || !is_int($amount) || $amount < 1 || $amount > 100000000) { http_response_code(422); echo json_encode(['ok' => false, 'error' => 'valid id and amount between 1 and 100000000 are required']); break; }

            // A retry of a wallet mutation must never credit twice. The caller
            // supplies one stable key per logical charge; the request body is
            // bound to it and the exact completed response is replayed.
            $idempotencyKey = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_.:-]{16,100}$/', $idempotencyKey)) {
                http_response_code(428);
                echo json_encode(['ok' => false, 'error' => 'valid Idempotency-Key header required']);
                break;
            }
            $requestHash = hash('sha256', $rawInput);
            $idemLookup = $pdo->prepare('SELECT request_hash,status_code,response_body FROM integration_api_idempotency WHERE credential_hash=? AND idempotency_key=? LIMIT 1');
            $idemLookup->execute([$integrationCredentialHash, $idempotencyKey]);
            $existingIdem = $idemLookup->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingIdem)) {
                if (!hash_equals((string)$existingIdem['request_hash'], $requestHash)) {
                    http_response_code(409);
                    echo json_encode(['ok' => false, 'error' => 'idempotency key reused with different request']);
                    break;
                }
                if ($existingIdem['status_code'] !== null && is_string($existingIdem['response_body'])) {
                    http_response_code((int)$existingIdem['status_code']);
                    echo $existingIdem['response_body'];
                    break;
                }
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'request with this idempotency key is already processing']);
                break;
            }
            try {
                $pdo->prepare('INSERT INTO integration_api_idempotency(credential_hash,idempotency_key,endpoint,request_hash,created_at) VALUES(?,?,?,?,?)')
                    ->execute([$integrationCredentialHash, $idempotencyKey, 'charge', $requestHash, time()]);
            } catch (PDOException $idempotencyRace) {
                if ((string)$idempotencyRace->getCode() !== '23000') throw $idempotencyRace;
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'idempotency claim conflict']);
                break;
            }
            try {
                $pdo->prepare('DELETE FROM integration_api_idempotency WHERE completed_at IS NOT NULL AND completed_at<?')
                    ->execute([time() - 2592000]);
            } catch (Throwable $cleanupError) {
                redfox_log_exception($cleanupError, 'api.integration_idempotency_cleanup');
            }

            $pdo->beginTransaction();
            try {
                $check = $pdo->prepare('SELECT Balance FROM user WHERE id = ? FOR UPDATE');
                $check->execute([$uid]); $cur = $check->fetchColumn(); $check->closeCursor();
                if ($cur === false) throw new RedFoxApiUserNotFound('user not found');
                $newBal = (int)$cur + $amount;
                if ($newBal < 0 || $newBal > PHP_INT_MAX) throw new RuntimeException('balance overflow');
                $update = $pdo->prepare('UPDATE user SET Balance = ? WHERE id = ?'); $update->execute([$newBal, $uid]);
                if ($update->rowCount() !== 1) throw new RuntimeException('balance update failed');
                $pdo->prepare("INSERT INTO audit_log (admin_user, action, entity, details, created_at) VALUES (?, ?, ?, ?, ?)")
                    ->execute(['API', 'charge_user', "user:$uid", json_encode(['amount'=>$amount,'old_balance'=>(int)$cur,'new_balance'=>$newBal], JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s')]);
                $pdo->commit();

                $chargeBody = json_encode(['ok' => true, 'user_id' => $uid, 'old_balance' => (int)$cur, 'new_balance' => $newBal, 'changed' => $amount], JSON_UNESCAPED_SLASHES);
                $finishIdem = $pdo->prepare('UPDATE integration_api_idempotency SET status_code=?,response_body=?,completed_at=? WHERE credential_hash=? AND idempotency_key=? AND completed_at IS NULL');
                $finishIdem->execute([200, $chargeBody, time(), $integrationCredentialHash, $idempotencyKey]);
                if ($finishIdem->rowCount() !== 1) {
                    // The wallet commit is already durable. Leave the key in a
                    // non-replayable state and surface a reconciliation error.
                    throw new RuntimeException('idempotency result finalization failed');
                }
                echo $chargeBody;
            } catch (Throwable $chargeError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $chargeStatus = $chargeError instanceof RedFoxApiUserNotFound ? 404 : 500;
                $chargePayload = $chargeError instanceof RedFoxApiUserNotFound
                    ? ['ok' => false, 'error' => 'user not found']
                    : ['ok' => false, 'error' => 'internal server error'];
                if (!($chargeError instanceof RedFoxApiUserNotFound)) {
                    redfox_log_exception($chargeError, 'api.integration_charge');
                }
                $chargeErrorBody = json_encode($chargePayload, JSON_UNESCAPED_SLASHES);
                $finishIdem = $pdo->prepare('UPDATE integration_api_idempotency SET status_code=?,response_body=?,completed_at=? WHERE credential_hash=? AND idempotency_key=? AND completed_at IS NULL');
                $finishIdem->execute([$chargeStatus, $chargeErrorBody, time(), $integrationCredentialHash, $idempotencyKey]);
                // If a post-commit finalization failed, rowCount can be zero;
                // never overwrite/replay the operation. Return a generic 500.
                if ($finishIdem->rowCount() !== 1) {
                    $chargeStatus = 500;
                    $chargeErrorBody = json_encode(['ok' => false, 'error' => 'internal server error']);
                }
                http_response_code($chargeStatus);
                echo $chargeErrorBody;
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Unknown endpoint', 'available' => ['stats', 'users', 'user', 'invoices', 'products', 'charge', 'health']]);
    }
} catch (Throwable $e) {
    rx_log_event_structured('error', 'rest_integration_failure', ['exception' => get_class($e), 'code' => (string)$e->getCode(), 'endpoint' => $ep]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal server error']);
}
