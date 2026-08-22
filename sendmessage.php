<?php
declare(strict_types=1);
// Legacy GET/IP-authorized message relay removed. Internal bot messages must
// use application services after authenticated authorization checks.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode(['ok' => false, 'error' => 'endpoint removed'], JSON_UNESCAPED_UNICODE);
