<?php
declare(strict_types=1);

require_once __DIR__ . '/Security.php';

/**
 * Return a deliberately allow-listed, secret-free explanation for a Telegram
 * webhook/API failure. The upstream description is used only for matching and
 * hashing; it is never returned verbatim.
 */
function redfox_telegram_webhook_error($response, string $transportError = ''): array
{
    $apiCode = is_array($response) ? (int)($response['error_code'] ?? 0) : 0;
    $description = is_array($response) ? (string)($response['upstream_description'] ?? $response['description'] ?? '') : '';
    $sample = strtolower(substr(preg_replace('/\s+/', ' ', strip_tags($description . ' ' . $transportError)) ?: '', 0, 1000));
    $fingerprint = substr(hash('sha256', $apiCode . '|' . $description . '|' . $transportError), 0, 16);

    $result = [
        'code' => 'TG-WH-UNKNOWN',
        'message' => 'Telegram درخواست Webhook را نپذیرفت، اما علت آن در دسته‌های امن شناخته‌شده نبود.',
        'action' => 'پس از بررسی گزارش خطای سرور با شناسهٔ اعلام‌شده، دوباره «تعمیر Webhook» را اجرا کنید.',
        'retryable' => false,
        'api_code' => $apiCode,
        'fingerprint' => $fingerprint,
    ];

    $set = static function (string $code, string $message, string $action, bool $retryable = false) use (&$result): void {
        $result['code'] = $code;
        $result['message'] = $message;
        $result['action'] = $action;
        $result['retryable'] = $retryable;
    };

    if ($sample === '' && $apiCode === 0) {
        $set(
            'TG-WH-TRANSPORT',
            'ارتباط خروجی سرور با Telegram برقرار نشد یا پاسخ معتبر دریافت نشد.',
            'DNS و فایروال خروجی هاست برای api.telegram.org و دسترسی HTTPS پورت 443 را بررسی کنید.',
            true
        );
    } elseif ($apiCode === 401 || preg_match('/unauthorized|invalid (bot )?token|token is invalid/', $sample)) {
        $set(
            'TG-WH-AUTH',
            'Telegram توکن ربات را نامعتبر یا لغوشده اعلام کرد.',
            'توکن جاری را در BotFather بررسی و در پنل جایگزین کنید.'
        );
    } elseif (preg_match('/failed to resolve host|name or service not known|no address associated|nodename nor servname|dns/', $sample)) {
        $set(
            'TG-WH-DNS',
            'Telegram نتوانست نام دامنهٔ Webhook را در DNS عمومی resolve کند.',
            'رکورد عمومی A یا AAAA دامنه را اصلاح کنید، تا انتشار DNS صبر کنید و سپس تعمیر Webhook را بزنید.',
            true
        );
    } elseif (preg_match('/ssl|tls|certificate|handshake|self[ -]?signed|cert/', $sample)) {
        $set(
            'TG-WH-TLS',
            'Telegram اعتبار گواهی TLS یا handshake دامنهٔ Webhook را نپذیرفت.',
            'زنجیرهٔ کامل گواهی، نام دامنه، تاریخ اعتبار و SNI را با یک گواهی عمومی معتبر اصلاح کنید.'
        );
    } elseif (preg_match('/port|unsupported.*(?:80|88|443|8443)|only ports/', $sample)) {
        $set(
            'TG-WH-PORT',
            'پورت URL وب‌هوک برای Telegram مجاز نیست.',
            'از HTTPS روی یکی از پورت‌های 443، 80، 88 یا 8443 استفاده کنید.'
        );
    } elseif (preg_match('/ip address|wrong ip|failed to resolve.*ip|private (?:address|ip)|host.*local/', $sample)) {
        $set(
            'TG-WH-IP',
            'دامنهٔ Webhook به IP نامعتبر، خصوصی یا غیرقابل‌دسترسی از اینترنت اشاره می‌کند.',
            'DNS دامنه را به IP عمومی همین هاست متصل و رکورد اشتباه یا قدیمی را حذف کنید.'
        );
    } elseif (preg_match('/connection refused|failed to connect|connect.*timed out|connection timed out|network is unreachable|connection reset/', $sample)) {
        $set(
            'TG-WH-CONNECT',
            'Telegram نتوانست به سرویس HTTPS دامنهٔ Webhook متصل شود.',
            'بازبودن پورت، VirtualHost، فایروال/WAF و دسترسی عمومی مسیر index.php را بررسی کنید.',
            true
        );
    } elseif (preg_match('/secret[_ -]?token|secret token/', $sample)) {
        $set(
            'TG-WH-SECRET',
            'Telegram قالب secret_token وب‌هوک را نپذیرفت.',
            'از دکمهٔ تعمیر استفاده کنید تا secret استاندارد جدید ساخته و ثبت شود.'
        );
    } elseif (preg_match('/too many redirects|redirect loop/', $sample)) {
        $set(
            'TG-WH-REDIRECT',
            'مسیر Webhook وارد redirect غیرمجاز یا حلقهٔ تغییر مسیر شده است.',
            'مسیر index.php را مستقیم و بدون redirect احراز هویت، www یا HTTP در دسترس قرار دهید.'
        );
    } elseif (preg_match('/wrong response.*webhook|webhook.*(?:returned|response).*(?:401|403|404|410|429|500|502|503|504)|response.*(?:401|403|404|410|429|500|502|503|504)/', $sample)) {
        $set(
            'TG-WH-HTTP',
            'endpoint وب‌هوک در پاسخ به Telegram کد HTTP ناموفق برگردانده است.',
            'وجود index.php، تنظیم secret، قوانین WAF/Cloudflare، دسترسی POST و سلامت PHP/دیتابیس را بررسی کنید.'
        );
    } elseif (preg_match('/webhook.*(?:url|https)|url.*(?:invalid|bad|wrong)|https url must be provided|bad webhook/', $sample)) {
        $set(
            'TG-WH-URL',
            'Telegram آدرس عمومی Webhook را نامعتبر یا غیرقابل‌استفاده اعلام کرد.',
            'دامنه و مسیر نصب را بررسی کنید؛ URL باید HTTPS عمومی و به index.php نصب جاری منتهی شود.'
        );
    } elseif ($apiCode === 429 || preg_match('/too many requests|retry after|rate.?limit/', $sample)) {
        $set(
            'TG-WH-RATE',
            'Telegram به‌دلیل محدودیت موقت تعداد درخواست‌ها عملیات را رد کرد.',
            'چند دقیقه صبر کنید و سپس تعمیر Webhook را دوباره اجرا کنید.',
            true
        );
    } elseif ($apiCode >= 500 || preg_match('/bad gateway|service unavailable|internal server error|temporarily unavailable/', $sample)) {
        $set(
            'TG-WH-UPSTREAM',
            'سرویس Telegram موقتاً در دسترس نبود.',
            'چند دقیقه بعد عملیات تعمیر Webhook را تکرار کنید.',
            true
        );
    } elseif ($apiCode === 400 || preg_match('/bad request/', $sample)) {
        $set(
            'TG-WH-REJECTED',
            'Telegram درخواست setWebhook را با خطای Bad Request رد کرد.',
            'URL، DNS و TLS را بررسی و سپس تعمیر را تکرار کنید؛ شناسهٔ خطا برای تطبیق با گزارش سرور ارائه شده است.'
        );
    }

    return $result;
}

function redfox_telegram_webhook_error_text(array $error): string
{
    $api = (int)($error['api_code'] ?? 0);
    $suffix = 'کد ' . (string)($error['code'] ?? 'TG-WH-UNKNOWN');
    if ($api > 0) $suffix .= ' / Telegram ' . $api;
    $suffix .= ' / شناسه ' . (string)($error['fingerprint'] ?? 'unknown');
    return (string)($error['message'] ?? 'خطای Webhook') . "\n" .
        (string)($error['action'] ?? '') . "\n" . $suffix;
}

/** Validate Telegram-specific URL constraints and the public DNS policy. */
function redfox_validate_telegram_webhook_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'code' => 'TG-WH-URL', 'message' => 'URL وب‌هوک از نظر ساختار معتبر نیست.'];
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        return ['ok' => false, 'code' => 'TG-WH-URL', 'message' => 'URL وب‌هوک باید HTTPS و بدون userinfo، query یا fragment باشد.'];
    }
    $port = isset($parts['port']) ? (int)$parts['port'] : 443;
    if (!in_array($port, [80, 88, 443, 8443], true)) {
        return ['ok' => false, 'code' => 'TG-WH-PORT', 'message' => 'پورت URL وب‌هوک در فهرست مجاز Telegram نیست.'];
    }
    $path = (string)($parts['path'] ?? '');
    if ($path === '' || !str_ends_with($path, '/index.php')) {
        return ['ok' => false, 'code' => 'TG-WH-PATH', 'message' => 'مسیر Webhook باید به index.php نصب جاری منتهی شود.'];
    }
    $policy = redfox_outbound_url_policy($url, false, false);
    if (empty($policy['ok'])) {
        $reason = (string)($policy['error'] ?? 'unknown');
        $map = [
            'dns_resolution_failed' => ['TG-WH-DNS', 'دامنهٔ Webhook در DNS عمومی resolve نشد.'],
            'destination_not_allowed' => ['TG-WH-IP', 'دامنهٔ Webhook به IP خصوصی یا رزروشده اشاره می‌کند.'],
            'host_not_allowed' => ['TG-WH-IP', 'مقصد محلی برای Webhook عمومی مجاز نیست.'],
            'invalid_port' => ['TG-WH-PORT', 'پورت URL وب‌هوک معتبر نیست.'],
        ];
        $mapped = $map[$reason] ?? ['TG-WH-URL', 'URL وب‌هوک با سیاست مقصد عمومی سازگار نیست.'];
        return ['ok' => false, 'code' => $mapped[0], 'message' => $mapped[1], 'policy_error' => $reason];
    }
    return [
        'ok' => true,
        'code' => 'TG-WH-PREFLIGHT-OK',
        'message' => 'ساختار URL، پورت و DNS عمومی معتبر است.',
        'host' => (string)$policy['host'],
        'port' => (int)$policy['port'],
        'addresses' => (array)$policy['addresses'],
    ];
}

/**
 * Probe TLS and the endpoint without sending an update. HTTP 405 is expected
 * because the production endpoint is POST-only; any real HTTP response proves
 * DNS pinning, TCP, TLS and VirtualHost routing from this host.
 */
function redfox_probe_telegram_webhook_endpoint(string $url, int $timeout = 8): array
{
    $validation = redfox_validate_telegram_webhook_url($url);
    if (empty($validation['ok'])) return $validation;
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 'TG-WH-CURL', 'message' => 'افزونه cURL برای آزمون endpoint در دسترس نیست.'];
    }
    $ch = curl_init($url);
    if ($ch === false) return ['ok' => false, 'code' => 'TG-WH-CURL', 'message' => 'ساخت درخواست آزمون endpoint ناموفق بود.'];
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeout)),
        CURLOPT_TIMEOUT => max(1, min(15, $timeout)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'RedFox-Webhook-Preflight/1.0',
    ]);
    $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
    if (empty($policy['ok'])) {
        curl_close($ch);
        return ['ok' => false, 'code' => 'TG-WH-DNS', 'message' => 'سیاست DNS عمومی endpoint را رد کرد.'];
    }
    $executed = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = (int)curl_errno($ch);
    $error = strtolower((string)curl_error($ch));
    curl_close($ch);
    if ($executed === false || $errno !== 0 || $status === 0) {
        $classified = redfox_telegram_webhook_error(null, $error);
        if (str_contains($error, 'ssl') || str_contains($error, 'certificate')) {
            $classified['code'] = 'TG-WH-TLS';
            $classified['message'] = 'آزمون مستقیم TLS دامنه ناموفق بود.';
            $classified['action'] = 'گواهی، زنجیرهٔ میانی، SNI و نام دامنه را بررسی کنید.';
        }
        $classified['ok'] = false;
        $classified['http'] = $status;
        return $classified;
    }
    // The main endpoint returns 405 to HEAD and reseller endpoints return 401
    // without Telegram's secret. Redirects, WAF denial, missing routes, rate
    // limits and server errors are not considered a healthy public endpoint.
    if (!in_array($status, [200, 204, 400, 401, 405, 415, 422], true)) {
        return [
            'ok' => false,
            'code' => ($status >= 300 && $status < 400) ? 'TG-WH-REDIRECT' : 'TG-WH-HTTP',
            'message' => 'endpoint وب‌هوک پاسخ HTTP قابل‌قبول برای آزمون عمومی برنگرداند.',
            'action' => 'مسیر، WAF/Cloudflare، redirect و سلامت PHP را بررسی کنید.',
            'retryable' => in_array($status, [429, 500, 502, 503, 504], true),
            'http' => $status,
        ];
    }
    return ['ok' => true, 'code' => 'TG-WH-ENDPOINT-OK', 'message' => 'اتصال HTTPS و endpoint از این سرور قابل دسترس است.', 'http' => $status];
}

/**
 * Shared bounded setWebhook + compatibility fallback + getWebhookInfo check.
 * The callback receives ($method, $parameters). Raw descriptions are consumed
 * only by the allow-list classifier and never returned by this coordinator.
 */
function redfox_apply_telegram_webhook(
    callable $request,
    string $url,
    string $secret,
    bool $performProbe = true
): array {
    $preflight = redfox_validate_telegram_webhook_url($url);
    if (empty($preflight['ok'])) return $preflight;
    if (!preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
        return redfox_telegram_webhook_error(['upstream_description' => 'secret token invalid']);
    }
    if ($performProbe) {
        $probe = redfox_probe_telegram_webhook_endpoint($url);
        // Shared hosts can lack NAT loopback while Telegram still reaches the
        // public address. Continue only for that connect class; DNS/TLS/HTTP
        // failures remain fail-closed.
        if (empty($probe['ok']) && (string)($probe['code'] ?? '') !== 'TG-WH-CONNECT') return $probe;
    }

    $safeCall = static function (string $method, array $parameters) use ($request): array {
        try {
            $response = $request($method, $parameters);
            return is_array($response) ? $response : ['ok' => false];
        } catch (Throwable $error) {
            error_log('[webhook] Telegram request exception ref=' . redfox_exception_fingerprint($error));
            return ['ok' => false];
        }
    };
    $parameters = [
        'url' => $url,
        'secret_token' => $secret,
        'drop_pending_updates' => 'false',
        'allowed_updates' => json_encode(['message', 'callback_query', 'pre_checkout_query'], JSON_UNESCAPED_SLASHES),
    ];
    $response = $safeCall('setWebhook', $parameters);
    $accepted = !empty($response['ok']);
    $diagnostic = $accepted ? ['code' => 'TG-WH-OK', 'message' => 'Webhook پذیرفته شد.', 'action' => '', 'retryable' => false]
        : redfox_telegram_webhook_error(['error_code' => (int)($response['error_code'] ?? 0), 'upstream_description' => (string)($response['description'] ?? '')]);

    if (!$accepted && !empty($diagnostic['retryable'])) {
        usleep(250000);
        $response = $safeCall('setWebhook', $parameters);
        $accepted = !empty($response['ok']);
        if (!$accepted) {
            $diagnostic = redfox_telegram_webhook_error(['error_code' => (int)($response['error_code'] ?? 0), 'upstream_description' => (string)($response['description'] ?? '')]);
        }
    }
    if (!$accepted) {
        // Exactly one minimal compatibility fallback; secret authentication stays mandatory.
        $response = $safeCall('setWebhook', ['url' => $url, 'secret_token' => $secret]);
        $accepted = !empty($response['ok']);
        if (!$accepted) {
            $diagnostic = redfox_telegram_webhook_error(['error_code' => (int)($response['error_code'] ?? 0), 'upstream_description' => (string)($response['description'] ?? '')]);
        }
    }

    // Always query independently: this also recovers from a lost success response.
    $info = $safeCall('getWebhookInfo', []);
    $actual = rtrim((string)($info['result']['url'] ?? ''), '/');
    $verified = !empty($info['ok']) && $actual !== '' && hash_equals(rtrim($url, '/'), $actual)
        && empty($info['result']['has_custom_certificate']);
    if ($accepted || $verified) {
        return ['ok' => true, 'code' => 'TG-WH-OK', 'message' => 'Webhook ثبت و بررسی شد.',
            'action' => '', 'retryable' => false, 'url' => $url, 'verified' => $verified];
    }
    if (!empty($info['result']['last_error_message'])) {
        $diagnostic = redfox_telegram_webhook_error([
            'upstream_description' => (string)$info['result']['last_error_message'],
        ]);
    }
    return $diagnostic;
}

function redfox_webhook_status_write(PDO $pdo, string $status, string $code = ''): bool
{
    if (!in_array($status, ['active', 'pending', 'disabled', 'unknown'], true)) $status = 'unknown';
    $code = preg_match('/^[A-Z0-9_-]{0,64}$/', $code) ? $code : 'TG-WH-UNKNOWN';
    try {
        $stmt = $pdo->prepare('UPDATE setting SET webhook_setup_status=?,webhook_last_error_code=?,webhook_last_attempt_at=?');
        return $stmt->execute([$status, $code, time()]);
    } catch (Throwable $error) {
        error_log('[webhook-status] write failed ' . redfox_exception_fingerprint($error));
        return false;
    }
}

function redfox_webhook_status_read(PDO $pdo): array
{
    try {
        $row = $pdo->query('SELECT webhook_setup_status,webhook_last_error_code,webhook_last_attempt_at FROM setting LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) return $row;
    } catch (Throwable $error) {
        error_log('[webhook-status] read failed ' . redfox_exception_fingerprint($error));
    }
    return ['webhook_setup_status' => 'unknown', 'webhook_last_error_code' => '', 'webhook_last_attempt_at' => 0];
}
