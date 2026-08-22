<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/lib/IntegrationAuth.php';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
rx_require_integration_auth($pdo);
$webhookRate = redfox_login_rate_check('panel-event-webhook', redfox_client_ip(), 120, 60);
if (empty($webhookRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($webhookRate['retry_after'] ?? 60)));
    http_response_code(429);
    exit;
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    http_response_code(415);
    exit;
}
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength === false || $contentLength < 2 || $contentLength > 65536) {
    http_response_code(413);
    exit;
}

require_once __DIR__ . '/botapi.php';
require_once __DIR__ . '/panels.php';
require_once __DIR__ . '/function.php';

$ManagePanel = new ManagePanel();
$reportcron = select("topicid","idreport","report","reportcron","select")['idreport'];
$textservice = select("textbot","text","id_text","text_Purchased_services","select")['text'];
$setting = select("setting", "*");
function rxWebhookInvoiceByUsername(PDO $pdo, string $username): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM invoice WHERE username=:username ORDER BY time_sell DESC LIMIT 2');
    $stmt->execute([':username' => $username]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Without a provider-side panel identifier, an ambiguous username must fail closed.
    return count($rows) === 1 ? $rows[0] : false;
}
$rawWebhookBody = file_get_contents("php://input", false, null, 0, 65537);
if (!is_string($rawWebhookBody) || strlen($rawWebhookBody) > 65536) {
    http_response_code(413);
    exit;
}
$decodedBody = json_decode($rawWebhookBody, true, 32, JSON_BIGINT_AS_STRING);
if (!is_array($decodedBody) || json_last_error() !== JSON_ERROR_NONE || count($decodedBody) !== 1 || !isset($decodedBody[0]) || !is_array($decodedBody[0])) {
    if (function_exists('rx_log_event')) {
        rx_log_event('WEBHOOK_BAD_BODY', 'Webhook body was not the expected [object] shape', [
            'remote_ip' => redfox_client_ip(),
        ]);
    }
    http_response_code(400);
    return;
}
$data = $decodedBody[0];
if (!isset($data['action']) || !is_string($data['action'])) {
    http_response_code(400);
    return;
}
$allowedActions = ['reached_usage_percent', 'reached_days_left', 'user_expired', 'user_limited'];
if (!in_array($data['action'], $allowedActions, true)) {
    http_response_code(204);
    return;
}
if (!isset($data['username'], $data['user']) || !is_string($data['username']) || !is_array($data['user'])
    || !preg_match('/^[A-Za-z0-9_.@-]{1,128}$/', $data['username'])) {
    http_response_code(400);
    return;
}
$rxWebhookUserStatus = '';
if (in_array($data['action'], ['reached_usage_percent', 'reached_days_left'], true)) {
    $rxWebhookUserStatusRaw = $data['user']['status'] ?? '';
    if (!is_string($rxWebhookUserStatusRaw) || strlen($rxWebhookUserStatusRaw) > 64) {
        http_response_code(400);
        return;
    }
    $rxWebhookUserStatus = htmlspecialchars($rxWebhookUserStatusRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if ($data['action'] === 'reached_usage_percent'
    && (!isset($data['user']['data_limit'], $data['user']['used_traffic'])
        || !is_numeric($data['user']['data_limit']) || !is_numeric($data['user']['used_traffic']))) {
    http_response_code(400);
    return;
}
if ($data['action'] === 'reached_days_left'
    && (!isset($data['user']['expire']) || !is_numeric($data['user']['expire'])
        || (float)$data['user']['expire'] < 0 || (float)$data['user']['expire'] > 4102444800)) {
    http_response_code(400);
    return;
}
if ($data['action'] === 'reached_usage_percent'
    && ((float)$data['user']['data_limit'] < 0 || (float)$data['user']['data_limit'] > 1.0e18
        || (float)$data['user']['used_traffic'] < 0 || (float)$data['user']['used_traffic'] > 1.0e18)) {
    http_response_code(400);
    return;
}
if (in_array($data['action'], ['user_expired', 'user_limited'], true)) {
    $rxResetStrategy = $data['user']['data_limit_reset_strategy'] ?? '';
    if (!is_string($rxResetStrategy) || strlen($rxResetStrategy) > 64) {
        http_response_code(400);
        return;
    }
    if ($rxResetStrategy === 'no_reset') {
        $rxProxies = $data['user']['proxies'] ?? null;
        if (!is_array($rxProxies) || count($rxProxies) > 64) {
            http_response_code(400);
            return;
        }
        $rxProxiesJson = json_encode($rxProxies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($rxProxiesJson) || strlen($rxProxiesJson) > 16384) {
            http_response_code(400);
            return;
        }
    }
}
// Suppress immediate provider retries atomically so users are not notified twice.
$rxWebhookReplayDir = sys_get_temp_dir() . '/redfox_panel_webhooks';
if (!is_dir($rxWebhookReplayDir) && !@mkdir($rxWebhookReplayDir, 0700, true) && !is_dir($rxWebhookReplayDir)) {
    http_response_code(503);
    return;
}
@chmod($rxWebhookReplayDir, 0700);
$rxWebhookReplayFile = $rxWebhookReplayDir . '/' . hash('sha256', $rawWebhookBody);
$rxWebhookReplayHandle = @fopen($rxWebhookReplayFile, 'c+');
if (!is_resource($rxWebhookReplayHandle) || !flock($rxWebhookReplayHandle, LOCK_EX)) {
    if (is_resource($rxWebhookReplayHandle)) fclose($rxWebhookReplayHandle);
    http_response_code(503);
    return;
}
rewind($rxWebhookReplayHandle);
$rxWebhookSeenAt = (int)trim((string)stream_get_contents($rxWebhookReplayHandle));
if ($rxWebhookSeenAt > 0 && time() - $rxWebhookSeenAt < 300) {
    flock($rxWebhookReplayHandle, LOCK_UN);
    fclose($rxWebhookReplayHandle);
    http_response_code(204);
    return;
}
ftruncate($rxWebhookReplayHandle, 0);
rewind($rxWebhookReplayHandle);
fwrite($rxWebhookReplayHandle, (string)time());
fflush($rxWebhookReplayHandle);
flock($rxWebhookReplayHandle, LOCK_UN);
fclose($rxWebhookReplayHandle);
@chmod($rxWebhookReplayFile, 0600);
if($data['action'] == "reached_usage_percent"){ 
    $line = $data['username'];
    $invoice = rxWebhookInvoiceByUsername($pdo, $line);
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = select("user","*","id",$invoice['id_user'],"select");
    $data = $data['user'];
    $output =  $data['data_limit'] - $data['used_traffic'];
    $RemainingVolume = formatBytes($output);
    $data_limit = formatBytes($data['data_limit']);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💊 تمدید سرویس", 'callback_data' => 'extend_' . $invoice['id_invoice']],
            ],
        ]
    ]);
    $text = "با سلام خدمت شما کاربر گرامی 👋
🚨 از حجم سرویس $line تنها $RemainingVolume باقی مانده است. لطفاً در صورت تمایل برای تمدید سرویستون از طریق بخش «{$textservice}» اقدام بفرمایین";
if(intval($user['status_cron']) != 0){
    sendmessage($invoice['id_user'], $text, $Response, 'HTML');
}
    $text_report = "📌 اطلاعیه کرون حجم

نام کاربری سرویس :‌ <code>$line</code>
آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code>
وضعیت سرویس : {$rxWebhookUserStatus}
حجم باقی مانده : $RemainingVolume
حجم کل سرویس : $data_limit";
    if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    update("invoice", "Status", "sendedwarn", "id_invoice", $invoice['id_invoice']);
}
elseif ($data['action'] == "reached_days_left"){
    $line = $data['username'];
    $invoice = rxWebhookInvoiceByUsername($pdo, $line);
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = select("user","*","id",$invoice['id_user'],"select");
    $data = $data['user'];
    $timeservice = $data['expire'] - time();
    $day = intval($timeservice / 86400);
    if($day <=0){
        $day = intval($timeservice / 3600) . "ساعت";
    }else{
        $day = $day. "روز";
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💊 تمدید سرویس", 'callback_data' => 'extend_' . $invoice['id_invoice']],
            ],
        ]
    ]);
    $text = "با سلام خدمت شما کاربر گرامی 👋
📌 از مهلت زمانی استفاده از سرویس {$invoice['username']} فقط $day باقی مانده است. لطفاً در صورت تمایل برای تمدید این سرویس، از طریق بخش «{$textservice}» اقدام بفرمایین. با تشکر از همراهی شما";
if(intval($user['status_cron']) != 0){
    sendmessage($invoice['id_user'], $text, $Response, 'HTML');
}
    $text_report = "📌 اطلاعیه کرون زمان

نام کاربری سرویس :‌ <code>{$line}</code>
آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code>
وضعیت سرویس : {$rxWebhookUserStatus}
تعداد روز باقی مانده ‌:‌$day";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
            }
        update("invoice", "Status", "sendedwarn", "id_invoice", $invoice['id_invoice']);
}
elseif(in_array($data['action'],["user_expired","user_limited"])){
        $line = $data['username'];
        $invoice = rxWebhookInvoiceByUsername($pdo, $line);
        if($invoice == false)return;
        if($invoice['name_product'] == "سرویس تست")return;
        $panel = select("marzban_panel","*","name_panel",$invoice['Service_location'],"select");
        if (!is_array($panel)) return;
        $data = $data['user'];
        if(($panel['inboundstatus'] ?? '') == "oninbounddisable"){
        if($rxResetStrategy == "no_reset"){
        $inbound = array_map('trim', explode("*", (string)($panel['inbound_deactive'] ?? ''), 2));
        if (count($inbound) !== 2 || $inbound[0] === '' || $inbound[1] === '') return;
        update("invoice", "uuid", $rxProxiesJson, "id_invoice", $invoice['id_invoice']);
        $proxies = [];
        $inbounds = [];
        $proxies[$inbound[0]] = new stdClass();
        $inbounds[$inbound[0]][] = $inbound[1];
        $configs  = array(
            "proxies" => $proxies,
            "inbounds" => $inbounds
            );
        $ManagePanel->Modifyuser($line,$panel['name_panel'],$configs);
         }
    }

}


