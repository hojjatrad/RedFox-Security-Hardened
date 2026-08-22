<?php
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
redfox_security_headers();
redfox_enforce_csrf();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
try { require_once __DIR__.'/../config.php'; if(isset($pdo)&&$pdo instanceof PDO)$pdo->prepare('UPDATE reseller_sessions SET revoked_at=? WHERE session_hash=?')->execute([time(),hash('sha256',session_id())]); } catch(Throwable $e) {}
$_SESSION=[];
if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); }
session_destroy();
header('Location: login.php');exit;
