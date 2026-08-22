<?php
/**
 * Durable payment fulfillment state machine.
 *
 * Invariants:
 *  - Payment_report is never marked paid before fulfillment completes.
 *  - DirectPayment is invoked at most once automatically for an order.
 *  - fulfillment_started is deliberately never retried by automation because
 *    the remote panel outcome can be ambiguous.
 *  - cashback, wallet credit, paid status and completed state commit together.
 */

if (!function_exists('rx_payment_source')) {
    function rx_payment_source(array $reportData): string
    {
        $source = trim((string)($reportData['source'] ?? $reportData['method'] ?? 'unknown'));
        $source = preg_replace('/[^A-Za-z0-9_.:\- ]/u', '', $source) ?: 'unknown';
        return substr($source, 0, 100);
    }
}

if (!function_exists('rx_payment_error')) {
    function rx_payment_error(Throwable $e): string
    {
        return substr(get_class($e) . ': ' . redfox_exception_fingerprint($e), 0, 1900);
    }
}

if (!function_exists('rx_payment_context_encode')) {
    function rx_payment_context_encode(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode payment fulfillment context');
        }
        return $json;
    }
}

if (!function_exists('rx_payment_context_decode')) {
    function rx_payment_context_decode(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('rx_payment_mark_reconcile')) {
    function rx_payment_mark_reconcile(PDO $pdo, string $orderId, string $error): void
    {
        try {
            $stmt = $pdo->prepare("UPDATE payment_effects SET status='needs_reconcile', updated_at=:now, last_error=:error WHERE order_id=:order_id AND status='fulfillment_started'");
            $stmt->execute([':now' => time(), ':error' => substr($error, 0, 1900), ':order_id' => $orderId]);
        } catch (Throwable $ignored) {
            error_log('[payment-confirm] unable to persist reconciliation state for ' . $orderId);
        }
    }
}

if (!function_exists('rx_payment_cashback_percent')) {
    function rx_payment_cashback_percent(PDO $pdo, string $cashbackKey): float
    {
        if ($cashbackKey === '' || !preg_match('/^[A-Za-z0-9_]{1,100}$/', $cashbackKey)) return 0.0;
        $stmt = $pdo->prepare('SELECT ValuePay FROM PaySetting WHERE NamePay=:name LIMIT 1');
        $stmt->execute([':name' => $cashbackKey]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) return 0.0;
        $value = (float)$raw;
        return ($value > 0 && $value <= 100) ? $value : 0.0;
    }
}

if (!function_exists('rx_payment_finalize')) {
    /** Finalize only a durable fulfillment_done state. Safe to call repeatedly. */
    function rx_payment_finalize(PDO $pdo, string $orderId): array
    {
        $pdo->beginTransaction();
        try {
            $effectStmt = $pdo->prepare('SELECT * FROM payment_effects WHERE order_id=:order_id FOR UPDATE');
            $effectStmt->execute([':order_id' => $orderId]);
            $effect = $effectStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($effect)) {
                throw new RuntimeException('Payment effect claim is missing');
            }
            if (($effect['status'] ?? '') === 'completed') {
                $pdo->commit();
                return ['ok' => true, 'duplicate' => true, 'status' => 'completed'];
            }
            if (($effect['status'] ?? '') !== 'fulfillment_done') {
                $status = (string)($effect['status'] ?? 'unknown');
                $pdo->rollBack();
                return ['ok' => false, 'duplicate' => true, 'status' => $status, 'error' => 'fulfillment_not_done'];
            }

            $context = rx_payment_context_decode($effect['report_context'] ?? null);
            $reportId = (int)($context['report_id'] ?? 0);
            $sql = 'SELECT * FROM Payment_report WHERE id_order=:order_id';
            $params = [':order_id' => $orderId];
            if ($reportId > 0) {
                $sql .= ' AND id=:report_id';
                $params[':report_id'] = $reportId;
            }
            $sql .= ' LIMIT 1 FOR UPDATE';
            $reportStmt = $pdo->prepare($sql);
            $reportStmt->execute($params);
            $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($report)) {
                throw new RuntimeException('Payment report disappeared before finalization');
            }

            $userId = (string)($report['id_user'] ?? '');
            if ($userId === '') throw new RuntimeException('Payment report has no user');
            $userStmt = $pdo->prepare('SELECT id FROM user WHERE id=:id LIMIT 1 FOR UPDATE');
            $userStmt->execute([':id' => $userId]);
            if (!$userStmt->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('Payment user does not exist');

            $price = max(0.0, (float)($report['price'] ?? 0));
            $cashbackKey = (string)($effect['cashback_key'] ?? '');
            $percent = rx_payment_cashback_percent($pdo, $cashbackKey);
            $cashback = (int)floor(($price * $percent) / 100);
            if ($cashback > 0) {
                $ledger = $pdo->prepare('INSERT IGNORE INTO payment_cashback_ledger (order_id,user_id,amount,percent,cashback_key,created_at) VALUES (:order_id,:user_id,:amount,:percent,:cashback_key,:created_at)');
                $ledger->execute([
                    ':order_id' => $orderId,
                    ':user_id' => $userId,
                    ':amount' => $cashback,
                    ':percent' => $percent,
                    ':cashback_key' => $cashbackKey,
                    ':created_at' => time(),
                ]);
                if ($ledger->rowCount() === 1) {
                    $wallet = $pdo->prepare('UPDATE user SET Balance=COALESCE(Balance,0)+:amount WHERE id=:id');
                    $wallet->execute([':amount' => $cashback, ':id' => $userId]);
                    if ($wallet->rowCount() !== 1) throw new RuntimeException('Cashback wallet update failed');
                }
            }

            $paid = $pdo->prepare("UPDATE Payment_report SET payment_Status='paid', at_updated=:updated WHERE id=:id AND id_order=:order_id");
            $paid->execute([':updated' => (string)time(), ':id' => (int)$report['id'], ':order_id' => $orderId]);
            if ($paid->rowCount() > 1) throw new RuntimeException('Unexpected payment report update count');

            $completed = $pdo->prepare("UPDATE payment_effects SET status='completed',completed_at=:now,updated_at=:now,last_error=NULL WHERE order_id=:order_id AND status='fulfillment_done'");
            $completed->execute([':now' => time(), ':order_id' => $orderId]);
            if ($completed->rowCount() !== 1) throw new RuntimeException('Unable to complete payment effect');
            $pdo->commit();
            return ['ok' => true, 'duplicate' => false, 'status' => 'completed', 'cashback' => $cashback];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[payment-confirm] finalize ' . $orderId . ': ' . rx_payment_error($e));
            return ['ok' => false, 'duplicate' => false, 'status' => 'fulfillment_done', 'error' => 'finalization_failed'];
        }
    }
}

if (!function_exists('rx_payment_prepare_fulfillment_context')) {
    function rx_payment_prepare_fulfillment_context(PDO $pdo, string $orderId): void
    {
        $stmt = $pdo->prepare('SELECT id_user,message_id FROM Payment_report WHERE id_order=:order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($report)) throw new RuntimeException('Payment report is unavailable for fulfillment');
        $GLOBALS['from_id'] = (string)($report['id_user'] ?? '');
        $GLOBALS['message_id'] = isset($report['message_id']) ? (int)$report['message_id'] : null;
        if ((!isset($GLOBALS['ManagePanel']) || !is_object($GLOBALS['ManagePanel'])) && class_exists('ManagePanel')) {
            $GLOBALS['ManagePanel'] = new ManagePanel();
        }
        if (!isset($GLOBALS['textbotlang']) || !is_array($GLOBALS['textbotlang'])) {
            $root = dirname(__DIR__);
            $GLOBALS['textbotlang'] = function_exists('languagechange') ? languagechange($root . '/text.json') : [];
        }
        if (!isset($GLOBALS['datatextbot']) || !is_array($GLOBALS['datatextbot'])) {
            $texts = ['textafterpay'=>'','textaftertext'=>'','textmanual'=>'','textselectlocation'=>'','text_wgdashboard'=>'','textafterpayibsng'=>''];
            if (function_exists('select')) {
                $rows = select('textbot', '*', null, null, 'fetchAll');
                if (is_array($rows)) foreach ($rows as $row) {
                    $key = (string)($row['id_text'] ?? '');
                    if (array_key_exists($key, $texts)) $texts[$key] = (string)($row['text'] ?? '');
                }
            }
            $GLOBALS['datatextbot'] = $texts;
        }
    }
}

if (!function_exists('rx_payment_run_claimed')) {
    /** Advance one already-claimed effect through fulfillment. */
    function rx_payment_run_claimed(PDO $pdo, string $orderId): array
    {
        $started = $pdo->prepare("UPDATE payment_effects SET status='fulfillment_started',fulfillment_started_at=:now,updated_at=:now,last_error=NULL WHERE order_id=:order_id AND status='claimed'");
        $started->execute([':now' => time(), ':order_id' => $orderId]);
        if ($started->rowCount() !== 1) {
            $state = $pdo->prepare('SELECT status FROM payment_effects WHERE order_id=:order_id');
            $state->execute([':order_id' => $orderId]);
            $status = (string)($state->fetchColumn() ?: 'unknown');
            if ($status === 'fulfillment_done') return rx_payment_finalize($pdo, $orderId);
            return ['ok' => $status === 'completed', 'duplicate' => true, 'status' => $status];
        }

        $active = ['stage' => 'fulfillment_started'];
        $GLOBALS['rx_active_payment_effects'][$orderId] = &$active;
        try {
            if (!function_exists('DirectPayment')) throw new RuntimeException('DirectPayment is unavailable');
            rx_payment_prepare_fulfillment_context($pdo, $orderId);
            $fulfilled = DirectPayment($orderId);
            if ($fulfilled !== true) {
                rx_payment_mark_reconcile($pdo, $orderId, 'DirectPayment returned false; manual reconciliation required');
                $active['stage'] = 'needs_reconcile';
                return ['ok' => false, 'duplicate' => false, 'status' => 'needs_reconcile', 'error' => 'fulfillment_failed'];
            }
            $done = $pdo->prepare("UPDATE payment_effects SET status='fulfillment_done',fulfillment_done_at=:now,updated_at=:now,last_error=NULL WHERE order_id=:order_id AND status='fulfillment_started'");
            $done->execute([':now' => time(), ':order_id' => $orderId]);
            if ($done->rowCount() !== 1) throw new RuntimeException('Unable to persist fulfillment completion');
            $active['stage'] = 'fulfillment_done';
            $result = rx_payment_finalize($pdo, $orderId);
            if (($result['status'] ?? '') === 'completed') $active['stage'] = 'completed';
            return $result;
        } catch (Throwable $e) {
            rx_payment_mark_reconcile($pdo, $orderId, rx_payment_error($e));
            $active['stage'] = 'needs_reconcile';
            error_log('[payment-confirm] fulfillment ' . $orderId . ': ' . rx_payment_error($e));
            return ['ok' => false, 'duplicate' => false, 'status' => 'needs_reconcile', 'error' => 'fulfillment_exception'];
        } finally {
            unset($GLOBALS['rx_active_payment_effects'][$orderId]);
        }
    }
}

if (!function_exists('payment_confirm_paid')) {
    function payment_confirm_paid(string $orderId, string $cashbackKey, array $reportData = []): array
    {
        global $pdo;
        $orderId = trim($orderId);
        if (!$pdo instanceof PDO) return ['ok' => false, 'error' => 'db_unavailable'];
        if ($orderId === '' || strlen($orderId) > 191) return ['ok' => false, 'error' => 'invalid_order_id'];
        if ($cashbackKey !== '' && !preg_match('/^[A-Za-z0-9_]{1,100}$/', $cashbackKey)) {
            return ['ok' => false, 'error' => 'invalid_cashback_key'];
        }

        try {
            $pdo->beginTransaction();
            $reportStmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order=:order_id LIMIT 1 FOR UPDATE');
            $reportStmt->execute([':order_id' => $orderId]);
            $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($report)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'payment_not_found'];
            }
            // A verified provider event must never be replayed against an order
            // created for another gateway. The binding is mandatory for every
            // caller; omission is a programming/configuration error and fails closed.
            $expected = $reportData['expected_method'] ?? null;
            $expected = is_array($expected) ? $expected : [$expected];
            $expected = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $expected), static fn(string $v): bool => $v !== ''));
            $actual = trim((string)($report['Payment_Method'] ?? ''));
            if (!$expected || !in_array($actual, $expected, true)) {
                $pdo->rollBack();
                error_log('[payment-confirm] missing or mismatched provider/method binding for order ' . $orderId);
                return ['ok' => false, 'error' => 'payment_method_mismatch'];
            }

            $effectStmt = $pdo->prepare('SELECT * FROM payment_effects WHERE order_id=:order_id FOR UPDATE');
            $effectStmt->execute([':order_id' => $orderId]);
            $effect = $effectStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($effect)) {
                $status = (string)($effect['status'] ?? 'unknown');
                $pdo->commit();
                if ($status === 'completed') return ['ok' => true, 'duplicate' => true, 'status' => 'completed'];
                if ($status === 'fulfillment_done') return rx_payment_finalize($pdo, $orderId);
                return ['ok' => false, 'duplicate' => true, 'status' => $status, 'error' => 'payment_in_progress_or_reconcile'];
            }

            $now = time();
            $context = ['report_id' => (int)$report['id'], 'report_data' => $reportData];
            if (strtolower((string)($report['payment_Status'] ?? '')) === 'paid') {
                $legacy = $pdo->prepare("INSERT INTO payment_effects (order_id,status,source,cashback_key,report_context,started_at,claimed_at,updated_at,last_error) VALUES (:order_id,'needs_reconcile',:source,:cashback_key,:context,:now,:now,:now,:error)");
                $legacy->execute([
                    ':order_id' => $orderId, ':source' => rx_payment_source($reportData), ':cashback_key' => $cashbackKey,
                    ':context' => rx_payment_context_encode($context), ':now' => $now,
                    ':error' => 'Legacy paid report had no durable fulfillment record; automatic replay refused',
                ]);
                $pdo->commit();
                return ['ok' => false, 'duplicate' => true, 'status' => 'needs_reconcile', 'error' => 'legacy_paid_requires_reconcile'];
            }

            $claim = $pdo->prepare("INSERT INTO payment_effects (order_id,status,source,cashback_key,report_context,started_at,claimed_at,updated_at,last_error) VALUES (:order_id,'claimed',:source,:cashback_key,:context,:now,:now,:now,NULL)");
            $claim->execute([
                ':order_id' => $orderId, ':source' => rx_payment_source($reportData), ':cashback_key' => $cashbackKey,
                ':context' => rx_payment_context_encode($context), ':now' => $now,
            ]);
            $pdo->commit();
            return rx_payment_run_claimed($pdo, $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[payment-confirm] claim ' . $orderId . ': ' . rx_payment_error($e));
            return ['ok' => false, 'error' => 'payment_claim_failed'];
        }
    }
}

if (!function_exists('rx_payment_reconcile_once')) {
    /**
     * Resume only states with unambiguous side effects:
     *  - stale claimed: DirectPayment was never started;
     *  - fulfillment_done: only the local atomic commit remains.
     */
    function rx_payment_reconcile_once(PDO $pdo, int $limit = 25, int $claimStaleSeconds = 900): array
    {
        $limit = max(1, min(100, $limit));
        $cutoff = time() - max(300, $claimStaleSeconds);
        $stmt = $pdo->prepare("SELECT order_id,status FROM payment_effects WHERE (status='claimed' AND updated_at<=:cutoff) OR status='fulfillment_done' ORDER BY updated_at ASC LIMIT {$limit}");
        $stmt->execute([':cutoff' => $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = ['selected' => count($rows), 'completed' => 0, 'reconcile' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $orderId = (string)$row['order_id'];
            $out = (($row['status'] ?? '') === 'fulfillment_done')
                ? rx_payment_finalize($pdo, $orderId)
                : rx_payment_run_claimed($pdo, $orderId);
            if (($out['status'] ?? '') === 'completed') $result['completed']++;
            elseif (($out['status'] ?? '') === 'needs_reconcile') $result['reconcile']++;
            else $result['failed']++;
        }
        return $result;
    }
}

if (empty($GLOBALS['rx_payment_shutdown_registered'])) {
    $GLOBALS['rx_payment_shutdown_registered'] = true;
    register_shutdown_function(static function (): void {
        $active = $GLOBALS['rx_active_payment_effects'] ?? [];
        if (!is_array($active) || !$active) return;
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) return;
        $last = error_get_last();
        $fatal = is_array($last) && in_array((int)$last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        if (!$fatal) return;
        foreach ($active as $orderId => $state) {
            if (($state['stage'] ?? '') === 'fulfillment_started') {
                rx_payment_mark_reconcile($pdo, (string)$orderId, 'Fatal shutdown after fulfillment started; manual reconciliation required');
            }
        }
    });
}
