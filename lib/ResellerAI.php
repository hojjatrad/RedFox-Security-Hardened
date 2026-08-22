<?php
declare(strict_types=1);

final class RedFoxResellerAI
{
    public static function settings(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT reseller_ai_price,reseller_ai_days FROM setting LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stmt->closeCursor();
        return [
            'price' => max(0, (int)($row['reseller_ai_price'] ?? 0)),
            'days' => max(1, min(3650, (int)($row['reseller_ai_days'] ?? 30))),
        ];
    }

    public static function request(PDO $pdo, string $resellerId, string $botToken, string $botUsername): array
    {
        $settings = self::settings($pdo);
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $bot = $pdo->prepare('SELECT id_user,username FROM botsaz WHERE bot_token=? AND id_user=? LIMIT 1 FOR UPDATE');
            $bot->execute([$botToken, $resellerId]);
            $verified = $bot->fetch(PDO::FETCH_ASSOC);
            $bot->closeCursor();
            if (!$verified) throw new RuntimeException('ربات نماینده متعلق به این حساب نیست.');

            $stmt = $pdo->prepare('SELECT * FROM reseller_ai_feature WHERE bot_token=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$botToken]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (is_array($current) && ($current['request_status'] ?? '') === 'pending') {
                $pdo->commit();
                return ['ok'=>true,'already_pending'=>true] + $settings;
            }

            $stillActive = is_array($current)
                && ($current['status'] ?? '') === 'active'
                && !empty($current['expires_at'])
                && (($ts = strtotime((string)$current['expires_at'])) !== false && $ts > time());
            $status = $stillActive ? 'active' : 'pending';
            if ($current) {
                $update = $pdo->prepare("UPDATE reseller_ai_feature SET reseller_id=?,bot_username=?,status=?,request_status='pending',requested_price=?,requested_days=?,requested_at=?,updated_at=? WHERE bot_token=?");
                $update->execute([$resellerId,$botUsername,$status,$settings['price'],$settings['days'],$now,$now,$botToken]);
            } else {
                $insert = $pdo->prepare("INSERT INTO reseller_ai_feature(reseller_id,bot_token,bot_username,status,request_status,requested_price,requested_days,requested_at,created_at,updated_at) VALUES(?,?,?,?,'pending',?,?,?,?,?)");
                $insert->execute([$resellerId,$botToken,$botUsername,$status,$settings['price'],$settings['days'],$now,$now,$now]);
            }
            $pdo->commit();
            return ['ok'=>true,'already_pending'=>false,'kept_active'=>$stillActive] + $settings;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function activate(PDO $pdo, string $botToken, int $days, int $pricePaid, string $actor, ?string $exactExpiry=null, string $note=''): array
    {
        $days = max(1, min(3650, $days));
        $pricePaid = max(0, $pricePaid);
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $bot = $pdo->prepare('SELECT id_user,username FROM botsaz WHERE bot_token=? LIMIT 1 FOR UPDATE');
            $bot->execute([$botToken]);
            $botInfo = $bot->fetch(PDO::FETCH_ASSOC);
            $bot->closeCursor();
            if (!$botInfo) throw new RuntimeException('این توکن در فهرست ربات‌های نماینده وجود ندارد.');

            $stmt = $pdo->prepare('SELECT * FROM reseller_ai_feature WHERE bot_token=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$botToken]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($exactExpiry !== null && $exactExpiry !== '') {
                $expiryTs = strtotime($exactExpiry);
                if ($expiryTs === false || $expiryTs <= time()) throw new RuntimeException('تاریخ انقضا باید معتبر و در آینده باشد.');
            } else {
                $base = time();
                if ($current && !empty($current['expires_at'])) {
                    $old = strtotime((string)$current['expires_at']);
                    if ($old !== false && $old > $base) $base = $old;
                }
                $expiryTs = strtotime('+' . $days . ' day', $base);
            }
            $expiry = date('Y-m-d H:i:s', $expiryTs);
            $noteLine = $note !== '' ? "\n[{$now}] " . mb_substr($note,0,500) : '';

            if ($current) {
                $update = $pdo->prepare("UPDATE reseller_ai_feature SET reseller_id=?,bot_username=?,status='active',expires_at=?,activated_at=COALESCE(activated_at,?),days=?,price_paid=?,notes=CONCAT(IFNULL(notes,''),?),request_status='approved',approved_by=?,approved_at=?,updated_at=? WHERE bot_token=?");
                $update->execute([$botInfo['id_user'],$botInfo['username'],$expiry,$now,$days,$pricePaid,$noteLine,$actor,$now,$now,$botToken]);
            } else {
                $insert = $pdo->prepare("INSERT INTO reseller_ai_feature(reseller_id,bot_token,bot_username,status,expires_at,activated_at,days,price_paid,notes,request_status,approved_by,approved_at,created_at,updated_at) VALUES(?,?,?,'active',?,?,?,?,?,'approved',?,?,?,?)");
                $insert->execute([$botInfo['id_user'],$botToken,$botInfo['username'],$expiry,$now,$days,$pricePaid,ltrim($noteLine),$actor,$now,$now,$now]);
            }
            $pdo->commit();
            return ['ok'=>true,'reseller_id'=>(string)$botInfo['id_user'],'username'=>(string)$botInfo['username'],'expires_at'=>$expiry,'days'=>$days,'price'=>$pricePaid];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectRequest(PDO $pdo, string $botToken, string $actor): ?array
    {
        $stmt = $pdo->prepare('SELECT reseller_id,bot_username,status,expires_at FROM reseller_ai_feature WHERE bot_token=? LIMIT 1');
        $stmt->execute([$botToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmt->closeCursor();
        if (!$row) return null;
        $active = ($row['status'] ?? '') === 'active' && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) > time();
        $nextStatus = $active ? 'active' : 'disabled';
        $pdo->prepare("UPDATE reseller_ai_feature SET status=?,request_status='rejected',approved_by=?,approved_at=?,updated_at=? WHERE bot_token=?")
            ->execute([$nextStatus,$actor,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$botToken]);
        return $row;
    }

    public static function deactivate(PDO $pdo, string $botToken, string $actor): ?array
    {
        $stmt = $pdo->prepare('SELECT reseller_id,bot_username FROM reseller_ai_feature WHERE bot_token=? LIMIT 1');
        $stmt->execute([$botToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmt->closeCursor();
        if (!$row) return null;
        $pdo->prepare("UPDATE reseller_ai_feature SET status='disabled',request_status=NULL,approved_by=?,approved_at=?,updated_at=? WHERE bot_token=?")
            ->execute([$actor,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$botToken]);
        return $row;
    }
}
