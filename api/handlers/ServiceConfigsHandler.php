<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/ServiceHandler.php';

final class ServiceConfigsHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = RedFoxInput::nullableString($this->data, 'username');
        if ($username === null || $username === '') {
            RedFoxResponse::badRequest('username is required');
        }


        $svcHandler = new ServiceHandler($this->user, $this->data);
        $invoice = $svcHandler->lookupInvoice($username);
        if ($invoice === null) {
            RedFoxResponse::notFound('Service not found');
        }

        $payload = $svcHandler->buildPayloadFromInvoice($invoice);
        if ($payload === null) {
            RedFoxResponse::fail(502, 'Service data unavailable');
        }


        $configs = [];
        foreach ((array)($payload['service_output'] ?? []) as $entry) {
            $t = strtolower((string)($entry['type'] ?? ''));
            if ($t === 'config') {
                $val = $entry['value'] ?? null;
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && $v !== '') $configs[] = $v;
                    }
                } elseif (is_string($val) && $val !== '') {
                    foreach (preg_split('/\r?\n/', $val) as $line) {
                        $line = trim($line);
                        if ($line !== '') $configs[] = $line;
                    }
                }
            } elseif ($t === 'password' && !empty($entry['value'])) {
                $configs[] = (string)$entry['value'];
            }
        }


        if (empty($configs)) {
            $subUrl = (string)($payload['subscription_url'] ?? '');
            if ($subUrl !== '') {
                $configs = $this->fetchConfigsFromSubscription($subUrl);
            }
        }

        RedFoxResponse::ok([
            'username'         => (string)($payload['username'] ?? $username),
            'configs'          => array_values(array_unique($configs)),
            'subscription_url' => (string)($payload['subscription_url'] ?? ''),
            'count'            => count(array_values(array_unique($configs))),
        ]);
    }


    private function fetchConfigsFromSubscription(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Red Fox-Mini/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
        ]);
        $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
        if (empty($policy['ok'])) { curl_close($ch); return []; }
        $body = '';
        $tooLarge = false;
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 2097152) { $tooLarge = true; return 0; }
            $body .= $chunk;
            return strlen($chunk);
        });
        $ok = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tooLarge || $ok === false || $http < 200 || $http >= 300 || $body === '') {
            return [];
        }

        $body = trim($body);


        if (preg_match('#^(vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic|wireguard)://#im', $body)) {
            return $this->splitConfigLines($body);
        }


        $decoded = $this->safeBase64Decode($body);
        if ($decoded !== null && preg_match('#^(vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic)://#im', $decoded)) {
            return $this->splitConfigLines($decoded);
        }


        return $this->splitConfigLines($body);
    }


    private function safeBase64Decode(string $s): ?string
    {
        $clean = preg_replace('/\s+/', '', $s);
        if ($clean === '' || !preg_match('#^[A-Za-z0-9+/=_\-]+$#', $clean)) return null;

        $std = strtr($clean, '-_', '+/');
        $pad = strlen($std) % 4;
        if ($pad > 0) $std .= str_repeat('=', 4 - $pad);
        $out = @base64_decode($std, true);
        if ($out === false) return null;
        return $out;
    }


    private function splitConfigLines(string $body): array
    {
        $lines = preg_split('/\r?\n/', $body);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line[0] === '#') continue;
            $out[] = $line;
        }
        return $out;
    }
}
