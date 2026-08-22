<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('plisio', 180);



$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) { return; }
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $connect, $pdo, $setting;
$setting = $ctx['setting'];
$paymentreports = $ctx['paymentreports'];
$ManagePanel = $ctx['managePanel'];


function statusplisio($tx_id){
    global $connect;
    $rowPlisio = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'api_plisio'"));
    $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
    if ($api_key === '' || $api_key === '0') {
        $rowLegacy = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'apinowpayment'"));
        $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
    }
    if ($api_key === '' || $api_key === '0' || !preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', (string)$tx_id)) {
        return null;
    }
    $url = 'https://api.plisio.net/api/v1/operations?api_key=' . urlencode($api_key);
    $url .= '&search=' . urlencode((string)$tx_id);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = (int)curl_errno($ch);
    curl_close($ch);
    if (!is_string($response) || $errno !== 0 || $httpCode < 200 || $httpCode >= 300) {
        error_log('[plisio cron] operations request failed with HTTP ' . $httpCode);
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

list($rxW, $rxN) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$rxShard = ($rxN > 1) ? " AND MOD(id, $rxN) = $rxW " : "";
$list_service = mysqli_query($connect, "SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'plisio'$rxShard ORDER BY id ASC LIMIT 15");
while ($row = mysqli_fetch_assoc($list_service)) {

    $reportStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method='plisio' AND provider_name='plisio' LIMIT 1");
    $reportStmt->bind_param('s', $row['id_order']);
    $reportStmt->execute();
    $Payment_report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
    if (!is_array($Payment_report)) continue;
    if ($Payment_report['payment_Status'] == 'paid') continue;
    if (!isset($Payment_report['dec_not_confirmed']) || $Payment_report['dec_not_confirmed'] == null) continue;

    $providerInvoiceId = trim((string)($Payment_report['provider_invoice_id'] ?? ''));
    $expectedAmount = (float)($Payment_report['provider_amount'] ?? 0);
    $expectedCurrency = strtoupper(trim((string)($Payment_report['provider_currency'] ?? '')));
    if ($providerInvoiceId === '' || $expectedAmount <= 0 || $expectedCurrency === '') continue;
    $StatusPayment = statusplisio($providerInvoiceId);

    $operations = is_array($StatusPayment) ? ($StatusPayment['data']['operations'] ?? null) : null;
    if (!is_array($operations)) continue;
    $matchingOperations = [];
    foreach ($operations as $candidate) {
        if (!is_array($candidate)) continue;
        $candidateId = trim((string)($candidate['txn_id'] ?? $candidate['tx_id'] ?? $candidate['id'] ?? ''));
        $candidateOrder = trim((string)($candidate['order_number'] ?? ''));
        if ($candidateId === '' || !hash_equals($providerInvoiceId, $candidateId)) continue;
        if ($candidateOrder !== '' && !hash_equals((string)$Payment_report['id_order'], $candidateOrder)) continue;
        $matchingOperations[] = $candidate;
    }
    if (count($matchingOperations) !== 1) {
        if (count($matchingOperations) > 1) error_log('[plisio cron] ambiguous provider operation for ' . $providerInvoiceId);
        continue;
    }
    $op = $matchingOperations[0];
    $opStatus = strtolower(trim((string)($op['status'] ?? '')));

    if ($opStatus === 'cancelled' || $opStatus === 'expired') {
        $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  {$Payment_report['price']} تومان";
        payment_mark_expired($Payment_report['id_order'], $textexpire);
        continue;
    }

    if ($opStatus === 'completed') {
        $invoiceUrl = trim((string)($op['invoice_url'] ?? ''));
        $sourceAmt  = trim((string)($op['source_amount'] ?? ''));
        $invoiceAmt = trim((string)($op['amount'] ?? ''));
        $sourceCur  = strtoupper(trim((string)($op['source_currency'] ?? '')));
        $invoiceCur = strtoupper(trim((string)($op['currency'] ?? '')));
        $amountMatches = false;
        $paidAmount = '';
        $paidCurrency = '';
        foreach ([[$invoiceAmt,$invoiceCur],[$sourceAmt,$sourceCur]] as $pair) {
            if ($pair[0] === '' || !is_numeric($pair[0]) || $pair[1] === '') continue;
            if (hash_equals($expectedCurrency, $pair[1]) && abs((float)$pair[0] - $expectedAmount) <= 0.00000001) {
                $amountMatches = true;
                $paidAmount = $pair[0];
                $paidCurrency = $pair[1];
                break;
            }
        }
        if (!$amountMatches) {
            error_log('[plisio cron] completed operation amount/currency mismatch for ' . $providerInvoiceId);
            continue;
        }
        $finalPaymentId = trim((string)($op['txid'] ?? $op['transaction_id'] ?? $op['txn_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $finalPaymentId)) continue;
        try {
            $claim = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='plisio' AND provider_invoice_id=:invoice_id AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
            $claim->execute([':payment_id'=>$finalPaymentId, ':same_payment_id'=>$finalPaymentId, ':id'=>(int)$Payment_report['id'], ':invoice_id'=>$providerInvoiceId]);
            if ($claim->rowCount() !== 1 && !hash_equals((string)($Payment_report['provider_payment_id'] ?? ''), $finalPaymentId)) {
                throw new RuntimeException('Provider payment id already bound');
            }
        } catch (Throwable $e) {
            error_log('[plisio cron] payment claim failed: ' . redfox_exception_fingerprint($e));
            continue;
        }
        $txUrl0 = trim((string)($op['tx_url'][0] ?? ''));
        if ($txUrl0 !== '' && (!filter_var($txUrl0, FILTER_VALIDATE_URL) || strtolower((string)parse_url($txUrl0, PHP_URL_SCHEME)) !== 'https')) $txUrl0 = '';
        if ($invoiceUrl !== '' && (!filter_var($invoiceUrl, FILTER_VALIDATE_URL) || strtolower((string)parse_url($invoiceUrl, PHP_URL_SCHEME)) !== 'https')) $invoiceUrl = '';

        if ($paidAmount !== '') {
            try {
                update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $Payment_report['id_order']);
            } catch (\Throwable $e) {}
        }
        if ($paidCurrency !== '') {
            try {
                update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
            } catch (\Throwable $e) {}
        }

        $paidLine = $paidAmount !== ''
            ? ("📥 مبلغ واریز شده : <b>" . htmlspecialchars($paidAmount, ENT_QUOTES, 'UTF-8') . "</b>" . ($paidCurrency !== '' ? ' ' . htmlspecialchars(strtoupper($paidCurrency), ENT_QUOTES, 'UTF-8') : ''))
            : "📥 مبلغ واریز شده : —";
        $extraLines = [$paidLine];
        if ($txUrl0 !== '') {
            $extraLines[] = "🔗 <a href=\"" . htmlspecialchars($txUrl0, ENT_QUOTES, 'UTF-8') . "\">لینک تراکنش </a>";
        }

        payment_confirm_paid(
            $Payment_report['id_order'],
            'chashbackplisio',
            [
                'method'      => 'plisio',
                'expected_method' => 'plisio',
                'link_label'  => 'لینک پرداخت plisio',
                'link_url'    => $invoiceUrl,
                'thread_id'   => $paymentreports,
                'extra_lines' => $extraLines,
            ]
        );
    }
}
