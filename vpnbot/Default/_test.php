<?php
declare(strict_types=1);
// Public reseller-bot diagnostics exposed filesystem paths, database counts,
// webhook details and error logs. The endpoint is intentionally disabled.
http_response_code(404);
header('Cache-Control: no-store');
exit;
