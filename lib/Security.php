<?php
/**
 * Red Fox security primitives.
 * This file has no database dependency and is safe to load before config.php.
 */

declare(strict_types=1);

if (!function_exists('rx_env')) require_once __DIR__ . '/HostingSecrets.php';

if (!function_exists('redfox_ip_in_cidr')) {
    function redfox_ip_in_cidr(string $ip, string $cidr): bool
    {
        [$network, $prefixRaw] = array_pad(explode('/', trim($cidr), 2), 2, null);
        $ipBinary = @inet_pton($ip); $networkBinary = @inet_pton((string)$network);
        if (!is_string($ipBinary) || !is_string($networkBinary) || strlen($ipBinary) !== strlen($networkBinary)) return false;
        $bits = strlen($ipBinary) * 8;
        $prefix = $prefixRaw === null ? $bits : filter_var($prefixRaw, FILTER_VALIDATE_INT);
        if (!is_int($prefix) || $prefix < 0 || $prefix > $bits) return false;
        $bytes = intdiv($prefix, 8); $remaining = $prefix % 8;
        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) return false;
        if ($remaining === 0) return true;
        $mask = (0xff << (8 - $remaining)) & 0xff;
        return (ord($ipBinary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }
}

if (!function_exists('redfox_trusted_proxy_cidrs')) {
    function redfox_trusted_proxy_cidrs(): array
    {
        static $cidrs = null;
        if (is_array($cidrs)) return $cidrs;
        $cidrs = [];
        foreach (explode(',', trim((string)(rx_env('REDFOX_TRUSTED_PROXY_CIDRS') ?: ''))) as $cidr) {
            $cidr = trim($cidr);
            if ($cidr !== '') $cidrs[] = $cidr;
        }
        return $cidrs;
    }
}

if (!function_exists('redfox_is_trusted_proxy')) {
    function redfox_is_trusted_proxy(?string $ip = null): bool
    {
        $ip = trim((string)($ip ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
        foreach (redfox_trusted_proxy_cidrs() as $cidr) if (redfox_ip_in_cidr($ip, $cidr)) return true;
        return false;
    }
}

if (!function_exists('redfox_client_ip')) {
    function redfox_client_ip(): string
    {
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remote, FILTER_VALIDATE_IP)) return '0.0.0.0';
        if (!redfox_is_trusted_proxy($remote)) return $remote;
        $chain = array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        $chain[] = $remote;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = $chain[$i];
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) continue;
            if (!redfox_is_trusted_proxy($candidate)) return $candidate;
        }
        $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        return filter_var($cf, FILTER_VALIDATE_IP) ? $cf : $remote;
    }
}

if (!function_exists('redfox_is_https')) {
    function redfox_is_https(): bool
    {
        if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '') return true;
        if (!redfox_is_trusted_proxy()) return false;
        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $forwardedProto === 'https';
    }
}

if (!function_exists('redfox_normalize_request_host')) {
    /** Return a canonical Host header authority, or an empty string if invalid. */
    function redfox_normalize_request_host(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 260
            || preg_match('/[\x00-\x20\x7f,;]/', $value)
            || str_contains($value, '/') || str_contains($value, '\\')
            || str_contains($value, '@') || str_contains($value, '?') || str_contains($value, '#')) {
            return '';
        }
        $parts = parse_url('http://' . $value);
        if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        $parsedHost = strtolower(rtrim((string)$parts['host'], '.'));
        $bracketed = str_starts_with($parsedHost, '[') && str_ends_with($parsedHost, ']');
        $host = $bracketed ? substr($parsedHost, 1, -1) : $parsedHost;
        $validIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $valid = $validIp || (!$bracketed
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false);
        if (!$valid) return '';
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) return '';
        $rendered = str_contains($host, ':') ? '[' . $host . ']' : $host;
        return $rendered . ($port !== null ? ':' . $port : '');
    }
}

if (!function_exists('redfox_normalize_domain')) {
    /**
     * Return a validated HTTPS base address as host[:port][/install/path].
     * Historical deployments used the name "domain" even when the application
     * lived below the document root, so a safe path is intentionally preserved.
     */
    function redfox_normalize_domain(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value) || str_contains($value, '\\')) return '';
        if (!preg_match('#^https?://#i', $value)) $value = 'https://' . $value;
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return '';
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return '';
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '') return '';
        $validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
            || (strlen($host) <= 253 && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host));
        if (!$validHost) return '';
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) return '';

        $path = (string)($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            if (!str_starts_with($path, '/') || strlen($path) > 512 || str_contains($path, '%')) return '';
            if (preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) || !preg_match("#^/[A-Za-z0-9._~!\$&'()*+,;=:@/-]+$#", $path)) return '';
            $path = '/' . implode('/', array_values(array_filter(explode('/', trim($path, '/')), static fn(string $part): bool => $part !== '')));
        } else {
            $path = '';
        }

        if (str_contains($host, ':')) $host = '[' . $host . ']';
        return $host . ($port !== null && $port !== 443 ? ':' . $port : '') . $path;
    }
}

if (!function_exists('redfox_configured_base_url')) {
    /** Canonical public application URL, including an optional install path. */
    function redfox_configured_base_url(): string
    {
        $configured = (string)(rx_env('REDFOX_DOMAIN') ?: ($GLOBALS['domainhosts'] ?? ''));
        $base = redfox_normalize_domain($configured);
        return $base === '' ? '' : 'https://' . $base;
    }
}

if (!function_exists('redfox_configured_origin')) {
    /** Browser origin (scheme + authority only); never includes the install path. */
    function redfox_configured_origin(): string
    {
        $baseUrl = redfox_configured_base_url();
        if ($baseUrl === '') return '';
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['host'])) return '';
        $host = strtolower((string)$parts['host']);
        if (str_contains($host, ':')) $host = '[' . $host . ']';
        $port = isset($parts['port']) && (int)$parts['port'] !== 443 ? ':' . (int)$parts['port'] : '';
        return 'https://' . $host . $port;
    }
}

if (!function_exists('redfox_secure_session_start')) {
    function redfox_secure_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (headers_sent()) { @session_start(); return; }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.cookie_secure', redfox_is_https() ? '1' : '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => redfox_is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

if (!function_exists('redfox_admin_session_version')) {
    /** Bind an administrator session to the current database credential and role. */
    function redfox_admin_session_version(array $admin): string
    {
        return hash('sha256',
            (string)($admin['id_admin'] ?? '') . "\n" .
            (string)($admin['username'] ?? '') . "\n" .
            (string)($admin['password'] ?? '') . "\n" .
            (string)($admin['rule'] ?? '')
        );
    }
}

if (!function_exists('redfox_bind_admin_session')) {
    function redfox_bind_admin_session(array $admin): void
    {
        $_SESSION['user'] = (string)($admin['username'] ?? '');
        $_SESSION['rx_admin_id'] = (string)($admin['id_admin'] ?? '');
        $_SESSION['rx_admin_auth_version'] = redfox_admin_session_version($admin);
    }
}

if (!function_exists('redfox_end_admin_session')) {
    function redfox_end_admin_session(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => (string)($params['samesite'] ?? 'Strict'),
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}

if (!function_exists('redfox_require_current_admin')) {
    /** Revalidate the session against the admin table before any panel action. */
    function redfox_require_current_admin(PDO $pdo, string $loginPath = 'login.php'): array
    {
        $username = $_SESSION['user'] ?? null;
        $version = $_SESSION['rx_admin_auth_version'] ?? null;
        $adminId = $_SESSION['rx_admin_id'] ?? null;
        $invalid = !is_string($username) || $username === '' || strlen($username) > 190
            || !is_string($version) || !preg_match('/^[a-f0-9]{64}$/', $version)
            || !is_string($adminId) || $adminId === '' || strlen($adminId) > 190;
        $admin = false;
        if (!$invalid) {
            try {
                $stmt = $pdo->prepare('SELECT id_admin,username,password,rule FROM admin WHERE username=? AND id_admin=? LIMIT 1');
                $stmt->execute([$username, $adminId]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            } catch (Throwable $e) {
                http_response_code(503);
                header('Cache-Control: no-store');
                exit('Administrator authentication is temporarily unavailable.');
            }
            $invalid = !is_array($admin) || !hash_equals(redfox_admin_session_version($admin), $version);
        }
        if ($invalid) {
            redfox_end_admin_session();
            header('Cache-Control: no-store');
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
            if ($method !== 'GET' || str_contains($accept, 'application/json')) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                exit('{"ok":false,"error":"administrator authentication required"}');
            }
            header('Location: ' . $loginPath, true, 302);
            exit;
        }
        return $admin;
    }
}

if (!function_exists('redfox_tls_verify_enabled')) {
    /** TLS certificate verification is mandatory and cannot be disabled at runtime. */
    function redfox_tls_verify_enabled(): bool
    {
        return true;
    }
}

if (!function_exists('redfox_private_panel_endpoints_allowed')) {
    /** Private/RFC1918 panel targets require an explicit hosting-level opt-in. */
    function redfox_private_panel_endpoints_allowed(): bool
    {
        if (defined('REDFOX_ALLOW_PRIVATE_PANEL_ENDPOINTS')) {
            return REDFOX_ALLOW_PRIVATE_PANEL_ENDPOINTS === true;
        }
        return in_array(strtolower(trim((string)rx_env('REDFOX_ALLOW_PRIVATE_PANEL_ENDPOINTS'))), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('redfox_outbound_ip_allowed')) {
    function redfox_outbound_ip_allowed(string $ip, bool $allowPrivate = false): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) return true;
        if (!$allowPrivate) return false;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) return false;
            $long = (int)sprintf('%u', $long);
            $in = static function (int $network, int $mask) use ($long): bool { return ($long & $mask) === ($network & $mask); };
            return $in((int)sprintf('%u', ip2long('10.0.0.0')), (int)sprintf('%u', ip2long('255.0.0.0')))
                || $in((int)sprintf('%u', ip2long('172.16.0.0')), (int)sprintf('%u', ip2long('255.240.0.0')))
                || $in((int)sprintf('%u', ip2long('192.168.0.0')), (int)sprintf('%u', ip2long('255.255.0.0')));
        }
        $normalized = strtolower($ip);
        return str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd');
    }
}

if (!function_exists('redfox_outbound_url_policy')) {
    /**
     * Validate and resolve an outbound URL before cURL sees it. Loopback,
     * link-local, metadata, multicast and reserved destinations are always denied.
     */
    function redfox_outbound_url_policy(string $url, bool $allowPrivate = false, bool $allowHttp = false): array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return ['ok' => false, 'error' => 'invalid_url_components'];
        }
        $scheme = strtolower((string)$parts['scheme']);
        if ($scheme !== 'https' && !($allowHttp && $scheme === 'http')) return ['ok' => false, 'error' => 'scheme_not_allowed'];
        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return ['ok' => false, 'error' => 'host_not_allowed'];
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) return ['ok' => false, 'error' => 'invalid_port'];
        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses[] = $host;
        } else {
            if (function_exists('dns_get_record')) {
                $records = @dns_get_record($host, DNS_A | DNS_AAAA);
                if (is_array($records)) {
                    foreach ($records as $record) {
                        $candidate = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                        if ($candidate !== '') $addresses[] = $candidate;
                    }
                }
            }
            if (!$addresses) {
                $fallback = @gethostbynamel($host);
                if (is_array($fallback)) $addresses = array_merge($addresses, $fallback);
            }
        }
        $addresses = array_values(array_unique(array_filter($addresses, static fn($ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)));
        if (!$addresses) return ['ok' => false, 'error' => 'dns_resolution_failed'];
        foreach ($addresses as $address) {
            if (!redfox_outbound_ip_allowed($address, $allowPrivate)) return ['ok' => false, 'error' => 'destination_not_allowed'];
        }
        return ['ok' => true, 'url' => $url, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'addresses' => $addresses];
    }
}

if (!function_exists('redfox_apply_curl_url_policy')) {
    /** Validate, pin DNS and install cURL protocol/TLS controls. */
    function redfox_apply_curl_url_policy($curl, string $url, bool $allowPrivate = false, bool $allowHttp = false): array
    {
        $policy = redfox_outbound_url_policy($url, $allowPrivate, $allowHttp);
        if (empty($policy['ok'])) {
            // Defense in depth: even if a caller forgets to inspect this return
            // value, the rejected destination is replaced with a non-resolving
            // reserved hostname before curl_exec() can run.
            if (is_resource($curl) || is_object($curl)) {
                @curl_setopt($curl, CURLOPT_URL, 'https://redfox-policy-block.invalid/');
                @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
            }
            return $policy;
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        if (defined('CURLOPT_PROTOCOLS')) curl_setopt($curl, CURLOPT_PROTOCOLS, $allowHttp ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS);
        if (defined('CURLOPT_REDIR_PROTOCOLS')) curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, $allowHttp ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        // CURLOPT_RESOLVE entries sharing the same host:port do not accumulate:
        // libcurl discards the previous address when the next entry is added.
        // With the old per-address loop an AAAA record commonly replaced the A
        // record, forcing IPv6 and breaking Telegram/install traffic on hosts
        // without IPv6 connectivity. Since curl 7.59.0 one entry may safely
        // contain a comma-separated address list. Older versions receive one
        // public IPv4 address (or IPv6 only when no IPv4 exists).
        $ipv4 = [];
        $ipv6 = [];
        foreach ($policy['addresses'] as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipv4[] = $address;
            } elseif (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipv6[] = '[' . $address . ']';
            }
        }
        $targets = array_merge($ipv4, $ipv6);
        if ($targets) {
            $curlVersion = function_exists('curl_version') ? curl_version() : [];
            $versionNumber = is_array($curlVersion) ? (int)($curlVersion['version_number'] ?? 0) : 0;
            $targetList = $versionNumber >= 0x073B00 ? implode(',', $targets) : $targets[0];
            curl_setopt($curl, CURLOPT_RESOLVE, [
                $policy['host'] . ':' . $policy['port'] . ':' . $targetList,
            ]);
        }
        return $policy;
    }
}

if (!function_exists('redfox_apply_panel_curl_url_policy')) {
    /** Apply the shared policy to a configured panel endpoint. */
    function redfox_apply_panel_curl_url_policy($curl, string $url): array
    {
        $allowPrivate = redfox_private_panel_endpoints_allowed();
        return redfox_apply_curl_url_policy($curl, $url, $allowPrivate, $allowPrivate);
    }
}

if (!function_exists('redfox_fetch_public_https')) {
    /** Bounded HTTPS GET for public, non-redirecting application fetches. */
    function redfox_fetch_public_https(string $url, int $maxBytes = 2097152, int $timeoutSeconds = 10, array $headers = []): array
    {
        if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'curl_unavailable', 'body' => ''];
        $maxBytes = max(1, min($maxBytes, 8388608));
        $timeoutSeconds = max(1, min($timeoutSeconds, 30));
        $ch = curl_init($url);
        if ($ch === false) return ['ok' => false, 'error' => 'curl_init_failed', 'body' => ''];
        $body = '';
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'RedFox-SecureFetcher/1.0',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) { $tooLarge = true; return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
        if (empty($policy['ok'])) { curl_close($ch); return ['ok' => false, 'error' => (string)($policy['error'] ?? 'endpoint_blocked'), 'body' => '']; }
        $executed = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower(trim((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
        $errno = (int)curl_errno($ch);
        curl_close($ch);
        if ($tooLarge) return ['ok' => false, 'error' => 'response_too_large', 'body' => '', 'http' => $status];
        if ($executed === false || $errno !== 0) return ['ok' => false, 'error' => 'transport_error', 'body' => '', 'http' => $status];
        if ($status < 200 || $status >= 300) return ['ok' => false, 'error' => 'http_error', 'body' => '', 'http' => $status];
        return ['ok' => true, 'error' => '', 'body' => $body, 'http' => $status, 'content_type' => $contentType];
    }
}

if (!function_exists('redfox_security_headers')) {
    function redfox_security_headers(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store, private');
        if (redfox_is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (!function_exists('redfox_csrf_token')) {
    function redfox_csrf_token(): string
    {
        redfox_secure_session_start();
        if (empty($_SESSION['rx_csrf_token']) || !is_string($_SESSION['rx_csrf_token'])) {
            $_SESSION['rx_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['rx_csrf_token'];
    }
}

if (!function_exists('redfox_csrf_field')) {
    function redfox_csrf_field(): string
    {
        return '<input type="hidden" name="rx_csrf_token" value="' . htmlspecialchars(redfox_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('redfox_request_origin_is_same_site')) {
    function redfox_request_origin_is_same_site(): bool
    {
        $source = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($source === '') $source = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($source === '') return false;
        $sourceParts = parse_url($source);
        if (!is_array($sourceParts)) return false;
        $sourceScheme = strtolower((string)($sourceParts['scheme'] ?? ''));
        $sourceHost = strtolower((string)($sourceParts['host'] ?? ''));
        $sourcePort = isset($sourceParts['port']) ? (int)$sourceParts['port'] : ($sourceScheme === 'https' ? 443 : 80);
        if ($sourceScheme !== 'https' || $sourceHost === '') return false;

        $expected = redfox_configured_origin();
        if ($expected === '') return false;
        $expectedParts = parse_url($expected);
        if (!is_array($expectedParts)) return false;
        $expectedHost = strtolower((string)($expectedParts['host'] ?? ''));
        $expectedPort = isset($expectedParts['port']) ? (int)$expectedParts['port'] : 443;
        return $expectedHost !== '' && $sourcePort === $expectedPort && hash_equals($expectedHost, $sourceHost);
    }
}

if (!function_exists('redfox_enforce_csrf')) {
    /** Require both a session-bound CSRF token and a same-origin browser source. */
    function redfox_enforce_csrf(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;
        $expected = redfox_csrf_token();
        $provided = trim((string)($_POST['rx_csrf_token'] ?? $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $validToken = $provided !== '' && hash_equals($expected, $provided);
        // Origin/Referer is defense in depth, not a substitute for a CSRF
        // token. Every state-changing panel request must carry the token.
        if (!$validToken || !redfox_request_origin_is_same_site()) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'error' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('redfox_remote_error_class')) {
    /**
     * Classify an untrusted upstream error using a deliberately small allowlist.
     * The original message is never returned, logged or rendered.
     */
    function redfox_remote_error_class($response): string
    {
        $values = [];
        if (is_string($response) || is_numeric($response)) {
            $values[] = (string)$response;
        } elseif (is_array($response)) {
            foreach (['error', 'errror', 'message', 'msg', 'detail', 'description'] as $field) {
                if (!isset($response[$field]) || (!is_string($response[$field]) && !is_numeric($response[$field]))) continue;
                $values[] = (string)$response[$field];
            }
        }
        $text = strtolower(trim(implode(' ', array_map(static function (string $value): string {
            $value = strip_tags($value);
            return substr(preg_replace('/\s+/', ' ', $value) ?: '', 0, 512);
        }, $values))));
        if ($text === '') return 'unknown';
        $rules = [
            'user_not_found'      => '/\b(user|account|inbound)\b.{0,32}\bnot[ _-]?found\b|\bnot[ _-]?found\b.{0,32}\b(user|account)\b/i',
            'invalid_credentials' => '/incorrect (username|password)|invalid (username|password|credentials)|username or password is incorrect|authentication failed|bad credentials|2fa|two-factor/i',
            'unauthorized'        => '/\bunauthori[sz]ed\b|\bforbidden\b|permission denied|access denied/i',
            'already_exists'      => '/already exists|duplicate (user|account|record)|\bconflict\b/i',
            'rate_limited'        => '/rate.?limit|too many requests|retry after/i',
            'quota_exceeded'      => '/quota|limit exceeded|insufficient (balance|credit|funds)/i',
            'timeout'             => '/timed? out|timeout/i',
            'connection_failed'   => '/connection refused|could not resolve|dns|network is unreachable|transport request failed|temporarily unavailable/i',
            'invalid_request'     => '/invalid request|bad request|validation (failed|error)|unprocessable/i',
        ];
        foreach ($rules as $class => $pattern) {
            if (preg_match($pattern, $text) === 1) return $class;
        }
        return 'unknown';
    }
}

if (!function_exists('redfox_remote_error_summary')) {
    /** Return only an allowlisted class and bounded HTTP status for an upstream failure. */
    function redfox_remote_error_summary($response): string
    {
        $status = null;
        if (is_array($response)) {
            foreach (['http_status', 'status_code', 'status', 'code'] as $field) {
                if (!isset($response[$field]) || !is_numeric($response[$field])) continue;
                $candidate = (int)$response[$field];
                if ($candidate >= 100 && $candidate <= 599) { $status = $candidate; break; }
            }
        }
        $class = redfox_remote_error_class($response);
        $label = $class === 'unknown' ? 'remote_failure' : 'remote_failure_' . $class;
        return $status === null ? $label : $label . '_http_' . $status;
    }
}

if (!function_exists('redfox_remote_error_fingerprint')) {
    /** Non-reversible short reference for correlating an unknown upstream failure. */
    function redfox_remote_error_fingerprint($response): string
    {
        $safe = is_array($response) ? $response : ['value' => $response];
        foreach ($safe as $key => $value) {
            if (is_array($value) || is_object($value)) $safe[$key] = gettype($value);
            elseif (is_string($value)) $safe[$key] = strlen($value) . ':' . hash('sha256', $value);
        }
        return substr(hash('sha256', json_encode($safe, JSON_UNESCAPED_SLASHES) ?: 'remote'), 0, 12);
    }
}

if (!function_exists('redfox_remote_error_is')) {
    function redfox_remote_error_is($response, string $allowedClass): bool
    {
        return in_array($allowedClass, ['user_not_found', 'invalid_credentials', 'unauthorized', 'already_exists', 'rate_limited', 'quota_exceeded', 'timeout', 'connection_failed', 'invalid_request'], true)
            && redfox_remote_error_class($response) === $allowedClass;
    }
}

if (!function_exists('redfox_exception_fingerprint')) {
    /** A safe replacement for Throwable::getMessage() in operational logs. */
    function redfox_exception_fingerprint(\Throwable $error): string
    {
        return preg_replace('/[^A-Za-z0-9_\\\\]/', '_', get_class($error)) ?: 'Throwable';
    }
}

if (!function_exists('redfox_log_exception')) {
    /** Log a non-sensitive exception fingerprint without attacker-controlled messages. */
    function redfox_log_exception(\Throwable $error, string $context = 'runtime'): void
    {
        $context = preg_replace('/[^A-Za-z0-9_.:\/-]/', '_', $context) ?: 'runtime';
        $class = preg_replace('/[^A-Za-z0-9_\\\\]/', '_', get_class($error)) ?: 'Throwable';
        $file = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($error->getFile())) ?: 'unknown';
        error_log('[redfox-error][' . $context . '] ' . $class . ' in ' . $file . ':' . max(0, $error->getLine()));
    }
}

if (!function_exists('redfox_public_exception')) {
    /** Log a safe fingerprint and return a stable, non-sensitive UI message. */
    function redfox_public_exception(\Throwable $error, string $context = 'runtime', string $publicMessage = 'عملیات به‌دلیل یک خطای داخلی انجام نشد.'): string
    {
        redfox_log_exception($error, $context);
        return $publicMessage;
    }
}

if (!function_exists('redfox_login_rate_check')) {
    /** Atomically reserve one attempt. This closes parallel-request rate-limit races. */
    function redfox_login_rate_check(string $scope, string $identity, int $limit = 8, int $window = 900): array
    {
        $limit = max(1, $limit); $window = max(1, $window);
        $dir = sys_get_temp_dir() . '/redfox_login_limits';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return ['allowed' => false, 'retry_after' => $window, 'path' => '', 'data' => [], 'reserved' => false];
        }
        @chmod($dir, 0700);
        $path = $dir . '/' . hash('sha256', $scope . '|' . $identity) . '.json';
        $fh = @fopen($path, 'c+');
        if (!is_resource($fh) || !flock($fh, LOCK_EX)) {
            if (is_resource($fh)) fclose($fh);
            return ['allowed' => false, 'retry_after' => $window, 'path' => $path, 'data' => [], 'reserved' => false];
        }
        $now = time();
        rewind($fh); $loaded = json_decode((string)stream_get_contents($fh), true);
        $data = is_array($loaded) ? $loaded : ['start' => $now, 'attempts' => 0];
        if (($now - (int)($data['start'] ?? 0)) >= $window || (int)($data['start'] ?? 0) > $now) {
            $data = ['start' => $now, 'attempts' => 0];
        }
        $allowed = (int)($data['attempts'] ?? 0) < $limit;
        if ($allowed) $data['attempts'] = (int)($data['attempts'] ?? 0) + 1;
        ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($data)); fflush($fh);
        flock($fh, LOCK_UN); fclose($fh); @chmod($path, 0600);
        $retry = max(0, $window - ($now - (int)$data['start']));
        return ['allowed' => $allowed, 'retry_after' => $retry, 'path' => $path, 'data' => $data, 'reserved' => $allowed];
    }
}

if (!function_exists('redfox_login_rate_fail')) {
    /** Kept for callers using the old check/fail API; check() already reserved atomically. */
    function redfox_login_rate_fail(array $state): void
    {
        if (!empty($state['reserved'])) return;
    }
}

if (!function_exists('redfox_login_rate_clear')) {
    function redfox_login_rate_clear(array $state): void
    {
        $path = (string)($state['path'] ?? '');
        if ($path === '') return;
        $fh = @fopen($path, 'c+');
        if (!is_resource($fh) || !flock($fh, LOCK_EX)) { if (is_resource($fh)) fclose($fh); return; }
        $data = ['start' => time(), 'attempts' => 0];
        ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($data)); fflush($fh);
        flock($fh, LOCK_UN); fclose($fh); @chmod($path, 0600);
    }
}
