<?php


declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Response.php';

if (class_exists('RedFoxAuth')) {
    return;
}

final class RedFoxAuth
{


    public static function validateInitData($rawData, string $botToken): array
    {
        if (is_string($rawData)) {
            $rawData = trim($rawData);
            if ($rawData === '' || strlen($rawData) > 16384) {
                throw new InvalidArgumentException('Telegram init data is missing or invalid');
            }
            // Telegram spec: parse the raw initData as a query string. No
            // html_entity_decode — it can corrupt URL-encoded values whose
            // bytes happen to resemble HTML5 named entities.
            parse_str($rawData, $initData);
        } elseif (is_array($rawData)) {
            $initData = $rawData;
        } else {
            throw new InvalidArgumentException('Telegram init data is missing or invalid');
        }

        if (!is_array($initData) || $initData === [] || count($initData, COUNT_RECURSIVE) > 128) {
            throw new InvalidArgumentException('Telegram init data payload is empty');
        }
        if (!isset($initData['hash'])) {
            throw new InvalidArgumentException('Telegram init data is missing required signature');
        }

        if (!is_string($initData['hash'])) {
            throw new InvalidArgumentException('Telegram init data signature is malformed');
        }
        $receivedHash = $initData['hash'];
        if (!preg_match('/^[a-f0-9]{64}$/i', $receivedHash)) {
            throw new InvalidArgumentException('Telegram init data signature is malformed');
        }
        unset($initData['hash']);

        // Telegram spec: build data_check_string from EVERY remaining field
        // (including empty ones), sorted by key, joined with \n as `key=value`.
        $checkArr = [];
        foreach ($initData as $key => $value) {
            $checkArr[] = $key . '=' . self::normalize($value);
        }
        if ($checkArr === []) {
            throw new InvalidArgumentException('Telegram init data payload is empty');
        }
        sort($checkArr, SORT_STRING);
        $checkString = implode("\n", $checkArr);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calcHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($calcHash, $receivedHash)) {
            throw new RuntimeException('User verification failed');
        }

        $userRaw = $initData['user'] ?? null;
        if (is_string($userRaw)) {
            $userData = json_decode($userRaw, true, 16, JSON_BIGINT_AS_STRING);
        } elseif (is_array($userRaw)) {
            $userData = $userRaw;
        } else {
            $userData = null;
        }

        if (!is_array($userData) || !isset($userData['id'])) {
            throw new RuntimeException('User data is missing or malformed in init data');
        }

        // Telegram initData must be short-lived to prevent replay attacks.
        $authDate = isset($initData['auth_date']) ? (int)$initData['auth_date'] : 0;
        $now = time();
        if ($authDate <= 0 || $authDate > ($now + 60) || ($now - $authDate) > 600) {
            throw new RuntimeException('Telegram init data has expired');
        }

        return $userData;
    }


    public static function validateContactResponse($rawData, string $botToken): array
    {
        if (!is_string($rawData)) {
            throw new InvalidArgumentException('Contact data is missing or invalid');
        }
        $rawData = trim($rawData);
        if ($rawData === '' || strlen($rawData) > 16384) {
            throw new InvalidArgumentException('Contact data is missing or invalid');
        }

        parse_str($rawData, $data);
        if (!is_array($data) || count($data, COUNT_RECURSIVE) > 128 || !isset($data['hash'])) {
            throw new InvalidArgumentException('Contact data is missing required signature');
        }

        if (!is_string($data['hash'])) {
            throw new InvalidArgumentException('Contact signature is malformed');
        }
        $receivedHash = $data['hash'];
        if (!preg_match('/^[a-f0-9]{64}$/i', $receivedHash)) {
            throw new InvalidArgumentException('Contact signature is malformed');
        }
        unset($data['hash']);

        $checkArr = [];
        foreach ($data as $key => $value) {
            $checkArr[] = $key . '=' . self::normalize($value);
        }
        if ($checkArr === []) {
            throw new InvalidArgumentException('Contact data payload is empty');
        }
        sort($checkArr, SORT_STRING);
        $checkString = implode("\n", $checkArr);

        $secrets = [
            hash_hmac('sha256', $botToken, 'WebAppData', true),
            $botToken,
        ];
        $valid = false;
        foreach ($secrets as $secret) {
            $calc = hash_hmac('sha256', $checkString, $secret);
            if (hash_equals($calc, $receivedHash)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new RuntimeException('Contact verification failed');
        }

        $authDate = isset($data['auth_date']) && is_scalar($data['auth_date']) ? (int)$data['auth_date'] : 0;
        $now = time();
        if ($authDate <= 0 || $authDate > ($now + 60) || ($now - $authDate) > 86400) {
            throw new RuntimeException('Contact data has expired');
        }

        $contactRaw = $data['contact'] ?? null;
        if (is_string($contactRaw) && strlen($contactRaw) <= 8192) {
            $contact = json_decode($contactRaw, true, 16, JSON_BIGINT_AS_STRING);
        } elseif (is_array($contactRaw)) {
            $contact = $contactRaw;
        } else {
            $contact = null;
        }
        if (!is_array($contact) || (!isset($contact['user_id']) && !isset($contact['phone_number']))) {
            throw new RuntimeException('Contact payload is missing or malformed');
        }

        return $contact;
    }


    public static function collectInitDataCandidates(): array
    {
        $candidates = [];

        $headers = self::getAllHeadersCompat();
        $headerKeys = ['X-Telegram-Init-Data', 'X-Telegram-Web-App-Init-Data', 'Telegram-Init-Data'];
        foreach ($headerKeys as $hk) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, $hk) === 0) {
                    $value = trim((string)$value);
                    if ($value !== '' && strlen($value) <= 16384) {
                        $candidates[] = $value;
                    }
                }
            }
        }

        $rawInput = file_get_contents('php://input', false, null, 0, 65537);
        $rawInput = $rawInput === false ? '' : trim($rawInput);
        if (strlen($rawInput) > 65536) {
            $rawInput = '';
        }
        $jsonBody = null;
        if ($rawInput !== '') {
            $jsonBody = json_decode($rawInput, true, 16, JSON_BIGINT_AS_STRING);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonBody = null;
            }
        }

        // Never accept Telegram authentication material from query parameters:
        // URLs are routinely persisted by proxies, access logs and analytics.
        // Verification entry points require JSON, while the dedicated headers
        // remain available for Telegram Web App clients.
        $candidateSources = [];
        if (is_array($jsonBody)) {
            $candidateSources[] = $jsonBody['initData'] ?? null;
            $candidateSources[] = $jsonBody['init_data'] ?? null;
        } elseif ($rawInput !== '') {
            $candidateSources[] = $rawInput;
        }

        foreach ($candidateSources as $value) {
            if ($value === null) continue;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' || strlen($value) > 16384) continue;
            } elseif (is_array($value) && count($value, COUNT_RECURSIVE) > 128) {
                continue;
            }
            $candidates[] = $value;
        }

        return $candidates;
    }


    public static function extractBearerToken(): ?string
    {
        $headers = self::getAllHeadersCompat();
        $auth = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $auth = $v;
                break;
            }
        }
        if ($auth === null) {
            $auth = $_SERVER['HTTP_AUTHORIZATION']
                 ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                 ?? null;
        }
        if (empty($auth)) {
            return null;
        }
        if (!preg_match('/^\s*Bearer\s+(\S.*)$/i', $auth, $m)) {
            return null;
        }
        $token = trim($m[1]);
        return strlen($token) <= 256 ? $token : null;
    }


    public static function userFromToken(string $token): ?array
    {
        if ($token === '') return null;
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) return null;
        try {
            $q = $pdo->prepare('SELECT * FROM user WHERE token = :token AND token_expires_at > :now LIMIT 1');
            $q->execute([':token' => $token, ':now' => time()]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            // Fail closed if the security migration has not been applied.
            RedFoxLogger::exception($e, 'Token expiry column unavailable');
            return null;
        }
    }


    public static function issueToken(int $userId): string
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            RedFoxLogger::exception($e, 'Failed to generate session token');
            throw new RuntimeException('Failed to generate session token');
        }
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Database unavailable');
        $expiresAt = time() + 86400; // 24 hours
        try {
            $q = $pdo->prepare('UPDATE user SET token = :token, token_expires_at = :expires WHERE id = :id');
            $q->execute([':token' => $token, ':expires' => $expiresAt, ':id' => $userId]);
        } catch (Throwable $e) {
            RedFoxLogger::exception($e, 'Failed to persist expiring session token');
            throw new RuntimeException('Security migration is required');
        }
        return $token;
    }

    private static function getAllHeadersCompat(): array
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            return is_array($h) ? $h : [];
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = $v;
            }
        }
        return $headers;
    }

    private static function normalize($value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($value === null) return '';
        return (string)$value;
    }
}

