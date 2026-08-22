<?php
/**
 * Red Fox — مرکز کمپین‌ها و قابلیت‌های درآمدی.
 *  ۱) کمپین فروش ویژه (Flash Sale)
 *  ۲) پاداش روزانه (Daily Reward)
 *  ۳) آپ‌سل هوشمند (Upsell)
 *  ۴) بازگشت مشتری (Win-back)
 *  ۵) پست خودکار کانال
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');
$flash = ''; $flashType = 'info';

rx_require_schema($pdo,['flash_campaigns'],['setting'=>['daily_reward_status','daily_reward_amount','daily_reward_streak_bonus','daily_reward_max_streak','upsell_status','winback_status','winback_days','winback_discount','channel_autopost_status']]);

// ── POST handling ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $act = (string)($_POST['action'] ?? '');
    try {
        // Flash Sale
        if ($act === 'create_flash') {
            $title = trim((string)($_POST['title'] ?? ''));
            $pct = max(1, min(90, (int)($_POST['discount_percent'] ?? 0)));
            $starts = trim((string)($_POST['starts_at'] ?? ''));
            $ends = trim((string)($_POST['ends_at'] ?? ''));
            if ($title !== '' && $starts !== '' && $ends !== '') {
                $pdo->prepare("INSERT INTO flash_campaigns (title, discount_percent, starts_at, ends_at, is_active, created_at) VALUES (?, ?, ?, ?, 1, ?)")
                    ->execute([$title, $pct, $starts . ':00', $ends . ':59', date('Y-m-d H:i:s')]);
                $flash = "کمپین «$title» با $pct٪ تخفیف ساخته شد! ✅"; $flashType = 'success';
            }
        } elseif ($act === 'toggle_flash') {
            $id = (int)($_POST['flash_id'] ?? 0);
            $pdo->prepare("UPDATE flash_campaigns SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            $flash = 'وضعیت کمپین تغییر کرد.'; $flashType = 'success';
        } elseif ($act === 'delete_flash') {
            $id = (int)($_POST['flash_id'] ?? 0);
            $pdo->prepare("DELETE FROM flash_campaigns WHERE id = ?")->execute([$id]);
            $flash = 'کمپین حذف شد.'; $flashType = 'success';
        } elseif ($act === 'notify_flash') {
            $id = (int)($_POST['flash_id'] ?? 0);
            $camp = $pdo->prepare("SELECT * FROM flash_campaigns WHERE id = ? LIMIT 1");
            $camp->execute([$id]); $campData = $camp->fetch(PDO::FETCH_ASSOC);
            if ($campData) {
                // ارسال پیام به همه کاربران
                $users = $pdo->query("SELECT id FROM user WHERE User_Status != 'block' LIMIT 5000")->fetchAll(PDO::FETCH_COLUMN);
                $sentCount = 0;
                foreach ($users as $uid) {
                    $msg = "⚡️ <b>{$campData['title']}</b>\n\n🔥 تخفیف ویژه: <b>{$campData['discount_percent']}٪</b>\n⏰ از {$campData['starts_at']} تا {$campData['ends_at']}\n\n👇 همین حالا خرید کنید!";
                    $kb = json_encode(['inline_keyboard' => [[['text' => '🛍 خرید با تخفیف', 'callback_data' => 'buy']]]]);
                    @sendmessage((string)$uid, $msg, $kb, 'HTML');
                    $sentCount++;
                }
                $pdo->prepare("UPDATE flash_campaigns SET notify_sent = 1 WHERE id = ?")->execute([$id]);
                $flash = "اعلام کمپین به $sentCount کاربر ارسال شد! ✅"; $flashType = 'success';
            }
        }
        // Settings toggles
        elseif ($act === 'save_settings') {
            $settingsMap = [
                'daily_reward_status' => isset($_POST['daily_reward_status']),
                'daily_reward_amount' => (int)($_POST['daily_reward_amount'] ?? 1000),
                'daily_reward_streak_bonus' => (int)($_POST['daily_reward_streak_bonus'] ?? 500),
                'daily_reward_max_streak' => (int)($_POST['daily_reward_max_streak'] ?? 7),
                'upsell_status' => isset($_POST['upsell_status']),
                'winback_status' => isset($_POST['winback_status']),
                'winback_days' => (int)($_POST['winback_days'] ?? 30),
                'winback_discount' => (int)($_POST['winback_discount'] ?? 20),
                'channel_autopost_status' => isset($_POST['channel_autopost_status']),
            ];
            foreach ($settingsMap as $k => $v) {
                $pdo->prepare("UPDATE setting SET `$k` = ?")->execute([(string)$v]);
            }
            $flash = 'تنظیمات ذخیره شد. ✅'; $flashType = 'success';
        }
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/campaigns.php')); $flashType = 'error'; }
}

// ── خواندن داده‌ها ──
$campaigns = []; $settings = [];
try { $campaigns = $pdo->query("SELECT * FROM flash_campaigns ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
try { $st = $pdo->query("SELECT * FROM setting LIMIT 1"); $settings = $st->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
$now = date('Y-m-d H:i:s');
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مرکز کمپین‌ها | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-card-title{color:var(--text-main)}
.rx-field{margin-bottom:10px}.rx-field label{display:block;color:var(--text-muted);font-size:12px;margin-bottom:3px}
.rx-field input,.rx-field select{width:100%;max-width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box;font-family:inherit}
.rx-field input[type=datetime-local]{direction:ltr}
.rx-toggle{display:flex;align-items:center;gap:8px;margin:8px 0}.rx-toggle input{width:20px;height:20px;cursor:pointer}
.rx-toggle label{font-size:13px;color:var(--text-main);cursor:pointer}
.rx-camp{background:var(--surface-2);border-radius:10px;padding:14px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.rx-camp .info{flex:1}.rx-camp .info b{color:var(--text-main);font-size:14px}.rx-camp .info small{color:var(--text-muted);font-size:11px;display:block;margin-top:3px}
.badge{padding:3px 9px;border-radius:12px;font-size:10px;font-weight:700}
.badge-active{background:var(--color-success-soft);color:var(--color-success)}
.badge-off{background:var(--surface-3);color:var(--text-muted)}
.badge-exp{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.rx-info-box{background:var(--accent-soft);border-radius:10px;padding:14px;font-size:12px;color:var(--text-main);line-height:2;margin-bottom:14px}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">⚡ مرکز کمپین‌ها</h1>
<div class="page-head__sub">ابزارهای افزایش درآمد و حفظ مشتری</div></div>

<?php if ($flash): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= htmlspecialchars($flash,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

<!-- ── ۱) کمپین فروش ویژه ── -->
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚡ ۱) کمپین فروش ویژه (Flash Sale)</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" style="margin-bottom:16px">
<input type="hidden" name="action" value="create_flash">
<div class="rx-row">
<div class="rx-field"><label>عنوان کمپین</label><input type="text" name="title" placeholder="مثلاً: تخفیف پاییزه" required></div>
<div class="rx-field"><label>درصد تخفیف (۱-۹۰)</label><input type="number" name="discount_percent" value="20" min="1" max="90" required></div>
</div>
<div class="rx-row">
<div class="rx-field"><label>شروع (تاریخ و ساعت)</label><input type="datetime-local" name="starts_at" required></div>
<div class="rx-field"><label>پایان (تاریخ و ساعت)</label><input type="datetime-local" name="ends_at" required></div>
</div>
<button class="btn btn-sm btn-primary" type="submit">➕ ساخت کمپین</button>
</form>
<?php endif; ?>

<?php foreach ($campaigns as $c):
$isExp = $c['ends_at'] < $now;
$isRunning = $c['is_active'] && !$isExp && $c['starts_at'] <= $now;
$badge = $isRunning ? '<span class="badge badge-active">در حال اجرا</span>' : ($isExp ? '<span class="badge badge-exp">تمام شده</span>' : '<span class="badge badge-off">غیرفعال</span>');
?>
<div class="rx-camp">
<div class="info">
<b><?= htmlspecialchars((string)$c['title'],ENT_QUOTES,'UTF-8') ?></b> <?= $badge ?>
<small>🔥 <?= (int)$c['discount_percent'] ?>٪ تخفیف &nbsp;|&nbsp; ⏰ <?= htmlspecialchars($c['starts_at']) ?> تا <?= htmlspecialchars($c['ends_at']) ?></small>
</div>
<div style="display:flex;gap:4px;flex-wrap:wrap">
<?php if ($isMainAdmin): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="notify_flash"><input type="hidden" name="flash_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-success" type="submit" title="ارسال به همه کاربران">📢 اطلاع</button></form>
<form method="post" style="display:inline"><input type="hidden" name="action" value="toggle_flash"><input type="hidden" name="flash_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm" type="submit"><?= $c['is_active'] ? '⏸' : '▶️' ?></button></form>
<form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="action" value="delete_flash"><input type="hidden" name="flash_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">🗑</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php if (empty($campaigns)): ?><p class="text-muted" style="font-size:13px">هنوز کمپینی ساخته نشده.</p><?php endif; ?>
<div class="rx-info-box">💡 با زدن دکمه «📢 اطلاع»، پیام کمپین به همه‌ی کاربران فعال ارسال می‌شود و دکمه‌ی خرید نمایش داده می‌شود.</div>
</div>

<!-- ── تنظیمات کلی ── -->
<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات قابلیت‌ها</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save_settings">

<!-- پاداش روزانه -->
<div style="border-top:1px solid var(--border-soft);padding-top:14px;margin-top:14px">
<h3 style="color:var(--text-main);font-size:14px;margin-bottom:8px">🎁 ۲) پاداش روزانه (Daily Reward)</h3>
<div class="rx-toggle"><input type="checkbox" name="daily_reward_status" id="dr_status" <?= ($settings['daily_reward_status']??'')==='on'?'checked':'' ?>><label for="dr_status">فعال‌سازی پاداش روزانه — کاربران با /daily پاداش بگیرند</label></div>
<div class="rx-row">
<div class="rx-field"><label>مبلغ پاداش روزانه (تومان)</label><input type="number" name="daily_reward_amount" value="<?= (int)($settings['daily_reward_amount']??1000) ?>"></div>
<div class="rx-field"><label>پاداش استریک (هر روز پشت‌سرهم)</label><input type="number" name="daily_reward_streak_bonus" value="<?= (int)($settings['daily_reward_streak_bonus']??500) ?>"></div>
</div>
<div class="rx-field"><label>حداکثر روز استریک (بعد از این همه‌ی پاداش‌ها ریست می‌شود)</label><input type="number" name="daily_reward_max_streak" value="<?= (int)($settings['daily_reward_max_streak']??7) ?>"></div>
</div>

<!-- آپ‌سل -->
<div style="border-top:1px solid var(--border-soft);padding-top:14px;margin-top:14px">
<h3 style="color:var(--text-main);font-size:14px;margin-bottom:8px">📈 ۳) آپ‌سل هوشمند (Upsell)</h3>
<div class="rx-toggle"><input type="checkbox" name="upsell_status" id="up_status" <?= ($settings['upsell_status']??'')==='on'?'checked':'' ?>><label for="up_status">فعال‌سازی پیشنهاد پلن برتر هنگام خرید</label></div>
<div class="rx-info-box">💡 وقتی کاربر در حال خرید یک پلن است، ربات به‌صورت خودکار پلن‌های بهتر را با محاسبه‌ی «ارزش هر گیگ» پیشنهاد می‌دهد.</div>
</div>

<!-- بازگشت مشتری -->
<div style="border-top:1px solid var(--border-soft);padding-top:14px;margin-top:14px">
<h3 style="color:var(--text-main);font-size:14px;margin-bottom:8px">🔄 ۴) بازگشت مشتری (Win-back)</h3>
<div class="rx-toggle"><input type="checkbox" name="winback_status" id="wb_status" <?= ($settings['winback_status']??'')==='on'?'checked':'' ?>><label for="wb_status">فعال‌سازی — پیام خودکار به کاربران غیرفعال با تخفیف ویژه</label></div>
<div class="rx-row">
<div class="rx-field"><label>تعداد روز عدم خرید برای فعال‌سازی</label><input type="number" name="winback_days" value="<?= (int)($settings['winback_days']??30) ?>"></div>
<div class="rx-field"><label>درصد تخفیف بازگشت</label><input type="number" name="winback_discount" value="<?= (int)($settings['winback_discount']??20) ?>"></div>
</div>
</div>

<!-- پست خودکار کانال -->
<div style="border-top:1px solid var(--border-soft);padding-top:14px;margin-top:14px">
<h3 style="color:var(--text-main);font-size:14px;margin-bottom:8px">📢 ۵) پست خودکار کانال</h3>
<div class="rx-toggle"><input type="checkbox" name="channel_autopost_status" id="cap_status" <?= ($settings['channel_autopost_status']??'')==='on'?'checked':'' ?>><label for="cap_status">فعال‌سازی اعلان خودکار در کانال هنگام افزودن محصول جدید</label></div>
</div>

<div style="margin-top:14px"><button class="btn btn-sm btn-primary" type="submit">💾 ذخیره همه‌ی تنظیمات</button></div>
</form>
<?php else: ?>
<p class="text-muted">فقط ادمین اصلی.</p>
<?php endif; ?>
</div>

</div></section></section></body></html>
