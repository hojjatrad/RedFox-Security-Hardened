<?php


declare(strict_types=1);

require_once __DIR__ . '/VerifyHandler.php';

final class PhoneVerifyHandler
{
    public static function run(): void
    {
        global $APIKEY;

        if (!is_string($APIKEY) || $APIKEY === '') {
            RedFoxLogger::critical('Bot APIKEY is not configured');
            self::fail(500, 'Server is not configured (missing bot token)');
        }

        $candidates = RedFoxAuth::collectInitDataCandidates();
        if (empty($candidates)) {
            self::fail(400, 'Telegram init data is missing or invalid');
        }

        $userData = null;
        $lastException = null;
        foreach ($candidates as $candidate) {
            try {
                $userData = RedFoxAuth::validateInitData($candidate, $APIKEY);
                break;
            } catch (InvalidArgumentException $e) {
                $lastException = $e;
                continue;
            } catch (RuntimeException $e) {
                $lastException = $e;
                continue;
            }
        }

        if ($userData === null) {
            $status = $lastException instanceof RuntimeException ? 403 : 400;
            RedFoxLogger::warn('Phone initData verification failed', [
                'exception' => $lastException ? get_class($lastException) : 'missing_init_data',
            ]);
            self::fail($status, 'Telegram authentication is invalid or expired');
        }

        $userId = (int)$userData['id'];

        $raw = file_get_contents('php://input', false, null, 0, 65537);
        $body = is_string($raw) && $raw !== '' && strlen($raw) <= 65536
            ? json_decode($raw, true, 16, JSON_BIGINT_AS_STRING)
            : null;
        $contactRaw = null;
        if (is_array($body)) {
            $contactRaw = $body['contact_response'] ?? $body['response'] ?? $body['contact'] ?? null;
        }
        if ($contactRaw === null && isset($_POST['contact_response'])) {
            $contactRaw = $_POST['contact_response'];
        }
        if (!is_string($contactRaw) || trim($contactRaw) === '') {
            self::fail(400, 'اطلاعات شماره دریافت نشد.');
        }

        $contact = null;
        try {
            $contact = RedFoxAuth::validateContactResponse($contactRaw, $APIKEY);
        } catch (InvalidArgumentException $e) {
            RedFoxLogger::exception($e, 'Invalid contact payload', ['tg_id' => $userId]);
            self::fail(400, 'اطلاعات شماره نامعتبر است.');
        } catch (RuntimeException $e) {
            RedFoxLogger::exception($e, 'Contact verification failed', ['tg_id' => $userId]);
            self::fail(403, 'اعتبارسنجی شماره ناموفق بود.');
        }

        $contactUserId = (int)($contact['user_id'] ?? 0);
        if ($contactUserId !== $userId) {
            self::fail(403, 'شمارهٔ به‌اشتراک‌گذاشته‌شده متعلق به شما نیست.');
        }

        $phone = preg_replace('/\D+/', '', (string)($contact['phone_number'] ?? ''));
        if ($phone === '') {
            self::fail(400, 'شمارهٔ موبایل نامعتبر است.');
        }

        $setting = select('setting', '*', null, null, 'select');
        if (!is_array($setting)) {
            $setting = [];
        }

        if (($setting['iran_number'] ?? '') === 'onAuthenticationiran' && !preg_match('/989[0-9]{9}$/', $phone)) {
            self::fail(422, 'لطفاً شمارهٔ موبایل ایران را به اشتراک بگذارید.');
        }

        update('user', 'number', $phone, 'id', $userId);
        if (($setting['verifystart'] ?? '') === 'onverify') {
            update('user', 'verify', '1', 'id', $userId);
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }

        $userRecord = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (!is_array($userRecord)) {
            self::fail(500, 'Failed to load user after phone verification');
        }

        RedFoxLogger::debug('Phone verified for miniapp user', ['tg_id' => $userId]);

        VerifyHandler::finalizeForUser($userId, $userData, $userRecord);
    }

    private static function fail(int $code, string $msg): void
    {
        if (function_exists('__verify_emit')) {
            __verify_emit($code, ['status' => false, 'msg' => $msg, 'token' => null]);
            exit;
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
        }
        echo json_encode(['status' => false, 'msg' => $msg, 'token' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
