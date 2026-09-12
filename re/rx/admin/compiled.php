<?php
/** Generated from manifest.php at release build time. Do not edit directly. */

/* ---- bootstrap_1.php ---- */
$guardHelperPath = REFACTORED_LEGACY_ROOT . '/guard.php';
if (is_file($guardHelperPath)) {
    require_once $guardHelperPath;
}

$textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
if (isset($datain) && $datain != "" && $text == "" && in_array($from_id, $admin_ids)) {
    $text = $datain;
}
// Red Fox: اگر ادمین متن خوش‌آمدگویی را در پنل ویرایش کرده، از آن استفاده کن
$__rxWOverride = '';
try { $__rxWs = $pdo->query("SELECT admin_welcome_text FROM setting LIMIT 1"); $__rxWOverride = trim((string)$__rxWs->fetchColumn()); } catch(\Throwable $e){}
$text_panel_admin_login_template = ($__rxWOverride !== '') ? $__rxWOverride : "💎 | Version Bot: 2.4.12\n📌 | Version Mini App: 2.4.12\n<blockquote>🔹 | این ربات کاملاً رایگان است — نسخه REDFOX+ Security Hardened</blockquote>\n\n<blockquote>🔹 | هرگونه فروش یا دریافت وجه بابت این ربات تخلف محسوب می‌شود.</blockquote>\n\n<blockquote>🔹 | در صورت مشاهده فروش یا دریافت وجه، لطفاً وجه خود را پیگیری کرده و بازپس‌گیری نمایید.</blockquote>\n\n<blockquote>🐞 | اگر در عملکرد ربات با باگ یا مشکلی مواجه شدید، از طریق گیت هاب یا گروه رد فاکس اطلاع رسانی کنید</blockquote>\n\n<blockquote><a href=\"https://github.com/hojjatrad/RedFox-Security-Hardened\">لینک گیت هاب</a></blockquote>";

if (!function_exists('normalizeXuiSingleSubscriptionBaseUrl')) {

}

if (!function_exists('buildXuiSingleBaseUrl')) {

}

if (!function_exists('hasLikelyXuiSubscriptionId')) {

}

function ensureGuardPanelColumnsReady(PDO $pdo)
{
    $requiredColumns = [
        'api_key' => "VARCHAR(500)",
        'guard_service_ids' => "TEXT",
        'guard_note' => "TEXT",
        'guard_auto_delete_days' => "INT(11)",
        'guard_auto_renewals' => "TEXT",
    ];

    if (function_exists('ensureMarzbanGuardFieldsMigrated')) {
        ensureMarzbanGuardFieldsMigrated();
    } else {
        foreach ($requiredColumns as $column => $datatype) {
            addFieldToTable("marzban_panel", $column, null, $datatype);
        }
    }

    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME IN ($placeholders)"
    );
    $stmt->execute(array_keys($requiredColumns));
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missingColumns = array_diff(array_keys($requiredColumns), $existingColumns);

    return [
        'status' => empty($missingColumns),
        'missing' => array_values($missingColumns),
    ];
}

function guardFormatServiceList(array $services)
{
    if (empty($services)) {
        return "• لیست سرویس خالی است.";
    }
    $lines = [];
    foreach ($services as $service) {
        $serviceData = is_array($service) ? $service : [];
        $id = isset($serviceData['id']) ? intval($serviceData['id']) : 'نامشخص';
        $title = guardServiceLabel($serviceData);
        $usageRate = null;
        foreach (['usage_rate', 'usageRate'] as $rateKey) {
            if (isset($serviceData[$rateKey]) && is_numeric($serviceData[$rateKey])) {
                $usageRate = $serviceData[$rateKey];
                break;
            }
        }
        $rateLabel = $usageRate !== null ? " [{$usageRate}x]" : '';
        $lines[] = "• id={$id} | {$title}{$rateLabel}";
    }
    return implode("\n", $lines);
}

function guardExtractUsageRateValue(array $service)
{
    foreach (['usage_rate', 'usageRate'] as $rateKey) {
        if (isset($service[$rateKey]) && is_numeric($service[$rateKey])) {
            return floatval($service[$rateKey]);
        }
    }
    return null;
}

function guardFormatUsageRateLabel($rate)
{
    $value = is_numeric($rate) ? floatval($rate) : 1.0;
    $precision = (floor($value) == $value) ? 1 : 2;
    $formatted = number_format($value, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    if (strpos($formatted, '.') === false) {
        $formatted .= '.0';
    }
    return $formatted;
}

function syncSuiInboundsWithProxies($panelId)
{
    if (empty($panelId)) {
        return;
    }

    $panelData = select("marzban_panel", "id,type,proxies,inbounds", "id", $panelId, "select");
    if (!is_array($panelData) || ($panelData['type'] ?? '') !== 's_ui') {
        return;
    }

    $proxies = $panelData['proxies'] ?? null;
    if ($proxies === null || $proxies === '') {
        return;
    }

    $inbounds = $panelData['inbounds'] ?? null;
    if ($inbounds === null || $inbounds === '' || $inbounds !== $proxies) {
        update("marzban_panel", "inbounds", $proxies, "id", $panelData['id']);
    }
}

function guardBuildServiceButtonLabel(array $service, $isSelected)
{
    $label = guardServiceLabel($service);
    $rateLabel = guardFormatUsageRateLabel(guardExtractUsageRateValue($service));
    $statusIcon = $isSelected ? '✅' : '❌';
    return "[{$rateLabel}x] {$label} {$statusIcon}";
}

function guardBuildServiceSummaryLabel(array $service)
{
    $label = guardServiceLabel($service);
    $rateLabel = guardFormatUsageRateLabel(guardExtractUsageRateValue($service));
    return "{$label} [{$rateLabel}x]";
}

function guardNormalizeSelectedServiceIds($selectedIds, array $availableIds, $fallbackToAll = false)
{
    $normalized = [];
    $hasAll = false;
    if (is_array($selectedIds)) {
        foreach ($selectedIds as $id) {
            if ($id === 'all' || $id === '0' || $id === 0) {
                $hasAll = true;
                continue;
            }
            if (is_numeric($id)) {
                $normalized[] = intval($id);
            }
        }
    }
    $normalized = array_values(array_intersect(array_unique($normalized), $availableIds));
    if ($hasAll) {
        return $availableIds;
    }
    if ($fallbackToAll && empty($normalized)) {
        return $availableIds;
    }
    return $normalized;
}

function guardBuildServiceSelectionSummary(array $services, array $selectedIds, $selectAll = false)
{
    $availableIds = guardExtractServiceIdsFromList($services);
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    if ($selectAll || (!empty($availableIds) && count($selectedIds) === count($availableIds))) {
        return "همه سرویس‌ها";
    }
    $labels = [];
    foreach ($services as $service) {
        $id = isset($service['id']) ? intval($service['id']) : 0;
        if ($id !== 0 && in_array($id, $selectedIds, true)) {
            $labels[] = guardBuildServiceSummaryLabel($service);
        }
    }
    return !empty($labels) ? implode(' ، ', $labels) : "هیچ سرویسی انتخاب نشده است.";
}

function guardBuildServiceSelectionMessage(array $services, array $selectedIds, $selectAll = false)
{
    $baseLines = [
        "✨ انتخاب سرویس‌های قابل ساخت توسط ربات",
        "لطفاً مشخص کنید ربات مجاز به ساخت کدام سرویس‌ها باشد.",
        "پس از اعمال تغییرات، دکمه «ذخیره و اعمال» را بزنید. برای خروج بدون ثبت از دکمه اختصاصی استفاده کنید.",
        "",
        "📌 توجه:",
        "حداقل یک سرویس باید انتخاب شود.",
    ];
    $allState = $selectAll ? "✅ همه سرویس‌ها فعال است" : "❌ همه سرویس‌ها غیرفعال است";
    $summary = guardBuildServiceSelectionSummary($services, $selectedIds, $selectAll);
    return implode("\n", $baseLines) . "\n\nحالت سرویس‌ها: {$allState}\n\nانتخاب فعلی:\n{$summary}";
}

function guardBuildServiceSelectionKeyboard(array $services, array $selectedIds, $mode, $selectAll = false)
{
    $availableIds = guardExtractServiceIdsFromList($services);
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    $buttons = [];
    foreach ($services as $service) {
        if (!isset($service['id'])) {
            continue;
        }
        $id = intval($service['id']);
        $isSelected = $selectAll || in_array($id, $selectedIds, true);
        $buttons[] = [
            'text' => guardBuildServiceButtonLabel($service, $isSelected),
            'callback_data' => "guardservice:{$mode}:toggle:{$id}",
        ];
    }
    $keyboard = ['inline_keyboard' => []];
    while (count($buttons) > 0) {
        $keyboard['inline_keyboard'][] = array_splice($buttons, 0, 2);
    }
    $keyboard['inline_keyboard'][] = [
        [
            'text' => $selectAll ? "✅ همه سرویس‌ها" : "❌ همه سرویس‌ها",
            'callback_data' => "guardservice:{$mode}:toggle_all",
        ],
    ];
    $keyboard['inline_keyboard'][] = [
        ['text' => "💾 ذخیره و اعمال", 'callback_data' => "guardservice:{$mode}:save"],
    ];
    $keyboard['inline_keyboard'][] = [
        ['text' => "↩️ خروج بدون ذخیره", 'callback_data' => "guardservice:{$mode}:close"],
    ];
    return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
}

function guardEncodeServiceSelectionForStorage(array $selectedIds, array $availableIds, $selectAll = false)
{
    $selectedIds = guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
    $allSelected = $selectAll || (!empty($availableIds) && count(array_diff($availableIds, $selectedIds)) === 0);
    if ($allSelected) {
        return "0";
    }
    return json_encode(array_values(array_unique($selectedIds)));
}

function guardExtractPanelNameFromState(array $state)
{
    foreach (['panel', 'panel_name', 'namepanel'] as $key) {
        if (!empty($state[$key]) && is_string($state[$key])) {
            return trim($state[$key]);
        }
    }
    return !empty($state['guard_service_selection']['panel'])
        ? trim((string) $state['guard_service_selection']['panel'])
        : null;
}

function guardResolveUserPanelName(array $user)
{
    if (!isset($user['Processing_value'])) {
        return null;
    }
    $raw = $user['Processing_value'];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach (['panel', 'panel_name', 'namepanel'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return trim($decoded[$key]);
            }
        }
        if (!empty($decoded['guard_service_selection']['panel'])) {
            return trim((string) $decoded['guard_service_selection']['panel']);
        }
    }

    $trimmed = trim((string) $raw);
    return $trimmed === '' || $trimmed === '0' ? null : $trimmed;
}

function guardRestorePanelSelection($userId, array $state)
{
    $panelName = guardExtractPanelNameFromState($state);
    if (!empty($state['mode']) && $state['mode'] === 'edit' && $panelName !== null) {
        update("user", "Processing_value", $panelName, "id", $userId);
    }
}

function guardFormatAutoRenewalsForSummary(array $entries)
{
    if (empty($entries)) {
        return '0';
    }
    $formatted = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $expireDays = isset($entry['expire_days']) ? intval($entry['expire_days']) : 0;
        $usageGb = isset($entry['usage_gb']) ? floatval($entry['usage_gb']) : 0;
        $usageGbFormatted = rtrim(rtrim(number_format($usageGb, 2, '.', ''), '0'), '.');
        if ($usageGbFormatted === '') {
            $usageGbFormatted = '0';
        }
        $resetUsage = (!empty($entry['reset_usage']) || (!empty($entry['reset']) && $entry['reset'] === true)) ? '1' : '0';
        $formatted[] = "{$expireDays},{$usageGbFormatted},{$resetUsage}";
    }
    return !empty($formatted) ? implode(' | ', $formatted) : '0';
}

function guardExtractSavedGuardSettings(array $panel)
{
    return [
        'note' => $panel['guard_note'] ?? '',
        'auto_delete_days' => max(0, intval($panel['guard_auto_delete_days'] ?? 0)),
        'auto_renewals' => guardDecodeAutoRenewalsConfig($panel['guard_auto_renewals'] ?? []),
    ];
}

function guardBuildGuardSettingsState(array $panel, $messageId = null)
{
    $savedSettings = guardExtractSavedGuardSettings($panel);

    return [
        'panel' => $panel['name_panel'] ?? null,
        'note' => $savedSettings['note'],
        'auto_delete_days' => $savedSettings['auto_delete_days'],
        'auto_renewals' => $savedSettings['auto_renewals'],
        'saved' => $savedSettings,
        'pending_changes' => false,
        'message_id' => $messageId,
    ];
}

function guardPersistGuardSettingsState($fromId, array $state)
{
    update("user", "Processing_value_one", json_encode($state, JSON_UNESCAPED_UNICODE), "id", $fromId);
}

function guardLoadGuardSettingsState(array $user, array $panel, $messageId = null)
{
    $state = [];
    if (isset($user['Processing_value_one'])) {
        $decoded = json_decode($user['Processing_value_one'], true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }
    if (!is_array($state) || empty($state)) {
        $userId = $user['id'] ?? null;
        if ($userId !== null) {
            $freshUser = select("user", "Processing_value_one", "id", $userId, "select");
            if (is_array($freshUser) && isset($freshUser['Processing_value_one'])) {
                $decoded = json_decode($freshUser['Processing_value_one'], true);
                if (is_array($decoded)) {
                    $state = $decoded;
                }
            }
        }
    }

    $panelName = $panel['name_panel'] ?? null;
    $savedFromPanel = guardExtractSavedGuardSettings($panel);
    $hasPendingChanges = !empty($state['pending_changes']);

    if (!is_array($state) || ($state['panel'] ?? null) !== $panelName) {
        $state = guardBuildGuardSettingsState($panel, $messageId);
    } else {
        $state['panel'] = $panelName;
        $state['saved'] = guardExtractSavedGuardSettings($panel);
        $state['message_id'] = $messageId ?? ($state['message_id'] ?? null);

        if ($hasPendingChanges) {
            $state['note'] = array_key_exists('note', $state) ? (string) $state['note'] : $savedFromPanel['note'];
            $state['auto_delete_days'] = max(0, intval($state['auto_delete_days'] ?? $savedFromPanel['auto_delete_days']));
            $state['auto_renewals'] = guardDecodeAutoRenewalsConfig($state['auto_renewals'] ?? $savedFromPanel['auto_renewals']);
            $state['pending_changes'] = true;
        } else {
            $state['note'] = $savedFromPanel['note'];
            $state['auto_delete_days'] = $savedFromPanel['auto_delete_days'];
            $state['auto_renewals'] = $savedFromPanel['auto_renewals'];
            $state['pending_changes'] = false;
        }
    }

    return $state;
}

function guardBuildGuardSettingsMessage(array $state, $includeSavedNotice = false)
{
    $note = trim((string) ($state['note'] ?? ''));
    $safeNote = $note === '' ? '-' : htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $autoDelete = max(0, intval($state['auto_delete_days'] ?? 0));
    $autoRenewals = guardFormatAutoRenewalsForSummary($state['auto_renewals'] ?? []);
    $lines = [
        "🎛️ تنظیمات سرویس",
        "",
        "📝 توضیحات : {$safeNote}",
        "🗑 حذف خودکار : {$autoDelete}",
        "👤 تمدید خودکار : {$autoRenewals}",
        "",
    ];
    if (!empty($state['pending_changes'])) {
        $lines[] = "💾 تغییرات ذخیره نشده است. برای ثبت، دکمه «✅ ذخیره» را بزنید.";
        $lines[] = "";
    }
    if ($includeSavedNotice) {
        $lines[] = "✅ تنظیمات سرویس Guard ذخیره شد.";
        $lines[] = "";
    }
    $lines[] = "برای ویرایش روی هر گزینه بزنید 👇";
    return implode("\n", $lines);
}

function guardBuildGuardSettingsKeyboard()
{
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔖 | ویرایش توضیحات", 'callback_data' => "guardsettings:note"],
            ],
            [
                ['text' => "🗑️ | ویرایش حذف خودکار", 'callback_data' => "guardsettings:auto_delete"],
            ],
            [
                ['text' => "🙎🏻‍♂️ | ویرایش تمدید خودکار کاربر", 'callback_data' => "guardsettings:auto_renew"],
            ],
            [
                ['text' => "✅ ذخیره", 'callback_data' => "guardsettings:save"],
            ],
            [
                ['text' => "❌ | بستن", 'callback_data' => "guardsettings:back"],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function guardRenderGuardSettingsSummary($chatId, array $state, $includeSavedNotice = false)
{
    $keyboard = guardBuildGuardSettingsKeyboard();
    $messageText = guardBuildGuardSettingsMessage($state, $includeSavedNotice);
    $messageId = $state['message_id'] ?? null;
    if ($messageId !== null) {
        deletemessage($chatId, $messageId);
    }
    $response = sendmessage($chatId, $messageText, $keyboard, 'HTML');
    return $response['result']['message_id'] ?? $messageId;
}

function guardRenderGuardSettingsPrompt($chatId, array $state, $promptText)
{
    $keyboard = guardBuildGuardSettingsKeyboard();
    $messageId = $state['message_id'] ?? null;
    if ($messageId !== null) {
        deletemessage($chatId, $messageId);
    }
    $response = sendmessage($chatId, $promptText, $keyboard, 'HTML');
    return $response['result']['message_id'] ?? $messageId;
}

function guardParseServiceSelectionInput($input, array $services)
{
    $input = trim((string) $input);
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        return [
            'status' => false,
            'msg' => 'لیست سرویس خالی است.'
        ];
    }
    if ($input === '') {
        return [
            'status' => false,
            'msg' => 'ورودی خالی است.'
        ];
    }
    $lower = strtolower($input);
    if (in_array($lower, ['0', 'all', 'skip', 'none'], true)) {
        return [
            'status' => true,
            'service_ids' => $availableIds
        ];
    }
    $parts = preg_split('/\s*,\s*/', $input);
    $selected = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (!ctype_digit($part)) {
            return [
                'status' => false,
                'msg' => 'شناسه سرویس نامعتبر است.'
            ];
        }
        $id = intval($part);
        if (!in_array($id, $availableIds, true)) {
            return [
                'status' => false,
                'msg' => "سرویس {$id} در لیست موجود نیست."
            ];
        }
        $selected[] = $id;
    }
    $selected = array_values(array_unique($selected));
    if (empty($selected)) {
        return [
            'status' => false,
            'msg' => 'هیچ سرویس معتبری ارسال نشد.'
        ];
    }

    return [
        'status' => true,
        'service_ids' => $selected
    ];
}

function guardFormatConnectionResult(array $result)
{
    global $textbotlang;
    $statusCode = $result['response']['status'] ?? null;
    if (!empty($result['status'])) {
        $adminName = guardExtractAdminName($result['data'] ?? []);
        $adminLabel = $adminName !== '' ? " ({$adminName})" : '';
        return trim(($textbotlang['Admin']['managepanel']['guard']['connection_ok'] ?? "✅ اتصال برقرار است") . $adminLabel);
    }
    if (in_array($statusCode, [401, 403], true)) {
        return "❌ عدم دسترسی (401/403) → API Key اشتباه";
    }
    $errorMsg = $result['msg'] ?? '';
    $prefix = $textbotlang['Admin']['managepanel']['guard']['connection_error'] ?? "❌ خطای اتصال";
    if ($errorMsg !== '') {
        return "{$prefix} → {$errorMsg}";
    }
    return $prefix;
}

if (!function_exists('sendAdminFinanceMenu')) {
function sendAdminFinanceMenu($chatId, $message = null)
{
    global $textbotlang, $datatextbot;
    $rxIranpayName = function ($key, $fallback) use ($datatextbot) {
        $name = (is_array($datatextbot) && isset($datatextbot[$key])) ? trim((string)$datatextbot[$key]) : '';
        if ($name === '') {
            $row = select("textbot", "text", "id_text", $key, "select");
            $name = is_array($row) ? trim((string)($row['text'] ?? '')) : '';
        }
        return $name !== '' ? ("📌 " . $name) : $fallback;
    };
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != 'onSwapinoBot' && $arzireyali1 != 'offSwapinoBot') {
        update('PaySetting', 'ValuePay', 'onSwapinoBot', 'NamePay', 'statusSwapWallet');
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');

    $statusOn = $textbotlang['Admin']['Status']['statuson'] ?? 'فعال';
    $statusOff = $textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال';
    $cartotcartstatus = $cartotcart === 'oncard' ? $statusOn : $statusOff;
    $plisiostatus = $plisio === 'onnowpayment' ? $statusOn : $statusOff;
    $arzireyali1status = $arzireyali1 === 'onSwapinoBot' ? $statusOn : $statusOff;
    $arzireyali2status = $arzireyali2 === 'onternado' ? $statusOn : $statusOff;
    $aqayepardakhtstatus = $aqayepardakht === 'onaqayepardakht' ? $statusOn : $statusOff;
    $zarinpalstatus = $zarinpal === 'onzarinpal' ? $statusOn : $statusOff;
    $zarinpeystatus = $zarinpey === 'onzarinpey' ? $statusOn : $statusOff;
    $affilnecurrencystatus = $affilnecurrency === 'ondigi' ? $statusOn : $statusOff;
    $arzireyali3text = $arzireyali3 === 'oniranpay3' ? $statusOn : $statusOff;
    $paymentstar = (string)$paymentsstartelegram === '1' ? $statusOn : $statusOff;
    $now_payment_status = (string)$payment_status_nowpayment === '1' ? $statusOn : $statusOff;

    $keyboard = json_encode(['inline_keyboard' => [
        [
            ['text' => 'عملیات', 'callback_data' => 'actions'],
            ['text' => $textbotlang['Admin']['Status']['statussubject'] ?? 'وضعیت', 'callback_data' => 'subjectde'],
            ['text' => $textbotlang['Admin']['Status']['subject'] ?? 'موضوع', 'callback_data' => 'subject'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'cartsetting'],
            ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
            ['text' => '🔌 کارت به کارت', 'callback_data' => 'carttocart'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'plisiosetting'],
            ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
            ['text' => '📌 plisio', 'callback_data' => 'plisio'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'nowpaymentsetting'],
            ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
            ['text' => '📌 nowpayment', 'callback_data' => 'nowpayment'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay1setting'],
            ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
            ['text' => $rxIranpayName('iranpay2', '📌 ارزی ریالی اول'), 'callback_data' => 'arzireyali1'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay2setting'],
            ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
            ['text' => $rxIranpayName('iranpay3', '📌 ارزی ریالی دوم'), 'callback_data' => 'arzireyali2'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'iranpay3setting'],
            ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
            ['text' => $rxIranpayName('iranpay1', '📌ارزی ریالی سوم'), 'callback_data' => 'oniranpay3'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'zarinpeysetting'],
            ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
            ['text' => '🟠 زرین پی', 'callback_data' => 'zarinpey'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'aqayepardakhtsetting'],
            ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
            ['text' => '🔵 آقای پرداخت', 'callback_data' => 'aqayepardakht'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'zarinpalsetting'],
            ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
            ['text' => '🟡 زرین پال', 'callback_data' => 'zarinpal'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'affilnecurrencysetting'],
            ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
            ['text' => '💵ارزی آفلاین', 'callback_data' => 'affilnecurrency'],
        ],
        [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'startelegram'],
            ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
            ['text' => '💫Star Telegram', 'callback_data' => 'none'],
        ],
        [
            ['text' => '⬆️ حداکثر شارژ موجودی', 'callback_data' => 'maxbalanceaccount'],
            ['text' => '⬇️ حداقل شارژ موجودی', 'callback_data' => 'mainbalanceaccount'],
        ],
        [
            ['text' => '💼 آدرس ولت', 'callback_data' => 'walletaddress'],
        ],
        [
            ['text' => '❌ بستن', 'callback_data' => 'close_stat'],
        ],
    ]], JSON_UNESCAPED_UNICODE);

    $text = $message ?: "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.\n\n⚠️ تیم رد فاکس هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد";
    sendmessage($chatId, $text, $keyboard, 'HTML');
}
}

if (!in_array($from_id, $admin_ids))
    return;

$domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$miniAppInstructionText = <<<HTML
📌 آموزش فعالسازی مینی اپ در ربات BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

مراحل بالا را طی کنید سپس آدرس زیر را ارسال نمایید :

<code>https://{$domainhostsEscaped}/app/</code>

➖➖➖➖➖➖➖➖➖➖➖➖
⚙️ تنظیم کرون‌جاب در هاست

فقط <b>یک کرون</b> کافی است — بقیه فرآیندها به‌صورت خودکار از همین کرون اجرا می‌شوند:

<b>⏱ هر ۱ دقیقه یک بار</b>
<code> curl -s https://{$domainhostsEscaped}/cron/cron.php &gt; /dev/null 2&gt;&amp;1</code>
HTML;

// Red Fox: اگر ادمین متن راهنمای نصب را در پنل ویرایش کرده، از آن استفاده کن
$__rxSOverride = '';
try { $__rxSs = $pdo->query("SELECT admin_setup_text FROM setting LIMIT 1"); $__rxSOverride = trim((string)$__rxSs->fetchColumn()); } catch(\Throwable $e){}
if ($__rxSOverride !== '') $miniAppInstructionText = $__rxSOverride;

if (!function_exists('nm_getBroadcastStatus')) {
    function nm_getBroadcastStatus() {
        $infoFile      = 'cronbot/info';
        $usersFileTxt  = 'cronbot/users.txt';
        $usersFileJson = 'cronbot/users.json';
        if (!is_file($infoFile)) {
            return null;
        }
        $infoContent = @file_get_contents($infoFile);
        if ($infoContent === false || $infoContent === '') {
            return null;
        }
        $info = json_decode($infoContent, true);
        if (!is_array($info)) {
            return null;
        }

        $remaining = 0;
        if (is_file($usersFileTxt)) {
            $fh = @fopen($usersFileTxt, 'r');
            if ($fh) {
                while (!feof($fh)) {
                    $chunk = fread($fh, 65536);
                    if ($chunk === false) break;
                    $remaining += substr_count($chunk, "\n");
                }
                fclose($fh);
            }
        } elseif (is_file($usersFileJson)) {
            $raw = @file_get_contents($usersFileJson);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $remaining = count($decoded);
            }
        }
        $stats = isset($info['stats']) && is_array($info['stats']) ? $info['stats'] : [];
        $stats += [
            'total'          => 0,
            'success'        => 0,
            'blocked'        => 0,
            'deleted'        => 0,
            'failed'         => 0,
            'chat_not_found' => 0,
            'started_at'     => 0,
        ];
        $totalSent = (int) $stats['success']
                   + (int) $stats['blocked']
                   + (int) $stats['failed']
                   + (int) $stats['chat_not_found'];
        $total = (int) $stats['total'];
        if ($total <= 0) {

            $total = $totalSent + $remaining;
        }

        if ($remaining === 0 && $totalSent === 0 && $total === 0) {
            return null;
        }
        return [
            'type'           => isset($info['type']) ? (string) $info['type'] : '',
            'total'          => $total,
            'sent'           => $totalSent,
            'remaining'      => $remaining,
            'success'        => (int) $stats['success'],
            'blocked'        => (int) $stats['blocked'],
            'deleted'        => (int) $stats['deleted'],
            'failed'         => (int) $stats['failed'],
            'chat_not_found' => (int) $stats['chat_not_found'],
            'started_at'     => (int) $stats['started_at'],
            'finished'       => ($remaining === 0),
        ];
    }
}
if (!function_exists('nm_buildBroadcastStatusText')) {
    function nm_buildBroadcastStatusText(array $status) {
        $typeMap = [
            'sendmessage'    => 'ارسال همگانی',
            'forwardmessage' => 'فوروارد همگانی',
            'xdaynotmessage' => 'پیام به کاربران غیرفعال',
            'unpinmessage'   => 'لغو پیام پین شده',
        ];
        $typeName  = isset($typeMap[$status['type']]) ? $typeMap[$status['type']] : $status['type'];
        $total     = (int) $status['total'];
        $sent      = (int) $status['sent'];
        $remaining = (int) $status['remaining'];
        $progress  = $total > 0 ? min(100, (int) floor(($sent / $total) * 100)) : 0;

        $cells  = 10;
        $filled = $total > 0 ? (int) floor(($sent / $total) * $cells) : 0;
        $bar    = str_repeat('█', $filled) . str_repeat('░', max(0, $cells - $filled));
        $t  = "⏳ <b>یک عملیات ارسال پیام در حال انجام است</b>\n";
        $t .= "—————————————————\n";
        $t .= "⚙️ نوع عملیات : <b>{$typeName}</b>\n\n";
        $t .= "👥 تعداد کل کاربران : <b>" . number_format($total)     . "</b>\n";
        $t .= "🚀 ارسال‌شده : <b>"        . number_format($sent)      . "</b>\n";
        $t .= "📊 باقی‌مانده در صف : <b>" . number_format($remaining) . "</b>\n\n";
        $t .= "📈 پیشرفت : <b>{$progress}%</b>\n<code>{$bar}</code>\n";
        $details = [];
        if ($status['success']        > 0) $details[] = '✅ موفق: '   . number_format($status['success']);
        if ($status['blocked']        > 0) $details[] = '🚫 بلاک: '    . number_format($status['blocked']);
        if ($status['chat_not_found'] > 0) $details[] = '📵 بدون چت: ' . number_format($status['chat_not_found']);
        if ($status['deleted']        > 0) $details[] = '🗑 حذف‌شده: '  . number_format($status['deleted']);
        if ($status['failed']         > 0) $details[] = '❌ خطا: '     . number_format($status['failed']);
        if (!empty($details)) {
            $t .= "\n📋 جزئیات : " . implode(' | ', $details) . "\n";
        }
        if ($status['started_at'] > 0) {
            $elapsed = max(0, time() - (int) $status['started_at']);
            $t .= "⏱ زمان سپری‌شده : <code>" . gmdate('H:i:s', $elapsed) . "</code>\n";
        }
        $t .= "\n🕒 آخرین بروزرسانی : <code>" . date('H:i:s') . "</code>";
        $t .= "\n💡 برای دیدن آخرین آمار روی «🔄 بروزرسانی» بزنید.";
        return $t;
    }
}
if (!function_exists('nm_buildBroadcastStatusKeyboard')) {
    function nm_buildBroadcastStatusKeyboard() {
        return json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 بروزرسانی",       'callback_data' => 'broadcast_status_refresh']],
                [['text' => "❌ لغو عملیات",       'callback_data' => 'cancel_sendmessage']],
                [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
            ]
        ]);
    }
}

if (!empty($datain) && in_array($from_id, $admin_ids ?? [])) {
    $_rx_adm_cb_map = [

        'admin_status'      => $textbotlang['Admin']['Status']['btn'],
        'admin_managepanel' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'],
        'admin_addpanel'    => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'],
        'admin_timeprice'   => "⏳ تنظیم سریع قیمت زمان",
        'admin_volprice'    => "🔋 تنظیم سریع قیمت حجم",
        'admin_users'       => $textbotlang['Admin']['btnkeyboardadmin']['managruser'],
        'admin_shop'        => "🏬 تنظیمات فروشگاه",
        'admin_finance'     => "💎 مالی",
        'admin_support'     => "🤙 بخش پشتیبانی",
        'admin_help'        => "📚 بخش آموزش",
        'admin_features'    => "🛠 قابلیت های پنل",
        'admin_settings'    => "⚙️ تنظیمات عمومی",
        'admin_invoices'    => "💵 رسید های تایید نشده",
        'admin_back'        => $textbotlang['Admin']['backadmin'],

        'seller_status'     => $textbotlang['Admin']['Status']['btn'],
        'seller_users'      => "👤 مدیریت کاربر",
        'seller_back'       => $textbotlang['users']['backbtn'],
        'support_users'     => "👤 مدیریت کاربر",
        'support_search'    => "👁‍🗨 جستجو کاربر",
        'support_back'      => $textbotlang['users']['backbtn'],

        'set_features'   => "⚙️ وضعیت قابلیت ها",
        'set_reports'    => "📣 گزارشات ربات",
        'set_channel'    => "📯 تنظیمات کانال",
        'set_webpanel'   => "✅ فعالسازی پنل تحت وب",
        'set_optimize'   => "🗑 بهینه سازی ربات",
        'set_text'       => "📝 تنظیم متن ربات",
        'set_adminmgr'   => "👨‍🔧 بخش ادمین",
        'set_testlimit'  => "➕ محدودیت ساخت اکانت تست برای همه",
        'set_agentprice' => "💰 مبلغ عضویت نمایندگی",
        'set_qrbg'       => "🖼 پس زمینه کیوآرکد",
        'set_webhook'    => "🔗 وبهوک مجدد ربات های نماینده",
        'set_backadmin'  => $textbotlang['Admin']['backadmin'],
        'set_backmenu'   => $textbotlang['Admin']['backmenu'],

        'shop_status'      => "🛒 وضعیت قابلیت های فروشگاه",
        'shop_category'    => "🗂 مدیریت دسته بندی",
        'shop_products'    => "🛍 مدیریت محصولات",
        'shop_giftadd'     => "🎁 ساخت کد هدیه",
        'shop_giftdel'     => "❌ حذف کد هدیه",
        'shop_discountadd' => "🎁 ساخت کد تخفیف",
        'shop_discountdel' => "❌ حذف کد تخفیف",
        'shop_minbulk'     => "⬇️ حداقل موجودی خرید عمده",
        'shop_renewcb'     => "🎁 کش بک تمدید",
        'shop_backadmin'   => $textbotlang['Admin']['backadmin'],
        'shop_backmenu'    => $textbotlang['Admin']['backmenu'],

        'cart_title'       => "🗂 نام درگاه کارت به کارت",
        'cart_setnum'      => "💳 تنظیم شماره کارت",
        'cart_delnum'      => "❌ حذف شماره کارت",
        'cart_support'     => "👤 آیدی پشتیبانی",
        'cart_pvmode'      => "💳 درگاه آفلاین در پیوی",
        'cart_autoconfirm' => "♻️ تایید خودکار رسید",
        'cart_cashback'    => "💰 کش بک کارت به کارت",
        'cart_firstpay'    => "🔒 نمایش کارت به کارت پس از اولین پرداخت",
        'cart_min'         => "⬇️ حداقل مبلغ کارت به کارت",
        'cart_max'         => "⬆️ حداکثر مبلغ کارت به کارت",
        'cart_edu'         => "📚 تنظیم آموزش کارت به کارت",
        'cart_hide_num'    => "💰  غیرفعالسازی  نمایش شماره کارت",
        'cart_show_num'    => "💰 فعالسازی نمایش شماره کارت",
        'cart_group_num'   => "♻️ نمایش گروهی شماره کارت",
        'cart_export_num'  => "📄 خروجی افراد شماره کارت فعال",
        'cart_autocheck'   => "🤖 تایید رسید  بدون بررسی",
        'cart_except_user' => "💳 استثناء کردن کاربر از تایید خودکار",
        'cart_autotime'    => "⏳ زمان تایید خودکار بدون بررسی",
        'cart_back'        => $textbotlang['Admin']['backadmin'],
        'cart_backmenu'    => $textbotlang['Admin']['backmenu'],
        'adm_backmenu'     => $textbotlang['Admin']['backmenu'],

        'trnado_name'     => "🏷️ نام نمایشی درگاه ترنادو",
        'trnado_apikey'   => "🔑 ثبت API Key ترنادو",
        'trnado_wallet'   => "💼 ثبت آدرس ولت ترون (TRC20)",
        'trnado_apiurl'   => "🌐 ثبت آدرس API ترنادو",
        'trnado_cashback' => "💰 کش بک ارزی ریالی دوم",
        'trnado_min'      => "⬇️ حداقل مبلغ ارزی ریالی دوم",
        'trnado_max'      => "⬆️ حداکثر مبلغ ارزی ریالی دوم",
        'trnado_edu'      => "📚 تنظیم آموزش ارزی ریالی  دوم",
        'trnado_back'     => $textbotlang['Admin']['backadmin'],
        'trnado_backmenu' => $textbotlang['Admin']['backmenu'],

        'zpal_name'     => "🗂 نام درگاه زرین پال",
        'zpal_merchant' => "مرچنت زرین پال",
        'zpal_cashback' => "💰 کش بک زرین پال",
        'zpal_min'      => "⬇️ حداقل مبلغ زرین پال",
        'zpal_max'      => "⬆️ حداکثر مبلغ زرین پال",
        'zpal_edu'      => "📚 تنظیم آموزش زرین پال",
        'zpal_back'     => $textbotlang['Admin']['backadmin'],
        'zpal_backmenu' => $textbotlang['Admin']['backmenu'],

        'zpey_name'     => "🗂 نام درگاه زرین پی",
        'zpey_token'    => "🔑 توکن زرین پی",
        'zpey_cashback' => "💰 کش بک زرین پی",
        'zpey_tutorial' => "🧑🏼‍💻 اموزش اتصال",
        'zpey_min'      => "⬇️ حداقل مبلغ زرین پی",
        'zpey_max'      => "⬆️ حداکثر مبلغ زرین پی",
        'zpey_edu'      => "📚 تنظیم آموزش زرین پی",
        'zpey_back'     => $textbotlang['Admin']['backadmin'],
        'zpey_backmenu' => $textbotlang['Admin']['backmenu'],

        'aqaye_name'     => "🗂 نام درگاه آقای پرداخت",
        'aqaye_merchant' => "تنظیم مرچنت آقای پرداخت",
        'aqaye_cashback' => "💰 کش بک آقای پرداخت",
        'aqaye_min'      => "⬇️ حداقل مبلغ آقای پرداخت",
        'aqaye_max'      => "⬆️ حداکثر مبلغ آقای پرداخت",
        'aqaye_edu'      => "📚 تنظیم آموزش درگاه اقای پرداخت",
        'aqaye_back'     => $textbotlang['Admin']['backadmin'],
        'aqaye_backmenu' => $textbotlang['Admin']['backmenu'],

        'plisio_name'     => "🗂 نام درگاه   plisio",
        'plisio_api'      => "🧩 api plisio",
        'plisio_cashback' => "💰 کش بک plisio",
        'plisio_min'      => "⬇️ حداقل مبلغ plisio",
        'plisio_max'      => "⬆️ حداکثر مبلغ plisio",
        'plisio_edu'      => "📚 تنظیم آموزش plisio",
        'plisio_back'     => $textbotlang['Admin']['backadmin'],
        'plisio_backmenu' => $textbotlang['Admin']['backmenu'],

        'help_add'      => "📚 اضافه کردن آموزش",
        'help_del'      => "❌ حذف آموزش",
        'help_edit'     => "✏️ ویرایش آموزش",
        'help_back'     => $textbotlang['Admin']['backadmin'],
        'help_backmenu' => $textbotlang['Admin']['backmenu'],

        'cat_add'  => "🛒 اضافه کردن دسته بندی",
        'cat_del'  => "❌ حذف دسته بندی",
        'cat_edit' => "✏️ ویرایش دسته بندی",
        'cat_back' => "⬅️ بازگشت به منوی فروشگاه",

        'shopitem_add'      => "🛍 اضافه کردن محصول",
        'shopitem_del'      => "❌ حذف محصول",
        'shopitem_edit'     => "✏️ ویرایش محصول",
        'shopitem_priceinc' => "⬆️ افزایش گروهی قیمت",
        'shopitem_pricedec' => "⬇️ کاهش  گروهی قیمت",
        'shopitem_back'     => "⬅️ بازگشت به منوی فروشگاه",

        'feat_info'     => "قابلیت مشاهده اطلاعات اکانت",
        'feat_test'     => "قابلیت اکانت تست",
        'feat_help'     => "قابلیت آموزش",
        'feat_back'     => $textbotlang['Admin']['backadmin'],
        'feat_backmenu' => $textbotlang['Admin']['backmenu'],

        'ch_add'      => "اضافه کردن کانال",
        'ch_del'      => "حذف کانال",
        'ch_back'     => $textbotlang['Admin']['backadmin'],
        'ch_backmenu' => $textbotlang['Admin']['backmenu'],
    ];
    if (isset($_rx_adm_cb_map[$datain])) {
        $text = $_rx_adm_cb_map[$datain];
    }
    unset($_rx_adm_cb_map);
}
/* ---- bootstrap_2.php ---- */
if (!function_exists('rx_featCategoryRows')) {
    function rx_featCategoryRows($cat)
    {
        global $textbotlang, $setting, $status_cron,
            $name_status, $name_status_role, $Authenticationphone, $Authenticationiran,
            $statusverify, $statusverifybyuser, $authScopeBtn, $authScopeVal, $statusinline,
            $name_status_username, $name_status_notifnewuser, $name_status_showagent,
            $statuspvsupport, $statusnameconfig, $statusnotef,
            $statusnamebulk, $btnstatuscategory, $keyboard_config_text, $status_copy_cart,
            $statusDebtsettlement, $statuslimitchangeloc, $infocardColorEmoji, $infocardStatusText,
            $infocardStatusValue, $btnstatuslinkapp,
            $wheel_luck, $statusfirstwheel, $wheelagent, $score, $Lotteryagent, $refralstatus, $statusDice,
            $cronteststatustext, $cronuptime_nodestatustext, $cronuptime_panelstatustext,
            $crondaystatustext, $cronon_holdtext, $cronvolumestatustext,
            $cronremovestatustext, $cronremovevolumestatustext;

        if ($cat === 'bot') {
            return [
                [['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                 ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"]],
                [['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"]],
                [['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"]],
                [['text' => $Authenticationphone, 'callback_data' => "editstsuts-get_number-{$setting['get_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"]],
                [['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"]],
                [['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                 ['text' => "🔒 احراز هویت", 'callback_data' => "verify"]],
                [['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                 ['text' => "🔑 احراز هویت با لینک", 'callback_data' => "verifybyuser"]],
                [['text' => $authScopeBtn, 'callback_data' => "editstsuts-authscope-{$authScopeVal}"]],
                [['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
                 ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"]],
            ];
        } elseif ($cat === 'users') {
            return [
                [['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"]],
                [['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"]],
                [['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"]],
                [['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                 ['text' => "👤 پشتیبانی در پیوی", 'callback_data' => "statussupportpv"]],
                [['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                 ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"]],
                [['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                 ['text' => "📨 یادداشت کاربر عادی", 'callback_data' => "statusnamecustomf"]],
            ];
        } elseif ($cat === 'shop') {
            return [
                [['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                 ['text' => "🛍 وضعیت خرید عمده", 'callback_data' => "bulkbuy"]],
                [['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                 ['text' => "📗 دسته بندی آموزش", 'callback_data' => "btn_status_category"]],
                [['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                 ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"]],
                [['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                 ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"]],
                [['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                 ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
                 ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                 ['text' => "🌍 محدودیت تغییر لوکیشن", 'callback_data' => "changeloc"]],
                [['text' => "{$infocardColorEmoji} انتخاب رنگ", 'callback_data' => "infocard_color_menu"],
                 ['text' => $infocardStatusText, 'callback_data' => "editstsuts-infocard-{$infocardStatusValue}"],
                 ['text' => "📊 کارت مشخصات سرویس", 'callback_data' => "infocard_status"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "linkappsetting"],
                 ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
                 ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappstatus"]],
            ];
        } elseif ($cat === 'lottery') {
            return [
                [['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
                 ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                 ['text' => "🎲 گردونه شانس", 'callback_data' => "wheel_luck"]],
                [['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                 ['text' => "🎲 گردونه شانس خرید اول", 'callback_data' => "wheelagentfirst"]],
                [['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                 ['text' => "🎲 گردونه شانس نمایندگان", 'callback_data' => "wheelagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
                 ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                 ['text' => "🎁 قرعه کشی شبانه", 'callback_data' => "score"]],
                [['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                 ['text' => "🎁 قرعه کشی نمایندگان", 'callback_data' => "Lotteryagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
                 ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                 ['text' => "🎁 زیرمجموعه", 'callback_data' => "affiliatesstatus"]],
                [['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                 ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"]],
            ];
        } elseif ($cat === 'crons') {
            return [
                [['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                 ['text' => "🔓 کرون تست", 'callback_data' => "none"]],
                [['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                 ['text' => "🎛 آپتایم نود", 'callback_data' => "none"]],
                [['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                 ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
                 ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                 ['text' => "🕚 کرون زمان", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان اولین اتصال", 'callback_data' => "setting_on_holdcron"],
                 ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                 ['text' => "🕚 کرون اولین اتصال", 'callback_data' => "none"]],
                [['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
                 ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                 ['text' => "🔋 کرون حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
                 ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                 ['text' => "❌ کرون حذف", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
                 ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                 ['text' => "❌ کرون حذف حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
                 ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
                 ['text' => "زمان‌بندی کرون‌ها", 'callback_data' => "none"]],
            ];
        }
        return [];
    }
}

if (in_array($text, $textadmin) || $datain == "admin") {
    if ($datain == "admin")
        deletemessage($from_id, $message_id);
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    activecron();
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
    if ($miniAppInstructionHidden !== '1') {
        $miniAppInstructionKeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'دیگر نمایش نده ⛓️‍💥', 'callback_data' => 'hide_mini_app_instruction'],
                ],
            ],
        ]);
        nm_adminInstantReply($from_id, $miniAppInstructionText, $miniAppInstructionKeyboard, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['backadmin']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    if (function_exists('nmResolvePanelNameForUser')) {
        $rawProcessing = (string)($user['Processing_value'] ?? '');
        if ($rawProcessing !== '' && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
            $resolvedName = nmResolvePanelNameForUser($user);
            if ($resolvedName !== '') {
                update("user", "Processing_value", $resolvedName, "id", $from_id);
            }
        }
        unset($rawProcessing, $resolvedName);
    }
    $version_mini_app = file_get_contents('app/version');
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "hide_mini_app_instruction") {
    if (!in_array($from_id, $admin_ids))
        return;
    if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
        update("user", "hide_mini_app_instruction", "1", "id", $from_id);
        $user['hide_mini_app_instruction'] = '1';
    }
    $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
    $confirmationText = $miniAppInstructionText . "\n\n✅ این پیام دیگر برای شما نمایش داده نخواهد شد.";
    Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
    return;
} elseif ($text == "🔙 بازگشت به انبار" && in_array($from_id, $admin_ids)) {
    step('home', $from_id);
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $keyboardadmin;
    nm_adminInstantReply($from_id, "📦 بازگشت به منوی انبارداری", $stockKb, 'HTML');
    return;
} elseif (($text == $textbotlang['Admin']['backmenu']) || ((isset($datain) ? (string)$datain : '') === 'backmenu')) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $setting_panel, 'HTML');
        return;
    }
    $currentStep = isset($user['step']) ? (string) $user['step'] : '';
    step('home', $from_id);

    if (in_array($currentStep, ['premium_emoji_get_char', 'premium_emoji_get_id', 'premium_emoji_edit_id'], true)) {
        if (function_exists('rxRenderPremiumEmojiPanel')) {
            rxRenderPremiumEmojiPanel($from_id, 1);
        }
        return;
    }
    if (in_array($currentStep, ["updatetime", "val_usertest", "getlimitnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
        $panelNameBack = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
        if ($panelNameBack !== '') {
            update("user", "Processing_value", $panelNameBack, "id", $from_id);
        }
        $typepanel = select("marzban_panel", "*", "name_panel", $panelNameBack !== '' ? $panelNameBack : $user['Processing_value'], "select");
        outtypepanel(is_array($typepanel) ? $typepanel['type'] : '', $textbotlang['Admin']['Back-menu']);
    } else {
        $financialStepKeyboardMap = [
            'apiternado' => $trnado,
            'changecard' => $CartManage,
            'getnamecard' => $CartManage,
            'getcardremove' => $CartManage,
            'getnamecarttocart' => $CartManage,
            'getnamenowpayment' => $nowpayment_setting_keyboard,
            'getnamecarttopaynotverify' => $CartManage,
            'gettextnowpayment' => $NowPaymentsManage,
            'gettextnowpaymentTRON' => $tronnowpayments,
            'gettextiranpay2' => $Swapinokey,
            'gettextstartelegram' => $Swapinokey,
            'gettextiranpay3' => $trnado,
            'gettextiranpay1' => $iranpaykeyboard,
            'gettextaqayepardakht' => $aqayepardakht,
            'gettextzarinpal' => $keyboardzarinpal,
            'gettextzarinpey' => $keyboardzarinpey,
            'token_zarinpey' => $keyboardzarinpey,
            'merchant_id_aqayepardakht' => $aqayepardakht,
            'merchant_zarinpal' => $keyboardzarinpal,
            'apinowpayment' => $NowPaymentsManage,
            'nowpayment_ipn_secret' => $nowpayment_setting_keyboard,
            'marchent_tronseller' => $nowpayment_setting_keyboard,
            'getcashcart' => $CartManage,
            'getcashahaypar' => $CartManage,
            'getcashiranpay2' => $trnado,
            'getcashiranpay4' => $CartManage,
            'getcashiranpay1' => $Swapinokey,
            'getcashplisio' => $CartManage,
            'getcashnowpayment' => $nowpayment_setting_keyboard,
            'getcashzarinpal' => $keyboardzarinpal,
            'getcashzarinpey' => $keyboardzarinpey,
            'getmaincart' => $CartManage,
            'getmaxcart' => $CartManage,
            'getmainplisio' => $NowPaymentsManage,
            'getmaxplisio' => $NowPaymentsManage,
            'getmaindigitaltron' => $tronnowpayments,
            'getmaxdigitaltron' => $tronnowpayments,
            'getmainiranpay1' => $Swapinokey,
            'getmaaxiranpay1' => $Swapinokey,
            'getmainiranpay2' => $trnado,
            'getmaaxiranpay2' => $Swapinokey,
            'getmainaqayepardakht' => $aqayepardakht,
            'getmaaxaqayepardakht' => $aqayepardakht,
            'getmainaqzarinpal' => $aqayepardakht,
            'getmaaxzarinpal' => $aqayepardakht,
            'getmainzarinpey' => $keyboardzarinpey,
            'getmaaxzarinpey' => $keyboardzarinpey,
            'helpzarinpey' => $keyboardzarinpey,
            'gethelpcart' => $CartManage,
            'gethelpnowpayment' => $nowpayment_setting_keyboard,
            'gethelpperfect' => $CartManage,
            'gethelpplisio' => $CartManage,
            'gethelpiranpay1' => $CartManage,
            'getmainaqstar' => $Startelegram,
            'maxbalancestar' => $Startelegram,
            'getmainaqnowpayment' => $nowpayment_setting_keyboard,
            'maxbalancenowpayment' => $nowpayment_setting_keyboard,
            'gethelpstar' => $Startelegram,
            'chashbackstar' => $Startelegram,
        ];

        $productStepKeyboardMap = [
            'change_price' => $change_product,
            'change_note' => $change_product,
            'change_categroy' => $change_product,
            'change_name' => $change_product,
            'change_type_agent' => $change_product,
            'change_reset_data' => $change_product,
            'change_loc_data' => $change_product,
            'getlistpanel' => $change_product,
            'change_val' => $change_product,
            'change_time' => $change_product,
        ];

        if (in_array($currentStep, ['admin_nav_cart_settings'], true)) {
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        if (in_array($currentStep, ['admin_nav_cron_settings', 'admin_nav_cron_jobs'], true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            step('admin_nav_cron_settings', $from_id);
            return;
        }

        if ($currentStep === 'cronjob_set_value') {
            if (function_exists('buildCronJobsKeyboard')) {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], buildCronJobsKeyboard(), 'HTML');
                step('admin_nav_cron_jobs', $from_id);
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                step('admin_nav_cron_settings', $from_id);
            }
            return;
        }

        if ($currentStep === 'walletaddresssiranpay') {
            $processingData = [];
            if (isset($user['Processing_value'])) {
                $decodedProcessing = json_decode($user['Processing_value'], true);
                if (is_array($decodedProcessing)) {
                    $processingData = $decodedProcessing;
                }
            }
            $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
            $keyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboard, 'HTML');
            return;
        }

        if (isset($financialStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $financialStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        if (isset($productStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $productStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        $financeSteps = [
            'marchent_tronseller', 'marchent_floypay', 'urlpaymenttron', 'cryptowallet_set',
            'admin_nav_finance', 'maxbalance', 'minbalance', 'CartDirect', 'showcardallusers',
            'apiiranpay', 'getnameconfigm',
        ];
        if (in_array($currentStep, $financeSteps, true)) {
            step('home', $from_id);
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        $financeSpecificMap = [
            'getmainiranpay3' => $trnado,    'getmaaxiranpay3' => $trnado,
            'gethelpiranpay3' => $trnado,    'getcashiranpay3' => $trnado,
            'getcashiranpay2'  => $trnado,   'getcashiranpay4'  => $Swapinokey,
            'getmainiranpay2'  => $trnado,   'getmaaxiranpay2'  => $Swapinokey,
            'getmainiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'getmaaxiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'gethelpiranpay2'  => $Swapinokey,
            'getagentbalancemax' => $shopkeyboard,
            'getagentbalancemin' => $shopkeyboard,
            'getmaindigitaltron2' => $tronnowpayments,
            'getmaxdigitaltron2'  => $tronnowpayments,
        ];
        if (isset($financeSpecificMap[$currentStep])) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $financeSpecificMap[$currentStep], 'HTML');
            return;
        }

        $discountSteps = [
            'getdiscont', 'getfirstdiscount', 'getlimitcode', 'getlimitcodedis',
            'getlocdiscount', 'getproductdiscount', 'gettimediscount', 'gettypeagentoflist',
            'gettypecodeagent', 'getuseuser', 'getmaxbuyagent', 'getpercentuser',
            'setpercentage', 'remove-Discount', 'remove-Discountsell', 'get_price_code',
            'get_price_codesell', 'get_price_Negative', 'Negative_Balance',
            'getlimitedpanel', 'getagent', 'getpricecashback', 'stependforaddorder',
            'getnameproduct', 'add_Balance_all', 'setbanner', 'show_info', 'reject-dec',
            'remove-product', 'get_number_limit', 'GetLocationEdit', 'PanelMenu',
        ];
        if (in_array($currentStep, $discountSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        }

        $textEditSteps = [
            'text_Add_Balance', 'text_Discount', 'text_Tariff_list', 'text_affiliates',
            'text_afterpaytext', 'text_afterpaytextibsng', 'text_aftertesttext',
            'text_cart', 'text_cart_auto', 'text_channel', 'text_crontest',
            'text_dec_Tariff_list', 'text_dec_fq', 'text_extend', 'text_fq',
            'text_help', 'text_pishinvoice', 'text_request_agent_dec', 'text_roll',
            'text_sell', 'text_support', 'text_textmanual', 'text_wgdashboard',
            'text_wheel_luck', 'textpanelagent', 'textrequestagent', 'textselectlocation',
            'changetextinfo', 'changetextstart', 'changetextusertest',
        ];
        if (in_array($currentStep, $textEditSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $textbot, 'HTML');
            return;
        }

        $helpSteps = [
            'changecategoryhelp', 'changedeshelp', 'changemedia', 'changenamehelp',
            'add_name_help', 'getcatgoryhelp', 'remove_help', 'getconfigtext',
            'getservceid',
        ];
        if (in_array($currentStep, $helpSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardhelpadmin, 'HTML');
            return;
        }

        $settingsSteps = [
            'getnamepanelconfig', 'getusernameconfig', 'addchannelid',
            'idsupportset', 'limit_usertest_allusers', 'get_codesell',
        ];
        if (in_array($currentStep, $settingsSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            return;
        }

        $userSteps = [
            'accountwallet', 'add_dec', 'addbalancemanual', 'addbalanceuser',
            'addbalanceusercurrent', 'adddecriptionblock', 'getuserhide',
            'getuserhideforremove', 'addadmin', 'getrule', 'GetusernameconfigAndOrdedrs',
            'antispam_get_count', 'antispam_get_mute', 'antispam_get_seconds',
            'getbtnresponseforward', 'getmessageAsAdmin', 'getmessageforward',
            'sendmessagetext', 'sendmessagetid', 'getcountcreate', 'getagentpanel',
            'getinboundiid', 'getuuidadmin', 'getprotocoldisable', 'getprotocolx_ui',
            'getvolumesconfig', 'getlocoption', 'GeturlNewx', 'add_link_panel',
            'add_password_panel', 'add_username_panel', 'confirmremovepanel',
            'getInbounddisable', 'getusernameconfigcr', 'removeprotocol',
            'GetPriceExtratime', 'GetPricecustomvo', 'GetPricetimeextra',
            'GetmaineExtra', 'Getmaintime', 'GetmaxeExtra', 'Getmaxtime',
        ];
        if (in_array($currentStep, $userSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            return;
        }

        $guardSteps = [
            'guard_edit_api_key', 'guard_service_selection_edit', 'guard_service_selection_new',
            'guard_settings_auto_delete', 'guard_settings_auto_renew',
            'guard_settings_note', 'guard_settings_summary', 'add_guard_api_key',
        ];
        if (in_array($currentStep, $guardSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            return;
        }

        $nmSteps = [
            'nm_delete_stock_action', 'nm_delete_stock_select_shelf', 'nm_edit_shelf_select',
            'nm_select_emergency_panel', 'nm_shelf_category', 'nm_shelf_name',
            'nm_shelf_panel', 'nm_shelf_product', 'nm_stock_ask_has_sub',
            'nm_stock_bulk_import', 'nm_stock_paired_cfg', 'nm_stock_paired_sub',
            'nm_stock_select_shelf_import',
            'nm_stock_delete_by_id', 'nm_stock_delete_confirm',
            'nm_delete_shelf_select', 'nm_delete_shelf_confirm',
        ];
        if (in_array($currentStep, $nmSteps, true)) {
            $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $shopkeyboard;
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $stockKb, 'HTML');
            return;
        }

        $shopSteps = [
            "selectloc",
            "get_limit",
            "selectlocedite",
            "GetPriceExtra",
            "GetPriceexstratime",
            "GetPricecustomtime",
            "GetPricecustomvolume",
            "get_code",
            "get_codesell",
            "minbalancebulk",
            "get_agent",
            "get_location",
            "getcategory",
            "get_time",
            "get_price",
            "gettimereset",
            "getnote",
            "endstep",
            "gettypeextra",
            "gettypeextracustom",
            "gettypeextratime",
            "gettypeextratimecustom",
            "gettypeextramain",
            "gettypeextramax",
            "gettypeextramaintime",
            "gettypeextramaxtime",
        ];

        if (in_array($currentStep, $shopSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        } elseif (in_array($currentStep, ["addchannel", "removechannel"])) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
        }
    }
    return;
} elseif (($text == $textbotlang['Admin']['channel']['title'] || $text == "➕ اضافه کردن کانال" || (isset($datain) && $datain == 'ch_add')) && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $backadmin, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel") {
    savedata("clear", "link", $text);
    nm_adminInstantReply($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $backadmin, 'HTML');
    step('getremark', $from_id);
} elseif ($user['step'] == "getremark") {
    savedata("save", "remark", $text);
    nm_adminInstantReply($from_id, "📌 لینک عضویت را ارسال کنید", $backadmin, 'HTML');
    step('getlinkjoin', $from_id);
} elseif ($user['step'] == "getlinkjoin") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, "آدرس عضویت صحیح نمی باشد", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }

    $remark = isset($userdata['remark']) ? (string) $userdata['remark'] : '';
    $link = isset($userdata['link']) ? (string) $userdata['link'] : '';

    nm_adminInstantReply($from_id, "✅ کانال جوین اجباری با موفقیت ثبت گردید.", $channelkeyboard, 'HTML');
    step('home', $from_id);

    $insertChannel = function ($remarkValue) use ($pdo, $link, $text) {
        $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
        $stmt->bindValue(':remark', $remarkValue, PDO::PARAM_STR);
        $stmt->bindValue(':link', $link, PDO::PARAM_STR);
        $stmt->bindValue(':linkjoin', $text, PDO::PARAM_STR);
        $stmt->execute();
    };

    try {
        $insertChannel($remark);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1366) {
            ensureTableUtf8mb4('channels');
            try {
                $insertChannel($remark);
            } catch (PDOException $retryException) {
                if ((int)($retryException->errorInfo[1] ?? 0) !== 1366) {
                    throw $retryException;
                }

                $sanitisedRemark = is_string($remark) ? @iconv('UTF-8', 'UTF-8//IGNORE', $remark) : '';
                if ($sanitisedRemark === false) {
                    $sanitisedRemark = '';
                }
                $insertChannel($sanitisedRemark);
            }
        } else {
            throw $e;
        }
    }
} elseif (($text == $textbotlang['Admin']['channel']['removechannelbtn'] || $text == "❌ حذف کانال" || (isset($datain) && $datain == 'ch_del')) && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removechannel'], $list_channels_joins, 'HTML');
    step('removechannel', $from_id);
} elseif ($user['step'] == "removechannel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM channels WHERE link = :link");
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    $adminId = trim($text);
    if ($adminId === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $adminId, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
    step('getrule', $from_id);
} elseif ($user['step'] == "getrule") {
    $rule = ['administrator', 'Seller', 'support'];
    if (!in_array($text, $rule)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $usernamepanel = "root";
    $randomString = bin2hex(random_bytes(10));
    $passwordHash = password_hash($randomString, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, rule) VALUES (:id_admin, :username, :password, :rule)");
    $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
    $stmt->bindParam(':password', $passwordHash, PDO::PARAM_STR);
    $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($user['Processing_value'], "🔐 اطلاعات ورود پنل مدیریت\n👤 نام کاربری: <code>{$usernamepanel}</code>\n🔑 رمز موقت: <code>{$randomString}</code>\nپس از ورود رمز را تغییر دهید.", null, 'HTML');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
    $id_user_set = $text;
    step('home', $from_id);
    update("user", "limit_usertest", $text, "id", $user['Processing_value']);
} elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
    step('home', $from_id);
    update("user", "limit_usertest", $text);
    update("setting", "limit_usertest_all", $text);
} elseif ($text == "📯 تنظیمات کانال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['description'], $channelkeyboard, 'HTML');
} elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
    $Balanceall = select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
    $statistics = select("user", "*", null, null, "count");
    $sumpanel = select("marzban_panel", "*", null, null, "count");
    $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
    $stmt1 = $pdo->query($sql1);
    $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
    $agentsumn = select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
    $agentsumn2 = select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt1 = $pdo->query($sql1);
    $invoiceactive = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sqlall = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE status != 'Unpaid' AND name_product != 'سرویس تست'";
    $sqlall = $pdo->query($sqlall);
    $invoice = $sqlall->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = $stmt2->fetch(PDO::FETCH_ASSOC)['total_price'];
    $sql33 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE status!= 'Unpaid' AND name_product != 'سرویس تست'";
    $sql33 = $pdo->query($sql33);
    $invoiceSumRow = $sql33->fetch(PDO::FETCH_ASSOC);
    $invoiceTotal = isset($invoiceSumRow['total_price']) ? (float) $invoiceSumRow['total_price'] : 0;
    $invoicesumall = number_format($invoiceTotal, 0);
    $sql3 = "SELECT SUM(price) AS total_extend FROM service_other WHERE type = 'extend_user'";
    $stmt3 = $pdo->query($sql3);
    $extendSumRow = $stmt3->fetch(PDO::FETCH_ASSOC);
    $extendsum = isset($extendSumRow['total_extend']) ? (float) $extendSumRow['total_extend'] : 0;
    $count_usertest = select("invoice", "*", "name_product", "سرویس تست", "count");
    $timeacc = jdate('H:i:s', time());
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as count FROM `invoice` WHERE Status != 'Unpaid'");
    $stmt2->execute();
    $statisticsorder = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $sqlsum = "SELECT SUM(price) AS sumpay , Payment_Method,COUNT(price) AS countpay FROM Payment_report WHERE payment_Status = 'paid' AND Payment_Method NOT IN ('add balance by admin','low balance by admin') GROUP BY  Payment_Method;";
    $stmt = $pdo->prepare($sqlsum);
    $stmt->execute();
    $statispay = $stmt->fetchAll();
    $date = date("Y-m-d");
    $timeacc = jdate('H:i:s', time());
    $start_time = date('d.m.Y', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('d.m.Y', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT SUM(price_product) FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR Status = 'send_on_hold' OR Status = 'sendedwarn') AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $suminvoiceday = $stmt->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'];
    $invoicesum = (float) ($invoicesum ?? 0);
    $extendsum = (float) ($extendsum ?? 0);
    $suminvoiceday = (float) ($suminvoiceday ?? 0);
    $statistics = (int) ($statistics ?? 0);
    $statisticsorder = (int) ($statisticsorder ?? 0);
    $paycount = "";
    $ratecustomer = round(safe_divide($statisticsorder * 100, $statistics, 0), 2);
    $averagePurchase = safe_divide($invoicesum, $statisticsorder, 0);
    $avgbuy_customer = $averagePurchase > 0 ? number_format($averagePurchase) : '0';
    $monthe_buy = number_format($suminvoiceday * 30);
    $percent_of_extend = round(safe_divide($extendsum * 100, $invoicesum, 0), 2);
    $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
    $extendsum = number_format($extendsum, 0);
    if (!empty($statispay)) {
        $statusLabels = [
            'cart to cart' => $datatextbot['carttocart'] ?? 'cart to cart',
            'aqayepardakht' => $datatextbot['aqayepardakht'] ?? 'aqayepardakht',
            'zarinpal' => $datatextbot['zarinpal'] ?? 'zarinpal',
            'zarinpey' => $datatextbot['zarinpey'] ?? 'zarinpey',
            'zarinpay' => $datatextbot['zarinpey'] ?? ($datatextbot['zarinpal'] ?? 'zarinpay'),
            'plisio' => $datatextbot['textnowpayment'] ?? 'plisio',
            'arze digital offline' => $datatextbot['textnowpaymenttron'] ?? 'arze digital offline',
            'Currency Rial 1' => $datatextbot['iranpay2'] ?? 'Currency Rial 1',
            'Currency Rial 2' => $datatextbot['iranpay3'] ?? 'Currency Rial 2',
            'Currency Rial 3' => $datatextbot['iranpay1'] ?? 'Currency Rial 3',
            'paymentnotverify' => $datatextbot['textpaymentnotverify'] ?? 'paymentnotverify',
            'Star Telegram' => $datatextbot['text_star_telegram'] ?? 'Star Telegram',
        ];

        foreach ($statispay as $tracepay) {
            $paymentMethod = $tracepay['Payment_Method'] ?? '';
            $status_var = $statusLabels[$paymentMethod] ?? $paymentMethod;
            $paycount .= "
📌 نام درگاه : <code>$status_var</code>
 - تعداد پرداخت موفق : <code>{$tracepay['countpay']}</code>
 - جمع پرداختی ها : <code>{$tracepay['sumpay']}</code>\n";
        }
    }
    $bot_ping = 'نامشخص';
    $ping_start_time = microtime(true);
    $ping_response = telegram('getMe');
    $ping_duration = (microtime(true) - $ping_start_time) * 1000;
    if (is_array($ping_response) && !empty($ping_response['ok'])) {
        $bot_ping = number_format(max($ping_duration, 0), 0) . ' میلی‌ثانیه';
    }

    $statisticsall = "📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>$statistics</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>$statisticsorder</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>$count_usertest</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>$Balanceall</code> تومان

🧾 <b>تعداد کل فروش:</b> <code>$invoice</code> عدد
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>$invoiceactive</code> عدد
💵 <b>جمع کل فروش :</b> <code>$invoicesumall</code> تومان
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>$invoicesum</code> تومان
🔄 <b>جمع کل تمدید:</b> <code>$extendsum</code> تومان
📈 <b>نرخ تبدیل به مشتری:</b> <code>$ratecustomer</code>٪
💳 <b>میانگین خرید هر مشتری:</b> <code>$avgbuy_customer</code> تومان
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>$monthe_buy</code> تومان
📊 <b>درصد تمدید از فروش:</b> <code>$percent_of_extend</code>٪

👨‍💼 <b>تعداد کل نمایندگان:</b> <code>$agentsum</code> نفر
🔹 <b>نمایندگان نوع N:</b> <code>$agentsumn</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>$agentsumn2</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>$sumpanel</code> عدد
📡 <b>پینگ ربات:</b> $bot_ping
$paycount
";
    if ($datain == "stat_all_bot") {
        Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $statisticsall, $keyboard_stat, 'HTML');
    }
} elseif ($datain == "close_stat") {
    deletemessage($from_id, $message_id);
} elseif ($datain == "hoursago_stat") {
    $desired_date_time_start = time() - 3600;
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $time_current = time();
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $extra_time_stat['count'];
    $sum_change_location = number_format($extra_time_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $countextendday = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ۱ ساعت گذشته</b>

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countextendday نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "yesterday_stat") {
    $start_time = date('Y/m/d', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('Y/m/d', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "today_stat") {
    $start_time = date('Y/m/d') . " 00:00:00";
    $end_time = date('Y/m/d H:i:s');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_old_stat") {
    $firstDayLastMonth = new DateTime('first day of last month');
    $lastDayLastMonth = new DateTime('last day of last month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_current_stat") {
    $firstDayLastMonth = new DateTime('first day of this month');
    $lastDayLastMonth = new DateTime('last day of this month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "view_stat_time") {
    nm_adminInstantReply($from_id, sprintf($textbotlang['Admin']['getstats'], date('Y/m/d')), $backadmin, 'HTML');
    step("get_time_start", $from_id);
} elseif ($user['step'] == "get_time_start") {
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    savedata("clear", "start_time", $text);
    nm_adminInstantReply($from_id, "تاریخ پایان را ارسال کنید بطور مثال :  \n<code>2025/09/08</code>", $backadmin, 'HTML');
    step("get_time_end", $from_id);
} elseif ($user['step'] == "get_time_end") {
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $start_time = $userdata['start_time'] . "00:00:00";
    $end_time = $text . "23:59:00";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND  Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار تاریخ انتخابی</b>

⏳ بازه تایم  : $start_time تا $end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    step('home', $from_id);
    nm_adminInstantReply($from_id, $statisticsall, $keyboardadmin, 'HTML');
} elseif ($datain == "settingaffiliatesf") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardtypepanel, 'HTML');
} elseif (preg_match('/typepanel#(.*)/', $datain, $dataget)) {
    $typepanel = $dataget[1];
    $rx_inline_mode = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
    if ($rx_inline_mode) {
        $rx_addpanel_back_kb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']]
            ],
        ]);
    } else {
        $rx_addpanel_back_kb = $backadmin;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $rx_addpanel_back_kb, 'HTML');
    step("add_name_panel", $from_id);
    savedata("clear", "type", $typepanel);
} elseif ($user['step'] == "add_name_panel") {
    if (in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    savedata("save", "namepanel", $text);
    if ($userdata['type'] == "Manualsale") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    }
    if ($userdata['type'] == "guard") {
        $defaultGuardUrl = guardGetBaseUrl();
        savedata("save", "url_panel", $defaultGuardUrl);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $allowPrivatePanel = redfox_private_panel_endpoints_allowed();
    $urlPolicy = redfox_outbound_url_policy((string)$text, $allowPrivatePanel, $allowPrivatePanel);
    if (empty($urlPolicy['ok'])) {
        nm_adminInstantReply($from_id, '❌ آدرس پنل طبق سیاست خروجی امن مجاز نیست. آدرس عمومی باید HTTPS باشد و پنل خصوصی نیازمند فعال‌سازی صریح در تنظیمات میزبانی است.', $backadmin, 'HTML');
        return;
    }
    $normalizedPanelUrl = rtrim($text, '/');
    savedata("save", "url_panel", $normalizedPanelUrl);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['type'] == "hiddify") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        savedata("save", "username", "null");
        return;
    } elseif ($userdata['type'] == "s_ui" || $userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username", "null");
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username", $text);
} elseif ($user['step'] == "add_guard_api_key") {
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $guardBaseUrl = guardGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
    $connectionResult = guardTestConnection($guardBaseUrl, $apiKey);
    if ($connectionResult['status'] === false) {
        $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به گارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $panelConfig = $connectionResult['panel_config'] ?? [
        'status' => true,
        'panel' => [
            'type' => 'guard',
            'url_panel' => $guardBaseUrl,
            'api_key' => $apiKey,
            'password_panel' => null,
        ],
        'api_key' => $apiKey,
    ];
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $guardBaseUrl);
    $servicesResponse = guardGetServices($panelConfig);
    if ($servicesResponse['status'] === false) {
        $errorMessage = $servicesResponse['msg'] ?? 'خطای ناشناخته';
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, $errorMessage);
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $services = $servicesResponse['services'];
    if (!is_array($services) || empty($services)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'لیست سرویس Guard خالی است.');
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.');
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $statusText = guardFormatConnectionResult($connectionResult);
    if ($statusText !== '') {
        nm_adminInstantReply($from_id, "✅ اتصال به گارد برقرار شد.\n{$statusText}", $backadmin, 'HTML');
    }
    $selectionState = [
        'mode' => 'create',
        'panel' => null,
        'services' => $services,
        'selected_ids' => guardNormalizeSelectedServiceIds($availableIds, $availableIds, true),
        'select_all' => true,
        'manual_selected_ids' => [],
    ];
    savedata("save", "guard_services_cache", $services);
    savedata("save", "guard_service_selection", $selectionState);
    $message = guardBuildServiceSelectionMessage($services, $selectionState['selected_ids'], true);
    $keyboard = guardBuildServiceSelectionKeyboard($services, $selectionState['selected_ids'], 'create', true);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $selectionState['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $selectionState);
    }
    step('guard_service_selection_new', $from_id);
} elseif (preg_match('/^guardservice:(create|edit):(toggle|toggle_all|save|done|back|close)(?::(\d+))?$/', $datain, $matches)) {
    $mode = $matches[1];
    $action = $matches[2];
    $serviceId = isset($matches[3]) ? intval($matches[3]) : null;
    $userdata = json_decode($user['Processing_value'], true);
    $state = isset($userdata['guard_service_selection']) ? $userdata['guard_service_selection'] : null;
    if (!is_array($state) || ($state['mode'] ?? null) !== $mode || empty($state['services'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات سرویس Guard در دسترس نیست. لطفاً دوباره تلاش کنید.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $services = is_array($state['services']) ? $state['services'] : [];
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($services) || empty($availableIds)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'لیست سرویس Guard در دسترس نیست.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $selectAll = !empty($state['select_all']);
    $selectedIds = guardNormalizeSelectedServiceIds($state['selected_ids'] ?? [], $availableIds, false);
    if ($selectAll) {
        $selectedIds = $availableIds;
    }
    if (!empty($message_id)) {
        $state['message_id'] = $message_id;
        savedata("save", "guard_service_selection", $state);
    }
    if ($action === 'toggle') {
        if ($selectAll) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'برای تغییر انتخاب‌ها، ابتدا حالت همه سرویس‌ها را خاموش کنید.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        if ($serviceId === null || !in_array($serviceId, $availableIds, true)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'شناسه سرویس نامعتبر است.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        if (in_array($serviceId, $selectedIds, true)) {
            $selectedIds = array_values(array_diff($selectedIds, [$serviceId]));
        } else {
            $selectedIds[] = $serviceId;
        }
        $state['selected_ids'] = $selectedIds;
        $state['manual_selected_ids'] = $selectedIds;
        $state['select_all'] = false;
        savedata("save", "guard_service_selection", $state);
        $message = guardBuildServiceSelectionMessage($services, $selectedIds, false);
        $keyboard = guardBuildServiceSelectionKeyboard($services, $selectedIds, $mode, false);
        Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'وضعیت سرویس به‌روزرسانی شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'toggle_all') {
        if ($selectAll) {
            $manualSelected = isset($state['manual_selected_ids']) && is_array($state['manual_selected_ids']) ? $state['manual_selected_ids'] : [];
            $selectedIds = guardNormalizeSelectedServiceIds($manualSelected, $availableIds, false);
            $state['select_all'] = false;
        } else {
            $state['manual_selected_ids'] = $selectedIds;
            $selectedIds = $availableIds;
            $state['select_all'] = true;
        }
        $state['selected_ids'] = $selectedIds;
        savedata("save", "guard_service_selection", $state);
        $message = guardBuildServiceSelectionMessage($services, $selectedIds, !empty($state['select_all']));
        $keyboard = guardBuildServiceSelectionKeyboard($services, $selectedIds, $mode, !empty($state['select_all']));
        Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => !empty($state['select_all']) ? 'همه سرویس‌ها فعال شد.' : 'حالت همه سرویس‌ها خاموش شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'back') {
        guardRestorePanelSelection($from_id, $state);
        savedata("save", "guard_service_selection", null);
        if ($mode === 'create') {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        } else {
            step('home', $from_id);
        }
        deletemessage($from_id, $message_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'عملیات لغو شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'close') {
        guardRestorePanelSelection($from_id, $state);
        savedata("save", "guard_service_selection", null);
        deletemessage($from_id, $message_id);
        if ($mode === 'create') {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        } else {
            step('home', $from_id);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منو بسته شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'done' || $action === 'save') {
        $effectiveSelected = $selectAll ? $availableIds : guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
        if (empty($effectiveSelected)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '⚠️ حداقل یک سرویس را انتخاب کنید',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        $storageValue = guardEncodeServiceSelectionForStorage($effectiveSelected, $availableIds, $selectAll);
        if ($mode === 'create') {
            savedata("save", "guard_service_ids", $storageValue);
            savedata("save", "guard_service_selection", null);
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            step('getlimitedpanel', $from_id);
            savedata("save", "password", "null");
        } else {
            $panelName = isset($state['panel']) ? $state['panel'] : ($user['Processing_value'] ?? null);
            if ($panelName === null) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'نام پنل Guard مشخص نیست.',
                    'show_alert' => true,
                    'cache_time' => 3,
                ]);
                return;
            }
            update("marzban_panel", "guard_service_ids", $storageValue, "name_panel", $panelName);
            $state['selected_ids'] = $selectAll ? $availableIds : $effectiveSelected;
            $state['select_all'] = $selectAll;
            $state['manual_selected_ids'] = $state['manual_selected_ids'] ?? $effectiveSelected;
            savedata("save", "guard_service_selection", $state);
            $message = guardBuildServiceSelectionMessage($services, $state['selected_ids'], $selectAll);
            $message .= "\n\n✅ تنظیمات سرویس Guard ذخیره شد.";
            $keyboard = guardBuildServiceSelectionKeyboard($services, $state['selected_ids'], $mode, $selectAll);
            Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
            guardRestorePanelSelection($from_id, $state);
            step('home', $from_id);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'ذخیره شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
} elseif ($user['step'] == "guard_service_selection_new" || $user['step'] == "guard_service_selection_edit") {
    $userdata = json_decode($user['Processing_value'], true);
    $state = isset($userdata['guard_service_selection']) ? $userdata['guard_service_selection'] : null;
    $mode = $state['mode'] ?? ($user['step'] == "guard_service_selection_edit" ? 'edit' : 'create');
    $inputText = isset($text) ? trim((string) $text) : '';
    if (!is_array($state) || empty($state['services'])) {
        if ($mode === 'edit') {
            outtypepanel("guard", "❌ لیست سرویس Guard در دسترس نیست. لطفاً دوباره تلاش کنید.");
            step('home', $from_id);
        } else {
            nm_adminInstantReply($from_id, "❌ لیست سرویس Guard در دسترس نیست. لطفاً مجدداً تلاش کنید.", $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        }
        return;
    }
    if ($mode === 'edit' && $inputText !== '' && !empty($state['panel'])) {
        $panelForRefresh = select("marzban_panel", "*", "name_panel", $state['panel'], "select");
        if (is_array($panelForRefresh) && ($panelForRefresh['type'] ?? '') === "guard") {
            $servicesResponse = guardGetServices($panelForRefresh['name_panel']);
            if (!empty($servicesResponse['status']) && !empty($servicesResponse['services'])) {
                $state['services'] = $servicesResponse['services'];
                $availableRefreshed = guardExtractServiceIdsFromList($state['services']);
                $currentServices = guardParseServiceIds($panelForRefresh['guard_service_ids'] ?? null);
                $state['selected_ids'] = guardNormalizeSelectedServiceIds($currentServices, $availableRefreshed, true);
                $state['select_all'] = in_array('all', $currentServices, true) || in_array(0, $currentServices, true) || count($state['selected_ids']) === count($availableRefreshed);
                if (!empty($state['select_all'])) {
                    $state['selected_ids'] = $availableRefreshed;
                }
                $state['manual_selected_ids'] = guardNormalizeSelectedServiceIds($currentServices, $availableRefreshed, false);
            }
        }
    }
    $availableIds = guardExtractServiceIdsFromList($state['services']);
    $selectedIds = guardNormalizeSelectedServiceIds($state['selected_ids'] ?? [], $availableIds, true);
    $selectAll = !empty($state['select_all']) || (!empty($availableIds) && count($selectedIds) === count($availableIds));
    if ($selectAll) {
        $selectedIds = $availableIds;
    }
    $manualSelected = isset($state['manual_selected_ids']) && is_array($state['manual_selected_ids']) ? guardNormalizeSelectedServiceIds($state['manual_selected_ids'], $availableIds, false) : [];
    if (empty($manualSelected)) {
        $manualSelected = $selectedIds;
    }
    $state['select_all'] = $selectAll;
    $state['selected_ids'] = $selectedIds;
    $state['manual_selected_ids'] = $manualSelected;
    if (!empty($state['message_id'])) {
        deletemessage($from_id, $state['message_id']);
        $state['message_id'] = null;
    }
    savedata("save", "guard_service_selection", $state);
    $message = guardBuildServiceSelectionMessage($state['services'], $selectedIds, $selectAll);
    $keyboard = guardBuildServiceSelectionKeyboard($state['services'], $selectedIds, $mode, $selectAll);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $state['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $state);
    }
    return;
} elseif (preg_match('/^guardsettings:(note|auto_delete|auto_renew|save|back)$/', $datain, $matches)) {
    $action = $matches[1];
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این گزینه فقط برای پنل Guard در دسترس است.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel, $message_id);
    $state['message_id'] = $message_id;
    guardPersistGuardSettingsState($from_id, $state);
    if ($action === 'note') {
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, "🧑‍🏫 لطفاً توضیحات خود را ارسال کنید.\nدر صورتی که توضیحی ندارید عبارت « - » را ارسال نمایید.");
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_note', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'دریافت توضیحات',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'auto_delete') {
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, "👩‍🏫 مدت‌زمان حذف خودکار را مشخص کنید\n0 = غیرفعال (حذف خودکار انجام نمی‌شود)\nمثال: 7 → حذف خودکار پس از 7 روز");
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_auto_delete', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منتظر عدد روزها هستم',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'auto_renew') {
        $promptText = $textbotlang['Admin']['managepanel']['guard']['settings_auto_renew'];
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, $promptText);
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_auto_renew', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منتظر تنظیم تمدید خودکار هستم',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'save') {
        $panelName = $state['panel'] ?? ($panel['name_panel'] ?? null);
        if ($panelName === null) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'نام پنل Guard مشخص نیست.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        $noteToSave = $state['note'] ?? '';
        $autoDeleteDays = max(0, intval($state['auto_delete_days'] ?? 0));
        $autoRenewals = $state['auto_renewals'] ?? [];
        update("marzban_panel", "guard_note", $noteToSave, "name_panel", $panelName);
        update("marzban_panel", "guard_auto_delete_days", $autoDeleteDays, "name_panel", $panelName);
        update("marzban_panel", "guard_auto_renewals", json_encode($autoRenewals, JSON_UNESCAPED_UNICODE), "name_panel", $panelName);
        $state['note'] = $noteToSave;
        $state['auto_delete_days'] = $autoDeleteDays;
        $state['auto_renewals'] = guardDecodeAutoRenewalsConfig($autoRenewals);
        $state['saved'] = guardExtractSavedGuardSettings([
            'guard_note' => $noteToSave,
            'guard_auto_delete_days' => $autoDeleteDays,
            'guard_auto_renewals' => $autoRenewals,
        ]);
        $state['pending_changes'] = false;
        $messageId = guardRenderGuardSettingsSummary($from_id, $state, true);
        $state['message_id'] = $messageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_summary', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'ذخیره شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'back') {
        update("user", "Processing_value_one", "none", "id", $from_id);
        if (!empty($message_id)) {
            deletemessage($from_id, $message_id);
        }
        step('home', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منو بسته شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
} elseif ($user['step'] == "add_password_panel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
    savedata("save", "password", $text);
} elseif ($user['step'] == "getlimitedpanel") {
    savedata("save", "limitpanel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));

    $rx_panel_version_flag = '0';
    if (isset($userdata['type']) && $userdata['type'] === 'pasargard') {
        $rx_panel_version_flag = '1';
        $userdata['type'] = 'marzban';
        $stmt_fix = $connect->prepare("UPDATE user SET Processing_value = ? WHERE id = ?");
        $rx_userdata_json = json_encode($userdata, JSON_UNESCAPED_UNICODE);
        $stmt_fix->bind_param("ss", $rx_userdata_json, $from_id);
        $stmt_fix->execute();
        $stmt_fix->close();
    }
    if ($userdata['type'] == "x-ui_single" || $userdata['type'] == "alireza") {
        $marzbanprotocol = $randomString;
        $protocols = "vmess";
        $settingpanel = json_encode(array(
            'network' => 'ws',
            'security' => 'none',
            'externalProxy' => array(),
            'wsSettings' => array(
                'acceptProxyProtocol' => false,
                'path' => '/',
                'host' => '',
                'headers' => array()

            ),
        ));
    }
    $sublink = "onsublink";
    $configstatus = "offconfig";
    $MethodUsername = "آیدی عددی + حروف و عدد رندوم";
    $status = "active";
    $ONTestAccount = "ONTestAccount";
    $extendtextadd = "ریست حجم و زمان";
    $namecustoms = "none";
    $type = "marzban";
    $conecton = "offconecton";
    $inboundid = 1;
    $agent = "all";
    $time = "1";
    $valume = "100";
    $changeloc = "offchangeloc";
    $apiKey = isset($userdata['api_key']) ? $userdata['api_key'] : null;
    $guardServiceIds = isset($userdata['guard_service_ids']) ? $userdata['guard_service_ids'] : null;
    if ($userdata['type'] == "guard" && ($guardServiceIds === null || $guardServiceIds === '')) {
        $guardServiceIds = "all";
    }
    $guardNoteSetting = isset($userdata['guard_note']) ? $userdata['guard_note'] : '';
    $guardAutoDeleteDays = isset($userdata['guard_auto_delete_days']) ? intval($userdata['guard_auto_delete_days']) : 0;
    $guardAutoRenewalsSetting = isset($userdata['guard_auto_renewals']) ? json_encode($userdata['guard_auto_renewals'], JSON_UNESCAPED_UNICODE) : json_encode([]);
    if ($userdata['type'] != "guard") {
        $guardNoteSetting = null;
        $guardAutoDeleteDays = 0;
        $guardAutoRenewalsSetting = null;
    }
    if ($userdata['type'] == "guard") {
        $guardColumnsCheck = ensureGuardPanelColumnsReady($pdo);
        if ($guardColumnsCheck['status'] === false) {
            $missingList = implode(', ', $guardColumnsCheck['missing']);
            $warningMessage = "❌ امکان ثبت پنل Guard وجود ندارد. ستون‌های موردنیاز یافت نشدند: {$missingList}\nلطفاً یکبار دیگر صفحه را اجرا کنید تا مهاجرت خودکار انجام شود و سپس مجدداً تلاش نمایید.";
            nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
            return;
        }
    }
    $value = json_encode(array(
        'f' => "4000",
        'n' => "4000",
        'n2' => "4000"
    ));
    $valuemain = json_encode(array(
        'f' => "1",
        'n' => "1",
        'n2' => "1"
    ));
    $valuemax = json_encode(array(
        'f' => "1000",
        'n' => "1000",
        'n2' => "1000"
    ));
    $VALUE = json_encode(array(
        'f' => '0',
        'n' => '0',
        'n2' => '0'
    ));
    $valuestatusin = "offinbounddisable";
    $statusextend = "on_extend";
    $subvip = "offsubvip";
    $stauts_on_holed = "1";
    $encryptedPanelPassword = function_exists('rx_secret_encrypt') ? rx_secret_encrypt((string)$userdata['password']) : $userdata['password'];
    $encryptedApiKey = function_exists('rx_secret_encrypt') ? rx_secret_encrypt((string)$apiKey) : $apiKey;
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,api_key,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test,version_panel,guard_service_ids,guard_note,guard_auto_delete_days,guard_auto_renewals) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:api_key,:val_usertest,:time_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test,:version_panel,:guard_service_ids,:guard_note,:guard_auto_delete_days,:guard_auto_renewals)");
    $stmt->bindParam(':code_panel', $randomString);
    $stmt->bindParam(':name_panel', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->bindParam(':sublink', $sublink);
    $stmt->bindParam(':config', $configstatus);
    $stmt->bindParam(':MethodUsername', $MethodUsername);
    $stmt->bindParam(':TestAccount', $ONTestAccount);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':limit_panel', $text);
    $stmt->bindParam(':namecustom', $namecustoms);
    $stmt->bindParam(':Methodextend', $extendtextadd);
    $stmt->bindParam(':type', $userdata['type'], PDO::PARAM_STR);
    $stmt->bindParam(':conecton', $conecton);
    $stmt->bindParam(':inboundid', $inboundid);
    $stmt->bindParam(':agent', $agent);
    $stmt->bindParam(':inbound_deactive', $inboundid);
    $stmt->bindParam(':inboundstatus', $valuestatusin);
    $stmt->bindParam(':url_panel', $userdata['url_panel']);
    $stmt->bindParam(':linksubx', $userdata['url_panel']);
    $stmt->bindParam(':username_panel', $userdata['username']);
    $stmt->bindParam(':password_panel', $encryptedPanelPassword);
    $stmt->bindParam(':api_key', $encryptedApiKey);
    $stmt->bindParam(':val_usertest', $valume);
    $stmt->bindParam(':time_usertest', $time);
    $stmt->bindParam(':priceextravolume', $value);
    $stmt->bindParam(':priceextratime', $value);
    $stmt->bindParam(':pricecustomtime', $value);
    $stmt->bindParam(':pricecustomvolume', $value);
    $stmt->bindParam(':mainvolume', $valuemain);
    $stmt->bindParam(':maxvolume', $valuemax);
    $stmt->bindParam(':maintime', $valuemain);
    $stmt->bindParam(':maxtime', $valuemax);
    $stmt->bindParam(':status_extend', $statusextend);
    $stmt->bindParam(':subvip', $subvip);
    $stmt->bindParam(':changeloc', $changeloc);
    $stmt->bindParam(':customvolume', $VALUE);
    $stmt->bindParam(':on_hold_test', $stauts_on_holed);
    $stmt->bindParam(':version_panel', $rx_panel_version_flag, PDO::PARAM_STR);
    $stmt->bindParam(':guard_service_ids', $guardServiceIds);
    $stmt->bindParam(':guard_note', $guardNoteSetting);
    $stmt->bindParam(':guard_auto_delete_days', $guardAutoDeleteDays);
    $stmt->bindParam(':guard_auto_renewals', $guardAutoRenewalsSetting);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    nm_adminInstantReply($from_id, "🥳", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if ($userdata['type'] == "x-ui_single" or $userdata['type'] == "alireza_single") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم شناسه اینباند و دامنه لینک ساب را حتما تنظیم نمایید در غیراینصورت کانفیگ ساخته نخواهد شد", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
    } elseif ($userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
منوی تنظیم شناسه اینباند رفته و نام کانفیگ را تنظیم نمایید در غیراینصورت ربات هیچ کانفیگی نمیسازد", null, 'HTML');
    } elseif ($userdata['type'] == "ibsng") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی باید از مدیریت پنل > تنظیم نام گروه یک نام پیشفرض گروه که در ibsng تعریف کردید در ربات بفرستید.", null, 'HTML');
    } elseif ($userdata['type'] == "mikrotik") {
        nm_adminInstantReply($from_id, "❌ نکته :
۱ - حتما باید پلاگین اکانتینگ در میکروتیک شما نصب باشد
۲ - در بخش ip » servies » http or https باید فعال باشد ( اگر ssl تهیه کردید https روشن باشد در غیراینصورت http)", null, 'HTML');
    } elseif ($userdata['type'] == "hiddify") {
        nm_adminInstantReply($from_id, "❌ نکته :
1 - از مدیریت پنل گزینه های زیر را تنظیم کنید

1 - uuid admin : uuid ادمین از پنل دریافت و ثبت کنید
2-  دامنه لینک ساب :‌ دامنه لینک ساب پنل هیدیفای را ارسال نمایید ", null, 'HTML');
    } elseif ($userdata['type'] == "s_ui") {
        nm_adminInstantReply($from_id, "❌ نکته :
1 - از مسیر مدیریت پنل > تنظیم ⚙️ تنظیم پروتکل و اینباند یک نام کاربری کانفیگ را ارسال نمایید.", null, 'HTML');
    }
}

elseif ($datain == "systemsms") {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard(),
            'HTML'
        );
        return;
    }

    if (!is_file('cronbot/users.json')) {
        @file_put_contents('cronbot/users.json', json_encode([]));
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage'],
            ],
            [
                ['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage'],
            ],
            [
                ['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage'],
            ],
            [
                ['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
} elseif ($datain == "broadcast_status_refresh") {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus === null) {
        Editmessagetext(
            $from_id,
            $message_id,
            "✅ عملیات ارسال پیامی در حال انجام نیست.\n\nبرای شروع یک ارسال جدید از منوی اصلی وارد بخش پیام‌رسانی شوید.",
            json_encode([
                'inline_keyboard' => [
                    [['text' => "📨 منوی پیام‌رسانی",  'callback_data' => 'systemsms']],
                    [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
                ]
            ]),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => 'عملیاتی در حال انجام نیست.',
                'show_alert'        => false,
                'cache_time'        => 1,
            ]);
        }
        return;
    }
    Editmessagetext(
        $from_id,
        $message_id,
        nm_buildBroadcastStatusText($broadcastStatus),
        nm_buildBroadcastStatusKeyboard(),
        'HTML'
    );
    if (!empty($callback_query_id)) {
        $toast = '🚀 ارسال‌شده: ' . number_format((int) $broadcastStatus['sent'])
               . ' | 📊 باقی‌مانده: ' . number_format((int) $broadcastStatus['remaining']);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text'              => $toast,
            'show_alert'        => false,
            'cache_time'        => 1,
        ]);
    }
    return;
} elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard(),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => '⏳ یک عملیات ارسال در حال انجام است.',
                'show_alert'        => true,
                'cache_time'        => 1,
            ]);
        }
        return;
    }
    $type = $dataget[1];
    savedata("clear", "typeservice", $type);
    if ($type == "unpinmessage") {
        deletemessage($from_id, $message_id);
        $typesend = [
            "unpinmessage" => "لغو پیام پین شده"
        ][$type];
        $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend";
        $startaction = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
                ],
            ]
        ]);
        nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
        nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeusermessage-all'],
            ],
            [
                ['text' => "مشتریانی که خرید داشتند", 'callback_data' => 'typeusermessage-customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typeusermessage-nonecustomer'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'systemsms'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای کدام گروه کاربری اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typeusermessage", $dataget[1]);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "کاربران گروه n", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "کاربران گروه n2", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای چه دسته از کاربران اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $type);
    if ($userdata['typeusermessage'] == "customer") {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
        $stmt->bindParam(':agent', $type);
        $stmt->execute();
        $list_panel = ['inline_keyboard' => []];
        $list_panel['inline_keyboard'][] = [['text' => "تمامی پنل ها", 'callback_data' => 'locationmessage_all']];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_panel['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
            ];
        }
        $list_panel['inline_keyboard'][] = [['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],];
        Editmessagetext($from_id, $message_id, "📌 پیام برای کدام کاربران موجود در پنل های زیر ارسال شود.", json_encode($list_panel));
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
    $typeoanel = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "selectpanel", $typeoanel);
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typepinmessage", $type);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
            ],
            [
                ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
            ],
            [
                ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
            ],
            [
                ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
            ],
        ]
    ]);
    if ($userdata['typeservice'] == "forwardmessage") {
        step("gettextSystemMessage", $from_id);
        nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
} elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $type = $dataget[1];
    savedata("save", "btntypemessage", $type);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextday") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "daynoyuse", $text);
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextSystemMessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "forwardmessage") {
        savedata("save", "message", $message_id);
    } elseif ($userdata['typeservice'] == "xdaynotmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش کاربرانی که به تعداد روز تعیین شده استفاده نکردند فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    } elseif ($userdata['typeservice'] == "sendmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش ارسال همگانی فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    }
    $typesend = [
        "xdaynotmessage" => "کاربرانی که به تعداد روز تعیین شده استفاده نکردند",
        "sendmessage" => "ارسال همگانی",
        "forwardmessage" => "فوروارد همگانی",
        "unpinmessage" => "لغو پیام پین شده"
    ][$userdata['typeservice']];
    $typeservice = [
        "all" => "ارسال به همه کاربران",
        "customer" => "مشتریان",
        "nonecustomer" => "کسانی که خرید نداشتند",
    ][$userdata['typeusermessage']];
    if ($userdata['typeservice'] == "xdaynotmessage") {
        $textday = "تعداد روزی که کاربر پیام نداده است : {$userdata['daynoyuse']}";
    } else {
        $textday = "";
    }
    $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend
🎛 نوع سرویس : $typeservice
🗂 نوع کاربری : {$userdata['agent']}
$textday
";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
    nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "startaction") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    $agent = $userdata['agent'];
    $typeservice = $userdata['typeservice'];
    $typeusermessage = $userdata['typeusermessage'];
    $text = $userdata['message'];
    $cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);

    @ini_set('memory_limit', '1G');
    @set_time_limit(300);

    if (!function_exists('nm_writeBroadcastQueueFromJson')) {
        function nm_writeBroadcastQueueFromJson($jsonStr) {
            $arr = is_string($jsonStr) ? json_decode($jsonStr) : $jsonStr;
            if (!is_array($arr)) {
                return 0;
            }
            $tmp = "cronbot/users.txt.new";
            $fh  = @fopen($tmp, 'w');
            if (!$fh) {
                return 0;
            }
            $count = 0;
            foreach ($arr as $row) {
                $id = null;
                if (is_object($row) && isset($row->id))     $id = $row->id;
                elseif (is_array($row) && isset($row['id']))$id = $row['id'];
                elseif (is_scalar($row))                    $id = $row;
                if ($id !== null && $id !== '' && is_numeric($id)) {
                    fwrite($fh, ((string) $id) . "\n");
                    $count++;
                }
            }
            fclose($fh);
            @unlink('cronbot/users.json');
            @unlink('cronbot/users.txt');
            @rename($tmp, 'cronbot/users.txt');

            $arr = null;
            unset($arr);
            return $count;
        }
    }

    if ($typeservice == "unpinmessage") {
        $userlist = json_encode(select("user", "id", null, null, "fetchAll"));
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $dataunpin = json_encode(array(
            "id_admin" => $from_id,
            'type' => "unpinmessage",
            "id_message" => $message_id['result']['message_id']
        ));
        nm_writeBroadcastQueueFromJson($userlist); $userlist = null;
        file_put_contents('cronbot/info', $dataunpin);
    } elseif ($typeservice == "sendmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        @file_put_contents('cronbot/broadcast_build.log', '[' . date('Y-m-d H:i:s') . '] type=' . $typeservice . ' | group=' . ($userdata['typeusermessage'] ?? '-') . ' | agent=' . $agent . ' | panel=' . ($userdata['selectpanel'] ?? '-') . ' | تعداد نوشته‌شده در users.txt=' . $rxBuilt . PHP_EOL, FILE_APPEND);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "forwardmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "forwardmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        @file_put_contents('cronbot/broadcast_build.log', '[' . date('Y-m-d H:i:s') . '] type=' . $typeservice . ' | group=' . ($userdata['typeusermessage'] ?? '-') . ' | agent=' . $agent . ' | panel=' . ($userdata['selectpanel'] ?? '-') . ' | تعداد نوشته‌شده در users.txt=' . $rxBuilt . PHP_EOL, FILE_APPEND);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "xdaynotmessage") {
        $timedaystamp = intval($userdata['daynoyuse']) * 86400;
        $timenouser = time() - $timedaystamp;
        if ($agent == "all") {
            $stmt = $pdo->prepare("SELECT id FROM user  WHERE last_message_time < $timenouser");
            $stmt->execute();
            $userslist = json_encode($stmt->fetchAll());
        } else {
            if ($typeusermessage == "all") {
                if ($typeusermessage == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "customer") {
                    if ($userdata['selectpanel'] == "all") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    } else {
                        $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                    }
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "nonecustomer") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                }
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "xdaynotmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        @file_put_contents('cronbot/broadcast_build.log', '[' . date('Y-m-d H:i:s') . '] type=' . $typeservice . ' | group=' . ($userdata['typeusermessage'] ?? '-') . ' | agent=' . $agent . ' | panel=' . ($userdata['selectpanel'] ?? '-') . ' | تعداد نوشته‌شده در users.txt=' . $rxBuilt . PHP_EOL, FILE_APPEND);
        file_put_contents('cronbot/info', $data);
    }
} elseif ($datain == "cancel_sendmessage") {
    @file_put_contents('users.json', json_encode(array()));
    @unlink('cronbot/users.json');
    @unlink('cronbot/users.txt');
    @unlink('cronbot/users.txt.new');
    @unlink('cronbot/users.txt.tail.tmp');
    @unlink('cronbot/info');
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "📌 ارسال پیام لغو گردید.", null, 'HTML');
}

elseif ($text == "📝 تنظیم متن ربات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $textbot, 'HTML');
} elseif ($text == "تنظیم متن شروع" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_start']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "📌 متغییر های قابل استفاده

⚠️نام کاربری :
 <blockquote>{username}</blockquote>

⚠️نام اکانت :‌
<blockquote>{first_name}</blockquote>

⚠️نام خانوادگی اکانت :‌
<blockquote>{last_name}</blockquote>

⚠️زمان فعلی :
<blockquote>{time}</blockquote>

⚠️ نسخه فعلی ربات  :
<blockquote>{version}</blockquote>", null, "html");
    step('changetextstart', $from_id);
} elseif ($user['step'] == "changetextstart") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_start");
    step('home', $from_id);
} elseif ($text == "دکمه سرویس خریداری شده" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Purchased_services']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('changetextinfo', $from_id);
} elseif ($user['step'] == "changetextinfo") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Purchased_services");
    step('home', $from_id);
} elseif ($text == "دکمه اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_usertest']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('changetextusertest', $from_id);
} elseif ($user['step'] == "changetextusertest") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_usertest");
    step('home', $from_id);
} elseif ($text == "متن دکمه 📚 آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_help']}</code>", $backadmin, 'HTML');
    step('text_help', $from_id);
} elseif ($user['step'] == "text_help") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_help");
    step('home', $from_id);
} elseif ($text == "متن درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textrequestagent']}</code>", $backadmin, 'HTML');
    step('textrequestagent', $from_id);
} elseif ($user['step'] == "premium_emoji_get_char" && $adminrulecheck['rule'] == "administrator") {

    try {
        $rxPemRawText = is_string($text) ? trim($text) : '';
        $rxPemSticker = $update['message']['sticker'] ?? null;

        $rxPemBase = '';
        if ($rxPemRawText !== '') {
            $rxPemBase = $rxPemRawText;
        } elseif (is_array($rxPemSticker) && !empty($rxPemSticker['emoji'])) {
            $rxPemBase = (string)$rxPemSticker['emoji'];
        }

        if ($rxPemBase === '' || mb_strlen($rxPemBase, 'UTF-8') > 50) {
            nm_adminInstantReply($from_id, "❌ ایموجی نامعتبر است.\n\nلطفاً یک <b>ایموجی عادی</b> ارسال کنید (مثل ✅، ❌، 🔥، 💎).", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }

        update("user", "Processing_value", $rxPemBase, "id", $from_id);

        nm_adminInstantReply($from_id, "✅ <b>ایموجی پایه ذخیره شد:</b> {$rxPemBase}\n\n📌 حالا <b>ایموجی پرمیوم متناظر</b> را ارسال کنید.\n\nربات خودکار آیدی آن را تشخیص می‌دهد و این دو را به هم متصل می‌کند ✨\n\n💡 می‌توانید ایموجی پرمیوم را به هر شکلی بفرستید: متن، استیکر، Forward یا Reply.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('premium_emoji_get_id', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_char] EXCEPTION: ' . redfox_exception_fingerprint($rxPemErr) . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی</b>\n\nعملیات انجام نشد؛ لطفاً دوباره تلاش کنید.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_get_id" && $adminrulecheck['rule'] == "administrator") {

    try {
        $rxPemBase = (string)($user['Processing_value'] ?? '');
        if ($rxPemBase === '') {
            nm_adminInstantReply($from_id, "❌ ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }

        $rxPemTableOk = true;
        try {
            $rxPemCheck = $pdo->query("SHOW TABLES LIKE 'premium_emojis'");
            $rxPemTableOk = ($rxPemCheck && $rxPemCheck->fetchColumn() !== false);
        } catch (\Throwable $rxPemTblErr) { $rxPemTableOk = false; }
        if (!$rxPemTableOk) {
            nm_adminInstantReply($from_id, "❌ <b>جدول دیتابیس آماده نیست</b>\n\nقبل از این، باید <code>table.php</code> را در مرورگر اجرا کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }

        $rxPemCid = '';

        $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
        if (is_array($rxPemEntities)) {
            foreach ($rxPemEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }

        if ($rxPemCid === '') {
            $rxPemSticker = $update['message']['sticker'] ?? null;
            if (is_array($rxPemSticker) && ($rxPemSticker['type'] ?? '') === 'custom_emoji'
                && !empty($rxPemSticker['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
            }
        }

        if ($rxPemCid === '') {
            $rxPemReply = $update['message']['reply_to_message'] ?? null;
            if (is_array($rxPemReply)) {
                $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
                if (is_array($rxPemReplyEntities)) {
                    foreach ($rxPemReplyEntities as $rxPemEnt) {
                        if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                            $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                            break;
                        }
                    }
                }
                if ($rxPemCid === '') {
                    $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
                    if (is_array($rxPemReplySticker)
                        && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
                        && !empty($rxPemReplySticker['custom_emoji_id'])) {
                        $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
                    }
                }
            }
        }

        if ($rxPemCid === '' && is_string($text)) {
            $rxPemCandidate = trim($text);
            if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
                $rxPemCid = $rxPemCandidate;
            }
        }

        if ($rxPemCid === '') {
            $rxPemDiag = '';
            $rxPemSentText = is_string($text) ? trim($text) : '';
            $rxPemStkType = is_array($update['message']['sticker'] ?? null) ? ($update['message']['sticker']['type'] ?? '') : '';
            if ($rxPemStkType !== '' && $rxPemStkType !== 'custom_emoji') {
                $rxPemDiag = "🔎 شما یک <b>استیکر معمولی</b> فرستادید (نوع آن custom_emoji نیست).";
            } elseif ($rxPemSentText !== '' && mb_strlen($rxPemSentText, 'UTF-8') <= 4) {
                $rxPemDiag = "🔎 شما یک <b>ایموجی عادی</b> فرستادید: <b>{$rxPemSentText}</b>\nاین فاقد متادیتای پرمیوم است.";
            } elseif (ctype_digit($rxPemSentText)) {
                $rxPemDiag = "🔎 آیدی عددی نامعتبر است (طول باید بین ۸ تا ۳۰ رقم باشد).";
            }
            if ($rxPemDiag !== '') { $rxPemDiag .= "\n\n"; }
            nm_adminInstantReply($from_id, "❌ ایموجی پرمیوم یافت نشد.\n\n{$rxPemDiag}📌 لطفاً <b>ایموجی پرمیوم</b> را برای ایموجی پایه «{$rxPemBase}» ارسال کنید.\n\n💡 یا اگر آیدی عددی پرمیوم را دارید، آن را پیست کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }

        $rxPemNow = time();
        $rxPemAlreadyExists = false;
        try {
            $rxPemIns = $pdo->prepare("INSERT INTO premium_emojis (emoji, custom_emoji_id, created_at, updated_at) VALUES (:e, :c, :t1, :t2)");
            $rxPemIns->execute([':e' => $rxPemBase, ':c' => $rxPemCid, ':t1' => $rxPemNow, ':t2' => $rxPemNow]);
        } catch (\Throwable $rxPemInsErr) {

            if ($rxPemInsErr instanceof PDOException && (int)($rxPemInsErr->errorInfo[1] ?? 0) === 1062) {
                $rxPemAlreadyExists = true;
                try {
                    $rxPemTouch = $pdo->prepare("UPDATE premium_emojis SET updated_at = :t WHERE emoji = :e AND custom_emoji_id = :c");
                    $rxPemTouch->execute([':t' => $rxPemNow, ':e' => $rxPemBase, ':c' => $rxPemCid]);
                } catch (\Throwable $rxPemTouchErr) {  }
            } else {
                throw $rxPemInsErr;
            }
        }
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

        if ($rxPemAlreadyExists) {
            $rxPemSuccessMsg = "ℹ️ <b>این ایموجی پرمیوم با همین آیدی قبلاً ثبت شده بود</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "هیچ ردیف تکراری اضافه نشد.";
        } else {
            $rxPemSuccessMsg = "✅ <b>ایموجی پرمیوم جدید با موفقیت اضافه شد!</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "✨ از این لحظه، در پیام‌های ربات «{$rxPemBase}» به نسخه پرمیوم تبدیل می‌شود.\n\n"
                . "💡 می‌توانید برای همین «{$rxPemBase}» آیدی‌های پرمیوم بیشتری هم اضافه کنید — جدیدترین آیدی به‌صورت فعال استفاده می‌شود و قبلی‌ها در لیست باقی می‌مانند.";
        }
        nm_adminInstantReply($from_id, $rxPemSuccessMsg, json_encode([
            'inline_keyboard' => [
                [['text' => "➕ افزودن ایموجی دیگر", 'callback_data' => "premium_emoji_add"]],
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
        step('home', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_id] EXCEPTION: ' . redfox_exception_fingerprint($rxPemErr) . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ذخیره ایموجی پرمیوم</b>\n\nعملیات انجام نشد؛ لطفاً دوباره تلاش کنید.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_edit_id" && $adminrulecheck['rule'] == "administrator") {

    try {
    $rxPemEmojiChar = (string)($user['Processing_value'] ?? '');
    if ($rxPemEmojiChar === '') {
        nm_adminInstantReply($from_id, "❌ خطا: ایموجی هدف یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $rxPemCid = '';
    $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
    if (is_array($rxPemEntities)) {
        foreach ($rxPemEntities as $rxPemEnt) {
            if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                break;
            }
        }
    }

    $rxPemSticker = $update['message']['sticker'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemSticker)) {
        if (($rxPemSticker['type'] ?? '') === 'custom_emoji' && !empty($rxPemSticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
        }
    }

    $rxPemReply = $update['message']['reply_to_message'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemReply)) {
        $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
        if (is_array($rxPemReplyEntities)) {
            foreach ($rxPemReplyEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }
        $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
        if ($rxPemCid === '' && is_array($rxPemReplySticker)
            && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
            && !empty($rxPemReplySticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
        }
    }
    if ($rxPemCid === '' && is_string($text)) {
        $rxPemCandidate = trim($text);
        if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
            $rxPemCid = $rxPemCandidate;
        }
    }
    if ($rxPemCid === '') {
        nm_adminInstantReply($from_id, "❌ آیدی ایموجی پرمیوم یافت نشد.\n\n📌 لطفاً <b>ایموجی پرمیوم</b> را ارسال کنید یا آیدی عددی را وارد نمایید.", null, 'HTML');
        return;
    }
    $rxPemNow = time();
    $rxPemStmt = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE emoji = :e");
    $rxPemStmt->execute([':e' => $rxPemEmojiChar, ':c' => $rxPemCid, ':t' => $rxPemNow]);
    if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    nm_adminInstantReply($from_id, "✅ ایموجی پرمیوم برای [{$rxPemEmojiChar}] به‌روزرسانی شد.\n\n🆔 <code>{$rxPemCid}</code>", json_encode([
        'inline_keyboard' => [
            [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
        ]
    ]), 'HTML');
    step('home', $from_id);
    } catch (\Throwable $rxPemEditErr) {
        @error_log('[premium_emoji_edit_id] EXCEPTION: ' . redfox_exception_fingerprint($rxPemEditErr) . ' @ ' . $rxPemEditErr->getFile() . ':' . $rxPemEditErr->getLine());
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ویرایش ایموجی پرمیوم</b>\n\nعملیات انجام نشد؛ لطفاً دوباره تلاش کنید.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "textrequestagent") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textrequestagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه  نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textpanelagent']}</code>", $backadmin, 'HTML');
    step('textpanelagent', $from_id);
} elseif ($user['step'] == "textpanelagent") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textpanelagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه ☎️ پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_support']}</code>", $backadmin, 'HTML');
    step('text_support', $from_id);
} elseif ($user['step'] == "text_support") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_support");
    step('home', $from_id);
} elseif ($text == "دکمه سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_fq']}</code>", $backadmin, 'HTML');
    step('text_fq', $from_id);
} elseif ($user['step'] == "text_fq") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_fq']}</code>", $backadmin, 'HTML');
    step('text_dec_fq', $from_id);
} elseif ($user['step'] == "text_dec_fq") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات عضویت اجباری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_channel']}</code>", $backadmin, 'HTML');
    step('text_channel', $from_id);
} elseif ($user['step'] == "text_channel") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_channel");
    step('home', $from_id);
} elseif ($text == "متن دکمه کیف پول" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['accountwallet']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('accountwallet', $from_id);
} elseif ($user['step'] == "accountwallet") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "accountwallet");
    step('home', $from_id);
} elseif ($text == "متن دکمه کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Discount']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('text_Discount', $from_id);
} elseif ($user['step'] == "text_Discount") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Discount");
    step('home', $from_id);
} elseif ($text == "دکمه افزایش موجودی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Add_Balance']}</code>", $backadmin, 'HTML');
    step('text_Add_Balance', $from_id);
} elseif ($user['step'] == "text_Add_Balance") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Add_Balance");
    step('home', $from_id);
} elseif ($text == "متن دکمه خرید اشتراک" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_sell']}</code>", $backadmin, 'HTML');
    step('text_sell', $from_id);
} elseif ($user['step'] == "text_sell") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_sell");
    step('home', $from_id);
} elseif ($text == "متن دکمه زیرمجموعه گیری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_affiliates']}</code>", $backadmin, 'HTML');
    step('text_affiliates', $from_id);
} elseif ($user['step'] == "text_affiliates") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_affiliates");
    step('home', $from_id);
} elseif ($text == "متن دکمه لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_Tariff_list', $from_id);
} elseif ($user['step'] == "text_Tariff_list") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن توضیحات لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_dec_Tariff_list', $from_id);
} elseif ($user['step'] == "text_dec_Tariff_list") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن انتخاب لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textselectlocation']}</code>", $backadmin, 'HTML');
    step('textselectlocation', $from_id);
} elseif ($user['step'] == "textselectlocation") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textselectlocation");
    step('home', $from_id);
} elseif ($text == "متن پیش فاکتور" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_pishinvoice']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_product : نام محصول
Service_time : زمان سرویس
price : قیمت سرویس
Volume : حجم سرویس
userBalance : موجودی کاربر
note : یادداشت

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_pishinvoice', $from_id);
} elseif ($user['step'] == "text_pishinvoice") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_pishinvoice");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpay']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytext', $from_id);
} elseif ($user['step'] == "text_afterpaytext") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpay");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید ibsng" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpayibsng']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytextibsng', $from_id);
} elseif ($user['step'] == "text_afterpaytextibsng") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpayibsng");
    step('home', $from_id);
} elseif ($text == "متن کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
price : مبلغ تراکنش
card_number : شماره کارت
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart', $from_id);
} elseif ($user['step'] == "text_cart") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart");
    step('home', $from_id);
} elseif ($text == "تنظیم متن کارت به کارت خودکار" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart_auto']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
price : مبلغ تراکنش
card_number : شماره کارت
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart_auto', $from_id);
} elseif ($user['step'] == "text_cart_auto") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart_auto");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textaftertext']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک اتصال
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_aftertesttext', $from_id);
} elseif ($user['step'] == "text_aftertesttext") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textaftertext");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($text == "متن کرون تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['crontest']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_crontest', $from_id);
} elseif ($user['step'] == "text_crontest") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "crontest");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($user['step'] == "text_textmanual") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textmanual");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت WGDashboard" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_wgdashboard']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_wgdashboard', $from_id);
} elseif ($user['step'] == "text_wgdashboard") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wgdashboard");
    step('home', $from_id);
} elseif ($text == "دکمه تمدید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_extend']}</code>", $backadmin, 'HTML');
    step('text_extend', $from_id);
} elseif ($user['step'] == "text_extend") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_extend");
    step('home', $from_id);
} elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "iduser", $iduser);
    nm_adminInstantReply($from_id, "📌 متن یا تصویر خود را ارسال نمایید", $backadmin, 'HTML');
    step('sendmessagetext', $from_id);
} elseif ($user['step'] == "sendmessagetext") {
    if ($photo) {
        savedata("save", "type", "photo");
        savedata("save", "photoid", $photoid);
        savedata("save", "text", $caption);
    } else {
        savedata("save", "text", $text);
        savedata("save", "type", "text");
    }
    $textb = "📌 کاربر بتواند پاسخ دهد یاخیر ؟
1 - بله  پاسخ دهد
2 - خیر پاسخ ندهد
پاسخ را به عدد ارسال کنید";
    nm_adminInstantReply($from_id, $textb, $backadmin, 'HTML');
    step('sendmessagetid', $from_id);
} elseif ($user['step'] == "sendmessagetid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $textsendadmin = "
👤 یک پیام از طرف ادمین ارسال شده است
متن پیام:

{$userdata['text']}";
    if (intval($text) == "1") {
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                ],
            ]
        ]);
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'reply_markup' => $Response,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
        }
    } else {
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
        }
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📤 فوروارد پیام برای یک کاربر") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
    step('getmessageforward', $from_id);
} elseif ($user['step'] == "getmessageforward") {
    savedata("clear", "messageid", $message_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
    step('getbtnresponseforward', $from_id);
} elseif ($user['step'] == "getbtnresponseforward") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    forwardMessage($from_id, $userdata['messageid'], $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 بخش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
} elseif ($text == "📚 اضافه کردن آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
    step('add_name_help', $from_id);
} elseif ($user['step'] == "add_name_help") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    $helpexits = select("help", "*", "name_os", $text, "count");
    if ($helpexits != 0) {
        nm_adminInstantReply($from_id, "❌ نام آموزش وجود دارد از نام دیگری استفاده نمایید.", null, 'HTML');
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    update("user", "Processing_value", $text, "id", $from_id);
    if ($setting['categoryhelp'] == "0") {
        update("help", "category", "0", "name_os", $user['Processing_value']);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
        step('add_dec', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "📌 نام دسته بندی برای آموزش را ارسال نمایید", $backadmin, 'HTML');
    step('getcatgoryhelp', $from_id);
} elseif ($user['step'] == "getcatgoryhelp") {
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
    step('add_dec', $from_id);
} elseif ($user['step'] == "add_dec") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($text) {
        update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    } elseif ($document) {
        if (isset($fileid))
            update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step('remove_help', $from_id);
} elseif ($user['step'] == "remove_help") {
    $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
    $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
} elseif ($user['step'] == "getmessageAsAdmin") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
    $Respuseronse = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
            ],
        ]
    ]);
    if ($text) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
        sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
    }
    if ($photo) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$caption";
        telegram('sendphoto', [
            'chat_id' => $user['Processing_value'],
            'photo' => $photoid,
            'reply_markup' => $Respuseronse,
            'caption' => $textSendAdminToUser,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
} elseif (
    ($text == "⚙️ وضعیت قابلیت ها" && $adminrulecheck['rule'] == "administrator")
    || (in_array((string)($datain ?? ''), ['featcat_main','featcat_bot','featcat_users','featcat_shop','featcat_lottery','featcat_crons','featcat_antispam'], true) && $adminrulecheck['rule'] == "administrator")
) {
    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $setting = select("setting", "*", null, null, "select");
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $authScopeVal = (string)($setting['auth_scope'] ?? 'all');
    if ($authScopeVal === '') { $authScopeVal = 'all'; }
    $authScopeBtn = ($authScopeVal === 'newonly')
        ? "👥 محدوده احراز هویت: فقط کاربران جدید"
        : "👥 محدوده احراز هویت: همه کاربران";
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = $infocardStatusValue === '1'
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $premiumEmojiStatusValue = (string)($setting['premium_emoji_status'] ?? '0');
    $premiumEmojiStatusText = ($premiumEmojiStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $premiumEmojiCount = 0;
    try {
        $rxPemRow = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> ''")->fetch(PDO::FETCH_ASSOC);
        $premiumEmojiCount = (int)($rxPemRow['c'] ?? 0);
    } catch (\Throwable $rxPemErr) { $premiumEmojiCount = 0; }

    $rxAsStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsStatusText  = ($rxAsStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsMsgCountVal = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCountVal < 1)    { $rxAsMsgCountVal = 1; }
    if ($rxAsMsgCountVal > 1000) { $rxAsMsgCountVal = 1000; }
    $rxAsSecondsVal = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSecondsVal < 1)    { $rxAsSecondsVal = 1; }
    if ($rxAsSecondsVal > 3600) { $rxAsSecondsVal = 3600; }
    $rxAsMuteSecondsVal = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSecondsVal < 1)     { $rxAsMuteSecondsVal = 1; }
    if ($rxAsMuteSecondsVal > 86400) { $rxAsMuteSecondsVal = 86400; }

    $rxFeatView = 'main';
    $rxDatainStr = (string)($datain ?? '');
    if ($rxDatainStr === 'featcat_bot')       $rxFeatView = 'bot';
    elseif ($rxDatainStr === 'featcat_users') $rxFeatView = 'users';
    elseif ($rxDatainStr === 'featcat_shop')  $rxFeatView = 'shop';
    elseif ($rxDatainStr === 'featcat_lottery') $rxFeatView = 'lottery';
    elseif ($rxDatainStr === 'featcat_crons') $rxFeatView = 'crons';
    elseif ($rxDatainStr === 'featcat_antispam') $rxFeatView = 'antispam';

    $rxBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']];

    if ($rxFeatView === 'main') {

        $rxFeatKb = [
            [['text' => "🤖 آپشن‌های اصلی ربات",       'callback_data' => 'featcat_bot']],
            [['text' => "👥 کاربران و پشتیبانی",        'callback_data' => 'featcat_users']],
            [['text' => "🛍 فروش و خدمات",              'callback_data' => 'featcat_shop']],
            [['text' => "🎁 گردونه و قرعه‌کشی",          'callback_data' => 'featcat_lottery']],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",          'callback_data' => 'featcat_crons']],
            [['text' => "🌟 ایموجی پرمیوم ({$premiumEmojiCount})", 'callback_data' => 'premium_emoji_settings']],
            [['text' => "🛡 آنتی اسپم", 'callback_data' => 'featcat_antispam']],
            [['text' => "❌ بستن", 'callback_data' => 'close_stat']],
        ];
        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟\n\n💡 برای تنظیمات هر بخش، روی دکمه دسته بزنید.";
    } elseif ($rxFeatView === 'bot') {
        $rxFeatKb = rx_featCategoryRows('bot');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>\n\nقابلیت‌های اصلی ربات را در اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'users') {
        $rxFeatKb = rx_featCategoryRows('users');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>\n\nقابلیت‌های مربوط به کاربران، اعلان‌ها و پشتیبانی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'shop') {
        $rxFeatKb = rx_featCategoryRows('shop');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>\n\nقابلیت‌های فروشگاه، کانفیگ و خدمات جانبی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'lottery') {
        $rxFeatKb = rx_featCategoryRows('lottery');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>\n\nقابلیت‌های گردونه شانس، قرعه‌کشی و زیرمجموعه را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'crons') {
        $rxFeatKb = rx_featCategoryRows('crons');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>\n\nقابلیت‌های مربوط به کرون‌ها (cronjobs) و زمان‌بندی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'antispam') {

        $rxFeatKb = [
            [['text' => $rxAsStatusText, 'callback_data' => "antispam_toggle"],
             ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMsgCountVal, 'callback_data' => "antispam_set_count"],
             ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsSecondsVal, 'callback_data' => "antispam_set_seconds"],
             ['text' => "⏱ بازه زمانی (ثانیه)", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMuteSecondsVal, 'callback_data' => "antispam_set_mute"],
             ['text' => "🔇 مدت آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🛡 <b>آنتی اسپم</b>\n\n"
            . "محدودیت ارسال پیام برای جلوگیری از اسپم.\n\n"
            . "📌 <b>تنظیمات فعلی:</b>\n"
            . "• وضعیت: {$rxAsStatusText}\n"
            . "• تعداد پیام مجاز: <b>{$rxAsMsgCountVal}</b> پیام\n"
            . "• بازه زمانی: هر <b>{$rxAsSecondsVal}</b> ثانیه\n"
            . "• مدت آف بودن پس از تخلف: <b>{$rxAsMuteSecondsVal}</b> ثانیه\n\n"
            . "💡 اگر کاربری بیشتر از {$rxAsMsgCountVal} پیام در {$rxAsSecondsVal} ثانیه ارسال کند، ربات برای {$rxAsMuteSecondsVal} ثانیه به او پاسخ نمی‌دهد.\n"
            . "پس از پایان این مدت، با ارسال مجدد /start کاربر مجدداً پاسخ می‌گیرد.\n\n"
            . "ℹ️ <b>توجه:</b> این محدودیت فقط برای کاربران معمولی اعمال می‌شود. ادمین‌ها هرگز محدود نمی‌شوند.";
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatKb]);
    nm_adminInstantReply($from_id, $rxFeatTitle, $Bot_Status, 'HTML');
} elseif ($datain == "antispam_noop" && $adminrulecheck['rule'] == "administrator") {

    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 1,
            ]);
        } catch (\Throwable $rxAsNoopErr) {  }
    }
} elseif ($datain == "antispam_toggle" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCurrent = (string)($setting['antispam_status'] ?? '0');
    $rxAsNew = ($rxAsCurrent === '1') ? '0' : '1';
    update("setting", "antispam_status", $rxAsNew);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => ($rxAsNew === '1') ? '✅ آنتی اسپم فعال شد' : '⛔️ آنتی اسپم غیرفعال شد',
                'show_alert' => false,
                'cache_time' => 2,
            ]);
        } catch (\Throwable $rxAsTogErr) {  }
    }

    $setting = select("setting", "*", null, null, "select", ['cache' => false]);
    $rxAsRefreshedStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsRefreshedStatusText  = ($rxAsRefreshedStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsRefreshedMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    $rxAsRefreshedSeconds  = (int)($setting['antispam_seconds'] ?? 3);
    $rxAsRefreshedMute     = (int)($setting['antispam_mute_seconds'] ?? 5);
    $rxAsRefreshedKb = [
        [['text' => $rxAsRefreshedStatusText, 'callback_data' => "antispam_toggle"],
         ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMsgCount, 'callback_data' => "antispam_set_count"],
         ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedSeconds, 'callback_data' => "antispam_set_seconds"],
         ['text' => "⏱ بازه زمانی (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMute, 'callback_data' => "antispam_set_mute"],
         ['text' => "🔇 مدت آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']],
    ];
    $rxAsRefreshedTitle = "🛡 <b>آنتی اسپم</b>\n\n"
        . "📌 <b>تنظیمات فعلی:</b>\n"
        . "• وضعیت: {$rxAsRefreshedStatusText}\n"
        . "• تعداد پیام مجاز: <b>{$rxAsRefreshedMsgCount}</b> پیام\n"
        . "• بازه زمانی: هر <b>{$rxAsRefreshedSeconds}</b> ثانیه\n"
        . "• مدت آف بودن: <b>{$rxAsRefreshedMute}</b> ثانیه";
    if (isset($message_id)) {
        Editmessagetext($from_id, $message_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    } else {
        nm_adminInstantReply($from_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    }
} elseif ($datain == "antispam_set_count" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_msg_count'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم تعداد پیام مجاز آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b>\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>1000</b> ارسال کنید.\n"
        . "این عدد بیشترین تعداد پیامی است که کاربر می‌تواند در بازه زمانی تعیین‌شده ارسال کند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_count', $from_id);
} elseif ($datain == "antispam_set_seconds" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_seconds'] ?? 3);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم بازه زمانی آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>3600</b> (یک ساعت) ارسال کنید.\n"
        . "این عدد طول بازه زمانی برای شمارش پیام‌ها است.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_seconds', $from_id);
} elseif ($datain == "antispam_set_mute" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_mute_seconds'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم مدت آف بودن ربات</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>86400</b> (یک روز) ارسال کنید.\n"
        . "این عدد مدت زمانی است که ربات پس از تخلف کاربر، به او پاسخ نمی‌دهد.\n\n"
        . "ℹ️ این محدودیت فقط برای کاربران اعمال می‌شود؛ ادمین‌ها هرگز محدود نمی‌شوند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_mute', $from_id);
} elseif ($user['step'] == "antispam_get_count" && $adminrulecheck['rule'] == "administrator") {

    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 1000) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>1000</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_msg_count", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ تعداد پیام مجاز آنتی اسپم روی <b>{$rxAsNum}</b> تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_seconds" && $adminrulecheck['rule'] == "administrator") {

    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 3600) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>3600</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ بازه زمانی آنتی اسپم روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_mute" && $adminrulecheck['rule'] == "administrator") {

    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 86400) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>86400</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_mute_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ مدت آف بودن ربات روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "botstatuson") {
            $valuenew = "botstatusoff";
        } else {
            $valuenew = "botstatuson";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "usernamebtn") {
        if ($value == "onnotuser") {
            $valuenew = "offnotuser";
        } else {
            $valuenew = "onnotuser";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "notifnew") {
        if ($value == "onnewuser") {
            $valuenew = "offnewuser";
        } else {
            $valuenew = "onnewuser";
        }
        update("setting", "statusnewuser", $valuenew);
    } elseif ($type == "showagent") {
        if ($value == "onrequestagent") {
            $valuenew = "offrequestagent";
        } else {
            $valuenew = "onrequestagent";
        }
        update("setting", "statusagentrequest", $valuenew);
    } elseif ($type == "role") {
        if ($value == "rolleon") {
            $valuenew = "rolleoff";
        } else {
            $valuenew = "rolleon";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "get_number") {
        $current = $setting['get_number'] ?? 'offAuthenticationphone';
        $valuenew = ($current === "onAuthenticationphone") ? "offAuthenticationphone" : "onAuthenticationphone";
        update("setting", "get_number", $valuenew);
        $setting['get_number'] = $valuenew;
    } elseif ($type == "Authenticationiran") {
        $current = $setting['iran_number'] ?? 'offAuthenticationiran';
        $valuenew = ($current === "onAuthenticationiran") ? "offAuthenticationiran" : "onAuthenticationiran";
        update("setting", "iran_number", $valuenew);
        $setting['iran_number'] = $valuenew;
    } elseif ($type == "inlinebtnmain") {
        if ($value == "oninline") {
            $valuenew = "offinline";
        } else {
            $valuenew = "oninline";
        }
        update("setting", "inlinebtnmain", $valuenew);
    } elseif ($type == "verifystart") {
        $current = $setting['verifystart'] ?? 'offverify';
        $valuenew = ($current === "onverify") ? "offverify" : "onverify";
        update("setting", "verifystart", $valuenew);
        $setting['verifystart'] = $valuenew;
    } elseif ($type == "statussupportpv") {
        if ($value == "onpvsupport") {
            $valuenew = "offpvsupport";
        } else {
            $valuenew = "onpvsupport";
        }
        update("setting", "statussupportpv", $valuenew);
    } elseif ($type == "statusnamecustom") {
        if ($value == "onnamecustom") {
            $valuenew = "offnamecustom";
        } else {
            $valuenew = "onnamecustom";
        }
        update("setting", "statusnamecustom", $valuenew);
    } elseif ($type == "bulkbuy") {
        if ($value == "onbulk") {
            $valuenew = "offbulk";
        } else {
            $valuenew = "onbulk";
        }
        update("setting", "bulkbuy", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "authscope") {
        if ($value == "newonly") {
            $valuenew = "all";
        } else {
            $valuenew = "newonly";
        }
        update("setting", "auth_scope", $valuenew);
    } elseif ($type == "wheelagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelagent", $valuenew);
    } elseif ($type == "keyconfig") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_keyboard_config", $valuenew);
    } elseif ($type == "Lotteryagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Lotteryagent", $valuenew);
    } elseif ($type == "compycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscopycart", $valuenew);
    } elseif ($type == "premiumemoji") {

        $rxPemCurrent = (string)($setting['premium_emoji_status'] ?? '0');
        $valuenew = ($rxPemCurrent === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);

        $setting['premium_emoji_status'] = $valuenew;
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

        if (function_exists('rxRenderPremiumEmojiPanel')) {
            rxRenderPremiumEmojiPanel($from_id, 1);
        }
        return;
    } elseif ($type == "score") {
        if ($value == "1") {
            if (isShellExecAvailable()) {
                $crontabBinary = getCrontabBinary();
                if ($crontabBinary === null) {
                    error_log('Unable to locate crontab executable; cannot remove lottery cron job.');
                } else {
                    $currentCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
                    $jobToRemove = "*/1 * * * * curl https://$domainhosts/cronbot/lottery.php";
                    $newCronJobs = preg_replace('/' . preg_quote($jobToRemove, '/') . '/', '', (string) $currentCronJobs);
                    $tempCronFile = '/tmp/crontab.txt';
                    file_put_contents($tempCronFile, trim($newCronJobs) . PHP_EOL);
                    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($tempCronFile)));
                    if (file_exists($tempCronFile)) {
                        unlink($tempCronFile);
                    }
                }
            } else {
                error_log('Unable to remove lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "0";
        } else {
            $phpFilePath = "https://$domainhosts/cronbot/lottery.php";
            $cronCommand = "*/1 * * * * curl $phpFilePath";
            if (!addCronIfNotExists($cronCommand)) {
                error_log('Unable to register lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "1";
        }
        update("setting", "scorestatus", $valuenew);
    } elseif ($type == "wheel_luck") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelـluck", $valuenew);
    } elseif ($type == "affiliatesstatus") {
        if ($value == "onaffiliates") {
            $valuenew = "offaffiliates";
        } else {
            $valuenew = "onaffiliates";
        }
        update("setting", "affiliatesstatus", $valuenew);
    } elseif ($type == "btn_status_category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "categoryhelp", $valuenew);
    } elseif ($type == "linkappstatus") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "linkappstatus", $valuenew);
    } elseif ($type == "btnstautslanguage") {
        if ($setting['languageru'] == "1") {
            nm_adminInstantReply($from_id, "زبان روسیه ای روشن است و نمی توانید زبان انگلیسی را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageen", $valuenew);
    } elseif ($type == "btnstautslanguageru") {
        if ($setting['languageen'] == "1") {
            nm_adminInstantReply($from_id, "زبان انگلیسی روشن است و نمی توانید زبان روسیه ای را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageru", $valuenew);
    } elseif ($type == "wheelagentfirst") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusfirstwheel", $valuenew);
    } elseif ($type == "changeloc") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuslimitchangeloc", $valuenew);
    } elseif ($type == "Debtsettlement") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Debtsettlement", $valuenew);
    } elseif ($type == "Dice") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Dice", $valuenew);
    } elseif ($type == "statusnamecustomf") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusnoteforf", $valuenew);
    } elseif ($type == "crontest") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['test'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronday") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['day'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronvolume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove_volume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove_volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_node") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_node'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_panel") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_panel'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "on_hold") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['on_hold'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "infocard") {

        $valuenew = ($value === '1') ? '0' : '1';
        $existing = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
        if (is_array($existing) && isset($existing['Namevalue'])) {
            update("shopSetting", "value", $valuenew, "Namevalue", "infocard_status");
        } else {

            try {
                $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                $stmt->execute([':n' => 'infocard_status', ':v' => $valuenew]);
                if (function_exists('clearSelectCache')) clearSelectCache('shopSetting');
            } catch (\Throwable $e) {
                error_log('infocard_status insert failed: ' . redfox_exception_fingerprint($e));
            }
        }
    } elseif ($type == "premiumemoji") {

        $valuenew = ($value === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    }
    $setting = select("setting", "*");
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $_rxOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_rxOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status = [
        'botstatuson' => $_rxOn,
        'botstatusoff' => $_rxOff,
    ][$setting['Bot_Status']] ?? $_rxOff;
    $name_status_username = [
        'onnotuser' => $_rxOn,
        'offnotuser' => $_rxOff,
    ][$setting['NotUser']] ?? $_rxOff;
    $name_status_notifnewuser = [
        'onnewuser' => $_rxOn,
        'offnewuser' => $_rxOff,
    ][$setting['statusnewuser']] ?? $_rxOff;
    $name_status_showagent = [
        'onrequestagent' => $_rxOn,
        'offrequestagent' => $_rxOff,
    ][$setting['statusagentrequest']] ?? $_rxOff;
    $name_status_role = [
        'rolleon' => $_rxOn,
        'rolleoff' => $_rxOff,
    ][$setting['roll_Status']] ?? $_rxOff;
    $Authenticationphone = [
        'onAuthenticationphone' => $_rxOn,
        'offAuthenticationphone' => $_rxOff,
    ][$setting['get_number']] ?? $_rxOff;
    $Authenticationiran = [
        'onAuthenticationiran' => $_rxOn,
        'offAuthenticationiran' => $_rxOff,
    ][$setting['iran_number']] ?? $_rxOff;
    $statusinline = [
        'oninline' => $_rxOn,
        'offinline' => $_rxOff,
    ][$setting['inlinebtnmain']] ?? $_rxOff;
    $statusverify = [
        'onverify' => $_rxOn,
        'offverify' => $_rxOff,
    ][$setting['verifystart']] ?? $_rxOff;
    $statuspvsupport = [
        'onpvsupport' => $_rxOn,
        'offpvsupport' => $_rxOff,
    ][$setting['statussupportpv']] ?? $_rxOff;
    $statusnameconfig = [
        'onnamecustom' => $_rxOn,
        'offnamecustom' => $_rxOff,
    ][$setting['statusnamecustom']] ?? $_rxOff;
    $statusnamebulk = [
        'onbulk' => $_rxOn,
        'offbulk' => $_rxOff,
    ][$setting['bulkbuy']] ?? $_rxOff;
    $statusverifybyuser = [
        'onverify' => $_rxOn,
        'offverify' => $_rxOff,
    ][$setting['verifybucodeuser']] ?? $_rxOff;
    $authScopeVal = (string)($setting['auth_scope'] ?? 'all');
    if ($authScopeVal === '') { $authScopeVal = 'all'; }
    $authScopeBtn = ($authScopeVal === 'newonly')
        ? "👥 محدوده احراز هویت: فقط کاربران جدید"
        : "👥 محدوده احراز هویت: همه کاربران";
    $score = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['scorestatus'] ?? '0')] ?? $_rxOff;
    $wheel_luck = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['wheelـluck'] ?? '0')] ?? $_rxOff;
    $refralstatus = [
        'onaffiliates' => $_rxOn,
        'offaffiliates' => $_rxOff,
    ][$setting['affiliatesstatus']] ?? $_rxOff;
    $btnstatuscategory = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['categoryhelp'] ?? '0')] ?? $_rxOff;
    $btnstatuslinkapp = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['linkappstatus'] ?? '0')] ?? $_rxOff;
    $cronteststatustext        = ($status_cron['test']           ?? false) ? $_rxOn : $_rxOff;
    $crondaystatustext         = ($status_cron['day']            ?? false) ? $_rxOn : $_rxOff;
    $cronvolumestatustext      = ($status_cron['volume']         ?? false) ? $_rxOn : $_rxOff;
    $cronremovestatustext      = ($status_cron['remove']         ?? false) ? $_rxOn : $_rxOff;
    $cronremovevolumestatustext= ($status_cron['remove_volume']  ?? false) ? $_rxOn : $_rxOff;
    $cronuptime_nodestatustext = ($status_cron['uptime_node']    ?? false) ? $_rxOn : $_rxOff;
    $cronuptime_panelstatustext= ($status_cron['uptime_panel']   ?? false) ? $_rxOn : $_rxOff;
    $cronon_holdtext           = ($status_cron['on_hold']        ?? false) ? $_rxOn : $_rxOff;
    $languagestatus = (((string)($setting['languageen'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $languagestatusru = (((string)($setting['languageru'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $wheelagent = (((string)($setting['wheelagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $Lotteryagent = (((string)($setting['Lotteryagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusfirstwheel = (((string)($setting['statusfirstwheel'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statuslimitchangeloc = (((string)($setting['statuslimitchangeloc'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusDebtsettlement = (((string)($setting['Debtsettlement'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusDice = (((string)($setting['Dice'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusnotef = (((string)($setting['statusnoteforf'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $status_copy_cart = (((string)($setting['statuscopycart'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $keyboard_config_text = (((string)($setting['status_keyboard_config'] ?? '0')) === '1') ? $_rxOn : $_rxOff;

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = ($infocardStatusValue === '1') ? $_rxOn : $_rxOff;
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $premiumEmojiStatusValue = (string)($setting['premium_emoji_status'] ?? '0');
    $premiumEmojiStatusText = ($premiumEmojiStatusValue === '1') ? $_rxOn : $_rxOff;

    $rxFeatTypeCatMap = [

        'statusbot' => 'bot', 'role' => 'bot',
        'get_number' => 'bot', 'Authenticationiran' => 'bot',
        'verifystart' => 'bot', 'verifybyuser' => 'bot', 'authscope' => 'bot',
        'inlinebtnmain' => 'bot',

        'usernamebtn' => 'users', 'notifnew' => 'users', 'showagent' => 'users',
        'statussupportpv' => 'users', 'statusnamecustom' => 'users', 'statusnamecustomf' => 'users',

        'bulkbuy' => 'shop', 'btn_status_category' => 'shop', 'keyconfig' => 'shop',
        'compycart' => 'shop', 'Debtsettlement' => 'shop', 'changeloc' => 'shop',
        'infocard' => 'shop', 'linkappstatus' => 'shop',

        'wheelagent' => 'lottery', 'wheelagentfirst' => 'lottery', 'wheel_luck' => 'lottery',
        'Lotteryagent' => 'lottery', 'score' => 'lottery', 'affiliatesstatus' => 'lottery',
        'Dice' => 'lottery',

        'crontest' => 'crons', 'uptime_node' => 'crons', 'uptime_panel' => 'crons',
        'cronday' => 'crons', 'on_hold' => 'crons', 'cronvolume' => 'crons',
        'notifremove' => 'crons', 'notifremove_volume' => 'crons',
    ];
    $rxFeatTargetCat = $rxFeatTypeCatMap[$type] ?? 'main';
    $rxPostTglPemCount = 0;
    try {
        $rxPostTglPemStmt = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis");
        if ($rxPostTglPemStmt) {
            $rxPostTglPemRow = $rxPostTglPemStmt->fetch(PDO::FETCH_ASSOC);
            $rxPostTglPemCount = (int)($rxPostTglPemRow['c'] ?? 0);
        }
    } catch (\Throwable $rxPostTglPemErr) { $rxPostTglPemCount = 0; }
    $rxPostTglPremiumLabel = "🌟 ایموجی پرمیوم" . ($rxPostTglPemCount > 0 ? " ({$rxPostTglPemCount})" : "");

    $rxFeatTitle = "📋 <b>وضعیت قابلیت‌ها</b>";
    $rxFeatBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main'], ['text' => "❌ بستن", 'callback_data' => 'close_stat']];

    if ($rxFeatTargetCat === 'bot') {
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>";
        $rxFeatRows = rx_featCategoryRows('bot');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'users') {
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>";
        $rxFeatRows = rx_featCategoryRows('users');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'shop') {
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>";
        $rxFeatRows = rx_featCategoryRows('shop');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'lottery') {
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>";
        $rxFeatRows = rx_featCategoryRows('lottery');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'crons') {
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>";
        $rxFeatRows = rx_featCategoryRows('crons');
        $rxFeatRows[] = $rxFeatBackRow;
    } else {

        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\n✅ تنظیم به‌روزرسانی شد.\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟";
        $rxFeatRows = [
            [['text' => "🤖 آپشن‌های اصلی ربات",   'callback_data' => "featcat_bot"]],
            [['text' => "👥 کاربران و پشتیبانی",   'callback_data' => "featcat_users"]],
            [['text' => "🛍 فروش و خدمات",         'callback_data' => "featcat_shop"]],
            [['text' => "🎁 گردونه و قرعه‌کشی",    'callback_data' => "featcat_lottery"]],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",   'callback_data' => "featcat_crons"]],
            [['text' => $rxPostTglPremiumLabel,    'callback_data' => "premium_emoji_settings"]],
            [['text' => "❌ بستن",                 'callback_data' => 'close_stat']],
        ];
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatRows]);
    Editmessagetext($from_id, $message_id, $rxFeatTitle, $Bot_Status);
} elseif ($text == "⚖️ متن قانون" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_roll'], $backadmin, 'HTML');
    step('text_roll', $from_id);
} elseif ($user['step'] == "text_roll") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_roll");
    step('home', $from_id);
} elseif ($text == "📣 گزارشات ربات" && $adminrulecheck['rule'] == "administrator") {
    $textreports = "📣در این بخش میتوانید آیدی عددی گروه را برای ارسال اعلان ارسال نمایید
آموزش تنظیم گروه :
1 - ابتدا یک گروه  بسازید
2 - ربات  @myidbot را عضو گروه کنید و دستور /getgroupid@myidbot داخل گروه ارسال کنید
3 - حالت تاپیک یا انجمن گروه را از تنظیمات گروه روشن کنید4
4 - ربات خودتان را ادمین گروه کنید
5 - آیدی عددی ارسال شده را در ربات ارسال کنید.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
    nm_adminInstantReply($from_id, $textreports, $backadmin, 'HTML');
    step('addchannelid', $from_id);
} elseif ($user['step'] == "addchannelid") {
    $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
    if (empty($outputcheck['ok'])) {
        $errorDescription = 'نامشخص';
        if (is_array($outputcheck) && isset($outputcheck['description'])) {
            $errorDescription = redfox_remote_error_summary($outputcheck);
        } elseif (is_string($outputcheck) && $outputcheck !== '') {
            $errorDescription = redfox_remote_error_summary($outputcheck);
        }
        $texterror = "❌ اتصال به گروه با موفقیت انجام نشد

خطای دریافتی :  {$errorDescription}";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($outputcheck['result']['chat']['is_forum'] == false) {
        $texterror = "❌ گروه انتخاب شده درحالت انجمن نیست ابتدا قابلیت تاپیک گروه را روشن کرده سپس آیدی عددی گروه را مجددا تنظیم نمایید";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🛍 گزارش های خرید"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($buyreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "buyreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "📌 گزارش خرید خدمات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherservice != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherservice");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🔑 گزارش اکانت تست"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($reporttest != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reporttest");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "⚙️ سایر گزارشات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "❌ گزارش خطا ها"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "errorreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "💰 گزارش مالی"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($paymentreports != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "paymentreport");
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $setting_panel, 'HTML');
    update("setting", "Channel_Report", $text);
    step('home', $from_id);
} elseif ($text == "🏬 تنظیمات فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 اضافه کردن محصول" && $adminrulecheck['rule'] == "administrator") {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
    step('get_limit', $from_id);
} elseif ($user['step'] == "get_limit") {
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name_product", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['setagentproduct'], rx_agentGroupKeyboard(false), 'HTML');
    step('get_agent', $from_id);
} elseif ($user['step'] == "get_agent") {
    $agent = ["n", "f", "n2"];
    $text = rx_resolveAgentGroup($text, $agent);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    $marzban_list[] = '/all';
    if (!in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی اشتباه است", null, 'HTML');
        return;
    }
    savedata("save", "Location", $text);
    if ($setting['statuscategorygenral'] == "oncategorys") {
        nm_adminInstantReply($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", KeyboardCategoryadmin(), 'HTML');
        step("getcategory", $from_id);
        return;
    }
    $panel = $text === '/all' ? null : select("marzban_panel", "*", "name_panel", $text, "select");
    if ($text !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "getcategory") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("save", "category", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Volume_constraint", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Service_time", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('gettimereset', $from_id);
} elseif ($user['step'] == "gettimereset") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price_product", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $panelType = is_array($panel) ? ($panel['type'] ?? '') : '';
    if ($panelType == "marzban" || $panelType == "marzneshin") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
        step('getnote', $from_id);
        return;
    }
    savedata("save", "data_limit_reset", "no_reset");
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید. این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "getnote") {
    savedata("save", "data_limit_reset", $text);
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید.این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    $varhide_panel = "{}";
    if (!isset($userdata['category']))
        $userdata['category'] = null;
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
    $stmt->bindParam(':name_product', $userdata['name_product']);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $userdata['price_product']);
    $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
    $stmt->bindParam(':Service_time', $userdata['Service_time']);
    $stmt->bindParam(':Location', $userdata['Location']);
    $stmt->bindParam(':agent', $userdata['agent']);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
    $stmt->bindParam(':note', $text, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "👨‍🔧 بخش ادمین" && $adminrulecheck['rule'] == "administrator") {
    $list_admin = select("admin", "*", null, null, "fetchAll");
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($list_admin as $admin) {
        $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
        if ($adminId === '') {
            continue;
        }
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
            ['text' => $adminId, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    nm_adminInstantReply($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($text == "⚙️ تنظیمات عمومی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "🤙 بخش پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ],
            [
                ['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']],
            ]
        ]
    ]);
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $countpay = $stmt->rowCount();
    $typepay = explode('|', $Payment_report['id_invoice']);
    if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
        nm_adminInstantReply($from_id, "⚠️ برای تأیید درخواست‌های کاربر، ابتدا رسیدهای خرید یا تمدید اشتراک را بررسی و تأیید کنید. سپس رسید شارژ کیف پول را تأیید کنید. ", null, 'HTML');
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی بعد از تایید : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }

    $confirmResult=payment_confirm_paid((string)$Payment_report['id_order'],'chashbackcart',[
        'method'=>'تایید دستی رسید توسط ادمین '.$from_id,'expected_method'=>['cart to cart','arze digital offline'],'thread_id'=>$paymentreports,
    ]);
    if(empty($confirmResult['ok'])){
        telegram('answerCallbackQuery',['callback_query_id'=>$callback_query_id,'text'=>'پرداخت در حال پردازش یا نیازمند تطبیق است','show_alert'=>true]);
        return;
    }
    $Balance_id=select("user","*","id",$Payment_report['id_user'],"select");
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 یک ادمین رسید پرداخت  را تایید کرد.

اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
💰 مبلغ پرداخت : {$Payment_report['price']}
👤 ایدی عددی کاربر : <code>{$Payment_report['id_user']}</code>
👤 نام کاربری کاربر : @{$Balance_id['username']}
        کد پیگیری پرداحت : $order_id";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    nm_adminInstantReply($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ یک ادمین رسید پرداخت را رد کرد.

اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
نام کاربری ادمین تایید کننده : @$username
💰 مبلغ پرداخت : {$Payment_report['price']}
دلیل رد کردن : $text
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^confirmcryptomanual_(\w+)$/', (string) $datain, $cmConf) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmConf[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] == 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این فاکتور قبلاً تایید شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $cmAutoIrr = (int) ($cmPayment['price'] ?? 0);
    $cmCoinAmtPick = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmtPick = rtrim(rtrim(number_format((float) $cmCoinAmtPick, 9, '.', ''), '0'), '.');
    $cmPickMsg = "🟢 <b>تایید درخواست بررسی دستی</b>\n\n"
               . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
               . "👤 کاربر: <code>{$cmPayment['id_user']}</code>\n"
               . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
               . "🪙 مقدار: <code>{$cmCoinAmtPick}</code>\n"
               . "💵 معادل خودکار: <b>" . number_format($cmAutoIrr) . " تومان</b>\n\n"
               . "👇 روش تایید را انتخاب کنید:";
    $cmPickKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⚡ تایید با همان مبلغ خودکار', 'callback_data' => 'cmauto_' . $cmOrderId]],
            [['text' => '✏️ ویرایش مبلغ و تایید',       'callback_data' => 'cmmanual_' . $cmOrderId]],
            [['text' => '🔙 بازگشت',                     'callback_data' => 'cmback_' . $cmOrderId]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $cmPickMsg, $cmPickKb);
    } else {
        sendmessage($from_id, $cmPickMsg, $cmPickKb, 'HTML');
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif (preg_match('/^cmauto_(\w+)$/', (string) $datain, $cmAuto) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmAuto[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
        return;
    }
    $cmFinalIrr = (int) ($cmPayment['price'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $message_id, $callback_query_id, $text_inline ?? '');
} elseif (preg_match('/^cmmanual_(\w+)$/', (string) $datain, $cmMan) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmMan[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    $cmCoinAmt = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmt = rtrim(rtrim(number_format((float) $cmCoinAmt, 9, '.', ''), '0'), '.');
    $cmAutoIrrShow = (int) ($cmPayment['price'] ?? 0);
    nm_adminInstantReply(
        $from_id,
        "✏️ <b>وارد کردن مبلغ تومانی دستی</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
        . "🪙 مقدار: <code>{$cmCoinAmt}</code>\n"
        . "💵 مبلغ خودکار: <b>" . number_format($cmAutoIrrShow) . " تومان</b>\n\n"
        . "💰 مبلغ تومانی نهایی را وارد کنید (فقط عدد):",
        $backadmin,
        'HTML'
    );
    step('cm_manual_irr_input', $from_id);
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif ($user['step'] == "cm_manual_irr_input" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    if ($cmOrderId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. دوباره تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmIrrRaw = trim(str_replace([',', '،'], ['', ''], (string) $text));
    if (!ctype_digit($cmIrrRaw) || (int) $cmIrrRaw <= 0) {
        nm_adminInstantReply($from_id, "❌ مبلغ باید عدد صحیح مثبت باشد. مثال: <code>50000</code>", null, 'HTML');
        return;
    }
    $cmFinalIrr = (int) $cmIrrRaw;
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        nm_adminInstantReply($from_id, "❌ تراکنش یافت نشد.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        nm_adminInstantReply($from_id, "❌ این فاکتور قبلاً تایید شده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "price", (string) $cmFinalIrr, "id_order", $cmOrderId);
    $cmPayment['price'] = (string) $cmFinalIrr;
    $cmPrevMsgId = (int) ($user['Processing_value_tow'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $cmPrevMsgId, null, '');
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ تایید شد با مبلغ " . number_format($cmFinalIrr) . " تومان.", $keyboardadmin, 'HTML');
} elseif (preg_match('/^cmback_(\w+)$/', (string) $datain, $cmBack) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmBack[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'تراکنش یافت نشد', 'show_alert' => true]);
        return;
    }
    $cmKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ کیف پول', 'callback_data' => 'confirmcryptomanual_' . $cmOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $cmOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        telegram('editMessageReplyMarkup', [
            'chat_id'      => $from_id,
            'message_id'   => $message_id,
            'reply_markup' => $cmKb,
        ]);
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif (preg_match('/^rejectcryptomanual_(\w+)$/', (string) $datain, $cmRej) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmRej[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این فاکتور قبلاً تایید شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("user", "Processing_value", $cmPayment['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    nm_adminInstantReply($from_id, "✍️ دلیل رد کردن این درخواست را وارد کنید:", $backadmin, 'HTML');
    step('reject_crypto_manual_reason', $from_id);
} elseif ($user['step'] == "reject_crypto_manual_reason" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    $cmUserId = (string) ($user['Processing_value'] ?? '');
    if ($cmOrderId === '' || $cmUserId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. مجدداً تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmReason = trim((string) $text);
    if ($cmReason === '' || mb_strlen($cmReason) > 500) {
        nm_adminInstantReply($from_id, "❌ دلیل معتبر نیست. حداکثر ۵۰۰ کاراکتر.", null, 'HTML');
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $cmOrderId);
    update("Payment_report", "dec_not_confirmed", $cmReason, "id_order", $cmOrderId);
    update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $cmOrderId);

    sendmessage(
        $cmUserId,
        "❌ <b>درخواست بررسی دستی پرداخت کریپتوی شما رد شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n\n"
        . "در صورت اعتراض با پشتیبانی در ارتباط باشید.",
        null,
        'HTML'
    );
    nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد شد.", $keyboardadmin, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => "❌ بررسی دستی پرداخت کریپتو رد شد\n\n"
                . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                . "👤 کاربر: <code>{$cmUserId}</code>\n"
                . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n"
                . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
            'parse_mode' => 'HTML',
        ]);
    }
} elseif (preg_match('/^cmdelete_(\w+)$/', (string) $datain, $cmDel) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmdOrderId = $cmDel[1];
    $cmdRow = select("Payment_report", "*", "id_order", $cmdOrderId, "select");
    if (!$cmdRow) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد یا قبلاً حذف شده',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if (($cmdRow['payment_Status'] ?? '') === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این تراکنش قبلاً تایید شده — قابل حذف نیست.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $cmdUserId = (string) ($cmdRow['id_user'] ?? '');
    try {
        $cmdDel = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :o");
        $cmdDel->execute([':o' => $cmdOrderId]);
    } catch (Throwable $e) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'خطا در حذف. لاگ را بررسی کنید.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        error_log('[cmdelete] failed: ' . redfox_exception_fingerprint($e));
        return;
    }
    if ($cmdUserId !== '' && function_exists('sendmessage')) {
        @sendmessage(
            $cmdUserId,
            "🗑️ <b>درخواست بررسی دستی شما لغو و حذف شد</b>\n\n"
            . "🛒 کد فاکتور: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n\n"
            . "اگر سوال دارید، با پشتیبانی در ارتباط باشید.",
            null,
            'HTML'
        );
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => '✅ حذف شد',
        'cache_time' => 1,
    ]);
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        $cmdOriginal = (string) ($update['callback_query']['message']['text'] ?? '');
        $cmdDoneNote = "\n\n━━━━━━━━━━━━\n🗑️ <b>لغو و حذف شده</b>\n"
            . "👨‍💼 توسط ادمین: <code>" . htmlspecialchars((string) $from_id) . "</code>\n"
            . "⏰ " . date('Y/m/d H:i:s');
        @Editmessagetext($from_id, $message_id, $cmdOriginal . $cmdDoneNote, null);
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0 && (string) $setting['Channel_Report'] !== (string) $from_id) {
        @telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => "🗑️ <b>بررسی دستی پرداخت کریپتو لغو و حذف شد</b>\n\n"
                . "🛒 کد پیگیری: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n"
                . "👤 کاربر: <code>" . htmlspecialchars($cmdUserId) . "</code>\n"
                . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
            'parse_mode' => 'HTML',
        ]);
    }
} elseif ($text == "❌ حذف محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    update("user", "Processing_value", $text, "id", $from_id);
    step('remove-product', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (Location= :Location or Location= '/all')");
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
} elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
    $location = $dataget[1];
    $location = $location == "all" ? "/all" : $location;
    update("user", "Processing_value_one", $location, "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "کاربر عادی", 'callback_data' => 'typeagenteditproduct_f'],
            ],
            [
                ['text' => "نماینده پیشرفته", 'callback_data' => 'typeagenteditproduct_n2'],
                ['text' => "نماینده عادی", 'callback_data' => 'typeagenteditproduct_n'],
            ],
            [
                ['text' => "بازگشت", 'callback_data' => "admin"]
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 نوع کاربری را انتخاب کنید", $Response);
} elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    update("user", "Processing_value_tow", $typeagent, "id", $from_id);
    $product = [];
    $escapedText = mysqli_real_escape_string($connect, $user['Processing_value_one']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc = $panel['name_panel'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent = ?");
    $_stmt->bind_param("ss", $_loc, $typeagent);
    $_stmt->execute();
    $getdataproduct = $_stmt->get_result();
    $_stmt->close();
    $list_product = [
        'inline_keyboard' => [],
    ];
    if (isset($getdataproduct)) {
        while ($row = mysqli_fetch_assoc($getdataproduct)) {
            $list_product['inline_keyboard'][] = [
                ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']]
            ];
        }
        $list_product['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به منوی قبل", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
        ];

        $json_list_product_list_admin = json_encode($list_product);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
} elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
    $id_product = $dataget[1];
    deletemessage($from_id, $message_id);
    update("user", "Processing_value", $id_product, "id", $from_id);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc2 = $panel['name_panel']; $_pv2 = $user['Processing_value_tow'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE id = ? AND agent = ? AND (Location = ? OR Location = '/all') LIMIT 1");
    $_stmt->bind_param("sss", $id_product, $_pv2, $_loc2);
    $_stmt->execute();
    $info_product = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $count_invoice = select("invoice", "*", "name_product", $info_product['name_product'], "count");
    $infoproduct = "
📌 اطلاعات محصول در حال ویرایش:
نام محصول :  {$info_product['name_product']}
قیمت محصول : {$info_product['price_product']}
حجم محصول : {$info_product['Volume_constraint']}
موقعیت محصول : {$info_product['Location']}
زمان محصول : {$info_product['Service_time']}
نوع کاربری محصول : {$info_product['agent']}
ریست دوره ای حجم محصول : {$info_product['data_limit_reset']}
یادداشت محصول : {$info_product['note']}
دسته بندی محصول : {$info_product['category']}
تعداد محصول فروخته شده : $count_invoice عدد
    ";
    nm_adminInstantReply($from_id, $infoproduct, $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "قیمت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "قیمت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':price_product', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ قیمت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "یادداشت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "یادداشت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_note', $from_id);
} elseif ($user['step'] == "change_note") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':notes', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ یادداشت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام دسته بندی جدید را انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step('change_categroy', $from_id);
} elseif ($user['step'] == "change_categroy") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی ُ دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':categroy', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ دسته بندی محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نام محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':name_products', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع کاربری جدید را ارسال کنید :
نوع کاربری ها :f , n , n2", $backadmin, 'HTML');
    step('change_type_agent', $from_id);
} elseif ($user['step'] == "change_type_agent") {
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر می باشد", null, 'HTML');
        return;
    }
    $text = $grp;
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':agents', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع ریست حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع ریست حجم را ارسال کنید", $keyboardtimereset, 'HTML');
    step('change_reset_data', $from_id);
} elseif ($user['step'] == "change_reset_data") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':data_limit_reset', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "موقعیت محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 موقعیت جدید محصول را انتخاب کنید", $json_list_marzban_panel, 'HTML');
    step('change_loc_data', $from_id);
} elseif ($user['step'] == "change_loc_data") {
    if ($text == "/all") {
        nm_adminInstantReply($from_id, "❌ نمی توانید محصول تعریف شده را به نام موقعیت /all تغییر دهید.", $shopkeyboard, 'HTML');
        return;
    }
    $product = select("product", "*", "name_product", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Location2', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
    $stmt->bindParam(':Service_location', $text);
    $stmt->bindParam(':name_product', $product['name_product']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅موقعیت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $product = select("product", "*", "id", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Volume_constraint', $text);
    $stmt->bindParam(':name_product', $product['id']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "زمان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Service_time', $text);
    $stmt->bindParam(':id_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "balanceaddall") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($user['step'] == "add_Balance_all") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    savedata("clear", "price", $text);
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typebalanceall_all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typebalanceall_f'],
                ['text' => "کاربران گروه n", 'callback_data' => 'typebalanceall_nl'],
                ['text' => "کاربران گروه n2", 'callback_data' => 'typebalanceall_n2'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 شارژ برای کدام یک از گروه کاربری زیر واریز شود.", $keyboardagent, 'HTML');
} elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    savedata("save", "agent", $typeagent);
    $keyboardtypeuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typecustomer_all'],
            ],
            [
                ['text' => "کاربرانی که خرید داشتند", 'callback_data' => 'typecustomer_customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typecustomer_notcustomer'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 چه کاربر شارژ همگانی ارسال شود", $keyboardtypeuser);
} elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
    $typecustomer = $dataget[1];
    savedata("save", "typecustomer", $typecustomer);
    nm_adminInstantReply($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟
بله : 1
خیر : 0", $backadmin, 'HTML');
    step("getmeesagestatus", $from_id);
} elseif ($user['step'] == "getmeesagestatus") {
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'], $keyboardadmin, 'HTML');
    $query_where = "";
    if ($userdata['agent'] == "all") {
        if ($userdata['typecustomer'] == "all") {
            $query_where = "";
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = "WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = "WHERE  NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    } else {
        if ($userdata['typecustomer'] == "all") {
            $query_where = null;
            ;
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    }
    $stmt = $pdo->prepare("SELECT u.id FROM user u " . $query_where);
    $stmt->execute();
    $Balance_user = $stmt->fetchAll();
    $stmt = $pdo->prepare("UPDATE user as u SET  Balance = Balance + {$userdata['price']} " . $query_where);
    $stmt->execute();
    step('home', $from_id);
    if ($text == "1") {
        $cancelmessage = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                ],
            ]
        ]);
        $textgift = "🎁 کاربر  عزیز مبلغ {$userdata['price']} تومان از طرف مدیریت به عنوان هدیه به کیف پول شما واریز گردید.";
        $message_id = sendmessage($from_id, "✅ عملیات ارسال پیام آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage, "html");
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $textgift,
            "pingmessage" => "no",
            "btnmessage" => "start"
        ));
        file_put_contents("cronbot/users.json", json_encode($Balance_user));
        file_put_contents('cronbot/info', $data);
    }
} elseif ($text == "⬇️ کم کردن موجودی") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
    step('Negative_Balance', $from_id);
} elseif ($user['step'] == "Negative_Balance") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_Negative") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) >= 100000000) {
        nm_adminInstantReply($from_id, "📌 حداکثر مقدار 100 میلیون ریال است.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');

    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $balances1 = number_format($text, 0);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $textkam = "❌ کاربر عزیز مبلغ $balances1 تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchuser") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    if ($user['step'] == "show_info") {
        $id_user = $text;
    } elseif (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $dataget[1];
    }
    if (!userExists($id_user)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $_stmt = $connect->prepare("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $dayListSell = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = ? AND Payment_Method != 'low balance by admin'");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $balanceall = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $subbuyuser = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $invoicecount = select("invoice", '*', "id_user", $id_user, "count");
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $_stmt = $connect->prepare("SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND name_product != 'سرویس تست'");
        $_stmt->bind_param("s", $id_user); $_stmt->execute();
        $sumvolume = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $keyboardmanage = [
        'inline_keyboard' => [
            [['text' => "♻️  بروزرسانی اطلاعات", 'callback_data' => "updateinfouser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
            [['text' => "🎁 درصد تخفیف", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ ارسال پیام به کاربر", 'callback_data' => "sendmessageuser_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
            [['text' => "👥 زیرمجموعه های کاربر", 'callback_data' => "affiliates-" . $id_user]],
            [['text' => "🔄 خارج کردن از زیرمجموعه", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 حذف زیرمجموعه های کاربر", 'callback_data' => "removeaffiliateuser-" . $id_user]],
            [['text' => "💳 فعالسازی شماره کارت", 'callback_data' => "showcarduser-" . $id_user]],
            [['text' => "احراز هویت کاربر", 'callback_data' => "verify_" . $id_user], ['text' => "عدم احراز کاربر", 'callback_data' => "unverify-" . $id_user]],
            [['text' => "💳  غیرفعالسازی شماره کارت", 'callback_data' => "carduserhide-" . $id_user]],
            [['text' => "🛒 افزودن سفارش", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ محدودیت اکانت تست", 'callback_data' => "limitusertest_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "انتقال حساب کاربری ", 'callback_data' => "transferaccount_" . $id_user]],
            [['text' => "💡 خاموش کردن اکانت", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 روشن کردن اکانت", 'callback_data' => "activeconfig-" . $id_user]],
            [['text' => "📑 احراز عضویت کانال", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ صفر کردن موجودی", 'callback_data' => "zerobalance-" . $id_user]],
            [['text' => "🕚 وضعیت ارسال پیام های کرون", 'callback_data' => "statuscronuser-" . $id_user]],
        ]
    ];
    if ($user['agent'] == "n2")
        $keyboardmanage['inline_keyboard'][] = [['text' => "سقف خرید  نماینده", 'callback_data' => "maxbuyagent_" . $id_user]];
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🤖 فعالسازی ربات فروش", 'callback_data' => "createbot_" . $id_user],
            ['text' => "❌ حذف ربات فروش", 'callback_data' => "removebotsell_" . $id_user]
        ];
    }
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🔋 قیمت پایه حجم", 'callback_data' => "setvolumesrc_" . $id_user],
            ['text' => "⏳ قیمت پایه زمان", 'callback_data' => "settimepricesrc_" . $id_user]
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "❌ مخفی کردن یک پنل برای نماینده", 'callback_data' => "hidepanel_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🗑 نمایش پنل های مخفی شده", 'callback_data' => "removehide_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => "expireset_" . $id_user],
        ];
    }
    if (intval($setting['statuslimitchangeloc']) == 1) {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "محدودیت تغییر لوکیشن", 'callback_data' => "changeloclimitbyuser_" . $id_user]
        ];
    }
    $keyboardmanage['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    $userverify = [
        '0' => "احراز نشده",
        '1' => "احراز شده"
    ][$user['verify']];
    $showcart = [
        '0' => "مخفی",
        '1' => "نمایش داده می شود"
    ][$user['cardpayment']];
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    if ($user['agent'] != "f" && $user['expire'] != null) {
        $text_expie_agent = "⭕️ تاریخ پایان نمایندگی : " . jdate('Y/m/d H:i:s', $user['expire']);
    } else {
        $text_expie_agent = "";
    }
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$user['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$user['number']}</code>
⭕️ نوع کاربری : {$user['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$user['affiliatescount']}
⭕  معرف کاربر : {$user['affiliates']}
⭕  وضعیت احراز هویت: $userverify
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$user['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$user['Balance']}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 درصد تخفیف کاربر : {$user['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان

";
    if (is_string($datain) && isset($datain[0]) && $datain[0] == "u") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "اطلاعات بروزرسانی گردید",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } else {
        nm_adminInstantReply($from_id, $textinfouser, $keyboardmanage, 'HTML');
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "get_code") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO Discount (code, limitused) VALUES (:code, :limitused)");
    $value = "0";
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->bindParam(':limitused', $value, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['PriceCode'], null, 'HTML');
    step('get_price_code', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_code") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['setlimituse'], $backadmin, 'HTML');
    update("Discount", "price", $text, "code", $user['Processing_value']);
    step('getlimitcodedis', $from_id);
} elseif ($user['step'] == "getlimitcodedis") {
    step("home", $from_id);
    update("Discount", "limituse", $text, "code", $user['Processing_value']);

    $giftRow = select("Discount", "*", "code", $user['Processing_value'], "select");
    $textgift = "🎁 کد هدیه شما با موفقیت ساخته شد.

📩 نام کد هدیه: <code>{$giftRow['code']}</code>
💰 مبلغ کد هدیه: {$giftRow['price']} تومان
🔴 محدودیت استفاده: {$giftRow['limituse']}";
    nm_adminInstantReply($from_id, $textgift, $keyboardadmin, 'HTML');
} elseif ($text == "❌ حذف کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    if (!in_array($text, $code_Discount)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "🗑 حذف پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
    step('removeprotocol', $from_id);
} elseif ($user['step'] == "removeprotocol") {
    if (!in_array($text, $protocoldata)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
    $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
    $stmt->execute();
    step('home', $from_id);
} elseif ($text == "💡 روش ساخت نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    $text_username = "⭕️ روش ساخت نام کاربری برای اکانت ها را از دکمه زیر انتخاب نمایید.

⚠️ در صورتی که کاربری نام کاربری نداشته باشه کلمه انتخابی توسط شما ثبت خواهد شد جای نام کاربری اعمال خواهد شد.

⚠️ در صورتی که نام کاربری وجود داشته باشه یک عدد رندوم به نام کاربری اضافه خواهد شد";
    nm_adminInstantReply($from_id, $text_username, $MethodUsername, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $allowedMethods = [
        "آیدی عددی + حروف و عدد رندوم",
        "نام کاربری + حروف و عدد رندوم",
        "نام کاربری دلخواه + عدد رندوم",
        "متن دلخواه + عدد رندوم",
        "متن دلخواه + عدد ترتیبی",
        "نام کاربری + عدد به ترتیب",
        "آیدی عددی+عدد ترتیبی",
        "متن دلخواه نماینده + عدد ترتیبی",
        "نام کاربری دلخواه",
    ];
    if (!in_array($text, $allowedMethods, true)) {
        nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است. لطفاً یکی از دکمه‌های زیر را انتخاب کنید.", $MethodUsername, 'HTML');
        return;
    }
    update("marzban_panel", "MethodUsername", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $backadmin, 'HTML');
        return;
    }
    if ($text == "نام کاربری + عدد به ترتیب") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, "📌 در صورتی که کاربر نام کاربری نداشت چه اسمی ثبت شود؟", $backadmin, 'HTML');
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
    step('home', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!preg_match('/^\w{3,32}$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "namecustom", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    step('home', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
} elseif (($datain == "cartsetting" || $text == "▶️ بازگشت به منوی تظنیمات کارت") && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cart_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
} elseif ($text == "💳 تنظیم شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "💳 شماره کارت خود را ارسال کنید

⚠️ توجه داشته باشید شما می توانید چندین شماره کارت تعریف کنید در صورت تعریف چندین شماره کارت به کاربر یک شماره کارت از بین شماره کارت ها رندوم نشان خواهد داد";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif ($user['step'] == "changecard") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌شماره کارت باید حتما عدد باشد.", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $listcard)) {
        nm_adminInstantReply($from_id, "❌ شماره کارت در دیتابیس وجود دارد.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['getnamecard'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('getnamecard', $from_id);
} elseif ($user['step'] == "getnamecard") {
    try {
        if (function_exists('ensureCardNumberTableSupportsUnicode')) {
            ensureCardNumberTableSupportsUnicode();
        }

        $stmt = $connect->prepare("INSERT INTO card_number (cardnumber,namecard) VALUES (?,?)");
        $stmt->bind_param("ss", $user['Processing_value'], $text);
        $stmt->execute();
        $stmt->close();
        nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['Savacard'], $CartManage, 'HTML');
        step('home', $from_id);
    } catch (\mysqli_sql_exception $e) {
        error_log('Failed to save card number: ' . redfox_exception_fingerprint($e));
        if ((int)$e->getCode() === 1366) {
            error_log('card_number insert failed due to charset mismatch. Please verify the table collation.');
        }
        nm_adminInstantReply($from_id, "❌ ثبت شماره کارت ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.", $backadmin, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
} elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_plisio");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $rowLegacy = select("PaySetting", "ValuePay", "NamePay", "apinowpayment");
        $PaySetting = is_array($rowLegacy) ? (string)($rowLegacy['ValuePay'] ?? '') : '';
    }
    $textcart = "⚙️ api سایت plisio.net.io را ارسال نمایید

        api plisio :$PaySetting";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('api_plisio', $from_id);
} elseif ($user['step'] == "api_plisio" || $user['step'] == "apinowpayment") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "api_plisio");
    step('home', $from_id);
} elseif ($datain == "iranpay1setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Swapinokey, 'HTML');
} elseif ($text == "API NOWPAYMENT") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller");
        $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    }
    $safeKey = htmlspecialchars($PaySetting, ENT_QUOTES, 'UTF-8');
    $displayKey = ($PaySetting !== '' && $PaySetting !== '0') ? "<code>$safeKey</code>" : '— تنظیم نشده';
    $texttronseller = "💳 API NowPayments خود را از داشبورد nowpayments.io دریافت و در این قسمت وارد کنید.\n\n"
                    . "🔑 کلید فعلی:\n$displayKey\n\n"
                    . "ℹ️ طول کلید فعلی: " . strlen($PaySetting) . " کاراکتر";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('marchent_tronseller', $from_id);
} elseif ($user['step'] == "marchent_tronseller") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');

    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
    update("PaySetting", "ValuePay", $text, "NamePay", "api_nowpayment");
    step('home', $from_id);
} elseif ($text == "🔐 IPN Secret nowpayment" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "nowpayment_ipn_secret");
    $currentSecret = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    $masked = $currentSecret !== '' ? substr($currentSecret, 0, 4) . str_repeat('*', max(0, strlen($currentSecret) - 8)) . substr($currentSecret, -4) : '— تنظیم نشده';
    $textIpn = "🔐 IPN Secret درگاه NowPayments را از داشبورد NowPayments → Store Settings → IPN Secret دریافت و در این قسمت وارد کنید\n\n"
             . "🔑 مقدار فعلی : <code>$masked</code>\n\n"
             . "⚠️ این مقدار برای اعتبارسنجی امضای IPN استفاده می‌شود و باید با مقدار داخل داشبورد NowPayments دقیقاً یکی باشد.";
    nm_adminInstantReply($from_id, $textIpn, $backadmin, 'HTML');
    step('nowpayment_ipn_secret', $from_id);
} elseif ($user['step'] == "nowpayment_ipn_secret") {
    update("PaySetting", "ValuePay", trim($text), "NamePay", "nowpayment_ipn_secret");
    nm_adminInstantReply($from_id, "✅ IPN Secret با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($datain == "zarinpeysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpey, 'HTML');
} elseif ($datain == "aqayepardakhtsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $aqayepardakht, 'HTML');
} elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpal, 'HTML');
} elseif ($text == "تنظیم مرچنت آقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را ازآقای پرداخت دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_id_aqayepardakht', $from_id);
} elseif ($user['step'] == "merchant_id_aqayepardakht") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $aqayepardakht, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_id_aqayepardakht");
    step('home', $from_id);
} elseif ($text == "مرچنت زرین پال" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را از زرین پال دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_zarinpal', $from_id);
} elseif ($user['step'] == "merchant_zarinpal") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
    step('home', $from_id);
} elseif ($text == "🗂 نام درگاه زرین پی") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpey", $from_id);
} elseif ($user['step'] == "gettextzarinpey") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpey, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpey");
    step("home", $from_id);
} elseif ($text == "🔑 توکن زرین پی" && $adminrulecheck['rule'] == "administrator") {
    $token = getPaySettingValue('token_zarinpey', '0');
    $message = "🔑 توکن دسترسی زرین پی خود را ارسال کنید.\n\nتوکن فعلی شما: {$token}";
    nm_adminInstantReply($from_id, $message, $backadmin, 'HTML');
    step('token_zarinpey', $from_id);
} elseif ($user['step'] == "token_zarinpey") {
    update("PaySetting", "ValuePay", $text, "NamePay", "token_zarinpey");
    nm_adminInstantReply($from_id, "✅ توکن با موفقیت ذخیره شد.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰 کش بک زرین پی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpey", $from_id);
} elseif ($user['step'] == "getcashzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpey");
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "🧑🏼‍💻 اموزش اتصال") {
    $inlineKeyboard = json_encode([
        'inline_keyboard' => [
            [
                [
                    'text' => '📞 دریافت API  مشاوره',
                    'url' => 'https://t.me/MiladRajabi2002',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $message = "🚀 درگاه کارت‌به‌کارت خودکار\n\nدرگاه هوشمند ZarinPay اکنون در رد فاکس بات نسخه پرو فعال است!\nتراکنش‌ها با خواندن پیامک بانکی به‌صورت خودکار و لحظه‌ای تأیید می‌شوند ⚡\nبدون نیاز به تأیید دستی، سریع، دقیق و ایمن 💳";

    nm_adminInstantReply($from_id, $message, $inlineKeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ زرین پی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainzarinpey", $from_id);
} elseif ($user['step'] == "getmainzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpey");
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬆️ حداکثر مبلغ زرین پی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpey", $from_id);
} elseif ($user['step'] == "getmaaxzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpey");
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش زرین پی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .\n۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید\n۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpey", $from_id);
} elseif ($user['step'] == "helpzarinpey") {
    if ($text) {
        if ((int) $text === 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpzarinpey");
        } else {
            $data = json_encode([
                'type' => 'text',
                'text' => $text,
            ], JSON_UNESCAPED_UNICODE);
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
        }
    } elseif ($photo) {
        $data = json_encode([
            'type' => 'photo',
            'text' => $caption,
            'photoid' => $photoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } elseif ($video) {
        $data = json_encode([
            'type' => 'video',
            'text' => $caption,
            'videoid' => $videoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
    update("user", "Processing_value", "0", "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
    step('GetLocationEdit', $from_id);
} elseif ($user['step'] == "GetLocationEdit") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!is_array($marzban_list_get) || empty($marzban_list_get)) {
        $notFoundMessage = $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.";
        nm_adminInstantReply($from_id, $notFoundMessage, $json_list_marzban_panel, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('PanelMenu', $from_id);
    if ($marzban_list_get['type'] == "marzban") {
        $Check_token = token_panel($marzban_list_get['code_panel'], false);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Stats($text);
            if ((string)($marzban_list_get['version_panel'] ?? '0') === '1') {
                $active_users = $System_Stats['active_users']
                    ?? $System_Stats['users_active']
                    ?? $System_Stats['online_users']
                    ?? 0;
            } else {
                $active_users = $System_Stats['users_active']
                    ?? $System_Stats['active_users']
                    ?? $System_Stats['online_users']
                    ?? 0;
            }
            $total_user = $System_Stats['total_user'];
            $mem_total = formatBytes($System_Stats['mem_total']);
            $mem_used = formatBytes($System_Stats['mem_used']);
            $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
            $rx_listsell_count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSell = number_format((int)($rx_listsell_count['COUNT(*)'] ?? 0));
            $rx_listsell_sum = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSellSUM = number_format((int)($rx_listsell_sum['SUM(price_product)'] ?? 0));

            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
📡 نسخه پنل مرزبان :  {$System_Stats['version']}
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل مرزبان  : $mem_used
🌐 ترافیک کل مصرف شده  ( آپلود / دانلود) : $bandwidth
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel']
                . PHP_EOL . 'کد تشخیصی: ' . redfox_remote_error_summary($Check_token);
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "guard") {
        $guardConfig = getGuardPanelConfig($marzban_list_get['name_panel']);
        if ($guardConfig['status'] === false) {
            $errorMsg = $guardConfig['msg'] ?? $textbotlang['Admin']['managepanel']['guard']['connection_error'];
            nm_adminInstantReply($from_id, "❌ {$errorMsg}", $optionGuard, 'HTML');
        } else {
            $testResult = guardTestConnection($guardConfig['panel']['url_panel'], $guardConfig['api_key']);
            $statusText = guardFormatConnectionResult($testResult);
            $text_marzban = "{$statusText}\nگروه کاربری :{$marzban_list_get['agent']}"
                . "\n\n⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionGuard, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . 'کد خطا: ' . redfox_remote_error_summary($x_ui_check_connect);
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "alireza_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionalireza_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "The username or password is incorrect") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionalireza_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . 'کد خطا: ' . redfox_remote_error_summary($x_ui_check_connect);
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionalireza_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "hiddify") {
        $System_Stats = serverstatus($marzban_list_get['name_panel']);
        if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
            $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
            nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } elseif (!empty($System_Stats['error'])) {
            $text_marzban = '❌ خطایی در دریافت اطلاعات رخ داد. کد: ' . redfox_remote_error_summary($System_Stats);
            nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } else {
            $System_Stats = json_decode($System_Stats['body'], true);
            if (isset($System_Stats['stats'])) {
                $mem_total = round($System_Stats['stats']['system']['ram_total'], 2);
                $mem_used = round($System_Stats['stats']['system']['ram_used'], 2);
                $outgoingBandwidth = 0;
                $incomingBandwidth = 0;

                if (isset($System_Stats['outgoing_bandwidth']) || isset($System_Stats['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['incoming_bandwidth'] ?? 0);
                } elseif (isset($System_Stats['stats']['outgoing_bandwidth']) || isset($System_Stats['stats']['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['stats']['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['stats']['incoming_bandwidth'] ?? 0);
                }

                $bandwidth = formatBytes($outgoingBandwidth + $incomingBandwidth);
                $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل : ✅ پنل متصل است
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل   : $mem_used
گروه کاربری :{$marzban_list_get['agent']}
⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } elseif (isset($System_Stats['message']) && $System_Stats['message'] == "Unathorized") {
                $text_marzban = "❌  لینک پنل اشتباه ارسال شده است";
                nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } else {
                nm_adminInstantReply($from_id, "پنل متصل نیست", $optionhiddfy, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "Manualsale") {
        nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionManualsale, 'HTML');
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $Check_token = token_panelm($marzban_list_get['code_panel']);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Statsm($text);
            if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
                $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
                nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            } elseif (!empty($System_Stats['error'])) {
                $text_marzban = '❌ خطایی در دریافت اطلاعات رخ داد. کد: ' . redfox_remote_error_summary($System_Stats);
                nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            }
            $System_Stats = json_decode($System_Stats['body'], true);
            $active_users = $System_Stats['active'];
            $total_user = $System_Stats['total'];
            $rx_listsell_count2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSell = number_format((int)($rx_listsell_count2['COUNT(*)'] ?? 0));
            $rx_listsell_sum2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSellSUM = number_format((int)($rx_listsell_sum2['SUM(price_product)'] ?? 0));
            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionmarzneshin, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel']
                . PHP_EOL . 'کد تشخیصی: ' . redfox_remote_error_summary($Check_token);
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionwg, 'HTML');
    } elseif ($marzban_list_get['type'] == "s_ui") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $options_ui, 'HTML');
    } elseif ($marzban_list_get['type'] == "ibsng") {
        $result = loginIBsng($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (is_array($result) && !empty($result['status'])) {
            nm_adminInstantReply($from_id, '✅ اتصال IBSng برقرار است.', $optionibsng, 'HTML');
        } else {
            nm_adminInstantReply($from_id, '❌ اتصال IBSng ناموفق بود. کد: ' . redfox_remote_error_summary($result), $optionibsng, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $result = login_mikrotik($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($result['error'])) {
            nm_adminInstantReply($from_id, '❌ اتصال میکروتیک ناموفق بود. کد: ' . redfox_remote_error_summary($result), $option_mikrotik, 'HTML');
        } else {
            $free_hdd_space = round($result['free-hdd-space'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $total_hdd_space = round($result['total-hdd-space'] / pow(1024, 3), 2);
            $total_memory = round($result['total-memory'] / pow(1024, 3), 2);
            nm_adminInstantReply($from_id, "<b>📡 اطلاعات سیستم MikroTik شما:</b>

<blockquote>
🖥 <b>پلتفرم:</b> {$result['platform']}
🏷 <b>نسخه:</b> {$result['version']}
🕰 <b>مدت زمان روشن بودن:</b> {$result['uptime']}
</blockquote>

<blockquote>
💽 <b>نام معماری:</b> {$result['architecture-name']}
📋 <b>مدل برد:</b> {$result['board-name']}
🏗 <b>زمان ساخت سیستم:</b> {$result['build-time']}
</blockquote>

<blockquote>
⚙️ <b>پردازنده:</b> {$result['cpu']}
🔢 <b>تعداد هسته‌ها:</b> {$result['cpu-count']}
🚀 <b>فرکانس CPU:</b> {$result['cpu-frequency']}
📊 <b>میزان بار CPU:</b> {$result['cpu-load']} %
</blockquote>

<blockquote>
💾 <b>فضای کل هارد:</b> $total_hdd_space گیگ
📂 <b>فضای آزاد هارد:</b> $free_hdd_space گیگ
🧠 <b>حافظه کل رم:</b> $total_memory گیگ
📉 <b>حافظه آزاد رم:</b> $free_memory گیگ
</blockquote>

<blockquote>
📝 <b>سکتورهای نوشته‌شده از زمان ریبوت:</b> {$result['write-sect-since-reboot']}
🧮 <b>مجموع سکتورهای نوشته‌شده:</b> {$result['write-sect-total']}
</blockquote>
", $option_mikrotik, 'HTML');
        }
    } else {
        nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionMarzban, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
}
/* ---- finance.php ---- */
if (function_exists('nmResolvePanelNameForUser')) {
    // Some legacy handlers in this file (panel-name edit, URL edit, etc.)
    // expect $user['Processing_value'] to be a SCALAR panel name. This shim
    // extracts the panel name from a JSON-encoded state and writes it back
    // as a scalar — but doing that UNCONDITIONALLY destroys the multi-key
    // JSON state used by the discount/gift creation flows.
    //
    // Symptom: in step `getproductdiscount` the admin types "all", the
    // handler runs json_decode($user['Processing_value']) and gets NULL
    // (because this shim just overwrote the JSON with "/all"), every key
    // looks "missing", and the bot replies "اطلاعات ساخت کد تخفیف ناقص".
    //
    // Skip the shim while the user is mid-flow in one of those JSON-state
    // steps. Other steps keep their previous behavior.
    $jsonStateSteps = [
        'get_code','get_price_code','getlimitcodedis',
        'get_codesell','get_price_codesell','getlimitcode','gettypecodeagent',
        'gettimediscount','getfirstdiscount','getuseuser','getlocdiscount','getproductdiscount',
    ];
    if (!in_array($user['step'] ?? '', $jsonStateSteps, true)) {
        $rxResolvedPanelName = nmResolvePanelNameForUser($user);
        if ($rxResolvedPanelName !== '') {
            $rawProcessing = (string)($user['Processing_value'] ?? '');
            $rxFlatten = ($rawProcessing === '');
            if (!$rxFlatten && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
                // Only flatten a JSON state that is a BARE panel reference. If it
                // carries ANY other key it is an in-progress multi-step flow state
                // (custom volume/time/price, add-config, etc.) whose JSON must
                // survive — otherwise the next step json_decode()s it, gets the
                // scalar panel name back, and reports "اطلاعات مرحله قبلی ناقص".
                $rxDecodedState = json_decode($rawProcessing, true);
                $rxFlatten = true;
                if (is_array($rxDecodedState)) {
                    foreach ($rxDecodedState as $rxK => $rxV) {
                        if (!in_array($rxK, ['namepanel', 'name_panel', 'panel', 'panel_name'], true)) {
                            $rxFlatten = false;
                            break;
                        }
                    }
                }
                unset($rxDecodedState, $rxK, $rxV);
            }
            if ($rxFlatten) {
                $user['Processing_value'] = $rxResolvedPanelName;
            }
            unset($rxFlatten);
        }
        unset($rxResolvedPanelName, $rawProcessing);
    }
    unset($jsonStateSteps);
}
if (false) {
} elseif ($text == "✍️ نام پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    if (in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
    update("user", "Processing_value", $text, "id", $from_id);
    update("marzban_panel", "name_panel", $text, "name_panel", $user['Processing_value']);
    update("invoice", "Service_location", $text, "Service_location", $user['Processing_value']);
    update("product", "Location", $text, "Location", $user['Processing_value']);
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔗 ویرایش آدرس پنل" && $adminrulecheck['rule'] == "administrator") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel && $typepanel['type'] == "guard") {
        outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['guardfixedurl']);
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "📍 تغییر گروه کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 نوع کاربری را ارسال کنید
گروه های کاربری : f,n,n2
❌ در صورتی که می خواهید پنل برای تمام گروه کاربری ها نمایش داده شود متن all را ارسال کنید", $backadmin, 'HTML');
    step('getagentpanel', $from_id);
} elseif ($user['step'] == "getagentpanel") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌گروه کاربری با موفقیت تغییر کرد");
    update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 دامنه لینک ساب" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 اگر پنل ثنایی هستید یک لینک ساب کاربر را از پنل کپی کرده سپس در این بخش ارسال کنید .بقیه پنل ها باید طبق ساختارش ارسال نمایید.", $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    $inputLink = trim($text);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel['type'] !== "x-ui_single" && !filter_var($inputLink, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    if ($typepanel['type'] === "x-ui_single") {
        $text = normalizeXuiSingleSubscriptionBaseUrl($inputLink);
    } else {
        $text = $inputLink;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 uuid ادمین را ارسال کنید", $backadmin, 'HTML');
    step('getuuidadmin', $from_id);
} elseif ($user['step'] == "getuuidadmin") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ uuid ادمین ذخیره گردید");
    update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🚨 محدودیت ساخت اکانت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
    step('getlimitnew', $from_id);
} elseif ($user['step'] == "getlimitnew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
    update("marzban_panel", "limit_panel", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⏳ زمان سرویس تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🕰 مدت زمان سرویس تست را ارسال کنید.
⚠️ زمان بر حسب ساعت است.", $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "time_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💾 حجم اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم سرویس تست را ارسال کنید.
⚠️ حجم بر حسب مگابایت است.", $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "val_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💎 تنظیم شناسه اینباند" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 شناسه اینباندی که می خواهید کانفیگ ازآن ساخته شود راارسال نمایید.  شناسه اینباند یک عدد چند رقمی است که در پنل  در صفحه اینباند ها ستون id  نوشته شده است

⚠️ در صورتی که پنل wgdashboard هستید باید نام کانفیگ را ارسال نمایید", $backadmin, 'HTML');
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    nm_adminInstantReply($from_id, "✅ شناسه اینباند با موفقیت ذخیره گردید", $optionX_ui_single, 'HTML');
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔐 ویرایش کلید" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['request_api_key'], $backadmin, 'HTML');
    step('guard_edit_api_key', $from_id);
} elseif ($user['step'] == "guard_edit_api_key") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $baseUrl = guardGetBaseUrl($typepanel['url_panel'] ?? null);
    $currentKey = trim((string) (!empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '')));
    if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
        $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
        $testResult = guardTestConnection($baseUrl, $apiKey);
        $statusText = guardFormatConnectionResult($testResult);
        if ($statusText !== '') {
            $sameKeyMessage .= "\n{$statusText}";
        }
        outtypepanel("guard", $sameKeyMessage);
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "api_key", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "password_panel", $apiKey, "name_panel", $user['Processing_value']);
    update("marzban_panel", "url_panel", $baseUrl, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    $connectionResult = guardTestConnection($baseUrl, $apiKey);
    $statusText = guardFormatConnectionResult($connectionResult);
    $savedMessage = $textbotlang['Admin']['managepanel']['guard']['api_key_saved'] ?? "🔑 کلید Guard ذخیره شد.";
    outtypepanel("guard", "{$savedMessage}\n{$statusText}");
    step('home', $from_id);
} elseif ($text == "⁉️ وضعیت اتصال به پنل" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $typepanel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['errorstateuspanel'], $backadmin, 'HTML');
        return;
    }
    $apiKey = !empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '');
    if (trim($apiKey) === '') {
        outtypepanel("guard", $textbotlang['Admin']['managepanel']['guard']['connection_missing_key']);
        step('home', $from_id);
        return;
    }
    $testResult = guardTestConnection($typepanel['url_panel'] ?? null, $apiKey);
    $statusText = guardFormatConnectionResult($testResult);
    outtypepanel("guard", $statusText);
    step('home', $from_id);
} elseif ($text == "⚙️ تنظیم سرویس ها" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $servicesResponse = guardGetServices($panel['name_panel']);
    if ($servicesResponse['status'] === false) {
        $errorMsg = $servicesResponse['msg'] ?? 'خطای ناشناخته';
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, $errorMsg);
        outtypepanel("guard", $failText);
        return;
    }
    $services = $servicesResponse['services'];
    if (empty($services)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'لیست سرویس Guard خالی است.');
        outtypepanel("guard", $failText);
        return;
    }
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.');
        outtypepanel("guard", $failText);
        return;
    }
    $currentServices = guardParseServiceIds($panel['guard_service_ids'] ?? null);
    $currentSelection = guardNormalizeSelectedServiceIds($currentServices, $availableIds, true);
    $selectAll = in_array('all', $currentServices, true) || in_array(0, $currentServices, true) || count($currentSelection) === count($availableIds);
    if ($selectAll) {
        $currentSelection = $availableIds;
    }
    $manualSelection = guardNormalizeSelectedServiceIds($currentServices, $availableIds, false);
    $selectionState = [
        'mode' => 'edit',
        'panel' => $panel['name_panel'],
        'services' => $services,
        'selected_ids' => $currentSelection,
        'select_all' => $selectAll,
        'manual_selected_ids' => $manualSelection,
    ];
    savedata("save", "guard_service_selection", $selectionState);
    $message = guardBuildServiceSelectionMessage($services, $currentSelection, $selectAll);
    $keyboard = guardBuildServiceSelectionKeyboard($services, $currentSelection, 'edit', $selectAll);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $selectionState['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $selectionState);
    }
    step('guard_service_selection_edit', $from_id);
} elseif ($text == "🎛️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['saveddata'], $backadmin, 'HTML');
        return;
    }
    $state = guardBuildGuardSettingsState($panel);
    $messageId = guardRenderGuardSettingsSummary($from_id, $state, false);
    $state['message_id'] = $messageId;
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_note") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $noteText = trim($text);
    $state['note'] = ($noteText === '-') ? '' : $noteText;
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_auto_delete") {
    if (!preg_match('/^\d+$/', $text)) {
        nm_adminInstantReply($from_id, "❌ مقدار باید فقط عدد باشد.", $backadmin, 'HTML');
        return;
    }
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $state['auto_delete_days'] = intval($text);
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($user['step'] == "guard_settings_auto_renew") {
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel);
    $parsedRenewal = guardParseAutoRenewalsInput($text);
    if ($parsedRenewal['status'] === false) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['settings_invalid_renewal'], $backadmin, 'HTML');
        return;
    }
    $state['auto_renewals'] = $parsedRenewal['entries'];
    $state['pending_changes'] = true;
    guardPersistGuardSettingsState($from_id, $state);
    $state['message_id'] = guardRenderGuardSettingsSummary($from_id, $state, false);
    guardPersistGuardSettingsState($from_id, $state);
    step('guard_settings_summary', $from_id);
} elseif ($text == "👤 ویرایش نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⚙️ تنظیم پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocolx_ui', $from_id);
} elseif ($user['step'] == "getprotocolx_ui") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
    $marzbanprotocol = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
    step('home', $from_id);
} elseif ($text == "🔐 ویرایش رمز عبور" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌ حذف پنل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "در صورت تایید کلمه زیر را ارسال کنید.
<code>تایید</code>", $backadmin, 'HTML');
    step('confirmremovepanel', $from_id);
} elseif ($user['step'] == "confirmremovepanel") {
    if ($text == "تایید") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $keyboardadmin, 'HTML');
        $marzban = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE name_panel = :name_panel");
        $stmt->bindParam(':name_panel', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->execute();
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لیست کاربرانی که موجودی دارند.", 'callback_data' => "balanceuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که زیرمجموعه دارند.", 'callback_data' => "listrefral"],
            ],
            [
                ['text' => "لیست کاربران شماره کارت فعال.", 'callback_data' => "cartuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که موجودی منفی دارند", 'callback_data' => "zerobalance"],
            ],
            [
                ['text' => "لیست نمایندگان", 'callback_data' => "agentlistusers"],
                ['text' => "لیست کل کاربران", 'callback_data' => "alllistusers"],
            ],
            [
                ['text' => "🛍 جستجو سفارش", 'callback_data' => "searchorder"],
                ['text' => "👥 شارژ همگانی", 'callback_data' => "balanceaddall"],
            ],
            [
                ['text' => "🔍 جستجو کاربر", 'callback_data' => "searchuser"],
                ['text' => "📨 بخش ارسال پیام", 'callback_data' => "systemsms"],
            ],
            [
                ['text' => "🔋 حجم یا زمان همگانی", 'callback_data' => "voloume_or_day_all"],
            ]
        ]
    ]);
    $text_list_users = "📌 از لیست زیر یک گزینه را انتخاب نمایید";
    if ($datain == "backlistuser") {
        Editmessagetext($from_id, $message_id, $text_list_users, $keyboardtypelistuser);
    } else {
        nm_adminInstantReply($from_id, $text_list_users, $keyboardtypelistuser, 'html');
    }
} elseif ($datain == "alllistusers") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuser') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuser') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'close_listusers') {
    deletemessage($from_id, $message_id);
} elseif ($datain == "agentlistusers") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "n", 'callback_data' => "agenttypshowlist_n"],
                ['text' => "n2", 'callback_data' => "agenttypshowlist_n2"],
            ],
            [
                ['text' => "تمام نمایندگان", 'callback_data' => "agenttypshowlist_all"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 کدام گروه از نمایندگان می خواهید مشاهده کنید ؟", $keyboardtypelistuser);
} elseif (preg_match('/agenttypshowlist_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/next_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    } else {
        $_s=(int)$start_index; $_p=(int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $typeagent, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "balanceuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserbalance') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserbalance') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE Balance != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "listrefral") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserrefral') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserrefral') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE affiliatescount != '0'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['addbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        nm_adminInstantReply($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "add balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');


    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
    $Balance_user_after = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $pricadd = number_format($text);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را افزایش داده است :

🪪 اطلاعات ادمین افزایش دهنده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر دریافت کننده موجودی :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $pricadd
موجودی کاربر پس از افزایش : $Balance_user_after";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        nm_adminInstantReply($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "low balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');


    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ((preg_match('/banuserlist_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
} elseif ($user['step'] == "adddecriptionblock") {
    update("user", "description_blocking", $text, "id", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['DescriptionBlock'], $keyboardadmin, 'HTML');
    step('home', $from_id);

} elseif ((preg_match('/acceptblock_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {

    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    update("user", "User_Status", "block", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات مسدود گردید
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "1", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ کاربر با موفقیت احراز گردید.", null, 'HTML');
    sendmessage($iduser, "💎 کاربر گرامی حساب کاربری شما توسط ادمین با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید", $keyboard, 'HTML');
} elseif (preg_match('/unverify-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "✅ کاربر با موفقیت از حالت احراز خارج گردید.", null, 'HTML');


} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "Active") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
        return;
    }
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات  رفع مسدود گردید
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "User_Status", "Active", "id", $iduser);
    update("user", "description_blocking", " ", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserUnblocked'], $keyboardadmin, 'HTML');
    sendmessage($iduser, "✳️ حساب کاربری شما از مسدودی خارج شد ✳️
اکنون میتوانید از ربات استفاده کنید ✔️", $keyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "number", "confrim number by admin", "id", $iduser);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['phone']['active'], $keyboardadmin, 'HTML');
} elseif (preg_match('/viewpaymentuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_user = ?");
    $_stmt->bind_param("s", $iduser);
    $_stmt->execute();
    $PaymentUsers = $_stmt->get_result();
    $_stmt->close();
    foreach ($PaymentUsers as $paymentUser) {
        $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
        nm_adminInstantReply($from_id, $text_order, null, 'HTML');
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['sendpayemntlist'], $keyboardadmin, 'HTML');
} elseif (preg_match('/affiliates-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "count");
    if ($affiliatesUsers == 0) {
        nm_adminInstantReply($from_id, "❌ کاربر دارای زیرمجموعه نمی باشد.", null, 'HTML');
        return;
    }
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "fetchAll");
    $count = 0;
    $text_affiliates = "";
    foreach ($affiliatesUsers as $affiliatesUser) {
        $text_affiliates .= "<code>{$affiliatesUser['id']}</code>\n\r";
        $count++;
        if ($count == 10) {
            nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
            $count = 0;
            $text_affiliates = "";
        }
    }
    nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
    nm_adminInstantReply($from_id, "📌 شناسه مربوط به زیرمجموعه های کاربر ارسال گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliate-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $user2 = select("user", "*", "id", $iduser, "select");
    $user2 = select("user", "*", "id", $user2['affiliates'], "select");
    $affiliatescount = intval($user2['affiliatescount']) - 1;
    update("user", "affiliatescount", $affiliatescount, "id", $user2['id']);
    update("user", "affiliates", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "📌 کاربر از زیرمجموعه خارج شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliateuser-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "affiliatescount", "0", "id", $iduser);
    update("user", "affiliates", "0", "affiliates", $iduser);
    nm_adminInstantReply($from_id, "📌 زیرمجموعه های کاربر حذف شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeservice-(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/removeserviceandback-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    if ($info_product['Status'] == "removebyadmin") {
        nm_adminInstantReply($from_id, "❌ سرویس از قبل حذف شده است", $keyboardadmin, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
    } else {
        if ($DataUserOut['status'] == "Unsuccessful") {
            nm_adminInstantReply($from_id, 'خطایی رخ داده است', $keyboardadmin, 'HTML');
        }
    }
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    $Balance_user = select("user", "*", "id", $info_product['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $info_product['price_product'];
    update("user", "Balance", $Balance_add_user, "id", $info_product['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ {$info_product['price_product']} تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($info_product['id_user'], $textadd, null, 'HTML');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "get_codesell") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
    step('get_price_codesell', $from_id);
    savedata("clear", "code", strtolower($text));
} elseif ($user['step'] == "get_price_codesell") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
    step('getlimitcode', $from_id);
} elseif ($user['step'] == "getlimitcode") {
    savedata("save", "limitDiscount", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['agentcode'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypecodeagent', $from_id);
} elseif ($user['step'] == "gettypecodeagent") {
    $agentst = ["n", "n2", "f", "allusers"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], rx_agentGroupKeyboard(true), 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    nm_adminInstantReply($from_id, "📌 کد تخفیف برای چند ساعت فعال باشد . در صورتی که میخواهید نامحدود باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('gettimediscount', $from_id);
} elseif ($user['step'] == "gettimediscount") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) == 0) {
        $text = "0";
    } else {
        $text = time() + (intval($text) * 3600);
    }
    savedata("save", "time", $text);
    $keyboarddiscount = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تمامی خرید ها", 'callback_data' => "discountlimitbuy_0"],
                ['text' => "خرید اول", 'callback_data' => "discountlimitbuy_1"],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['firstdiscount'], $keyboarddiscount, 'HTML');
    step('getfirstdiscount', $from_id);
} elseif (preg_match('/discountlimitbuy_(\w+)/', $datain, $dataget)) {
    $discountbuylimit = $dataget[1];
    savedata("save", "usefirst", $discountbuylimit);
    if (intval($discountbuylimit) == 1) {
        nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
        step('getuseuser', $from_id);
        savedata("save", "typediscount", "all");
    } else {
        $keyboarddiscount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "خرید", 'callback_data' => "discounttype_buy"],
                    ['text' => "تمدید", 'callback_data' => "discounttype_extend"],
                ],
                [
                    ['text' => "هردو", 'callback_data' => "discounttype_all"]
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 کد تخفیف برای کدوم بخش باشد", $keyboarddiscount);
    }
} elseif (preg_match('/discounttype_(\w+)/', $datain, $dataget)) {
    $discountbuytype = $dataget[1];
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    savedata("save", "typediscount", $discountbuytype);
    nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
    step('getuseuser', $from_id);
} elseif ($user['step'] == "getuseuser") {
    $userdata = json_decode($user['Processing_value'], true);
    $numberlimit = $userdata['limitDiscount'];
    if (intval($text) > intval($userdata['limitDiscount'])) {
        nm_adminInstantReply($from_id, "📌 تعداد استفاده برای یک کاربر باید کوچیک تر از محدودیت کل باشد", $backadmin, 'HTML');
        return;
    }
    step('getlocdiscount', $from_id);
    savedata("save", "useuser", $text);
    nm_adminInstantReply($from_id, "📌 برای تنظیم  کد تخفیف مخصوص یک محصول ابتدا موقعیت محصول راانتخاب نمایید.
توجه : برای انتخاب تمام پنل ها کلمه<code>/all</code> را ارسال کنید", $json_list_marzban_panel, 'HTML');
    step('getlocdiscount', $from_id);
} elseif ($user['step'] == "getlocdiscount") {
    if ($text == "/all") {
        $panel['code_panel'] = "/all";
    } else {
        $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    }
    if ($panel == false)
        return;
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "name_panel", $text);
    nm_adminInstantReply($from_id, "📌  میخواهید کد تخفیف برای کدام محصول باشد. توجه داشتید درصورتی که میخواهید کد تخفیف برای تمامی محصولات باشد کلمه all را ارسال کنید", $json_list_product_list_admin, 'HTML');
    step('getproductdiscount', $from_id);
} elseif ($user['step'] == "getproductdiscount") {
    if ($text != "all") {
        $product = select("product", "*", "name_product", $text, "select");
    } else {
        $product['code_product'] = "all";
    }
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصول انتخابی وجود ندارد", $keyboardadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, agent, usefirst, useuser, code_panel, code_product, time,type) VALUES (:codeDiscount, :usedDiscount, :price, :limitDiscount, :agent, :usefirst, :useuser, :code_panel, :code_product, :time,:type)");
    $values = "0";
    $values1 = "1";
    $code_product = "0";
    $stmt->bindParam(':codeDiscount', $userdata['code'], PDO::PARAM_STR);
    $stmt->bindParam(':usedDiscount', $values, PDO::PARAM_STR);
    $stmt->bindParam(':price', $userdata['price'], PDO::PARAM_STR);
    $stmt->bindParam(':limitDiscount', $userdata['limitDiscount'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $userdata['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':usefirst', $userdata['usefirst'], PDO::PARAM_STR);
    $stmt->bindParam(':useuser', $userdata['useuser'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $userdata['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':code_product', $product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':time', $userdata['time'], PDO::PARAM_STR);
    $stmt->bindParam(':type', $userdata['typediscount'], PDO::PARAM_STR);
    $stmt->execute();
    $textdiscount = "
🎁 کد تخفیف شما با موفقیت ساخته شد.

📩 نام کد تخفیف: <code>{$userdata['code']}</code>
🧮 درصد کد تخفیف: {$userdata['price']}
🎛 پنل :  {$userdata['name_panel']}
📌  محصول : $text
♻️ نوع کاربری :‌ {$userdata['agent']}
🔴 محدودیت استفاده :‌ {$userdata['limitDiscount']}";
    nm_adminInstantReply($from_id, $textdiscount, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "remove-Discountsell") {
    if (!in_array($text, $SellDiscount)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = :codeDiscount");
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "/end") {
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
    if ($panel['type'] == "marzneshin") {
        update("user", "Processing_value", $userdata['name_panel'], "id", $from_id);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionmarzneshin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
    step('home', $from_id);
    return;
} elseif ($text == "🧮 تنظیم درصد زیرمجموعه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "درصد نامعتبر", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
    update("setting", "affiliatespercentage", $text);
    step('home', $from_id);
} elseif ($text == "🏞 تنظیم بنر زیرمجموعه گیری") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($user['step'] == "setbanner") {
    if (!$photo) {
        nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "id_media", $photoid);
    update("affiliates", "description", $caption);
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['insertbanner'], $affiliates, 'HTML');
    step('home', $from_id);
} elseif ($text == "👤 آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDirect");
    $textcart = "📌 نام کاربری خود را بدون @ برای دریافت شماره کارت ارسال کنید\n\n{$PaySetting['ValuePay']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('CartDirect', $from_id);
} elseif ($user['step'] == "CartDirect") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
    step('home', $from_id);
} elseif ($text == "💳 درگاه آفلاین در پیوی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
} elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], null);
} elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], null);
} elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    update("user", "Processing_value", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        $ff = telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $id_order);

    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalancemanual', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "addbalancemanual") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Balance_user = select("user", "*", "id", $Payment_report['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    $balanceusers = number_format($text, 0);
    update("user", "Balance", $Balance_add_user, "id", $Payment_report['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ $balanceusers تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
    $text_report = "تایید رسید کارت به کارت و افزایش دستی موجودی توسط ادمین

آیدی عددی کاربر : {$Payment_report['id_user']}
نام کاربری کاربر : {$Balance_user['username']}
مبلغ تراکنش در فاکتور :  {$Payment_report['price']}
مبلغ تراکنش واریزی توسط ادمین : $text";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == "🎁 پورسانت بعد از خرید" && $adminrulecheck['rule'] == "administrator") {
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "oncommission") {
    update("affiliates", "status_commission", "offcommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
} elseif ($datain == "offcommission") {
    update("affiliates", "status_commission", "oncommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
} elseif ($text == "🎁 هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "onDiscountaffiliates") {
    update("affiliates", "Discount", "offDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
} elseif ($datain == "offDiscountaffiliates") {
    update("affiliates", "Discount", "onDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
} elseif ($text == "🌟 مبلغ هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont") {
    nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
    update("affiliates", "price_Discount", $text);
    step('home', $from_id);
} elseif ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")[$user['agent']], true);
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalance', $from_id);
} elseif ($user['step'] == "minbalance") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemin', $from_id);
    nm_adminInstantReply($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemin") {
    $agentst = ["n", "n2", "f", "allusers"];
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $bakcadmin, 'HTML');
        return;
    }
    $text = $grp;
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
} elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select");
    $textmax = "📌 حداکثر مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    nm_adminInstantReply($from_id, $textmax, $backadmin, 'HTML');
    step('maxbalance', $from_id);
} elseif ($user['step'] == "maxbalance") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemax', $from_id);
    nm_adminInstantReply($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemax") {
    $agentst = ["n", "n2", "f", "allusers"];
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $bakcadmin, 'HTML');
        return;
    }
    $text = $grp;
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "maxbalance");
} elseif (preg_match('/removeagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['useragentremoved'],
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    update("user", "agent", "f", "id", $id_user);
    update("user", "pricediscount", "0", "id", $id_user);
    update("user", "expire", null, "id", $id_user);
    $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE id = '$id_user'");
    $stmt->execute();
    step('home', $from_id);
} elseif (preg_match('/addagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['gettypeagent'],
        'parse_mode' => "HTML",
        'reply_markup' => $backadmin,
        'reply_to_message_id' => $message_id,
    ]);
    step('gettypeagentoflist', $from_id);
} elseif ($user['step'] == "gettypeagentoflist") {
    $agentst = ["n", "n2"];
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, $agentst) : (in_array($text, $agentst, true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $text = $grp;
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    update("user", "expire", null, "id", $user['Processing_value']);
    update("user", "agent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/Percentlow_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => "📌 تعداد درصدی که میخواهید در صورتی که کاربر هرگونه خریدی انجام داده است تخفیفی دریافت کند را ارسال نمایید.",
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('getpercentuser', $from_id);
} elseif ($user['step'] == "getpercentuser") {
    if (intval($text) > 100 || intval($text) < 0 || !ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "pricediscount", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/maxbuyagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغی که کاربر می توانید موجودی  اش در زمان خرید منفی شود را ارسال نمایید
توجه : عدد بدون خط تیره یا نماد منفی باشد
در صورتی که می خواهید کاربر نامحدود خریداری کند عدد 0 ارسال کنید", $backadmin, 'HTML');
    step('getmaxbuyagent', $from_id);
} elseif ($user['step'] == "getmaxbuyagent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "maxbuyagent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif ($datain == "searchorder") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['vieworderusername'], $backadmin, 'HTML');
    step('GetusernameconfigAndOrdedrs', $from_id);
} elseif ($user['step'] == "GetusernameconfigAndOrdedrs" || strpos($text, "/config ") !== false || preg_match('/manageinvoice_(\w+)/', $datain, $datagetr)) {
    if ($user['step'] == "GetusernameconfigAndOrdedrs") {
        $usernameconfig = $text;
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } elseif ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } else {
        $usernameconfig = select("invoice", "*", "id_invoice", $datagetr[1], "select")['username'];
        $sql = "SELECT * FROM invoice WHERE username = :username OR note  = :notes";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    }
    $stmt->execute();
    step("home", $from_id);
    if ($stmt->rowCount() > 1) {
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "عملیات", 'callback_data' => "action"],
            ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
            ['text' => "نام کاربری", 'callback_data' => "username"],
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "مشاهده اطلاعات",
                    'callback_data' => "manageinvoice_" . $row['id_invoice']
                ],
                [
                    'text' => $row['Status'],
                    'callback_data' => "username"
                ],
                [
                    'text' => $row['username'],
                    'callback_data' => $row['username']
                ],
            ];
        }
        $keyboardlists = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, "⚠️ بیشتر از یک سرویس یافت از لیست زیر سرویس صحیح را انتخاب کنید", $keyboardlists, 'HTML');
        return;
    }
    $OrderUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$OrderUser) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['order']['notfound'], null, 'HTML');
        return;
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "♻️ بروزرسانی", 'callback_data' => "manageinvoice_" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['Admin']['ManageUser']['removeservice'], 'callback_data' => "removeservice-" . $OrderUser['id_invoice']],
        ['text' => $textbotlang['Admin']['ManageUser']['removeserviceandback'], 'callback_data' => "removeserviceandback-" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "🗑 حذف کامل سرویس", 'callback_data' => "removefull-" . $OrderUser['id_invoice']],
    ];
    if (isset($OrderUser['time_sell'])) {
        $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
    } else {
        $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
    }
    if ($OrderUser['name_product'] == "سرویس تست") {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "مگابایت";
    } else {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "گیگابایت";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 20");
    $stmt->execute([
        ':username' => $OrderUser['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "name_product", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $OrderUser['name_product'] = $codeproduct['name_product'];
                $OrderUser['Volume'] = $codeproduct['Volume_constraint'];
                $OrderUser['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }
    $text_order = "
🛒 شماره سفارش  :  <code>{$OrderUser['id_invoice']}</code>
🛒  وضعیت سفارش در ربات : <code>{$OrderUser['Status']}</code>
🙍‍♂️ شناسه کاربر : <code>{$OrderUser['id_user']}</code>
👤 نام کاربری اشتراک :  <code>{$OrderUser['username']}</code>
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
";
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($OrderUser, $from_id, $keyboardadmin)) {
        $keyboard_json = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $keyboard_json = json_encode($keyboardlists);
        nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", $keyboardadmin, 'html');
        nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
    ][$status];

    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $lastupdate = "";
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    $limitValue = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
    $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float) $DataUserOut['used_traffic'] : 0;
    $Percent = safe_divide(($limitValue - $usedTrafficValue) * 100, $limitValue, 100);
    if ($Percent < 0) {
        $Percent = -$Percent;
    }
    $Percent = round($Percent, 2);
    $text_order .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
    if ($DataUserOut['status'] == "active") {
        $namestatus = '❌ خاموش کردن اکانت';
    } else {
        $namestatus = '💡 روشن کردن اکانت';
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
        ['text' => $textbotlang['users']['stateus']['config'], 'callback_data' => 'config_' . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $namestatus, 'callback_data' => 'changestatusadmin_' . $OrderUser['id_invoice']],
    ];
    $keyboard_json = json_encode($keyboardlists);
    nm_adminInstantReply($from_id, $text_order, $keyboard_json, 'HTML');
    $stmt = $pdo->prepare("SELECT * FROM service_other s WHERE username = :uc AND (status = 'paid' OR status IS NULL)");
    $stmt->execute([':uc' => (string) $usernameconfig]);
    $list_service = $stmt->fetchAll();
    if ($list_service) {
        foreach ($list_service as $extend) {
            $extend_type = [
                'extend_user' => "تمدید",
                'extend_user_by_admin' => 'تمدید شده توسط ادمین',
                'extra_user' => "حجم اضافه",
                "extra_time_user" => "زمان اضافه",
                "transfertouser" => "انتقال به حساب دیگر",
                "extends_not_user" => "تمدید از نوع نبودن یوزر در لیست",
                "change_location" => "تغییر لوکیشن",
                'gift_time' => 'هدیه همگانی زمان',
                'gift_volume' => 'هدیه همگانی حجم'
            ][$extend['type']];
            $time_jalali = jdate('Y/m/d H:i:s', strtotime($extend['time']));

            $extendtext = "
📌 گزارش سرویس
🔗  نوع سرویس : $extend_type
🕰 زمان انجام سرویس : {$extend['time']} \n\n($time_jalali)
💰مبلغ انجام سرویس : {$extend['price']}
👤 آیدی عددی کاربر : {$extend['id_user']}
👤 نام کاربری کانفیگ: {$extend['username']}";
            nm_adminInstantReply($from_id, $extendtext, null, 'HTML');
        }
    }
    step('home', $from_id);
} elseif ($text == "🛒 وضعیت قابلیت های فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }
    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editshops-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "extravolunme") {
        if ($value == "onextra") {
            $valuenew = "offextra";
        } else {
            $valuenew = "onextra";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusextra");
    } elseif ($type == "paydirect") {
        if ($value == "ondirectbuy") {
            $valuenew = "offdirectbuy";
        } else {
            $valuenew = "ondirectbuy";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdirectpabuy");
    } elseif ($type == "statustimeextra") {
        if ($value == "ontimeextraa") {
            $valuenew = "offtimeextraa";
        } else {
            $valuenew = "ontimeextraa";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statustimeextra");
    } elseif ($type == "disorderss") {
        if ($value == "ondisorder") {
            $valuenew = "offdisorder";
        } else {
            $valuenew = "ondisorder";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdisorder");
    } elseif ($type == "categroygenral") {
        if ($value == "oncategorys") {
            $valuenew = "offcategorys";
        } else {
            $valuenew = "oncategorys";
        }
        update("setting", "statuscategorygenral", $valuenew, null, null);
    } elseif ($type == "changgestatus") {
        if ($value == "onstatus") {
            $valuenew = "offstatus";
        } else {
            $valuenew = "onstatus";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statuschangeservice");
    } elseif ($type == "showprice") {
        if ($value == "onshowprice") {
            $valuenew = "offshowprice";
        } else {
            $valuenew = "onshowprice";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusshowprice");
    } elseif ($type == "showconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "configshow");
    } elseif ($type == "removeservicebackbtn") {
        if ($value == "on") {
            $valuenew = "off";
        } else {
            $valuenew = "on";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "backserviecstatus");
    } elseif ($type == "categorytime") {
        if ($value == "oncategory") {
            $valuenew = "offcategory";
        } else {
            $valuenew = "oncategory";
        }
        update("setting", "statuscategory", $valuenew);
    }
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }

    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "🪪 خروجی گرفتن اطلاعات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardexportdata, 'HTML');
} elseif ($text == "🕚 تنظیمات کرون جاب" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cron_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($datain == "cronjobs_settings" && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('buildCronJobsKeyboard')) {
        step('admin_nav_cron_jobs', $from_id);
        $rx_cron_title = "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.";
        nm_adminInstantReply($from_id, $rx_cron_title, buildCronJobsKeyboard(), 'HTML');
    } else {
        nm_adminInstantReply($from_id, "❌ سیستم کرون در دسترس نیست.", $setting_panel, 'HTML');
    }
} elseif ($datain == "cronjob_display" && $adminrulecheck['rule'] == "administrator") {
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'cache_time' => 1,
        ]);
    }
} elseif ($datain == "cronjobs_back_settings" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cron_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif (preg_match('/^cronjob_config-([A-Za-z0-9_]+)$/', $datain, $rx_cron_cfg) && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('getCronJobDefinitions') && function_exists('loadCronSchedules') && function_exists('describeCronSchedule')) {
        $rx_cron_key = $rx_cron_cfg[1];
        $rx_cron_defs = getCronJobDefinitions();
        if (!isset($rx_cron_defs[$rx_cron_key])) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => "❌ کرون نامعتبر",
                'show_alert' => true,
                'cache_time' => 5,
            ]);
            return;
        }
        $rx_cron_def = $rx_cron_defs[$rx_cron_key];
        $rx_cron_schedules = loadCronSchedules();
        $rx_cron_current = $rx_cron_schedules[$rx_cron_key] ?? $rx_cron_def['default'];
        $rx_cron_current_label = describeCronSchedule($rx_cron_current);
        $rx_cron_label = (string) ($rx_cron_def['admin_label'] ?? $rx_cron_key);
        $rx_cron_minute_options = [1, 2, 3, 5, 10, 15, 30];
        $rx_cron_hour_options   = [1, 2, 3, 6, 12];
        $rx_cron_day_options    = [1, 2, 5, 7];
        $rx_cron_rows = [];
        $rx_cron_row = [];
        foreach ($rx_cron_minute_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "🕒 هر {$rx_cron_v} دقیقه", 'callback_data' => "cronjob_apply-{$rx_cron_key}-minute-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        foreach ($rx_cron_hour_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "⏰ هر {$rx_cron_v} ساعت", 'callback_data' => "cronjob_apply-{$rx_cron_key}-hour-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        foreach ($rx_cron_day_options as $rx_cron_v) {
            $rx_cron_row[] = ['text' => "📅 هر {$rx_cron_v} روز", 'callback_data' => "cronjob_apply-{$rx_cron_key}-day-{$rx_cron_v}"];
            if (count($rx_cron_row) === 2) { $rx_cron_rows[] = $rx_cron_row; $rx_cron_row = []; }
        }
        if (!empty($rx_cron_row)) { $rx_cron_rows[] = $rx_cron_row; }
        $rx_cron_rows[] = [
            ['text' => "⛔ غیرفعال", 'callback_data' => "cronjob_apply-{$rx_cron_key}-disabled-1"],
        ];
        $rx_cron_hour_fields = ['lottery' => 'lottery_hour', 'statusday' => 'statusday_hour'];
        $rx_cron_has_hour = isset($rx_cron_hour_fields[$rx_cron_key]);
        $rx_cron_hour_now = $rx_cron_has_hour ? (int) ($setting[$rx_cron_hour_fields[$rx_cron_key]] ?? 0) : 0;
        if ($rx_cron_has_hour) {
            $rx_cron_rows[] = [
                ['text' => "🕛 تنظیم ساعت اجرا (فعلی: {$rx_cron_hour_now}:00)", 'callback_data' => "cronjob_sethour-{$rx_cron_key}"],
            ];
        }
        $rx_cron_rows[] = [
            ['text' => "🔙 بازگشت به لیست کرون‌ها", 'callback_data' => "cronjobs_settings"],
        ];
        $rx_cron_keyboard = json_encode(['inline_keyboard' => $rx_cron_rows], JSON_UNESCAPED_UNICODE);
        $rx_cron_text = "⚙️ تنظیم زمان‌بندی\n\n📌 کرون: <b>{$rx_cron_label}</b>\n⏱ زمان فعلی: <b>{$rx_cron_current_label}</b>\n\nزمان‌بندی جدید را انتخاب کنید:";
        if ($rx_cron_has_hour) {
            $rx_cron_text .= "\n\n🕛 اگر بازه را روی «هر ۱ روز» بگذاری، این کرون رأس ساعت <b>{$rx_cron_hour_now}:00</b> (به وقت تهران) اجرا می‌شود.\nبرای تغییرِ این ساعت، دکمهٔ «تنظیم ساعت اجرا» را بزن.\n(برای حالت «هر N ساعت/دقیقه» این ساعت بی‌اثر است و دقیقاً طبق همان بازه اجرا می‌شود.)";
        }
        step('cronjob_set_value', $from_id);
        nm_adminInstantReply($from_id, $rx_cron_text, $rx_cron_keyboard, 'HTML');
    }
} elseif (preg_match('/^cronjob_apply-([A-Za-z0-9_]+)-(minute|hour|day|disabled)-(\d+)$/', $datain, $rx_cron_apply) && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('updateCronSchedule') && function_exists('buildCronJobsKeyboard')) {
        $rx_cron_key  = $rx_cron_apply[1];
        $rx_cron_unit = $rx_cron_apply[2];
        $rx_cron_val  = max(1, (int) $rx_cron_apply[3]);
        $rx_cron_ok   = updateCronSchedule($rx_cron_key, ['unit' => $rx_cron_unit, 'value' => $rx_cron_val]);
        if (function_exists('activecron')) {
            try { @activecron(); } catch (Throwable $rx_cron_e) {}
        }
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $rx_cron_ok ? "✅ ذخیره شد" : "❌ ذخیره نشد",
                'show_alert' => false,
                'cache_time' => 1,
            ]);
        }
        step('admin_nav_cron_jobs', $from_id);
        nm_adminInstantReply($from_id, "🕚 زمان‌بندی کرون‌ها\n\nبرای تغییر بازهٔ اجرای هر کرون روی ⚙️ تنظیمات همان ردیف بزنید.", buildCronJobsKeyboard(), 'HTML');
    }
} elseif (preg_match('/^cronjob_sethour-(lottery|statusday)$/', $datain, $rx_cron_h) && $adminrulecheck['rule'] == "administrator") {
    $rx_h_field = $rx_cron_h[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
    $rx_h_now   = (int) ($setting[$rx_h_field] ?? 0);
    step("cronjob_get_hour-{$rx_cron_h[1]}", $from_id);
    nm_adminInstantReply($from_id, "🕛 ساعت اجرای این کرون را به‌صورت عددی بین <b>0</b> تا <b>23</b> ارسال کنید (به وقت تهران).\n\nساعت فعلی: <b>{$rx_h_now}:00</b>", $backadmin, 'HTML');
} elseif (preg_match('/^cronjob_get_hour-(lottery|statusday)$/', (string) ($user['step'] ?? ''), $rx_cron_hs) && $adminrulecheck['rule'] == "administrator") {
    $rx_h_field = $rx_cron_hs[1] === 'lottery' ? 'lottery_hour' : 'statusday_hour';
    if (!ctype_digit((string) $text) || (int) $text < 0 || (int) $text > 23) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط یک عدد بین 0 تا 23 ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    update("setting", $rx_h_field, (int) $text, null, null);
    step('admin_nav_cron_jobs', $from_id);
    nm_adminInstantReply($from_id, "✅ ساعت اجرا روی <b>" . (int) $text . ":00</b> تنظیم شد.", buildCronJobsKeyboard(), 'HTML');
} elseif ($text == "خروجی کاربران" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("user", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM user";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "users_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی دیتای کاربران");
    unlink($filename);
} elseif ($text == "خروجی سفارشات" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("invoice", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM invoice";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "invoice_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی سفارشات کاربران");
    unlink($filename);
} elseif ($text == "خروجی گرفتن پرداخت ها" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("Payment_report", "*", null, null, "count");
    if ($counttable == 0) {
        nm_adminInstantReply($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM Payment_report";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "Payment_report_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی پرداختی های کاربران");
    unlink($filename);
} elseif (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    step("descriptionsrequsts", $from_id);
    update("user", "Processing_value", $requestcheck['username'], "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['requestadmin'], $backuser, 'HTML');
} elseif ($user['step'] == "descriptionsrequsts") {
    nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['accecptreqests'], $keyboardadmin, 'HTML');
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    update("cancel_service", "status", "reject", "username", $user['Processing_value']);
    update("cancel_service", "description", $text, "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], "❌ کاربری گرامی درخواست حذف شما با نام کاربری  {$user['Processing_value']} موافقت نگردید.

        دلیل عدم تایید : $text", null, 'HTML');
} elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $nameloc = select("invoice", "*", "username", $requestcheck['username'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $requestcheck['username']);
    $stmt = $pdo->prepare("SELECT  SUM(price) FROM service_other WHERE username = :username AND type != 'change_location' AND type != 'extend_user' LIMIT 1");
    $stmt->bindParam(':username', $nameloc['username']);
    $stmt->execute();
    $sumproduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['data_limit'] == null && $DataUserOut['expire'] == null) {
        nm_adminInstantReply($from_id, "❌ به دلیل نامحدود بودن حجم و زمان امکان حذف سرویس وجود ندارد. ", null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        $pricelast = $invoice['price_product'];
    } elseif ($DataUserOut['data_limit'] == null) {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        if ($serviceTime > 0) {
            $pricetime = safe_divide($nameloc['price_product'], $serviceTime, 0) + intval($sumproduct['SUM(price)']);
            $pricelast = (($DataUserOut['expire'] - time()) / 86400) * $pricetime;
        } else {
            $pricelast = 0;
        }
    } elseif ($DataUserOut['expire'] == null) {
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        if ($dataLimit > 0) {
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeDivisor = $dataLimit / pow(1024, 3);
            $volumeleft = $volumeDivisor > 0 ? safe_divide($volumelefts, $volumeDivisor, 0) : 0;
            $pricelast = round($volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    } else {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        $volumeDivisor = $dataLimit / pow(1024, 3);
        if ($serviceTime > 0 && $volumeDivisor > 0) {
            $timeleft = safe_divide(round(($DataUserOut['expire'] - time()) / 86400, 0), $serviceTime, 0);
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeleft = safe_divide($volumelefts, $volumeDivisor, 0);
            $pricelast = round($timeleft * $volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    }
    $pricelast = intval($pricelast);
    if (intval($pricelast) != 0) {


        $stmtAtomicRefund = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
        $stmtAtomicRefund->bindValue(':delta', (int) $pricelast, PDO::PARAM_INT);
        $stmtAtomicRefund->bindValue(':uid', $nameloc['id_user'], PDO::PARAM_STR);
        $stmtAtomicRefund->execute();
        sendmessage($nameloc['id_user'], "💰کاربر گرامی مبلغ $pricelast تومان به موجودی شما اضافه گردید.", null, 'HTML');
    }
    $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    nm_adminInstantReply($from_id, "❌ مبلغ $pricelast تومان به موجودی کاربر اضافه گردید.", null, 'HTML');
    sendmessage($nameloc['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$nameloc['username']} موافقت گردید.", null, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $pricelast تومان
👤 نام کاربری : {$requestcheck['username']}
        آیدی عددی درخواست کننده کنسل کردن : {$nameloc['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/remoceserviceadminmanual-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $ManagePanel->RemoveUser($invoice['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($invoice['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$invoice['username']} موافقت گردید.", null, 'HTML');
    nm_adminInstantReply($from_id, "📌 مبلغ  برای بازگشت وجه را ارسال نمایید", $backadmin, 'HTML');
    step("getpricebackremove", $from_id);
} elseif ($user['step'] == "getpricebackremove") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");

    $stmtAtomicRefund2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicRefund2->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomicRefund2->bindValue(':uid', $invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicRefund2->execute();
    sendmessage($invoice['id_user'], "💰کاربر گرامی مبلغ $text تومان به موجودی شما اضافه گردید.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت به حساب کاربر اضافه گردید.", $keyboardadmin, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد

اطلاعات کاربر تایید کننده  :

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $text تومان
👤 نام کاربری : {$invoice['username']}
آیدی عددی درخواست کننده کنسل کردن : {$invoice['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^nmrefokdef_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    $stmtClaimDefault = $pdo->prepare(
        "UPDATE invoice SET Status = 'removebyuser' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'"
    );
    $stmtClaimDefault->bindValue(':inv', $nm_inv_id, PDO::PARAM_STR);
    $stmtClaimDefault->execute();
    if ($stmtClaimDefault->rowCount() === 0) {
        if (function_exists('rx_log_event')) {
            rx_log_event('NM_REFUND_RACE', 'Default refund raced; dropping duplicate', [
                'invoice' => $nm_inv_id,
                'admin'   => $from_id,
            ]);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $nm_target_user = select("user", "*", "id", $nm_invoice['id_user'], "select");
    $nm_refund_amount = (float)($nm_invoice['price_product'] ?? 0);
    $stmtAtomicNmRefund = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicNmRefund->bindValue(':delta', (int) round($nm_refund_amount), PDO::PARAM_INT);
    $stmtAtomicNmRefund->bindValue(':uid', $nm_invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicNmRefund->execute();
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_approved_default', ['refund' => $nm_refund_amount, 'admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "✅ سرویس انبار از لیست فعال خارج شد و مبلغ " . number_format($nm_refund_amount) . " تومان به کیف پول شما برگشت خورد.", null, 'HTML');
    $nm_admin_done = "✅ بازگشت وجه تأیید شد.\n\n💰 مبلغ: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nm_admin_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nm_admin_done, $keyboardadmin, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'تأیید شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
    if (!empty($setting['Channel_Report'])) {
        $nm_report_payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "⭕️ ادمین درخواست بازگشت وجه انبار را تأیید کرد\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ بازگشتی: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $nm_report_payload['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nm_report_payload);
    }
} elseif (preg_match('/^nmrefcustom_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("user", "Processing_value", $nm_inv_id, "id", $from_id);
    step("nm_getpricebackrefund", $from_id);
    nm_adminInstantReply($from_id, "📌 مبلغی که باید به کیف‌پول کاربر اضافه شود را به‌صورت عدد (تومان) ارسال کنید.\n\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>\n💰 مبلغ پیش‌فرض پرداختی: " . number_format((float)($nm_invoice['price_product'] ?? 0)) . " تومان", $backadmin, 'HTML');
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'مبلغ موردنظر را ارسال کنید',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif ($user['step'] == "nm_getpricebackrefund" && $adminrulecheck['rule'] == "administrator") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار واردشده معتبر نیست. لطفاً فقط عدد ارسال کنید.', $backadmin, 'HTML');
        return;
    }
    $nm_inv_id = $user['Processing_value'];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده یا نامعتبر است.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }


    $stmtClaimCustom = $pdo->prepare(
        "UPDATE invoice SET Status = 'removebyuser' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'"
    );
    $stmtClaimCustom->bindValue(':inv', $nm_inv_id, PDO::PARAM_STR);
    $stmtClaimCustom->execute();
    if ($stmtClaimCustom->rowCount() === 0) {
        if (function_exists('rx_log_event')) {
            rx_log_event('NM_REFUND_RACE', 'Custom refund raced; dropping duplicate', [
                'invoice' => $nm_inv_id,
                'admin'   => $from_id,
            ]);
        }
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده یا نامعتبر است.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $nm_target_user = select("user", "*", "id", $nm_invoice['id_user'], "select");
    $nm_refund_amount = intval($text);
    $stmtAtomicNmRefundCustom = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $stmtAtomicNmRefundCustom->bindValue(':delta', (int) $nm_refund_amount, PDO::PARAM_INT);
    $stmtAtomicNmRefundCustom->bindValue(':uid', $nm_invoice['id_user'], PDO::PARAM_STR);
    $stmtAtomicNmRefundCustom->execute();
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_approved_custom', ['refund' => $nm_refund_amount, 'admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "✅ سرویس انبار از لیست فعال خارج شد و مبلغ " . number_format($nm_refund_amount) . " تومان به کیف پول شما برگشت خورد.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($nm_refund_amount) . " تومان به حساب کاربر اضافه شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if (!empty($setting['Channel_Report'])) {
        $nm_report_payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "⭕️ ادمین درخواست بازگشت وجه انبار را با مبلغ دلخواه تأیید کرد\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ بازگشتی: " . number_format($nm_refund_amount) . " تومان\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $nm_report_payload['message_thread_id'] = $otherreport;
        telegram('sendmessage', $nm_report_payload);
    }
} elseif (preg_match('/^nmrefreject_([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $nm_inv_id = $dataget[1];
    $nm_invoice = select("invoice", "*", "id_invoice", $nm_inv_id, "select");
    if (!is_array($nm_invoice) || (string)($nm_invoice['Status'] ?? '') !== 'nm_refund_pending') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده یا نامعتبر است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("invoice", "Status", "active", "id_invoice", $nm_inv_id);
    if (function_exists('nmStockLog')) {
        try { nmStockLog(null, $nm_invoice['id_user'], $nm_inv_id, 'refund_rejected', ['admin' => $from_id]); } catch (Throwable $e) {}
    }
    sendmessage($nm_invoice['id_user'], "❌ درخواست بازگشت وجه شما برای سرویس انبار توسط ادمین رد شد. سرویس همچنان فعال است.", null, 'HTML');
    $nm_admin_done = "❌ درخواست بازگشت وجه رد شد.\n\n👤 کاربر: <code>" . $nm_invoice['id_user'] . "</code>\n🧾 کد سرویس: <code>" . $nm_inv_id . "</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $nm_admin_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $nm_admin_done, $keyboardadmin, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'رد شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif (preg_match('/^mafurefauto-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $mafu_inv_id = $dataget[1];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'فاکتور پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }

    $mafu_price = (int)($mafu_invoice['price_product'] ?? 0);
    $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
    $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
    $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');

    if ($mafu_price <= 0 || $mafu_user_id === '' || $mafu_username_svc === '') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات فاکتور ناقص است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    $mafu_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')");
    $mafu_claim->bindValue(':inv', $mafu_inv_id, PDO::PARAM_STR);
    $mafu_claim->execute();
    if ($mafu_claim->rowCount() === 0) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً پردازش شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }


    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser')) {
        try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
    }


    $mafu_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $mafu_bal->bindValue(':delta', $mafu_price, PDO::PARAM_INT);
    $mafu_bal->bindValue(':uid', $mafu_user_id, PDO::PARAM_STR);
    $mafu_bal->execute();

    sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_price) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

    $mafu_done = "✅ بازگشت وجه خودکار انجام شد.\n\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>";
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        Editmessagetext($from_id, $message_id, $mafu_done, null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $mafu_done, $keyboardadmin ?? null, 'HTML');
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'بازگشت وجه انجام شد',
        'show_alert' => false,
        'cache_time' => 2,
    ]);

    if (!empty($setting['Channel_Report'])) {
        $mafu_rep = [
            'chat_id'    => $setting['Channel_Report'],
            'text'       => "⭕️ بازگشت وجه خودکار (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $mafu_rep);
    }
} elseif (preg_match('/^mafurefmanu-([A-Za-z0-9_\-]+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {

    $mafu_inv_id = $dataget[1];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'فاکتور پیدا نشد.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }

    update("user", "Processing_value", $mafu_inv_id, "id", $from_id);
    step("mafurefamount", $from_id);

    $mafu_default = number_format((int)($mafu_invoice['price_product'] ?? 0));
    nm_adminInstantReply(
        $from_id,
        "📌 مبلغ بازگشتی برای این فاکتور را به‌صورت عدد (تومان) ارسال کنید.\n\n🆔 فاکتور: <code>$mafu_inv_id</code>\n💵 مبلغ خرید اولیه: $mafu_default تومان\n📛 سرویس: <code>" . htmlspecialchars((string)($mafu_invoice['username'] ?? '')) . "</code>",
        $backadmin ?? null,
        'HTML'
    );
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'مبلغ موردنظر را ارسال کنید',
        'show_alert' => false,
        'cache_time' => 2,
    ]);
} elseif (($user['step'] ?? '') == "mafurefamount" && $adminrulecheck['rule'] == "administrator") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار معتبر نیست. فقط عدد ارسال کنید.', $backadmin ?? null, 'HTML');
        return;
    }

    $mafu_inv_id = (string)$user['Processing_value'];
    $mafu_invoice = select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
    if (!is_array($mafu_invoice)) {
        nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }
    $mafu_status = (string)($mafu_invoice['Status'] ?? '');
    if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }

    $mafu_amount = intval($text);
    $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
    $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
    $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');


    $mafu_claim = $pdo->prepare("UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')");
    $mafu_claim->bindValue(':inv', $mafu_inv_id, PDO::PARAM_STR);
    $mafu_claim->execute();
    if ($mafu_claim->rowCount() === 0) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً پردازش شده است.", $keyboardadmin ?? null, 'HTML');
        step("home", $from_id);
        return;
    }


    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $mafu_username_svc !== '') {
        try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
    }


    $mafu_bal = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
    $mafu_bal->bindValue(':delta', $mafu_amount, PDO::PARAM_INT);
    $mafu_bal->bindValue(':uid', $mafu_user_id, PDO::PARAM_STR);
    $mafu_bal->execute();

    sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

    nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول کاربر اضافه شد و سرویس حذف گردید.", $keyboardadmin ?? null, 'HTML');
    step("home", $from_id);

    if (!empty($setting['Channel_Report'])) {
        $mafu_rep = [
            'chat_id'    => $setting['Channel_Report'],
            'text'       => "⭕️ بازگشت وجه دستی (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_amount) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
        telegram('sendmessage', $mafu_rep);
    }
} elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "روز", $backadmin, 'HTML');
    step("getcronvolumere", $from_id);
} elseif ($user['step'] == "getcronvolumere") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "cronvolumere", $text);
} elseif ($datain == "setting_on_holdcron" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "در این بخش باید تغیین کنید که اگر کاربر بعد از چند روز به کانفیگ خود وصل نشد و در وضعیت on_hold بود به کاربر پیام دهد" . $setting['on_hold_day'] . "روز", $backadmin, 'HTML');
    step("on_hold_day", $from_id);
} elseif ($user['step'] == "on_hold_day") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "on_hold_day", $text);
}
/* ---- settings.php ---- */
if (!function_exists('rx_iranpay_label')) {
    function rx_iranpay_label($datatextbot, $key, $fallback)
    {
        $name = (is_array($datatextbot) && isset($datatextbot[$key])) ? trim((string)$datatextbot[$key]) : '';
        if ($name !== '') {
            return "📌 " . $name;
        }
        return $fallback;
    }
}

if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "روز", $backadmin, 'HTML');
    step("getdaycron", $from_id);
} elseif ($user['step'] == "getdaycron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text);
} elseif ($text == "🌐 ثبت آدرس API ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "urlpaymenttron", "select");
    $currentUrl = is_array($PaySetting) && isset($PaySetting['ValuePay']) ? $PaySetting['ValuePay'] : 'تنظیم نشده';
    $recommendedUrl = (defined('TRONADO_ORDER_TOKEN_ENDPOINTS') && isset(TRONADO_ORDER_TOKEN_ENDPOINTS[0]))
        ? TRONADO_ORDER_TOKEN_ENDPOINTS[0]
        : 'https://bot.tronado.cloud/api/v1/Order/GetOrderToken';
    $texttronseller = "🌐 آدرس API مورد استفاده برای اتصال به ترنادو را ارسال کنید.\n\nآدرس فعلی: {$currentUrl}\n\nℹ️ پیشنهاد ویژه برای ترنادو:\n{$recommendedUrl}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('urlpaymenttron', $from_id);
} elseif ($user['step'] == "urlpaymenttron") {
    $submittedUrl = trim($text);
    $oldDomain = 'tronseller.storeddownloader.fun';
    if (stripos($submittedUrl, $oldDomain) !== false) {
        $warningMessage = "⚠️ دامنه قدیمی ترنادو هنوز استفاده می‌شود. لطفاً آدرس جدید را وارد کنید.";
        nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $submittedUrl, "NamePay", "urlpaymenttron");
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == "ویرایش نام" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "دسته بندی جدید خود را ارسال کنید", $backadmin, 'HTML');
    step('changecategoryhelp', $from_id);
} elseif ($user['step'] == "changecategoryhelp") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ نام دسته آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش توضیحات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "توضیحات جدید را ارسال کنید", $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش رسانه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "تصویر یا فیلم جدید را ارسال کنید", $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    }
    nm_adminInstantReply($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰  غیرفعالسازی  نمایش شماره کارت") {
    nm_adminInstantReply($from_id, "برای تمامی کاربران غیرفعال گردید یا کاربران جدید؟
    کاربران جدید 0
    همه کاربران 1
    2 کاربران بجز نمایندگان", null, 'HTML');
    step('showcardallusers', $from_id);
} elseif ($user['step'] == "showcardallusers") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['disableshowcardstatus'], null, 'HTML');
    if (intval($text) == "1") {
        update("user", "cardpayment", "0");
        update("setting", "showcard", "0");
    } elseif (intval($text) == 2) {
        update("user", "cardpayment", "0", "agent", "f");
        update("setting", "showcard", "0");
    } else {
        update("setting", "showcard", "0");
    }
} elseif ($text == "💰 فعالسازی نمایش شماره کارت") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
    update("user", "cardpayment", "1");
    update("setting", "showcard", "1");
} elseif ($text == "🔋 روش تمدید سرویس" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Methodextend, 'HTML');
    step('updateextendmethod', $from_id);
} elseif ($user['step'] == "updateextendmethod") {
    $aarayvalid = array(
        'ریست حجم و زمان',
        'اضافه شدن زمان و حجم به ماه بعد',
        'ریست زمان و اضافه کردن حجم قبلی',
        'ریست شدن حجم و اضافه شدن زمان',
        'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده'
    );
    if (!in_array($text, $aarayvalid)) {
        nm_adminInstantReply($from_id, "❌ روش تمدید نامعتبر می باشد از لیست زیر روش تمدید درست را انتخاب کنید", null, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "Methodextend", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
    step('home', $from_id);
} elseif ($text == "♻️ تایید خودکار رسید" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    if ($paymentverify == "onauto") {
        nm_adminInstantReply($from_id, "❌ ابتدا تایید خودکار بدون بررسی را خاموش کنید.", null, 'HTML');
        return;
    }
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    $card_Status_auto = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['autoconfirmcard'], $card_Status_auto, 'HTML');
} elseif ($datain == "onautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffautoconfirmcard'], null);
} elseif ($datain == "offautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "onautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonautoconfirmcard'], null);
} elseif ($text == "/token" && $adminrulecheck['rule'] == "administrator") {
    // کلید integration مستقل از رمز ورود پنل و secret وبهوک تلگرام است.
    try {
        $secretRaw = (string)($pdo->query("SELECT integration_secret_token FROM setting LIMIT 1")->fetchColumn() ?: '');
        if ($secretRaw === '') {
            $secretRaw = bin2hex(random_bytes(32));
            rx_require_schema($pdo,[],['setting'=>['integration_secret_token']]);
            $pdo->prepare("UPDATE setting SET integration_secret_token = ?")->execute([$secretRaw]);
        }
        $secret_key = htmlspecialchars($secretRaw, ENT_QUOTES, 'UTF-8');
        nm_adminInstantReply($from_id, "🔐 توکن integration مستقل (برای هدر Token یا Bearer):\n<code>$secret_key</code>", null, 'HTML');
    } catch (Throwable $e) {
        nm_adminInstantReply($from_id, "❌ تولید توکن ناموفق بود؛ ابتدا table.php را یک‌بار اجرا کنید.", null, 'HTML');
    }
} elseif ($text == "✅ فعالسازی پنل تحت وب" && $adminrulecheck['rule'] == "administrator") {
    // رمز hash شده قابل بازیابی نیست؛ هر بار یک رمز موقت تازه صادر می‌شود.
    $randomString = bin2hex(random_bytes(10));
    $passwordHash = password_hash($randomString, PASSWORD_DEFAULT);
    update("admin", "username", $from_id, "id_admin", $from_id);
    update("admin", "password", $passwordHash, "id_admin", $from_id);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تنظیم آیپی ورود", 'callback_data' => 'iploginset'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  پنل تحت وب شما با موفقیت فعال گردید.

🔗آدرس ورود : https://$domainhosts/panel
👤نام کاربری :  <code>$from_id</code>
🔑رمز عبور :  <code>$randomString</code>", $keyboardstatistics, 'HTML');
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['towstep'], $backadmin, 'HTML');
    step('getusernameconfig', $from_id);
} elseif ($user['step'] == "getusernameconfig") {
    $text = strtolower($text);
    if (!preg_match('/^\w{3,32}$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
        return;
    }
    if (in_array($text, $usernameinvoice)) {
        nm_adminInstantReply($from_id, "❌ این نام کاربری از قبل داخل ربات وجود دارد.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    update("user", "Processing_value_tow", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fourstep'], $json_list_product_list_admin, 'HTML');
    step('stependforaddorder', $from_id);
} elseif ($user['step'] == "stependforaddorder") {
    $sql = "SELECT * FROM product  WHERE name_product = :name_product AND (Location = :location OR Location = '/all') LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_tow'], $user['Processing_value_one']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
        if ($info_product['Service_time'] == 0) {
            $datetimestep = 0;
        } else {
            $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
        }
        $datac = array(
            'expire' => $datetimestep,
            'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
            'from_id' => $user['Processing_value'],
            'username' => "",
            'type' => 'buy'
        );
        $DataUserOut = $ManagePanel->createUser($user['Processing_value_tow'], $info_product['code_product'], $user['Processing_value_one'], $datac);
        if ($DataUserOut['username'] == null) {
            nm_adminInstantReply($from_id, "❌ خطایی در ساخت اشتراک رخ داده است برای رفع مشکل علت خطا را در گروه گزارش تان بررسی کنید", null, 'HTML');
            $DataUserOut['msg'] = redfox_remote_error_summary($DataUserOut);
            $texterros = "
خطا در ساخت کافنیگ از پنل ادمین
✍️ دلیل خطا :
{$DataUserOut['msg']}
آیدی ادمین : $from_id
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
    } else {
        $DataUserOut['configs'] = $DataUserOut['links'];
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status,:notifctions)");
    $Status = "active";
    $stmt->bindParam(':id_user', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':id_invoice', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
    $stmt->bindParam(':time_sell', $date, PDO::PARAM_STR);
    $stmt->bindParam(':Service_location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->bindParam(':name_product', $info_product['name_product'], PDO::PARAM_STR);
    $stmt->bindParam(':price_product', $info_product['price_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $info_product['Volume_constraint'], PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $info_product['Service_time'], PDO::PARAM_STR);
    $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
    $stmt->bindParam(':notifctions', $notifctions, PDO::PARAM_STR);
    $stmt->execute();
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $DataUserOut['subscription_url'] : "";
    $config = "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($DataUserOut['configs'])) {
        foreach ($DataUserOut['configs'] as $link) {
            $config .= "\n" . $link;
        }
    }
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$DataUserOut['username']}</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $DataUserOut['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $DataUserOut['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $DataUserOut['configs'], $output_config_link, $DataUserOut['username'], $Shoppinginfo, $textcreatuser, $randomString, $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['addorder']['fivestep'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل موجودی خرید عمده" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر  خرید انبوه کند را ارسال کنید.

مبلغ فعلی : $PaySetting";
    nm_adminInstantReply($from_id, $textmin, $backadmin, 'HTML');
    step('minbalancebulk', $from_id);
} elseif ($user['step'] == "minbalancebulk") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
    update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
    step('home', $from_id);
} elseif (preg_match('/showcarduser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($id_user, "💳 کاربر عزیز شماره کارت برای شما فعال شد هم اکنون می توانید خرید خود را انجام دهید.", null, 'HTML');
    nm_adminInstantReply($from_id, "✅  شماره کارت فعال گردید", null, 'HTML');
    update("user", "cardpayment", "1", "id", $id_user);
} elseif (preg_match('/carduserhide-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    nm_adminInstantReply($from_id, "✅  شماره کارت غیرفعال گردید", null, 'HTML');
    update("user", "cardpayment", "0", "id", $id_user);
} elseif ($text == "❌ حذف شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 شماره کارتی که می خواهید حذف کنید را ارسال نمایید.", $list_card_remove, 'HTML');
    step('getcardremove', $from_id);
} elseif ($user['step'] == "getcardremove") {
    $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
    $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت حذف گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/^rejectrequesta_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $confirmKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، رد کن", 'callback_data' => "cfmreja_" . $id_user],
                ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textConfirm .= "\n\n⚠️ آیا از <b>رد</b> این درخواست اطمینان دارید؟";
    Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "برای تایید نهایی روی «بله، رد کن» بزنید.",
        'show_alert' => false,
        'cache_time' => 1,
    ));
} elseif (preg_match('/^cfmreja_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);

    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'reject',
            ':type' => 'None',
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtBalance = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
        $stmtBalance->execute([
            ':amount' => intval($setting['agentreqprice']),
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست رد شده.", 'callback_data' => "reject"]],
        ]
    ]);
    nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد گردید.", null, 'HTML');
    sendmessage($id_user, "❌ کاربر گرامی درخواست نمایندگی شما رد گردید.", null, 'HTML');
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: رد شد.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست با موفقیت رد شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/^addagentrequest_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $confirmKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، تایید کن", 'callback_data' => "cfmacea_" . $id_user],
                ['text' => "🔙 لغو", 'callback_data' => "cnclagentreq_" . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textConfirm = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textConfirm .= "\n\n⚠️ آیا از <b>تایید</b> این درخواست اطمینان دارید؟";
    Editmessagetext($from_id, $message_id, $textConfirm, $confirmKeyboard);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "برای تایید نهایی روی «بله، تایید کن» بزنید.",
        'show_alert' => false,
        'cache_time' => 1,
    ));
} elseif (preg_match('/^cnclagentreq_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست قبلاً بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $id_user],
                ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $id_user],
            ],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $id_user],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardmanage);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "عملیات لغو شد.",
        'show_alert' => false,
        'cache_time' => 1,
    ));
} elseif (preg_match('/^cfmacea_(\w+)/', $datain, $datagetr)) {

    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select", ['cache' => false]);
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $defaultAgentType = 'n';
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'accept',
            ':type' => $defaultAgentType,
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtUser = $pdo->prepare("UPDATE user SET agent = :agent, expire = NULL WHERE id = :id");
        $stmtUser->execute([
            ':agent' => $defaultAgentType,
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    sendmessage($id_user, "✅ کاربر گرامی با درخواست نمایندگی شما موافقت و شما نماینده شدید.", null, 'HTML');
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    $agentTypeButtons = [];
    foreach ($agentTypeLabels as $typeCode => $label) {
        $buttonText = ($typeCode === $defaultAgentType ? "✅ " : "") . $label;
        $agentTypeButtons[] = [
            'text' => $buttonText,
            'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
        ];
    }
    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
            $agentTypeButtons,
            [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
            [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$defaultAgentType]})";
    $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست تایید شد و نماینده عادی فعال شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/^setagenttype_(n|n2)_(\w+)/', $datain, $datagetr)) {
    $selectedType = $datagetr[1];
    $id_user = $datagetr[2];
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];
    if (!array_key_exists($selectedType, $agentTypeLabels)) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['agent']['invalidtypeagent'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "agent", $selectedType, "id", $id_user);
    update("Requestagent", "type", $selectedType, "id", $id_user);
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if ($request_agent) {
        $agentTypeButtons = [];
        foreach ($agentTypeLabels as $typeCode => $label) {
            $buttonText = ($typeCode === $selectedType ? "✅ " : "") . $label;
            $agentTypeButtons[] = [
                'text' => $buttonText,
                'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
            ];
        }
        $keyboardreject = json_encode([
            'inline_keyboard' => [
                [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
                $agentTypeButtons,
                [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
                [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
        $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$selectedType]})";
        $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
        Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "نوع نماینده به {$agentTypeLabels[$selectedType]} تغییر کرد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
} elseif ($datain == "iranpay3setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $iranpaykeyboard, 'HTML');
} elseif ($text == "وضعیت  درگاه ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    $textternado = "در این بخش می توانید درگاه ترنادو را خاموش یا روشن کنید";
    nm_adminInstantReply($from_id, $textternado, $statusternadoo, 'HTML');
} elseif ($datain == "onternado") {
    update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش گردید", $statusternadoo);
} elseif ($datain == "offternado") {
    update("PaySetting", "ValuePay", "onternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن گردید", $statusternadoo);
} elseif ($text == "🔑 ثبت API Key ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttronseller = "🔑 کلید API ترنادو خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiternado', $from_id);
} elseif ($user['step'] == "apiternado") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiternado");
    step('home', $from_id);
} elseif ($datain == "affilnecurrencysetting") {
    nm_adminInstantReply($from_id, "یک گزینه را انتخاب کنید", $tronnowpayments, 'HTML');
} elseif ($text == "🗂 نام درگاه کارت به کارت") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttocart", $from_id);
} elseif ($user['step'] == "getnamecarttocart") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "carttocart");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه nowpayment") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamenowpayment", $from_id);
} elseif ($user['step'] == "getnamenowpayment") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "textsnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ریالی بدون احراز") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttopaynotverify", $from_id);
} elseif ($user['step'] == "getnamecarttopaynotverify") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textpaymentnotverify");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه   plisio") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpayment", $from_id);
} elseif ($user['step'] == "gettextnowpayment") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $NowPaymentsManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه رمز ارز آفلاین") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpaymentTRON", $from_id);
} elseif ($user['step'] == "gettextnowpaymentTRON") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $tronnowpayments, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpaymenttron");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay2", $from_id);
} elseif ($user['step'] == "gettextiranpay2") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay2");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه استار") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextstartelegram", $from_id);
} elseif ($user['step'] == "gettextstartelegram") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "text_star_telegram");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه ترنادو") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه ترنادو را ارسال کنید.";
    nm_adminInstantReply($from_id, $prompt, $backadmin, 'HTML');
    step("gettextiranpay3", $from_id);
} elseif ($user['step'] == "gettextiranpay3") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $trnado, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay3");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay1", $from_id);
} elseif ($user['step'] == "gettextiranpay1") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $iranpaykeyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay1");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه آقای پرداخت") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextaqayepardakht", $from_id);
} elseif ($user['step'] == "gettextaqayepardakht") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $aqayepardakht, 'HTML');
    update("textbot", "text", $text, "id_text", "aqayepardakht");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه زرین پال") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpal", $from_id);
} elseif ($user['step'] == "gettextzarinpal") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpal, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpal");
    step("home", $from_id);
} elseif ($text == "⚙️  اینباند اکانت غیرفعال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocoldisable', $from_id);
} elseif ($user['step'] == "getprotocoldisable") {
    global $json_list_marzban_panel_inbounds;
    $protocol = ["vless", "vmess", "trojan", "shadowsocks"];
    if (!in_array($text, $protocol)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['invalidprotocol'], null, 'HTML');
        return;
    }
    $getinbounds = getinbounds($user['Processing_value'])[$text];
    $list_marzban_panel_inbounds = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($getinbounds as $button) {
        $list_marzban_panel_inbounds['keyboard'][] = [
            ['text' => $button['tag']]
        ];
    }
    $list_marzban_panel_inbounds['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    $json_list_marzban_panel_inbounds = json_encode($list_marzban_panel_inbounds);
    update("user", "Processing_value_one", $text, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['getInbound'], $json_list_marzban_panel_inbounds, 'HTML');
    step('getInbounddisable', $from_id);
} elseif ($user['step'] == "getInbounddisable") {
    nm_adminInstantReply($from_id, "نام اینباند با موفقیت ذخیره گردید", $optionMarzban, 'HTML');
    $textpro = "{$user['Processing_value_one']}*$text";
    update("marzban_panel", "inbound_deactive", $textpro, "name_panel", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🗑 بهینه سازی ربات" && $adminrulecheck['rule'] == "administrator") {
    $textoptimize = "❌❌❌❌❌❌❌ متن زیر را با دقت بخوانید

📌 با تایید گزینه زیر عملیات زیر انجام خواهد شد. و قابل بازگشت نیستند

1 - سفارش های غیرفعال حذف خواهند شد
2 - سفارش های پرداخت نشده حذف خواهند شد.
3 - سفارش های حذف شده توسط ادمین
4 - حذف سرویس های تست غیرفعال
5 - سفارش های حذف شده توسط کاربر
6 - سفارشاتی که زمان یا حجم شان تمام شده باشد
7 - فاکتورهای قدیمی پرداخت نشده (بیش از ۳۰ روز)

🛡 اگر در عملکرد ربات با باگ یا مشکلی مواجه شدید، از طریق گیت هاب یا گروه رد فاکس اطلاع رسانی کنید
<a href=\"https://github.com/hojjatrad/RedFox-Security-Hardened\">لینک گیت هاب</a>";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و  بهینه سازی", 'callback_data' => 'optimizebot'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textoptimize, $Response, 'HTML');
} elseif ($text == "💀 بازنشانی ربات" && $adminrulecheck['rule'] == "administrator") {
    global $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        nm_adminInstantReply($from_id, "⚠️ فقط ادمین اصلی می‌تواند این بخش را مشاهده کند.", null, 'HTML');
        return;
    }
    $resetWarning = "⚠️ هشدار مهم\n\nبا تایید بازنشانی، تمامی جداول پایگاه داده حذف و مجدداً ساخته خواهند شد. این عملیات غیرقابل بازگشت است.\n\nآیا از انجام این کار مطمئن هستید؟";
    $resetKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، مطمئن هستم", 'callback_data' => 'resetbot_confirm'],
                ['text' => "❌ خیر", 'callback_data' => 'resetbot_cancel'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $resetWarning, $resetKeyboard, 'HTML');
} elseif ($datain == "resetbot_cancel") {
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "عملیات لغو شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "❌ عملیات بازنشانی لغو شد.", null);
} elseif ($datain == "resetbot_confirm" && $adminrulecheck['rule'] == "administrator") {
    global $pdo, $domainhosts, $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "❌ شما اجازه انجام این عملیات را ندارید.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "⏳ در حال بازنشانی...",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "⏳ عملیات بازنشانی ربات آغاز شد. لطفاً منتظر بمانید...", null);

    $dropError = new RuntimeException('حذف جداول از مسیر ربات غیرفعال است؛ فقط Restore/Reset کنترل‌شده CLI مجاز است.');

    if ($dropError !== null) {
        file_put_contents(REFACTORED_LEGACY_ROOT . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] DROP ERROR: " . redfox_exception_fingerprint($dropError) . PHP_EOL, FILE_APPEND);
        Editmessagetext($from_id, $message_id, "❌ خطا در حذف جداول. لطفاً فایل resetbot_error.log را بررسی کنید.", null);
        nm_adminInstantReply($from_id, "❌ عملیات بازنشانی به دلیل خطا در حذف جداول متوقف شد.", null, 'HTML');
        return;
    }

} elseif ($datain == "optimizebot") {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countunpiadorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countdisableorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'removebyadmin' OR Status = 'removedbyadmin')");
    $stmt->execute();
    $countremoveadminorder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countdisableordtester = (int)$stmt->fetchColumn();

    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countoldunpaid = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject')");
    $stmt->execute();
    $countpayexpired = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removedbyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removeTime'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removevolume'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyuser'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject')");
    $stmt->execute();

    $optimizebot = "✅ بهینه سازی با موفقیت انجام شد

📊 خلاصه عملیات:
✅ {$countunpiadorder} سفارش پرداخت نشده حذف گردید
✅ {$countdisableorder} سفارش غیرفعال حذف گردید
✅ {$countremoveadminorder} سفارش حذف شده توسط ادمین پاک گردید
✅ {$countdisableordtester} سرویس تست غیرفعال حذف گردید
✅ {$countoldunpaid} فاکتور تست پرداخت نشده پاک گردید
✅ {$countpayexpired} گزارش تراکنش منقضی/رد شده پاک گردید";

    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $optimizebot, null);
    }
    nm_adminInstantReply($from_id, $optimizebot, $setting_panel, 'HTML');

    $time = time();
    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_$time";
    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
} elseif ($datain == "settimecornvolume") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تنظیم کنید که اگر حجم کاربر به x رسید پیام اخطار ارسال شود. حجم را براساس گیگ ارسال نمایید.", $backadmin, 'HTML');
    step("getvolumewarn", $from_id);
} elseif ($user['step'] == "getvolumewarn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("setting", "volumewarn", $text);
    nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره شد", $setting_panel, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔧 ساخت کانفیگ دستی") {
    savedata("clear", "idpanel", $user['Processing_value']);
    nm_adminInstantReply($from_id, "📌در این بخش میتوانید یک سفارش را بطور دستی ایجاد و دریافت کنید
⚠️ در صورتی که می خواهید  کانفیگ به حساب کاربر اضافه شود و کاربر مدیریت کند باید از گزینه افزودن سفارش  استفاده نمایید.
- برای اضافه کردن کانفیگ ابتدا نام کاربری را ارسال نمایید.", $backadmin, 'HTML');
    step('getusernameconfigcr', $from_id);
} elseif ($user['step'] == "getusernameconfigcr") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['invalidusername'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    step('getcountcreate', $from_id);
    nm_adminInstantReply($from_id, "📌 تعداد کانفیگی که میخواهید ساخته شود را ارسال کنید حداکثر ۱۰ تا می توانید ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "getcountcreate") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) > 10 or intval($text) < 0) {
        nm_adminInstantReply($from_id, "❌ حداقل ۱ عدد و حداکثر می توانید ۱۰ عدد ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "count", $text);
    step('getvolumesconfig', $from_id);
    nm_adminInstantReply($from_id, "📌 حجم مصرفی اکانت را ارسال نمایید . حجم براساس گیگابایت است.", $backadmin, 'HTML');
} elseif ($user['step'] == "getvolumesconfig") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("user", "Processing_value_tow", $text, "id", $from_id);
    nm_adminInstantReply($from_id, "📌 زمان سرویس را ارسال نمایید زمان براساس روز است.", $backadmin, 'HTML');
    step("gettimeaccount", $from_id);
} elseif ($user['step'] == "gettimeaccount") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    if (intval($text) == 0) {
        $expire = 0;
    } else {
        $datetimestep = strtotime("+" . $text . "days");
        $expire = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $expire,
        'data_limit' => $user['Processing_value_tow'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => "$username",
        'type' => "new by admin $from_id"
    );
    $panel = select("marzban_panel", "*", "name_panel", $userdata['idpanel'], "select");
    for ($i = 0; $i < $userdata['count']; $i++) {
        $usernameconfig = $user['Processing_value_one'] . "_" . $i;
        $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = redfox_remote_error_summary($dataoutput);
            nm_adminInstantReply($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
            $texterros = "
⭕️ یک کاربر قصد دریافت اکانت داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$panel['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
        $randomString = bin2hex(random_bytes(5));
        $output_config_link = $panel['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $config = "";
        if ($panel['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "ibsng" || $panel['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($text) == 0)
            $text = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', "پلن دلخواه", $textcreatuser);
        $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $text, $textcreatuser);
        $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($panel['type'] == "Manualsale" || $panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
        }
        sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    $text_report = "";
    if (strlen($setting['Channel_Report']) > 0) {
        $text_report = " 🛍 ساخت کانفیگ توسط ادمین

نام کاربری کانفیگ : {$user['Processing_value_one']}
حجم کانفیگ  : {$user['Processing_value_tow']} گیگ
زمان کانفیگ : $text روز
آیدی عددی ادمین : $from_id
نام کاربری ادمین : $username
تعداد ساخت : {$userdata['count']}";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("user", "Processing_value", $userdata['idpanel'], "id", $from_id);
    step("home", $from_id);
} elseif ($text == "🛠 قابلیت های پنل") {
    nm_adminInstantReply($from_id, "🪚 برای استفاده از این قابلیت یکی از پنل های زیر را انتخاب نمایید", $json_list_marzban_panel, 'HTML');
    step('getlocoption', $from_id);
} elseif ($user['step'] == "getlocoption") {
    update("user", "Processing_value", $text, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $text, "select")['type'];
    if ($typepanel == "marzban") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "hiddify") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🖥 مدیریت نود ها" || $datain == "bakcnode") {
    if ($adminnumber != $from_id) {
        nm_adminInstantReply($from_id, "❌ این بخش فقط در دسترس ادمین اصلی است", null, 'HTML');
        return;
    }
    $nodes = Get_Nodes($user['Processing_value']);
    if (!empty($nodes['error'])) {
        nm_adminInstantReply($from_id, redfox_remote_error_summary($nodes), null, 'HTML');
        return;
    }
    if (!empty($nodes['status']) && $nodes['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodes['status']}", null, 'HTML');
        return;
    }
    $nodes = json_decode($nodes['body'], true);
    if (count($nodes) == 0) {
        nm_adminInstantReply($from_id, "❌  امکان مشاهده تنظیمات نود ها وجود ندارد", null, 'HTML');
        return;
    }
    $keyboardlistsnode['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "actionnode"],
        ['text' => "نام", 'callback_data' => "namenode"]
    ];
    foreach ($nodes as $result) {
        if (!isset($result['id']))
            continue;
        $keyboardlistsnode['inline_keyboard'][] = [
            ['text' => "مدیریت", 'callback_data' => "node_{$result['id']}"],
            ['text' => $result['name'], 'callback_data' => "node_{$result['id']}"],
        ];
    }
    $keyboardlistsnode = json_encode($keyboardlistsnode);
    if ($datain == "bakcnode") {
        Editmessagetext($from_id, $message_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode);
    } else {
        nm_adminInstantReply($from_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode, 'HTML');
    }
} elseif (preg_match('/^node_(.*)/', $datain, $dataget)) {
    $nodeid = $dataget[1];
    update("user", "Processing_value_one", $nodeid, "id", $from_id);
    $node = Get_Node($user['Processing_value'], $nodeid);
    if (!empty($node['error'])) {
        nm_adminInstantReply($from_id, redfox_remote_error_summary($node), null, 'HTML');
        return;
    }
    if (!empty($node['status']) && $node['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$node['status']}", null, 'HTML');
        return;
    }
    $nodeusage = Get_usage_Nodes($user['Processing_value']);
    if (!empty($nodeusage['error'])) {
        nm_adminInstantReply($from_id, redfox_remote_error_summary($nodeusage), null, 'HTML');
        return;
    }
    if (!empty($nodeusage['status']) && $nodeusage['status'] != 200) {
        nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodeusage['status']}", null, 'HTML');
        return;
    }
    $node = json_decode($node['body'], true);
    $nodeusage = json_decode($nodeusage['body'], true);
    foreach ($nodeusage['usages'] as $nodeusages) {
        if ($nodeusages['node_id'] == $nodeid) {
            $nodeusage = $nodeusages;
            break;
        }
    }
    $sumvolume = formatBytes($nodeusage['downlink'] + $nodeusage['uplink']);
    $textnode = "📌 اطلاعات نود

🖥 نام نود :  {$node['name']}
🌍 آیپی نود : {$node['address']}
🔻 پورت نود : {$node['port']}
🔺 پورت api نود : {$node['api_port']}
🔋جمع مصرف نود  : $sumvolume
🔄 ضریب مصرف نود : {$node['usage_coefficient']}
🔵 نسخه xray نود : {$node['xray_version']}
🟢 وضعیت نود : {$node['status']}
    ";
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🗂 تغییر نام نود", 'callback_data' => "changenamenode"],
                ['text' => "🔄 تغییر ضریب مصرف نود", 'callback_data' => "changecoefficient"],
            ],
            [
                ['text' => "🌍 تغییر آدرس ایپی نود", 'callback_data' => "changeipnode"],
                ['text' => "♻️ اتصال مجدد نود", 'callback_data' => "reconnectnode"],
            ],
            [
                ['text' => "❌ حذف نود", 'callback_data' => "removenode"],
            ],
            [
                ['text' => "🔙 بازگشت به لیست نود ها", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "changecoefficient") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 ضریب مصرف نودتان را ارسال نمایید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getusage_coefficient", $from_id);
} elseif ($user['step'] == "getusage_coefficient") {
    $config = array(
        'usage_coefficient' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅ ضریب مصرف نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changenamenode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 نام نودتان را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getnamenode", $from_id);
} elseif ($user['step'] == "getnamenode") {
    $config = array(
        'name' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  نام نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changeipnode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 آیپی نود را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getipnodeset", $from_id);
} elseif ($user['step'] == "getipnodeset") {
    $config = array(
        'address' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "✅  آدرس نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "reconnectnode") {
    reconnect_node($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "✅ اتصال مجدد نود انجام گردید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "removenode") {
    removenode($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    $textnode = "✅ نود با موفقیت حذف گردید";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($text == "💎 مالی" && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_finance', $from_id);
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != "onSwapinoBot" && $arzireyali1 != "offSwapinoBot") {
        update("PaySetting", "ValuePay", "onSwapinoBot", "NamePay", "statusSwapWallet");
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay2', "📌 ارزی ریالی اول"), 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay3', "📌 ارزی ریالی دوم"), 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay1', "📌ارزی ریالی سوم"), 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "💼 آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم رد فاکس هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status, 'HTML');
} elseif ($text == "🎁 کش بک تمدید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 مقدار درصدی که می خواهید حساب کاربر بعد از تمدید به عنوان هدیه شارژ شود را ارسال کنید.
⚠️ در صورتی که میخواهید غیرفعال باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('getpricecashback', $from_id);
} elseif ($user['step'] == "getpricecashback") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "price_cashback", $text);
    nm_adminInstantReply($from_id, "📌 نوع کاربری را انتخاب نمایید", rx_agentGroupKeyboard(false), 'HTML');
    step('getagent', $from_id);
} elseif ($user['step'] == "getagent") {
    $text = rx_resolveAgentGroup($text, ['f', 'n', 'n2']);
    if ($text === null) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر است", rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if ($text == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$text] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^editpayment-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "Cartstatus") {
        if ($value == "oncard") {
            $valuenew = "offcard";
        } else {
            $valuenew = "oncard";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "Cartstatus");
    } elseif ($type == "plisio") {
        if ($value == "onnowpayment") {
            $valuenew = "offnowpayment";
        } else {
            $valuenew = "onnowpayment";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "nowpaymentstatus");
    } elseif ($type == "arzireyali1") {
        if ($value == "onSwapinoBot") {
            $valuenew = "offSwapinoBot";
        } else {
            $valuenew = "onSwapinoBot";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusSwapWallet");
    } elseif ($type == "arzireyali2") {
        if ($value == "onternado") {
            $valuenew = "offternado";
        } else {
            $valuenew = "onternado";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
    } elseif ($type == "aqayepardakht") {
        if ($value == "onaqayepardakht") {
            $valuenew = "offaqayepardakht";
        } else {
            $valuenew = "onaqayepardakht";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusaqayepardakht");
    } elseif ($type == "zarinpey") {
        if ($value == "onzarinpey") {
            $valuenew = "offzarinpey";
        } else {
            $valuenew = "onzarinpey";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpeystatus");
    } elseif ($type == "zarinpal") {
        if ($value == "onzarinpal") {
            $valuenew = "offzarinpal";
        } else {
            $valuenew = "onzarinpal";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpalstatus");
    } elseif ($type == "affilnecurrency") {
        if ($value == "ondigi") {
            $valuenew = "offdigi";
        } else {
            $valuenew = "ondigi";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "digistatus");
    } elseif ($type == "oniranpay3") {
        if ($value == "oniranpay3") {
            $valuenew = "offiranpay3";
        } else {
            $valuenew = "oniranpay3";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusiranpay3");
    } elseif ($type == "startelegram") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusstar");
    } elseif ($type == "nowpayment") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusnowpayment");
    }
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay2', "📌 ارزی ریالی اول"), 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay3', "📌 ارزی ریالی دوم"), 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => rx_iranpay_label($datatextbot, 'iranpay1', "📌ارزی ریالی سوم"), 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "💼 آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم رد فاکس هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status);
} elseif ($text == "💰 کش بک کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashcart", $from_id);
} elseif ($user['step'] == "getcashcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackcart");
} elseif ($text == "💰 کش بک آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashahaypar", $from_id);
} elseif ($user['step'] == "getcashahaypar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackaqaypardokht");
} elseif ($text == "💰 کش بک ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay2", $from_id);
} elseif ($user['step'] == "getcashiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay2");
} elseif ($text == "💰 کش بک ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay4", $from_id);
} elseif ($user['step'] == "getcashiranpay4") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay3");
} elseif ($text == "💰 کش بک ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay1", $from_id);
} elseif ($user['step'] == "getcashiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay1");
} elseif ($text == "💰 کش بک plisio") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashplisio", $from_id);
} elseif ($user['step'] == "getcashplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackplisio");
} elseif ($text == "💰 کش بک nowpayment") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashnowpayment", $from_id);
} elseif ($user['step'] == "getcashnowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "cashbacknowpayment");
} elseif ($text == "💰 کش بک زرین پال") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpal", $from_id);
} elseif ($user['step'] == "getcashzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpal");
} elseif ($text == "📦 انبار شبکه ملی" && $adminrulecheck['rule'] == "administrator") {

    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) {

        nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. لطفاً ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید و سپس به این بخش بیایید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }

    savedata("clear", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $panelCode = $panel['code_panel'];
    $stockKeyboard = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : json_encode(['keyboard'=>[[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true]);
    nm_replyOrEdit($from_id, nmStockShelfStatusText($panelCode) . "\n\n📌 پنل فعال: <b>" . htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8') . "</b>\n\nابتدا برای هر محصول یک انبار بسازید؛ مثلا «انبار 10 گیگ». بعد کانفیگ‌ها را داخل همان انبار وارد کنید تا ربات دقیقاً بداند کدام کانفیگ برای کدام محصول/حجم/مدت است.", $stockKeyboard, 'HTML');
    step('home', $from_id);

} elseif (($text == "🚨 پنل اضطراری" || $text == "🌐 وضعیت نت ملی") && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }

    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $slimNationalKb = function_exists('nmNationalEmergencyMenuKeyboard') ? nmNationalEmergencyMenuKeyboard() : $optionManualsale;
    if ($text == "🚨 پنل اضطراری") {
        $newStatus = nmPanelEmergencyEnabled($panel) ? 'off_emergency_panel' : 'on_emergency_panel';
        update("marzban_panel", "emergency_panel_status", $newStatus, "code_panel", $panel['code_panel']);
        if ($newStatus === 'on_emergency_panel' && empty($panel['emergency_source_panel'])) {
            nm_replyOrEdit($from_id, "✅ پنل اضطراری روشن شد.\n\n📌 حالا روی «📌 ثبت پنل اضطراری» بزنید و یک پنل جداگانه از پنل‌های ثبت‌شده انتخاب کنید. تا وقتی پنل اضطراری انتخاب نشود، فقط وضعیت روشن است و جایگزینی انجام نمی‌شود.", $slimNationalKb, 'HTML');
        }
    } else {
        $newStatus = nmPanelNationalEnabled($panel) ? 'off_national_net' : 'on_national_net';
        update("marzban_panel", "national_net_status", $newStatus, "code_panel", $panel['code_panel']);
        if (empty($panel['stock_source_panel'])) update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
    }
    $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    nm_replyOrEdit($from_id, nmStockAdminPanelStatusText($panel), $slimNationalKb, 'HTML');

} elseif ($text == "📌 ثبت پنل اضطراری" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل اصلی انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    if (!nmPanelEmergencyEnabled($panel)) { nm_replyOrEdit($from_id, "❌ اول گزینه «🚨 پنل اضطراری» را روشن کنید، سپس پنل اضطراری را ثبت کنید.", $optionManualsale, 'HTML'); return; }

    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $rows = [];
    $stmt = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel <> :code ORDER BY name_panel ASC");
    $stmt->execute([':code' => $panel['code_panel']]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = [['text' => $r['name_panel']]];
    $rows[] = [['text' => "🏠 بازگشت به منوی مدیریت"]];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 پنل جداگانه‌ای که باید در حالت اضطراری جایگزین پنل فعلی شود را انتخاب کنید.\n\n⚠️ این پنل باید قبلاً از بخش افزودن پنل ثبت شده باشد؛ مثل مرزبان، مرزنشین، سنایی، تک‌پورت و ...", $rowsKeyboard, 'HTML');
    step('nm_select_emergency_panel', $from_id);

} elseif ($user['step'] == "nm_select_emergency_panel" && $adminrulecheck['rule'] == "administrator") {
    $mainPanel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $emPanel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$mainPanel || !$emPanel || $mainPanel['code_panel'] == $emPanel['code_panel']) {
        nm_replyOrEdit($from_id, "❌ پنل انتخابی معتبر نیست.", $optionManualsale, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "emergency_source_panel", $emPanel['code_panel'], "code_panel", $mainPanel['code_panel']);
    $cnt = nmStockSyncProductMap($emPanel['code_panel'], $emPanel['code_panel'], $user['agent'] ?? 'all');
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ پنل اضطراری ثبت شد: <b>{$emPanel['name_panel']}</b>\n\nاز این به بعد اگر پنل اصلی قطع باشد، ساخت/تمدید/حجم اضافه ابتدا با این پنل انجام می‌شود. محصولات پنل اضطراری همگام شدند: {$cnt}", $stockKb, 'HTML');
    step('home', $from_id);

} elseif ($text == "🔄 همگام‌سازی محصولات انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید و سپس به «📦 انبار شبکه ملی» بیایید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $count = nmStockSyncProductMap($panel['code_panel'], nmPanelResolveStockCode($panel), $user['agent'] ?? 'all');
    $emPanel = nmPanelEmergencyPanel($panel);
    if ($emPanel) $count += nmStockSyncProductMap($emPanel['code_panel'], $emPanel['code_panel'], $user['agent'] ?? 'all');
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ همگام‌سازی محصولات انجام شد.\n\nتعداد محصولات: {$count}\n\n" . nmStockShelfStatusText($panel['code_panel']), $stockKb, 'HTML');

} elseif ($text == "📊 گزارش موجودی انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $panelCode = is_array($panel) ? $panel['code_panel'] : 'auto';
    nm_replyOrEdit($from_id, nmStockShelfStatusText($panelCode) . "\n\n" . nmStockStatusText($panelCode), null, 'HTML');

} elseif ($text == "➕ افزودن انبار مدنظر" && $adminrulecheck['rule'] == "administrator") {
    nm_replyOrEdit($from_id, "📌 نام انبار را ارسال کنید.\n\nمثال: <code>انبار 10 گیگ 30 روزه</code>\nبعد از ثبت، دسته‌بندی و محصول فروشگاه را انتخاب می‌کنید و دفعات بعد کانفیگ‌ها مستقیم داخل همین انبار ثبت می‌شوند.", $backadmin, 'HTML');
    step('nm_shelf_name', $from_id);

} elseif ($user['step'] == "nm_shelf_name" && $adminrulecheck['rule'] == "administrator") {
    $shelfNameRaw = trim((string)$text);
    if ($shelfNameRaw === '' || $shelfNameRaw[0] === '{' || $shelfNameRaw[0] === '[' || mb_strlen($shelfNameRaw, 'UTF-8') > 120) {
        nm_replyOrEdit($from_id, "❌ نام انبار نامعتبر است. یک نام ساده (حداکثر ۱۲۰ کاراکتر) بدون کاراکترهای {، [ یا کد JSON ارسال کنید.\n\nمثال: <code>انبار 10 گیگ 30 روزه</code>", $backadmin, 'HTML');
        return;
    }
    $currentPanel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    savedata("clear", "nm_shelf_name", $shelfNameRaw);
    if ($currentPanel) {
        savedata("save", "namepanel", $currentPanel['name_panel']);
        savedata("save", "code_panel", $currentPanel['code_panel']);
    }
    if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
        $panelKeyboard = function_exists('nmNationalAdminPanelKeyboard') ? nmNationalAdminPanelKeyboard() : $json_list_marzban_panel;
        nm_replyOrEdit($from_id, "📌 وضعیت نت ملی / پنل اضطراری روشن است؛ ابتدا پنلی که این انبار برای آن ساخته می‌شود را انتخاب کنید.", $panelKeyboard, 'HTML');
        step('nm_shelf_panel', $from_id);
        return;
    }
    $catCountOff = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount(null) : -1;
    $catCountOffPanel = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount($currentPanel ?: null) : -1;
    error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_name user=%s national=0 panel=%s category_count_global=%d category_count_panel=%d', (string)$from_id, (string)(is_array($currentPanel) ? ($currentPanel['name_panel'] ?? '∅') : '∅'), $catCountOff, $catCountOffPanel));
    $rowsKeyboard = function_exists('nmNationalAdminCategoryKeyboard') ? nmNationalAdminCategoryKeyboard() : json_encode(['keyboard'=>[[['text'=>'بدون دسته‌بندی']],[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 دسته‌بندی محصول این انبار را انتخاب کنید.", $rowsKeyboard, 'HTML');
    step('nm_shelf_category', $from_id);

} elseif ($user['step'] == "nm_shelf_panel" && $adminrulecheck['rule'] == "administrator") {
    if ($text == "❌ پنلی پیدا نشد") {
        nm_replyOrEdit($from_id, "❌ پنل فعالی پیدا نشد. ابتدا از منوی مدیریت یک پنل فعال ثبت کنید.", $optionManualsale, 'HTML');
        step('home', $from_id);
        return;
    }

    $panel = function_exists('nmResolveActivePanelByName') ? nmResolveActivePanelByName($text) : select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$panel) $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$panel && function_exists('nmResolvePanelFromUserState')) $panel = nmResolvePanelFromUserState(null, $text);
    if (!$panel) {
        error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_panel user=%s panel_pick_unresolved text=%s', (string)$from_id, (string)$text));
        nm_replyOrEdit($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً یکی از دکمه‌های لیست پنل‌ها را انتخاب کنید.", function_exists('nmNationalAdminPanelKeyboard') ? nmNationalAdminPanelKeyboard() : $json_list_marzban_panel, 'HTML');
        return;
    }
    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    $catCount = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount(null) : -1;
    $catCountPanel = function_exists('nmAdminCategoryCount') ? nmAdminCategoryCount($panel) : -1;
    error_log(sprintf('[STOCK_SHELF_CAT] step=nm_shelf_panel user=%s national=1 panel=%s category_count_global=%d category_count_panel=%d', (string)$from_id, (string)($panel['name_panel'] ?? '∅'), $catCount, $catCountPanel));
    $rowsKeyboard = function_exists('nmNationalAdminCategoryKeyboard') ? nmNationalAdminCategoryKeyboard() : json_encode(['keyboard'=>[[['text'=>'بدون دسته‌بندی']],[['text'=>'🏠 بازگشت به منوی مدیریت']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 حالا دسته‌بندی محصول این انبار را انتخاب کنید.", $rowsKeyboard, 'HTML');
    step('nm_shelf_category', $from_id);

} elseif ($user['step'] == "nm_shelf_category" && $adminrulecheck['rule'] == "administrator") {
    $cat = $text === "بدون دسته‌بندی" ? null : $text;
    savedata("save", "nm_shelf_category", $cat ?: "");
    $freshUser = select("user", "*", "id", $from_id, "select");
    $data = json_decode($freshUser['Processing_value'] ?? $user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($freshUser ?: $user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? '', "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره انبار را ثبت کنید و ابتدا پنل را انتخاب کنید.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    $rows = [];
    $products = function_exists('nmProductsForPanelCategory') ? nmProductsForPanelCategory($panel, $user['agent'] ?? 'all', $cat) : [];
    if (!$products) {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') ORDER BY CAST(price_product AS UNSIGNED) ASC");
        $stmt->execute([':loc' => $panel['name_panel']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    foreach ($products as $r) $rows[] = [['text' => $r['name_product']]];
    if (!$rows) $rows[] = [['text' => "❌ محصولی پیدا نشد"]];
    $rows[] = [['text' => "🏠 بازگشت به منوی مدیریت"]];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 محصولی که این انبار باید به آن وصل شود را انتخاب کنید.\n\nربات از حجم و مدت همین محصول تشخیص می‌دهد کانفیگ واردشده برای چند گیگ و چند روز است.", $rowsKeyboard, 'HTML');
    step('nm_shelf_product', $from_id);

} elseif ($user['step'] == "nm_shelf_product" && $adminrulecheck['rule'] == "administrator") {
    $freshUser = select("user", "*", "id", $from_id, "select");
    $data = json_decode($freshUser['Processing_value'] ?? $user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($freshUser ?: $user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    if (!$panel) $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $categoryName = $data['nm_shelf_category'] ?? null;
    if (function_exists('nmProductByNameForShelf')) {
        $product = nmProductByNameForShelf($text, $panel ?: [], $user['agent'] ?? 'all', $categoryName);
    } elseif (function_exists('nmProductByNameForPanel')) {
        $product = nmProductByNameForPanel($text, $panel['name_panel'] ?? '', $user['agent'] ?? null, $categoryName);
    } else {
        $product = select("product", "*", "name_product", $text, "select");
    }
    if (!$product || !$panel) { nm_replyOrEdit($from_id, "❌ محصول یا پنل پیدا نشد.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    nmStockShelfCreate($data['nm_shelf_name'] ?? ('انبار '.$product['name_product']), $panel, $product, null, $categoryName);
    update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
    $stockKbAfterCreate = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ انبار ثبت شد.\n\nنام: <b>".htmlspecialchars($data['nm_shelf_name'] ?? '', ENT_QUOTES, 'UTF-8')."</b>\nمحصول: <b>{$product['name_product']}</b>\nحجم: {$product['Volume_constraint']} گیگ\nمدت: {$product['Service_time']} روز\n\nحالا از «➕ وارد کردن دسته‌ای انبار» یا «➕ افزودن کانفیگ تکی انبار» کانفیگ وارد کنید.", $stockKbAfterCreate, 'HTML');
    step('home', $from_id);

} elseif (($text == "➕ وارد کردن دسته‌ای انبار" || $text == "➕ افزودن کانفیگ تکی انبار") && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel) { nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. لطفاً ابتدا از منوی مدیریت پنل ها یک پنل را انتخاب کنید و سپس وارد بخش انبار شبکه ملی شوید.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }

    savedata("save", "namepanel", $panel['name_panel']);
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "nm_stock_import_mode", $text == "➕ افزودن کانفیگ تکی انبار" ? "single" : "bulk");

    if (function_exists('nmStockSessionClear')) nmStockSessionClear($from_id);
    if (function_exists('nmStockSessionPatch')) {
        nmStockSessionPatch($from_id, ['mode' => ($text == "➕ افزودن کانفیگ تکی انبار" ? "single" : "bulk")]);
    }
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel']);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انباری که کانفیگ‌ها باید داخل آن ثبت شوند را انتخاب کنید.", $shelfKeyboard, 'HTML');
    step('nm_stock_select_shelf_import', $from_id);

} elseif ($user['step'] == "nm_stock_select_shelf_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    $needle = trim((string)$text);

    $candidateLists = [nmStockShelves($panel['code_panel'] ?? null, false)];
    if (!empty($panel['code_panel'])) $candidateLists[] = nmStockShelves(null, false);
    foreach ($candidateLists as $list) {
        foreach ($list as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName === '') continue;
            if ($needle === $shelfName || strpos($needle, $shelfName) !== false) { $chosen = $shelf; break 2; }
        }
    }
    if (!$chosen) {
        $availableNames = [];
        foreach ($candidateLists as $list) {
            foreach ($list as $sh) { $availableNames[] = (string)($sh['name'] ?? ''); }
        }
        $availableNames = array_values(array_unique(array_filter($availableNames)));
        error_log(sprintf(
            '[STOCK_SHELF_MISS] step=nm_stock_select_shelf_import user=%s panel_code=%s panel_name=%s needle=%s data=%s available_count=%d names=%s',
            (string)$from_id,
            (string)($panel['code_panel'] ?? '∅'),
            (string)($panel['name_panel'] ?? '∅'),
            $needle,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            count($availableNames),
            json_encode($availableNames, JSON_UNESCAPED_UNICODE)
        ));
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار انتخابی پیدا نشد. لطفاً از همان لیست روی یک انبار بزنید.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    error_log(sprintf(
        '[STOCK_SHELF_PICK] step=nm_stock_select_shelf_import user=%s chosen_id=%s chosen_name=%s pre_pv=%s',
        (string)$from_id,
        var_export($chosen['id'] ?? null, true),
        (string)($chosen['name'] ?? '∅'),
        (string)($user['Processing_value'] ?? '∅')
    ));

    nmStockSetActiveShelf($from_id, $chosen['id']);

    $postWrite = select("user", "Processing_value", "id", $from_id, "select", ['cache' => false]);
    error_log(sprintf(
        '[STOCK_SHELF_PICK] post_save_pv=%s',
        is_array($postWrite) ? (string)($postWrite['Processing_value'] ?? '∅') : '∅'
    ));

    $askSubKbArr = [
        'keyboard' => [
            [['text' => "✅ بله، لینک اشتراک هم دارم"], ['text' => "🚫 خیر، فقط کانفیگ"]],
            [['text' => "🔗 فقط لینک اشتراک (بدون کانفیگ)"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $askSubKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($askSubKbArr)
        : json_encode($askSubKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 چه چیزی برای این انبار وارد می‌کنید؟\n\n✅ <b>کانفیگ + لینک اشتراک</b>: هر کانفیگ به‌همراه لینک اشتراکش.\n🚫 <b>فقط کانفیگ</b>: بدون لینک اشتراک.\n🔗 <b>فقط لینک اشتراک</b>: بدون کانفیگ تکی، فقط لینک(های) اشتراک.\n\nانبار: <b>{$chosen['name']}</b>\nمحصول: <b>{$chosen['product_name']}</b>", $askSubKb, 'HTML');
    step('nm_stock_ask_has_sub', $from_id);

} elseif ($user['step'] == "nm_stock_ask_has_sub" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_ask_has_sub user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s text=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE),
            (string)$text
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }

    nmStockSetActiveShelf($from_id, $shelf['id']);
    $session = nmStockSessionGet($user);
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    if ($text == "✅ بله، لینک اشتراک هم دارم") {
        nmStockSessionPatch($from_id, ['has_sub' => '1', 'pending_cfg' => '', 'imported' => 0]);
        $endKbArr = [
            'keyboard' => [
                [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
                [['text' => "🏠 بازگشت به منوی مدیریت"]],
            ],
            'resize_keyboard' => true,
        ];
        $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
            ? rx_keyboardToInline($endKbArr)
            : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headMsg = $mode === 'single'
            ? "📌 لطفاً کانفیگ تکی را ارسال کنید.\n\nبعد از آن، لینک اشتراک متناظرش را خواهم خواست."
            : "📌 لطفاً اولین کانفیگ را ارسال کنید.\n\nبعد از هر کانفیگ، لینک اشتراک آن را می‌خواهم؛ سپس کانفیگ بعدی و لینک بعدی به‌صورت پشت‌به‌پشت.\n\nهر زمان خواستید پایان دهید روی «🏁 پایان ورود کانفیگ‌ها» بزنید.";
        nm_replyOrEdit($from_id, $headMsg, $endKb, 'HTML');
        step('nm_stock_paired_cfg', $from_id);
    } elseif ($text == "🚫 خیر، فقط کانفیگ") {
        nmStockSessionPatch($from_id, ['has_sub' => '0', 'imported' => 0]);
        $msg = $mode === 'single'
            ? "📌 کانفیگ تکی را ارسال کنید."
            : "📌 کانفیگ‌ها را هر خط یک مورد ارسال کنید.\n\nاین کانفیگ‌ها داخل انبار <b>{$shelf['name']}</b> و محصول <b>{$shelf['product_name']}</b> ثبت می‌شوند.";
        nm_replyOrEdit($from_id, $msg, $backadmin, 'HTML');
        step('nm_stock_bulk_import', $from_id);
    } elseif ($text == "🔗 فقط لینک اشتراک (بدون کانفیگ)") {
        nmStockSessionPatch($from_id, ['has_sub' => 'link_only', 'imported' => 0]);
        $msg = $mode === 'single'
            ? "📌 لینک اشتراک را ارسال کنید (بدون کانفیگ تکی).\n\nباید یک آدرس کامل با <code>http(s)://</code> باشد."
            : "📌 لینک(های) اشتراک را هر خط یک مورد ارسال کنید (بدون کانفیگ تکی).\n\nهر خط باید یک آدرس کامل با <code>http(s)://</code> باشد.\nاین لینک‌ها داخل انبار <b>{$shelf['name']}</b> و محصول <b>{$shelf['product_name']}</b> ثبت می‌شوند.";
        nm_replyOrEdit($from_id, $msg, $backadmin, 'HTML');
        step('nm_stock_linkonly_import', $from_id);
    } else {
        $stockKbErr2 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ یکی از گزینه‌های زیر را انتخاب کنید.", $stockKbErr2, 'HTML');
        step('home', $from_id);
    }

} elseif ($user['step'] == "nm_stock_paired_cfg" && $adminrulecheck['rule'] == "administrator") {

    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_paired_cfg user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $session = nmStockSessionGet($user);
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    $imported = (int)($session['imported'] ?? ($data['nm_stock_imported'] ?? 0));
    if ($text == "🏁 پایان ورود کانفیگ‌ها") {
        $stockKbDone1 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ ورود جفتی کانفیگ‌ها پایان یافت.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ تعداد ثبت‌شده در این جلسه: {$imported}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone1, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }
    $cfg = trim((string)$text);
    if ($cfg === '' || mb_strlen($cfg, 'UTF-8') < 8) {
        nm_replyOrEdit($from_id, "❌ کانفیگ معتبر نیست. لطفاً مجدداً ارسال کنید.", $backadmin, 'HTML');
        return;
    }

    nmStockSessionPatch($from_id, ['pending_cfg' => $cfg]);
    $endKbArr = [
        'keyboard' => [
            [['text' => "↩️ بدون لینک اشتراک ثبت کن"]],
            [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($endKbArr)
        : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 حالا لینک اشتراک (Subscription URL) متناظر با همین کانفیگ را ارسال کنید.\n\nاگر برای این کانفیگ خاص لینک ندارید، روی «↩️ بدون لینک اشتراک ثبت کن» بزنید تا فقط خود کانفیگ ذخیره شود.", $endKb, 'HTML');
    step('nm_stock_paired_sub', $from_id);

} elseif ($user['step'] == "nm_stock_paired_sub" && $adminrulecheck['rule'] == "administrator") {

    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_paired_sub user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $session = nmStockSessionGet($user);
    $cfg = (string)($session['pending_cfg'] ?? ($data['nm_stock_pending_cfg'] ?? ''));
    if ($cfg === '') {
        nm_replyOrEdit($from_id, "❌ کانفیگ pending پیدا نشد. دوباره ارسال کنید.", $backadmin, 'HTML');
        step('nm_stock_paired_cfg', $from_id);
        return;
    }
    $mode = $session['mode'] ?? ($data['nm_stock_import_mode'] ?? 'bulk');
    $imported = (int)($session['imported'] ?? ($data['nm_stock_imported'] ?? 0));
    if ($text == "🏁 پایان ورود کانفیگ‌ها") {

        nmStockSessionPatch($from_id, ['pending_cfg' => '']);
        $stockKbDone2 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ ورود جفتی کانفیگ‌ها پایان یافت.\n\n⚠️ آخرین کانفیگ ارسالی به‌دلیل نداشتن لینک اشتراک ذخیره نشد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ تعداد ثبت‌شده در این جلسه: {$imported}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone2, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }
    $sub = null;
    if ($text == "↩️ بدون لینک اشتراک ثبت کن") {
        $sub = null;
    } else {
        $candidate = trim((string)$text);
        if ($candidate === '' || !preg_match('#^https?://\S+$#i', $candidate)) {
            nm_replyOrEdit($from_id, "❌ لینک اشتراک معتبر نیست. لطفاً یک URL کامل با http(s):// ارسال کنید یا روی «↩️ بدون لینک اشتراک ثبت کن» بزنید.", $backadmin, 'HTML');
            return;
        }
        $sub = $candidate;
    }

    $result = nmStockImportBatch($shelf['stock_codepanel'], $shelf['codeproduct'], $cfg, $shelf['volume_gb'], $shelf['id'], $sub);
    $imported += (int)($result['ok'] ?? 0);
    nmStockSessionPatch($from_id, ['imported' => $imported, 'pending_cfg' => '']);
    if ($mode === 'single') {
        $stockKbDone3 = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "✅ کانفیگ تکی همراه با لینک اشتراک ذخیره شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر: {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbDone3, 'HTML');
        if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
        step('home', $from_id);
        return;
    }

    $endKbArr = [
        'keyboard' => [
            [['text' => "🏁 پایان ورود کانفیگ‌ها"]],
            [['text' => "🏠 بازگشت به منوی مدیریت"]],
        ],
        'resize_keyboard' => true,
    ];
    $endKb = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($endKbArr)
        : json_encode($endKbArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "✅ جفت کانفیگ + لینک ثبت شد. (تعداد ثبت‌شده تا اینجا: {$imported})\n\n📌 کانفیگ بعدی را ارسال کنید یا روی «🏁 پایان ورود کانفیگ‌ها» بزنید.", $endKb, 'HTML');
    step('nm_stock_paired_cfg', $from_id);

} elseif ($user['step'] == "nm_stock_bulk_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_bulk_import user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $raw = $text;
    $sub = null;
    if (preg_match('/^(.+?)\nSUB=(https?:\/\/\S+)/is', $raw, $m)) { $raw = trim($m[1]); $sub = trim($m[2]); }
    $result = nmStockImportBatch($shelf['stock_codepanel'], $shelf['codeproduct'], $raw, $shelf['volume_gb'], $shelf['id'], $sub);
    $stockKbBulk = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ واردسازی انبار انجام شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n➕ ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر: {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbBulk, 'HTML');
    if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
    step('home', $from_id);

} elseif ($user['step'] == "nm_stock_linkonly_import" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $shelf = nmStockActiveShelf($user, $data);
    if (!$shelf) {
        error_log(sprintf(
            '[STOCK_SHELF_INVALID] step=nm_stock_linkonly_import user=%s nm_shelf_id=%s nm_shelf_active=%s data=%s',
            (string)$from_id,
            var_export($data['nm_shelf_id'] ?? null, true),
            var_export($user['nm_shelf_active'] ?? null, true),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
        nmStockRepromptShelf($from_id, $user);
        return;
    }
    $raw = trim((string)$text);
    if ($raw === '') {
        nm_replyOrEdit($from_id, "❌ چیزی دریافت نشد. لطفاً لینک(های) اشتراک را ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    $result = nmStockImportLinkOnly($shelf['stock_codepanel'], $shelf['codeproduct'], $raw, $shelf['volume_gb'], $shelf['id']);
    $stockKbLink = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
    nm_replyOrEdit($from_id, "✅ واردسازی لینک‌های اشتراک انجام شد.\n\nانبار: <b>{$shelf['name']}</b>\nمحصول: <b>{$shelf['product_name']}</b>\n🔗 ثبت‌شده: {$result['ok']}\n♻️ تکراری: {$result['duplicate']}\n⚠️ نامعتبر (آدرس غیرمعتبر): {$result['bad']}\n\n" . nmStockShelfStatusText($shelf['source_codepanel']), $stockKbLink, 'HTML');
    if (function_exists('nmStockClearActiveShelf')) nmStockClearActiveShelf($from_id);
    step('home', $from_id);
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 برای ویرایش اتصال انبار، همان انبار را انتخاب کنید؛ سپس نام/دسته/محصول را دوباره ثبت کنید.", $shelfKeyboard, 'HTML');
    step('nm_edit_shelf_select', $from_id);

} elseif ($user['step'] == "nm_edit_shelf_select" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) if (strpos($text, $shelf['name']) !== false) { $chosen = $shelf; break; }
    if (!$chosen) { nm_replyOrEdit($from_id, "❌ انبار پیدا نشد.", $optionManualsale, 'HTML'); step('home', $from_id); return; }
    savedata("clear", "nm_shelf_name", $user['Processing_value']);
    savedata("save", "nm_shelf_name", $chosen['name']);
    nm_replyOrEdit($from_id, "📌 نام جدید انبار را ارسال کنید یا همین نام را مجدد ارسال کنید:\n<code>{$chosen['name']}</code>", $backadmin, 'HTML');
    step('nm_shelf_name', $from_id);

} elseif ($text == "🗑 حذف کامل انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انباری که می‌خواهید کاملاً حذف شود را انتخاب کنید.\n\n⚠️ همه کانفیگ‌های این انبار هم با آن حذف می‌شوند.", $shelfKeyboard, 'HTML');
    step('nm_delete_shelf_select', $from_id);

} elseif ($user['step'] == "nm_delete_shelf_select" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $needle = trim((string)$text);
    $chosen = null;
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
    }
    if (!$chosen) {
        foreach (nmStockShelves(null, false) as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
        }
    }
    if (!$chosen) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار پیدا نشد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_shelf_delete_id", (string)$chosen['id']);
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '✅ تایید حذف کامل', 'callback_data' => 'nm_shelf_del:confirm:' . (int)$chosen['id']]],
            [['text' => '❌ لغو',           'callback_data' => 'nm_shelf_del:cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_replyOrEdit($from_id, "⚠️ آیا انبار <b>" . htmlspecialchars($chosen['name']) . "</b> به همراه همه کانفیگ‌های آن حذف شود؟", $kb, 'HTML');
    step('nm_delete_shelf_confirm', $from_id);

} elseif (isset($datain) && is_string($datain) && strpos($datain, 'nm_shelf_del:') === 0 && in_array($from_id, $admin_ids)) {
    $parts = explode(':', $datain);
    $act = $parts[1] ?? '';
    if ($act === 'cancel') {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❎ حذف انبار لغو شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($act === 'confirm') {
        $sid = (int)($parts[2] ?? 0);
        if ($sid > 0) {
            try {
                $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE shelf_id=:sid")->execute([':sid'=>$sid]);
                $pdo->prepare("DELETE FROM nm_stock_shelves WHERE id=:id")->execute([':id'=>$sid]);
            } catch (Throwable $e) { error_log('shelf delete failed: '.redfox_exception_fingerprint($e)); }
        }
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "✅ انبار و کانفیگ‌های آن حذف شدند.", null, 'HTML');
        }
        nm_adminInstantReply($from_id, "🗑 انبار حذف شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }

} elseif ($text == "❌ حذف کانفیگ انبار" && $adminrulecheck['rule'] == "administrator") {
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user) : select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $shelfKeyboard = nmStockShelfKeyboard($panel['code_panel'] ?? null);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        $decodedShelfKeyboard = json_decode($shelfKeyboard, true);
        if (isset($decodedShelfKeyboard['keyboard'])) $shelfKeyboard = rx_keyboardToInline($decodedShelfKeyboard);
    }
    nm_replyOrEdit($from_id, "📌 انبار موردنظر برای حذف کانفیگ را انتخاب کنید.", $shelfKeyboard, 'HTML');
    step('nm_delete_stock_select_shelf', $from_id);

} elseif ($user['step'] == "nm_delete_stock_select_shelf" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $panel = function_exists('nmResolvePanelFromUserState') ? nmResolvePanelFromUserState($user, $data['namepanel'] ?? null) : select("marzban_panel", "*", "name_panel", $data['namepanel'] ?? $user['Processing_value'], "select");
    $chosen = null;
    $needle = trim((string)$text);
    foreach (nmStockShelves($panel['code_panel'] ?? null, false) as $shelf) {
        $shelfName = trim((string)($shelf['name'] ?? ''));
        if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
    }
    if (!$chosen) {

        foreach (nmStockShelves(null, false) as $shelf) {
            $shelfName = trim((string)($shelf['name'] ?? ''));
            if ($shelfName !== '' && ($needle === $shelfName || strpos($needle, $shelfName) !== false)) { $chosen = $shelf; break; }
        }
    }
    if (!$chosen) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_replyOrEdit($from_id, "❌ انبار پیدا نشد. لطفاً مجدد از لیست انتخاب کنید.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_shelf_id", $chosen['id']);
    $rows = [
        [['text' => "🗑 حذف همه کانفیگ‌های فعال این انبار"]],
        [['text' => "🔢 حذف کانفیگ با آیدی"]],
        [['text' => "📋 نمایش لیست کانفیگ‌ها"]],
        [['text' => "🔙 بازگشت به انبار"]],
    ];
    $rowsKeyboardArray = ['keyboard'=>$rows,'resize_keyboard'=>true];
    $rowsKeyboard = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline'))
        ? rx_keyboardToInline($rowsKeyboardArray)
        : json_encode($rowsKeyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    nm_replyOrEdit($from_id, "📌 نوع حذف را انتخاب کنید.\n\nانبار: <b>{$chosen['name']}</b>", $rowsKeyboard, 'HTML');
    step('nm_delete_stock_action', $from_id);

} elseif ($user['step'] == "nm_delete_stock_action" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $sid = (int)($data['nm_shelf_id'] ?? 0);

    if ($text == "🗑 حذف همه کانفیگ‌های فعال این انبار" || $text == "حذف همه کانفیگ‌های فعال این انبار" || $datain == "nm_del_all_stock") {
        $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE shelf_id=:sid AND status='active'");
        $stmt->execute([':sid' => $sid]);
        $count = $stmt->rowCount();
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "✅ عملیات حذف انجام شد.\nتعداد حذف‌شده: {$count}", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }

    if ($text == "🔢 حذف کانفیگ با آیدی") {
        nm_adminInstantReply($from_id, "🔢 آیدی عددی کانفیگ موردنظر را ارسال کنید.\n\nبرای مشاهده آیدی‌ها می‌توانید از «📋 نمایش لیست کانفیگ‌ها» استفاده کنید.", json_encode(['keyboard'=>[[['text'=>'🔙 بازگشت به انبار']]],'resize_keyboard'=>true], JSON_UNESCAPED_UNICODE), 'HTML');
        step('nm_stock_delete_by_id', $from_id);
        return;
    }

    if ($text == "📋 نمایش لیست کانفیگ‌ها") {
        if (!function_exists('nmStockConfigInlineList')) {
            nm_adminInstantReply($from_id, "❌ تابع لیست در دسترس نیست.", $optionManualsale, 'HTML');
            step('home', $from_id);
            return;
        }
        $list = nmStockConfigInlineList($sid, 0, 10, 'nm_cfg');
        if ((int)$list['count'] === 0) {
            $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
            nm_adminInstantReply($from_id, "ℹ️ هیچ کانفیگ فعالی در این انبار وجود ندارد.", $stockKb, 'HTML');
            step('home', $from_id);
            return;
        }
        nm_adminInstantReply($from_id, "📋 لیست کانفیگ‌های فعال — صفحه ۱:\n<i>روی هر آیتم بزنید تا جزئیات و دکمه حذف باز شود.</i>", $list['keyboard'], 'HTML');
        step('home', $from_id);
        return;
    }

    nm_adminInstantReply($from_id, "❌ گزینه نامعتبر.", $optionManualsale, 'HTML');
    step('home', $from_id);

} elseif ($user['step'] == "nm_stock_delete_by_id" && $adminrulecheck['rule'] == "administrator") {
    $data = json_decode($user['Processing_value'], true) ?: [];
    $sid = (int)($data['nm_shelf_id'] ?? 0);
    $cfgId = (int)trim((string)$text);
    if ($cfgId <= 0) {
        nm_adminInstantReply($from_id, "❌ آیدی نامعتبر است. لطفاً یک عدد صحیح ارسال کنید.", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT id, content, status, shelf_id FROM nm_config_stock WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $cfgId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❌ کانفیگی با این آیدی پیدا نشد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($sid > 0 && (int)$row['shelf_id'] !== $sid) {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❌ این کانفیگ متعلق به انبار انتخاب‌شده نیست.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "nm_pending_cfg_delete", (string)$cfgId);
    $info = function_exists('nmStockExtractConfigInfo') ? nmStockExtractConfigInfo($row['content']) : ['username'=>'—','port'=>'—','host'=>'—'];
    $preview = mb_substr((string)$row['content'], 0, 80, 'UTF-8') . (mb_strlen((string)$row['content'], 'UTF-8') > 80 ? '…' : '');
    $msg = "🧾 جزئیات کانفیگ #<b>{$cfgId}</b>\n"
         . "👤 نام کاربری: <code>" . htmlspecialchars($info['username'] !== '' ? $info['username'] : '—') . "</code>\n"
         . "🔌 پورت: <code>" . htmlspecialchars($info['port'] !== '' ? $info['port'] : '—') . "</code>\n"
         . "🌐 هاست: <code>" . htmlspecialchars($info['host'] !== '' ? $info['host'] : '—') . "</code>\n"
         . "📋 پیش‌نمایش: <code>" . htmlspecialchars($preview) . "</code>\n\nآیا حذف شود؟";
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '✅ تایید حذف', 'callback_data' => 'nm_cfg:del:' . $cfgId]],
            [['text' => '❌ لغو',       'callback_data' => 'nm_cfg:cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
    step('nm_stock_delete_confirm', $from_id);

} elseif (isset($datain) && is_string($datain) && strpos($datain, 'nm_cfg:') === 0 && in_array($from_id, $admin_ids)) {
    $parts = explode(':', $datain);
    $action = $parts[1] ?? '';
    $arg    = $parts[2] ?? '';
    if ($action === 'close' || $action === 'cancel') {
        $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $optionManualsale;
        nm_adminInstantReply($from_id, "❎ لغو شد.", $stockKb, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($action === 'page') {
        $data = json_decode($user['Processing_value'], true) ?: [];
        $sid = (int)($data['nm_shelf_id'] ?? 0);
        $page = max(0, (int)$arg);
        $list = nmStockConfigInlineList($sid, $page, 10, 'nm_cfg');
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "📋 لیست کانفیگ‌های فعال — صفحه " . ($page + 1) . ":\n<i>روی هر آیتم بزنید تا جزئیات و دکمه حذف باز شود.</i>", $list['keyboard'], 'HTML');
        } else {
            nm_adminInstantReply($from_id, "📋 لیست کانفیگ‌های فعال — صفحه " . ($page + 1), $list['keyboard'], 'HTML');
        }
        return;
    }
    if ($action === 'view') {
        $cfgId = (int)$arg;
        $stmt = $pdo->prepare("SELECT id, content, status FROM nm_config_stock WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $cfgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { nm_adminInstantReply($from_id, "❌ کانفیگ یافت نشد.", null, 'HTML'); return; }
        $info = function_exists('nmStockExtractConfigInfo') ? nmStockExtractConfigInfo($row['content']) : ['username'=>'—','port'=>'—','host'=>'—'];
        $preview = mb_substr((string)$row['content'], 0, 100, 'UTF-8') . (mb_strlen((string)$row['content'], 'UTF-8') > 100 ? '…' : '');
        $msg = "🧾 جزئیات کانفیگ #<b>{$cfgId}</b>\n"
             . "👤 نام کاربری: <code>" . htmlspecialchars($info['username'] !== '' ? $info['username'] : '—') . "</code>\n"
             . "🔌 پورت: <code>" . htmlspecialchars($info['port'] !== '' ? $info['port'] : '—') . "</code>\n"
             . "🌐 هاست: <code>" . htmlspecialchars($info['host'] !== '' ? $info['host'] : '—') . "</code>\n"
             . "📋 پیش‌نمایش: <code>" . htmlspecialchars($preview) . "</code>";
        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => '🗑 حذف این کانفیگ', 'callback_data' => 'nm_cfg:askdel:' . $cfgId]],
                [['text' => '⬅️ بازگشت به لیست', 'callback_data' => 'nm_cfg:page:0']],
                [['text' => '❌ بستن',           'callback_data' => 'nm_cfg:close']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
        }
        return;
    }
    if ($action === 'askdel') {
        $cfgId = (int)$arg;
        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ تایید حذف', 'callback_data' => 'nm_cfg:del:' . $cfgId]],
                [['text' => '❌ لغو',       'callback_data' => 'nm_cfg:view:' . $cfgId]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, "⚠️ حذف کانفیگ #<b>{$cfgId}</b> تایید می‌کنید؟", $kb, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "⚠️ حذف کانفیگ #<b>{$cfgId}</b> تایید می‌کنید؟", $kb, 'HTML');
        }
        return;
    }
    if ($action === 'del') {
        $cfgId = (int)$arg;
        $stmt = $pdo->prepare("UPDATE nm_config_stock SET status='disabled' WHERE id=:id AND status='active'");
        $stmt->execute([':id' => $cfgId]);
        $ok = $stmt->rowCount() > 0;
        if (!empty($message_id) && function_exists('Editmessagetext')) {
            Editmessagetext($from_id, $message_id, $ok ? "✅ کانفیگ #<b>{$cfgId}</b> حذف شد." : "ℹ️ کانفیگ #<b>{$cfgId}</b> از قبل حذف بود یا یافت نشد.", null, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $ok ? "✅ کانفیگ #{$cfgId} حذف شد." : "ℹ️ یافت نشد.", null, 'HTML');
        }
        return;
    }

} elseif ($text == "➕ اضافه کردن کانفیگ") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
    step('getnameconfigm', $from_id);
    savedata("clear", "namepanel", $panelName);
} elseif ($user['step'] == "getnameconfigm") {
    $exitsname = select("manualsell", "*", "namerecord", $text, "count");
    if (intval($exitsname) != 0) {
        nm_adminInstantReply($from_id, "این نام وجود دارد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $product = [];
    savedata("save", "namerecord", $text);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    nm_adminInstantReply($from_id, "📌 نام محصول خود را ارسال نمایید در صورتی که میخواهید  برای اکانت تست تنظیم کنید متن تست را ارسال کنید.", $json_list_product_list_admin, 'HTML');
    step('getnameproduct', $from_id);
    savedata("save", "namerecord", $text);
} elseif ($user['step'] == "getnameproduct") {
    if ($text != "تست") {
        $product = select("product", "*", "name_product", $text, "select");
        if ($product == false) {
            nm_adminInstantReply($from_id, "محصول در ربات وجود ندارد", $backadmin, 'HTML');
            return;
        }
        savedata("save", "codeproduct", $product['code_product']);
    } else {
        savedata("save", "codeproduct", "usertest");
    }
    nm_adminInstantReply($from_id, "📌 کانفیگ یا متن دیگر خود را ارسال نمایید", $backadmin, 'HTML');
    step('getconfigtext', $from_id);
} elseif ($user['step'] == "getconfigtext") {
    nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت ذخیره گردید.", $optionManualsale, 'HTML');
    step('home', $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    $status = "active";
    $stmt = $pdo->prepare("INSERT IGNORE INTO manualsell (codepanel,namerecord,contentrecord,status,codeproduct) VALUES (:codepanel,:namerecord,:contentrecord,:status,:codeproduct)");
    $stmt->bindParam(':codepanel', $panel['code_panel']);
    $stmt->bindParam(':namerecord', $userdata['namerecord']);
    $stmt->bindParam(':contentrecord', $text);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':codeproduct', $userdata['codeproduct']);
    $stmt->execute();
    update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
} elseif (trim($text) == "❌ حذف کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید حذف نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameremove", $from_id);
} elseif ($user['step'] == "getnameremove") {
    nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت حذف گردید.", $optionManualsale, 'HTML');
    $userdata = json_decode($user['Processing_value'], true);
    $panelName = is_array($userdata) && isset($userdata['namepanel']) ? $userdata['namepanel'] : $user['Processing_value'];
    $panel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if ($panel) {
        $stmt = $pdo->prepare("DELETE FROM manualsell WHERE namerecord = ? AND codepanel = ?");
        $stmt->bindParam(1, $text);
        $stmt->bindParam(2, $panel['code_panel']);
        $stmt->execute();
    }
    step("home", $from_id);
} elseif ($text == "🌍 قیمت تغییر لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 قیمت تغییر لوکیشن از سایر پنل‌ها به این پنل را ارسال کنید", $backadmin, 'HTML');
    step('setpricechangelocation', $from_id);
} elseif ($user['step'] == "setpricechangelocation") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], "📌قیمت تغییر لوکیشن با موفقیت تغییر کرد");
    update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "➕ قیمت حجم اضافه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextra', $from_id);
} elseif ($user['step'] == "gettypeextra") {
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextravolume'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextravolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⚙️ قیمت حجم سرویس دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه دلخواه این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricecustomvo', $from_id);
} elseif ($user['step'] == "GetPricecustomvo") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextracustom', $from_id);
} elseif ($user['step'] == "gettypeextracustom") {
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomvolume'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان اضافه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت زمان اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricetimeextra', $from_id);
} elseif ($user['step'] == "GetPricetimeextra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextratime', $from_id);
} elseif ($user['step'] == "gettypeextratime") {
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextratime'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextratime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 قیمت زمان دلخواه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtratime', $from_id);
} elseif ($user['step'] == "GetPriceExtratime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "price", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(true), 'HTML');
    step('gettypeextratimecustom', $from_id);
} elseif ($user['step'] == "gettypeextratimecustom") {
    $agentst = ["n", "n2", "f", "all"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(true), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomtime'] ?? '{}', true);
    if (!is_array($eextraprice)) $eextraprice = [];
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔒 نمایش کارت به کارت پس از اولین پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با روشن کردن این قابلیت پس از اولین پرداخت کاربر درگاه کارت به کارت برای کاربر فعال می شود", $keyboardverify, 'HTML');
} elseif ($datain == "onpayverify") {
    update("PaySetting", "ValuePay", "offpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offpayverify") {
    update("PaySetting", "ValuePay", "onpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif ($text == "✏️ ویرایش کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید ویرایش نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameedit", $from_id);
} elseif ($user['step'] == "getnameedit") {
    nm_adminInstantReply($from_id, "یکی از گزینه های زیر را انتخاب کنید ", $configedit, 'HTML');
    step("home", $from_id);
    update("user", "Processing_value_one", $text, "id", $from_id);
} elseif ($text == "مخشصات کانفیگ") {
    nm_adminInstantReply($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
    step("getcontentedit", $from_id);
} elseif ($user['step'] == "getcontentedit") {
    nm_adminInstantReply($from_id, "✅ ذخیره گردید.", $optionManualsale, 'HTML');
    update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
} elseif ($text == "⬆️ افزایش گروهی قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getaddpricepeoductloc", $from_id);
} elseif ($user['step'] == "getaddpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
    savedata("clear", "namepanel", $text);
    step("getagentaddpriceproduct", $from_id);
} elseif ($user['step'] == "getagentaddpriceproduct") {
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $text = $grp;
    $keyboard_type_price = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "درصدی", 'callback_data' => 'typeaddprice_percent'],
                ['text' => "ثابت", 'callback_data' => 'typeaddprice_static'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 مبلغ به صورت درصدی اضافه شود یا مبلغ ثابت", $keyboard_type_price, 'HTML');
    savedata("save", "agent", $text);
    step("home", $from_id);
} elseif (preg_match('/^typeaddprice_(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    deletemessage($from_id, $message_id);
    if ($type == "static") {
        nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "📌 درصدی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    }
    savedata("save", "type_price", $type);
    step("getaddpricepeoduct", $from_id);
} elseif ($user['step'] == "getaddpricepeoduct") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        step("home", $from_id);
        return;
    }
    if ($userdata['type_price'] == "static") {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + :price WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + (price_product * :price / 100)  WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    }
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ کاهش  گروهی قیمت") {
    nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getlowpricepeoductloc", $from_id);
} elseif ($user['step'] == "getlowpricepeoductloc") {
    nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
    savedata("clear", "namepanel", $text);
    step("getkampricepeoductloc", $from_id);
} elseif ($user['step'] == "getkampricepeoductloc") {
    $grp = function_exists('rx_resolveAgentGroup') ? rx_resolveAgentGroup($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    savedata("save", "agent", $grp);
    step("getkampricepeoduct", $from_id);
} elseif ($user['step'] == "getkampricepeoduct") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        return;
    }
    foreach ($product as $products) {
        $result = $products['price_product'] - intval($text);
        update("product", "price_product", round($result), "code_product", $products['code_product']);
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ حداقل مبلغ کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaincart", $from_id);
} elseif ($user['step'] == "getmaincart") {
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
} elseif ($text == "⬆️ حداکثر مبلغ کارت به کارت") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxcart", $from_id);
} elseif ($user['step'] == "getmaxcart") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
} elseif ($text == "⬇️ حداقل مبلغ plisio") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainplisio", $from_id);
} elseif ($user['step'] == "getmainplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceplisio");
} elseif ($text == "⬆️ حداکثر مبلغ plisio") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxplisio", $from_id);
} elseif ($user['step'] == "getmaxplisio") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceplisio");
} elseif ($text == "⬇️ حداقل مبلغ رمزارز آفلاین") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaindigitaltron", $from_id);
} elseif ($user['step'] == "getmaindigitaltron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancedigitaltron");
} elseif ($text == "⬆️ حداکثر مبلغ رمزارز آفلاین") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxdigitaltron", $from_id);
} elseif ($user['step'] == "getmaxdigitaltron") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancedigitaltron");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay1", $from_id);
} elseif ($user['step'] == "getmainiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay1");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay1", $from_id);
} elseif ($user['step'] == "getmaaxiranpay1") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay1");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay2", $from_id);
} elseif ($user['step'] == "getmainiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay2");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی دوم") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay2", $from_id);
} elseif ($user['step'] == "getmaaxiranpay2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay2");
} elseif ($text == "⬇️ حداقل مبلغ آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqayepardakht", $from_id);
} elseif ($user['step'] == "getmainaqayepardakht") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceaqayepardakht");
} elseif ($text == "⬆️ حداکثر مبلغ آقای پرداخت") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxaqayepardakht", $from_id);
} elseif ($user['step'] == "getmaaxaqayepardakht") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceaqayepardakht");
} elseif ($text == "⬇️ حداقل مبلغ زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqzarinpal", $from_id);
} elseif ($user['step'] == "getmainaqzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpal");
} elseif ($text == "⬆️ حداکثر مبلغ زرین پال") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpal", $from_id);
} elseif ($user['step'] == "getmaaxzarinpal") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpal");
} elseif ($datain == "walletaddress" && $adminrulecheck['rule'] == "administrator") {

    if (function_exists('crypto_active_wallet')) {
        $rowTrx   = crypto_active_wallet('TRX');
        $rowUsdtT = crypto_active_wallet('USDT_TRC20');
        $rowTon   = crypto_active_wallet('TON');
        $rowUsdtN = crypto_active_wallet('USDT_TON');
        $shorten = static function ($row) {
            if (!$row || empty($row['wallet_address'])) return '—';
            $w = (string) $row['wallet_address'];
            return mb_strlen($w) > 14 ? mb_substr($w, 0, 8) . '…' . mb_substr($w, -4) : $w;
        };
        $msg = "💼 <b>آدرس کیف پول هش‌چکر</b>\n\n"
             . "شبکه‌ای که می‌خواهید آدرسش را ثبت/ویرایش کنید را انتخاب کنید:\n\n"
             . "🟥 ترون (TRX): <code>" . $shorten($rowTrx) . "</code>\n"
             . "🟢 تتر روی ترون: <code>" . $shorten($rowUsdtT) . "</code>\n"
             . "🟦 تون (TON): <code>" . $shorten($rowTon) . "</code>\n"
             . "🟢 تتر روی تون: <code>" . $shorten($rowUsdtN) . "</code>\n\n"
             . "ℹ️ بعد از ثبت آدرس، کاربر هنگام انتخاب «ارز آفلاین» یک هش پرداخت می‌فرستد و ربات هر دقیقه به‌صورت خودکار از Tronscan / TonAPI تایید می‌کند.";
        $networkPickerKb = json_encode([
            'inline_keyboard' => [
                [['text' => '🟥 ترون (TRX)',     'callback_data' => 'cryptowallet_TRX'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TRX']],
                [['text' => '🟢 تتر روی ترون',  'callback_data' => 'cryptowallet_USDT_TRC20'],  ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TRC20']],
                [['text' => '🟦 تون (TON)',     'callback_data' => 'cryptowallet_TON'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TON']],
                [['text' => '🟢 تتر روی تون',   'callback_data' => 'cryptowallet_USDT_TON'],    ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TON']],
                [['text' => '🔍 بررسی دستی هش (آفلاین)', 'callback_data' => 'cryptocheck_manual']],
                [['text' => '❌ بستن',          'callback_data' => 'close_stat']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, $msg, $networkPickerKb, 'HTML');
    } else {

        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
        $currentWallet = $PaySetting['ValuePay'] ?? '';
        $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) را ارسال کنید.\n\nولت فعلی شما: " . ($currentWallet === '' ? '—' : $currentWallet);
        nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
        savedata('clear', 'walletaddress_origin', 'general');
        step('walletaddresssiranpay', $from_id);
    }
} elseif (preg_match('/^cryptomemo_(yes|no)_(TON|USDT_TON)$/', (string) $datain, $cmm) && $adminrulecheck['rule'] == "administrator") {

    $memoAction = $cmm[1];
    $memoCur    = $cmm[2];
    if ($memoAction === 'no') {
        if (function_exists('crypto_save_wallet_memo')) {
            crypto_save_wallet_memo($memoCur, '');
        }
        update("user", "Processing_value", "0", "id", $from_id);
        $doneTxt = "✅ کیف پول <b>{$memoCur}</b> بدون ممو ذخیره شد.";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $doneTxt, null);
        } else {
            sendmessage($from_id, $doneTxt, null, 'HTML');
        }
        step('home', $from_id);
    } else {
        update("user", "Processing_value", $memoCur, "id", $from_id);
        $askMemoTxt = "🏷 لطفاً <b>ممو (Memo / Comment)</b> کیف پول <b>{$memoCur}</b> را ارسال کنید.\n\n"
                    . "<i>این مقدار هنگام پرداخت توسط کاربر در فیلد Memo/Comment کیف پولش وارد می‌شود.</i>";
        if (!empty($message_id)) {
            Editmessagetext($from_id, $message_id, $askMemoTxt, null);
        } else {
            sendmessage($from_id, $askMemoTxt, $backadmin, 'HTML');
        }
        step('cryptowallet_set_memo', $from_id);
    }
} elseif ($user['step'] == "cryptowallet_set_memo" && empty($datain)) {

    $memoText = trim((string) $text);
    $looksLikeNav = (
        $memoText === ''
        || mb_strlen($memoText) > 200
    );
    $memoCur = trim((string) ($user['Processing_value'] ?? ''));
    if (!in_array($memoCur, ['TON', 'USDT_TON'], true)) {
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        return;
    }
    if ($looksLikeNav) {
        if ($memoText === '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت ممو خارج شدید.", $keyboardadmin, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "❌ ممو معتبر نیست (طول بیش از ۲۰۰ کاراکتر).", null, 'HTML');
            return;
        }
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        return;
    }
    if (function_exists('crypto_save_wallet_memo')) {
        crypto_save_wallet_memo($memoCur, $memoText);
    }
    update("user", "Processing_value", "0", "id", $from_id);
    $successMsg = "✅ ممو برای کیف پول <b>{$memoCur}</b> ذخیره شد:\n<code>" . htmlspecialchars($memoText) . "</code>";
    nm_adminInstantReply($from_id, $successMsg, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowallet_del_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $cwd) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwd[1];
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $label = $supported[$cur]['label'] ?? $cur;
    $ok = function_exists('crypto_delete_wallet') ? crypto_delete_wallet($cur) : false;
    if ($ok) {
        $doneTxt = "🗑 کیف پول <b>{$label}</b> حذف و خالی شد.\nاز این پس برای این ارز هیچ آدرسی تنظیم نیست و کاربر نمی‌تواند پرداخت آفلاین انجام دهد.";
    } else {
        $doneTxt = "❌ حذف کیف پول <b>{$label}</b> ناموفق بود.";
    }
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $doneTxt, null);
    } else {
        sendmessage($from_id, $doneTxt, null, 'HTML');
    }
    if (function_exists('telegram') && !empty($callback_query_id ?? null)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $ok ? '✅ حذف شد' : '❌ خطا',
            'cache_time' => 1,
        ]);
    }
    step('home', $from_id);
} elseif ($datain === "cryptocheck_manual" && $adminrulecheck['rule'] == "administrator") {

    $msg  = "🔍 <b>بررسی دستی هش تراکنش</b>\n\n";
    $msg .= "هش تراکنش (TX Hash) را در یک پیام ارسال کنید.\n\n";
    $msg .= "ربات از روی هش، ارز را روی کیف پول‌های ثبت‌شده‌ی شما تشخیص می‌دهد و وضعیت تایید را گزارش می‌کند. اگر هش هنوز در بلاکچین نباشد، خطای «tx-not-found» می‌گیرید.";
    update("user", "Processing_value", "", "id", $from_id);
    nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
    step('cryptocheck_manual_wait', $from_id);
} elseif ($user['step'] == "cryptocheck_manual_wait" && empty($datain)) {

    $hash = trim((string) $text);
    if ($hash === '' || mb_strlen($hash) < 20 || preg_match('/[\x{0600}-\x{06FF}\s]/u', $hash)) {
        nm_adminInstantReply($from_id, "🏠 از حالت بررسی دستی خارج شدید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $hash = preg_replace('/^0x/i', '', $hash);
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $usdtTrc20Jetton = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    $usdtTonJetton   = 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs';
    $results = [];
    foreach (array_keys($supported) as $cur) {
        $wallet = function_exists('crypto_active_wallet') ? crypto_active_wallet($cur) : null;
        $addr = is_array($wallet) ? trim((string)($wallet['wallet_address'] ?? '')) : '';
        if ($addr === '') {
            $results[$cur] = ['ok' => false, 'reason' => 'no-wallet-configured'];
            continue;
        }

        $expectedAmount = 0.0;
        if ($cur === 'TRX' && function_exists('crypto_check_tron_tx')) {
            $r = crypto_check_tron_tx($hash, $addr, $expectedAmount, null, false);
        } elseif ($cur === 'USDT_TRC20' && function_exists('crypto_check_tron_tx')) {
            $r = crypto_check_tron_tx($hash, $addr, $expectedAmount, $usdtTrc20Jetton, false);
        } elseif ($cur === 'TON' && function_exists('crypto_check_ton_tx')) {
            $r = crypto_check_ton_tx($hash, $addr, $expectedAmount, null, false, '');
        } elseif ($cur === 'USDT_TON' && function_exists('crypto_check_ton_tx')) {
            $r = crypto_check_ton_tx($hash, $addr, $expectedAmount, $usdtTonJetton, false, '');
        } else {
            $r = ['ok' => false, 'reason' => 'helper-missing'];
        }
        $results[$cur] = is_array($r) ? $r : ['ok' => false, 'reason' => 'unknown'];
    }
    $lines = ["📋 <b>نتیجه بررسی دستی</b>\n", "🔗 هش: <code>" . htmlspecialchars($hash) . "</code>\n"];
    $foundAny = false;
    foreach ($results as $cur => $r) {
        $label = $supported[$cur]['label'] ?? $cur;
        $reason = (string)($r['reason'] ?? '');
        if (!empty($r['ok']) || $reason === 'amount-mismatch') {
            $foundAny = true;
            $amt = $r['detail']['amount'] ?? '?';
            $sender = $r['detail']['sender'] ?? '';
            $statusEmoji = !empty($r['ok']) ? '✅' : '⚠️';
            $statusTxt = !empty($r['ok']) ? 'تایید (بدون مقایسه مبلغ)' : 'گیرنده درست، مبلغ متفاوت';
            $lines[] = "{$statusEmoji} <b>{$label}</b>: {$statusTxt}";
            $lines[] = "   • مقدار: <code>{$amt}</code>";
            if ($sender !== '') $lines[] = "   • فرستنده: <code>" . htmlspecialchars((string)$sender) . "</code>";
            $explorer = function_exists('crypto_explorer_url') ? crypto_explorer_url($cur, $hash) : '';
            if ($explorer !== '') $lines[] = "   🔎 <a href=\"" . htmlspecialchars($explorer, ENT_QUOTES) . "\">مشاهده در اکسپلورر</a>";
        } else {
            $lines[] = "❌ <b>{$label}</b>: " . htmlspecialchars($reason);
        }
    }
    if (!$foundAny) {
        $lines[] = "\nℹ️ هیچ‌کدام از کیف‌پول‌های شما این تراکنش را در شبکه‌ی متناظر دریافت نکرده‌اند، یا هش روی شبکه‌ها معتبر نیست.";
    }
    nm_adminInstantReply($from_id, implode("\n", $lines), $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^cryptowallet_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $cwm) && $adminrulecheck['rule'] == "administrator") {

    $cur = $cwm[1];
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    $label = $supported[$cur]['label'] ?? $cur;
    $row = function_exists('crypto_active_wallet') ? crypto_active_wallet($cur) : null;
    $cur_now = $row['wallet_address'] ?? '';
    $hintNet = $supported[$cur]['network'] ?? '';
    $hint = $hintNet === 'TRON'
        ? "ℹ️ آدرس باید با حرف <code>T</code> شروع شود و ۳۴ کاراکتر باشد (TRC20)."
        : "ℹ️ آدرس TON معمولاً با <code>EQ</code>، <code>UQ</code> یا <code>kQ</code> شروع می‌شود و ۴۸ کاراکتر است.";
    $msg = "💼 شبکه انتخاب‌شده: <b>{$label}</b>\n\n"
         . "آدرس فعلی: <code>" . ($cur_now === '' ? '—' : htmlspecialchars($cur_now)) . "</code>\n\n"
         . "آدرس کیف پول جدید را ارسال کنید:\n\n{$hint}";

    update("user", "Processing_value", $cur, "id", $from_id);
    nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
    step('cryptowallet_set', $from_id);
} elseif ($user['step'] == "cryptowallet_set" && empty($datain)) {

    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 30
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value", "0", "id", $from_id);
        step('home', $from_id);
        if ($trimmed !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }
    $cur = trim((string) ($user['Processing_value'] ?? ''));
    $supported = function_exists('crypto_supported_currencies') ? crypto_supported_currencies() : [];
    if ($cur === '' || !isset($supported[$cur])) {
        nm_adminInstantReply($from_id, "❌ خطای داخلی: ارز نامشخص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $address = trim((string) $text);
    $network = $supported[$cur]['network'];
    if (!function_exists('crypto_validate_address') || !crypto_validate_address($address, $network)) {
        $exHint = $network === 'TRON'
            ? "آدرس TRC20 معتبر مثل <code>TR7N…</code>."
            : "آدرس TON معتبر مثل <code>EQ…</code> یا <code>UQ…</code>.";
        nm_adminInstantReply($from_id, "❌ آدرس وارد شده برای شبکه <b>{$network}</b> معتبر نیست.\n\n{$exHint}", null, 'HTML');
        return;
    }
    $ok = function_exists('crypto_save_wallet') ? crypto_save_wallet($cur, $address) : false;
    if (!$ok) {
        nm_adminInstantReply($from_id, "❌ ذخیره‌سازی با خطا مواجه شد. لاگ سرور را بررسی کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }

    $label = $supported[$cur]['label'];
    $okMsg = "✅ آدرس کیف پول <b>{$label}</b> با موفقیت ثبت شد.\n\n<code>" . htmlspecialchars($address) . "</code>";
    if ($cur === 'TRX') {
        $okMsg .= "\n\nℹ️ آدرس تتر روی ترون (USDT-TRC20) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی ترون» آن را تنظیم کنید.";
    } elseif ($cur === 'TON') {
        $okMsg .= "\n\nℹ️ آدرس تتر روی تون (USDT-TON) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی تون» آن را تنظیم کنید.";
    }
    nm_adminInstantReply($from_id, $okMsg, $keyboardadmin, 'HTML');

    if ($cur === 'TON' || $cur === 'USDT_TON') {
        update("user", "Processing_value", $cur, "id", $from_id);
        $memoAskMsg = "🏷 <b>آیا این کیف پول ممو (Memo / Comment) دارد؟</b>\n\n"
                    . "<i>اگر آدرس از یک صرافی است (مثل نوبیتکس / MEXC)، معمولاً ممو لازم دارد.\n"
                    . "آدرس‌های شخصی Tonkeeper معمولاً نیاز ندارند.</i>";
        $memoAskKb = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ دارم — وارد می‌کنم', 'callback_data' => 'cryptomemo_yes_' . $cur]],
                [['text' => '❌ ندارم',              'callback_data' => 'cryptomemo_no_'  . $cur]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, $memoAskMsg, $memoAskKb, 'HTML');
        step('home', $from_id);
        return;
    }

    update("user", "Processing_value", "0", "id", $from_id);
    step('home', $from_id);
} elseif ($user['step'] == "walletaddresssiranpay") {
    $walletInput = trim((string) $text);

    if ($walletInput === ''
        || mb_strlen($walletInput) < 30
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $walletInput)
        || strpos($walletInput, ' ') !== false
    ) {
        step('home', $from_id);
        if ($walletInput !== '') {
            nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس ولت خارج شدید.", $keyboardadmin, 'HTML');
        }
        return;
    }

    $userRecord = select("user", "*", "id", $from_id, "select");
    $processingData = [];
    if ($userRecord && isset($userRecord['Processing_value'])) {
        $decodedProcessing = json_decode($userRecord['Processing_value'], true);
        if (is_array($decodedProcessing)) {
            $processingData = $decodedProcessing;
        }
    }

    $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
    $invalidKeyboard = $walletOrigin === 'trnado' ? $trnado : $backadmin;

    if ($walletInput === '' || !preg_match('/^T[a-zA-Z0-9]{33}$/', $walletInput)) {
        nm_adminInstantReply($from_id, "❌ آدرس ولت وارد شده نامعتبر است. لطفاً آدرس TRC20 معتبر ارسال کنید.", $invalidKeyboard, 'HTML');
        return;
    }

    $standardizedWallet = strtoupper($walletInput);

    $successKeyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;

    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $successKeyboard, 'HTML');
    update("PaySetting", "ValuePay", $standardizedWallet, "NamePay", "walletaddress");
    update("user", "Processing_value", '{}', "id", $from_id);
    step('home', $from_id);
} elseif ($text == "💼 ثبت آدرس ولت ترون (TRC20)" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $currentWallet = $PaySetting['ValuePay'] ?? '';
    $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) مرتبط با درگاه ترنادو را ارسال کنید.\n\nولت فعلی شما: {$currentWallet}";
    nm_adminInstantReply($from_id, $texttronseller, $trnado, 'HTML');
    savedata('clear', 'walletaddress_origin', 'trnado');
    step('walletaddresssiranpay', $from_id);
} elseif ($text == "api  درگاه ارزی ریالی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiiranpay", "select")['ValuePay'];
    $texttronseller = "📌 کد api خود را ارسال نمایید.

        مرچنت فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiiranpay', $from_id);
} elseif ($user['step'] == "apiiranpay") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $iranpaykeyboard, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiiranpay");
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("minbalanceiranpay", $from_id);
} elseif ($user['step'] == "minbalanceiranpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی سوم") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalanceiranpay", $from_id);
} elseif ($user['step'] == "maxbalanceiranpay") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay");
} elseif ($text == "📍 حداقل حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaineExtra', $from_id);
} elseif ($user['step'] == "GetmaineExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "mainvalume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramain', $from_id);
} elseif ($user['step'] == "gettypeextramain") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('mainvalume', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['mainvolume'], true);
    $eextraprice[$text] = $userdata['mainvalume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaxeExtra', $from_id);
} elseif ($user['step'] == "GetmaxeExtra") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maxvolume", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramax', $from_id);
} elseif ($user['step'] == "gettypeextramax") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxvolume', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxvolume'], true);
    $eextraprice[$text] = $userdata['maxvolume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداقل زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaintime', $from_id);
} elseif ($user['step'] == "Getmaintime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maintime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramaintime', $from_id);
} elseif ($user['step'] == "gettypeextramaintime") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maintime', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maintime'], true);
    $eextraprice[$text] = $userdata['maintime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $panelName);
    nm_adminInstantReply($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaxtime', $from_id);
} elseif ($user['step'] == "Getmaxtime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("clear", "namepanel", $panelName);
    savedata("save", "maxtime", $text);
    nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], rx_agentGroupKeyboard(false), 'HTML');
    step('gettypeextramaxtime', $from_id);
} elseif ($user['step'] == "gettypeextramaxtime") {
    $agentst = ["n", "n2", "f"];
    $text = rx_resolveAgentGroup($text, $agentst);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'] ?? '{}', true);
    if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxtime', $userdata)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if (!is_array($typepanel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxtime'], true);
    $eextraprice[$text] = $userdata['maxtime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔼 اضافه کردن دپارتمان") {
    nm_adminInstantReply($from_id, "📌 ایدی عددی ادمینی که میخواهید پیام ها به آن ادمین ارسال شود را بفرستید", $backadmin, 'HTML');
    step("getidadmindep", $from_id);
} elseif ($user['step'] == "getidadmindep") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata('clear', 'idadmin', $text);
    nm_adminInstantReply($from_id, "📌 نام دپارتمان را ارسال نمایید", $backadmin, 'HTML');
    step("getdeparteman", $from_id);
} elseif ($user['step'] == "getdeparteman") {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT IGNORE INTO departman (idsupport,name_departman) VALUES (:idsupport,:name_departman)");
    $stmt->bindParam(':idsupport', $userdata['idadmin']);
    $stmt->bindParam(':name_departman', $text);
    $stmt->execute();
    step("home", $from_id);
    nm_adminInstantReply($from_id, "📌 دپارتمان با موفقیت اضافه گردید.", $supportcenter, 'HTML');
} elseif ($text == "🔽 حذف کردن دپارتمان") {
    $countdeparteman = select("departman", "*", null, null, "count");
    if ($countdeparteman == 0) {
        nm_adminInstantReply($from_id, "❌ دپارتمانی برای حذف وجود ندارد.", $departemanslist, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 نوع دپارتمان را برای حذف ارسال کنید.", $departemanslist, 'HTML');
    step("getremovedep", $from_id);
} elseif ($user['step'] == "getremovedep") {
    $stmt = $pdo->prepare("DELETE FROM departman WHERE name_departman = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    nm_adminInstantReply($from_id, "📌 بخش مورد نظر حذف گردید.", $supportcenter, 'HTML');
    step("home", $from_id);
} elseif ($text == "⚙️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
    $textsetservice = "📌 برای تنظیم سرویس یک کانفیگ در پنل خود ساخته و  سرویس هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    nm_adminInstantReply($from_id, $textsetservice, $backadmin, 'HTML');
    step('getservceid', $from_id);
} elseif ($user['step'] == "getservceid") {
    $userdata = json_decode(getuserm($text, $user['Processing_value'])['body'], true);
    if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
        nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
        return;
    }
    update("marzban_panel", "proxies", json_encode($userdata['service_ids']), "name_panel", $user['Processing_value']);
    step("home", $from_id);
    nm_adminInstantReply($from_id, "✅ اطلاعات با موفقیت تنظیم گردید", $optionmarzneshin, 'HTML');
} elseif ($text == "👤 تنظیم آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "📌 نام کاربری خود را بدون @ برای پشتیبانی  ارسال کنید\n\n{$setting['id_support']}";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('idsupportset', $from_id);
} elseif ($user['step'] == "idsupportset") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
    update("setting", "id_support", $text, null, null);
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpcart", $from_id);
} elseif ($user['step'] == "gethelpcart") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش nowpayment" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpnowpayment", $from_id);
} elseif ($user['step'] == "gethelpnowpayment") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpnowpayment");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "📚 تنظیم آموزش پرفکت مانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpperfect", $from_id);
} elseif ($user['step'] == "gethelpperfect") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpperfectmony");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش plisio" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpplisio", $from_id);
} elseif ($user['step'] == "gethelpplisio") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpplisio");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی اول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpiranpay1", $from_id);
} elseif ($user['step'] == "gethelpiranpay1") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی  دوم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay2", $from_id);
} elseif ($user['step'] == "helpiranpay2") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay2");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی سوم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay3", $from_id);
} elseif ($user['step'] == "helpiranpay3") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay3");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش درگاه اقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpaqayepardakht", $from_id);
} elseif ($user['step'] == "helpaqayepardakht") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش زرین پال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpal", $from_id);
} elseif ($user['step'] == "helpzarinpal") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش  ارزی افلاین" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpofflinearze", $from_id);
} elseif ($user['step'] == "helpofflinearze") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpofflinearze");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "💰 مبلغ عضویت نمایندگی") {
    nm_adminInstantReply($from_id, "📌 قیمت درخواست  عضویت  برای نمایندگی را ارسال کنید.", $backadmin, 'HTML');
    step("getpricereqagent", $from_id);
} elseif ($user['step'] == "getpricereqagent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره گردید", $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "agentreqprice", $text, null, null);
} elseif ($text == "🤖 تایید رسید  بدون بررسی" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    if ($paymentverify == "onautoconfirm") {
        nm_adminInstantReply($from_id, "❌ ابتدا تایید خودکار را خاموش کنید.", null, 'HTML');
        return;
    }
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با فعال کردن این قابلیت  در زمان هایی که آنلاین نیستید ربات بصورت خودکار تمامی تراکنش های کارت به کارت را تایید می کند سپس بعد از آنلاین شدن شما رسید ها را بررسی میکنید سپس اگر رسید فیک  ارسال شده تراکنش را کنسل میکنید", $keyboardverify, 'HTML');
} elseif ($datain == "onauto") {
    update("PaySetting", "ValuePay", "offauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offauto") {
    update("PaySetting", "ValuePay", "onauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif (preg_match('/transferaccount_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    nm_adminInstantReply($from_id, "آیدی عددی کاربری که میخواهید تمامی اطلاعات به آن کاربر منتقل شود را ارسال نمایید
    توجه داشتید باشید در کاربر مقصد در صورت داشتن موجودی حذف خواهد شد", $backadmin, 'HTML');
    step("getidfortransfers", $from_id);
} elseif ($user['step'] == "getidfortransfers") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    if ($text == $user['Processing_value']) {
        nm_adminInstantReply($from_id, "❌ شما نمی توانید اطلاعات به کاربر فعلی منتقل کنید", $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "اطلاعات با موفقیت به حساب کاربری جدید منتقل گردید", $keyboardadmin, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
    $stmt->bindParam(':id_user', $text, PDO::PARAM_STR);
    $stmt->execute();
    update("user", "id", $text, "id", $user['Processing_value']);
    update("Payment_report", "id_user", $text, "id_user", $user['Processing_value']);
    update("invoice", "id_user", $text, "id_user", $user['Processing_value']);
    update("support_message", "iduser", $text, "iduser", $user['Processing_value']);
    update("service_other", "id_user", $text, "id_user", $user['Processing_value']);
    update("Giftcodeconsumed", "id_user", $text, "id_user", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🖼 پس زمینه کیوآرکد") {
    nm_adminInstantReply($from_id, "تصویر خود را برای پس زمینه ارسال کنید", $backadmin, 'HTML');
    step("getimagebackgroundqr", $from_id);
} elseif ($user['step'] == "getimagebackgroundqr") {
    if (!$photo) {
        nm_adminInstantReply($from_id, "تصویر نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $response = getFileddire($photoid);
    if (!empty($response['ok'])) {
        $filePath = (string)($response['result']['file_path'] ?? '');
        if (!preg_match('#^[A-Za-z0-9_./-]{1,512}$#D', $filePath) || str_contains($filePath, '..')) {
            nm_adminInstantReply($from_id, '❌ مسیر فایل تلگرام معتبر نیست.', $setting_panel, 'HTML');
            step('home', $from_id);
            return;
        }
        $fileUrl = 'https://api.telegram.org/file/bot' . $APIKEY . '/' . ltrim($filePath, '/');
        $download = redfox_fetch_public_https($fileUrl, 5242880, 20);
        $image = (!empty($download['ok']) && function_exists('imagecreatefromstring')) ? @imagecreatefromstring((string)$download['body']) : false;
        if ($image === false || imagesx($image) < 1 || imagesy($image) < 1 || imagesx($image) > 5000 || imagesy($image) > 5000 || imagesx($image) * imagesy($image) > 16000000) {
            if (is_resource($image) || is_object($image)) imagedestroy($image);
            nm_adminInstantReply($from_id, '❌ فایل تصویر معتبر نیست یا از سقف مجاز بزرگ‌تر است.', $setting_panel, 'HTML');
            step('home', $from_id);
            return;
        }
        ob_start();
        $encoded = imagejpeg($image, null, 90);
        $fileContent = (string)ob_get_clean();
        imagedestroy($image);
        if (!$encoded || $fileContent === '' || strlen($fileContent) > 5242880) {
            nm_adminInstantReply($from_id, '❌ پردازش امن تصویر ناموفق بود.', $setting_panel, 'HTML');
            step('home', $from_id);
            return;
        }

        $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        $written = 0;
        $written += (int) @file_put_contents($projectRoot . '/custom.jpg', $fileContent, LOCK_EX);
        $written += (int) @file_put_contents($projectRoot . '/images.jpg', $fileContent, LOCK_EX);
        $written += (int) @file_put_contents($projectRoot . '/images.jpeg', $fileContent, LOCK_EX);
        if ($written > 0) {
            nm_adminInstantReply($from_id, "🖼 پس زمینه با موفقیت تنظیم گردید (همه‌جا اعمال شد: ربات، مینی‌اپ، کیف‌پول‌های ارز)", $setting_panel, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "❌ ذخیره‌سازی فایل ناموفق بود — دسترسی نوشتن روی پوشه‌ی روت پروژه را بررسی کنید.", $setting_panel, 'HTML');
        }
        step("home", $from_id);
    }
} elseif ($text == "⚙️ تنظیم پروتکل و اینباند" || $text == "🎛 تنظیم نام گروه" || $text == "⚙️ تنظیم نود") {
    if ($text == "🎛 تنظیم نام گروه") {
        $textsetprotocol = "📌 نام گروهی که بصورت پیشفرض می خواهید از آن ساخته شود را ارسال نمایید.";
    } elseif ($text == "⚙️ تنظیم نود") {
        $textsetprotocol = "📌 برای تنظیم نود یک کاربر در پنل خود ساخته و  نودهایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کاربر را ارسال نمایید";
    } else {
        $textsetprotocol = "📌 برای تنظیم اینباند  و پروتکل باید یک کانفیگ در پنل خود ساخته و  پروتکل و اینباند هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    }
    nm_adminInstantReply($from_id, $textsetprotocol, $backadmin, 'HTML');
    step("setinboundandprotocol", $from_id);
} elseif ($user['step'] == "setinboundandprotocol") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['type'] == "marzban") {
        if ((string)($panel['version_panel'] ?? '0') === '1') {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                nm_adminInstantReply($from_id, redfox_remote_error_summary($DataUserOut), null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } else {
                    unset($DataUserOut['proxy_settings'][$key]['id']);
                }
                if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                    $DataUserOut['proxy_settings'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
        } else {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                nm_adminInstantReply($from_id, redfox_remote_error_summary($DataUserOut), null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxies'], true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']); {
            if (count($data) == 0) {
                nm_adminInstantReply($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
                return;
            }
            $servies = [];
            foreach ($data['inbounds'] as $service) {
                $servies[] = $service;
            }
            update("marzban_panel", "proxies", json_encode($servies, true), "name_panel", $user['Processing_value']);
            syncSuiInboundsWithProxies($panel['id'] ?? null);
        }
    } elseif ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        update("marzban_panel", "proxies", $text, "name_panel", $user['Processing_value']);
    }
    if ($panel['type'] == "ibsng") {
        nm_adminInstantReply($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $optionibsng, 'HTML');
    } elseif ($panel['type'] == "mikrotik") {
        nm_adminInstantReply($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $option_mikrotik, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "✅ اینباند و پروتکل های شما با موفقیت تنظیم گردیدند.", $optionMarzban, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🔋 وضعیت تمدید" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['activepanel'], $keyboardstatus, 'HTML');
} elseif ($datain == "on_extend") {
    update("marzban_panel", "status_extend", "off_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepanelStatusOff'], $keyboardstatus);
} elseif ($datain == "off_extend") {
    update("marzban_panel", "status_extend", "on_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepaneltatuson'], $keyboardstatus);
} elseif ((preg_match('/confirmchannel-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['joinchannel'] == "active") {
        nm_adminInstantReply($from_id, "✍️ کاربر از قبل تایید شده است", null, 'HTML');
        return;
    }
    update("user", "joinchannel", "active", "id", $iduser);
    nm_adminInstantReply($from_id, "📌 کاربر از این پس بدون عضویت در کانال می تواند در ربات فعالیت داشته باشد", $keyboardadmin, 'HTML');
} elseif ((preg_match('/zerobalance-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    update("user", "Balance", "0", "id", $iduser);
    nm_adminInstantReply($from_id, "موجودی کاربر به مبلغ {$userdata['Balance']} صفر گردید", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $idadmin = trim($dataget[1]);
    $mainAdminId = trim((string) $adminnumber);
    if ($idadmin === $mainAdminId) {
        nm_adminInstantReply($from_id, "❌ امکان حذف ادمین اصلی وجود ندارد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM admin WHERE TRIM(id_admin) = :id_admin");
    $stmt->bindParam(':id_admin', $idadmin, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        nm_adminInstantReply($from_id, "⚠️ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ ادمین با موفقیت حذف گردید", null, 'HTML');
}

elseif ($text == "🫣 مخفی کردن پنل برای یک کاربر" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhide', $from_id);
} elseif ($user['step'] == "getuserhide") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ پنل با موفقیت برای کاربر مخفی گردید");
    if ($typepanel['hide_user'] == null) {
        $hideuserid = [];
    } else {
        $hideuserid = json_decode($typepanel['hide_user'], true);
    }
    $hideuserid[] = $text;
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌  حذف کاربر از لیست مخفی شدگان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhideforremove', $from_id);
} elseif ($user['step'] == "getuserhideforremove") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    step("home", $from_id);
    if ($typepanel['hide_user'] == null) {
        outtypepanel($typepanel['type'], "❌ هیچ کاربری در لیست مخفی شدگان وجود ندارد");
        return;
    }
    $hideuserid = json_decode($typepanel['hide_user'], true);
    if (count($hideuserid) == 0) {
        outtypepanel($typepanel['type'], "❌  کاربر در لیست وجود ندارد");
        return;
    }
    if (!in_array($text, $hideuserid)) {
        outtypepanel($typepanel['type'], "❌ کاربر در لیست وجود ندارد.");
        return;
    }
    $key = array_search($text, $hideuserid);
    if ($key !== false) {
        unset($hideuserid[$key]);
        $hideuserid = array_values($hideuserid);
    }
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    outtypepanel($typepanel['type'], "✅  کاربر با موفقیت از لیست حذف گردید.");
} elseif ($datain == "scoresetting") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
} elseif ($text == "1️⃣ تنظیم جایزه نفر اول") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary", $from_id);
} elseif ($user['step'] == "getonelotary") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['one'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "2️⃣ تنظیم جایزه نفر دوم") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary2", $from_id);
} elseif ($user['step'] == "getonelotary2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['tow'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "3️⃣ تنظیم جایزه نفر سوم") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary3", $from_id);
} elseif ($user['step'] == "getonelotary3") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['theree'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($datain == "gradonhshans") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
} elseif ($text == "🎲 مبلغ برنده شدن کاربر") {
    nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricewheel", $from_id);
} elseif ($user['step'] == "getpricewheel") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $wheelkeyboard, 'HTML');
    step("home", $from_id);
    update("setting", "wheelـluck_price", $text, null, null);
} elseif ($text == "💵 رسید های تایید نشده") {
    $sql = "SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $list_payment = $stmt->fetchAll();
    $list_payment_count = $stmt->rowCount();
    if ($list_payment_count == 0) {
        nm_adminInstantReply($from_id, "❌ هیچ پرداخت تایید نشده ای ندارید.", null, 'HTML');
        return;
    }
    $list_pay = ['inline_keyboard' => []];
    foreach ($list_payment as $payment) {
        $list_pay['inline_keyboard'][] = [
            ['text' => $payment['id_user'], 'callback_data' => "checkpay"]
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "✅", 'callback_data' => "Confirm_pay_{$payment['id_order']}"],
            ['text' => "❌", 'callback_data' => "reject_pay_{$payment['id_order']}"],
            ['text' => "📝", 'callback_data' => "showinfopay_{$payment['id_order']}"],
            ['text' => "🗑", 'callback_data' => "removeresid_{$payment['id_order']}"],
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "💸💸💸💸💸💸💸💸💸", 'callback_data' => "checkpay"]
        ];
    }
    $list_pay['inline_keyboard'][] = [
        ['text' => "❌ حذف همه رسید ها", 'callback_data' => "removeresid"]
    ];
    $list_pay_json = json_encode($list_pay, JSON_UNESCAPED_UNICODE);
    if ($list_pay_json === false) {
        error_log('Failed to encode pending receipts keyboard: ' . json_last_error_msg());
        $list_pay_json = json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE);
    }
    nm_adminInstantReply($from_id, "📌 پرداخت های تایید نشده کارت به کارت
در این بخش میتوانید پرداخت های تایید نشده مشاهده و تایید یا رد نمایید.
❌ : رد کردن پرداخت
✅ : تایید پرداخت
📝 مشخصات پرداخت
🗑 : حذف رسید بدون اطلاع کاربر", $list_pay_json, 'HTML');
} elseif ($datain == "removeresid") {
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "✅  تمامی رسید ها با موفقیت حذف شدند ", null, 'HTML');
    $sql = "UPDATE Payment_report SET payment_Status = 'reject',dec_not_confirmed = 'remove_all' WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} elseif (preg_match('/showinfopay_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $paymentUser = select("Payment_report", "*", "id_order", $idorder, "select");
    if ($paymentUser == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
    nm_adminInstantReply($from_id, $text_order, null, 'HTML');
} elseif ($text == "🎛 تنظیم اینباند") {
    nm_adminInstantReply($from_id, "📌 در صورتی که پنل مرزبان  یا مرزنشین هستید یک نام کاربری کانفیگ از پنل کپی و ارسال نمایید در غیراینصورت برای پنل های ثنایی و علیرضا شناسه اینباند را ارسال نمایید", $backadmin, 'HTML');
    step("getdatainboundproduct", $from_id);
} elseif ($user['step'] == "getdatainboundproduct") {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $datainbound = "";
    if ($marzban_list_get['type'] == "marzban") {
        $DataUserOut = getuser($text, $marzban_list_get['name_panel']);
        if (!empty($DataUserOut['error'])) {
            nm_adminInstantReply($from_id, redfox_remote_error_summary($DataUserOut), null, 'HTML');
            return;
        }
        if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
            nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
            return;
        }
        $DataUserOut = json_decode($DataUserOut['body'], true);
        if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
            nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        foreach ($DataUserOut['proxies'] as $key => &$value) {
            if ($key == "shadowsocks") {
                unset($DataUserOut['proxies'][$key]['password']);
            } elseif ($key == "trojan") {
                unset($DataUserOut['proxies'][$key]['password']);
            } else {
                unset($DataUserOut['proxies'][$key]['id']);
            }
            if (count($DataUserOut['proxies'][$key]) == 0) {
                $DataUserOut['proxies'][$key] = new stdClass();
            }
        }
        $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
        $proxies_json = json_encode($DataUserOut['proxies']);
        $stmt->bindParam(':proxies', $proxies_json);
        $stmt->bindParam(':name_product', $user['Processing_value']);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
        $stmt->bindParam(':agent', $user['Processing_value_tow']);
        $stmt->execute();
        $datainbound = json_encode($DataUserOut['inbounds']);
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $userdata = json_decode(getuserm($text, $marzban_list_get['name_panel'])['body'], true);
        if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
            nm_adminInstantReply($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
            return;
        }
        $datainbound = json_encode($userdata['service_ids'], true);
    } elseif ($marzban_list_get['type'] == "x-ui_single" || $marzban_list_get['type'] == "alireza_single") {
        $datainbound = $text;
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']);
        if (count($data) == 0) {
            nm_adminInstantReply($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
            return;
        }
        $servies = [];
        foreach ($data['inbounds'] as $service) {
            $servies[] = $service;
        }
        $datainbound = json_encode($servies);
    } elseif ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $datainbound = $text;
    } else {
        nm_adminInstantReply($from_id, "❌ برای این پنل قابلیت تعریف اینباند وجود ندارد", $shopkeyboard, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':inbounds', $datainbound);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "iploginset") {
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    $iplogin_unlimited = false;
    if ($raw_ip === '*' || $raw_ip === 'all' || $raw_ip === 'unlimited') {
        $iplogin_unlimited = true;
    } elseif (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            if (in_array('*', $decoded, true) || in_array('all', $decoded, true) || in_array('unlimited', $decoded, true)) {
                $iplogin_unlimited = true;
            } else {
                $ip_list = $decoded;
            }
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }

    $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    if ($iplogin_unlimited) {
        $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
        $msg .= "ورود به پنل وب از هر آیپی‌ای آزاد است.\n";
    } elseif (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
        $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
    } else {
        $msg .= "📋 آیپی‌های مجاز ورود:\n";
        foreach ($ip_list as $i => $ip) {
            $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
        }
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "➕ برای افزودن آیپی جدید، دکمه <b>افزودن آیپی</b> را بزنید.\n";
    $msg .= "♾️ برای دسترسی نامحدود، دکمه <b>حالت نامحدود</b> را بزنید.";

    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "deliplogin_" . $i],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 غیرفعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_off"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_on"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);

    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }

} elseif ($datain == "iploginunlim_on" || $datain == "iploginunlim_off") {
    if ($datain == "iploginunlim_on") {
        update("setting", "iplogin", "*", null, null);
        $toggle_msg = "♾️ <b>حالت نامحدود فعال شد.</b>\nورود به پنل از هر آیپی‌ای ممکن است.";
        $ip_list = [];
        $iplogin_unlimited = true;
    } else {
        update("setting", "iplogin", json_encode([]), null, null);
        $toggle_msg = "🔒 <b>حالت نامحدود غیرفعال شد.</b>\nبرای ورود، آیپی مجاز را تنظیم کنید.";
        $ip_list = [];
        $iplogin_unlimited = false;
    }
    $msg = $toggle_msg . "\n━━━━━━━━━━━━━━━━━━━━\n";
    if ($iplogin_unlimited) {
        $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
    } elseif (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━";
    $ip_keyboard = ['inline_keyboard' => []];
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    if ($iplogin_unlimited) {
        $ip_keyboard['inline_keyboard'][] = [['text' => "🔒 غیرفعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_off"]];
    } else {
        $ip_keyboard['inline_keyboard'][] = [['text' => "♾️ فعال‌سازی حالت نامحدود", 'callback_data' => "iploginunlim_on"]];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "🛡 مدیریت آیپی‌ها", 'callback_data' => "iploginset"]];
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }
} elseif ($datain == "addiplogin") {
    nm_adminInstantReply($from_id, "📌 آیپی جدید خود را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", null, 'HTML');
    step("getiplogin", $from_id);

} elseif ($user['step'] == "getiplogin") {
    $new_ip = trim($text);
    if (!filter_var($new_ip, FILTER_VALIDATE_IP)) {
        nm_adminInstantReply($from_id, "❌ آیپی وارد شده معتبر نیست. لطفاً یک آیپی صحیح ارسال کنید.\nمثال: <code>1.2.3.4</code>", null, 'HTML');
        return;
    }
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    if (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            $ip_list = $decoded;
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }
    if (in_array($new_ip, $ip_list)) {
        nm_adminInstantReply($from_id, "⚠️ این آیپی قبلاً در لیست وجود دارد.", null, 'HTML');
        step("home", $from_id);
        return;
    }
    $ip_list[] = $new_ip;
    update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);
    step("home", $from_id);
    nm_adminInstantReply($from_id, "✅ آیپی <code>" . htmlspecialchars($new_ip) . "</code> با موفقیت اضافه شد.", $shopkeyboard, 'HTML');

} elseif (preg_match('/^deliplogin_(\d+)$/', $datain, $ipdel_match)) {
    $del_index = (int)$ipdel_match[1];
    $setting_row = select("setting", "*", null, null, "select");
    $raw_ip = $setting_row['iplogin'] ?? '';
    $ip_list = [];
    if (!empty($raw_ip) && $raw_ip !== '0') {
        $decoded = json_decode($raw_ip, true);
        if (is_array($decoded)) {
            $ip_list = $decoded;
        } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_list = [$raw_ip];
        }
    }
    if (!isset($ip_list[$del_index])) {
        nm_adminInstantReply($from_id, "❌ آیپی مورد نظر یافت نشد.", $shopkeyboard, 'HTML');
        return;
    }
    $deleted_ip = $ip_list[$del_index];
    array_splice($ip_list, $del_index, 1);
    update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);

    $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "✅ آیپی <code>" . htmlspecialchars($deleted_ip) . "</code> حذف شد.\n\n";
    if (empty($ip_list)) {
        $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
        $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
    } else {
        $msg .= "📋 آیپی‌های مجاز ورود:\n";
        foreach ($ip_list as $i => $ip) {
            $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
        }
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━";

    $ip_keyboard = ['inline_keyboard' => []];
    foreach ($ip_list as $i => $ip) {
        $ip_keyboard['inline_keyboard'][] = [
            ['text' => "🔸 " . $ip, 'callback_data' => "noop"],
            ['text' => "🗑 حذف",     'callback_data' => "deliplogin_" . $i],
        ];
    }
    $ip_keyboard['inline_keyboard'][] = [['text' => "➕ افزودن آیپی", 'callback_data' => "addiplogin"]];
    $ip_keyboard['inline_keyboard'][] = [['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backadmin"]];
    $ip_keyboard_json = json_encode($ip_keyboard);

    if ($message_id) {
        Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
    } else {
        nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
    }
} elseif (preg_match('/extendadmin_(\w+)/', $datain, $dataget) || strpos($text, "/extend ") !== false) {
    if ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $id_invoice = select("invoice", "id_invoice", "username", $usernameconfig, 'select');
        if ($id_invoice == false) {
            nm_adminInstantReply($from_id, "❌ کاربر وجو ندارد.", null, 'HTML');
            return;
        }
        $id_invoice = $id_invoice['id_invoice'];
    } else {
        $id_invoice = $dataget[1];
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        nm_adminInstantReply($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.";
    nm_adminInstantReply($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvolomforextendadmin', $from_id);
} elseif ($user['step'] == "gettimecustomvolomforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید ";
    nm_adminInstantReply($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextendadmin', $from_id);
} elseif ($user['step'] == "getvolumecustomuserforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['note'] = "";
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $text;
    $prodcut['Volume_constraint'] = $userdate['volume'];
    update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $userdate['id_invoice']);
    step("home", $from_id);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivceadmin-" . $nameloc['id_invoice']],
            ],
            [
                ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"]
            ]
        ]
    ]);
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$prodcut['name_product']}
⏱ مدت زمان تمدید :{$prodcut['Service_time']} روز
🔋 حجم تمدید :{$prodcut['Volume_constraint']} گیگ
✍️ توضیحات : {$prodcut['note']}
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    if ($user['step'] == "getvolumecustomuserforextendadmin") {
        nm_adminInstantReply($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif (preg_match('/^confirmserivceadmin-(.*)/', $datain, $dataget)) {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $prodcut['code_product'] = "custom_volume";
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $nameloc['Service_time'];
    $prodcut['Volume_constraint'] = $nameloc['Volume'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        nm_adminInstantReply($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        if (nmStockCompleteExtendFallback($from_id, $user, $nameloc, $prodcut, 0, 'maintenance_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = redfox_remote_error_summary($extend);
        $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
        nm_adminInstantReply($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $dateacc = date('Y/m/d H:i:s');
    $value = $prodcut['Volume_constraint'] . "_" . $prodcut['Service_time'];
    $type = "extend_user_by_admin";
    $stmt->bindParam(':id_user', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':username', $nameloc['username'], PDO::PARAM_STR);
    $stmt->bindParam(':value', $value, PDO::PARAM_STR);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':time', $dateacc, PDO::PARAM_STR);
    $stmt->bindParam(':price', $prodcut['price_product'], PDO::PARAM_STR);
    $output_json = json_encode(['status' => true], JSON_UNESCAPED_SLASHES);
    $stmt->bindParam(':output', $output_json, PDO::PARAM_STR);
    $stmt->execute();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    nm_adminInstantReply($from_id, $textbotlang['users']['extend']['thanks'], null, 'HTML');
    $text_report = "⭕️ ادمین سرویس کاربر را تمدید کرد.

اطلاعات کاربر :

🪪 آیدی عددی ادمین : <code>$from_id</code>
🪪 آیدی عددی : <code>{$nameloc['id_user']}</code>
🛍 نام محصول :  {$prodcut['name_product']}
👤 نام کاربری مشتری در پنل  : {$nameloc['username']}
موقعیت سرویس سرویس کاربر : {$nameloc['Service_location']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/removeresid_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order");
    $stmt->bindParam(':id_order', $idorder, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ رسید با موفقیت حذف شد.", null, 'HTML');
}
/* ---- maintenance.php ---- */
if (isset($update["inline_query"])) {
    $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $query, PDO::PARAM_STR);
    $stmt->bindParam(':notes', $query, PDO::PARAM_STR);
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    $results = [];
    foreach ($invoices as $OrderUser) {
        if (isset($OrderUser['time_sell'])) {
            $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        } else {
            $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
        }
        if ($OrderUser['name_product'] == "سرویس تست") {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "مگابایت";
        } else {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "گیگابایت";
        }
        $results[] = [
            "type" => "article",
            "id" => uniqid(),
            'cache_time' => 0,
            'is_personal' => true,
            "title" => $OrderUser['username'],
            "input_message_content" => [
                "message_text" => "
🛒 شماره سفارش  :  {$OrderUser['id_invoice']}
🛒  وضعیت سفارش در ربات : {$OrderUser['Status']}
🙍‍♂️ شناسه کاربر : {$OrderUser['id_user']}
👤 نام کاربری اشتراک :  {$OrderUser['username']}
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
"
            ]
        ];
    }
    answerInlineQuery($inline_query_id, $results);
} elseif (preg_match('/vieworderuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json, 'html');
} elseif (preg_match('/next_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "متن دکمه گردونه شانس" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_wheel_luck'], $backadmin, 'HTML');
    step('text_wheel_luck', $from_id);
} elseif ($user['step'] == "text_wheel_luck") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wheel_luck");
    step('home', $from_id);
} elseif ($datain == "cartuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageusercart') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageusercart') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/createbot_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $checkbot = select("botsaz", "*", "id_user", $id_user, "count");
    $checkbots = select("botsaz", "*", null, null, "count");
    if ($checkbots >= 15) {
        nm_adminInstantReply($from_id, "❌  درحال حاضر فقط محدود به ساختن 15 ربات برای نماینده های خود هستید.", $keyboardadmin, 'HTML');
        return;
    }
    if ($checkbot != 0) {
        $textexitsbot = "❌ این ربات از قبل نصب شده است امکان نصب مجدد وجود ندارد.";
        nm_adminInstantReply($from_id, $textexitsbot, $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "id_user", $id_user);
    $texbot = "📌  از طریق این بخش شما می توانید برای نماینده خود یک ربات فروش بسازید تا نماینده با ربات اختصاصی خودش فروش داشته باشد

- جهت ساخت ربات توکن ربات را ارسال نمایید.";
    nm_adminInstantReply($from_id, $texbot, $backadmin, 'HTML');
    step("gettokenbot", $from_id);
} elseif ($user['step'] == "gettokenbot") {
    $candidateToken = trim((string)$text);
    $getInfoToken = preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $candidateToken)
        ? telegram('getMe', [], $candidateToken) : ['ok' => false];
    $remoteUsername = is_array($getInfoToken) ? (string)($getInfoToken['result']['username'] ?? '') : '';
    if (empty($getInfoToken['ok']) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $remoteUsername)) {
        nm_adminInstantReply($from_id, "❌ توکن نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $checkbot = select("botsaz", "*", "bot_token", $candidateToken, "count");
    if ($checkbot != 0) {
        nm_adminInstantReply($from_id, "📌 این توکن از قبل ثبت شده است", null, 'HTML');
        return;
    }
    savedata("save", "token", $candidateToken);
    savedata("save", "username", $remoteUsername);
    $texbot = "📌 آیدی عددی ادمین را ارسال نمایید";
    nm_adminInstantReply($from_id, $texbot, $backadmin, 'HTML');
    step("getadminidbot", $from_id);
} elseif ($user['step'] == "getadminidbot") {
    if (!preg_match('/^[0-9]{1,20}$/', (string)$text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode((string)$user['Processing_value'], true);
    $ownerId = is_array($userdate) ? trim((string)($userdate['id_user'] ?? '')) : '';
    $botToken = is_array($userdate) ? trim((string)($userdate['token'] ?? '')) : '';
    $botUsername = is_array($userdate) ? trim((string)($userdate['username'] ?? '')) : '';
    if (!preg_match('/^[0-9]{1,20}$/', $ownerId)
        || !preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $botToken)
        || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $botUsername)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات ذخیره‌شده ربات نامعتبر است؛ دوباره شروع کنید.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $getInfoToken = telegram('getMe', [], $botToken);
    if (empty($getInfoToken['ok'])
        || strcasecmp((string)($getInfoToken['result']['username'] ?? ''), $botUsername) !== 0) {
        nm_adminInstantReply($from_id, "❌ توکن دیگر با یوزرنیم ربات مطابقت ندارد.", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }

    $webhookSecret = bin2hex(random_bytes(32));
    $admin_ids = json_encode([$ownerId], JSON_UNESCAPED_UNICODE);
    $datasetting = json_encode([
        'minpricetime' => 4000, 'pricetime' => 4000,
        'minpricevolume' => 4000, 'pricevolume' => 4000,
        'support_username' => '@support', 'Channel_Report' => 0,
        'cart_info' => 'جهت پرداخت مبلغ را به شماره کارت زیر واریز نمایید',
        'show_product' => true,
    ], JSON_UNESCAPED_UNICODE);
    $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : getcwd();
    $botDir = rtrim((string)$projectRoot, '/\\') . '/vpnbot/' . $ownerId . $botUsername;
    try {
        require_once rtrim((string)$projectRoot, '/\\') . '/lib/ResellerBotManager.php';
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO botsaz (id_user,bot_token,admin_ids,username,time,setting,hide_panel,webhook_secret_token) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$ownerId, $botToken, $admin_ids, $botUsername, date('Y/m/d H:i:s'), $datasetting, '{}', $webhookSecret]);
        $rowId = (int)$pdo->lastInsertId();
        (new RedFoxResellerBotManager($pdo, (string)$projectRoot))->repair([
            'id' => $rowId,
            'id_user' => $ownerId,
            'username' => $botUsername,
            'bot_token' => $botToken,
            'webhook_secret_token' => $webhookSecret,
        ]);
        $pdo->commit();
        telegram('sendMessage', [
            'chat_id' => $ownerId,
            'text' => '✅ ربات اختصاصی شما با موفقیت نصب شد.',
        ], $botToken);
        nm_adminInstantReply($from_id, "✅ ربات نماینده با موفقیت و Webhook امن ساخته شد.\n⚙️ نام کاربری ربات: @" . htmlspecialchars($botUsername, ENT_QUOTES, 'UTF-8'), $keyboardadmin, 'HTML');
    } catch (Throwable $createBotError) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try { telegram('deleteWebhook', [], $botToken); } catch (Throwable $ignored) {}
        if ((is_dir($botDir) || is_link($botDir)) && function_exists('deleteDirectory')) deleteDirectory($botDir);
        error_log('[admin-maintenance] reseller bot create failed: ' . redfox_exception_fingerprint($createBotError));
        nm_adminInstantReply($from_id, "❌ ساخت ربات ناموفق بود و تغییرات بازگردانی شد.", $keyboardadmin, 'HTML');
    }
    step("home", $from_id);
} elseif (preg_match('/removebotsell_(\w+)/', $datain, $datagetr)) {
    $id_user = (string)$datagetr[1];
    if (!preg_match('/^[0-9]{1,20}$/', $id_user)) {
        nm_adminInstantReply($from_id, "❌ شناسه نماینده نامعتبر است.", $keyboardadmin, 'HTML');
        return;
    }
    $contentbto = select("botsaz", "*", "id_user", $id_user, "select");
    $storedUsername = is_array($contentbto) ? (string)($contentbto['username'] ?? '') : '';
    $storedToken = is_array($contentbto) ? (string)($contentbto['bot_token'] ?? '') : '';
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $storedUsername)
        || !preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $storedToken)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات ذخیره‌شده ربات ناامن است؛ حذف متوقف شد.", $keyboardadmin, 'HTML');
        return;
    }
    $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : getcwd();
    $dirsource = rtrim((string)$projectRoot, '/\\') . '/vpnbot/' . $id_user . $storedUsername;
    if ((is_dir($dirsource) || is_link($dirsource)) && !deleteDirectory($dirsource)) {
        nm_adminInstantReply($from_id, "❌ حذف امن پوشه ربات ناموفق بود؛ رکورد حذف نشد.", $keyboardadmin, 'HTML');
        return;
    }
    $deleteHook = telegram('deleteWebhook', [], $storedToken);
    if (!is_array($deleteHook) || empty($deleteHook['ok'])) {
        nm_adminInstantReply($from_id, "❌ حذف Webhook در تلگرام ناموفق بود؛ بعداً دوباره تلاش کنید.", $keyboardadmin, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM botsaz WHERE id_user = :id_user AND bot_token = :bot_token");
    $stmt->execute([':id_user' => $id_user, ':bot_token' => $storedToken]);
    if ($stmt->rowCount() !== 1) {
        nm_adminInstantReply($from_id, "❌ حذف رکورد ربات ناموفق بود.", $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ ربات فروش نماینده با موفقیت حذف گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/setvolumesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر گیگ حجم بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricevolumesrc", $from_id);
} elseif ($user['step'] == "getpricevolumesrc") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricevolume'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/settimepricesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر روز زمان بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricetimesrc", $from_id);
} elseif ($user['step'] == "getpricetimesrc") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricetime'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
}
if ($datain == "settimecornday" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید چند روز مانده است به پایان اشتراک به کاربر اطلاع داده شود. زمان برحسب روز است" . $setting['daywarn'] . "روز", $backadmin, 'HTML');
    step("getdaywarn", $from_id);
} elseif ($user['step'] == "getdaywarn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $keyboardadmin, 'HTML');
    step("home", $from_id);
    update("setting", "daywarn", $text);
} elseif ($datain == "linkappsetting") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب نمایید.", $keyboardlinkapp, 'HTML');
} elseif ($datain == "infocard_color_menu" && $adminrulecheck['rule'] == "administrator") {

    $currentRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
    $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
    $colorKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
            ],
            [
                ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
            ],
            [
                ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => 'close_stat'],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    if (function_exists('Editmessagetext') && isset($message_id)) {
        Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
    } else {
        nm_adminInstantReply($from_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard, 'HTML');
    }
} elseif (preg_match('/^infocard_setcolor_(yellow|green|red|blue|purple|orange)$/', $datain ?? '', $colorMatch) && $adminrulecheck['rule'] == "administrator") {
    $newColor = $colorMatch[1];
    $existing = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    if (is_array($existing) && isset($existing['Namevalue'])) {
        update("shopSetting", "value", $newColor, "Namevalue", "infocard_color");
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
            $stmt->execute([':n' => 'infocard_color', ':v' => $newColor]);
            if (function_exists('clearSelectCache')) clearSelectCache('shopSetting');
        } catch (\Throwable $e) {
            error_log('infocard_color insert failed: ' . redfox_exception_fingerprint($e));
        }
    }
    if (isset($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ رنگ کارت تنظیم شد',
            'show_alert' => false,
            'cache_time' => 2,
        ]);
    }

    $currentRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
    $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
    $colorKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
            ],
            [
                ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
            ],
            [
                ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => 'close_stat'],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    if (function_exists('Editmessagetext') && isset($message_id)) {
        Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
    }
} elseif ($text == "🔗 اضافه کردن برنامه") {
    nm_adminInstantReply($from_id, "📌 جهت اضافه کردن لینک دانلود برنامه  نام اپ یا نام دکمه را ارسال نمایید.", $backadmin, 'HTML');
    step("getnamebtnapp", $from_id);
} elseif ($user['step'] == "getnamebtnapp") {
    if (strlen($text) > 200) {
        nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name", $text);
    nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
    step("geturlbtnapp", $from_id);
} elseif ($user['step'] == "geturlbtnapp") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO app (name, link) VALUES (:name, :link)");
    $stmt->bindParam(':name', $userdate['name'], PDO::PARAM_STR);
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ لینک اپ شما با موفقیت اضافه گردید.", $keyboardlinkapp, 'HTML');
    step("home", $from_id);
} elseif ($text == "❌ حذف برنامه") {
    nm_adminInstantReply($from_id, "📌 برای حذف برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("getnameappforremove", $from_id);
} elseif ($user['step'] == "getnameappforremove") {
    nm_adminInstantReply($from_id, "✅ برنامه با موفقیت حذف گردید.", $keyboardlinkapp, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM app WHERE name = :name");
    $stmt->bindParam(':name', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($text == "⚙️ وضعیت قابلیت ها پنل" && $adminrulecheck['rule'] == "administrator") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!in_array($panel['subvip'], ['offsubvip', 'onsubvip'])) {
        update("marzban_panel", "subvip", "offsubvip", "code_panel", $panel['code_panel']);
        $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    }
    $customvlume = json_decode($panel['customvolume'], true);
    if (!is_array($customvlume)) { $customvlume = ['f' => '0', 'n' => '0', 'n2' => '0']; }
    $_pOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_pOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig     = ($panel['config']      === 'onconfig')          ? $_pOn : $_pOff;
    $statussublink    = ($panel['sublink']      === 'onsublink')         ? $_pOn : $_pOff;
    $statusshowbuy    = ($panel['status']       === 'active')            ? $_pOn : $_pOff;
    $statusshowtest   = ($panel['TestAccount']  === 'ONTestAccount')     ? $_pOn : $_pOff;
    $statusconnecton  = ($panel['conecton']      === 'onconecton')       ? $_pOn : $_pOff;
    $status_extend    = ($panel['status_extend'] === 'on_extend')        ? $_pOn : $_pOff;
    $changeloc        = ($panel['changeloc']     === 'onchangeloc')      ? $_pOn : $_pOff;
    $inbocunddisable  = ($panel['inboundstatus'] === 'oninbounddisable') ? $_pOn : $_pOff;
    $subvip           = ($panel['subvip']        === 'onsubvip')         ? $_pOn : $_pOff;
    $customstatusf    = (((string)($customvlume['f']  ?? '0')) === '1') ? $_pOn : $_pOff;
    $customstatusn    = (((string)($customvlume['n']  ?? '0')) === '1') ? $_pOn : $_pOff;
    $customstatusn2   = (((string)($customvlume['n2'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $on_hold_test     = (((string)($panel['on_hold_test'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $version_panel_status = (((string)($panel['version_panel'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ],
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify', 'guard'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard", "guard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    if ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        unset($Bot_Status['inline_keyboard'][2]);
        unset($Bot_Status['inline_keyboard'][3]);
        unset($Bot_Status['inline_keyboard'][4]);
        unset($Bot_Status['inline_keyboard'][5]);
        unset($Bot_Status['inline_keyboard'][6]);
        unset($Bot_Status['inline_keyboard'][7]);
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    $Bot_Status = json_encode($Bot_Status);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editpanel-(.*)-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    $code_panel = $dataget[3];
    if ($type == "stautsconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("marzban_panel", "config", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "sublink") {
        if ($value == "onsublink") {
            $valuenew = "offsublink";
        } else {
            $valuenew = "onsublink";
        }
        update("marzban_panel", "sublink", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statusbuy") {
        if ($value == "active") {
            $valuenew = "disable";
        } else {
            $valuenew = "active";
        }
        update("marzban_panel", "status", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statustest") {
        if ($value == "ONTestAccount") {
            $valuenew = "OFFTestAccount";
        } else {
            $valuenew = "ONTestAccount";
        }
        update("marzban_panel", "TestAccount", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "versionpanel") {
        $valuenew = ((string)$value === "1") ? "0" : "1";
        update("marzban_panel", "version_panel", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "connecton") {
        if ($value == "onconecton") {
            $valuenew = "offconecton";
        } else {
            $valuenew = "onconecton";
        }
        update("marzban_panel", "conecton", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "stautsextend") {
        if ($value == "on_extend") {
            $valuenew = "off_extend";
        } else {
            $valuenew = "on_extend";
        }
        update("marzban_panel", "status_extend", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "changeloc") {
        if ($value == "onchangeloc") {
            $valuenew = "offchangeloc";
        } else {
            $valuenew = "onchangeloc";
        }
        update("marzban_panel", "changeloc", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "inbocunddisable") {
        if ($value == "oninbounddisable") {
            $valuenew = "offinbounddisable";
        } else {
            $valuenew = "oninbounddisable";
        }
        update("marzban_panel", "inboundstatus", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "subvip") {
        if ($value == "onsubvip") {
            $valuenew = "offsubvip";
        } else {
            $valuenew = "onsubvip";
        }
        update("marzban_panel", "subvip", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "customstatusf") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['f'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn2") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n2'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "nationalnet") {
        $valuenew = ($value == "on_national_net") ? "off_national_net" : "on_national_net";
        update("marzban_panel", "national_net_status", $valuenew, "code_panel", $code_panel);
        if ($valuenew == "on_national_net") update("marzban_panel", "stock_source_panel", $code_panel, "code_panel", $code_panel);
    } elseif ($type == "emergency") {
        $valuenew = ($value == "on_emergency_panel") ? "off_emergency_panel" : "on_emergency_panel";
        update("marzban_panel", "emergency_panel_status", $valuenew, "code_panel", $code_panel);
        if ($valuenew == "on_emergency_panel") update("marzban_panel", "emergency_source_panel", $code_panel, "code_panel", $code_panel);
    } elseif ($type == "on_hold_Test") {
        if ($value == "0") {
            $valuenew = "1";
        } else {
            $valuenew = "0";
        }
        update("marzban_panel", "on_hold_test", $valuenew, "code_panel", $code_panel);
    }
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");

    $customvlume = json_decode($panel['customvolume'], true);
    if (!is_array($customvlume)) {
        $customvlume = [];
    }
    $customvlume = array_merge([
        'f' => '0',
        'n' => '0',
        'n2' => '0',
    ], $customvlume);

    $_p2On  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_p2Off = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig  = ($panel['config']     === 'onconfig')      ? $_p2On : $_p2Off;
    $statussublink = ($panel['sublink']    === 'onsublink')      ? $_p2On : $_p2Off;
    $statusshowbuy = ($panel['status']     === 'active')         ? $_p2On : $_p2Off;
    $statusshowtest= ($panel['TestAccount']=== 'ONTestAccount')  ? $_p2On : $_p2Off;
    $statusconnecton = [
        'onconecton' => $_p2On,
        'offconecton' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['conecton'] ?? 'offconecton'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['status_extend'] ?? 'off_extend'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['changeloc'] ?? 'offchangeloc'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['inboundstatus'] ?? 'offinbounddisable'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['subvip'] ?? 'offsubvip'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['f'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n2'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['on_hold_test'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $version_panel_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['version_panel'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ],
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status = json_encode($Bot_Status);
    Editmessagetext($from_id, $message_id, ($textbotlang['Admin']['Status']['BotTitle'] ?? '⚙️ وضعیت'), $Bot_Status);
} elseif (($datain == "premium_emoji_settings" || preg_match('/^premium_emoji_settings_(\d+)$/', (string)$datain, $rxPemPgMatch)) && $adminrulecheck['rule'] == "administrator") {


    $rxPemPage = isset($rxPemPgMatch[1]) ? max(1, (int)$rxPemPgMatch[1]) : 1;
    if (function_exists('rxRenderPremiumEmojiPanel')) {
        rxRenderPremiumEmojiPanel($from_id, $rxPemPage);
    }
} elseif ($datain == "premium_emoji_status" && $adminrulecheck['rule'] == "administrator") {

    nm_adminInstantReply($from_id, "🌟 برای مدیریت ایموجی‌های پرمیوم، روی «⚙️ تنظیمات» کنار همین دکمه بزنید یا از دکمه زیر استفاده کنید.", json_encode([
        'inline_keyboard' => [
            [['text' => "⚙️ تنظیمات ایموجی پرمیوم", 'callback_data' => "premium_emoji_settings"]],
            [['text' => "❌ بستن", 'callback_data' => "close_stat"]],
        ]
    ]), 'HTML');
} elseif ($datain == "premium_emoji_noop") {
    if (!empty($callback_query_id)) {
        try { telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]); } catch (\Throwable $e) {}
    }
} elseif ($datain == "premium_emoji_replace_confirm" && $adminrulecheck['rule'] == "administrator") {


    $rxPemBase = (string)($user['Processing_value'] ?? '');
    if ($rxPemBase === '') {
        nm_adminInstantReply($from_id, "❌ خطا: ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "✅ تأیید شد. حالا <b>ایموجی پرمیوم جدید</b> را برای [<b>{$rxPemBase}</b>] ارسال کنید.\n\n💡 آیدی فعلی پس از دریافت ایموجی جدید، جایگزین می‌شود.", json_encode([
        'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
    ]), 'HTML');
    step('premium_emoji_get_id', $from_id);
} elseif ($datain == "premium_emoji_add" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌟 <b>افزودن ایموجی پرمیوم جدید</b>\n\n<b>مرحله ۱ از ۲:</b>\n📤 لطفاً ابتدا <b>ایموجی عادی (پایه)</b> را ارسال کنید.\n\nمثال: ✅ یا ❌ یا 🔥 یا 💎\n\n⏭ سپس از شما <b>ایموجی پرمیوم متناظر</b> را می‌خواهیم تا به‌صورت خودکار جایگزین شود.", $backadmin, 'HTML');
    step('premium_emoji_get_char', $from_id);
} elseif (preg_match('/^premium_emoji_edit_(\d+)$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
    $rxPemRowId = (int)$rxPemMatch[1];
    try {
        $rxPemStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id");
        $rxPemStmt->execute([':id' => $rxPemRowId]);
        $rxPemRow = $rxPemStmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { $rxPemRow = null; }
    if (!$rxPemRow) {
        nm_adminInstantReply($from_id, "❌ ایموجی یافت نشد.", null, 'HTML');
    } else {
        update("user", "Processing_value", (string)$rxPemRow['emoji'], "id", $from_id);
        $rxPemCurCid = (string)$rxPemRow['custom_emoji_id'];
        nm_adminInstantReply($from_id, "✏️ <b>ویرایش ایموجی [{$rxPemRow['emoji']}]</b>\n\n🆔 آیدی فعلی: <code>{$rxPemCurCid}</code>\n\n📤 <b>ایموجی پرمیوم جدید</b> را همین‌جا ارسال کنید — ربات آیدی جدید را خودکار تشخیص می‌دهد.", $backadmin, 'HTML');
        step('premium_emoji_edit_id', $from_id);
    }
} elseif (preg_match('/^premium_emoji_del_(\d+)$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
    $rxPemRowId = (int)$rxPemMatch[1];
    try {
        $rxPemStmt = $pdo->prepare("DELETE FROM premium_emojis WHERE id = :id");
        $rxPemStmt->execute([':id' => $rxPemRowId]);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
        nm_adminInstantReply($from_id, "🗑 ایموجی حذف شد.", json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
    } catch (\Throwable $e) {
        nm_adminInstantReply($from_id, "❌ خطا در حذف ایموجی.", null, 'HTML');
    }
} elseif (preg_match('/^premium_emoji_replace_(\d+)_(\d+)$/', (string)$datain, $rxPemReplaceMatch) && $adminrulecheck['rule'] == "administrator") {


    $rxPemReplaceId = (int)$rxPemReplaceMatch[1];
    $rxPemReplaceCid = (string)$rxPemReplaceMatch[2];
    try {
        $rxPemRowStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id LIMIT 1");
        $rxPemRowStmt->execute([':id' => $rxPemReplaceId]);
        $rxPemRowData = $rxPemRowStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($rxPemRowData)) {
            nm_adminInstantReply($from_id, "❌ ردیف مورد نظر یافت نشد. ممکن است حذف شده باشد.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }
        $rxPemReplBase = (string)$rxPemRowData['emoji'];
        $rxPemReplOldCid = (string)$rxPemRowData['custom_emoji_id'];
        $rxPemUpd = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE id = :id");
        $rxPemUpd->execute([':c' => $rxPemReplaceCid, ':t' => time(), ':id' => $rxPemReplaceId]);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
        nm_adminInstantReply($from_id, "♻️ <b>آیدی پرمیوم با موفقیت جایگزین شد!</b>\n\n• ایموجی پایه: {$rxPemReplBase}\n• 🆔 آیدی قبلی: <code>{$rxPemReplOldCid}</code>\n• 🆔 آیدی جدید: <code>{$rxPemReplaceCid}</code>", json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
    } catch (\Throwable $rxPemReplErr) {
        @error_log('[premium_emoji_replace] ' . redfox_exception_fingerprint($rxPemReplErr));
        nm_adminInstantReply($from_id, "❌ خطا در جایگزینی آیدی.", null, 'HTML');
    }
} elseif ($datain == "startelegram") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Startelegram, 'HTML');
} elseif ($text == "⬇️ حداقل مبلغ استار") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqstar", $from_id);
} elseif ($user['step'] == "getmainaqstar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancestar");
} elseif ($text == "⬆️ حداکثر مبلغ استار") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancestar", $from_id);
} elseif ($user['step'] == "maxbalancestar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancestar");
} elseif ($text == "⬇️ حداقل مبلغ nowpayment") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqnowpayment", $from_id);
} elseif ($user['step'] == "getmainaqnowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancenowpayment");
} elseif ($text == "⬆️ حداکثر مبلغ nowpayment") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancenowpayment", $from_id);
} elseif ($user['step'] == "maxbalancenowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancenowpayment");
} elseif ($text == "📚 تنظیم آموزش استار" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpstar", $from_id);
} elseif ($user['step'] == "gethelpstar") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpstar");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
} elseif ($text == "💰 کش بک استار") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید )", $backadmin, 'HTML');
    step("chashbackstar", $from_id);
} elseif ($user['step'] == "chashbackstar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackstar");
} elseif ($text == "🔋 تنظیم سریع قیمت حجم") {
    nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت، ابتدا «قیمت گروه کاربر عادی (f)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpricef", $from_id);
} elseif ($user['step'] == "getpricef") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده عادی (n)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpricnn", $from_id);
} elseif ($user['step'] == "getpricnn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده پیشرفته (n2)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpricnn2", $from_id);
} elseif ($user['step'] == "getpricnn2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomvolume", $pricelist, null, null);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($text == "⏳ تنظیم سریع قیمت زمان") {
    nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت، ابتدا «قیمت گروه کاربر عادی (f)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpriceftime", $from_id);
} elseif ($user['step'] == "getpriceftime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده عادی (n)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpricnntime", $from_id);
} elseif ($user['step'] == "getpricnntime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده پیشرفته (n2)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
    step("getpricnn2time", $from_id);
} elseif ($user['step'] == "getpricnn2time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomtime", $pricelist, null, null);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "changeloclimit") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب نمایید.
۱ - محدودیت کلی کاربر در کل چند بار می تواند تغییر لوکیشن انجام دهد.
۲ - محدودیت رایگان  کاربر از محدودیت کلی چند بار می تواند رایگان تغییر لوکیشن دهد.", $keyboardchangelimit, 'HTML');
} elseif ($text == "↙️ محدودیت کلی") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    nm_adminInstantReply($from_id, "📌  محدودیت کلی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['all']}", $backadmin, 'HTML');
    step("limitchangeall", $from_id);
} elseif ($user['step'] == "limitchangeall") {
    nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['all'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🆓 محدودیت رایگان") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    nm_adminInstantReply($from_id, "📌  محدودیت رایگانی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['free']}", $backadmin, 'HTML');
    step("limitfreechangefree", $from_id);
} elseif ($user['step'] == "limitfreechangefree") {
    nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['free'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🔄 ریست محدودیت کل کاربران") {
    $keyboarddata = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و صفر شدن", 'callback_data' => 'reasetchangeloc'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با تأیید گزینه زیر، تمام تغییر لوکیشن هایی که توسط کاربر انجام شده است صفر خواهد شد. در صورت موافقت، روی گزینه زیر کلیک کنید.", $keyboarddata, 'HTML');
} elseif ($datain == "reasetchangeloc") {
    Editmessagetext($from_id, $message_id, "✅ تمامی محدودیت کاربران صفر شد.", null);
    update("user", "limitchangeloc", "0", null, null);
} elseif (preg_match('/changeloclimitbyuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 محدودیت جدیدی که میخواهید برای کاربر تنظیم کنید را ارسال کنید توجه داشته باشید این قابلیت تعداد تعییر لوکیشن انجام شده را تغییر میدهد", $backadmin, 'HTML');
    step("getlimitchangenewbyuser", $from_id);
} elseif ($user['step'] == "getlimitchangenewbyuser") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    update("user", "limitchangeloc", $text, "id", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ تعداد استفاده کاربر با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/hidepanel_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "❌ پنل هایی که می خواهید برای این نماینده نشان داده نشود از دکمه  زیر انتخاب نمایید بعد از انتخاب دستور /finish را ارسال کنید تا ذخیره شود.", $json_list_marzban_panel, 'HTML');
    step("getpanelhidebotsaz", $from_id);
} elseif ($text == "/finish") {
    nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل های برای کاربر مخفی شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getpanelhidebotsaz") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /finish را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif (preg_match('/removehide_(\w+)/', $datain, $datagetr)) {
    global $list_hide_panel;
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $id_user, "select")['hide_panel'], true);
    $list_hide_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_panel as $panelname) {
        $list_hide_panel['keyboard'][] = [
            ['text' => $panelname]
        ];
    }
    $list_hide_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $list_hide_panel = json_encode($list_hide_panel);
    nm_adminInstantReply($from_id, "❌ از لیست زیر پنل هایی که میخواهید مجددا در ربات نماینده نشان داده شود را  انتخاب نمایید بعد از انتخاب تمامی پنل ها  دستور /remove را ارسال کنید تا ذخیره شود.", $list_hide_panel, 'HTML');
    step("getremovehidepanel", $from_id);
} elseif ($text == "/remove") {
    nm_adminInstantReply($from_id, "✅ نمایش پنل ها با موفقیت انجام و پنل های برای کاربر فعال شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getremovehidepanel") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (!in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_panel as $panel) {
        if ($panel == $text) {
            unset($list_panel[$count]);
            break;
        }
        $count += 1;
    }
    $list_panel = array_values($list_panel);
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /remove را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($datain == "voloume_or_day_all") {
    $userslistData = '[]';
    if (is_file('cronbot/username.json')) {
        $fileContents = file_get_contents('cronbot/username.json');
        if ($fileContents !== false && $fileContents !== '') {
            $userslistData = $fileContents;
        }
    }
    $userslist = json_decode($userslistData, true);
    if (is_array($userslist) && count($userslist) != 0) {
        nm_adminInstantReply($from_id, "❌ سیستم ارسال هدیه درحال انجام عملیات است پس از پایان و اطلاع رسانی  می توانید پیام جدید را ارسال نمایید.", $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 برای سرویس های کدام پنل میخواهید حجم یا زمان هدیه دهید؟", $json_list_marzban_panel, "html");
    step("getpanelgift", $from_id);
} elseif ($user['step'] == "getpanelgift") {
    $panel = select("marzban_panel", "*", "name_panel", $text, "count");
    if ($panel == 0) {
        nm_adminInstantReply($from_id, "❌ پنل وجود ندارد", null, "html");
        return;
    }
    savedata("clear", "name_panel", $text);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔋 حجم", 'callback_data' => 'typegift_volume'],
                ['text' => "⏳ زمان", 'callback_data' => 'typegift_day'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 یکی از هدیه های زیر را انتخاب نمایید.", $keyboardstatistics, "html");
    step('home', $from_id);
} elseif (preg_match('/typegift_(\w+)/', $datain, $datagetr)) {
    $typegift = $datagetr[1];
    savedata("save", "typegift", $typegift);
    deletemessage($from_id, $message_id);
    if ($typegift == "volume") {
        nm_adminInstantReply($from_id, "📌 چند گیگ حجم می خواهید به سرویس های کاربر اضافه شود", $backadmin, "html");
    } else {
        nm_adminInstantReply($from_id, "📌 چند روز می خواهید به سرویس های کاربران اضافه شود", $backadmin, "html");
    }
    step("getvaluegift", $from_id);
} elseif ($user['step'] == "getvaluegift") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "value", $text);
    nm_adminInstantReply($from_id, "📌 متنی که می خواهید برای کاربر ارسال شود را ارسال کنید", $backadmin, "html");
    step("gettextgift", $from_id);
} elseif ($user['step'] == "gettextgift") {
    savedata("save", "text", $text);
    savedata("save", "id_admin", $from_id);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و شروع فرآیند", 'callback_data' => 'startgift'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 ادمین عزیز با تایید بر روی گزینه زیر فرآیند اعمال هدیه ها آغاز خواهد شد توجه داشته باشید با توجه به محدودیت ها اعمال هدیه زمان بر خواهد بود.", $keyboardstatistics, "html");
    step("home", $from_id);
} elseif ($datain == "startgift") {
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌ لفو ارسال هدیه", 'callback_data' => 'cancel_gift'],
            ],
        ]
    ]);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typegift'])) {
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید.", $keyboardstatistics, "html");
        return;
    }
    $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات ارسال هدیه با موفقیت آغاز گردید پس از اضافه شدن و اتمام به شما اطلاع داده می شود.", $keyboardstatistics);
    $userdata['id_message'] = $message_id['result']['message_id'];
    $stmt = $pdo->prepare("SELECT username FROM invoice WHERE  (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$userdata['name_panel']}' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $userslist = json_encode($stmt->fetchAll());
    file_put_contents('cronbot/gift', json_encode($userdata));
    file_put_contents('cronbot/username.json', $userslist);
} elseif ($datain == "cancel_gift") {
    unlink('cronbot/username.json');
    unlink('cronbot/gift');
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "📌 ارسال هدیه لغو گردید.", null, 'HTML');
} elseif (preg_match('/expireset_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "🕘 زمان انقضا نمایندگی را ارسال نمایید. پس از پایان تعداد روز تعیین شده کاربر از حالت نمایندگی خارج شده و گروه کاربر f خواهد شد.
توجه داشته باشید این قابلیت ارتباطی با قابلیت ربات ساز یا ربات فروش نماینده ندارد و فقط مربوط به ربات اصلی شما است

📌 تعداد روز را ارسال نمایید", $backadmin, 'HTML');
    step("gettime_expire_agent", $from_id);
} elseif ($user['step'] == "gettime_expire_agent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $timestamp = time() + (intval(value: $text) * 86400);
    update("user", "expire", $timestamp, "id", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ تاریخ انقضا تنظیم شد.
📌 پس از پایان زمان گروه کاربری کاربر به f تغییر داده می شود و به کاربر اطلاع داده می شود.", $keyboardadmin, 'HTML');
} elseif ($text == "♻️ نمایش گروهی شماره کارت") {
    nm_adminInstantReply($from_id, "📌 لیست آیدی هایی که  می خواهید شماره کارت برایشان نشان داده شود را ارسال شود
مثال :
1234435423
23423131", $backadmin, 'HTML');
    step("getlistidcart", $from_id);
} elseif ($user['step'] == "getlistidcart") {
    $list = explode("\n", $text);
    foreach ($list as $id_user) {
        if (!userExists($id_user)) {
            nm_adminInstantReply($from_id, "📌 کاربر با آیدی عددی $id_user در  دیتابیس وجود ندارد", $backadmin, 'HTML');
            continue;
        }
        update("user", "cardpayment", "1", "id", $id_user);
    }
    nm_adminInstantReply($from_id, "✅ شماره کارت برای کاربران ارسال شده فعال گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "📄 خروجی افراد شماره کارت فعال") {
    $listusers = select("user", "id", "cardpayment", "1", "fetchAll");
    if (!$listusers) {
        nm_adminInstantReply($from_id, "📌 برای کاربری شماره کارت فعال نشده است", $CartManage, 'HTML');
        return;
    }
    $filename = 'cartlist.txt';
    foreach ($listusers as $id_user) {
        file_put_contents($filename, $id_user['id'] . "\n", FILE_APPEND);
    }
    sendDocument($from_id, $filename, "🪪 لیست کاربرانی که شماره کارت برای آنها فعال است");
    unlink($filename);
} elseif ($text == "🎉 پورسانت فقط برای خرید اول" && $adminrulecheck['rule'] == "administrator") {
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "on_buy_porsant") {
    update("affiliates", "porsant_one_buy", "off_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($datain == "off_buy_porsant") {
    update("affiliates", "porsant_one_buy", "on_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($text == "متن توضیحات درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_request_agent_dec']}</code>", $backadmin, 'HTML');
    step('text_request_agent_dec', $from_id);
} elseif ($user['step'] == "text_request_agent_dec") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_request_agent_dec");
    step('home', $from_id);
} elseif (preg_match('/changestatusadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        nm_adminInstantReply($from_id, "❌ هنوز به کانفیگ متصل نشده است کانفیگ و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisableadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disablebyadmin", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/removefull-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و حذف ", 'callback_data' => "confirmremovefulls-" . $id_invoice],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 با تایید بر روی گزینه زیر این سرویس بطور کامل از دیتابیس ربات حذف خواهد شد و دیگرجزء آمار حساب نخواهد شد ( این بخش سرویس را از پنل حذف نمی کند و فقط از دیتابیس ربات حذف می کند)", $bakinfos);
} elseif (preg_match('/confirmremovefulls-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invocie = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id_invoice");
    $stmt->bindParam(':id_invoice', $id_invoice, PDO::PARAM_STR);
    $stmt->execute();
    Editmessagetext($from_id, $message_id, "✅ سرویس با موفقیت حذف گردید.", json_encode(['inline_keyboard' => []]));
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => "🔗 یک ادمین یک سرویس را از دیتابیس ربات حذف کرد.

- آیدی عددی ادمین :‌$from_id
- نام ادمین : $first_name
- نام کاربری سرویس :‌ {$invocie['username']}",
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "🛒 اضافه کردن دسته بندی") {
    nm_adminInstantReply($from_id, "📌 جهت اضافه کردن دسته بندی نام دسته بندی را ارسال کنید.", $backadmin, 'HTML');
    step("getremarkcategory", $from_id);
} elseif ($user['step'] == "getremarkcategory") {
    nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت اضافه گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
    $stmt->bindParam(1, $text);
    $stmt->execute();
} elseif ($text == "❌ حذف دسته بندی") {
    nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت حذف انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("removecategory", $from_id);
} elseif ($user['step'] == "removecategory") {
    nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت حذف گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("DELETE FROM category WHERE remark = :remark ");
    $stmt->bindParam(':remark', $text);
    $stmt->execute();
} elseif ($text == "مخفی کردن پنل" && $adminrulecheck['rule'] == "administrator") {
    if ($user['Processing_value_one'] != "/all") {
        nm_adminInstantReply($from_id, "📌 این قابلیت فقط زمانی کاربرد دارد که شما لوکیشن محصول را /all تعریف کرده باشید.", null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 در صورتی که لوکیشن پنل را /all انتخاب کرده باشید اما نیاز داشته باشید که یک پنل را نشان ندهید از این قابلیت می توانید استفاده نمایید

جهت مخفی کردن پنل  از لیست زیر پنل های خود را اتنخاب کنید سپس دستور /end_hide را ارسال نمایید.", $json_list_marzban_panel, 'HTML');
    step('getlistpanel', $from_id);
} elseif ($text == "/end_hide") {
    nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل ها برای محصول انتخابی مخفی شد.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getlistpanel") {
    $list_panel = json_decode(select("product", "hide_panel", "id", $user['Processing_value'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("product", "hide_panel", json_encode($list_panel), "id", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /end_hide را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($text == "حذف کلی پنل های مخفی" && $adminrulecheck['rule'] == "administrator") {
    update("product", "hide_panel", "{}", "name_product", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ تمامی پنل های مخفی حذف شدند", null, 'HTML');
} elseif ($text == "🔗 وبهوک مجدد ربات های نماینده") {
    $projectRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : getcwd();
    try {
        require_once rtrim((string)$projectRoot, '/\\') . '/lib/ResellerBotManager.php';
        nm_adminInstantReply($from_id, "📌 بازبینی امن فایل‌ها و Webhook ربات‌ها آغاز شد...", null, 'HTML');
        $repair = (new RedFoxResellerBotManager($pdo, (string)$projectRoot))->repairAll();
        $okCount = (int)($repair['ok'] ?? 0);
        $failedCount = (int)($repair['failed'] ?? 0);
        nm_adminInstantReply($from_id, "✅ عملیات پایان یافت. موفق: {$okCount} — ناموفق: {$failedCount}", null, 'HTML');
    } catch (Throwable $repairError) {
        error_log('[admin-maintenance] reseller bot repair failed: ' . redfox_exception_fingerprint($repairError));
        nm_adminInstantReply($from_id, "❌ بازبینی ربات‌ها ناموفق بود؛ جزئیات امن در لاگ ثبت شد.", null, 'HTML');
    }
} elseif (preg_match('/statuscronuser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $user_status = select("user", "*", "id", $id_user);
    if (intval($user_status['status_cron']) == 0) {
        update("user", "status_cron", "1", "id", $id_user);
        nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر فعال گردید.", null, 'HTML');
    } else {
        update("user", "status_cron", "0", "id", $id_user);
        nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر غیرفعال گردید.", null, 'HTML');
    }
} elseif ($text == "🗂 مدیریت دسته بندی") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
} elseif ($text == "⬅️ بازگشت به منوی فروشگاه") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 مدیریت محصولات" || $datain == "backproductadmin") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
} elseif ($text == "✏️ ویرایش دسته بندی") {
    nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت ویرایش انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("editcategory_name", $from_id);
} elseif ($user['step'] == "editcategory_name") {
    savedata("clear", "category", $text);
    nm_adminInstantReply($from_id, "📌  نام جدید دسته بندی را ارسال کنید", $backadmin, 'HTML');
    step("get_name_new_category", $from_id);
} elseif ($user['step'] == "get_name_new_category") {
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, "✅ نام دسته بندی با موفقیت تغییر کرد.", $keyboard_Category_manage, 'HTML');
    step("home", $from_id);
    update("category", "remark", $text, "remark", $userdata['category']);
    update("product", "category", $text, "category", $userdata['category']);
} elseif ($datain == "zerobalance") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserzero') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserzero') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "✏️ ویرایش برنامه") {
    nm_adminInstantReply($from_id, "📌 برای ویرایش برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("edit_app", $from_id);
} elseif ($user['step'] == "edit_app") {
    savedata("clear", "nameapp", $text);
    step("get_new_lin_app", $from_id);
    nm_adminInstantReply($from_id, "📌 لینک جدید اپ را ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_lin_app") {
    step("home", $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, "✅ لینک برنامه با موفقیت بروزرسانی گردید.", $keyboardlinkapp, 'HTML');
    update("app", "link", $text, "name", $userdata['nameapp']);
} elseif ($datain == "nowpaymentsetting") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "⏳ زمان تایید خودکار بدون بررسی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید که قابلیت تایید خودکار بدون بررسی  بعد از چند دقیقه رسید را تایید کند.
زمان خود را بر حسب دقیقه ارسال کنید
زمان فعلی : {$setting['timeauto_not_verify']}", $backadmin, 'HTML');
    step("gettimeauto", $from_id);
} elseif ($user['step'] == "gettimeauto") {
    if (!is_numeric($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("setting", "timeauto_not_verify", $text);
    nm_adminInstantReply($from_id, "✅ زمان با موفقیت ثبت گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "نمایش برای خرید اول") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :name_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response, 'HTML');
} elseif (preg_match('/status_on_buy-(.*)-(.*)/', $datain, $dataget)) {
    $code_product = $dataget[1];
    $status_now = $dataget[2];
    if ($status_now == '0') {
        $status_now = '1';
    } else {
        $status_now = '0';
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET one_buy_status = :one_buy_status WHERE code_product = :code_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':one_buy_status', $status_now);
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response);
} elseif ($text == "💳 استثناء کردن کاربر از تایید خودکار") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید
⚠️ این بخش برای تایید خودکار بدون بررسی می باشد", $Exception_auto_cart_keyboard, 'HTML');
} elseif ($text == "➕ استثناء کردن کاربر") {
    nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را ارسال کنید", $backadmin, 'HTML');
    step("getidExceptio", $from_id);
} elseif ($user['step'] == "getidExceptio") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود دارد", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions[] = $text;
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت به لیست اضافه گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "❌ حذف کاربر از لیست") {
    sendmessage($from_id, "📌 آیدی عددی کاربر را جهت حذف از لیست ارسال کنید", $backadmin, 'HTML');
    step("getidExceptioremove", $from_id);
} elseif ($user['step'] == "getidExceptioremove") {
    if (!userExists($text)) {
        sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (!in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود ندارد", $backadmin, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_Exceptions as $list) {
        if ($list == $text) {
            unset($list_Exceptions[$count]);
            break;
        }
        $count += 1;
    }
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت از لیست حذف گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "👁 نمایش لیست افراد") {
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (count($list_Exceptions) == 0) {
        sendmessage($from_id, "❌ کاربری در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $list = "";
    foreach ($list_Exceptions as $list_ex) {
        $list .= $list_ex . "\n";
    }
    sendmessage($from_id, "لیست افراد👇", null, 'HTML');
    sendmessage($from_id, $list, null, 'HTML');
} elseif ($text == "تنظیم api" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_floypay")['ValuePay'];
    $textaqayepardakht = "api دریافت شده را در این بخش ارسال کنید

مرچنت کد فعلی شما : $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('marchent_floypay', $from_id);
} elseif ($user['step'] == "marchent_floypay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $Swapinokey, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_floypay");
    step('home', $from_id);
}