<?php
/**
 * Red Fox — مدیریت نمایندگان (Reseller management) در پنل تحت وب.
 *
 * این صفحه روی فیلدهای موجود سیستم نمایندگی (جدول user.agent / user.maxbuyagent /
 * user.Balance و جدول Requestagent) عمل می‌کند و منطق تأیید/رد را دقیقاً مطابق
 * گردش‌کار داخل خود ربات (re/rx/admin/settings.php) تکرار می‌کند تا چیزی نشکنه.
 *
 * نقش‌ها: f = کاربر عادی (غیرنماینده) ، n = نماینده عادی ، n2 = نماینده پیشرفته
 */

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}

require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../lib/ResellerBotManager.php';
require_once __DIR__ . '/../lib/TelegramWebhook.php';
require_once __DIR__ . '/lib/icons.php';

// احراز هویت ادمین
$adminRow = null;
if (!empty($_SESSION['user'])) {
    $q = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
    $q->bindValue(':u', $_SESSION['user'], PDO::PARAM_STR);
    $q->execute();
    $adminRow = $q->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) {
    header('Location: login.php');
    exit;
}
// فقط ادمین اصلی می‌تواند نماینده‌ها را مدیریت کند
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');

$flash = '';
$flashType = 'info';

/**
 * ارسال پیام به یک کاربر از طریق ربات (در صورت موجود بودن توابع botapi).
 */
function redfox_agents_notify($chatId, $text): void
{
    $chatId = trim((string)$chatId);
    if ($chatId === '' || !ctype_digit($chatId)) {
        return;
    }
    try {
        if (function_exists('sendmessage')) {
            sendmessage($chatId, $text, null, 'HTML');
        } elseif (function_exists('telegram')) {
            telegram('sendmessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        }
    } catch (Throwable $e) {
        error_log('[agents.php] notify failed: ' . redfox_exception_fingerprint($e));
    }
}

/**
 * قیمت درخواست نمایندگی (برای بازگشت وجه هنگام رد).
 */
function redfox_agent_request_price(): int
{
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT agentreqprice FROM setting LIMIT 1");
        $st->execute();
        $val = $st->fetchColumn();
        if ($val !== false && $val !== null) {
            return (int)$val;
        }
    } catch (Throwable $e) {
        error_log('[agents.php] agentreqprice fetch failed: ' . redfox_exception_fingerprint($e));
    }
    return 0;
}

/** فاز ۴: کپی بازگشتی دایرکتوری */
function rx_copy_dir($src, $dst) {
    if (!is_dir($src) || is_link($src) || is_link($dst)) return false;
    $dir = @opendir($src);
    if (!$dir) return false;
    if (!is_dir($dst) && !@mkdir($dst, 0750, true)) { closedir($dir); return false; }
    @chmod($dst, 0750);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_link($srcPath)) { closedir($dir); return false; }
        if (is_dir($srcPath)) {
            if (!rx_copy_dir($srcPath, $dstPath)) { closedir($dir); return false; }
        } else {
            if (!is_file($srcPath) || !@copy($srcPath, $dstPath)) { closedir($dir); return false; }
            @chmod($dstPath, $file === '.htaccess' ? 0644 : 0640);
        }
    }
    closedir($dir);
    return true;
}

/** فاز ۴: حذف بازگشتی دایرکتوری */
function rx_delete_dir($dir) {
    if (is_link($dir)) return @unlink($dir);
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    if ($items === false) return false;
    foreach (array_diff($items, ['.', '..']) as $file) {
        $path = $dir . '/' . $file;
        if (is_link($path)) {
            if (!@unlink($path)) return false;
        } elseif (is_dir($path)) {
            if (!rx_delete_dir($path)) return false;
        } elseif (!@unlink($path)) {
            return false;
        }
    }
    return @rmdir($dir);
}

/** Write a sensitive bot config through a same-directory, exclusive temp file. */
function redfox_agents_atomic_config_write(string $destination, string $content): bool
{
    $directory = dirname($destination);
    if (!is_dir($directory) || is_link($directory) || is_link($destination) || !is_file($destination)) {
        return false;
    }

    try {
        $temporary = $directory . '/.config-' . bin2hex(random_bytes(16)) . '.tmp';
    } catch (Throwable $e) {
        return false;
    }
    $handle = @fopen($temporary, 'x+b');
    if (!is_resource($handle)) return false;

    $ok = @chmod($temporary, 0600);
    $length = strlen($content);
    $offset = 0;
    while ($ok && $offset < $length) {
        $written = @fwrite($handle, substr($content, $offset));
        if (!is_int($written) || $written < 1) {
            $ok = false;
            break;
        }
        $offset += $written;
    }
    if ($ok) $ok = @fflush($handle);
    if ($ok && function_exists('fsync')) $ok = @fsync($handle);
    $stat = @fstat($handle);
    if (!is_array($stat) || (((int)($stat['mode'] ?? 0) & 0170000) !== 0100000)) $ok = false;
    @fclose($handle);

    clearstatcache(true, $temporary);
    if (!$ok || is_link($temporary) || !is_file($temporary)
        || !is_dir($directory) || is_link($directory) || is_link($destination)) {
        @unlink($temporary);
        return false;
    }
    if (!@rename($temporary, $destination)) {
        @unlink($temporary);
        return false;
    }
    return !is_link($destination) && is_file($destination) && @chmod($destination, 0600);
}

/* ---------------------------------------------------------------------
 *  پردازش اکشن‌ها (POST)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    $agentId = trim((string)($_POST['agent_id'] ?? ''));
    if (!ctype_digit($agentId)) {
        $agentId = '';
    }

    try {
        if ($action === 'approve' && $agentId !== '') {
            // مطابق cfmacea_ در ربات — با امکان انتخاب نوع نماینده
            $agentType = (string)($_POST['agent_type'] ?? 'n');
            if (!in_array($agentType, ['n', 'n2'], true)) { $agentType = 'n'; }
            $isSuper = isset($_POST['is_super']);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected");
            $stmt->execute([':status' => 'accept', ':type' => $agentType, ':id' => $agentId, ':expected' => 'waiting']);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                $flash = 'این درخواست قبلاً بررسی شده یا یافت نشد.';
                $flashType = 'error';
            } else {
                $stmtUser = $pdo->prepare("UPDATE user SET agent = :agent, expire = NULL, reseller_role = :role, reseller_portal_status = 'active' WHERE id = :id");
                $stmtUser->execute([':agent' => $agentType, ':role' => $isSuper ? 'super' : 'agent', ':id' => $agentId]);
                // اگر سوپر نماینده است، دسترسی‌های پیش‌فرض بده
                if ($isSuper) {
                    $superPerms = ['products'=>1,'categories'=>1,'extend_user'=>1,'charge_user'=>1,'manage_users'=>1,'reports'=>1,'set_prices'=>1,'broadcast'=>1,'api'=>1,'support'=>1];
                    $pdo->prepare("UPDATE user SET reseller_perms = ? WHERE id = ?")->execute([json_encode($superPerms), $agentId]);
                }
                $pdo->commit();
                $typeLabel = $agentType === 'n2' ? 'نماینده پیشرفته' : 'نماینده عادی';
                $superLabel = $isSuper ? ' + سوپر نماینده' : '';
                redfox_agents_notify($agentId, "✅ کاربر گرامی با درخواست نمایندگی شما موافقت شد!\n\n🏷 نوع شما: {$typeLabel}{$superLabel}\n\n💡 برای دسترسی به پنل نمایندگی، دکمه «پنل نمایندگی» را در ربات بزنید.");
                $flash = "درخواست تأیید شد. نوع: {$typeLabel}{$superLabel}";
                $flashType = 'success';
            }
        } elseif ($action === 'reject' && $agentId !== '') {
            // مطابق cfmreja_ در ربات (بازگشت هزینه‌ی درخواست)
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected");
            $stmt->execute([':status' => 'reject', ':type' => 'None', ':id' => $agentId, ':expected' => 'waiting']);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                $flash = 'این درخواست قبلاً بررسی شده یا یافت نشد.';
                $flashType = 'error';
            } else {
                $refund = redfox_agent_request_price();
                if ($refund > 0) {
                    $stmtBalance = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
                    $stmtBalance->execute([':amount' => $refund, ':id' => $agentId]);
                }
                $pdo->commit();
                redfox_agents_notify($agentId, "❌ کاربر گرامی درخواست نمایندگی شما رد گردید.");
                $flash = 'درخواست رد شد.' . ($refund > 0 ? " (مبلغ درخواست {$refund} تومان به کیف پول کاربر بازگردانده شد)" : '');
                $flashType = 'success';
            }
        } elseif ($action === 'settype' && $agentId !== '') {
            $type = (string)($_POST['type'] ?? '');
            if (!in_array($type, ['n', 'n2'], true)) {
                $flash = 'نوع نماینده نامعتبر است.';
                $flashType = 'error';
            } else {
                $pdo->prepare("UPDATE user SET agent = ? WHERE id = ?")->execute([$type, $agentId]);
                $pdo->prepare("UPDATE Requestagent SET type = ? WHERE id = ?")->execute([$type, $agentId]);
                $label = $type === 'n2' ? 'نماینده پیشرفته' : 'نماینده عادی';
                redfox_agents_notify($agentId, "🔄 نوع حساب نمایندگی شما به «{$label}» تغییر کرد.");
                $flash = "نوع نماینده به «{$label}» تغییر کرد.";
                $flashType = 'success';
            }
        } elseif ($action === 'sethierarchy' && $agentId !== '') {
            $role = (string)($_POST['reseller_role'] ?? 'agent');
            if (!in_array($role, ['agent','super'], true)) $role = 'agent';
            $parent = trim((string)($_POST['parent_id'] ?? ''));
            if ($parent === '' || $parent === '0') $parent = null;
            if ($parent !== null) {
                if (!ctype_digit($parent) || $parent === $agentId) throw new RuntimeException('والد نمایندگی نامعتبر است.');
                $chk = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id=? AND agent IN ('n','n2') AND reseller_role='super' AND (reseller_parent_id IS NULL OR reseller_parent_id='')");
                $chk->execute([$parent]);
                if ((int)$chk->fetchColumn() !== 1) throw new RuntimeException('سوپرنماینده والد معتبر نیست.');
            }
            $portalStatus = (string)($_POST['portal_status'] ?? 'active');
            if (!in_array($portalStatus, ['active','disabled'], true)) $portalStatus = 'active';
            $pdo->prepare("UPDATE user SET reseller_role=?, reseller_parent_id=?, reseller_portal_status=? WHERE id=? AND agent IN ('n','n2')")
                ->execute([$role,$parent,$portalStatus,$agentId]);
            $flash = 'نقش و سلسله‌مراتب نماینده ذخیره شد.'; $flashType = 'success';
        } elseif ($action === 'addcredit' && $agentId !== '') {
            $amount = (int)($_POST['amount'] ?? 0);
            if ($amount === 0) {
                $flash = 'مبلغ نامعتبر است (باید عدد صحیح غیر صفر باشد).';
                $flashType = 'error';
            } else {
                // افزایش مثبت موجودی (اعتبار نمایندگی)
                $stmtBal = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
                $stmtBal->execute([':amount' => $amount, ':id' => $agentId]);
                $disp = number_format($amount);
                $sign = $amount > 0 ? 'افزایش' : 'کاهش';
                redfox_agents_notify($agentId, "💰 موجودی کیف پول شما به مبلغ " . number_format(abs($amount)) . " تومان {$sign} یافت.");
                $flash = "موجودی کیف پول نماینده به مبلغ {$disp} تومان تغییر کرد.";
                $flashType = 'success';
            }
        } elseif ($action === 'setmaxbuy' && $agentId !== '') {
            $maxbuy = (string)($_POST['maxbuy'] ?? '0');
            if (!ctype_digit(ltrim($maxbuy, '-')) && $maxbuy !== '0') {
                $maxbuy = '0';
            }
            $pdo->prepare("UPDATE user SET maxbuyagent = ? WHERE id = ?")->execute([$maxbuy, $agentId]);
            $flash = "سقف اعتبار/بدهی نماینده روی «{$maxbuy}» تنظیم شد.";
            $flashType = 'success';
        } elseif ($action === 'revoke' && $agentId !== '') {
            // لغو نمایندگی (مطابق finance.php در ربات)
            $pdo->prepare("UPDATE user SET agent = 'f', reseller_portal_status = 'disabled', reseller_parent_id = NULL WHERE id = ?")->execute([$agentId]);
            $pdo->prepare("UPDATE Requestagent SET status = 'reject' WHERE id = ?")->execute([$agentId]);
            redfox_agents_notify($agentId, "⚠️ نمایندگی شما لغو گردید. در صورت نیاز با پشتیبانی در ارتباط باشید.");
            $flash = 'نمایندگی این کاربر لغو شد.';
            $flashType = 'success';
        } elseif ($action === 'setportal' && $agentId !== '') {
            // Red Fox: تعیین رمز عبور پورتال نماینده
            $pw = trim((string)($_POST['portal_pw'] ?? ''));
            if (mb_strlen($pw) < 10) {
                $flash = 'رمز عبور باید حداقل ۱۰ کاراکتر باشد.'; $flashType = 'error';
            } else {
                $portalOrigin = redfox_configured_base_url();
                if ($portalOrigin === '') throw new RuntimeException('REDFOX_DOMAIN تنظیم نشده یا نامعتبر است.');
                $hashed = password_hash($pw, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE user SET panel_password = ? WHERE id = ?")->execute([$hashed, $agentId]);
                redfox_agents_notify($agentId, "🔐 رمز عبور پورتال نمایندگی شما تغییر کرد.\n\n🌐 آدرس پورتال: " . $portalOrigin . "/portal/login.php\n\nبرای امنیت، رمز عبور در تلگرام ارسال نمی‌شود؛ آن را فقط از کانال امن دریافت کنید.");
                $flash = "رمز پورتال تنظیم شد؛ مقدار رمز عمداً در تلگرام ارسال نشد و باید امن تحویل شود."; $flashType = 'success';
            }
        } elseif ($action === 'repair_all_bots') {
            $repair=(new RedFoxResellerBotManager($pdo,dirname(__DIR__)))->repairAll();$flash='✅ '.(int)$repair['ok'].' ربات تعمیر و Webhook آن‌ها بازبینی شد.'.((int)$repair['failed']>0?' — خطا: '.(int)$repair['failed']:'');$flashType=(int)$repair['failed']>0?'error':'success';
        } elseif ($action === 'create_bot' && $agentId !== '') {
            $botToken = trim((string)($_POST['bot_token'] ?? ''));
            $requestedUsername = ltrim(trim((string)($_POST['bot_username'] ?? '')), '@');
            if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $botToken)
                || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $requestedUsername)) {
                throw new RuntimeException('توکن یا یوزرنیم ربات نامعتبر است.');
            }

            $getMe = telegram('getMe', [], $botToken);
            $botUsername = is_array($getMe) ? (string)($getMe['result']['username'] ?? '') : '';
            if (empty($getMe['ok']) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $botUsername)
                || strcasecmp($botUsername, $requestedUsername) !== 0) {
                throw new RuntimeException('توکن معتبر نیست یا یوزرنیم با ربات توکن مطابقت ندارد.');
            }

            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM botsaz WHERE id_user=? OR bot_token=?');
            $duplicate->execute([$agentId, $botToken]);
            if ((int)$duplicate->fetchColumn() > 0) {
                throw new RuntimeException('برای این نماینده یا توکن قبلاً ربات ثبت شده است.');
            }

            $projectRoot = dirname(__DIR__);
            $templateDir = $projectRoot . '/vpnbot/Default';
            $botDir = $projectRoot . '/vpnbot/' . $agentId . $botUsername;
            $webhookConfigured = false;
            try {
                if (is_link($templateDir) || !is_dir($templateDir)) {
                    throw new RuntimeException('قالب امن ربات در دسترس نیست.');
                }
                if ((is_dir($botDir) || is_link($botDir)) && !rx_delete_dir($botDir)) {
                    throw new RuntimeException('پاک‌سازی پوشه قبلی ربات ناموفق بود.');
                }
                if (!rx_copy_dir($templateDir, $botDir)) {
                    throw new RuntimeException('کپی قالب ربات ناموفق بود.');
                }

                $webhookSecret = bin2hex(random_bytes(32));
                $cfgFile = $botDir . '/config.php';
                $cfg = is_file($cfgFile) && !is_link($cfgFile) ? file_get_contents($cfgFile) : false;
                if (!is_string($cfg)
                    || substr_count($cfg, 'BotTokenNew') !== 1
                    || substr_count($cfg, 'WebhookSecretNew') !== 1) {
                    throw new RuntimeException('قالب تنظیمات ربات نامعتبر است.');
                }
                $cfg = str_replace(['BotTokenNew', 'WebhookSecretNew'], [$botToken, $webhookSecret], $cfg);
                if (!redfox_agents_atomic_config_write($cfgFile, $cfg)) {
                    throw new RuntimeException('ذخیره امن و اتمیک تنظیمات ربات ناموفق بود.');
                }

                $origin = redfox_configured_base_url();
                if ($origin === '') throw new RuntimeException('REDFOX_DOMAIN معتبر تنظیم نشده است.');
                $hookOutcome = redfox_apply_telegram_webhook(
                    static function (string $method, array $parameters) use ($botToken): array {
                        $result = telegram($method, $parameters, $botToken);
                        return is_array($result) ? $result : ['ok' => false];
                    },
                    $origin . '/vpnbot/' . $agentId . $botUsername . '/index.php',
                    $webhookSecret,
                    true
                );
                if (empty($hookOutcome['ok'])) {
                    $hookCode = preg_match('/^[A-Z0-9_-]{1,64}$/', (string)($hookOutcome['code'] ?? ''))
                        ? (string)$hookOutcome['code'] : 'TG-WH-UNKNOWN';
                    throw new RuntimeException('تلگرام تنظیم Webhook را نپذیرفت؛ کد ' . $hookCode . '.');
                }
                $webhookConfigured = true;

                $settingJson = json_encode(['minpricetime'=>4000,'pricetime'=>4000,'minpricevolume'=>4000,'pricevolume'=>4000,'support_username'=>'@support','Channel_Report'=>0,'cart_info'=>'جهت پرداخت مبلغ را به شماره کارت زیر واریز نمایید','show_product'=>true]);
                $pdo->prepare("INSERT INTO botsaz (id_user,bot_token,admin_ids,username,time,setting,hide_panel,webhook_secret_token) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$agentId, $botToken, json_encode([$agentId]), $botUsername, date('Y/m/d H:i:s'), $settingJson, '{}', $webhookSecret]);
            } catch (Throwable $createError) {
                if ($webhookConfigured) telegram('deleteWebhook', [], $botToken);
                if (is_dir($botDir) || is_link($botDir)) rx_delete_dir($botDir);
                throw $createError;
            }
            redfox_agents_notify($agentId, "🤖 ربات اختصاصی شما با موفقیت نصب شد!\n🌐 پورتال: " . redfox_configured_base_url() . "/portal/login.php");
            $flash = "ربات اختصاصی ساخته شد و به نماینده اطلاع داده شد.";
            $flashType = 'success';
        } elseif ($action === 'delete_bot' && $agentId !== '') {
            // فاز ۴: حذف ربات اختصاصی
            try {
                $bot = $pdo->prepare("SELECT * FROM botsaz WHERE id_user=? LIMIT 1"); $bot->execute([$agentId]); $botInfo = $bot->fetch(PDO::FETCH_ASSOC);
                if ($botInfo) {
                    $projectRoot = dirname(__DIR__);
                    $storedUsername = (string)($botInfo['username'] ?? '');
                    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $storedUsername)) {
                        throw new RuntimeException('یوزرنیم ذخیره‌شده ربات ناامن است؛ حذف پوشه متوقف شد.');
                    }
                    $botDir = $projectRoot . '/vpnbot/' . $agentId . $storedUsername;
                    if ((is_dir($botDir) || is_link($botDir)) && !rx_delete_dir($botDir)) {
                        throw new RuntimeException('حذف پوشه ربات ناموفق بود.');
                    }
                    $storedToken = (string)($botInfo['bot_token'] ?? '');
                    if (preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $storedToken)) {
                        telegram('deleteWebhook', [], $storedToken);
                    }
                    $pdo->prepare("DELETE FROM botsaz WHERE id_user=?")->execute([$agentId]);
                    $flash = 'ربات اختصاصی حذف شد.'; $flashType = 'success';
                } else { $flash = 'رباتی برای این نماینده یافت نشد.'; $flashType = 'error'; }
            } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/agents.php'), ENT_QUOTES); $flashType = 'error'; }
        }
    } catch (Throwable $e) {
        if (isset($pdo) && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $flash = 'خطا در انجام عملیات: ' . htmlspecialchars(redfox_public_exception($e, 'panel/agents.php'), ENT_QUOTES);
        $flashType = 'error';
        error_log('[agents.php] action error: ' . redfox_exception_fingerprint($e));
    }
}

/* ---------------------------------------------------------------------
 *  بارگذاری داده‌ها برای نمایش
 *
 *  نکته‌ی مهم: جدول user ستون first_name ندارد. کوئری قبلی به‌خاطر ستون
 *  first_name ناموجود خطا می‌زد و توسط try/catch خورده می‌شد و لیست خالی
 *  برمی‌گشت (دلیل نمایش‌ندادن نماینده‌ی تأییدشده). ستون‌های معتبر: username،
 *  User_Status، agent، Balance، maxbuyagent، expire.
 * ------------------------------------------------------------------- */
$agentTypeLabels = ['n' => 'نماینده عادی', 'n2' => 'نماینده پیشرفته', 'f' => 'عادی'];

// درخواست‌های در انتظار
$pendingRequests = [];
try {
    $pr = $pdo->prepare("SELECT * FROM Requestagent WHERE status = 'waiting' ORDER BY id ASC");
    $pr->execute();
    $pendingRequests = $pr->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[agents.php] pending fetch failed: ' . redfox_exception_fingerprint($e));
}

// نماینده‌های فعلی (کاربرانی که agent در n یا n2 است) + وضعیت ربات اختصاصی
$agents = [];
try {
    // بررسی وجود جدول botsaz (ممکن است در بکاپ قدیمی نباشد)
    $botsazExists = false;
    try { $botsazExists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'botsaz'")->fetchColumn() > 0; } catch(Throwable $e){}

    if ($botsazExists) {
        $ag = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM botsaz b WHERE b.id_user = u.id) AS has_bot FROM user u WHERE u.agent IN ('n','n2') ORDER BY u.id DESC LIMIT 500");
    } else {
        $ag = $pdo->prepare("SELECT u.*, 0 AS has_bot FROM user u WHERE u.agent IN ('n','n2') ORDER BY u.id DESC LIMIT 500");
    }
    $ag->execute();
    $agents = $ag->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[agents.php] agents fetch failed: ' . redfox_exception_fingerprint($e));
}

/**
 * نمایش تمیز یک فیلد (تبدیل none/خالی به خط تیره).
 */
function redfox_agents_disp($v): string
{
    $v = trim((string)$v);
    if ($v === '' || strtolower($v) === 'none') {
        return '—';
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت نمایندگان | ربات رد فاکس</title>
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .rx-flash { padding: 12px 16px; border-radius: 10px; margin: 0 0 16px; font-size: 13px; line-height: 1.9; }
        .rx-flash.success { background: var(--color-success-soft); color: var(--color-success); }
        .rx-flash.error   { background: var(--color-danger-soft);  color: var(--color-danger); }
        .rx-flash.info    { background: var(--accent-soft);        color: var(--text-main); }
        .rx-mini-form { display: inline-flex; gap: 4px; align-items: center; flex-wrap: wrap; }
        .rx-mini-form input, .rx-mini-form select {
            padding: 7px 9px; border-radius: 8px; border: 1px solid var(--border-mid);
            background: var(--surface-1); color: var(--text-main); font-size: 12px; min-width: 70px; max-width: 130px;
        }
        .rx-row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .rx-card-title { color: var(--text-main); }
        .rx-help { color: var(--text-muted); font-size: 12px; line-height: 1.9; margin-top: 14px; }
        code { color: var(--text-main); }
        .rx-agent-card {
            background: var(--surface-1); border: 1px solid var(--border-soft);
            border-radius: 12px; padding: 14px; margin-bottom: 10px;
        }
        .rx-agent-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
        .rx-agent-head .name { font-size: 14px; font-weight: 700; color: var(--text-main); }
        .rx-agent-head .badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .rx-agent-info { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 6px; font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .rx-agent-info div b { color: var(--text-main); }
        .rx-agent-actions { display: flex; gap: 4px; flex-wrap: wrap; padding-top: 8px; border-top: 1px solid var(--border-soft); }
        .rx-agent-actions input, .rx-agent-actions select { padding: 6px 8px; border-radius: 7px; border: 1px solid var(--border-mid); background: var(--surface-2, var(--surface-1)); color: var(--text-main); font-size: 11px; }
        .rx-agent-actions button { padding: 6px 10px; border-radius: 7px; border: none; font-size: 11px; font-weight: 600; cursor: pointer; }
        .rx-btn-primary { background: var(--accent); color: var(--accent-fg, #fff); }
        .rx-btn-success { background: var(--color-success); color: #fff; }
        .rx-btn-danger { background: var(--color-danger); color: #fff; }
        .rx-btn-warn { background: var(--color-warning); color: #fff; }
        @media (max-width: 768px) {
            .rx-agent-info { grid-template-columns: 1fr 1fr; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <div class="wrapper">
            <div class="page-head">
                <h1 class="page-head__title">🤝 مدیریت نمایندگان</h1>
                <div class="page-head__sub">تأیید/رد درخواست‌های نمایندگی، تعیین نوع، شارژ اعتبار و سقف بدهی نماینده‌ها</div>
            </div>

            <?php if ($flash !== ''): ?>
                <div class="rx-flash <?= htmlspecialchars($flashType, ENT_QUOTES) ?>"><?= $flash ?></div>
            <?php endif; ?>

            <?php if (!$isMainAdmin): ?>
                <div class="rx-flash error">⛔ این بخش فقط برای ادمین اصلی قابل‌مشاهده است.</div>
            <?php endif; ?>

            <!-- درخواست‌های نمایندگی در انتظار -->
            <div class="card">
                <div class="card__head">
                    <h2 class="card__title rx-card-title">📥 درخواست‌های نمایندگی در انتظار (<?= count($pendingRequests) ?>)</h2>
                </div>
                <?php if (empty($pendingRequests)): ?>
                    <p class="text-muted">درخواست نمایندگی در انتظاری وجود ندارد.</p>
                <?php else: ?>
                    <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead><tr><th>آیدی</th><th>نام کاربری</th><th>توضیحات</th><th>زمان</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingRequests as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string)$r['id'], ENT_QUOTES) ?></code></td>
                                <td><?= htmlspecialchars((string)$r['username'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth((string)$r['Description'], 0, 60, '…'), ENT_QUOTES) ?></td>
                                <td class="text-muted"><?= htmlspecialchars((string)$r['time'], ENT_QUOTES) ?></td>
                                <td>
                                    <?php if ($isMainAdmin): ?>
                                    <div class="rx-row-actions">
                                        <form method="post" class="rx-mini-form">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$r['id'], ENT_QUOTES) ?>">
                                            <select name="agent_type">
                                                <option value="n">عادی</option>
                                                <option value="n2">پیشرفته</option>
                                            </select>
                                            <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer"><input type="checkbox" name="is_super" value="1"> ⭐ سوپر</label>
                                            <button class="btn btn-sm btn-success" type="submit">✅ تأیید</button>
                                        </form>
                                        <form method="post" class="rx-mini-form">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$r['id'], ENT_QUOTES) ?>">
                                            <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('رد شود؟ هزینه درخواست به کاربر بازگردانده می‌شود.')">❌ رد</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- لیست نماینده‌های فعلی -->
            <div class="card">
                <div class="card__head">
                    <h2 class="card__title rx-card-title">👥 نماینده‌های فعال (<?= count($agents) ?>)</h2>
                    <?php if ($isMainAdmin): ?>
                    <form method="post" style="display:inline-block;margin-bottom:10px" onsubmit="return confirm('فایل، توکن، مسیر و Webhook همه ربات‌های نماینده تعمیر شود؟')"><input type="hidden" name="action" value="repair_all_bots"><input type="hidden" name="agent_id" value="0"><button class="btn btn-sm btn-success">🛠 تعمیر کامل ربات‌ها و Webhook</button></form>
                    <?php endif; ?>
                </div>
                <?php if (empty($agents)): ?>
                    <p class="text-muted">نماینده‌ای ثبت نشده است.</p>
                <?php else: ?>
                    <?php foreach ($agents as $u):
                        $aType = (string)$u['agent'];
                        $badgeStyle = $aType === 'n2' ? 'background:var(--accent-soft);color:var(--accent);' : 'background:var(--color-success-soft);color:var(--color-success);';
                        // بررسی سوپر نماینده
                        $isSuperAgent = (($u['reseller_role'] ?? '') === 'super');
                        if (!$isSuperAgent && !empty($u['reseller_perms'])) {
                            $sp = json_decode($u['reseller_perms'], true) ?: [];
                            foreach ($sp as $v) { if (!empty($v)) { $isSuperAgent = true; break; } }
                        }
                        $typeText = $agentTypeLabels[$aType] ?? $aType;
                        if ($isSuperAgent) $typeText .= ' ⭐ سوپر';
                    ?>
                    <div class="rx-agent-card">
                        <div class="rx-agent-head">
                            <div class="name">
                                <code><?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?></code>
                                <?= redfox_agents_disp($u['tg_name'] ?? '') ?>
                                <?= $u['username'] && strtolower(trim((string)$u['username'])) !== 'none' ? '@' . htmlspecialchars($u['username']) : '' ?>
                            </div>
                            <span class="badge" style="<?= $badgeStyle ?>"><?= htmlspecialchars($typeText, ENT_QUOTES) ?></span>
                        </div>
                        <div class="rx-agent-info">
                            <div>💰 <b><?= number_format((int)($u['Balance'] ?? 0)) ?></b> ت</div>
                            <div>📊 سقف: <b><?= number_format((int)($u['maxbuyagent'] ?? 0)) ?></b></div>
                            <div>📱 <?= redfox_agents_disp($u['number'] ?? '') ?></div>
                            <div>🤖 ربات: <?= (int)($u['has_bot'] ?? 0) > 0 ? '✅ دارد' : '❌' ?></div>
                        </div>
                        <?php if ($isMainAdmin): ?>
                        <div class="rx-agent-actions">
                            <form method="post" style="display:flex;gap:3px;align-items:center">
                                <input type="hidden" name="action" value="settype">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <select name="type"><option value="n" <?= $aType==='n'?'selected':'' ?>>عادی</option><option value="n2" <?= $aType==='n2'?'selected':'' ?>>پیشرفته</option></select>
                                <button class="rx-btn-primary" type="submit">نوع</button>
                            </form>
                            <form method="post" style="display:flex;gap:3px;align-items:center;flex-wrap:wrap">
                                <input type="hidden" name="action" value="sethierarchy">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <select name="reseller_role"><option value="agent" <?= (($u['reseller_role']??'agent')==='agent')?'selected':'' ?>>نماینده</option><option value="super" <?= (($u['reseller_role']??'')==='super')?'selected':'' ?>>سوپر</option></select>
                                <input type="number" name="parent_id" value="<?= htmlspecialchars((string)($u['reseller_parent_id']??''), ENT_QUOTES) ?>" placeholder="آیدی والد" style="width:90px">
                                <select name="portal_status"><option value="active" <?= (($u['reseller_portal_status']??'active')==='active')?'selected':'' ?>>پورتال فعال</option><option value="disabled" <?= (($u['reseller_portal_status']??'')==='disabled')?'selected':'' ?>>غیرفعال</option></select>
                                <button class="rx-btn-primary" type="submit">سلسله‌مراتب</button>
                            </form>
                            <form method="post" style="display:flex;gap:3px;align-items:center">
                                <input type="hidden" name="action" value="addcredit">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <input type="number" name="amount" placeholder="مبلغ" style="width:80px">
                                <button class="rx-btn-success" type="submit">شارژ</button>
                            </form>
                            <form method="post" style="display:flex;gap:3px;align-items:center">
                                <input type="hidden" name="action" value="setmaxbuy">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <input type="number" name="maxbuy" value="<?= htmlspecialchars((string)($u['maxbuyagent'] ?? '0'), ENT_QUOTES) ?>" style="width:70px">
                                <button class="rx-btn-warn" type="submit">سقف</button>
                            </form>
                            <a class="btn btn-sm btn-primary" href="reseller_report.php?id=<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>" style="font-size:11px;padding:6px 8px">📊 گزارش</a>
                            <a class="btn btn-sm btn-soft-purple" href="reseller_permissions.php?id=<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>" style="font-size:11px;padding:6px 8px">⭐ دسترسی</a>
                            <form method="post" style="display:flex;gap:3px;align-items:center">
                                <input type="hidden" name="action" value="setportal">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <input type="password" name="portal_pw" placeholder="رمز پورتال" style="width:100px">
                                <button class="rx-btn-primary" type="submit">🔐 پورتال</button>
                            </form>
                            <?php if ((int)($u['has_bot'] ?? 0) > 0): ?>
                                <form method="post" onsubmit="return confirm('حذف ربات اختصاصی؟')">
                                    <input type="hidden" name="action" value="delete_bot">
                                    <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                    <button class="rx-btn-danger" type="submit">🗑 حذف ربات</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display:flex;gap:3px;align-items:center;flex-wrap:wrap">
                                    <input type="hidden" name="action" value="create_bot">
                                    <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                    <input type="text" name="bot_token" placeholder="توکن" style="width:110px;direction:ltr" required>
                                    <input type="text" name="bot_username" placeholder="یوزرنیم" style="width:90px;direction:ltr" required>
                                    <button class="rx-btn-success" type="submit">🤖 ساخت ربات</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('لغو نمایندگی؟')">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="agent_id" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES) ?>">
                                <button class="rx-btn-danger" type="submit">🚫 لغو</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="rx-help">
                    💡 «شارژ» مبلغ را به کیف پول/اعتبار نماینده اضافه می‌کند (عدد مثبت).<br>
                    💡 «سقف» همان maxbuyagent است: حداکثر مبلغی که نماینده می‌تواند به‌صورت بدهی منفی شود. صفر = بدون بدهی.
                </p>
            </div>
        </div>
    </section>
</section>
</body>
</html>
