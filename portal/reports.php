<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/lib/Portal.php';
require_once __DIR__.'/lib/Layout.php';
$ctx = rxp_context($pdo);
rxp_require_perm($ctx, 'reports');

/* ─── helpers ─── */
function rxpr_rows(PDO $p, string $s, array $x): array {
    $q = $p->prepare($s); $q->execute($x); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function rxpr_one(PDO $p, string $s, array $x): int {
    $q = $p->prepare($s); $q->execute($x); return (int)($q->fetchColumn() ?: 0);
}

/* ─── date range filter ─── */
$range = trim((string)($_GET['range'] ?? 'all'));
$ts = time();
$dateFilter = '';
$dateParams = [];
if ($range === '7d') {
    $dateFilter = ' AND CAST(i.time_sell AS UNSIGNED) >= ?';
    $dateParams = [$ts - 7 * 86400];
} elseif ($range === '30d') {
    $dateFilter = ' AND CAST(i.time_sell AS UNSIGNED) >= ?';
    $dateParams = [$ts - 30 * 86400];
} elseif ($range === '90d') {
    $dateFilter = ' AND CAST(i.time_sell AS UNSIGNED) >= ?';
    $dateParams = [$ts - 90 * 86400];
} elseif ($range === 'month') {
    $monthStart = strtotime(date('Y-m-01'));
    $dateFilter = ' AND CAST(i.time_sell AS UNSIGNED) >= ?';
    $dateParams = [$monthStart];
}

/* ─── scope ─── */
[$scope, $scopeParams] = rxp_invoice_scope($ctx, 'i');
$done = "'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
$baseWhere = "$scope $dateFilter";
$baseParams = array_merge($scopeParams, $dateParams);

/* ─── core metrics ─── */
$revenue  = rxpr_one($pdo, "SELECT COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) FROM invoice i WHERE $baseWhere AND i.Status IN ($done)", $baseParams);
$sales    = rxpr_one($pdo, "SELECT COUNT(*) FROM invoice i WHERE $baseWhere", $baseParams);
$avg      = $sales ? intdiv($revenue, $sales) : 0;
$pending  = rxpr_one($pdo, "SELECT COUNT(*) FROM invoice i WHERE $baseWhere AND i.Status IN ('pending','pending_payment')", $baseParams);
$failed   = rxpr_one($pdo, "SELECT COUNT(*) FROM invoice i WHERE $baseWhere AND i.Status IN ('cancelled','failed','expired')", $baseParams);
$walletIn = rxpr_one($pdo, "SELECT COALESCE(SUM(Balance),0) FROM user WHERE id IN (" . implode(',', array_fill(0, count($ctx['scope_ids']), '?')) . ")", $ctx['scope_ids']);

/* ─── daily revenue chart (last 30 days) ─── */
$dailyRows = rxpr_rows($pdo,
    "SELECT DATE(FROM_UNIXTIME(CAST(i.time_sell AS UNSIGNED))) AS d, 
            COUNT(*) AS c,
            COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) AS r
     FROM invoice i
     WHERE $scope AND CAST(i.time_sell AS UNSIGNED) >= ?
     GROUP BY d ORDER BY d",
    array_merge($scopeParams, [$ts - 30 * 86400])
);

/* ─── breakdowns ─── */
$byProduct = rxpr_rows($pdo,
    "SELECT i.name_product, COUNT(*) c, COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) r
     FROM invoice i WHERE $baseWhere GROUP BY i.name_product ORDER BY r DESC LIMIT 30",
    $baseParams
);
$byStatus = rxpr_rows($pdo,
    "SELECT i.Status, COUNT(*) c FROM invoice i WHERE $baseWhere GROUP BY i.Status ORDER BY c DESC",
    $baseParams
);

/* ─── reseller performance (for super) ─── */
$byOwner = [];
if ($ctx['role'] === 'super') {
    foreach ($ctx['scope_ids'] as $oid) {
        $q = $pdo->prepare(
            "SELECT u.username, u.namecustom,
                    COUNT(*) c,
                    COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) r,
                    COUNT(DISTINCT i.id_user) AS customers
             FROM invoice i
             LEFT JOIN user u ON u.id = ?
             WHERE i.refral = ? AND i.Status IN ($done) $dateFilter"
        );
        $params = array_merge([$oid, $oid], $dateParams);
        $q->execute($params);
        $v = $q->fetch(PDO::FETCH_ASSOC);
        $byOwner[] = [
            'id'        => $oid,
            'name'      => $v['username'] ?: ($v['namecustom'] ?: $oid),
            'c'         => $v['c'] ?? 0,
            'r'         => $v['r'] ?? 0,
            'customers' => $v['customers'] ?? 0,
        ];
    }
    usort($byOwner, fn($a, $b) => $b['r'] <=> $a['r']);
}

/* ─── top customers ─── */
$topCustomers = rxpr_rows($pdo,
    "SELECT i.id_user, u.username, u.namecustom,
            COUNT(*) c, COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) r
     FROM invoice i
     LEFT JOIN user u ON u.id = i.id_user
     WHERE $baseWhere AND i.Status IN ($done)
     GROUP BY i.id_user ORDER BY r DESC LIMIT 20",
    $baseParams
);

/* ─── product popularity (pie-friendly) ─── */
$totalProductRevenue = array_sum(array_column($byProduct, 'r'));

/* ─── CSV export ─── */
if (($_GET['export'] ?? '') === 'csv') {
    rxp_require_perm($ctx, 'reports');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="redfox-report-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fprintf($o, "\xEF\xBB\xBF");
    fputcsv($o, ['invoice_id', 'user_id', 'username', 'product', 'price', 'status', 'date', 'reseller']);
    $q = $pdo->prepare(
        "SELECT i.*, u.username AS uname
         FROM invoice i LEFT JOIN user u ON u.id = i.id_user
         WHERE $baseWhere ORDER BY CAST(i.time_sell AS UNSIGNED) DESC LIMIT 50000"
    );
    $q->execute($baseParams);
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($o, [
            $r['id_invoice'], $r['id_user'], $r['uname'] ?? '',
            $r['name_product'], $r['price_product'], $r['Status'],
            date('Y/m/d H:i', (int)$r['time_sell']), $r['refral'] ?? ''
        ]);
    }
    fclose($o);
    exit;
}

/* ─── render ─── */
rxp_layout_start($ctx, 'گزارش مالی حرفه‌ای', 'reports.php');

$rangeOptions = [
    'all'   => 'همه دوره‌ها',
    'month' => 'ماه جاری',
    '90d'   => '۹۰ روز اخیر',
    '30d'   => '۳۰ روز اخیر',
    '7d'    => '۷ روز اخیر',
];
?>

<h1 class="rxp-title">📊 گزارش مالی حرفه‌ای</h1>
<p class="rxp-sub">تحلیل جامع فروش، درآمد و عملکرد نمایندگان</p>

<!-- Date range filter -->
<div class="rxp-card" style="margin-bottom:14px">
    <form method="get" class="rxp-form">
        <div class="rxp-field">
            <label>بازه زمانی</label>
            <select name="range">
                <?php foreach ($rangeOptions as $k => $v): ?>
                    <option value="<?=$k?>" <?=$range === $k ? 'selected' : ''?>><?=rxp_h($v)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="rxp-btn">فیلتر</button>
        <a class="rxp-btn gray" href="?range=<?=rxp_h($range)?>&export=csv">📥 خروجی CSV</a>
    </form>
</div>

<!-- KPI Cards -->
<div class="rxp-grid">
    <div class="rxp-stat">
        <b><?=rxp_money($revenue)?></b>
        <span>💰 درآمد موفق (تومان)</span>
    </div>
    <div class="rxp-stat">
        <b><?=$sales?></b>
        <span>📦 کل سفارش‌ها</span>
    </div>
    <div class="rxp-stat">
        <b><?=rxp_money($avg)?></b>
        <span>📈 میانگین سفارش</span>
    </div>
    <div class="rxp-stat">
        <b><?=$pending?></b>
        <span>⏳ در انتظار پرداخت</span>
    </div>
    <div class="rxp-stat">
        <b><?=$failed?></b>
        <span>❌ ناموفق / لغو شده</span>
    </div>
    <div class="rxp-stat">
        <b><?=rxp_money($walletIn)?></b>
        <span>🏦 موجودی کیف پول</span>
    </div>
</div>

<!-- Daily Revenue Chart (text-based) -->
<?php if (!empty($dailyRows)): ?>
<div class="rxp-card">
    <h2>📊 نمودار درآمد روزانه (۳۰ روز اخیر)</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>تاریخ</th><th>تعداد</th><th>درآمد</th><th>نمودار</th></tr>
            <?php
            $maxR = max(1, ...array_column($dailyRows, 'r'));
            foreach ($dailyRows as $d):
                $pct = round(($d['r'] / $maxR) * 100);
            ?>
            <tr>
                <td><?=rxp_h($d['d'])?></td>
                <td><?=$d['c']?></td>
                <td><?=rxp_money($d['r'])?></td>
                <td style="min-width:200px">
                    <div style="background:var(--accent-soft);border-radius:4px;height:18px;width:100%;position:relative">
                        <div style="background:var(--accent);height:100%;border-radius:4px;width:<?=$pct?>%;min-width:2px"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- By Product -->
<div class="rxp-card">
    <h2>🛍 فروش بر اساس محصول</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>محصول</th><th>تعداد فروش</th><th>درآمد</th><th>سهم از کل</th><th>نمودار</th></tr>
            <?php foreach ($byProduct as $r):
                $share = $totalProductRevenue > 0 ? round(($r['r'] / $totalProductRevenue) * 100, 1) : 0;
            ?>
            <tr>
                <td><b><?=rxp_h($r['name_product'] ?: 'نامشخص')?></b></td>
                <td><?=$r['c']?></td>
                <td><?=rxp_money($r['r'])?></td>
                <td><?=$share?>%</td>
                <td style="min-width:120px">
                    <div style="background:var(--accent-soft);border-radius:4px;height:14px;width:100%;position:relative">
                        <div style="background:var(--color-success);height:100%;border-radius:4px;width:<?=$share?>%;min-width:2px"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- By Status -->
<div class="rxp-card">
    <h2>📋 وضعیت سفارش‌ها</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>وضعیت</th><th>تعداد</th><th>درصد</th></tr>
            <?php foreach ($byStatus as $r):
                $pct = $sales > 0 ? round(($r['c'] / $sales) * 100, 1) : 0;
            ?>
            <tr>
                <td>
                    <?php
                    $statusColors = [
                        'active' => '🟢', 'end_of_time' => '🔴', 'end_of_volume' => '🔴',
                        'pending' => '🟡', 'pending_payment' => '🟡', 'cancelled' => '⚫',
                        'sendedwarn' => '🟠', 'send_on_hold' => '🟠', 'expired' => '🔴',
                    ];
                    $icon = $statusColors[$r['Status']] ?? '⚪';
                    ?>
                    <?=$icon?> <?=rxp_h($r['Status'])?>
                </td>
                <td><?=$r['c']?></td>
                <td><?=$pct?>%</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- Top Customers -->
<?php if (!empty($topCustomers)): ?>
<div class="rxp-card">
    <h2>🏆 مشتریان برتر (بیشترین خرید)</h2>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr><th>#</th><th>کاربر</th><th>آیدی</th><th>تعداد خرید</th><th>مجموع خرید</th></tr>
            <?php $rank = 0; foreach ($topCustomers as $r): $rank++; ?>
            <tr>
                <td>
                    <?php if ($rank <= 3): ?>
                        <span style="font-size:16px"><?=$rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉')?></span>
                    <?php else: ?>
                        <?=$rank?>
                    <?php endif; ?>
                </td>
                <td><b><?=rxp_h($r['username'] ?: ($r['namecustom'] ?: '—'))?></b></td>
                <td><code><?=rxp_h($r['id_user'])?></code></td>
                <td><?=$r['c']?></td>
                <td><?=rxp_money($r['r'])?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Reseller Performance (super only) -->
<?php if ($ctx['role'] === 'super' && !empty($byOwner)): ?>
<div class="rxp-card">
    <h2>⭐ عملکرد نمایندگان</h2>
    <p class="rxp-muted">فروش، درآمد و تعداد مشتریان هر نماینده</p>
    <div class="rxp-table-wrap">
        <table class="rxp-table">
            <tr>
                <th>نماینده</th>
                <th>شناسه</th>
                <th>تعداد فروش</th>
                <th>درآمد</th>
                <th>تعداد مشتریان</th>
                <th>میانگین هر مشتری</th>
                <th></th>
            </tr>
            <?php foreach ($byOwner as $r):
                $avgPerCustomer = $r['customers'] > 0 ? intdiv($r['r'], $r['customers']) : 0;
            ?>
            <tr>
                <td><b><?=rxp_h($r['name'])?></b></td>
                <td><code><?=rxp_h($r['id'])?></code></td>
                <td><?=$r['c']?></td>
                <td><?=rxp_money($r['r'])?></td>
                <td><span class="rxp-badge"><?=$r['customers']?></span></td>
                <td><?=rxp_money($avgPerCustomer)?></td>
                <td><a class="rxp-btn gray" href="reseller_customers.php?id=<?=urlencode($r['id'])?>">👥 مشتریان</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php rxp_layout_end(); ?>
