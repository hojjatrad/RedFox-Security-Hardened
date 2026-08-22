<?php

// ════════════════════════════════════════════════════════════════════
//  Red Fox — استایل‌دهی (رنگ‌بندی) دکمه‌های ربات نماینده
//  استایل‌ها از style.json خوانده می‌شوند و قابل ویرایش از پورتال هستند.
//  مقادیر مجاز: primary, success, danger, warning, secondary, default
// ════════════════════════════════════════════════════════════════════

/**
 * بارگذاری تنظیمات استایل دکمه‌ها از style.json
 */
function rxVpnbotLoadStyles() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $defaults = [
        'enabled' => true,
        'buy' => 'success',
        'test' => 'warning',
        'my_service' => 'primary',
        'wallet' => 'primary',
        'support' => 'secondary',
        'admin' => 'danger',
        'back' => 'default',
        'payment_confirm' => 'success',
        'payment_add_balance' => 'primary',
        'payment_back' => 'default',
        'product_item' => 'default',
        'category_item' => 'default',
        'panel_location' => 'primary',
        'custom_volume' => 'warning',
    ];
    $path = __DIR__ . '/style.json';
    if (is_file($path)) {
        $json = json_decode((string)file_get_contents($path), true);
        if (is_array($json)) {
            $cache = array_merge($defaults, $json);
            return $cache;
        }
    }
    $cache = $defaults;
    return $cache;
}

/**
 * بررسی فعال بودن استایل‌دهی
 */
function rxVpnbotStylesEnabled() {
    $s = rxVpnbotLoadStyles();
    return !empty($s['enabled']);
}

/**
 * گرفتن استایل یک دکمه بر اساس کلید. اگر استایل default یا خالی باشد، null برمی‌گرداند.
 */
function rxVpnbotGetStyle($key) {
    if (!rxVpnbotStylesEnabled()) return null;
    $s = rxVpnbotLoadStyles();
    $style = (string)($s[$key] ?? 'default');
    $valid = ['primary', 'success', 'danger', 'warning', 'secondary', 'default'];
    if (!in_array($style, $valid, true)) return null;
    if ($style === 'default') return null;
    return $style;
}

/**
 * ساخت یک دکمه reply keyboard (بدون style — Telegram از style فقط در inline پشتیبانی می‌کند).
 * اگر style به reply keyboard اضافه شود، Telegram پیام را رد می‌کند و دکمه‌ای نمایش داده نمی‌شود!
 */
function rxVpnbotBtn($text, $styleKey = '') {
    return ['text' => (string)$text];
}

/**
 * ساخت یک دکمه inline استایل‌دار.
 * فقط برای inline_keyboard — reply keyboard از style پشتیبانی نمی‌کند.
 */
function rxVpnbotInlineBtn($text, $extra = [], $styleKey = '') {
    $btn = array_merge(['text' => $text], $extra);
    // Bot API رسمی در بسیاری از نسخه‌ها فیلد style را رد می‌کند؛ عملکرد بر ظاهر اولویت دارد.
    if ((string)rx_env('REDFOX_TELEGRAM_BUTTON_STYLES') === '1') {
        $style = rxVpnbotGetStyle($styleKey);
        if ($style !== null) $btn['style'] = $style;
    }
    return $btn;
}


// ════════════════════════════════════════════════════════════════════
//  Red Fox — دسترسی‌های انتخابی سوپر نماینده (Super Reseller Permissions)
// ════════════════════════════════════════════════════════════════════

/**
 * بارگذاری دسترسی‌های یک نماینده از ستون reseller_perms
 * @return array لیست دسترسی‌های فعال
 */
function rxVpnbotPerms($resellerId) {
    global $pdo;
    $defaults = [
        'products' => false,
        'categories' => false,
        'extend_user' => false,
        'charge_user' => false,
        'manage_users' => false,
        'reports' => false,
        'set_prices' => false,
    ];
    if (!($pdo instanceof PDO)) return $defaults;
    try {
        $st = $pdo->prepare("SELECT reseller_perms FROM user WHERE id = :id LIMIT 1");
        $st->execute([':id' => (string)$resellerId]);
        $raw = (string)$st->fetchColumn();
        if ($raw !== '' && $raw !== '{}') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                foreach ($defaults as $k => $v) {
                    if (isset($dec[$k])) $defaults[$k] = (bool)$dec[$k];
                }
            }
        }
    } catch (Throwable $e) {}
    return $defaults;
}

/**
 * آیا نماینده دسترسی مشخصی دارد؟
 */
function rxVpnbotHasPerm($resellerId, $perm) {
    $perms = rxVpnbotPerms($resellerId);
    return !empty($perms[$perm]);
}

/**
 * آیا نماینده حداقل یک دسترسی سوپر دارد؟
 */
function rxVpnbotIsSuper($resellerId) {
    $perms = rxVpnbotPerms($resellerId);
    foreach ($perms as $v) { if ($v) return true; }
    return false;
}


function rxVpnbotCallbackData(string$prefix,string$payload):string{global$pdo,$ApiToken;$candidate=$prefix.$payload;if(strlen($candidate)<=64)return$candidate;$token='rxc_'.bin2hex(random_bytes(10));try{$pdo->prepare('INSERT INTO bot_callback_map(token,bot_token,callback_data,expires_at) VALUES(?,?,?,?)')->execute([$token,hash('sha256',(string)$ApiToken),$candidate,time()+86400]);return$token;}catch(Throwable$e){error_log('[vpnbot callback map] '.redfox_exception_fingerprint($e));return substr($candidate,0,64);}}
function rxVpnbotResolveCallback(string$value):string{global$pdo,$ApiToken;if(!str_starts_with($value,'rxc_'))return$value;try{$q=$pdo->prepare('SELECT callback_data FROM bot_callback_map WHERE token=? AND bot_token=? AND expires_at>? LIMIT 1');$q->execute([$value,hash('sha256',(string)$ApiToken),time()]);$v=$q->fetchColumn();$q->closeCursor();return$v!==false?(string)$v:$value;}catch(Throwable$e){return$value;}}

function readJsonFileIfExists($path, $default = [])
{
    if (!is_file($path)) {
        return $default;
    }

    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return $default;
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : $default;
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — هوش مصنوعی برای ربات نماینده (vpnbot)
//  از تنظیمات مشترکِ ربات اصلی (setting.ai_support) استفاده می‌کند.
//  فعال/غیرفعال و تاریخ انقضا از جدول reseller_ai_feature خوانده می‌شود.
// ════════════════════════════════════════════════════════════════════

/**
 * وضعیت اشتراک هوش مصنوعی این ربات نماینده را برمی‌گرداند.
 * @return array ['ok'=>bool,'status'=>string,'expires_at'=>string|null,'info'=>array|null]
 */
function rxVpnbotAiStatus($botToken) {
    global $pdo;
    $result = ['ok'=>false,'status'=>'disabled','expires_at'=>null,'info'=>null];
    if (!($pdo instanceof PDO) || trim((string)$botToken)==='') return $result;
    try {
        $st = $pdo->prepare("SELECT * FROM reseller_ai_feature WHERE bot_token = :t LIMIT 1");
        $st->execute([':t'=>$botToken]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return $result;
        $result['info'] = $row;
        $status = (string)($row['status'] ?? 'disabled');
        if ($status !== 'active') { $result['status'] = ($status === 'expired' ? 'expired' : 'disabled'); return $result; }
        // بررسی انقضا
        $exp = trim((string)($row['expires_at'] ?? ''));
        if ($exp !== '') {
            $expTs = strtotime($exp);
            if ($expTs !== false && $expTs < time()) {
                $result['status'] = 'expired';
                $result['expires_at'] = $exp;
                return $result;
            }
        }
        $result['ok'] = true;
        $result['status'] = 'active';
        $result['expires_at'] = $exp;
        return $result;
    } catch (Throwable $e) {
        error_log('[vpnbot ai] rxVpnbotAiStatus: ' . redfox_exception_fingerprint($e));
        return $result;
    }
}

/**
 * تنظیمات هوش مصنوعی را از ربات اصلی می‌خواند (setting.ai_support).
 */
function rxVpnbotAiConfig() {
    global $pdo;
    $defaults = [
        'status' => 'off',
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'api_key' => '',
        'model' => 'gpt-4o-mini',
        'system_prompt' => "تو دستیار پشتیبانی یک فروشگاه اشتراک VPN هستی و به فارسی محترمانه و کوتاه پاسخ می‌دهی.\nقوانین:\n۱) فقط سؤالات عمومی (نحوه‌ی اتصال، تفاوت پلن‌ها، روش پرداخت، سؤالات متداول) را جواب بده.\n۲) اگر سؤال مربوط به حسابِ شخصی کاربر است یا مطمئن نیستی، در ابتدای پاسخ دقیقاً بنویس «[ESCALATE]».\n۳) هرگز مبلغ یا قیمتی را اگر مطمئن نیستی نگو.",
        'escalate_keywords' => "ادمین,انسانی,اپراتور,بشر,شارژ کن,موجودی,رسید,بازگشت وجه,پرداخت",
        'max_history' => 4,
        'confidence_escalation' => 'on',
    ];
    if (!($pdo instanceof PDO)) return $defaults;
    try {
        $s = $pdo->prepare("SELECT ai_support AS v FROM setting LIMIT 1");
        $s->execute();
        $raw = (string)$s->fetchColumn();
        if ($raw === '' || $raw === '0') return $defaults;
        $dec = json_decode($raw, true);
        if (is_array($dec)) return array_merge($defaults, $dec);
    } catch (Throwable $e) {
        error_log('[vpnbot ai] rxVpnbotAiConfig: ' . redfox_exception_fingerprint($e));
    }
    return $defaults;
}

/**
 * لاگ مکالمه در ai_support_log (با bottype برای تفکیک از ربات اصلی).
 */
function rxVpnbotAiLog($userId, $role, $message, $botToken, $escalated = 0) {
    global $pdo;
    if (!($pdo instanceof PDO)) return;
    try {
        $pdo->prepare("INSERT INTO ai_support_log (id_user, role, message, created_at, escalated, bottype) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $role, mb_strimwidth((string)$message, 0, 4000), date('Y-m-d H:i:s'), $escalated ? 1 : 0, (string)$botToken]);
    } catch (Throwable $e) {
        // ستون bottype ممکن است نباشد — بدون آن تلاش کن
        try {
            $pdo->prepare("INSERT INTO ai_support_log (id_user, role, message, created_at, escalated) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $role, mb_strimwidth((string)$message, 0, 4000), date('Y-m-d H:i:s'), $escalated ? 1 : 0]);
        } catch (Throwable $e2) {
            error_log('[vpnbot ai] log failed: ' . redfox_exception_fingerprint($e2));
        }
    }
}

/**
 * بازیابی دانش مرتبط از ai_knowledge (اشتراکی با ربات اصلی).
 */
function rxVpnbotAiKb($userMessage, $limit = 5) {
    global $pdo;
    $limit = max(1, min(10, (int)$limit));
    $results = [];
    if (!($pdo instanceof PDO)) return $results;
    try {
        $msgClean = preg_replace('/[\p{P}\p{S}\d]+/u', ' ', (string)$userMessage);
        $msgClean = preg_replace('/\s+/', ' ', trim($msgClean));
        $words = explode(' ', $msgClean);
        $stop = ['و','در','به','از','که','این','است','را','با','برای','یا','ها','های','شد','میشه','کنید','بود','آن','یک','تا','هم','اما','اگر','چه','می','کرد','هست','ما','شما','من','روی','نه','بله','خب','پس'];
        $needles = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) >= 2 && !in_array($w, $stop, true)) {
                $needles[] = mb_strtolower($w, 'UTF-8');
            }
        }
        if (!empty($needles)) {
            $likeParts = []; $likeParams = [];
            foreach ($needles as $n) {
                $likeParts[] = "LOWER(question) LIKE ? OR LOWER(answer) LIKE ?";
                $likeParams[] = '%' . $n . '%';
                $likeParams[] = '%' . $n . '%';
            }
            $likeSql = "SELECT id, question, answer FROM ai_knowledge WHERE enabled = 1 AND (" . implode(' OR ', $likeParts) . ") ORDER BY id DESC LIMIT " . ($limit * 3);
            $st = $pdo->prepare($likeSql);
            $st->execute($likeParams);
            $likeResults = $st->fetchAll(PDO::FETCH_ASSOC);
            $scored = [];
            foreach ($likeResults as $r) {
                $hay = mb_strtolower((string)$r['question'] . ' ' . (string)$r['answer'], 'UTF-8');
                $score = 0;
                foreach ($needles as $n) { if (mb_strpos($hay, $n) !== false) $score++; }
                $scored[] = ['score'=>$score, 'row'=>$r];
            }
            usort($scored, function($a,$b){ return $b['score']<=>$a['score']; });
            foreach (array_slice($scored, 0, $limit) as $s) $results[] = $s['row'];
        }
    } catch (Throwable $e) {
        error_log('[vpnbot ai] kb: ' . redfox_exception_fingerprint($e));
    }
    if (empty($results)) {
        try {
            $st2 = $pdo->query("SELECT id, question, answer FROM ai_knowledge WHERE enabled = 1 ORDER BY id DESC LIMIT " . $limit);
            $results = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
    }
    return $results;
}

/**
 * ارسال سؤال به API هوش مصنوعی و دریافت پاسخ.
 */
function rxVpnbotAiAsk($userMessage, $userId, $cfg, $botToken) {
    global $pdo;
    if (empty($cfg['api_key']) || empty($cfg['api_url'])) return null;

    $sys = (string)($cfg['system_prompt'] ?? '');

    // ── Red Fox: همیشه تمام دانش را به AI بده (جستجوی معنایی توسط مدل) ──
    $allKb = [];
    try {
        $st = $pdo->query("SELECT question, answer FROM ai_knowledge WHERE enabled = 1 ORDER BY id DESC LIMIT 40");
        $allKb = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    if (!empty($allKb)) {
        $kbText = "\n\n📚 پایگاه دانش شما (سؤالات و پاسخ‌ها):";
        $kbText .= "\n⚠️ کاربر ممکن است سؤالش را با کلمات متفاوت بپرسد. معنای سؤال را بفهمید و بهترین پاسخ را از بین موارد زیر پیدا کنید.";
        $kbText .= "\nحتی اگر کلمات کاربر دقیقاً با کلمات پایگاه دانش یکی نباشد، اگر موضوع مرتبط است، همان پاسخ را بدهید.";
        $kbText .= "\nفقط زمانی پاسخی نمی‌دهید که هیچ مورد مرتبطی نباشد.\n";
        foreach ($allKb as $i => $k) {
            $q = trim((string)($k['question'] ?? ''));
            $a = mb_strimwidth(trim((string)($k['answer'] ?? '')), 0, 1000, '…', 'UTF-8');
            $kbText .= "\n[" . ($i+1) . "] سؤال: " . $q . "\nپاسخ: " . $a;
        }
        $sys .= $kbText;
    }

    $messages = [['role'=>'system','content'=>$sys]];

    // تاریخچه‌ی مکالمه (فقط همین ربات)
    if ($pdo instanceof PDO) {
        try {
            $maxh = max(1, min(10, (int)($cfg['max_history'] ?? 4)));
            $h = $pdo->prepare("SELECT role, message FROM ai_support_log WHERE id_user = ? AND role IN ('user','assistant') AND bottype = ? ORDER BY id DESC LIMIT " . $maxh);
            $h->execute([$userId, $botToken]);
            $rows = array_reverse($h->fetchAll(PDO::FETCH_ASSOC));
            foreach ($rows as $r) {
                $messages[] = ['role'=>$r['role'], 'content'=>(string)$r['message']];
            }
        } catch (Throwable $e) {}
    }

    $messages[] = ['role'=>'user','content'=>(string)$userMessage];

    $payload = json_encode([
        'model' => (string)($cfg['model'] ?? 'gpt-4o-mini'),
        'messages' => $messages,
        'temperature' => 0.3,
        'max_tokens' => 600,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init((string)$cfg['api_url']);
    $rxHeaders = ['Content-Type: application/json', 'Authorization: Bearer ' . (string)$cfg['api_key']];
    if (strpos((string)$cfg['api_url'], 'openrouter.ai') !== false) {
        $rxHeaders[] = 'HTTP-Referer: https://vpbotn.ir';
        $rxHeaders[] = 'X-Title: Red Fox VPN Reseller Bot';
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $rxHeaders,
    ]);
    $policy = redfox_apply_curl_url_policy($ch, (string)$cfg['api_url'], false, false);
    if (empty($policy['ok'])) { curl_close($ch); return null; }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = (int)curl_errno($ch);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        error_log('[vpnbot ai] provider request failed HTTP=' . $code . ' errno=' . $errno);
        return null;
    }
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) return null;
    $reply = $j['choices'][0]['message']['content'] ?? null;
    if (!is_string($reply)) $reply = $j['message']['content'] ?? ($j['response'] ?? null);
    return (is_string($reply) && trim($reply) !== '') ? trim($reply) : null;
}

/**
 * بررسی آیا پیام نیاز به ارجاع به پشتیبان دارد (کلمات کلیدی).
 */
function rxVpnbotAiShouldEscalate($text, $cfg) {
    $kws = array_filter(array_map('trim', explode(',', (string)($cfg['escalate_keywords'] ?? ''))));
    foreach ($kws as $kw) {
        if ($kw !== '' && function_exists('mb_stripos') && mb_stripos((string)$text, $kw) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * پردازش کامل پیام پشتیبانی با هوش مصنوعی.
 * @return bool true اگر AI پاسخ داد (پیام به کاربر ارسال شد)، false اگر باید به پشتیبانی عادی برود.
 */
function rxVpnbotAiHandleSupport($from_id, $username, $text, $botToken, $setting) {
    global $ApiToken;

    $aiStatus = rxVpnbotAiStatus($botToken);
    if (!$aiStatus['ok']) {
        return false; // فعال نیست یا منقضی شده
    }

    $cfg = rxVpnbotAiConfig();
    if (empty($cfg['api_key'])) return false;

    // کلمات ارجاع → پشتیبانی عادی
    if (rxVpnbotAiShouldEscalate($text, $cfg)) {
        rxVpnbotAiLog($from_id, 'user', $text, $botToken, 1);
        return false;
    }

    rxVpnbotAiLog($from_id, 'user', $text, $botToken, 0);
    $reply = rxVpnbotAiAsk($text, $from_id, $cfg, $botToken);

    if ($reply === null) {
        // AI جواب نداد → ارجاع به پشتیبانی
        return false;
    }

    // بررسی نشانگر ESCALATE
    $isEsc = false;
    if (($cfg['confidence_escalation'] ?? 'on') === 'on' && stripos($reply, '[ESCALATE]') !== false) {
        $isEsc = true;
        $reply = trim(str_ireplace('[ESCALATE]', '', $reply));
    }
    if ($isEsc) {
        rxVpnbotAiLog($from_id, 'assistant', '[ارجاع خودکار] ' . $reply, $botToken, 1);
        $kbEsc = json_encode(['inline_keyboard'=>[[['text'=>'📞 ارتباط با پشتیبانی','url'=>'https://t.me/' . ((string)($setting['support_username'] ?? ''))]]]]);
        if (function_exists('sendmessage')) {
            sendmessage($from_id, "🤖 این سؤال نیاز به بررسی دقیق‌تر دارد.\n📨 لطفاً از طریق دکمه زیر با پشتیبانی در ارتباط باشید.", $kbEsc, 'HTML');
        }
        return true;
    }

    rxVpnbotAiLog($from_id, 'assistant', $reply, $botToken, 0);
    $aiText = "🤖 <i>دستیار هوشمند:</i>\n\n" . $reply . "\n\n💬 سؤال دیگری دارید؟\nبرای صحبت با ادمین: «ادمین» را بفرستید یا دکمه زیر را بزنید.";
    $kb = json_encode(['inline_keyboard'=>[
        [['text'=>'👥 ارتباط با پشتیبانی','url'=>'https://t.me/' . ((string)($setting['support_username'] ?? ''))]],
    ]]);
    if (function_exists('sendmessage')) {
        sendmessage($from_id, $aiText, $kb, 'HTML');
    }
    return true;
}

function DirectPaymentbot($order_id,$image = 'images.jpg'){
    global $pdo,$ManagePanel,$textbotlang,$keyboardextendfnished,$keyboard,$Confirm_pay,$from_id,$message_id,$datatextbot;
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id,"select");
    $paymentNote = function_exists('formatPaymentReportNote')
        ? formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null)
        : ($Payment_report['dec_not_confirmed'] ?? '');
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'],"select");
    $Balance_id['Balance'] = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true)['Balance'];
    update("user","Processing_value","0", "id",$Balance_id['id']);
    update("user","Processing_value_one","0", "id",$Balance_id['id']);
    update("user","Processing_value_tow","0", "id",$Balance_id['id']);
    update("user","Processing_value_four","0", "id",$Balance_id['id']);
        $Balance_confrim = intval($Balance_id['Balance']) + intval($Payment_report['price']);
        $userbalance = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true);
        $userbalance['Balance'] = $Balance_confrim;
        file_put_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json",json_encode($userbalance));
        update("Payment_report","payment_Status","paid","id_order",$Payment_report['id_order']);
        $Payment_report['price'] = number_format($Payment_report['price'], 0);
        $format_price_cart = $Payment_report['price'];
        if($Payment_report['Payment_Method'] == "cart to cart" or   $Payment_report['Payment_Method'] == "arze digital offline"){
        $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.

🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
}
function channel_check($id_channel){
    global $from_id;
        $channel_link = array();
         $response = telegram('getChatMember',[
                'chat_id' => $id_channel,
                'user_id' => $from_id
                ]);
            if($response['ok']){
        if(!in_array($response['result']['status'], ['member', 'creator', 'administrator'])){
                $channel_link[] = $id_channel;
            }
        }

        if(count($channel_link) == 0){
            return [];
        }else{
            return $channel_link;
        }
}

