<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('POST only');
}
// config.php already enforces CSRF and revalidates the current admin session.
redfox_end_admin_session();
header('Cache-Control: no-store');
header('Location: login.php', true, 303);
exit;
