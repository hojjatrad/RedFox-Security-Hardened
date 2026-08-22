<?php
require_once __DIR__ . '/lib/CronGuard.php';
rx_cron_authorize();
/**
 * Red Fox — سیستم تمدید خودکار + یادآوری هوشمند انقضا و حجم.
 *
 * این فایل باید با cron job هر ساعت اجرا شود:
 *   0 * * * * php /path/to/cron_renewal.php
 *
 * یا با HTTP POST/GET و Header امن X-Cron-Secret (هرگز Secret را در URL نگذارید).
 *
 * کارایی:
 *  ۱) یادآوری انقضا: ۳ روز و ۱ روز قبل از انقضا → پیام + دکمه تمدید سریع
 *  ۲) تمدید خودکار: اگر کاربر فعال کرده، از موجودی کیف پول خودکار تمدید می‌شود
 *  ۳) هشدار حجم: وقتی حجم به ۹۰٪ یا ۱۰۰٪ رسید → پیام
 *  ۴) آپدیت خودکار وضعیت فاکتورها (expired/limited)
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Tehran');
$isCli = PHP_SAPI === 'cli';

define('REFACTORED_LEGACY_ROOT', __DIR__);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/botapi.php';
require_once __DIR__ . '/jdf.php';
if (function_exists('rx_cron_telemetry_start')) rx_cron_telemetry_start($pdo,'renewal');

// دسترسی در ابتدای فایل توسط CronGuard به‌صورت یکپارچه کنترل شده است.

$log = [];
function rxLog($msg) {
    global $log;
    $log[] = date('H:i:s') . ' — ' . $msg;
}

// ── تنظیمات ──
$settings = [];
try {
    $row = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $settings = $row ?: [];
} catch (Throwable $e) {}

$reminderEnabled = (string)($settings['renewal_reminder_status'] ?? 'on') === 'on';
$reminderDays = array_filter(array_map('intval', explode(',', (string)($settings['renewal_reminder_days'] ?? '3,1'))));
$autoRenewEnabled = (string)($settings['auto_renew_status'] ?? 'off') === 'on';
$volumeWarnEnabled = (string)($settings['volume_warn_status'] ?? 'on') === 'on';

rxLog("Start — reminder=" . ($reminderEnabled ? 'ON' : 'off') . " autoRenew=" . ($autoRenewEnabled ? 'ON' : 'off'));

// ── ۱) یادآوری انقضا + تمدید خودکار ──
if ($reminderEnabled || $autoRenewEnabled) {
    $now = time();
    $doneStatuses = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";

    // همه‌ی فاکتورهای فعال که نزدیک انقضا هستند
    foreach ($reminderDays as $daysBefore) {
        $targetTs = $now + ($daysBefore * 86400); // انقضای هدف
        $dayStart = strtotime(date('Y-m-d 00:00:00', $targetTs));
        $dayEnd = strtotime(date('Y-m-d 23:59:59', $targetTs));

        try {
            $stmt = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location,
                                          i.name_product, i.price_product, i.Volume, i.Service_time,
                                          i.Status, i.notifctions, i.bottype,
                                          u.Balance, u.User_Status
                                   FROM invoice i
                                   INNER JOIN user u ON u.id = i.id_user
                                   WHERE i.Status IN ($doneStatuses)
                                   AND i.bottype IS NULL
                                   LIMIT 5000");
            $stmt->execute();
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rxLog("Query error: " . redfox_exception_fingerprint($e));
            $invoices = [];
        }

        foreach ($invoices as $inv) {
            // خواندن انقضا از پنل
            $expireTs = 0;
            $panel = null;
            try {
                $panel = select("marzban_panel", "*", "name_panel", $inv['Service_location'], "select");
                if ($panel && in_array($panel['type'] ?? '', ['marzban', 'marzneshin'], true) && class_exists('ManagePanel')) {
                    $mp = new ManagePanel();
                    $du = $mp->DataUser($inv['Service_location'], $inv['username']);
                    $expireTs = (int)($du['expire'] ?? 0);
                }
            } catch (Throwable $e) { continue; }

            if ($expireTs <= 0) continue;
            if ($expireTs < $dayStart || $expireTs > $dayEnd) continue;

            // بررسی اینکه قبلاً یادآوری نشده
            $notifs = json_decode($inv['notifctions'] ?? '{}', true) ?: [];
            $notifKey = 'renewal_remind_' . $daysBefore . '_' . date('Ymd', $expireTs);
            if (!empty($notifs[$notifKey])) continue;

            $userId = (string)$inv['id_user'];
            $userBalance = (int)($inv['Balance'] ?? 0);
            $price = (int)$inv['price_product'];
            $daysLeft = max(0, floor(($expireTs - $now) / 86400));

            // ── تمدید خودکار ──
            if ($autoRenewEnabled) {
                $autoRenewPref = false;
                try {
                    $arStmt = $pdo->prepare("SELECT auto_renew FROM user WHERE id = ? LIMIT 1");
                    $arStmt->execute([$userId]);
                    $autoRenewPref = (int)$arStmt->fetchColumn() === 1;
                } catch (Throwable $e) {}

                if ($autoRenewPref && $userBalance >= $price && $price > 0 && $panel) {
                    // تمدید از کیف پول
                    try {
                        $mp = new ManagePanel();
                        $extResult = $mp->extend($panel['Methodextend'] ?? 'ریست حجم و زمان',
                            $inv['Volume'], $inv['Service_time'], $inv['username'],
                            'auto_renew', $panel['code_panel']);
                        if (!empty($extResult['status'])) {
                            // کسر از موجودی
                            $newBal = $userBalance - $price;
                            update("user", "Balance", $newBal, "id", $userId);
                            // ثبت یادآوری
                            $notifs[$notifKey] = 'auto_renewed';
                            update("invoice", "notifctions", json_encode($notifs), "id_invoice", $inv['id_invoice']);
                            // اطلاع به کاربر
                            @sendmessage($userId,
                                "✅ <b>سرویس شما خودکار تمدید شد!</b>\n\n" .
                                "🔄 سرویس: {$inv['username']}\n" .
                                "💰 مبلغ: " . number_format($price) . " تومان از کیف پول شما کسر شد\n" .
                                "💎 موجودی باقی‌مانده: " . number_format($newBal) . " تومان",
                                null, 'HTML');
                            rxLog("Auto-renewed #{$inv['id_invoice']} user=$userId");
                            continue;
                        }
                    } catch (Throwable $e) {
                        rxLog("Auto-renew error #{$inv['id_invoice']}: " . redfox_exception_fingerprint($e));
                    }
                }
            }

            // ── یادآوری انقضا ──
            if ($reminderEnabled) {
                $msg = "⏰ <b>یادآوری انقضای سرویس</b>\n\n";
                $msg .= "👤 سرویس: <code>{$inv['username']}</code>\n";
                $msg .= "📦 محصول: {$inv['name_product']}\n";
                $msg .= "⏳ " . ($daysBefore === 1 ? "فردا" : "$daysBefore روز دیگر") . " منقضی می‌شود!\n\n";

                if ($userBalance >= $price && $price > 0) {
                    $msg .= "✅ موجودی شما کافی است ($userBalance تومان).\n";
                    $msg .= "💡 برای تمدید روی دکمه زیر بزنید:";
                    $kb = json_encode(['inline_keyboard' => [[
                        ['text' => '🔄 تمدید سریع (' . number_format($price) . ' ت)', 'callback_data' => 'autorenew_' . $inv['id_invoice']],
                    ]]]);
                } else {
                    $msg .= "⚠️ موجودی شما کافی نیست.\n";
                    $msg .= "💰 موجودی فعلی: " . number_format($userBalance) . " تومان\n";
                    $msg .= "💸 مبلغ تمدید: " . number_format($price) . " تومان\n\n";
                    $msg .= "برای شارژ کیف پول از ربات استفاده کنید.";
                    $kb = null;
                }

                @sendmessage($userId, $msg, $kb, 'HTML');

                // ثبت یادآوری
                $notifs[$notifKey] = 'sent';
                update("invoice", "notifctions", json_encode($notifs), "id_invoice", $inv['id_invoice']);
                rxLog("Reminder sent #{$inv['id_invoice']} user=$userId days=$daysBefore");
            }
        }
    }
}

// ── ۲) هشدار حجم (۹۰٪ مصرف) ──
if ($volumeWarnEnabled) {
    try {
        $stmt = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location, i.notifctions
                               FROM invoice i
                               WHERE i.Status IN ('active','sendedwarn','send_on_hold')
                               AND i.bottype IS NULL
                               LIMIT 5000");
        $stmt->execute();
        $volInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $volInvoices = []; }

    foreach ($volInvoices as $inv) {
        try {
            $panel = select("marzban_panel", "*", "name_panel", $inv['Service_location'], "select");
            if (!$panel || !in_array($panel['type'] ?? '', ['marzban', 'marzneshin'], true)) continue;
            $mp = new ManagePanel();
            $du = $mp->DataUser($inv['Service_location'], $inv['username']);
            $dataLimit = (int)($du['data_limit'] ?? 0);
            $usedTraffic = (int)($du['used_traffic'] ?? 0);
            if ($dataLimit <= 0) continue;

            $pct = ($usedTraffic / $dataLimit) * 100;
            if ($pct < 90) continue;

            $notifs = json_decode($inv['notifctions'] ?? '{}', true) ?: [];
            $volKey = 'vol_warn_' . date('Ymd');
            if (!empty($notifs[$volKey])) continue;

            $remaining = max(0, $dataLimit - $usedTraffic);
            $remainingGb = round($remaining / 1073741824, 1);

            @sendmessage((string)$inv['id_user'],
                "⚠️ <b>حجم سرویس شما رو به اتمام است</b>\n\n" .
                "👤 سرویس: <code>{$inv['username']}</code>\n" .
                "📊 مصرف: " . round($pct, 0) . "%\n" .
                "💎 حجم باقی‌مانده: $remainingGb گیگابایت\n\n" .
                "💡 برای خرید حجم اضافه از ربات استفاده کنید.",
                null, 'HTML');

            $notifs[$volKey] = 'sent';
            update("invoice", "notifctions", json_encode($notifs), "id_invoice", $inv['id_invoice']);
            rxLog("Volume warn #{$inv['id_invoice']} user={$inv['id_user']} pct=" . round($pct));
        } catch (Throwable $e) {}
    }
}

// ── گزارش ──
rxLog("Done.");
$output = implode("\n", $log);
if ($isCli) {
    echo $output . "\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "✅ cron_renewal.php executed\n\n" . $output;
}

rx_require_schema($pdo,[],['user'=>['auto_renew'],'setting'=>['renewal_reminder_status','renewal_reminder_days','auto_renew_status','volume_warn_status']]);
