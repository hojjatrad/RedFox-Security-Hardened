<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/lib/Portal.php';
require_once __DIR__.'/lib/Layout.php';
$ctx = rxp_context($pdo);
rxp_require_perm($ctx, 'reports');

/* ─── helpers ─── */
function rxpc_rows(PDO $p, string $s, array $x): array {
    $q = $p->prepare($s); $q->execute($x); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function rxpc_one(PDO $p, string $s, array $x): int {
    $q = $p->prepare($s); $q->execute($x); return (int)($q->fetchColumn() ?: 0);
}

/* ─── scope ─── */
[$scope, $scopeParams] = rxp_invoice_scope($ctx, 'i');
$done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";

/* ─── selected reseller ─── */
$selectedId = trim((string)($_GET['id'] ?? $ctx['id']));
if (!in_array($selectedId, $ctx['scope_ids'], true)) {
    rxp_abort(403, 'این نماینده در محدوده شما نیست.');
}

/* ─── reseller info ─── */
$resellerInfo = rxpc_rows($pdo, "SELECT id, username, namecustom, Balance, agent, register FROM user WHERE id = ?", [$selectedId]);
$reseller = $resellerInfo[0] ?? null;

/* ─── customers of this reseller ─── */
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$off = ($page - 1) * $per;

$customerWhere = "i.refral = ?";
$customerParams = [$selectedId];

if ($search !== '') {
    $customerWhere .= ' AND (i.id_user LIKE ? OR u.username LIKE ? OR u.namecustom LIKE ?)';
    $like = '%' . $search . '%';
    $customerParams = array_merge($customerParams, [$like, $like, $like]);
}

/* Count total customers */
$totalCustomers = rxpc_one($pdo,
    "SELECT COUNT(DISTINCT i.id_user) FROM invoice i LEFT JOIN user u ON u.id = i.id_user WHERE $customerWhere",
    $customerParams
);

/* Customer list with stats */
$customers = rxpc_rows($pdo,
    "SELECT i.id_user,
            u.username, u.namecustom, u.Balance, u.User_Status, u.register,
            COUNT(*) AS total_invoices,
            COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) AS total_spent,
            MAX(CAST(i.time_sell AS UNSIGNED)) AS last_purchase,
            SUM(CASE WHEN i.Status IN ($done) THEN 1 ELSE 0 END) AS active_services
     FROM invoice i
     LEFT JOIN user u ON u.id = i.id_user
     WHERE $customerWhere
     GROUP BY i.id_user
     ORDER BY total_spent DESC
     LIMIT $per OFFSET $off",
    $customerParams
);

/* Reseller totals */
$resellerRevenue = rxpc_one($pdo,
    "SELECT COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) FROM invoice i WHERE i.refral = ? AND i.Status IN ($done)",
    [$selectedId]
);
$resellerTotalInvoices = rxpc_one($pdo,
    "SELECT COUNT(*) FROM invoice i WHERE i.refral = ?",
    [$selectedId]
);
$resellerActiveServices = rxpc_one($pdo,
    "SELECT COUNT(*) FROM invoice i WHERE i.refral = ? AND i.Status IN ($done)",
    [$selectedId]
);

/* Recent invoices */
$recentInvoices = rxpc_rows($pdo,
    "SELECT i.id_invoice, i.id_user, i.name_product, i.price_product, i.Status, i.time_sell,
            u.username AS uname
     FROM invoice i LEFT JOIN user u ON u.id = i.id_user
     WHERE i.refral = ?
     ORDER BY CAST(i.time_sell AS UNSIGNED) DESC LIMIT 20",
    [$selectedId]
);

/* Product breakdown for this reseller */
$productBreakdown = rxpc_rows($pdo,
    "SELECT i.name_product, COUNT(*) c, COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) r
     FROM invoice i WHERE i.refral = ? GROUP BY i.name_product ORDER BY r DESC LIMIT 15",
    [$selectedId]
);

/* ─── CSV export ─── */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="reseller-' . $selectedId . '-customers.csv"');
    $o = fopen('php://output', 'w');
    fprintf($o, "\xEF\xBB\xBF");
    fputcsv($o, ['user_id', 'username', 'name', 'balance', 'status', 'total_invoices', 'total_spent', 'last_purchase', 'active_services']);
    foreach ($customers as $c) {
        fputcsv($o, [
            $c['id_user'], $c['username'], $c['namecustom'],
            $c['Balance'], $c['User_Status'], $c['total_invoices'],
            $c['total_spent'], date('Y/m/d H:i', (int)$c['last_purchase']),
            $c['active_services']
        ]);
    }
    fclose($o);
    exit;
}

/* ─── render ─── */
rxp_layout_start($ctx, 'مشتریان نماینده', 'reports.php');

$resellerName = $reseller ? ($reseller['username'] ?: ($reseller['namecustom'] ?: $selectedId)) : $selectedId;
?>

<h1 class="rxp-title">👥 مشتریان نماینده: <?=rxp_h($resellerName)?></h1>
<p class="rxp-sub">شناسه: <code><?=rxp_h($selectedId)?></code> — لیست تمام کاربرانی که توسط این نماینده خرید انجام داده‌اند</p>

<!-- Reseller Stats -->
<div class="rxp-grid">
    <div class="rxp-stat">
        <b><?=$totalCustomers?></b>
        <span>👥 تعداد مشتریان</span>
    </div>
    <div class="rxp-stat">
        <b><?=rxp_money($resellerRevenue)?></b>
        <span>💰 درآمد کل</span>
    </div>
    <div class="rxp-stat">
        <b><?=$resellerTotalInvoices?></b>
        <span>📦 کل سفارش‌ها</span>
    </div>
    <div class="rxp-stat">
        <b><?=$resellerActiveServices?></b>
        <span>🟢 سرویس‌های فعال</span>
    </div>
    <div class="rxp-stat">
        <b><?=rxp_money($reseller['Balance'] ?? 0)?></b>
        <span>🏦 موجودی نماینده</span>
    </div>
    <div class="rxp-stat">
        <b><?=rxp_money($totalCustomers > 0 ? intdiv($resellerRevenue, $totalCustomers) : 0)?></b>
        <span>📈 میانگین خرید هر مشتری</span>
    </div>
</div>

<!-- Back link + export -->
<div style="margin:14px 0;display:flex;gap:8px;flex-wrap:wrap">
    <a class="rxp-btn gray" href="reports.php">📊 بازگشت به گزارشات</a>
    <a class="rxp-btn green" href="?id=<?=urlencode($selectedId)?>&export=csv&q=<?=urlencode($search)?>">📥 خروجی CSV مشتریان</a>
    <?php if ($ctx['role'] === 'super'): ?>
    <a class="rxp-btn gray" href="reseller_customers.php">👥 همه نمایندگان</a>
    <?php endif; ?>
</div>

<!-- Customer List -->
<div class="rxp-card">
    <h2>👥 لیست مشتریان (<?=$totalCustomers?> نفر)</h2>
    
    <!-- Search -->
    <form method="get" class="rxp-form" style="margin-bottom:14px">
        <input type="hidden" name="id" value="<?=rxp_h($selectedId)?>">
        <div class="rxp-field">
            <label>جستجو با آیدی یا نام کاربر</label>
            <input name="q" value="<?=rxp_h($search)?>" placeholder="مثال: 123456 یا علی">
        </div>
        <button class="rxp-btn">🔍 جستجو</button>
        <?php if ($search !== ''): ?>
            <a class="rxp-btn gray" href="?id=<?=urlencode($selectedId)?>">پاک کردن</a>
        <?php endif; ?>
    </form>

    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <thead>
                <tr>
                    <th>آیدی کاربر</th>
                    <th>نام کاربری</th>
                    <th>نام سفارشی</th>
                    <th>موجودی</th>
                    <th>وضعیت</th>
                    <th>تعداد خرید</th>
                    <th>مجموع خرید</th>
                    <th>سرویس فعال</th>
                    <th>آخرین خرید</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="10" style="text-align:center;padding:20px;color:var(--text-muted)">
                    <?= $search !== '' ? 'نتیجه‌ای یافت نشد.' : 'هنوز مشتری‌ای ثبت نشده.' ?>
                </td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><code><?=rxp_h($c['id_user'])?></code></td>
                    <td><b><?=rxp_h($c['username'] ?: '—')?></b></td>
                    <td><?=rxp_h($c['namecustom'] ?: '—')?></td>
                    <td><?=rxp_money($c['Balance'])?></td>
                    <td>
                        <?php if ($c['User_Status'] === 'Active'): ?>
                            <span style="color:var(--color-success)">🟢 فعال</span>
                        <?php elseif ($c['User_Status'] === 'Block'): ?>
                            <span style="color:var(--color-danger)">🔴 مسدود</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">⚪ <?=rxp_h($c['User_Status'])?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="rxp-badge"><?=$c['total_invoices']?></span></td>
                    <td><b><?=rxp_money($c['total_spent'])?></b></td>
                    <td>
                        <?php if ($c['active_services'] > 0): ?>
                            <span class="rxp-badge" style="background:var(--color-success-soft);color:var(--color-success)"><?=$c['active_services']?></span>
                        <?php else: ?>
                            <span class="rxp-badge" style="background:var(--color-danger-soft);color:var(--color-danger)">۰</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['last_purchase'] ? date('Y/m/d', (int)$c['last_purchase']) : '—' ?></td>
                    <td>
                        <a class="rxp-btn gray" href="users.php?id=<?=urlencode($c['id_user'])?>" style="font-size:11px;padding:5px 8px">مشاهده</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rxp_pager($page, (int)ceil($totalCustomers / $per), ['id' => $selectedId, 'q' => $search]); ?>
</div>

<!-- Product Breakdown -->
<?php if (!empty($productBreakdown)): ?>
<div class="rxp-card">
    <h2>🛍 محصولات فروخته‌شده توسط این نماینده</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>محصول</th><th>تعداد فروش</th><th>درآمد</th></tr>
            <?php foreach ($productBreakdown as $r): ?>
            <tr>
                <td><b><?=rxp_h($r['name_product'] ?: 'نامشخص')?></b></td>
                <td><?=$r['c']?></td>
                <td><?=rxp_money($r['r'])?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Invoices -->
<?php if (!empty($recentInvoices)): ?>
<div class="rxp-card">
    <h2>🧾 آخرین سفارشات این نماینده</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>کد سفارش</th><th>مشتری</th><th>محصول</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
            <?php foreach ($recentInvoices as $inv): ?>
            <tr>
                <td><code><?=rxp_h($inv['id_invoice'])?></code></td>
                <td><?=rxp_h($inv['uname'] ?: $inv['id_user'])?></td>
                <td><?=rxp_h($inv['name_product'])?></td>
                <td><?=rxp_money($inv['price_product'])?></td>
                <td>
                    <?php
                    $sc = [
                        'active' => '🟢', 'end_of_time' => '🔴', 'end_of_volume' => '🔴',
                        'pending' => '🟡', 'pending_payment' => '🟡', 'cancelled' => '⚫',
                        'sendedwarn' => '🟠', 'send_on_hold' => '🟠',
                    ];
                    echo ($sc[$inv['Status']] ?? '⚪') . ' ' . rxp_h($inv['Status']);
                    ?>
                </td>
                <td><?=date('Y/m/d H:i', (int)$inv['time_sell'])?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
/* ─── All resellers overview (super only, when no specific id selected) ─── */
if ($ctx['role'] === 'super' && !isset($_GET['id'])):
?>
<div class="rxp-card">
    <h2>⭐ خلاصه عملکرد همه نمایندگان</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr>
                <th>نماینده</th>
                <th>شناسه</th>
                <th>تعداد مشتریان</th>
                <th>کل فروش</th>
                <th>درآمد</th>
                <th>سرویس فعال</th>
                <th></th>
            </tr>
            <?php
            foreach ($ctx['scope_ids'] as $oid):
                $info = rxpc_rows($pdo, "SELECT username, namecustom FROM user WHERE id = ?", [$oid]);
                $name = $info[0]['username'] ?? ($info[0]['namecustom'] ?? $oid);
                $custCount = rxpc_one($pdo, "SELECT COUNT(DISTINCT id_user) FROM invoice WHERE refral = ?", [$oid]);
                $totalSales = rxpc_one($pdo, "SELECT COUNT(*) FROM invoice WHERE refral = ?", [$oid]);
                $totalRev = rxpc_one($pdo, "SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE refral = ? AND Status IN ($done)", [$oid]);
                $activeSvc = rxpc_one($pdo, "SELECT COUNT(*) FROM invoice WHERE refral = ? AND Status IN ($done)", [$oid]);
            ?>
            <tr>
                <td><b><?=rxp_h($name)?></b></td>
                <td><code><?=rxp_h($oid)?></code></td>
                <td><span class="rxp-badge"><?=$custCount?></span></td>
                <td><?=$totalSales?></td>
                <td><?=rxp_money($totalRev)?></td>
                <td><?=$activeSvc?></td>
                <td><a class="rxp-btn gray" href="?id=<?=urlencode($oid)?>">👥 مشتریان</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php rxp_layout_end(); ?>
