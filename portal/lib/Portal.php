<?php
/** Scoped authorization/data helpers for the reseller portal. */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/Security.php';
if(!(defined('REDFOX_API_MODE')&&REDFOX_API_MODE===true)){redfox_secure_session_start();redfox_security_headers();redfox_enforce_csrf();}else{redfox_security_headers();}

if (!function_exists('rxp_public_exception_message')) {
    /** Expose only application-authored validation errors; log all runtime failures safely. */
    function rxp_public_exception_message(Throwable $error, string $context = 'portal'): string {
        if ($error instanceof DomainException) {
            return mb_substr((string)$error->getMessage(), 0, 500);
        }
        redfox_log_exception($error, $context);
        return 'عملیات به‌دلیل یک خطای داخلی انجام نشد.';
    }
}

if (!function_exists('rxp_abort')) {
    function rxp_abort(int $code, string $message): never {
        if(defined('REDFOX_API_MODE')&&REDFOX_API_MODE===true)throw new DomainException($message,$code);
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="background:#0b0c10;color:#fff;font-family:sans-serif;padding:40px"><h2>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h2></body></html>';
        exit;
    }
}

if (!function_exists('rxp_context')) {
    function rxp_context(PDO $pdo): array {
        static $ctx = null;
        if (is_array($ctx)) return $ctx;
        $rid = (string)($_SESSION['portal_reseller_id'] ?? '');
        if ($rid === '' || !ctype_digit($rid)) { header('Location: login.php'); exit; }
        $now = time();
        $lastSeen = (int)($_SESSION['portal_last_seen'] ?? $now);
        if (($now - $lastSeen) > 1800) { session_destroy(); header('Location: login.php?expired=1'); exit; }
        $uaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!empty($_SESSION['portal_ua_hash']) && !hash_equals((string)$_SESSION['portal_ua_hash'], $uaHash)) {
            session_destroy(); header('Location: login.php'); exit;
        }
        $_SESSION['portal_last_seen'] = $now;
        $_SESSION['portal_ua_hash'] = $uaHash;
        $q = $pdo->prepare("SELECT * FROM user WHERE id = ? AND agent IN ('n','n2') LIMIT 1");
        $q->execute([$rid]);
        $u = $q->fetch(PDO::FETCH_ASSOC);
        if (!$u) { session_destroy(); header('Location: login.php'); exit; }
        try {
            $sessionHash=hash('sha256',session_id());
            $ss=$pdo->prepare("SELECT * FROM reseller_sessions WHERE session_hash=? AND reseller_id=? AND revoked_at IS NULL AND expires_at>? LIMIT 1");
            $ss->execute([$sessionHash,$rid,$now]);$sessionRow=$ss->fetch(PDO::FETCH_ASSOC);
            if(!$sessionRow){session_destroy();header('Location:login.php?session=expired');exit;}
            if($now-(int)$sessionRow['last_seen_at']>60)$pdo->prepare('UPDATE reseller_sessions SET last_seen_at=? WHERE session_hash=?')->execute([$now,$sessionHash]);
        } catch(Throwable $e) { session_destroy(); header('Location:login.php?migration=required'); exit; }
        $perms = json_decode((string)($u['reseller_perms'] ?? '{}'), true);
        if (!is_array($perms)) $perms = [];
        $legacySuper = false;
        foreach ($perms as $v) { if (!empty($v)) { $legacySuper = true; break; } }
        $role = (string)($u['reseller_role'] ?? '');
        if (!in_array($role, ['agent','super'], true)) $role = $legacySuper ? 'super' : 'agent';
        $status = (string)($u['reseller_portal_status'] ?? 'active');
        if ($status === 'disabled') rxp_abort(403, 'دسترسی پورتال این نماینده غیرفعال است.');
        $ids = [$rid];
        if ($role === 'super') {
            try {
                $s = $pdo->prepare("SELECT id FROM user WHERE reseller_parent_id = ? AND agent IN ('n','n2') AND (reseller_portal_status IS NULL OR reseller_portal_status <> 'disabled')");
                $s->execute([$rid]);
                foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) if (ctype_digit((string)$id)) $ids[] = (string)$id;
            } catch (Throwable $e) { /* migration may not yet be applied */ }
        }
        $ids = array_values(array_unique($ids));
        $tokens = [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($ph !== '') {
            $s = $pdo->prepare("SELECT bot_token FROM botsaz WHERE id_user IN ($ph) AND bot_token IS NOT NULL AND bot_token <> ''");
            $s->execute($ids);
            $tokens = array_values(array_filter(array_map('strval', $s->fetchAll(PDO::FETCH_COLUMN))));
        }
        $ctx = ['id'=>$rid, 'user'=>$u, 'role'=>$role, 'perms'=>$perms, 'scope_ids'=>$ids, 'bot_tokens'=>$tokens];
        $_SESSION['portal_agent_type'] = (string)$u['agent'];
        $_SESSION['portal_reseller_role'] = $role;
        return $ctx;
    }
}

if (!function_exists('rxp_can')) {
    function rxp_can(array $ctx, string $permission): bool {
        $always = ['dashboard','reports','products','branding','cards','profile'];
        if (in_array($permission, $always, true)) return true;
        if ($permission === 'subresellers') return $ctx['role'] === 'super';
        return !empty($ctx['perms'][$permission]);
    }
}
if (!function_exists('rxp_require_perm')) {
    function rxp_require_perm(array $ctx, string $permission): void {
        if (!rxp_can($ctx, $permission)) rxp_abort(403, 'شما اجازه دسترسی به این بخش را ندارید.');
    }
}

if (!function_exists('rxp_in')) {
    function rxp_in(array $values): array {
        if (!$values) return ['NULL', []];
        return [implode(',', array_fill(0, count($values), '?')), array_values($values)];
    }
}

if (!function_exists('rxp_invoice_scope')) {
    function rxp_invoice_scope(array $ctx, string $alias = 'i'): array {
        [$idPh,$idParams] = rxp_in($ctx['scope_ids']);
        $clauses = ["{$alias}.refral IN ($idPh)"];
        $params = $idParams;
        if ($ctx['bot_tokens']) {
            [$tokPh,$tokParams] = rxp_in($ctx['bot_tokens']);
            $clauses[] = "{$alias}.bottype IN ($tokPh)";
            $params = array_merge($params, $tokParams);
        }
        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }
}

if (!function_exists('rxp_customer_scope')) {
    function rxp_customer_scope(array $ctx, string $alias = 'u'): array {
        [$invSql,$invParams] = rxp_invoice_scope($ctx, 'ix');
        $clauses = ["EXISTS (SELECT 1 FROM invoice ix WHERE ix.id_user = {$alias}.id AND $invSql)"];
        $params = $invParams;
        if ($ctx['bot_tokens']) {
            [$tokPh,$tokParams] = rxp_in($ctx['bot_tokens']);
            $clauses[] = "{$alias}.bottype IN ($tokPh)";
            $params = array_merge($params, $tokParams);
        }
        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }
}

if (!function_exists('rxp_assert_customer')) {
    function rxp_assert_customer(PDO $pdo, array $ctx, string $uid): array {
        if (!ctype_digit($uid)) rxp_abort(400, 'شناسه کاربر نامعتبر است.');
        [$scope,$params] = rxp_customer_scope($ctx, 'u');
        $q=$pdo->prepare("SELECT u.* FROM user u WHERE u.id=? AND $scope LIMIT 1");
        $q->execute(array_merge([$uid],$params));
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) rxp_abort(404, 'این کاربر در محدوده نمایندگی شما نیست.');
        return $row;
    }
}

if (!function_exists('rxp_assert_invoice')) {
    function rxp_assert_invoice(PDO $pdo, array $ctx, string $invoiceId): array {
        [$scope,$params] = rxp_invoice_scope($ctx, 'i');
        $q=$pdo->prepare("SELECT i.* FROM invoice i WHERE i.id_invoice=? AND $scope LIMIT 1");
        $q->execute(array_merge([$invoiceId],$params));
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) rxp_abort(404, 'این سرویس در محدوده نمایندگی شما نیست.');
        return $row;
    }
}

if (!function_exists('rxp_target_reseller')) {
    function rxp_target_reseller(array $ctx): string {
        $target = trim((string)($_GET['owner'] ?? $_POST['owner'] ?? $ctx['id']));
        if (!in_array($target, $ctx['scope_ids'], true)) rxp_abort(403, 'این نماینده در محدوده شما نیست.');
        return $target;
    }
}

if (!function_exists('rxp_bot_info')) {
    function rxp_bot_info(PDO $pdo, string $rid): ?array {
        $q=$pdo->prepare('SELECT * FROM botsaz WHERE id_user=? LIMIT 1'); $q->execute([$rid]);
        $r=$q->fetch(PDO::FETCH_ASSOC); return is_array($r)?$r:null;
    }
}
if (!function_exists('rxp_bot_dir')) {
    function rxp_bot_dir(PDO $pdo, string $rid): ?string {
        $bot=rxp_bot_info($pdo,$rid); if(!$bot) return null;
        $name=preg_replace('/[^A-Za-z0-9_@.-]/','',(string)$bot['username']);
        $root=realpath(dirname(__DIR__,2).'/vpnbot'); if(!$root) return null;
        $path=$root.DIRECTORY_SEPARATOR.$rid.$name;
        $real=realpath($path);
        if(!$real || strpos($real,$root.DIRECTORY_SEPARATOR)!==0) return null;
        return $real;
    }
}

if (!function_exists('rxp_audit')) {
    function rxp_audit(PDO $pdo, array $ctx, string $action, string $entity='', array $details=[]): void {
        try {
            $q=$pdo->prepare('INSERT INTO reseller_audit_log (reseller_id,actor_role,action,entity,details,ip,created_at) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$ctx['id'],$ctx['role'],$action,$entity,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),redfox_client_ip(),time()]);
        } catch(Throwable $e) { error_log('[portal audit] '.redfox_exception_fingerprint($e)); }
    }
}

if (!function_exists('rxp_money')) { function rxp_money($v): string { return number_format((int)$v); } }
if (!function_exists('rxp_h')) { function rxp_h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); } }
