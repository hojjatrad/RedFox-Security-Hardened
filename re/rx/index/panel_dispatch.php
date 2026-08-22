<?php

// Re-authorize every privileged reseller-bot action server-side. Hiding a
// keyboard button is not authorization, and stale conversation steps must not
// survive permission revocation.
$rxCurrentResellerPerms = json_decode((string)($user['reseller_perms'] ?? '{}'), true);
if (!is_array($rxCurrentResellerPerms)) $rxCurrentResellerPerms = [];
$rxCurrentResellerRole = (string)($user['agent'] ?? '');
$rxResellerCan = static function (string $permission) use ($rxCurrentResellerPerms, $rxCurrentResellerRole): bool {
    return in_array($rxCurrentResellerRole, ['n', 'n2'], true)
        && !empty($rxCurrentResellerPerms[$permission]);
};
$rxAssertLockedPermission = static function (string $permission) use ($pdo, $from_id): array {
    $stmt = $pdo->prepare('SELECT id,Balance,agent,reseller_perms,bottype FROM user WHERE id=? FOR UPDATE');
    $stmt->execute([(string)$from_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $perms = is_array($row) ? json_decode((string)($row['reseller_perms'] ?? '{}'), true) : null;
    if (!is_array($row)
        || !in_array((string)($row['agent'] ?? ''), ['n', 'n2'], true)
        || !is_array($perms)
        || empty($perms[$permission])) {
        throw new RuntimeException('reseller permission revoked');
    }
    return $row;
};
$rxOwnBotToken = '';
try {
    $rxOwnBot = select('botsaz', 'bot_token', 'id_user', (string)$from_id, 'select');
    if (is_array($rxOwnBot)) $rxOwnBotToken = (string)($rxOwnBot['bot_token'] ?? '');
} catch (Throwable $rxBotLookupError) {
    redfox_log_exception($rxBotLookupError, 'reseller.scope.bot');
}
$rxFindScopedUser = static function (string $query, bool $byUsername = false) use ($pdo, $from_id, $rxOwnBotToken): ?array {
    if ($byUsername) {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $query)) return null;
        $identitySql = 'u.username = :identity';
    } else {
        if (!preg_match('/^[0-9]{1,20}$/', $query)) return null;
        $identitySql = 'u.id = :identity';
    }
    $sql = "SELECT u.* FROM user u WHERE {$identitySql} AND ("
        . "EXISTS (SELECT 1 FROM invoice i WHERE i.id_user=u.id AND i.refral=:owner";
    $params = [':identity' => $query, ':owner' => (string)$from_id];
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR i.bottype=:invoice_bot';
        $params[':invoice_bot'] = $rxOwnBotToken;
    }
    $sql .= ')';
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR u.bottype=:user_bot';
        $params[':user_bot'] = $rxOwnBotToken;
    }
    $sql .= ') LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$rxFindScopedInvoice = static function (string $userId) use ($pdo, $from_id, $rxOwnBotToken): ?array {
    if (!preg_match('/^[0-9]{1,20}$/', $userId)) return null;
    $sql = 'SELECT i.* FROM invoice i WHERE i.id_user=:user_id AND (i.refral=:owner';
    $params = [':user_id' => $userId, ':owner' => (string)$from_id];
    if ($rxOwnBotToken !== '') {
        $sql .= ' OR i.bottype=:bot_token';
        $params[':bot_token'] = $rxOwnBotToken;
    }
    $sql .= ') ORDER BY i.time_sell DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$rxPrivilegedStepPermissions = [
    'super_create_product' => 'products',
    'super_create_category' => 'categories',
    'super_extend_main' => 'extend_user',
    'super_extend_val_main' => 'extend_user',
    'super_charge_main' => 'charge_user',
    'super_charge_amt_main' => 'charge_user',
    'super_search_main' => 'manage_users',
];
$rxCurrentStep = (string)($user['step'] ?? '');
if (isset($rxPrivilegedStepPermissions[$rxCurrentStep])
    && !$rxResellerCan($rxPrivilegedStepPermissions[$rxCurrentStep])) {
    step('home', $from_id);
    sendmessage($from_id, '⛔️ دسترسی این عملیات لغو شده است.', $keyboard, 'HTML');
    return;
}
$rxPrivilegedRequests = [
    'super_products' => 'products',
    'super_categories' => 'categories',
    'super_extend' => 'extend_user',
    'super_charge' => 'charge_user',
    'super_search' => 'manage_users',
    'super_reports' => 'reports',
];
$rxPrivilegedTexts = [
    '🛍 محصولات' => 'products',
    '📂 دسته‌بندی' => 'categories',
    '⏰ حجم/زمان کاربر' => 'extend_user',
    '⏰ افزایش حجم/زمان' => 'extend_user',
    '💰 شارژ کاربر' => 'charge_user',
    '👥 جستجوی کاربر' => 'manage_users',
    '📊 گزارش فروش' => 'reports',
];
$rxRequestedPrivilege = $rxPrivilegedRequests[(string)$datain]
    ?? $rxPrivilegedTexts[(string)$text]
    ?? null;
if ($rxRequestedPrivilege !== null && !$rxResellerCan($rxRequestedPrivilege)) {
    step('home', $from_id);
    if ($message_id && $datain) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این دسترسی برای شما فعال نیست.',
            'show_alert' => true,
        ]);
    } else {
        sendmessage($from_id, '⛔️ دسترسی این عملیات برای شما فعال نیست.', $keyboard, 'HTML');
    }
    return;
}

if (preg_match('/^Confirmpay_user_([A-Za-z0-9_.:\-]{1,191})_([A-Za-z0-9_.:\-]{1,191})$/', $datain, $dataget)) {
    $id_payment = $dataget[1];
    $id_order = $dataget[2];


    $stmtPay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :id_order AND Payment_Method='Currency Rial 2' AND provider_name='tronado' AND provider_invoice_id=:provider_id LIMIT 2");
    $stmtPay->bindValue(':id_order', $id_order, PDO::PARAM_STR);
    $stmtPay->bindValue(':provider_id', $id_payment, PDO::PARAM_STR);
    $stmtPay->execute();
    $Payment_report = $stmtPay->fetch(PDO::FETCH_ASSOC);
    $duplicatePaymentReport = $stmtPay->fetch(PDO::FETCH_ASSOC);
    if (!is_array($Payment_report) || is_array($duplicatePaymentReport) || (string)($Payment_report['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('PAYMENT_CONFIRM_FORBIDDEN', 'Non-owner attempted Confirmpay_user', [
                'from_id' => $from_id, 'id_order' => $id_order, 'id_payment' => $id_payment,
            ]);
        }
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Confirmpayadmin'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $StatusPayment = StatusPayment($id_payment);
    if (is_array($StatusPayment) && ($StatusPayment['payment_status'] ?? '') === "finished") {
        $statusPaymentId = trim((string)($StatusPayment['payment_id'] ?? ''));
        $statusOrderId = trim((string)($StatusPayment['order_id'] ?? ''));
        $statusAmount = $StatusPayment['pay_amount'] ?? null;
        $statusCurrency = strtoupper(trim((string)($StatusPayment['pay_currency'] ?? '')));
        if (($statusPaymentId !== '' && !hash_equals($id_payment, $statusPaymentId))
            || ($statusOrderId !== '' && !hash_equals($id_order, $statusOrderId))
            || ($statusAmount !== null && abs((float)$statusAmount - (float)$Payment_report['provider_amount']) > 0.00000001)
            || ($statusCurrency !== '' && !hash_equals(strtoupper((string)$Payment_report['provider_currency']), $statusCurrency))) {
            if (function_exists('rx_log_event')) {
                rx_log_event('PAYMENT_PROVIDER_MISMATCH', 'Tronado/StatusPayment response did not match bound invoice', [
                    'order_id'=>$id_order, 'provider_id'=>$id_payment,
                ]);
            }
            return;
        }
        $finalPaymentId = $statusPaymentId !== '' ? $statusPaymentId : $id_payment;
        try {
            $bindFinalPayment = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:payment_id WHERE id=:id AND provider_name='tronado' AND provider_invoice_id=:invoice_id AND (provider_payment_id IS NULL OR provider_payment_id=:same_payment_id)");
            $bindFinalPayment->execute([':payment_id'=>$finalPaymentId, ':same_payment_id'=>$finalPaymentId, ':id'=>(int)$Payment_report['id'], ':invoice_id'=>$id_payment]);
            if ($bindFinalPayment->rowCount() !== 1 && !hash_equals((string)($Payment_report['provider_payment_id'] ?? ''), $finalPaymentId)) {
                throw new RuntimeException('Provider payment id already bound');
            }
        } catch (Throwable $e) {
            error_log('[Confirmpay_user] provider payment binding failed: ' . redfox_exception_fingerprint($e));
            return;
        }
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['finished'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $confirm=payment_confirm_paid((string)$Payment_report['id_order'],'chashbackiranpay2',[
            'method'=>'درگاه ارزی ریالی','expected_method'=>'Currency Rial 2','thread_id'=>$paymentreports,
            'extra_lines'=>['شناسه پرداخت: '.htmlspecialchars((string)$id_payment)]
        ]);
        if(empty($confirm['ok'])){
            sendmessage($from_id,'⚠️ پرداخت تایید شد اما تکمیل سفارش نیازمند بررسی پشتیبانی است.',null,'HTML');
            return;
        }
        update("user", "Processing_value_one", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_tow", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_four", "none", "id", $Payment_report['id_user']);
    } elseif (($StatusPayment['payment_status'] ?? '') === "expired") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['expired'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "refunded") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['refunded'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "waiting") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['waiting'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif (($StatusPayment['payment_status'] ?? '') === "sending") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['sending'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } else {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Failed'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    }
}
if (preg_match('/^sendresidcart-([A-Za-z0-9_-]{1,128})$/', $datain, $dataget)) {
    $timefivemin = date('Y/m/d H:i:s', time() - 120);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user=:id_user AND Payment_Method='cart to cart' AND at_updated>:cutoff");
    $stmt->execute([':id_user'=>(string)$from_id, ':cutoff'=>$timefivemin]);
    $paymentcount = (int)$stmt->fetchColumn();
    if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.", null, 'HTML');
        return;
    }
    $receiptLookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='cart to cart' LIMIT 1");
    $receiptLookup->execute([':order_id'=>$dataget[1], ':id_user'=>(string)$from_id]);
    $payemntcheck = $receiptLookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($payemntcheck)) {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Card receipt order ownership mismatch',['order_id'=>$dataget[1],'from_id'=>$from_id]);
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "🖼 تصویر رسید خود را ارسال نمایید", $backuser, 'HTML');
    step('cart_to_cart_user', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif (preg_match('/^sendresidarze-([A-Za-z0-9_-]{1,128})$/', $datain, $dataget) and $text_inline != null) {
    $receiptLookup = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='arze digital offline' LIMIT 1");
    $receiptLookup->execute([':order_id'=>$dataget[1], ':id_user'=>(string)$from_id]);
    $payemntcheck = $receiptLookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($payemntcheck)) {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Crypto receipt order ownership mismatch',['order_id'=>$dataget[1],'from_id'=>$from_id]);
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if (strtolower((string)$payemntcheck['payment_Status']) === "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.", $backuser, 'HTML');
    step('getresidcurrency', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == "digitaltron_hash_input") {


    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        if (!empty($message_id)) {
            @deletemessage($from_id, $message_id);
        }
        sendmessage(
            $from_id,
            "🏠 از حالت ارسال هش خارج شدید. هر زمان خواستید می‌توانید روی دکمه «📨 ارسال هش تراکنش» در فاکتور خودتان کلیک کنید.",
            $keyboard,
            'HTML'
        );
        return;
    }


    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 40
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);


        if ($trimmed !== '') {
            sendmessage($from_id, "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.", $keyboard, 'HTML');
        }
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_attach_hash')) {
        sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_extract_hash') || crypto_extract_hash($text) === null) {
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش ۶۴ رقمی hex یا لینک کامل تراکنش را ارسال کنید.", null, 'HTML');
        return;
    }
    $r = crypto_attach_hash($orderId, (string) $text, (string)$from_id);
    if (empty($r['ok'])) {
        $faMap = [
            'invalid-hash'      => 'هش وارد شده معتبر نیست.',
            'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است.',
            'order-not-pending' => 'این فاکتور در وضعیت قابل پرداخت نیست.',
            'no-db'             => 'خطای داخلی در پایگاه داده.',
            'db-update-failed'  => 'خطای داخلی در ذخیره‌سازی.',
        ];
        $msg = $faMap[$r['error'] ?? ''] ?? 'خطای داخلی در ثبت هش.';
        sendmessage($from_id, "❌ {$msg}", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    sendmessage(
        $from_id,
        "✅ هش تراکنش شما ثبت شد.\n\n"
        . "ربات هر <b>۱ دقیقه</b> یک بار به‌صورت خودکار شبکه را بررسی می‌کند:\n\n"
        . "✅ اگر همه چیز درست باشد → معمولاً <b>۱ تا ۲ دقیقه</b> طول می‌کشد و موجودی شارژ می‌شود.\n"
        . "❌ اگر مبلغ یا آدرس مقصد اشتباه باشد → <b>در همان دقیقه‌ی اول</b> پیام رد دریافت می‌کنید.\n"
        . "⏰ اگر هش روی شبکه پیدا نشود → پس از <b>۳۰ دقیقه</b> فاکتور لغو می‌شود.\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
        . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
        $keyboard,
        'HTML'
    );
} elseif ($user['step'] == "getresidcurrency") {
    $format_balance = number_format($user['Balance'], 0);
    $ownedReceipt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='arze digital offline' LIMIT 1");
    $ownedReceipt->execute([':order_id'=>(string)$user['Processing_value'], ':id_user'=>(string)$from_id]);
    $PaymentReport = $ownedReceipt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($PaymentReport) || strtolower((string)$PaymentReport['payment_Status']) !== 'unpaid') {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Crypto receipt final handler rejected order',['order_id'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
        return;
    }
    $Paymentusercount = select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
    step('home', $from_id);
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $textdiscount = "";
    $format_price_cart = number_format($PaymentReport['price'], 0);
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $user['Processing_value_one'], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
            return;
        }
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید سرویس جدید

نام کاربری سرویس : {$get_invoice['username']}
نام محصول : {$get_invoice['name_product']}
حجم محصول : {$get_invoice['Volume']} گیگ
زمان محصول : {$get_invoice['Service_time']} روز
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان


توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextenduser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($nameloc['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
تمدید
نام کاربری سرویس : $usernamepanel
نام محصول : {$prodcut['name_product']}
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextravolumeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید حجم اضافه
نام کاربری سرویس : $usernamepanel
حجم خریداری شده  : $volumes
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } elseif ($user['Processing_value_tow'] == "getextratimeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید زمان اضافه
نام کاربری سرویس : $usernamepanel
تعداد روز خریداری شده  : $time
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    } else {

        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .
افزایش موجودی
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💵 تعداد کل پرداختی های کاربر : $Paymentusercount عدد
💸 مبلغ پرداختی: $format_price_cart تومان

توضیحات: $caption $text
✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
    }
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => $textbotlang['users']['Balance']['receiptimage'],
                'parse_mode' => "HTML",
            ]);
        }
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    update("Payment_report", "dec_not_confirmed", "$text $caption", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($user['step'] == "cart_to_cart_user") {
    $format_balance = number_format($user['Balance'], 0);
    if (!$photo or isset($update['message']['media_group_id'])) {
        sendmessage($from_id, "❌  فقط مجاز به ارسال یک تصویر هستید", null, 'HTML');
        return;
    }
    $ownedReceipt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=:order_id AND id_user=:id_user AND Payment_Method='cart to cart' LIMIT 1");
    $ownedReceipt->execute([':order_id'=>(string)$user['Processing_value'], ':id_user'=>(string)$from_id]);
    $PaymentReport = $ownedReceipt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($PaymentReport) || strtolower((string)$PaymentReport['payment_Status']) !== 'unpaid') {
        if (function_exists('rx_log_event')) rx_log_event('PAYMENT_RECEIPT_FORBIDDEN','Card receipt final handler rejected order',['order_id'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
        return;
    }
    step('home', $from_id);
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price'], 0);
    $split_data = explode('|', $PaymentReport['id_invoice']);
    if ($split_data[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $split_data[1], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید", $keyboard, 'HTML');
            return;
        }
        $textdiscount = "";
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید سرویس جدید
نام کاربری سرویس  : {$get_invoice['username']}
نام محصول : {$get_invoice['name_product']}
حجم محصول : {$get_invoice['Volume']} گیگ
زمان محصول : {$get_invoice['Service_time']} روز
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextenduser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($service_other['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
تمدید
نام کاربری سرویس : $usernamepanel
نام محصول : {$prodcut['name_product']}
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد", $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextravolumeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید حجم اضافه
نام کاربری سرویس : $usernamepanel
حجم خریداری شده  : $volumes
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.", $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextratimeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .

⭕️⭕️⭕️⭕️⭕️
خرید زمان اضافه
نام کاربری سرویس : $usernamepanel
تعداد روز خریداری شده  : $time
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, "🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد", $keyboard, 'HTML');
    } else {

        $textsendrasid = "
⭕️ یک پرداخت جدید انجام شده است .
افزایش موجودی
👤 نام اکانت کاربر : $first_name
👤 شناسه کاربر:  <a href = \"tg://user?id=$from_id\">$from_id</a>
💸 موجودی فعلی کاربر : $format_balance تومان
🛒 کد پیگیری پرداخت: {$PaymentReport['id_order']}
⚜️ نام کاربری: @$username
💸 مبلغ پرداختی: $format_price_cart تومان

✍️ در صورت درست بودن رسید پرداخت را تایید نمایید.";
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        telegram('sendphoto', [
            'chat_id' => $id_admin,
            'photo' => $photoid,
            'caption' => $caption,
            'parse_mode' => "HTML",
        ]);
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($datain == "Discount") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }
    $checklimit = select("Discount", "*", "code", $text, "select");
    $__gstatus = strtolower(trim((string)($checklimit['status'] ?? '')));
    if ($__gstatus !== '' && $__gstatus !== 'active') {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gtarget = trim((string)($checklimit['target_user'] ?? ''));
    if ($__gtarget !== '' && $__gtarget !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gexp = intval($checklimit['expire_at'] ?? 0);
    if ($__gexp !== 0 && time() >= $__gexp) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    if (intval($checklimit['limituse']) > 0 && intval($checklimit['limitused']) >= intval($checklimit['limituse'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_user = $user['Balance'] + $get_codesql['price'];
    update("user", "Balance", $balance_user, "id", $from_id);
    $discountlimitadd = intval($checklimit['limitused']) + 1;
    update("Discount", "limitused", $discountlimitadd, "code", $text);
    step('home', $from_id);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], $get_codesql['price']);
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:id_user, :code)");
    $stmt->execute([
        ':id_user' => $from_id,
        ':code' => $text,
    ]);
    $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == $datatextbot['text_Tariff_list'] || $datain == "Tariff_list") {
    sendmessage($from_id, $datatextbot['text_dec_Tariff_list'], null, 'HTML');
} elseif ($datain == "colselist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
} elseif ($text == $datatextbot['text_affiliates'] || $datain == "affiliatesbtn") {
    if (!check_active_btn($setting['keyboardmain'], "text_affiliates")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['affiliatesstatus'] == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    $textaffiliates = "{$affiliates['description']}\n\n🔗 https://t.me/$usernamebot?start=$from_id";
    if (strlen($affiliates['id_media']) >= 5) {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $affiliates['id_media'],
            'caption' => $textaffiliates,
            'parse_mode' => "HTML",
        ]);
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $sqlPanel = "SELECT COUNT(*) AS orders, COALESCE(SUM(price_product), 0) AS total_price
                 FROM invoice
                 WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
                 AND refral = :refral
                 AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sqlPanel);
    $stmt->execute([':refral' => $from_id]);
    $inforefral = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'total_price' => 0];
    $orders_count = (int)($inforefral['orders'] ?? 0);
    $total_purchase = (float)($inforefral['total_price'] ?? 0);
    $keyboard_share = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🎁 دریافت هدیه عضویت", 'callback_data' => "get_gift_start"],
                ['text' => "🔗 اشتراک گذاری لینک", 'url' => "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id"],
            ],
        ]
    ]);
    $text_start = "";
    $text_porsant = "";
    $Percent_porsant = $setting['affiliatespercentage'];
    $sum_order = number_format($total_purchase, 0);
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $text_start = "<b>🎁 هدیه عضویت:</b>
• 🎉 مجموع هدیه: {$affiliatescommission['price_Discount']} تومان
• 🔻 ۵۰٪ برای شما (معرف)
• 🔻 ۵۰٪ برای زیرمجموعه (کاربر جدید)
";
    }
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $text_porsant = "<b>💸 پورسانت خرید:</b>
•  $Percent_porsant درصد از مبلغ خرید زیرمجموعه به شما تعلق می‌گیره";
    }
    $textaffiliates = "<b>💼 زیرمجموعه‌گیری و هدیه خوش‌آمد</b>

با دعوت دوستان از طریق <b>لینک اختصاصی</b>، بدون پرداخت حتی ۱ ریال کیف پولت شارژ میشه و از خدمات ربات استفاده می‌کنی!

$text_start
$text_porsant

<b>📊 آمار شما:</b>
• 👥 زیرمجموعه‌ها: {$user['affiliatescount']} نفر
• 🛒 خریدها: $orders_count عدد
• 💵 مجموع خرید: $sum_order تومان

<b>📢 دعوت کن، هدیه بگیر، رشد کن!</b>
";

    sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
} elseif ($datain == "get_gift_start") {
    $gift_status = select("affiliates", "*", null, null, "select");
    if ($gift_status['Discount'] == "offDiscountaffiliates") {
        sendmessage($from_id, "📛 این بخش درحال حاضر غیرفعال می باشد", $keyboard, 'HTML');
        return;
    }
    if (!userExists($user['affiliates'])) {
        sendmessage($from_id, "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
        return;
    }
    $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
    if (!$reagent) {
        $affiliateId = intval($user['affiliates']);
        if ($affiliateId && userExists($affiliateId)) {
            $stmt = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent)
                                   VALUES (:user_id, :get_gift, :time, :reagent)
                                   ON DUPLICATE KEY UPDATE reagent = VALUES(reagent), get_gift = VALUES(get_gift), time = VALUES(time)");
            $stmt->execute([
                ':user_id' => $from_id,
                ':get_gift' => 0,
                ':time' => date('Y/m/d H:i:s'),
                ':reagent' => $affiliateId,
            ]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('reagent_report');
            }
            $reagent = select("reagent_report", "*", "user_id", $from_id, "select", ['cache' => false]);
        }
        if (!$reagent) {
            sendmessage($from_id, "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
            return;
        }
    }
    if (!empty($reagent['get_gift'])) {
        sendmessage($from_id, "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>
این هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
        return;
    }
    update("reagent_report", "get_gift", true, "user_id", $from_id);
    $reagent['get_gift'] = true;
    $price_gift_Start = select("affiliates", "*", null, null, "select");
    $price_gift_Start = intval($price_gift_Start['price_Discount']) / 2;
    $useraffiliates = select("user", "*", 'id', $reagent['reagent'], "select");
    $Balance_add_regent = $useraffiliates['Balance'] + $price_gift_Start;
    update("user", "Balance", $Balance_add_regent, "id", $reagent['reagent']);
    $Balance_add_user = $user['Balance'] + $price_gift_Start;
    update("user", "Balance", $Balance_add_user, "id", $from_id);
    $addbalancediscount = number_format($price_gift_Start, 0);
    sendmessage($reagent['reagent'], "🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.", null, 'html');
    sendmessage($from_id, "🎉 هدیه عضویت برای شما فعال شد!", null, 'html');
    $report_join_gift = "🎁 پرداخت هدیه عضویت
 -آیدی عددی : $from_id
 - نام کاربری : @$username
 - آیدی عددی معرف : {$reagent['reagent']}
 - موجودی زیرمجموعه قبل از هدیه : {$user['Balance']}
 - موجودی زیرمجموعه بعد از هدیه : $Balance_add_user
  - موجودی معرف قبل از هدیه : {$useraffiliates['Balance']}
 - موجودی معرف بعد از هدیه : $Balance_add_regent
 ";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $porsantreport,
            'text' => $report_join_gift,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $locations = select("marzban_panel", "*", "code_panel", $dataget[2], "select");
    $location = $locations['name_panel'];
    $eextraprice = json_decode($locations['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    update("user", "Processing_value_one", $location, "id", $from_id);

    $textextra = sprintf($textbotlang['users']['Extra_volume']['enterextravolume'], $extrapricevalue);
    sendmessage($from_id, $textextra, $backuser, 'HTML');
    step('getvolumeextras', $from_id);
} elseif ($user['step'] == "getvolumeextras") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['Extra_volume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextras_(\w+)/', $datain, $dataget)) {
    $volume = $dataget[1];
    if ($user['Balance'] < $volume && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $volume - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volume = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }

    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_one'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + (intval($volume) / intval($extrapricevalue) * pow(1024, 3));
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price) VALUES (:id_user, :username, :value, :type, :time, :price)");
    $value = $data_limit;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $user['Processing_value'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volume,
    ]);
    $data_limit_new = (intval($volume) / intval($extrapricevalue));
    $extra_volume = $ManagePanel->extra_volume($user['Processing_value'], $marzban_list_get['code_panel'], $data_limit_new);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = redfox_remote_error_summary($extra_volume);
        $textreports = "خطای خرید حجم اضافه
نام پنل : {$user['Processing_value_one']}
نام کاربری سرویس : {$user['Processing_value']}
دلیل خطا : {$extra_volume['msg']}";
        sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegEv = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargeEv = balance_atomic_charge($from_id, (float)$volume, $__allowNegEv);
        if (empty($__chargeEv['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEv['new_balance'];
    } else {
        $Balance_Low_user = $user['Balance'] - $volume;
        update("user", "Balance", $Balance_Low_user, "id", $from_id);
    }
    $back = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
    $volumes = $volume / $extrapricevalue;
    $volumes = number_format($volumes, 0);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumepurchase'], $from_id, $volumes, $volume, $user['Balance'], $user['Processing_value']);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchservice") {
    sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
    step('getuseragnetservice', $from_id);
} elseif ($datain == "Responseuser") {
    step('getmessageAsuser', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backuser, 'HTML');
} elseif ($user['step'] == "getmessageAsuser") {
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        if ($text) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['usermessage'], $from_id, $username, $caption . $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['userresponse'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
} elseif (($text == $datatextbot['textpanelagent'] || $text == '👨‍💻 پنل نمایندگی' || $datain == "agentpanel") && $user['agent'] != "f") {
    if ($setting['inlinebtnmain'] == "oninline") {
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    }
} elseif ($datain == "reseller_ai_menu" || $text == "🤖 هوش مصنوعی ربات من") {
    // Red Fox: منوی هوش مصنوعی برای نماینده — نمایش وضعیت + دکمه خرید/درخواست
    $rxBotToken = '';
    $rxBotUsername = '';
    try {
        $rxBs = $pdo->prepare("SELECT bot_token, username FROM botsaz WHERE id_user = :uid LIMIT 1");
        $rxBs->execute([':uid' => (string)$from_id]);
        $rxBsRow = $rxBs->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxBsRow)) {
            $rxBotToken = (string)$rxBsRow['bot_token'];
            $rxBotUsername = (string)$rxBsRow['username'];
        }
    } catch (Throwable $e) {}

    if ($rxBotToken === '') {
        sendmessage($from_id, "❌ شما هنوز ربات نماینده‌ای نساخته‌اید. ابتدا از طریق مدیریت، ربات خود را بسازید.", $keyboard, 'HTML');
        return;
    }

    // وضعیت اشتراک
    $rxAiRow = null;
    try {
        $rxAst = $pdo->prepare("SELECT * FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
        $rxAst->execute([':t' => $rxBotToken]);
        $rxAiRow = $rxAst->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    $rxPrice = '0'; $rxDays = '30';
    try {
        $rxPrice = (string)$pdo->query("SELECT reseller_ai_price FROM setting LIMIT 1")->fetchColumn();
        $rxDays  = (string)$pdo->query("SELECT reseller_ai_days FROM setting LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}

    $rxStatusText = "🔴 غیرفعال";
    $rxExpInfo = "—";
    if (is_array($rxAiRow)) {
        $rxSt = (string)($rxAiRow['status'] ?? 'disabled');
        $rxExp = (string)($rxAiRow['expires_at'] ?? '');
        if ($rxSt === 'active' && $rxExp !== '') {
            $rxExpTs = strtotime($rxExp);
            if ($rxExpTs && $rxExpTs > time()) {
                $rxStatusText = "🟢 فعال";
                $rxDiff = $rxExpTs - time();
                $rxD = floor($rxDiff / 86400);
                $rxH = floor(($rxDiff % 86400) / 3600);
                $rxExpInfo = $rxExp . " (" . (int)$rxD . " روز و " . (int)$rxH . " ساعت باقی)";
            } else {
                $rxStatusText = "🔴 منقضی";
                $rxExpInfo = $rxExp;
            }
        } elseif ($rxSt === 'pending') {
            $rxStatusText = "🟡 در انتظار تأیید";
        }
    }

    $rxPriceFmt = number_format((int)$rxPrice);
    $rxMsg = "🤖 <b>قابلیت پاسخ‌گویی هوش مصنوعی برای ربات شما</b>\n\n";
    $rxMsg .= "🤖 ربات شما: @" . htmlspecialchars($rxBotUsername) . "\n";
    $rxMsg .= "📊 وضعیت اشتراک: <b>" . $rxStatusText . "</b>\n";
    $rxMsg .= "⏰ تاریخ انقضا: " . $rxExpInfo . "\n\n";
    $rxMsg .= "💡 <b>این قابلیت چیست؟</b>\n";
    $rxMsg .= "با فعال‌سازی این قابلیت، مشتریانِ ربات شما می‌توانند سؤالات خود را به دستیار هوش مصنوعی بپرسند و پاسخ آنی بگیرند — بدون نیاز به حضور شما!\n\n";
    $rxMsg .= "💰 <b>قیمت:</b> " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxMsg .= "👇 برای خرید یا تمدید، روی دکمه زیر بزنید:";

    $rxKb = json_encode([
        'inline_keyboard' => [
            [['text' => "🛒 خرید / تمدید هوش مصنوعی (" . $rxPriceFmt . " ت)", 'callback_data' => "reseller_ai_buy"]],
            [['text' => "🔙 بازگشت", 'callback_data' => "agentpanel"]],
        ]
    ]);
    if ($message_id && $datain) {
        Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML');
    } else {
        sendmessage($from_id, $rxMsg, $rxKb, 'HTML');
    }
} elseif ($datain == "reseller_ai_buy") {
    // Red Fox: نماینده درخواست خرید هوش مصنوعی داد → ثبت + اطلاع به ادمین
    $rxBotToken = '';
    $rxBotUsername = '';
    try {
        $rxBs = $pdo->prepare("SELECT bot_token, username FROM botsaz WHERE id_user = :uid LIMIT 1");
        $rxBs->execute([':uid' => (string)$from_id]);
        $rxBsRow = $rxBs->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxBsRow)) {
            $rxBotToken = (string)$rxBsRow['bot_token'];
            $rxBotUsername = (string)$rxBsRow['username'];
        }
    } catch (Throwable $e) {}

    if ($rxBotToken === '') {
        telegram('answerCallbackQuery', ['callback_query_id'=>$callback_query_id, 'text'=>'ربات نماینده‌ای یافت نشد', 'show_alert'=>true]);
        return;
    }

    // ثبت/به‌روزرسانی رکورد با وضعیت pending
    $rxNow = date('Y-m-d H:i:s');
    try {
        $rxChk = $pdo->prepare("SELECT id, status FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
        $rxChk->execute([':t'=>$rxBotToken]);
        $rxExist = $rxChk->fetch(PDO::FETCH_ASSOC);
        if (is_array($rxExist) && $rxExist['status'] === 'pending') {
            telegram('answerCallbackQuery', ['callback_query_id'=>$callback_query_id, 'text'=>'شما قبلاً درخواست داده‌اید و در انتظار تأیید است.', 'show_alert'=>true]);
            return;
        }
        if (is_array($rxExist)) {
            $pdo->prepare("UPDATE reseller_ai_feature SET status='pending', updated_at=:u WHERE bot_token=:t")
                ->execute([':u'=>$rxNow, ':t'=>$rxBotToken]);
        } else {
            $pdo->prepare("INSERT INTO reseller_ai_feature (reseller_id, bot_token, bot_username, status, created_at, updated_at) VALUES (:rid, :bt, :bu, 'pending', :c, :u)")
                ->execute([':rid'=>(string)$from_id, ':bt'=>$rxBotToken, ':bu'=>$rxBotUsername, ':c'=>$rxNow, ':u'=>$rxNow]);
        }
    } catch (Throwable $e) {
        error_log('[reseller_ai_buy] ' . redfox_exception_fingerprint($e));
    }

    $rxPrice = '0'; $rxDays = '30';
    try {
        $rxPrice = (string)$pdo->query("SELECT reseller_ai_price FROM setting LIMIT 1")->fetchColumn();
        $rxDays  = (string)$pdo->query("SELECT reseller_ai_days FROM setting LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}
    $rxPriceFmt = number_format((int)$rxPrice);

    // پیام به کاربر
    $rxUserMsg = "✅ درخواست شما ثبت شد!\n\n";
    $rxUserMsg .= "💰 مبلغ: " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxUserMsg .= "📌 لطفاً مبلغ را واریز کنید و رسید را ارسال کنید. پس از تأیید ادمین، قابلیت هوش مصنوعی برای ربات شما فعال خواهد شد.\n";
    $rxUserMsg .= "⏰ شما می‌توانید وضعیت را از منوی «🤖 هوش مصنوعی ربات من» پیگیری کنید.";
    Editmessagetext($from_id, $message_id, $rxUserMsg, json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['users']['backbtn'],'callback_data'=>'agentpanel']]]]), 'HTML');

    // اطلاع به ادمین
    $rxAdminMsg = "🤖 <b>درخواست خرید قابلیت هوش مصنوعی</b>\n\n";
    $rxAdminMsg .= "👤 نماینده: <a href=\"tg://user?id=$from_id\">$from_id</a> (@$username)\n";
    $rxAdminMsg .= "🤖 ربات: @" . htmlspecialchars($rxBotUsername) . "\n";
    $rxAdminMsg .= "💰 مبلغ درخواستی: " . $rxPriceFmt . " تومان برای " . htmlspecialchars($rxDays) . " روز\n\n";
    $rxAdminMsg .= "✅ برای تأیید و فعال‌سازی به پنل وب → «هوش مصنوعی نماینده‌ها» مراجعه کنید.";
    $rxAdminKb = json_encode(['inline_keyboard'=>[[['text'=>'🔗 پنل مدیریت هوش مصنوعی','url'=>'https://' . ($domainhosts ?? '') . '/panel/reseller_ai.php']]]]);
    foreach ($admin_ids as $rxAdmin) {
        sendmessage((string)$rxAdmin, $rxAdminMsg, $rxAdminKb, 'HTML');
    }
// ── Red Fox: قابلیت‌های سوپر نماینده در ربات اصلی ──
// ════════════════════════════════════════════════════════════════════
//  ۱) پاداش روزانه (/daily)
// ════════════════════════════════════════════════════════════════════
} elseif (($text == "/daily" || $text == "daily" || $text == "🎁 پاداش روزانه") && !in_array($from_id, $admin_ids)) {
    $drStatus = '';
    $drAmount = 1000;
    $drStreakBonus = 500;
    $drMaxStreak = 7;
    try {
        $drRow = $pdo->query("SELECT daily_reward_status, daily_reward_amount, daily_reward_streak_bonus, daily_reward_max_streak FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $drStatus = (string)($drRow['daily_reward_status'] ?? 'off');
        $drAmount = (int)($drRow['daily_reward_amount'] ?? 1000);
        $drStreakBonus = (int)($drRow['daily_reward_streak_bonus'] ?? 500);
        $drMaxStreak = (int)($drRow['daily_reward_max_streak'] ?? 7);
    } catch (Throwable $e) {}
    if ($drStatus !== 'on') {
        sendmessage($from_id, "❌ پاداش روزانه فعلاً غیرفعال است.", $keyboard, 'HTML');
    } else {
        $today = date('Y-m-d');
        $drExisting = null;
        try {
            $drSt = $pdo->prepare("SELECT * FROM daily_rewards WHERE user_id = ? LIMIT 1");
            $drSt->execute([$from_id]);
            $drExisting = $drSt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        if ($drExisting && $drExisting['last_claim_date'] === $today) {
            sendmessage($from_id, "✅ شما امروز پاداش خود را دریافت کرده‌اید.\n🔄 فردا دوباره /daily بزنید!\n🔥 استریک شما: " . (int)$drExisting['streak_count'] . " روز", $keyboard, 'HTML');
        } else {
            // محاسبه استریک
            $streak = 1;
            if ($drExisting) {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($drExisting['last_claim_date'] === $yesterday) {
                    $streak = (int)$drExisting['streak_count'] + 1;
                    if ($streak > $drMaxStreak) $streak = 1; // ریست بعد از ماکزیمم
                }
            }
            $reward = $drAmount + (($streak - 1) * $drStreakBonus);
            // شارژ موجودی
            $newBalance = (int)$user['Balance'] + $reward;
            update("user", "Balance", $newBalance, "id", $from_id);
            // ثبت در جدول
            try {
                if ($drExisting) {
                    $pdo->prepare("UPDATE daily_rewards SET last_claim_date = ?, streak_count = ?, total_claimed = total_claimed + 1, total_amount = total_amount + ? WHERE user_id = ?")
                        ->execute([$today, $streak, $reward, $from_id]);
                } else {
                    $pdo->prepare("INSERT INTO daily_rewards (user_id, last_claim_date, streak_count, total_claimed, total_amount) VALUES (?, ?, 1, 1, ?)")
                        ->execute([$from_id, $today, $reward]);
                }
            } catch (Throwable $e) {}
            $msg = "🎁 <b>پاداش روزانه شما!</b>\n\n";
            $msg .= "💰 مبلغ: <b>" . number_format($reward) . " تومان</b>\n";
            $msg .= "🔥 استریک: <b>$streak روز</b>\n";
            if ($streak < $drMaxStreak) {
                $nextReward = $drAmount + ($streak * $drStreakBonus);
                $msg .= "📅 فردا: " . number_format($nextReward) . " تومان (با استریک بیشتر!)\n";
            } else {
                $msg .= "🏆 شما به حداکثر استریک رسیدید!\n";
            }
            $msg .= "💎 موجودی جدید: <b>" . number_format($newBalance) . " تومان</b>";
            sendmessage($from_id, $msg, $keyboard, 'HTML');
        }
    }
    step('home', $from_id);

// ════════════════════════════════════════════════════════════════════
//  ۲) فعال/غیرفعال‌سازی تمدید خودکار
// ════════════════════════════════════════════════════════════════════
} elseif ($text == "🔄 تمدید خودکار" || $datain == "toggle_autorenew") {
    rx_require_schema($pdo,[],['user'=>['auto_renew']]);
    $currentAR = 0;
    try {
        $arRow = $pdo->prepare("SELECT auto_renew FROM user WHERE id = ? LIMIT 1");
        $arRow->execute([$from_id]);
        $currentAR = (int)$arRow->fetchColumn();
    } catch (Throwable $e) {}
    $newAR = $currentAR ? 0 : 1;
    try { $pdo->prepare("UPDATE user SET auto_renew = ? WHERE id = ?")->execute([$newAR, $from_id]); } catch (Throwable $e) {}
    if ($newAR) {
        sendmessage($from_id, "✅ <b>تمدید خودکار فعال شد!</b>\n\n💡 سرویس‌های شما قبل از انقضا، خودکار از کیف پول تمدید می‌شوند.\n⚠️ مطمئن شوید موجودی کافی دارید.", $keyboard, 'HTML');
    } else {
        sendmessage($from_id, "❌ تمدید خودکار غیرفعال شد.\n💡 برای فعال‌سازی مجدد دوباره بزنید.", $keyboard, 'HTML');
    }
    step('home', $from_id);

// ════════════════════════════════════════════════════════════════════
//  ۳) کمپین فروش ویژه — نمایش کمپین‌های فعال
// ════════════════════════════════════════════════════════════════════
} elseif ($text == "⚡ تخفیف ویژه" || $datain == "flash_sale_info") {
    $activeCampaigns = [];
    try {
        $fcStmt = $pdo->query("SELECT * FROM flash_campaigns WHERE is_active = 1 AND ends_at > NOW() AND starts_at <= NOW() ORDER BY ends_at ASC LIMIT 5");
        $activeCampaigns = $fcStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if (empty($activeCampaigns)) {
        sendmessage($from_id, "📭 در حال حاضر کمپین تخفیف فعالی وجود ندارد.", $keyboard, 'HTML');
    } else {
        $fcMsg = "⚡ <b>کمپین‌های فروش ویژه فعال!</b>\n\n";
        foreach ($activeCampaigns as $fc) {
            $fcMsg .= "🔥 <b>" . htmlspecialchars((string)$fc['title'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            $fcMsg .= "💶 تخفیف: <b>" . (int)$fc['discount_percent'] . "٪</b>\n";
            $fcMsg .= "⏰ تا: " . htmlspecialchars((string)$fc['ends_at']) . "\n\n";
        }
        $fcMsg .= "💡 همین حالا خرید کنید و از تخفیف بهره‌مند شوید!";
        $fcKb = json_encode(['inline_keyboard' => [[['text' => '🛍 خرید با تخفیف', 'callback_data' => 'buy']]]]);
        sendmessage($from_id, $fcMsg, $fcKb, 'HTML');
    }
    step('home', $from_id);

// ── Red Fox: قابلیت‌های سوپر نماینده در ربات اصلی ──
} elseif (($datain == "super_products" || $text == "🛍 محصولات") && $rxResellerCan('products')) {
    $rxMsg = "🛍 <b>مدیریت محصولات شما</b>\n\nبرای ایجاد یا ویرایش محصول، فرمت زیر را ارسال کنید:\n\n<code>نام_محصول-حجم-زمان-قیمت</code>\n\nمثال:\n<code>ماهانه ۱۰ گیگ-10-30-50000</code>\n(نام - حجم GB - زمان روز - قیمت تومان)\n\n💡 حجم 0 = نامحدود | زمان 0 = نامحدود";
    $rxKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "agentpanel"]]]]);
    if ($message_id && $datain) { Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML'); }
    else { sendmessage($from_id, $rxMsg, $rxKb, 'HTML'); }
    step('super_create_product', $from_id);
} elseif ($user['step'] == "super_create_product" && $text && $rxResellerCan('products')) {
    // فرمت: نام-حجم-زمان-قیمت
    $parts = explode('-', (string)$text);
    if (count($parts) >= 4) {
        $pName = mb_substr(trim($parts[0]), 0, 191);
        $pVolRaw = trim($parts[1]);
        $pTimeRaw = trim($parts[2]);
        $pPriceRaw = trim($parts[3]);
        $pVol = ctype_digit($pVolRaw) ? (int)$pVolRaw : -1;
        $pTime = ctype_digit($pTimeRaw) ? (int)$pTimeRaw : -1;
        $pPrice = ctype_digit($pPriceRaw) ? (int)$pPriceRaw : 0;
        if ($pName !== '' && $pPrice >= 1 && $pPrice <= 1000000000
            && $pVol >= 0 && $pVol <= 100000 && $pTime >= 0 && $pTime <= 36500) {
            $pCode = 'sr_' . bin2hex(random_bytes(3));
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO product (code_product, name_product, price_product, Volume_constraint, Service_time, agent, Location, category, note, data_limit_reset, hide_panel) VALUES (:code, :name, :price, :vol, :time, :agent, '/all', '', '', 'no_reset', '{}')");
                $stmt->bindValue(':code', $pCode);
                $stmt->bindValue(':name', $pName);
                $stmt->bindValue(':price', (string)$pPrice);
                $stmt->bindValue(':vol', (string)$pVol);
                $stmt->bindValue(':time', (string)$pTime);
                $stmt->bindValue(':agent', (string)$from_id); // محصول فقط برای این نماینده
                $stmt->execute();
                $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                    ->execute([
                        (string)$from_id,
                        'bot',
                        'product.create',
                        $pCode,
                        json_encode(['price' => $pPrice, 'volume_gb' => $pVol, 'days' => $pTime], JSON_UNESCAPED_UNICODE),
                        null,
                        time(),
                    ]);
                $pdo->commit();
                $safeProductName = htmlspecialchars($pName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                sendmessage($from_id, "✅ محصول «{$safeProductName}» با موفقیت ساخته شد!\n📦 حجم: " . ($pVol ?: 'نامحدود') . " GB\n⏳ زمان: " . ($pTime ?: 'نامحدود') . " روز\n💶 قیمت: " . number_format($pPrice) . " ت\n🔑 کد: <code>$pCode</code>", $keyboard, 'HTML');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                redfox_log_exception($e, 'reseller.product.create');
                sendmessage($from_id, "❌ ساخت محصول به‌دلیل خطای داخلی انجام نشد.", $keyboard, 'HTML');
            }
        } else { sendmessage($from_id, "❌ نام، حجم، زمان یا قیمت خارج از محدوده مجاز است.", $keyboard, 'HTML'); }
    } else { sendmessage($from_id, "❌ فرمت اشتباه. مثال:\n<code>ماهانه ۱۰ گیگ-10-30-50000</code>", $keyboard, 'HTML'); }
    step('home', $from_id);

} elseif (($datain == "super_categories" || $text == "📂 دسته‌بندی") && $rxResellerCan('categories')) {
    sendmessage($from_id, "📂 <b>ایجاد دسته‌بندی</b>\n\nنام دسته‌بندی را ارسال کنید:", null, 'HTML');
    step('super_create_category', $from_id);
} elseif ($user['step'] == "super_create_category" && $text && $rxResellerCan('categories')) {
    $catName = mb_substr(trim((string)$text), 0, 191);
    if ($catName !== '') {
        try {
            $categoryId = 'rc-' . bin2hex(random_bytes(8));
            $now = time();
            $pdo->beginTransaction();
            $rxAssertLockedPermission('categories');
            $pdo->prepare("INSERT INTO reseller_categories (reseller_id,name,slug,sort_order,status,created_at,updated_at) VALUES (?,?,?,0,'active',?,?)")
                ->execute([(string)$from_id, $catName, $categoryId, $now, $now]);
            $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    (string)$from_id,
                    'bot',
                    'category.create',
                    $categoryId,
                    json_encode(['name_length' => mb_strlen($catName, 'UTF-8')], JSON_UNESCAPED_UNICODE),
                    null,
                    $now,
                ]);
            $pdo->commit();
            $safeCategoryName = htmlspecialchars($catName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            sendmessage($from_id, "✅ دسته‌بندی مستقل «{$safeCategoryName}» ساخته شد!", $keyboard, 'HTML');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redfox_log_exception($e, 'reseller.category.create');
            sendmessage($from_id, "❌ ساخت دسته‌بندی به‌دلیل خطای داخلی انجام نشد.", $keyboard, 'HTML');
        }
    } else { sendmessage($from_id, "❌ نام خالی است.", $keyboard, 'HTML'); }
    step('home', $from_id);

} elseif (($datain == "super_extend" || $text == "⏰ حجم/زمان کاربر") && $rxResellerCan('extend_user')) {
    sendmessage($from_id, "⏰ <b>افزایش حجم/زمان کاربر</b>\n\nآیدی عددی کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_extend_main', $from_id);
} elseif ($user['step'] == "super_extend_main" && $text && $rxResellerCan('extend_user')) {
    if (!ctype_digit((string)$text)) { sendmessage($from_id, "❌ آیدی عددی معتبر ارسال کنید.", $keyboard, 'HTML'); return; }
    $rxTargetUser = $rxFindScopedUser((string)$text);
    $rxTargetInv = $rxTargetUser ? $rxFindScopedInvoice((string)$text) : null;
    if (!$rxTargetInv) { sendmessage($from_id, "❌ کاربر یا سرویس در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'extend_target', (string)$text);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\nسرویس: <code>{$rxTargetInv['username']}</code>\n\nفرمت: <code>حجم_روز</code> (مثال: <code>5_30</code>):", $keyboard, 'HTML');
    step('super_extend_val_main', $from_id);
} elseif ($user['step'] == "super_extend_val_main" && $text && $rxResellerCan('extend_user')) {
    $parts = explode('_', (string)$text);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) { sendmessage($from_id, "❌ فرمت اشتباه. مثال: 5_30", $keyboard, 'HTML'); return; }
    $volGB = (int)$parts[0]; $days = (int)$parts[1];
    if (($volGB < 1 && $days < 1) || $volGB > 100000 || $days > 36500) {
        sendmessage($from_id, "❌ حجم یا زمان خارج از محدوده مجاز است.", $keyboard, 'HTML');
        return;
    }
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetUid = is_array($pv) ? (string)($pv['extend_target'] ?? '') : '';
    $rxTargetUser = $rxFindScopedUser($targetUid);
    $rxTargetInv = $rxTargetUser ? $rxFindScopedInvoice($targetUid) : null;
    if (!$rxTargetInv) { sendmessage($from_id, "❌ سرویس در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    if (isset($ManagePanel)) {
        // Persist a freshly authorized intent under row locks before the
        // irreversible provider request. Revocation or ownership changes
        // racing the conversation step cannot authorize this operation.
        $extendAuditId = 0;
        try {
            $pdo->beginTransaction();
            $rxAssertLockedPermission('extend_user');

            $lockedBotToken = '';
            if ($rxOwnBotToken !== '') {
                $botScopeLock = $pdo->prepare('SELECT bot_token FROM botsaz WHERE id_user=? AND bot_token=? LIMIT 1 FOR UPDATE');
                $botScopeLock->execute([(string)$from_id, $rxOwnBotToken]);
                $tokenValue = $botScopeLock->fetchColumn();
                if ($tokenValue !== false) $lockedBotToken = (string)$tokenValue;
            }

            $invoiceId = trim((string)($rxTargetInv['id_invoice'] ?? ''));
            if ($invoiceId === '') {
                throw new RuntimeException('extend invoice identity missing');
            }
            if ($lockedBotToken !== '') {
                $invoiceLock = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=? AND id_user=? AND (refral=? OR bottype=?) LIMIT 1 FOR UPDATE');
                $invoiceLock->execute([$invoiceId, $targetUid, (string)$from_id, $lockedBotToken]);
            } else {
                $invoiceLock = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice=? AND id_user=? AND refral=? LIMIT 1 FOR UPDATE');
                $invoiceLock->execute([$invoiceId, $targetUid, (string)$from_id]);
            }
            $lockedInvoice = $invoiceLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lockedInvoice)) {
                throw new RuntimeException('extend invoice scope changed');
            }
            $rxTargetInv = $lockedInvoice;

            $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    (string)$from_id,
                    'bot',
                    'service.extend.requested',
                    $invoiceId,
                    json_encode(['target_user_id' => $targetUid, 'volume_gb' => $volGB, 'days' => $days], JSON_UNESCAPED_UNICODE),
                    null,
                    time(),
                ]);
            $extendAuditId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redfox_log_exception($e, 'reseller.service.extend.audit_request');
            sendmessage($from_id, "❌ تمدید انجام نشد؛ مجوز، مالکیت سرویس یا ثبت گزارش امنیتی معتبر نبود.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }

        $panel = select("marzban_panel", "*", "name_panel", (string)$rxTargetInv['Service_location'], "select");
        if (!is_array($panel) || empty($panel['code_panel'])) {
            try {
                $pdo->prepare("UPDATE reseller_audit_log SET action='service.extend.failed',details=? WHERE id=? AND reseller_id=?")
                    ->execute([json_encode(['reason' => 'panel_not_found'], JSON_UNESCAPED_UNICODE), $extendAuditId, (string)$from_id]);
            } catch (Throwable $ignored) {
            }
            sendmessage($from_id, "❌ پنل سرویس در دسترس نیست.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }

        try {
            $extResult = $ManagePanel->extend("ریست حجم و زمان", $volGB, $days, $rxTargetInv['username'], $rxTargetInv['code_product'] ?? 'custom', $panel['code_panel']);
        } catch (Throwable $e) {
            $extResult = ['status' => false];
            redfox_log_exception($e, 'reseller.service.extend.provider');
        }
        $extendSucceeded = !empty($extResult['status']);
        try {
            if ($extendAuditId > 0) {
                $pdo->prepare('UPDATE reseller_audit_log SET action=?, details=? WHERE id=? AND reseller_id=?')
                    ->execute([
                        $extendSucceeded ? 'service.extend.succeeded' : 'service.extend.failed',
                        json_encode(['target_user_id' => $targetUid, 'volume_gb' => $volGB, 'days' => $days], JSON_UNESCAPED_UNICODE),
                        $extendAuditId,
                        (string)$from_id,
                    ]);
            }
        } catch (Throwable $e) {
            redfox_log_exception($e, 'reseller.service.extend.audit_result');
        }
        if ($extendSucceeded) {
            sendmessage($from_id, "✅ به کاربر <code>$targetUid</code>، $volGB گیگ و $days روز اضافه شد.", $keyboard, 'HTML');
            @sendmessage($targetUid, "🎁 سرویس شما تمدید شد!\n💾 $volGB گیگ\n⏰ $days روز", null, 'HTML');
        } else {
            sendmessage($from_id, "❌ خطا در تمدید.", $keyboard, 'HTML');
        }
    } else { sendmessage($from_id, "❌ پنل یافت نشد.", $keyboard, 'HTML'); }
    step('home', $from_id);
} elseif (($datain == "super_charge" || $text == "💰 شارژ کاربر") && $rxResellerCan('charge_user')) {
    sendmessage($from_id, "💰 <b>شارژ کیف پول کاربر</b>\n\nآیدی عددی کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_charge_main', $from_id);
} elseif ($user['step'] == "super_charge_main" && $text && $rxResellerCan('charge_user')) {
    if (!ctype_digit((string)$text)) { sendmessage($from_id, "❌ آیدی عددی معتبر.", $keyboard, 'HTML'); return; }
    $rxTarget = $rxFindScopedUser((string)$text);
    if (!$rxTarget) { sendmessage($from_id, "❌ کاربر در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'charge_target', (string)$text);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\nموجودی فعلی: " . number_format((int)$rxTarget['Balance']) . " ت\n\nمبلغ مثبت انتقال از کیف پول نمایندگی (حداکثر ۱٬۰۰۰٬۰۰۰٬۰۰۰):", $keyboard, 'HTML');
    step('super_charge_amt_main', $from_id);
} elseif ($user['step'] == "super_charge_amt_main" && $text && $rxResellerCan('charge_user')) {
    $amountText = trim((string)$text);
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetUid = is_array($pv) ? (string)($pv['charge_target'] ?? '') : '';
    if (!ctype_digit($amountText) || $amountText === '0' || strlen($amountText) > 10) {
        sendmessage($from_id, "❌ مبلغ باید عددی مثبت و حداکثر یک میلیارد تومان باشد.", $keyboard, 'HTML');
        return;
    }
    $amt = (int)$amountText;
    if ($amt < 1 || $amt > 1000000000 || !$rxFindScopedUser($targetUid)) {
        sendmessage($from_id, "❌ مبلغ یا کاربر خارج از محدوده مجاز است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $chargeError = '';
    try {
        $pdo->beginTransaction();

        if ($targetUid === '' || hash_equals((string)$from_id, $targetUid)) {
            $chargeError = 'انتقال به حساب نمایندگی مجاز نیست.';
            throw new RuntimeException('reseller self charge denied');
        }

        // Lock actor and target in one deterministic key order. This avoids
        // reciprocal-transfer deadlocks caused by locking each owner first.
        $userLocks = $pdo->prepare('SELECT id,Balance,agent,reseller_perms,bottype FROM user WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $userLocks->execute([(string)$from_id, $targetUid]);
        $lockedUsers = [];
        while ($lockedUser = $userLocks->fetch(PDO::FETCH_ASSOC)) {
            $lockedUsers[(string)$lockedUser['id']] = $lockedUser;
        }
        $ownerRow = $lockedUsers[(string)$from_id] ?? null;
        $targetRow = $lockedUsers[$targetUid] ?? null;
        $lockedPerms = is_array($ownerRow) ? json_decode((string)($ownerRow['reseller_perms'] ?? '{}'), true) : [];
        if (!is_array($ownerRow)
            || !in_array((string)($ownerRow['agent'] ?? ''), ['n', 'n2'], true)
            || !is_array($lockedPerms)
            || empty($lockedPerms['charge_user'])) {
            $chargeError = 'دسترسی شارژ نمایندگی لغو شده است.';
            throw new RuntimeException('reseller charge permission revoked');
        }
        if (!is_array($targetRow)) {
            $chargeError = 'کاربر دیگر در دسترس نیست.';
            throw new RuntimeException('scoped customer disappeared');
        }
        $ownerBalance = (int)($ownerRow['Balance'] ?? 0);
        if ($ownerBalance < $amt) {
            $chargeError = 'موجودی نمایندگی کافی نیست.';
            throw new RuntimeException('reseller balance insufficient');
        }

        // Lock the token ownership row used by legacy own-bot scoping so it
        // cannot be reassigned while target scope is being established.
        $lockedBotToken = '';
        if ($rxOwnBotToken !== '') {
            $botScopeLock = $pdo->prepare('SELECT bot_token FROM botsaz WHERE id_user=? AND bot_token=? LIMIT 1 FOR UPDATE');
            $botScopeLock->execute([(string)$from_id, $rxOwnBotToken]);
            $lockedBotTokenValue = $botScopeLock->fetchColumn();
            if ($lockedBotTokenValue !== false) $lockedBotToken = (string)$lockedBotTokenValue;
        }

        // The target row and the exact ownership evidence are both locked and
        // rechecked inside this transaction. A concurrent referral/bot-scope
        // change can no longer turn the earlier precheck into an out-of-scope
        // credit.
        $scopeLocked = $lockedBotToken !== ''
            && (string)($targetRow['bottype'] ?? '') !== ''
            && hash_equals($lockedBotToken, (string)$targetRow['bottype']);
        if (!$scopeLocked) {
            if ($lockedBotToken !== '') {
                $scopeLock = $pdo->prepare('SELECT id_invoice FROM invoice WHERE id_user=? AND (refral=? OR bottype=?) LIMIT 1 FOR UPDATE');
                $scopeLock->execute([$targetUid, (string)$from_id, $lockedBotToken]);
            } else {
                $scopeLock = $pdo->prepare('SELECT id_invoice FROM invoice WHERE id_user=? AND refral=? LIMIT 1 FOR UPDATE');
                $scopeLock->execute([$targetUid, (string)$from_id]);
            }
            $scopeLocked = $scopeLock->fetchColumn() !== false;
        }
        if (!$scopeLocked) {
            $chargeError = 'کاربر از محدوده نمایندگی خارج شده است.';
            throw new RuntimeException('customer scope changed before charge');
        }
        $targetBalance = (int)($targetRow['Balance'] ?? 0);

        $debit = $pdo->prepare('UPDATE user SET Balance=Balance-? WHERE id=? AND Balance>=?');
        $debit->execute([$amt, (string)$from_id, $amt]);
        if ($debit->rowCount() !== 1) {
            $chargeError = 'موجودی نمایندگی کافی نیست.';
            throw new RuntimeException('conditional reseller debit failed');
        }
        $credit = $pdo->prepare('UPDATE user SET Balance=Balance+? WHERE id=?');
        $credit->execute([$amt, $targetUid]);
        if ($credit->rowCount() !== 1) {
            $chargeError = 'واریز به کاربر انجام نشد.';
            throw new RuntimeException('customer credit failed');
        }
        $pdo->prepare('INSERT INTO reseller_wallet_ledger(from_user_id,to_user_id,amount,reason,actor_reseller_id,created_at) VALUES(?,?,?,?,?,?)')
            ->execute([(string)$from_id, $targetUid, $amt, 'main_bot_customer_charge', (string)$from_id, time()]);
        $pdo->prepare('INSERT INTO reseller_audit_log(reseller_id,actor_role,action,entity,details,ip,created_at) VALUES(?,?,?,?,?,?,?)')
            ->execute([(string)$from_id, 'bot', 'customer.charge', $targetUid, json_encode(['amount' => $amt]), null, time()]);
        $pdo->commit();
        $newBal = (int)$targetBalance + $amt;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redfox_log_exception($e, 'reseller.customer.charge');
        sendmessage($from_id, "❌ " . ($chargeError !== '' ? $chargeError : 'انتقال اعتبار به‌دلیل خطای داخلی انجام نشد.'), $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ از کیف پول نمایندگی به کاربر <code>$targetUid</code> منتقل شد.\nمبلغ: " . number_format($amt) . " ت\nموجودی جدید کاربر: " . number_format($newBal) . " ت", $keyboard, 'HTML');
    @sendmessage($targetUid, "💰 کیف پول شما توسط نماینده به مبلغ " . number_format($amt) . " تومان شارژ شد.\nموجودی جدید: " . number_format($newBal) . " تومان", null, 'HTML');
} elseif (($datain == "super_search" || $text == "👥 جستجوی کاربر") && $rxResellerCan('manage_users')) {
    sendmessage($from_id, "🔍 آیدی عددی یا یوزرنیم کاربر را ارسال کنید:", $keyboard, 'HTML');
    step('super_search_main', $from_id);
} elseif ($user['step'] == "super_search_main" && $text && $rxResellerCan('manage_users')) {
    $q = trim((string)$text);
    $rxTarget = $rxFindScopedUser($q, !ctype_digit($q));
    if (!$rxTarget) { sendmessage($from_id, "❌ کاربر در محدوده نمایندگی شما یافت نشد.", $keyboard, 'HTML'); step('home', $from_id); return; }
    $rxMsg = "👤 <b>اطلاعات کاربر</b>\n\n🆔 آیدی: <code>{$rxTarget['id']}</code>\n👤 نام: " . htmlspecialchars((string)$rxTarget['username']) . "\n💰 موجودی: " . number_format((int)$rxTarget['Balance']) . " ت\n📊 وضعیت: " . htmlspecialchars((string)$rxTarget['User_Status']) . "\n🏷 نوع: " . htmlspecialchars((string)$rxTarget['agent']);
    sendmessage($from_id, $rxMsg, $keyboard, 'HTML');
    step('home', $from_id);
} elseif (($datain == "super_reports" || $text == "📊 گزارش فروش") && $rxResellerCan('reports')) {
    $rid = (string)$from_id;
    $done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
    try {
        $salesStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE refral = ? AND Status IN ($done)");
        $salesStmt->execute([$rid]);
        $sales = (int)$salesStmt->fetchColumn();
        $revStmt = $pdo->prepare("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ($done)");
        $revStmt->execute([$rid]);
        $rev = (int)$revStmt->fetchColumn();
        $custStmt = $pdo->prepare("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE refral = ?");
        $custStmt->execute([$rid]);
        $custs = (int)$custStmt->fetchColumn();
    } catch (Throwable $e) { $sales = 0; $rev = 0; $custs = 0; }
    $rxMsg = "📊 <b>گزارش فروش شما</b>\n\n🛍 فروش موفق: <b>$sales</b>\n💰 درآمد کل: <b>" . number_format($rev) . " ت</b>\n👥 مشتریان: <b>$custs</b>\n💎 موجودی شما: <b>" . number_format((int)$user['Balance']) . " ت</b>";
    sendmessage($from_id, $rxMsg, $keyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['users']['agenttext']['customnameusername'] || $datain == "selectname") {
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
    step('selectusernamecustom', $from_id);
} elseif ($user['step'] == "selectusernamecustom") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['submitusername'], $keyboardagent, 'html');
    update("user", "namecustom", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == $datatextbot['textrequestagent'] || $datain == "requestagent") {
    if ($user['Balance'] < $setting['agentreqprice']) {
        $priceagent = number_format($setting['agentreqprice']);
        sendmessage($from_id, sprintf($textbotlang['users']['agenttext']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
        return;
    }
    $existingAgentRequest = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($existingAgentRequest) {
        // فقط درخواستی که هنوز در حال بررسی (waiting) است مانع ثبت درخواست جدید می‌شود.
        if ($existingAgentRequest['status'] == "waiting") {
            sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], null, 'html');
            return;
        }
        // اگر درخواست قبلی رد شده بود، رکورد قدیمی پاک می‌شود تا کاربر بتواند مجدداً درخواست دهد.
        if ($existingAgentRequest['status'] == "reject") {
            $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id AND status = 'reject'");
            $stmtDelOld->execute([':id' => $from_id]);
        }
    }
    if ($user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['agenttext']['isagent'], null, 'html');
        return;
    }
    if ($datain == "requestagent") {
        Editmessagetext($from_id, $message_id, $datatextbot['text_request_agent_dec'], $backuser);
    } else {
        sendmessage($from_id, $datatextbot['text_request_agent_dec'], $backuser, 'html');
    }
    step("getagentrequest", $from_id);
} elseif ($user['step'] == "getagentrequest" && $text) {
    // اطمینان از نبود درخواست قدیمیِ رد/تاییدشده تا INSERT جدید با خطای کلید تکراری مواجه نشود.
    $oldReq = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($oldReq && $oldReq['status'] == "waiting") {
        // اگر همزمان درخواست در حال بررسی ثبت شده، از ثبت دوباره جلوگیری می‌کنیم.
        sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($oldReq) {
        $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id");
        $stmtDelOld->execute([':id' => $from_id]);
    }
    $balancelow = $user['Balance'] - $setting['agentreqprice'];
    update("user", "Balance", $balancelow, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['agenttext']['endrequest'], $keyboard, 'html');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
    $status = "waiting";
    $type = "None";
    $current_time = time();
    $description = $text;
    $requestAgentInserted = false;
    try {
        $stmt->execute([
            ':id' => $from_id,
            ':username' => $username,
            ':time' => $current_time,
            ':description' => $description,
            ':status' => $status,
            ':type' => $type,
        ]);
        $requestAgentInserted = true;
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1366) {
            $tableConverted = ensureTableUtf8mb4('Requestagent');
            if ($tableConverted) {
                try {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $description,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . redfox_exception_fingerprint($retryException));
                }
            }

            if (!$requestAgentInserted) {
                $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                if ($sanitisedDescription !== $description) {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $sanitisedDescription,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } else {
                    throw $e;
                }
            }
        } else {
            throw $e;
        }
    }

    if (!$requestAgentInserted) {
        throw new RuntimeException('Failed to persist agent request description.');
    }
    $textrequestagent = sprintf($textbotlang['users']['agenttext']['agent-request'], $from_id, $username, $first_name, $text);
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
    }
} elseif ($text == "/privacy") {
    sendmessage($from_id, $datatextbot['text_roll'], null, 'HTML');
} elseif ($text == $datatextbot['text_wheel_luck'] || $datain == "wheel_luck" || $text == "/gift") {
    if (!check_active_btn($setting['keyboardmain'], "text_wheel_luck")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['wheelagent'] == "0" and $user['agent'] != "f") {
        sendmessage($from_id, "❌ این دکمه برای شما غیرفعال می باشد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if (intval($setting['statusfirstwheel']) == 1 and $countinvoice != 0) {
        sendmessage($from_id, "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.", null, 'HTML');
        return;
    }
    if ($setting['wheelـluck'] == "0" or ($setting['wheelagent'] == "0" and $users['agent'] != "f")) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['feature-disabled'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if (intval($setting['Dice']) == 1) {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎲",
        ]);
        sleep(4.5);
    } else {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎰",
        ]);
        sleep(2);
    }
    if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
        $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
        error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['error'] ?? '❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.', null, 'HTML');
        return;
    }
    $diceValue = (int) $diceResponse['result']['dice']['value'];
    $dateacc = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    $status = false;
    if (intval($setting['Dice']) == 1) {
        if ($diceValue === 6) {
            $status = true;
        }
    } else {
        if (in_array($diceValue, [1, 43, 64, 22], true)) {
            $status = true;
        }
    }
    if ($status) {
        $balance_last = intval($setting['wheelـluck_price']) + $user['Balance'];
        update("user", "Balance", $balance_last, "id", $from_id);
        $price = number_format($setting['wheelـluck_price']);
        sendmessage($from_id, sprintf($textbotlang['users']['wheel_luck']['winner-congratulations'], $price), null, 'HTML');
        $pricelast = $setting['wheelـluck_price'];
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => sprintf($textbotlang['users']['wheel_luck']['wheel-winner'], $username, $from_id),
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['notWinner'], null, 'HTML');
        $pricelast = 0;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price) VALUES (:id_user,:first_name,:wheel_code,:time,:price)");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':wheel_code', $diceValue);
    $stmt->bindParam(':time', $dateacc);
    $stmt->bindParam(':price', $pricelast);
    $stmt->execute();
} elseif ($text == "/tron") {
    $rates = requireTronRates(['TRX']);
    if ($rates === null) {
        sendmessage($from_id, "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['TRX'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tron-price'], $price), null, 'HTML');
} elseif ($text == "/usd") {
    $rates = requireTronRates(['USD']);
    if ($rates === null) {
        sendmessage($from_id, "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['USD'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tether-price'], $price), null, 'HTML');
} elseif ($text == $datatextbot['text_extend'] or $datain == "extendbtn") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
        return;
    }
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $_start_index_i = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_start_index_i, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_si2 = (int)$start_index; $_p2 = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_si2, $_p2);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == 'previous_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $_pp = (int)$previous_page; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_pp, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == "linkappdownlod" || $text === "📱 نرم‌افزارهای اتصال") {
    $platforms=[];try{$platforms=$pdo->query("SELECT DISTINCT platform FROM app WHERE enabled=1 ORDER BY platform")->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable$e){}
    if(!$platforms){sendmessage($from_id,'❌ هنوز نرم‌افزار فعالی ثبت نشده است.',null,'HTML');return;}
    $kb=['inline_keyboard'=>[]];foreach($platforms as$platform){$token=rtrim(strtr(base64_encode((string)$platform),'+/','-_'),'=');$kb['inline_keyboard'][]=[['text'=>'💻 '.(string)$platform,'callback_data'=>'appplatform_'.$token]];}$kb['inline_keyboard'][]=[['text'=>$textbotlang['users']['backbtn'],'callback_data'=>'backuser']];sendmessage($from_id,'📱 سیستم‌عامل خود را انتخاب کنید:',json_encode($kb,JSON_UNESCAPED_UNICODE),'HTML');
} elseif (preg_match('/^appplatform_([A-Za-z0-9_-]+)$/',$datain,$appMatch)) {
    $raw=strtr($appMatch[1],'-_','+/');$raw.=str_repeat('=',(4-strlen($raw)%4)%4);$platform=base64_decode($raw,true);if(!is_string($platform)){return;}$q=$pdo->prepare('SELECT name,link FROM app WHERE enabled=1 AND platform=? ORDER BY name');$q->execute([$platform]);$apps=$q->fetchAll(PDO::FETCH_ASSOC);$kb=['inline_keyboard'=>[]];foreach($apps as$app)$kb['inline_keyboard'][]=[['text'=>$app['name'],'url'=>$app['link']]];$kb['inline_keyboard'][]=[['text'=>'بازگشت به سیستم‌عامل‌ها','callback_data'=>'linkappdownlod']];Editmessagetext($from_id,$message_id,'📥 نرم‌افزارهای '.htmlspecialchars($platform,ENT_QUOTES,'UTF-8'),json_encode($kb,JSON_UNESCAPED_UNICODE),'HTML');
} elseif (preg_match('/changenote_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $rxNoteCheck = select("invoice", "id_user", "id_invoice", $id_invoice, "select");
    if (!is_array($rxNoteCheck) || (string)($rxNoteCheck['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changenote on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changenote',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['SendNote'], $backinfoss);
    step("getnotedit", $from_id);
} elseif ($user['step'] == "getnotedit") {
    $invoice = rxOwnedInvoice($pdo, $user['Processing_value'], $from_id);
    if (!is_array($invoice)) {
        if (function_exists('rx_log_event')) rx_log_event('INVOICE_OWNERSHIP_DENIED','getnotedit stopped before mutation',['invoice'=>(string)$user['Processing_value'],'from_id'=>$from_id]);
        step('home', $from_id);
        return;
    }
    if (strlen($text) > 150) {
        sendmessage($from_id, $textbotlang['users']['note']['ErrorLongNote'], $keyboard, "html");
        return;
    }
    $text = sanitizeUserName($text);
    $id_invoice = $user['Processing_value'];
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    update("invoice", "note", $text, "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
    step("home", $from_id);
    $timejalali = jdate('Y/m/d H:i:s');
    $textreport = "📌  یک کاربر یادداشت سرویس خود را تغییر داد.

▫️ نام کاربری سرویس : {$invoice['username']}
▫️ یاداشت قبلی :‌ {$invoice['note']}
▫️ یاداشت جدید :‌  $text

زمان تغییر یادداشت : $timejalali ";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
