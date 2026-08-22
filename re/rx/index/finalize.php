<?php

if (isset($update['pre_checkout_query'])) {
    $pc = $update['pre_checkout_query'];
    $idOrder = trim((string)($pc['invoice_payload'] ?? ''));
    $payer = (string)($pc['from']['id'] ?? '');
    $currency = strtoupper(trim((string)($pc['currency'] ?? '')));
    $totalAmount = $pc['total_amount'] ?? null;
    $row = false;
    if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $idOrder)) {
        $q = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=? AND id_user=? AND Payment_Method='Star Telegram' AND provider_name='telegram_stars' AND provider_invoice_id=? LIMIT 2");
        $q->execute([$idOrder, $payer, $idOrder]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $duplicate = $q->fetch(PDO::FETCH_ASSOC);
        if (is_array($duplicate)) $row = false;
    }
    $ok = is_array($row)
        && strtolower((string)$row['payment_Status']) === 'unpaid'
        && $currency === 'XTR'
        && strtoupper((string)$row['provider_currency']) === 'XTR'
        && is_int($totalAmount)
        && $totalAmount > 0
        && (int)$row['provider_amount'] === $totalAmount;
    telegram('answerPreCheckoutQuery', [
        'pre_checkout_query_id'=>(string)($pc['id'] ?? ''),
        'ok'=>$ok,
        'error_message'=>$ok ? null : 'فاکتور، ارز یا مبلغ پرداخت نامعتبر است',
    ]);
    if ($ok) {
        $audit = json_encode($pc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $save = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed=? WHERE id=? AND payment_Status='Unpaid'");
        $save->execute([$audit, (int)$row['id']]);
    } elseif (function_exists('rx_log_event')) {
        rx_log_event('STARS_PRECHECKOUT_REJECTED', 'Telegram Stars pre-checkout binding failed', [
            'order_id'=>$idOrder, 'payer'=>$payer, 'currency'=>$currency,
        ]);
    }
} elseif (isset($update['message']['successful_payment'])) {
    $sp = $update['message']['successful_payment'];
    $idOrder = trim((string)($sp['invoice_payload'] ?? ''));
    $payer = (string)($update['message']['from']['id'] ?? '');
    $currency = strtoupper(trim((string)($sp['currency'] ?? '')));
    $totalAmount = $sp['total_amount'] ?? null;
    $telegramCharge = trim((string)($sp['telegram_payment_charge_id'] ?? ''));
    $providerCharge = trim((string)($sp['provider_payment_charge_id'] ?? ''));
    $validIdentifiers = preg_match('/^[A-Za-z0-9_-]{1,128}$/', $idOrder)
        && preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $telegramCharge)
        && ($providerCharge === '' || preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $providerCharge));
    $row = false;
    if ($validIdentifiers) {
        $q = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order=? AND id_user=? AND Payment_Method='Star Telegram' AND provider_name='telegram_stars' AND provider_invoice_id=? LIMIT 2");
        $q->execute([$idOrder, $payer, $idOrder]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $duplicate = $q->fetch(PDO::FETCH_ASSOC);
        if (is_array($duplicate)) $row = false;
    }
    $bound = is_array($row)
        && in_array(strtolower((string)$row['payment_Status']), ['unpaid','paid'], true)
        && $currency === 'XTR'
        && strtoupper((string)$row['provider_currency']) === 'XTR'
        && is_int($totalAmount)
        && $totalAmount > 0
        && (int)$row['provider_amount'] === $totalAmount;
    if (!$bound) {
        if (function_exists('rx_log_event')) {
            rx_log_event('STARS_PAYMENT_REJECTED', 'Telegram Stars successful_payment binding failed', [
                'order_id'=>$idOrder, 'payer'=>$payer, 'currency'=>$currency,
            ]);
        }
        return;
    }
    try {
        $claim = $pdo->prepare("UPDATE Payment_report SET provider_payment_id=:charge,dec_not_confirmed=:audit WHERE id=:id AND provider_name='telegram_stars' AND provider_invoice_id=:invoice AND (provider_payment_id IS NULL OR provider_payment_id=:same_charge)");
        $claim->execute([
            ':charge'=>$telegramCharge,
            ':same_charge'=>$telegramCharge,
            ':audit'=>json_encode($sp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id'=>(int)$row['id'],
            ':invoice'=>$idOrder,
        ]);
        if ($claim->rowCount() !== 1 && !hash_equals((string)($row['provider_payment_id'] ?? ''), $telegramCharge)) {
            throw new RuntimeException('Telegram charge id already bound');
        }
    } catch (Throwable $e) {
        error_log('[telegram_stars] payment claim failed: ' . redfox_exception_fingerprint($e));
        return;
    }
    $extraLines = ['Telegram charge: '.htmlspecialchars($telegramCharge, ENT_QUOTES, 'UTF-8')];
    if ($providerCharge !== '') $extraLines[] = 'Provider charge: '.htmlspecialchars($providerCharge, ENT_QUOTES, 'UTF-8');
    $confirm = payment_confirm_paid($idOrder, 'chashbackstar', [
        'method'=>'Telegram Stars',
        'expected_method'=>'Star Telegram',
        'thread_id'=>$paymentreports,
        'extra_lines'=>$extraLines,
    ]);
    if (empty($confirm['ok'])) sendmessage($payer,'⚠️ پرداخت ثبت شد اما تکمیل سفارش نیازمند بررسی پشتیبانی است.',null,'HTML');
} elseif (preg_match('/extends_(\w+)_(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $invoiceIdForExtend = $dataget[2] ?? '';
    $nameloc = false;
    if ($invoiceIdForExtend !== '') {
        $nameloc = rxOwnedInvoice($pdo, $invoiceIdForExtend, $from_id);
        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extends on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $invoiceIdForExtend, 'handler' => 'extends',
                ]);
            }
            $nameloc = false;
        }
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $username, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if (function_exists('nmStockPanelForInvoice') && is_array($nameloc)) {
        $marzban_list_get = nmStockPanelForInvoice($nameloc);
    } else {
        $marzban_list_get = false;
    }
    if (!is_array($marzban_list_get)) {
        $panelRef = $user['Processing_value_four'] ?? '';
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $panelRef, "select");
        if (!is_array($marzban_list_get)) {
            $marzban_list_get = select("marzban_panel", "*", "code_panel", $panelRef, "select");
        }
    }
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید", null, 'html');
        return;
    }
    $location = $marzban_list_get['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    if (is_array($nameloc) && !empty($nameloc['id_invoice'])) {
        update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    }

    if (is_array($nameloc) && function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get) && function_exists('nmStockExtendProductKeyboard')) {
        $stockKeyboard = nmStockExtendProductKeyboard($nameloc, $user, $marzban_list_get);
        if (!$stockKeyboard) {
            Editmessagetext($from_id, $message_id, "❌ برای این سرویس موجودی انبار قابل تمدید وجود ندارد.", json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE), 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, "📦 وضعیت نت ملی فعال است؛ محصول انباری تمدید را انتخاب کنید:", $stockKeyboard, 'HTML');
        return;
    }

    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
    $queryParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $customVolumeData = json_decode($marzban_list_get['customvolume'] ?? '{}', true);
    if (!is_array($customVolumeData)) $customVolumeData = [];
    $statuscustomvolume = $customVolumeData[$user['agent']] ?? '0';
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $codeproduct = $dataget[1];
    $username = $dataget[2];
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend preview failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
            ]
        ]
    ]);
    sendmessage($from_id, sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcut['Volume_constraint'], $prodcut['note'], $user['Balance']), $keyboardextend, 'html');
} elseif (preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget)) {
    $codeproduct = $dataget[1];
    $usernamePanelExtends = $dataget[2];
    deletemessage($from_id, $message_id);
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend confirmation failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $nameloc = false;
    if (!empty($user['Processing_value_one'])) {
        $nameloc = rxOwnedInvoice($pdo, $user['Processing_value_one'], $from_id);
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $usernamePanelExtends, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if ($user['Balance'] < $prodcut['price_product'] && $user['agent'] != "n2") {
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
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $prodcut['price_product'] - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    // ── Red Fox: نماینده‌ها اجازه‌ی منفی‌شدن کیف پول ندارند (کف صفر) ──
    // مدل پیشین به نماینده‌ی پیشرفته (n2) اجازه می‌داد تا سقف maxbuyagent بدهی
    // منفی شود. طبق درخواست مالک، اعتبار نمایندگی نباید منفی شود؛ اگر فروش
    // کانفیگ موجودی را زیر صفر ببرد، فروش متوقف و به نماینده (برای شارژ) و
    // به ادمین (اطلاع‌رسانی) پیام داده می‌شود. (نماینده‌ی عادی n از قبل از طریق
    // مسیر پرداخت هدایت می‌شود و منفی نمی‌شود، لذا این گارد فقط روی n2 اثر دارد.)
    if (!function_exists('redfox_notify_reseller_low_balance')) {
        function redfox_notify_reseller_low_balance($from_id, $price, $balance) {
            global $pdo;
            try {
                $chatId = trim((string)$from_id);
                // پیام شارژ به نماینده
                if (function_exists('sendmessage')) {
                    sendmessage($chatId, "⚠️ موجودی کیف پول/اعتبار نمایندگی شما برای این فروش کافی نیست.\n\n💰 مبلغ لازم: " . number_format((int)$price) . " تومان\n💎 موجودی فعلی شما: " . number_format((int)$balance) . " تومان\n\n💳 لطفاً کیف پول خود را شارژ کنید و دوباره تلاش کنید.", null, 'HTML');
                }
                // پیام اطلاع به ادمین‌ها
                $admins = [];
                if ($pdo instanceof PDO) {
                    $st = $pdo->prepare("SELECT id_admin FROM admin WHERE rule = 'administrator'");
                    $st->execute();
                    $admins = $st->fetchAll(PDO::FETCH_COLUMN, 0);
                }
                $txt = "🔁 تلاش ناموفق فروش توسط نماینده\n\n🪪 آیدی نماینده: {$chatId}\n💰 مبلغ فروش: " . number_format((int)$price) . " تومان\n💎 موجودی فعلی: " . number_format((int)$balance) . " تومان\n\n📌 این نماینده موجودی کافی نداشت و فروش متوقف شد؛ به شارژ کیف پول نیاز دارد.";
                foreach ($admins as $aid) {
                    $aid = trim((string)$aid);
                    if ($aid === '' || !ctype_digit($aid)) {
                        continue;
                    }
                    if (function_exists('sendmessage')) {
                        sendmessage($aid, $txt, null, 'HTML');
                    }
                }
            } catch (Throwable $e) {
                error_log('[redfox] notify_reseller_low_balance failed: ' . redfox_exception_fingerprint($e));
            }
        }
    }
    if ($user['agent'] === 'n2') {
        if (((float)$user['Balance'] - (float)$prodcut['price_product']) < 0) {
            redfox_notify_reseller_low_balance($from_id, $prodcut['price_product'], $user['Balance']);
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
        $prodcut['price_product'] = $prodcut['price_product'] - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (!is_array($nameloc)) {
            sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
            return;
        }
        $stockNew = function_exists('nmStockReserveForProduct') ? nmStockReserveForProduct($marzban_list_get, $prodcut, $from_id, $nameloc['id_invoice'], 'normal_extend_national_stock') : false;
        if (!$stockNew) {
            sendmessage($from_id, "❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.", $keyboard, 'HTML');
            return;
        }
        $__pp = (float)$prodcut['price_product'];
        // Red Fox: نماینده‌ها منفی نمی‌شوند (کف صفر) — گارد بالاتر موجودی کافی را تضمین می‌کند.
        $__allowNeg = 0;
        $__charge = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp, $__allowNeg) : ['ok' => false, 'reason' => 'helper-missing'];
        if (empty($__charge['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
            return;
        }
        $Balance_Low_user = $__charge['new_balance'];
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        $invoiceNew = array_merge($nameloc, [
            'name_product' => $prodcut['name_product'],
            'price_product' => $prodcut['price_product'],
            'Volume' => $prodcut['Volume_constraint'],
            'Service_time' => $prodcut['Service_time'],
            'time_sell' => time(),
            'user_info' => $stockNew['content'],
            'source_panel_code' => $marzban_list_get['code_panel'],
        ]);
        nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
        sendmessage($from_id, "✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.", $keyboard, 'HTML');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $__pp2 = (float)$prodcut['price_product'];
    // Red Fox: نماینده‌ها منفی نمی‌شوند (کف صفر) — گارد بالاتر موجودی کافی را تضمین می‌کند.
    $__allowNeg2 = 0;
    $__charge2 = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp2, $__allowNeg2) : ['ok' => false, 'reason' => 'helper-missing'];
    if (empty($__charge2['ok'])) {
        sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
        return;
    }
    $Balance_Low_user = $__charge2['new_balance'];
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel']);
    if (empty($extend['status']) && function_exists('balance_atomic_credit')) {
        balance_atomic_credit($from_id, $__pp2);
        $Balance_Low_user = $user['Balance'];
    }
    if ($extend['status'] == false) {
        $fallbackInvoice = is_array($nameloc) ? $nameloc : ['id_invoice' => '', 'id_user' => $from_id, 'username' => $usernamePanelExtends, 'Service_location' => $marzban_list_get['name_panel'], 'name_product' => $prodcut['name_product'], 'Volume' => $prodcut['Volume_constraint'], 'Service_time' => $prodcut['Service_time']];
        if (nmStockCompleteExtendFallback($from_id, $user, array_merge($fallbackInvoice, ['username' => $usernamePanelExtends]), $prodcut, 0, 'paid_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = redfox_remote_error_summary($extend);
        $textreports = "خطای تمدید سرویس
        نام پنل : {$marzban_list_get['name_panel']}
        نام کاربری سرویس : $usernamePanelExtends
        دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
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
    if (is_array($nameloc)) {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "user_info", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        try { update("invoice", "source_panel_code", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
    ));
    $dateacc = date('Y/m/d H:i:s');
    $type = "extends_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $usernamePanelExtends,
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $prodcut['price_product'],
        ':output' => json_encode(['status' => true], JSON_UNESCAPED_SLASHES)
    ]);
    $prodcut['price_product'] = number_format($prodcut['price_product']);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamePanelExtends
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید {$prodcut['price_product']} تومان
";
    sendmessage($from_id, $textextend, $keyboard, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewaldetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
}
if (in_array($from_id, $admin_ids))
    require_once 'admin.php';

$pdo = null;
$connect->close();