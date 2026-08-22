<?php
/** Red Fox — لاگ ممیزی (Audit Log) — نمایش همه‌ی عملیات انجام‌شده در پنل */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
$adminRow = null;
if (!empty($_SESSION['user'])) { $q=$pdo->prepare("SELECT * FROM admin WHERE username=:u"); $q->bindValue(':u',$_SESSION['user'],PDO::PARAM_STR); $q->execute(); $adminRow=$q->fetch(PDO::FETCH_ASSOC); }
if (!$adminRow) { header('Location: login.php'); exit; }

rx_require_schema($pdo,['audit_log']);

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$filterAction = trim((string)($_GET['action'] ?? ''));

$where = ''; $params = [];
if ($filterAction !== '') { $where = ' WHERE action LIKE ?'; $params[] = "%$filterAction%"; }

try {
    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM audit_log $where")->execute($params) ? $pdo->query("SELECT COUNT(*) FROM audit_log $where" . ($where ? '' : ''))->fetchColumn() : 0;
} catch (Throwable $e) { $total = 0; }

$logs = [];
try {
    $sql = "SELECT * FROM audit_log $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$pages = max(1, ceil($total / $perPage));
$actionIcons = ['create' => '➕', 'update' => '✏️', 'delete' => '🗑', 'login' => '🔓', 'logout' => '🚪', 'backup' => '💾', 'restore' => '♻️', 'export' => '📤', 'settings' => '⚙️', 'default' => '📋'];
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>لاگ ممیزی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-filter{display:flex;gap:8px;align-items:center;margin-bottom:14px}
.rx-filter input{padding:8px 12px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px}
table{width:100%;border-collapse:collapse}th,td{padding:9px;text-align:right;font-size:12px;border-bottom:1px solid var(--border-soft);color:var(--text-main)}
th{color:var(--text-muted);font-size:11px}
.rx-page-btn{padding:5px 12px;border-radius:7px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:12px;text-decoration:none;margin:2px}
.rx-page-btn.active{background:var(--accent);color:var(--accent-fg)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">📋 لاگ ممیزی</h1>
<div class="page-head__sub">تاریخچه‌ی همه‌ی عملیات انجام‌شده در پنل (<?= number_format($total) ?> رکورد)</div></div>

<div class="rx-filter">
<form method="get" style="display:flex;gap:8px">
<input type="text" name="action" value="<?= htmlspecialchars($filterAction,ENT_QUOTES) ?>" placeholder="فیلتر بر اساس عملیات...">
<button class="btn btn-sm btn-primary" type="submit">🔍 فیلتر</button>
<?php if ($filterAction): ?><a class="btn btn-sm" href="audit_log.php">پاک‌سازی</a><?php endif; ?>
</form>
</div>

<div class="card">
<div class="table-wrap">
<table><thead><tr><th>#</th><th>عملیات</th><th>کاربر</th><th>موضوع</th><th>جزئیات</th><th>IP</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($logs as $log):
    $icon = $actionIcons['default'];
    foreach ($actionIcons as $k => $v) { if (stripos($log['action'], $k) !== false) { $icon = $v; break; } }
?>
<tr>
<td><?= (int)$log['id'] ?></td>
<td><?= $icon ?> <?= htmlspecialchars((string)$log['action'],ENT_QUOTES,'UTF-8') ?></td>
<td><code><?= htmlspecialchars((string)($log['admin_user'] ?? ''),ENT_QUOTES) ?></code></td>
<td><?= htmlspecialchars((string)($log['entity'] ?? '—'),ENT_QUOTES,'UTF-8') ?></td>
<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;font-size:11px;color:var(--text-muted)"><?= htmlspecialchars(mb_substr((string)($log['details'] ?? ''),0,100),ENT_QUOTES,'UTF-8') ?></td>
<td style="direction:ltr"><?= htmlspecialchars((string)($log['ip'] ?? ''),ENT_QUOTES) ?></td>
<td style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars((string)$log['created_at'],ENT_QUOTES) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($logs)): ?><tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:20px">هیچ رکوردی ثبت نشده.</td></tr><?php endif; ?>
</tbody></table>
</div>
<?php if ($pages > 1): ?>
<div style="margin-top:12px;display:flex;gap:2px;flex-wrap:wrap">
<?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
<a href="audit_log.php?p=<?= $p ?><?= $filterAction ? '&action=' . urlencode($filterAction) : '' ?>" class="rx-page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>

</div></section></section></body></html>
