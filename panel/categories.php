<?php
/**
 * Red Fox — مدیریت دسته‌بندی محصولات در پنل تحت وب.
 *
 * جدول category (id, remark) — هر دسته یک «remark» (نام) دارد.
 * محصولات از طریق فیلد product.category با همین نام به دسته وصل می‌شوند.
 * مطابق رفتار خود ربات، افزودن/ویرایش/حذف فقط روی جدول category انجام می‌شود
 * (cascade خودکار روی محصولات انجام نمی‌گیرد تا با ربات سازگار بماند).
 */

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}

require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
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
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');

$flash = '';
$flashType = 'info';

/* ---- پردازش اکشن‌ها ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 480) {
                $flash = 'نام دسته نامعتبر است (۱ تا ۴۸۰ کاراکتر).';
                $flashType = 'error';
            } else {
                // جلوگیری از تکرار (بدون حساسیت به حروف)
                $chk = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ? COLLATE utf8mb4_bin");
                $chk->execute([$name]);
                if ((int)$chk->fetchColumn() > 0) {
                    $flash = 'این دسته از قبل وجود دارد.';
                    $flashType = 'error';
                } else {
                    $ins = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
                    $ins->execute([$name]);
                    $flash = 'دسته‌بندی اضافه شد.';
                    $flashType = 'success';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($id <= 0 || $name === '' || mb_strlen($name) > 480) {
                $flash = 'اطلاعات نامعتبر است.';
                $flashType = 'error';
            } else {
                $upd = $pdo->prepare("UPDATE category SET remark = ? WHERE id = ?");
                $upd->execute([$name, $id]);
                if ($upd->rowCount() > 0 || $upd->errorCode() === '00000') {
                    $flash = 'دسته‌بندی ویرایش شد.';
                    $flashType = 'success';
                } else {
                    $flash = 'ویرایش ناموفق بود.';
                    $flashType = 'error';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash = 'شناسه نامعتبر است.';
                $flashType = 'error';
            } else {
                $del = $pdo->prepare("DELETE FROM category WHERE id = ?");
                $del->execute([$id]);
                $flash = 'دسته‌بندی حذف شد.';
                $flashType = 'success';
            }
        }
    } catch (Throwable $e) {
        $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/categories.php'), ENT_QUOTES);
        $flashType = 'error';
        error_log('[categories.php] action error: ' . redfox_exception_fingerprint($e));
    }
}

/* ---- بارگذاری لیست دسته‌ها + تعداد محصولات هرکدام ---- */
$categories = [];
try {
    // چون product.category (unicode_ci) و category.remark (_bin) collation متفاوت دارند،
    // تطبیق را با CONVERT ایمن می‌کنیم.
    $stmt = $pdo->query("
        SELECT c.id, c.remark,
               (SELECT COUNT(*) FROM product p WHERE p.category COLLATE utf8mb4_bin = c.remark COLLATE utf8mb4_bin) AS product_count
        FROM category c
        ORDER BY c.id ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[categories.php] list failed: ' . redfox_exception_fingerprint($e));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>دسته‌بندی محصولات | ربات رد فاکس</title>
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .rx-flash { padding: 12px 16px; border-radius: 10px; margin: 0 0 16px; font-size: 13px; line-height: 1.9; }
        .rx-flash.success { background: var(--color-success-soft); color: var(--color-success); }
        .rx-flash.error   { background: var(--color-danger-soft);  color: var(--color-danger); }
        .rx-flash.info    { background: var(--accent-soft);        color: var(--text-main); }
        .rx-card-title { color: var(--text-main); }
        .rx-help { color: var(--text-muted); font-size: 12px; line-height: 1.9; margin-top: 14px; }
        .rx-inline { display: inline-flex; gap: 5px; align-items: center; flex-wrap: wrap; }
        .rx-inline input { padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-mid); background: var(--surface-1); color: var(--text-main); font-size: 13px; width: 220px; }
        code { color: var(--text-main); }
    </style>
</head>
<body>
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <div class="wrapper">
            <div class="page-head">
                <h1 class="page-head__title">🗂 دسته‌بندی محصولات</h1>
                <div class="page-head__sub">افزودن، ویرایش و حذف دسته‌بندی‌های فروشگاه</div>
            </div>

            <?php if ($flash !== ''): ?>
                <div class="rx-flash <?= htmlspecialchars($flashType, ENT_QUOTES) ?>"><?= $flash ?></div>
            <?php endif; ?>

            <!-- افزودن دسته جدید -->
            <div class="card">
                <div class="card__head"><h2 class="card__title rx-card-title">➕ دسته‌بندی جدید</h2></div>
                <?php if ($isMainAdmin): ?>
                <form method="post" class="rx-inline">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="name" placeholder="نام دسته (مثلاً: اشتراک یک‌ماهه)" required maxlength="480">
                    <button class="btn btn-sm btn-success" type="submit">افزودن</button>
                </form>
                <?php else: ?>
                <p class="text-muted">فقط ادمین اصلی می‌تواند افزودن کند.</p>
                <?php endif; ?>
            </div>

            <!-- لیست دسته‌ها -->
            <div class="card">
                <div class="card__head"><h2 class="card__title rx-card-title">📋 دسته‌بندی‌ها (<?= count($categories) ?>)</h2></div>
                <?php if (empty($categories)): ?>
                    <p class="text-muted">هنوز دسته‌بندی‌ای ثبت نشده است.</p>
                <?php else: ?>
                    <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead><tr><th>شناسه</th><th>نام دسته</th><th>تعداد محصولات</th><th>مدیریت</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><code><?= (int)$c['id'] ?></code></td>
                                <td><?= htmlspecialchars((string)$c['remark'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)$c['product_count'] ?></td>
                                <td>
                                    <?php if ($isMainAdmin): ?>
                                    <form method="post" class="rx-inline">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="text" name="name" value="<?= htmlspecialchars((string)$c['remark'], ENT_QUOTES, 'UTF-8') ?>" maxlength="480">
                                        <button class="btn btn-sm btn-primary" type="submit">ذخیره</button>
                                    </form>
                                    <form method="post" class="rx-inline" onsubmit="return confirm('حذف شود؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
                <p class="rx-help">
                    💡 محصولات از طریق فیلد «دسته‌بندی» به این دسته‌ها وصل می‌شوند.<br>
                    ⚠️ مطابق رفتار ربات، ویرایش/حذف یک دسته، خودکار روی محصولات موجود اعمال نمی‌شود؛ اگر نام دسته را عوض می‌کنی، محصولاتِ مرتبط را هم به‌روز کن.
                </p>
            </div>
        </div>
    </section>
</section>
</body>
</html>
