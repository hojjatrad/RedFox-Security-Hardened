<?php
/**
 * Red Fox — پایگاه دانش هوش مصنوعی (فاز ۳).
 *  - افزودن دستی جفت سؤال/پاسخ
 *  - آپلود فایل متنی (.txt/.md) → تبدیل به دانش
 *  - فعال/غیرفعال/حذف
 *  - نمایش موارد یادگرفته‌شده از پاسخ‌های ادمین (source=admin)
 * هوش مصنوعی هنگام پاسخ‌دهی، مرتبط‌ترین موارد را به‌عنوان زمینه دریافت می‌کند.
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$adminRow = null;
if (!empty($_SESSION['user'])) {
    $q = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
    $q->bindValue(':u', $_SESSION['user'], PDO::PARAM_STR); $q->execute();
    $adminRow = $q->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');
$flash = ''; $flashType = 'info';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $q = trim((string)($_POST['question'] ?? ''));
            $a = trim((string)($_POST['answer'] ?? ''));
            if ($q === '' || $a === '') { $flash = 'سؤال و پاسخ هر دو لازم است.'; $flashType = 'error'; }
            else {
                $pdo->prepare("INSERT INTO ai_knowledge (question, answer, source, enabled, created_at) VALUES (?, ?, 'manual', 1, ?)")->execute([$q, $a, date('Y-m-d H:i:s')]);
                $flash = 'دانش جدید اضافه شد.'; $flashType = 'success';
            }
        } elseif ($action === 'upload') {
            if (!isset($_FILES['kbfile']) || (int)($_FILES['kbfile']['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $flash = 'فایلی آپلود نشد یا خطا در آپلود.'; $flashType = 'error';
            } elseif ((int)($_FILES['kbfile']['size'] ?? 0) > 2 * 1024 * 1024) {
                $flash = 'حجم فایل بیش از ۲ مگابایت است.'; $flashType = 'error';
            } else {
                $content = (string)@file_get_contents((string)$_FILES['kbfile']['tmp_name']);
                // تبدیل encoding — فقط UTF-8 و UTF-16 را تست کن (Windows-1256 در PHP 8.4 نامعتبر است)
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $det = mb_detect_encoding($content, ['UTF-8', 'UTF-16', 'ISO-8859-1', 'ASCII'], true);
                    if ($det !== false && $det !== 'UTF-8') {
                        $content = @mb_convert_encoding($content, 'UTF-8', $det);
                    } else {
                        // fallback: iconv
                        $content = @iconv('UTF-8//IGNORE', 'UTF-8//IGNORE', $content) ?: $content;
                    }
                }
                $content = trim($content);
                if ($content === '') { $flash = 'محتوای فایل خالی است.'; $flashType = 'error'; }
                else {
                    $name = mb_strimwidth((string)($_FILES['kbfile']['name'] ?? 'فایل آپلودی'), 0, 200, '', 'UTF-8');
                    $pdo->prepare("INSERT INTO ai_knowledge (question, answer, source, enabled, created_at) VALUES (?, ?, 'file', 1, ?)")
                        ->execute([$name, mb_strimwidth($content, 0, 60000, '', 'UTF-8'), date('Y-m-d H:i:s')]);
                    $flash = 'فایل به پایگاه دانش اضافه شد.'; $flashType = 'success';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) { $pdo->prepare("UPDATE ai_knowledge SET enabled = 1 - enabled WHERE id = ?")->execute([$id]); $flash = 'وضعیت تغییر کرد.'; $flashType = 'success'; }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) { $pdo->prepare("DELETE FROM ai_knowledge WHERE id = ?")->execute([$id]); $flash = 'حذف شد.'; $flashType = 'success'; }
        }
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/ai_knowledge.php'), ENT_QUOTES); $flashType = 'error'; error_log('[ai_knowledge.php] '.redfox_exception_fingerprint($e)); }
}

$kbRows = [];
try { $kbRows = $pdo->query("SELECT * FROM ai_knowledge ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$sourceLabels = ['admin' => '🧑‍💼 از پاسخ ادمین', 'manual' => '✍️ دستی', 'file' => '📄 فایل'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پایگاه دانش هوش مصنوعی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
.rx-field{margin-bottom:10px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}
.rx-field input,.rx-field textarea{width:100%;max-width:680px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;font-family:inherit;box-sizing:border-box}
.rx-field textarea{min-height:70px}
.rx-badge{display:inline-block;padding:3px 8px;border-radius:14px;font-size:11px;font-weight:700}
.rx-badge.on{background:var(--color-success-soft);color:var(--color-success)}
.rx-badge.off{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-pre{white-space:pre-wrap;word-break:break-word;font-size:12px;color:var(--text-muted);max-height:90px;overflow:auto;background:var(--surface-2,#222);border-radius:6px;padding:6px 8px;margin-top:4px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📚 پایگاه دانش هوش مصنوعی</h1>
<div class="page-head__sub">یادگیری از پاسخ‌های ادمین و فایل‌ها — هوش مصنوعی از این دانش برای پاسخ‌دهی استفاده می‌کند</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">➕ افزودن دستی (سؤال + پاسخ)</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="add">
<div class="rx-field"><label>سؤال / موضوع / کلمات کلیدی</label><input type="text" name="question" placeholder="مثلاً: چطور موجودی کیف پول را شارژ کنم؟"></div>
<div class="rx-field"><label>پاسخ</label><textarea name="answer" placeholder="پاسخی که هوش مصنوعی باید بدهد..."></textarea></div>
<button class="btn btn-sm btn-success" type="submit">افزودن به دانش</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📝 افزودن متن بزرگ (کپی‌پیست)</h2></div>
<?php if($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="add_text">
<div class="rx-field"><label>عنوان (اختیاری)</label><input type="text" name="text_title" placeholder="مثلاً: قوانین فروشگاه" style="width:300px"></div>
<div class="rx-field"><label>متن کامل — هوش مصنوعی از این متن یاد می‌گیرد</label><textarea name="text_body" placeholder="کل متن را اینجا کپی‌پیست کنید... (تا ۶۰ هزار کاراکتر)" style="min-height:200px;line-height:1.8"></textarea></div>
<button class="btn btn-sm btn-primary" type="submit">➕ افزودن به پایگاه دانش</button>
</form>
<p class="rx-info">💡 کل متن FAQ، قوانین، توضیحات محصولات یا هر راهنمایی را اینجا کپی‌پیست کنید. هوش مصنوعی از این متن برای پاسخ‌دهی استفاده می‌کند.</p>
<?php else: ?><p class="text-muted">فقط ادمین اصلی.</p><?php endif; ?>
</div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📄 آپلود فایل متنی</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload">
<input type="file" name="kbfile" accept=".txt,.md,.csv,text/plain" required style="color:var(--text-main)">
<button class="btn btn-sm btn-primary" type="submit" style="margin-right:8px">آپلود و تبدیل به دانش</button>
</form>
<?php endif; ?>
<p class="rx-help">💡 محتوای فایل (تا ۲ مگابایت) به‌عنوان یک سندِ دانش ذخیره می‌شود. هنگام پاسخ‌دهی، بخش مرتبط آن به هوش مصنوعی داده می‌شود.</p></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📋 دانش ذخیره‌شده (<?= count($kbRows) ?>)</h2></div>
<?php if (empty($kbRows)): ?><p class="text-muted">هنوز چیزی ثبت نشده است. با پاسخ‌دادن به تیکت‌ها، هوش مصنوعی خودکار یاد می‌گیرد.</p><?php else: ?>
<div class="table-wrap"><table class="app-table" style="width:100%">
<thead><tr><th>منبع</th><th>عنوان/سؤال</th><th>متن کامل</th><th>وضعیت</th><th>مدیریت</th></tr></thead><tbody>
<?php foreach ($kbRows as $k):
    $on = (int)$k['enabled'] === 1; ?>
<tr>
<td><span class="rx-badge <?= $on?'on':'off' ?>"><?= htmlspecialchars($sourceLabels[$k['source']] ?? $k['source'], ENT_QUOTES,'UTF-8') ?></span></td>
<td><?= htmlspecialchars(mb_strimwidth((string)$k['question'],0,80,'…','UTF-8'),ENT_QUOTES,'UTF-8') ?></td>
<td><div class="rx-pre"><?= htmlspecialchars(mb_strimwidth((string)$k['answer'],0,160,'…','UTF-8'),ENT_QUOTES,'UTF-8') ?></div></td>
<td><?= $on ? 'فعال' : 'غیرفعال' ?></td>
<td>
<?php if ($isMainAdmin): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
<button class="btn btn-sm <?= $on?'btn-soft-warning':'btn-success' ?>" type="submit"><?= $on?'غیرفعال':'فعال' ?></button></form>
<form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
<button class="btn btn-sm btn-danger" type="submit">حذف</button></form>
<?php endif; ?>
</td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<p class="rx-help">🧠 هر بار که ادمین به یک تیکت پاسخ می‌دهد، آن جفت سؤال/پاسخ خودکار با منبع «از پاسخ ادمین» اینجا اضافه می‌شود.</p>
</div>

</div></section></section></body></html>
