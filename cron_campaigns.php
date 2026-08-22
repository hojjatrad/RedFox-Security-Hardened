<?php
require_once __DIR__ . '/lib/CronGuard.php';
rx_cron_authorize();
/**
 * Red Fox — cron مرکز کمپین‌ها (Win-back + Health Monitor + Flash Sale auto-notify).
 * هر ساعت اجرا شود: 0 * * * * php /path/to/cron_campaigns.php
 */
error_reporting(E_ERROR); ini_set('display_errors','0'); ini_set('log_errors','1');
date_default_timezone_set('Asia/Tehran');
define('REFACTORED_LEGACY_ROOT', __DIR__);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/botapi.php';
if (function_exists('rx_cron_telemetry_start')) rx_cron_telemetry_start($pdo,'campaigns');
$log = [];
function rxcl($m) { global $log; $log[] = date('H:i:s').' — '.$m; }

$settings = [];
try { $settings = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}

// ── ۱) Win-back: پیام به کاربران غیرفعال ──
if (($settings['winback_status'] ?? '') === 'on') {
    $days = (int)($settings['winback_days'] ?? 30);
    $discount = (int)($settings['winback_discount'] ?? 20);
    $cutoff = time() - ($days * 86400);
    $done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
    try {
        // کاربرانی که آخرین خریدشان بیش از N روز پیش بوده و الان سرویس ندارند
        $stmt = $pdo->prepare("SELECT u.id, u.username, MAX(i.time_sell) last_buy
                               FROM user u
                               INNER JOIN invoice i ON i.id_user = u.id
                               WHERE u.User_Status != 'block'
                               AND i.time_sell REGEXP '^[0-9]+$'
                               GROUP BY u.id
                               HAVING last_buy < ? AND last_buy > 0
                               LIMIT 100");
        $stmt->execute([$cutoff]);
        $churned = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sent = 0;
        foreach ($churned as $c) {
            // بررسی اینکه سرویس فعال ندارد
            try {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND Status IN ($done)");
                $chk->execute([$c['id']]);
                if ((int)$chk->fetchColumn() > 0) continue; // هنوز سرویس فعال دارد
            } catch (Throwable $e) { continue; }
            // بررسی اینکه قبلاً winback ارسال نشده (در ۷ روز اخیر)
            try {
                $wbChk = $pdo->prepare("SELECT COUNT(*) FROM daily_rewards WHERE user_id = ? AND last_claim_date = 'winback_sent'");
                // ساده‌تر: استفاده از notifctions یا یک جدول جدا
            } catch (Throwable $e) {}
            $msg = "👋 دلتنگ شدیم!\n\n🎁 به‌عنوان پیشنهاد ویژه‌ی بازگشت، {$discount}٪ تخفیف روی خرید بعدی شما!\n\n همین حالا از ربات خرید کنید و کد تخفیف <code>BACK$discount</code> را وارد کنید. 💜";
            @sendmessage((string)$c['id'], $msg, null, 'HTML');
            $sent++;
        }
        rxcl("Win-back: sent to $sent users");
    } catch (Throwable $e) { rxcl("Win-back error: ".redfox_exception_fingerprint($e)); }
}

// ── ۲) Health Monitor: بررسی سلامت پنل‌ها ──
try {
    $panels = $pdo->query("SELECT code_panel, name_panel, url_panel, username_panel, password_panel, type FROM marzban_panel WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($panels as $panel) {
        $panel = function_exists('rx_secret_decrypt_panel_row') ? rx_secret_decrypt_panel_row($panel) : $panel;
        $url = rtrim((string)($panel['url_panel'] ?? ''), '/');
        if ($url === '') continue;
        $healthy = false; $latency = 0;
        $startTs = microtime(true);
        try {
            $ch = curl_init($url . '/api/admin/token');
            if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch, 'panel');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['username'=>$panel['username_panel'],'password'=>$panel['password_panel'],'grant_type'=>'password']), CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_SSL_VERIFYPEER => true]);
            $requestUrl = $url . '/api/admin/token';
            $policy = redfox_apply_panel_curl_url_policy($ch, $requestUrl);
            if (empty($policy['ok'])) { curl_close($ch); throw new RuntimeException('panel_endpoint_blocked'); }
            $resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $latency = round((microtime(true) - $startTs) * 1000);
            $healthy = ($code === 200);
        } catch (Throwable $e) {}
        $status = $healthy ? 'healthy' : 'down';
        try {
            $pdo->prepare("UPDATE marzban_panel SET last_health_check = ?, health_status = ? WHERE code_panel = ?")
                ->execute([date('Y-m-d H:i:s'), $status . " (${latency}ms)", $panel['code_panel']]);
        } catch (Throwable $e) {}
        if (!$healthy) rxcl("Panel DOWN: {$panel['name_panel']}");
    }
    rxcl("Health check: ".count($panels)." panels checked");
} catch (Throwable $e) { rxcl("Health error: ".redfox_exception_fingerprint($e)); }

// ── ۳) Flash Sale: غیرفعال‌سازی خودکار کمپین‌های منقضی ──
try {
    $pdo->exec("UPDATE flash_campaigns SET is_active = 0 WHERE ends_at < NOW() AND is_active = 1");
    rxcl("Flash sale expiry check done");
} catch (Throwable $e) {}

header('Content-Type: text/plain; charset=utf-8');
echo "✅ cron_campaigns.php\n\n" . implode("\n", $log) . "\n";
