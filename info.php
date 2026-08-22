<?php
declare(strict_types=1);
// Legacy vendor-control/update endpoint removed. Updates are handled only by
// the authenticated, signed updater in panel/update.php.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode(['ok' => false, 'error' => 'endpoint removed'], JSON_UNESCAPED_UNICODE);
