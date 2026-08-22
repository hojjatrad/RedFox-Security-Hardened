<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class PaymentReceiptHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $orderId = RedFoxInput::string($_POST, 'order_id');
        if (!preg_match('/^[a-f0-9]{8,64}$/i', $orderId)) {
            RedFoxResponse::badRequest('order_id is invalid');
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            RedFoxResponse::badRequest('photo file is required');
        }
        $f = $_FILES['photo'];
        if ((int)($f['error'] ?? 99) !== UPLOAD_ERR_OK) {
            RedFoxResponse::badRequest('photo upload error: ' . ($f['error'] ?? 'unknown'));
        }
        $uploadSize = (int)($f['size'] ?? 0);
        if ($uploadSize < 1 || $uploadSize > 4 * 1024 * 1024) {
            RedFoxResponse::badRequest('photo too large (max 4 MB)');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp) || !is_readable($tmp)) {
            RedFoxResponse::badRequest('photo not accessible on server');
        }
        $imageInfo = @getimagesize($tmp);
        if (!is_array($imageInfo)) {
            RedFoxResponse::badRequest('photo is not a valid image');
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        $mime = (string)($imageInfo['mime'] ?? '');
        if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || ($width * $height) > 20000000
            || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            RedFoxResponse::badRequest('photo format or dimensions are invalid');
        }


        $payment = RedFoxDb::fetchOne(
            'SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND source = \'miniapp\'
              LIMIT 1',
            [':o' => $orderId, ':u' => $this->user['id']]
        );
        if ($payment === null) {
            RedFoxResponse::notFound('Payment record not found');
        }
        $currentStatus = strtolower((string)($payment['payment_Status'] ?? ''));
        if ($currentStatus === 'paid') {
            RedFoxResponse::fail(409, '✅ این پرداخت قبلاً تأیید شده است.');
        }
        if ($currentStatus !== 'unpaid') {
            RedFoxResponse::fail(409, 'این رسید قبلاً ارسال شده یا پرداخت دیگر قابل تغییر نیست.');
        }
        $paymentMethod = strtolower(trim((string)($payment['Payment_Method'] ?? '')));
        if (!in_array($paymentMethod, ['cart to cart', 'carttocart_pv'], true)) {
            RedFoxResponse::fail(422, 'برای این روش پرداخت امکان بارگذاری رسید وجود ندارد.');
        }


        $admins = [];
        try {
            $admins = RedFoxDb::fetchAll(
                "SELECT id_admin FROM admin
                  WHERE rule = 'administrator'
                     OR rule = 'Seller'"
            );
        } catch (Throwable $e) {
            RedFoxLogger::exception($e, 'admin table fetch failed');
        }
        $adminIds = [];
        foreach ($admins as $row) {
            $id = trim((string)($row['id_admin'] ?? ''));
            if ($id !== '' && ctype_digit($id)) {
                $adminIds[] = $id;
            }
        }
        if (empty($adminIds)) {
            RedFoxResponse::fail(503, '❌ هیچ ادمینی روی سرور تنظیم نشده است.');
        }


        global $APIKEY;
        $apiKey = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($apiKey === '') {
            $rowKey = select('setting', 'token_bot', null, null, 'select');
            $apiKey = is_array($rowKey) ? (string)($rowKey['token_bot'] ?? '') : '';
        }
        if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $apiKey)) {
            RedFoxResponse::fail(503, '❌ توکن معتبر ربات روی سرور تنظیم نشده است.');
        }

        // Claim the receipt atomically before contacting Telegram. This blocks
        // concurrent/replayed uploads from notifying administrators twice.
        try {
            $pdo = RedFoxDb::pdo();
            $claim = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'waiting'
                  WHERE id_order = :o AND id_user = :u AND source = 'miniapp'
                    AND payment_Status = 'Unpaid'
                    AND Payment_Method IN ('cart to cart', 'carttocart_pv')"
            );
            $claim->execute([':o' => $orderId, ':u' => $this->user['id']]);
            if ($claim->rowCount() !== 1) {
                RedFoxResponse::fail(409, 'این رسید قبلاً ارسال شده یا پرداخت دیگر قابل تغییر نیست.');
            }
        } catch (Throwable $e) {
            RedFoxLogger::exception($e, 'Receipt state claim failed');
            RedFoxResponse::fail(503, 'ثبت وضعیت رسید موقتاً ناموفق بود؛ دوباره تلاش کنید.');
        }

        $deliveredToAnyAdmin = false;
        try {
        $userId   = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $name     = trim((string)($this->user['first_name'] ?? '') . ' ' . (string)($this->user['last_name'] ?? ''));
        $balance  = (int)($this->user['Balance'] ?? 0);
        $amount   = (int)($payment['price'] ?? 0);
        $method   = (string)($payment['Payment_Method'] ?? 'cart to cart');


        $caption =
            "💳 رسید پرداخت کارت‌به‌کارت (از مینی‌اپ)\n\n" .
            "🆔 کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES) . "</code>\n" .
            "💰 مبلغ: " . number_format($amount) . " تومان\n" .
            "👤 کاربر: <a href=\"tg://user?id={$userId}\">" .
                htmlspecialchars($name !== '' ? $name : $userId, ENT_QUOTES) .
                "</a>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '') . "\n" .
            "🪪 شناسه عددی: <code>{$userId}</code>\n" .
            "💎 موجودی فعلی: " . number_format($balance) . " تومان\n" .
            "📌 روش: " . htmlspecialchars($method, ENT_QUOTES);


        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => 'Confirm_pay_' . $orderId],
                    ['text' => '❌ رد',    'callback_data' => 'reject_pay_'  . $orderId],
                ],
                [
                    ['text' => '➕ افزایش موجودی',     'callback_data' => 'addbalamceuser_' . $orderId],
                    ['text' => '🚫 مسدود (رسید جعلی)', 'callback_data' => 'blockuserfake_' . $userId],
                ],
                [
                    ['text' => '👁 مشاهده کاربر', 'url' => 'tg://user?id=' . $userId],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);


        $fileId = null;
        $failedAdmins = [];
        $remainingAdmins = $adminIds;
            while (!empty($remainingAdmins)) {
                $candidate = array_shift($remainingAdmins);
                $photoResult = $this->sendReceiptPhoto($apiKey, $candidate, $tmp, $mime, $caption, $keyboard);
                if (!empty($photoResult['delivered'])) {
                    $deliveredToAnyAdmin = true;
                    $maybeFileId = $photoResult['file_id'] ?? null;
                    if (is_string($maybeFileId) && $maybeFileId !== '') {
                        $fileId = $maybeFileId;
                        break;
                    }
                    continue;
                }
                $failedAdmins[] = $candidate;
            }

            if ($fileId === null) {
                foreach ($failedAdmins as $adminId) {
                    if ($this->sendReceiptText($apiKey, $adminId, $caption, $keyboard)) {
                        $deliveredToAnyAdmin = true;
                    }
                }
                if (!$deliveredToAnyAdmin) {
                    $restored = $this->restoreReceiptClaim($orderId, $userId);
                    RedFoxLogger::warn('Receipt delivery failed for every administrator', ['admin_count' => count($adminIds)]);
                    RedFoxResponse::fail(
                        502,
                        $restored
                            ? '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'
                            : '❌ ارسال رسید و بازیابی وضعیت ناموفق بود؛ با پشتیبانی تماس بگیرید.'
                    );
                }
            } else {
                foreach (array_merge($failedAdmins, $remainingAdmins) as $adminId) {
                    if ($this->forwardReceiptByFileId($apiKey, $adminId, $fileId, $caption, $keyboard)) {
                        $deliveredToAnyAdmin = true;
                    } elseif ($this->sendReceiptText($apiKey, $adminId, $caption, $keyboard)) {
                        $deliveredToAnyAdmin = true;
                    }
                }
            }
        } catch (Throwable $deliveryError) {
            $restored = false;
            if (!$deliveredToAnyAdmin) {
                $restored = $this->restoreReceiptClaim($orderId, $userId);
            }
            RedFoxLogger::exception($deliveryError, 'Unexpected receipt delivery failure');
            RedFoxResponse::fail(
                502,
                $deliveredToAnyAdmin
                    ? 'رسید به حداقل یک ادمین تحویل شد، اما تکمیل اعلان‌ها ناموفق بود.'
                    : ($restored
                        ? '❌ ارسال رسید ناموفق بود؛ وضعیت برای تلاش مجدد بازگردانی شد.'
                        : '❌ ارسال رسید و بازیابی وضعیت ناموفق بود؛ با پشتیبانی تماس بگیرید.')
            );
        }


        RedFoxLogger::debug('Receipt delivered to administrators', [
            'admin_count' => count($adminIds),
            'photo_ok' => $fileId !== null,
        ]);

        RedFoxResponse::ok([
            'order_id' => $orderId,
            'message'  => '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.',
        ]);
    }


    /** @return array{delivered:bool,file_id:?string} */
    private function sendReceiptPhoto(string $apiKey, string $chatId, string $localPath, string $mime, string $caption, string $keyboardJson): array
    {
        $result = telegram('sendPhoto', [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboardJson,
            'photo' => new CURLFile($localPath, $mime, 'receipt.jpg'),
        ], $apiKey);
        if (!is_array($result) || empty($result['ok'])) {
            RedFoxLogger::warn('Receipt photo delivery rejected');
            return ['delivered' => false, 'file_id' => null];
        }
        $sizes = $result['result']['photo'] ?? [];
        $best = is_array($sizes) && !empty($sizes) ? end($sizes) : null;
        $fileId = is_array($best) ? trim((string)($best['file_id'] ?? '')) : '';
        // A successful API response means the receipt was delivered even if a
        // future Telegram response shape omits the reusable file_id.
        return ['delivered' => true, 'file_id' => $fileId !== '' ? $fileId : null];
    }

    private function forwardReceiptByFileId(string $apiKey, string $chatId, string $fileId, string $caption, string $keyboardJson): bool
    {
        if ($fileId === '') return false;
        $result = telegram('sendPhoto', [
            'chat_id' => $chatId,
            'photo' => $fileId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboardJson,
        ], $apiKey);
        return is_array($result) && !empty($result['ok']);
    }

    private function sendReceiptText(string $apiKey, string $chatId, string $caption, string $keyboardJson): bool
    {
        $result = telegram('sendMessage', [
            'chat_id' => $chatId,
            'text' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboardJson,
        ], $apiKey);
        return is_array($result) && !empty($result['ok']);
    }

    private function restoreReceiptClaim(string $orderId, string $userId): bool
    {
        try {
            $restore = RedFoxDb::pdo()->prepare(
                "UPDATE Payment_report SET payment_Status='Unpaid'
                  WHERE id_order=:o AND id_user=:u AND source='miniapp'
                    AND payment_Status='waiting'"
            );
            $restore->execute([':o' => $orderId, ':u' => $userId]);
            if ($restore->rowCount() !== 1) {
                RedFoxLogger::warn('Receipt state restore did not update exactly one row');
                return false;
            }
            return true;
        } catch (Throwable $restoreError) {
            RedFoxLogger::exception($restoreError, 'Receipt state restore failed');
            return false;
        }
    }

}

