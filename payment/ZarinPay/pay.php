<?php
declare(strict_types=1);

/**
 * Legacy unauthenticated payment-creation endpoint.
 *
 * Payment creation now happens only inside authenticated bot/Mini App flows.
 * Keeping the former GET endpoint would allow third parties to trigger provider
 * invoice creation for guessed order identifiers and would expose transaction
 * metadata in URLs and access logs.
 */
ini_set('display_errors', '0');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');
http_response_code(410);
echo 'این مسیر قدیمی غیرفعال است؛ پرداخت را از داخل ربات یا مینی‌اپ آغاز کنید.';
