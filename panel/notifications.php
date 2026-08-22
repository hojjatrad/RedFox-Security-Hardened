<?php
declare(strict_types=1);

if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) {
    define('REDFOX_SKIP_BOTAPI_ROUTER', true);
}
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/AdminNotifications.php';
require_once __DIR__ . '/../lib/PersianDate.php';
require_once __DIR__ . '/lib/icons.php';

$q = $pdo->prepare('SELECT * FROM admin WHERE username=? LIMIT 1');
$q->execute([$_SESSION['user'] ?? '']);
$admin = $q->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    header('Location: login.php', true, 302);
    exit;
}
if (($admin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit;
}

$svc = new RedFoxAdminNotifications($pdo);

// Polling is strictly read-only. Synchronization is handled by the protected
// admin_notifications cron worker or by the explicit POST refresh action.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['ajax'] ?? '') === 'count') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['ok' => true, 'count' => $svc->count(), 'counts' => $svc->counts()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    redfox_enforce_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'refresh') {
        $svc->sync();
        header('Location: notifications.php', true, 303);
        exit;
    }

    if ($action === 'all') {
        $svc->markAll();
        header('Location: notifications.php', true, 303);
        exit;
    }

    $rawId = trim((string)($_POST['id'] ?? ''));
    if (!preg_match('/^[1-9][0-9]{0,9}$/', $rawId)) {
        http_response_code(422);
        exit('شناسه اعلان نامعتبر است.');
    }
    $id = (int)$rawId;

    if ($action === 'mark') {
        $svc->mark($id);
        header('Location: notifications.php', true, 303);
        exit;
    }

    if ($action === 'open') {
        $q = $pdo->prepare('SELECT type FROM admin_notifications WHERE id=? LIMIT 1');
        $q->execute([$id]);
        $type = (string)$q->fetchColumn();
        $destinations = [
            'receipt' => 'payment.php',
            'agent_request' => 'agents.php',
            'support' => 'support_inbox.php',
            'cancel_service' => 'cancelService.php',
            'deposit' => 'reseller_settlements.php',
            'payment_reconcile' => 'payment_effects.php',
            'service_reconcile' => 'service_operations.php',
            'message_reconcile' => 'reseller_security.php?tab=reconcile',
        ];
        if (!isset($destinations[$type])) {
            http_response_code(404);
            exit('اعلان یافت نشد.');
        }
        $svc->mark($id);
        header('Location: ' . $destinations[$type], true, 303);
        exit;
    }

    http_response_code(400);
    exit('عملیات ناشناخته است.');
}

$rows = $pdo->query(
    'SELECT * FROM admin_notifications ORDER BY is_read ASC,created_at DESC LIMIT 500'
)->fetchAll(PDO::FETCH_ASSOC);
$labels = [
    'receipt' => 'رسید و پرداخت',
    'agent_request' => 'درخواست نمایندگی',
    'support' => 'پشتیبانی',
    'cancel_service' => 'حذف سرویس',
    'deposit' => 'واریز نماینده',
    'payment_reconcile' => 'تطبیق پرداخت',
    'service_reconcile' => 'تطبیق سرویس',
    'message_reconcile' => 'تطبیق پیام',
];
$csrf = htmlspecialchars(rx_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>مرکز اعلان‌ها</title>
    <link rel="stylesheet" href="css/theme.css">
</head>
<body>
<section id="container">
    <?php include 'header.php'; ?>
    <section id="main-content">
        <div class="wrapper">
            <div class="page-head">
                <h1 class="page-head__title">مرکز اعلان‌های مدیریت</h1>
                <div class="page-head__sub">تمام رسیدها، درخواست‌ها، پیام‌های پشتیبانی و عملیات نیازمند بررسی در یک صفحه</div>
            </div>
            <div class="card">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                    <form method="post">
                        <input type="hidden" name="rx_csrf_token" value="<?=$csrf?>">
                        <input type="hidden" name="action" value="refresh">
                        <button class="btn">به‌روزرسانی فهرست</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="rx_csrf_token" value="<?=$csrf?>">
                        <input type="hidden" name="action" value="all">
                        <button class="btn btn-primary">خواندن همه اعلان‌ها</button>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="app-table">
                        <tr><th>وضعیت</th><th>بخش</th><th>عنوان</th><th>جزئیات</th><th>زمان شمسی</th><th></th></tr>
                        <?php foreach ($rows as $row): ?>
                            <tr style="<?=$row['is_read'] ? 'opacity:.58' : 'font-weight:700'?>">
                                <td><?=$row['is_read'] ? 'خوانده‌شده' : '🔴 جدید'?></td>
                                <td><?=htmlspecialchars($labels[$row['type']] ?? $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></td>
                                <td><?=htmlspecialchars((string)$row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></td>
                                <td><?=htmlspecialchars((string)($row['body'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></td>
                                <td><?=rx_fa_date((int)$row['created_at'])?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="rx_csrf_token" value="<?=$csrf?>">
                                        <input type="hidden" name="action" value="open">
                                        <input type="hidden" name="id" value="<?=(int)$row['id']?>">
                                        <button class="btn btn-sm">مشاهده بخش</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </section>
</section>
</body>
</html>
