<?php

require_once __DIR__ . '/lib/Security.php';

if (!function_exists('redfox_dedup_error_log')) {
    function redfox_dedup_error_log($key, $message, $ttl = 3600) {
        $cacheDir = sys_get_temp_dir() . '/redfox_log_dedup';
        if (is_link($cacheDir)
            || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0700, true))
            || !is_dir($cacheDir)) {
            return false;
        }
        @chmod($cacheDir, 0700);
        $cacheFile = $cacheDir . '/' . hash('sha256', (string)$key);
        if (is_link($cacheFile)) return false;
        $handle = @fopen($cacheFile, 'c+');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }
        $duplicate = (int)@filesize($cacheFile) > 0 && (time() - (int)@filemtime($cacheFile)) < max(1, (int)$ttl);
        if (!$duplicate) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)time());
            fflush($handle);
            @touch($cacheFile);
            @chmod($cacheFile, 0600);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        if ($duplicate) return false;
        error_log((string)$message);
        return true;
    }
}

class CurlRequest {
    private $url;
    private $headers = [];
    private $timeout = null;
    private $authToken = null;
    private $api_key = null;
    private $cookie = null;
    public function __construct($url) {
        $this->url = $url;
    }

    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }

    public function setHeaders(array $headers) {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken($token) {
        $this->authToken = $token;
    }

    public function api_key($token) {
        $this->api_key = $token;
    }

    public function setCookie($cookieStr) {
        $this->cookie = $cookieStr;
    }

    private function prepareHeaders() {
        $headers = $this->headers;

        if ($this->authToken) {
            $headers[] = "Authorization: Bearer {$this->authToken}";
        }
        if ($this->api_key) {
            $headers[] = "X-API-Key: {$this->api_key}";
        }

        return $headers;
    }

    private function execute($method, $data = null) {
        $this->timeout = !$this->timeout ? 8 : $this->timeout;
        $allowPrivate = redfox_private_panel_endpoints_allowed();
        $policy = redfox_outbound_url_policy((string)$this->url, $allowPrivate, $allowPrivate);
        if (empty($policy['ok'])) {
            redfox_dedup_error_log('curl-policy|' . hash('sha256', (string)$this->url), 'CurlRequest blocked by outbound URL policy: ' . (string)($policy['error'] ?? 'unknown'));
            return ['status' => 0, 'body' => false, 'error' => 'Panel endpoint is not allowed by outbound policy.'];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);


        $verifyTls = defined('BOT_CURL_VERIFY_TLS') && BOT_CURL_VERIFY_TLS === true;
        if ($verifyTls) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        if (function_exists('redfox_apply_curl_proxy')) {
            redfox_apply_curl_proxy($ch, 'panel');
        }

        $finalHeaders = $this->prepareHeaders();
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
        }
        if ($data) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $policy = redfox_apply_curl_url_policy($ch, (string)$this->url, $allowPrivate, $allowPrivate);
        if (empty($policy['ok'])) {
            curl_close($ch);
            return ['status' => 0, 'body' => false, 'error' => 'Panel endpoint is not allowed by outbound policy.'];
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $host = parse_url($this->url, PHP_URL_HOST);
            $port = parse_url($this->url, PHP_URL_PORT);
            $dedupKey = 'curlerr|' . ($host ?: 'unknown') . ':' . ($port ?: '') . '|' . curl_errno($ch);
            redfox_dedup_error_log(
                $dedupKey,
                sprintf('CurlRequest transport failure host=%s HTTP=%d errno=%d',
                    preg_replace('/[^A-Za-z0-9.-]/', '_', (string)($host ?: 'unknown')),
                    (int)$httpCode,
                    (int)curl_errno($ch))
            );
            curl_close($ch);
            return [
                'status' => $httpCode,
                'body' => $response,
                'error' => 'Panel transport request failed.',
            ];
        }
        curl_close($ch);


        $rxLogAll = defined('BOT_CURL_LOG_ALL_HTTP_ERRORS') && BOT_CURL_LOG_ALL_HTTP_ERRORS === true;
        if ($httpCode === 0 || $httpCode >= 500 || ($rxLogAll && $httpCode >= 400)) {
            $host = parse_url($this->url, PHP_URL_HOST);
            $port = parse_url($this->url, PHP_URL_PORT);
            $dedupKey = 'curlhttp|' . ($host ?: 'unknown') . ':' . ($port ?: '') . '|' . $httpCode;
            redfox_dedup_error_log(
                $dedupKey,
                sprintf('CurlRequest host=%s returned HTTP=%d',
                    preg_replace('/[^A-Za-z0-9.-]/', '_', (string)($host ?: 'unknown')),
                    (int)$httpCode)
            );
        }

        $upstreamDownCodes = [502, 503, 504, 520, 521, 522, 523, 524, 525, 526, 527];
        if (in_array((int)$httpCode, $upstreamDownCodes, true)) {
            return [
                'status' => $httpCode,
                'body'   => $response,
                'error'  => sprintf('Panel temporarily unavailable (HTTP %d).', (int)$httpCode),
            ];
        }

        return [
            'status' => $httpCode,
            'body' => $response
        ];
    }

    public function get() {
        return $this->execute("GET");
    }

    public function post($data) {
        return $this->execute("POST", $data);
    }

    public function put($data) {
        return $this->execute("PUT", $data);
    }

    public function delete($data = null) {
        return $this->execute("DELETE", $data);
    }
    public function PATCH($data = null){
        return $this->execute('PATCH',$data);
    }
}

