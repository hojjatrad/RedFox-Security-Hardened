<?php
if (!defined('REDFOX_VPNBOT_WEBHOOK_AUTHENTICATED') || REDFOX_VPNBOT_WEBHOOK_AUTHENTICATED !== true) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config.php';
function rxVpnbotCleanMarkup($markup){$d=is_array($markup)?$markup:json_decode((string)$markup,true);if(!is_array($d))return$markup;$walk=function(&$x)use(&$walk){if(!is_array($x))return;unset($x['style']);foreach($x as&$v)$walk($v);};$walk($d);return json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
function telegram($method, $datas = [], $botToken = null)
{
    global $ApiToken;
    $token = trim((string)($botToken !== null ? $botToken : $ApiToken));
    $method = trim((string)$method);
    if (!preg_match('/^\d{6,20}:[A-Za-z0-9_-]{20,}$/', $token)
        || !preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $method)) {
        error_log('[vpnbot telegram] rejected invalid client parameters');
        return ['ok' => false, 'description' => 'Invalid Telegram request parameters.'];
    }
    $url = rtrim((string)(rx_env('REDFOX_TELEGRAM_API_BASE', 'https://api.telegram.org') ?: 'https://api.telegram.org'), '/')
        . '/bot' . $token . '/' . $method;
    $send = static function (array $payload) use ($url): array {
        $c = curl_init($url);
        if ($c === false) return ['ok' => false, 'description' => 'Telegram transport unavailable.'];
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'RedFox-Telegram-Client/2.4.12',
        ]);
        $policy = redfox_apply_curl_url_policy($c, $url, false, false);
        if (empty($policy['ok'])) { curl_close($c); return ['ok' => false, 'description' => 'Telegram endpoint is not permitted.']; }
        $raw = curl_exec($c);
        $http = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if (!is_string($raw) || strlen($raw) > 1048576) {
            return ['ok' => false, 'description' => 'Telegram transport failed.', 'http' => $http];
        }
        $decoded = json_decode($raw, true, 32);
        if (!is_array($decoded)) {
            return ['ok' => false, 'description' => 'Invalid Telegram response.', 'http' => $http];
        }
        if (isset($decoded['description'])) $decoded['description'] = 'Telegram request was rejected.';
        unset($decoded['parameters']['migrate_to_chat_id']);
        return $decoded;
    };
    $result = $send(is_array($datas) ? $datas : []);
    if (empty($result['ok']) && is_array($datas) && isset($datas['reply_markup'])) {
        $datas['reply_markup'] = rxVpnbotCleanMarkup($datas['reply_markup']);
        $result = $send($datas);
    }
    if (empty($result['ok'])) {
        error_log('[vpnbot telegram] method=' . $method
            . ' error_code=' . (int)($result['error_code'] ?? 0)
            . ' http=' . (int)($result['http'] ?? 0));
    }
    return $result;
}
function sendmessage($chat_id,$text,$keyboard,$parse_mode){$p=['chat_id'=>$chat_id,'text'=>$text,'reply_markup'=>$keyboard,'parse_mode'=>$parse_mode];$r=telegram('sendMessage',$p);if(empty($r['ok'])&&strtoupper((string)$parse_mode)==='HTML'){$p['text']=html_entity_decode(strip_tags((string)$text),ENT_QUOTES|ENT_HTML5,'UTF-8');unset($p['parse_mode']);$r=telegram('sendMessage',$p);}return$r;}
function prepareTelegramInputFile($input)
{
    if ($input instanceof CURLFile) {
        return $input;
    }

    if (is_string($input)) {
        if (preg_match('/^https?:\/\//i', $input)) {
            return $input;
        }

        $realPath = realpath($input);
        if ($realPath !== false && is_file($realPath) && is_readable($realPath)) {
            return new CURLFile($realPath);
        }

        error_log('[vpnbot telegram] document path is not readable');
        return null;
    }

    error_log('Unsupported Telegram input file type: ' . gettype($input));
    return null;
}

function sendDocument($chat_id, $documentPath, $caption) {
        $document = prepareTelegramInputFile($documentPath);
        if ($document === null) {
            return [
                'ok' => false,
                'description' => 'Document could not be prepared for Telegram upload.'
            ];
        }

        return telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => $caption,
        ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption){
    telegram('sendphoto',[
        'chat_id' => $chat_id,
        'photo'=> $photoid,
        'caption'=> $caption,
    ]);
}
function sendvideo($chat_id,$videoid,$caption){
    telegram('sendvideo',[
        'chat_id' => $chat_id,
        'video'=> $videoid,
        'caption'=> $caption,
    ]);
}
function senddocumentsid($chat_id,$documentid,$caption){
    telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document'=> $documentid,
        'caption'=> $caption,
    ]);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard,$parse_mode = 'HTML'){
    return telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,

    ]);
}
 function deletemessage($chat_id, $message_id){
  telegram('deletemessage', [
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
 }
function getFileddire($photoid){
  return telegram('getFile', [
'file_id' => $photoid,
]);
 }
function pinmessage($from_id,$message_id){
  return telegram('pinChatMessage', [
'chat_id' => $from_id,
'message_id' => $message_id,
]);
 }
 function unpinmessage($from_id){
  return telegram('unpinAllChatMessages', [
'chat_id' => $from_id,
]);
 }
  function answerInlineQuery($inline_query_id,$results){
  return telegram('answerInlineQuery', [
      "inline_query_id" => $inline_query_id,
        "results" => json_encode($results)
]);
 }
 function convertPersianNumbersToEnglish($string) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($persian_numbers, $english_numbers, $string);
}

$rxVpnbotRaw = file_get_contents('php://input', false, null, 0, 1048577);
$update = is_string($rxVpnbotRaw) ? json_decode($rxVpnbotRaw, true, 32) : null;
unset($rxVpnbotRaw);
if (!is_array($update) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(200);
    exit;
}
$rxVpnbotUpdateId = $update['update_id'] ?? null;
if (!(is_int($rxVpnbotUpdateId) || (is_string($rxVpnbotUpdateId) && ctype_digit($rxVpnbotUpdateId)))) {
    http_response_code(200);
    exit;
}
// Claim each Telegram update atomically. This prevents retry-induced duplicate
// purchases, balance changes, or messages while staying safe under concurrency.
$rxVpnbotReplayDir = sys_get_temp_dir() . '/redfox_vpnbot_updates';
if (!is_dir($rxVpnbotReplayDir) && !@mkdir($rxVpnbotReplayDir, 0700, true) && !is_dir($rxVpnbotReplayDir)) {
    error_log('[vpnbot] replay cache unavailable');
    http_response_code(503);
    exit;
}
@chmod($rxVpnbotReplayDir, 0700);
$rxVpnbotReplayKey = hash('sha256', (string)$ApiToken . '|' . (string)$rxVpnbotUpdateId);
$rxVpnbotReplayFile = $rxVpnbotReplayDir . '/' . $rxVpnbotReplayKey;
$rxVpnbotReplayHandle = @fopen($rxVpnbotReplayFile, 'x');
if (!is_resource($rxVpnbotReplayHandle)) {
    http_response_code(200);
    exit;
}
fwrite($rxVpnbotReplayHandle, (string)time());
fclose($rxVpnbotReplayHandle);
@chmod($rxVpnbotReplayFile, 0600);
if (random_int(1, 100) === 1) {
    $rxVpnbotCutoff = time() - 172800;
    foreach ((array)glob($rxVpnbotReplayDir . '/*') as $rxVpnbotOldFile) {
        if (is_file($rxVpnbotOldFile) && (int)@filemtime($rxVpnbotOldFile) < $rxVpnbotCutoff) @unlink($rxVpnbotOldFile);
    }
}
unset($rxVpnbotReplayKey, $rxVpnbotReplayFile, $rxVpnbotReplayHandle, $rxVpnbotReplayDir, $rxVpnbotCutoff, $rxVpnbotOldFile);
$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;


if (!is_numeric($from_id) || (string)(int)$from_id !== (string)$from_id) {
    error_log('[vpnbot] Rejected non-numeric from_id from webhook payload');
    http_response_code(200);
    exit;
}
$from_id = (int) $from_id;
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
$text =convertPersianNumbersToEnglish($text);
$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$datain = $update["callback_query"]["data"] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$callback_query_id = $update["callback_query"]["id"] ?? 0;
$inline_query_id = $update["inline_query"]["id"] ?? 0;
$query = $update["inline_query"]["query"] ?? 0;
