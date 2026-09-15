<?php
include('config.php');



function alireza_cookie_path()
{
    static $path = null;
    if ($path === null) {
        try {
            $entropy = bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $entropy = uniqid('', true);
        }
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'alireza_cookie_' . getmypid() . '_' . $entropy . '.txt';
    }
    return $path;
}


if (!function_exists('alireza_tls_verifypeer')) {
    function alireza_tls_verifypeer() {
        return defined('BOT_PANEL_VERIFY_TLS') && BOT_PANEL_VERIFY_TLS;
    }
}
if (!function_exists('alireza_tls_verifyhost')) {
    function alireza_tls_verifyhost() {
        return alireza_tls_verifypeer() ? 2 : 0;
    }
}


function loginalireza($url,$username,$password){
$curl = curl_init();
if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
curl_setopt_array($curl, array(
  CURLOPT_URL => $url.'/login',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT_MS => 6000,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => "username=$username&password=$password",
  CURLOPT_COOKIEJAR => alireza_cookie_path(),
));
$rxPolicyUrl = isset($marzban_list_get['url_panel']) ? (string)$marzban_list_get['url_panel'] : (string)$url;
redfox_apply_panel_curl_url_policy($curl, $rxPolicyUrl);
$response = curl_exec($curl);
if (curl_errno($curl)) {
        $token = [];
        $token['error'] = 'curl_errno_' . (int)curl_errno($curl);
        curl_close($curl);
        return $token;
    }
curl_close($curl);
return json_decode($response,true);
}
function get_useralireza($username,$namepanel){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    if(!is_array($marzban_list_get)) return [];
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return [];
    $usernameac = $username;
    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => alireza_cookie_path(),
));
    $output = [];
    $rxPolicyUrl = isset($marzban_list_get['url_panel']) ? (string)$marzban_list_get['url_panel'] : (string)$url;
    redfox_apply_panel_curl_url_policy($curl, $rxPolicyUrl);
    $response = curl_exec($curl);
    curl_close($curl);
    @unlink(alireza_cookie_path());
    if($response === false)return [];
    $decoded = json_decode($response,true);
    if(!is_array($decoded) || !isset($decoded['obj']) || !is_array($decoded['obj'])) return [];
    foreach ($decoded['obj'] as $client){
        if($client['remark'] == $usernameac){
            $output = $client;
            break;
        }
    }
    return $output;
}
function checkportalireza($port,$namepanel){

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return false;
    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');

curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => alireza_cookie_path(),
));
redfox_apply_panel_curl_url_policy($curl, (string)$marzban_list_get['url_panel']);
    $raw = curl_exec($curl);
    curl_close($curl);
    @unlink(alireza_cookie_path());
    if($raw === false) return false;
    $decoded = json_decode($raw, true);
    if(!is_array($decoded) || !isset($decoded['obj']) || !is_array($decoded['obj'])) return false;
    foreach ($decoded['obj'] as $client){
        if(isset($client['port']) && (int)$client['port'] === (int)$port){
            return true;
        }
    }
    return false;
}

function addinboundalireza($namepanel, $usernameac, $Port, $Expire,$Total, $Uuid, $Flow){
    $protocol = select("marzban_panel", "*", "name_panel", $namepanel,"select")['code_panel'];
    $protocol = select("x_ui", "*", "codepanel", $protocol,"select");
    $randomstring = random_bytes(3);
    $subId = bin2hex(random_bytes(8));
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $Allowedusername = get_useralireza($usernameac,$namepanel);
    $checkport = checkportalireza($Port,$namepanel);
    if (isset($Allowedusername['remark'])) {
        $random_number = rand(1000000, 9999999);
        $username_ac = $usernameac . $random_number;
    }
    if($checkport){
        $port =  rand(0, 65530);
    }
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    $email = bin2hex(random_bytes(6));
    if(isset($loginalirezapanel['error']))return;
    $config = array(
        'enable' => true,
        'remark' => $usernameac,
        'listen' => '',
        'port' => $Port,
        'protocol' => $protocol['protocol'],
        'expiryTime' => $Expire,
        "total" => $Total,
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $Uuid,
                "flow" => $Flow,
                "email" => $email,
                "totalGB" => $Total,
                "expiryTime" => $Expire,
                "enable" => true,
                "tgId" => "",
                "subId" => $subId,
                "reset" => 0
            )),
            'decryption' => 'none',
            'fallbacks' => array(),
        )),
        'streamSettings' => $protocol['setting'],
        'sniffing' => json_encode(array(
            'enabled' => true,
            'destOverride' => array('http', 'tls','quic','fakedns'),
        )),
    );

    $configpanel = json_encode($config,true);

    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
    curl_setopt_array($curl, array(
        CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/add',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $configpanel,
        CURLOPT_COOKIEFILE => alireza_cookie_path(),
        CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
        CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/json',
        ),
    ));

    $rxPolicyUrl = isset($marzban_list_get['url_panel']) ? (string)$marzban_list_get['url_panel'] : (string)$url;
redfox_apply_panel_curl_url_policy($curl, $rxPolicyUrl);
$response = curl_exec($curl);

    curl_close($curl);
    @unlink(alireza_cookie_path());
    return json_decode($response, true);
}
function updateinboundalireza($namepanel, $inboundid,array $config){

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return;
    $configpanel = json_encode($config,true);

    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
    curl_setopt_array($curl, array(
        CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/update/'.$inboundid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $configpanel,
        CURLOPT_COOKIEFILE => alireza_cookie_path(),
        CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
        CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/json',
        ),
    ));

    $rxPolicyUrl = isset($marzban_list_get['url_panel']) ? (string)$marzban_list_get['url_panel'] : (string)$url;
redfox_apply_panel_curl_url_policy($curl, $rxPolicyUrl);
$response = curl_exec($curl);

    curl_close($curl);
    @unlink(alireza_cookie_path());
    return json_decode($response, true);
}
function ResetUserDataUsagealireza($usernamepanel, $namepanel){

    $data_user = get_useralireza($usernamepanel,$namepanel);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return;
    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/resetAllClientTraffics/'.$data_user['id'],
  CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_COOKIEFILE => alireza_cookie_path(),
        CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
        CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/json',
        ),

));

$rxPolicyUrl = isset($marzban_list_get['url_panel']) ? (string)$marzban_list_get['url_panel'] : (string)$url;
redfox_apply_panel_curl_url_policy($curl, $rxPolicyUrl);
$response = curl_exec($curl);
curl_close($curl);
@unlink(alireza_cookie_path());
}


function remove_useralireza($location,$username){

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return;
    $data_user = get_useralireza($username,$location);
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return;
    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
    curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/del/'.$data_user['id'],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_COOKIEFILE => alireza_cookie_path(),
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json',
  ),
));

redfox_apply_panel_curl_url_policy($curl, (string)$marzban_list_get['url_panel']);
    $response = json_decode(curl_exec($curl),true);
curl_close($curl);
@unlink(alireza_cookie_path());
return $response;
}
function get_onlineuseralireza($name_panel,$username){

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $name_panel,"select");
    if(!is_array($marzban_list_get)) return "offline";
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return "offline";
    $userData = get_useralireza($username,$name_panel);
    if(!is_array($userData) || empty($userData['settings'])) return "offline";
    $clients = json_decode($userData['settings'],true);
    if(!is_array($clients) || !isset($clients['clients'][0]['email'])) return "offline";
    $userEmail = $clients['clients'][0]['email'];
    $loginalirezapanel = loginalireza($marzban_list_get['url_panel'],$marzban_list_get['username_panel'],$marzban_list_get['password_panel']);
    if(isset($loginalirezapanel['error']))return "offline";
    $curl = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($curl, 'panel');
curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/onlines',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => alireza_cookie_path(),
));
redfox_apply_panel_curl_url_policy($curl, (string)$marzban_list_get['url_panel']);
    $response = curl_exec($curl);
    curl_close($curl);
    @unlink(alireza_cookie_path());
    if($response === false)return "offline";
    $decoded = json_decode($response,true);
    if(!is_array($decoded))return "offline";
    if(in_array($userEmail,$decoded))return "online";
    return "offline";

}
function extendalireza($Metode,$namepanel,$usernamepanel,$Service_time,$data_limit = null){
    $data_user = get_useralireza($usernamepanel, $namepanel);
    $clients = json_decode($data_user['settings'],true)['clients'][0];
    $subId = bin2hex(random_bytes(8));
    if($Metode == "ریست حجم و زمان"){
    ResetUserDataUsagealireza($usernamepanel, $namepanel);
    $date = strtotime("+" . $Service_time . "day");
    $newDate = strtotime(date("Y-m-d H:i:s", $date))*1000;
    $data_limit = intval($data_limit) * pow(1024, 3);
    $config = array(
        'enable' => true,
        'remark' => $data_user['remark'],
        'listen' => '',
        'port' => $data_user['port'],
        'protocol' => $data_user['protocol'],
        'expiryTime' => $newDate,
        'total' => $data_limit,
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $clients['id'],
                "flow" => $clients['flow'],
                "email" => $clients['email'],
                "totalGB" => $data_limit,
                "expiryTime" => $newDate,
                "enable" => true,
                "subId" => $subId,
            )),
            'decryption' => 'none',
            'fallbacks' => array(),
    )
),
        'streamSettings' => $data_user['streamSettings'],
        'sniffing' => json_encode(array(
            'enabled' => true,
            'destOverride' => array('http', 'tls','quic','fakedns'),
        )),
);
    $updateinbound = updateinboundalireza($namepanel, $data_user['id'],$config);
    }elseif($Metode == "اضافه شدن زمان و حجم به ماه بعد"){
    $timeservice = ($data_user['expiryTime']/1000) - time();
    $day = floor($timeservice / 86400) + 1;
    if($day >= 0){
    $date = strtotime("+" . $Service_time . "day",$data_user['expiryTime']/1000);
    }else{
    $date = strtotime("+" . $Service_time . "day");
    }
    $newDate = strtotime(date("Y-m-d H:i:s", $date))*1000;
    if($data_limit == 0){
    $data_limit = 0;
    }else{
    $data_limit = $data_user['total'] + (intval($data_limit) * pow(1024, 3));
    }
    $config = array(
        'enable' => true,
        'remark' => $data_user['remark'],
        'listen' => '',
        'port' => $data_user['port'],
        'protocol' => $data_user['protocol'],
        'expiryTime' => $newDate,
        'total' => $data_limit,
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $clients['id'],
                "flow" => $clients['flow'],
                "email" => $clients['email'],
                "totalGB" => $data_limit,
                "expiryTime" => $newDate,
                "enable" => true,
                "subId" => $subId,
            )),
            'decryption' => 'none',
            'fallbacks' => array(),
    )
),
        'streamSettings' => $data_user['streamSettings'],
        'sniffing' => json_encode(array(
            'enabled' => true,
            'destOverride' => array('http', 'tls','quic','fakedns'),
        )),
);
    $updateinbound = updateinboundalireza($namepanel, $data_user['id'],$config);
    }
    elseif($Metode == "ریست شدن حجم و اضافه شدن زمان"){
    $timeservice = ($data_user['expiryTime']/1000) - time();
    $day = floor($timeservice / 86400) + 1;
    ResetUserDataUsagealireza($usernamepanel, $namepanel);
    if($day >= 0){
    $date = strtotime("+" . $Service_time . "day",$data_user['expiryTime']/1000);
    }else{
    $date = strtotime("+" . $Service_time . "day");
    }
    $newDate = strtotime(date("Y-m-d H:i:s", $date))*1000;
    $config = array(
        'enable' => true,
        'remark' => $data_user['remark'],
        'listen' => '',
        'port' => $data_user['port'],
        'protocol' => $data_user['protocol'],
        'expiryTime' => $newDate,
        'total' => $data_user['total'],
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $clients['id'],
                "flow" => $clients['flow'],
                "email" => $clients['email'],
                "totalGB" => $clients['total'],
                "expiryTime" => $newDate,
                "enable" => true,
                "subId" => $subId,
            )),
            'decryption' => 'none',
            'fallbacks' => array(),
    )
),
        'streamSettings' => $data_user['streamSettings'],
        'sniffing' => json_encode(array(
            'enabled' => true,
            'destOverride' => array('http', 'tls','quic','fakedns'),
        )),
);
    $updateinbound = updateinboundalireza($namepanel, $data_user['id'],$config);
    }
    elseif($Metode == "ریست زمان و اضافه کردن حجم قبلی"){
    $date = strtotime("+" . $Service_time . "day");
    $newDate = strtotime(date("Y-m-d H:i:s", $date))*1000;
    if($data_limit == 0){
     $data_limit = 0;
    }else{
    $data_limit = $data_user['total'] + intval($data_limit) * pow(1024, 3);
    }
    $config = array(
        'enable' => true,
        'remark' => $data_user['remark'],
        'listen' => '',
        'port' => $data_user['port'],
        'protocol' => $data_user['protocol'],
        'expiryTime' => $newDate,
        'total' => $data_limit,
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" =>$clients['id'],
                "flow" => $clients['flow'],
                "email" => $clients['email'],
                "totalGB" => $data_limit,
                "expiryTime" => $newDate,
                "enable" => true,
                "subId" => $subId,
            )),
            'decryption' => 'none',
            'fallbacks' => array(),
    )
),
        'streamSettings' => $data_user['streamSettings'],
        'sniffing' => json_encode(array(
            'enabled' => true,
            'destOverride' => array('http', 'tls','quic','fakedns'),
        )),
);
    $updateinbound = updateinboundalireza($namepanel, $data_user['id'],$config);
    }
    update("invoice",'user_info',null,"username",$usernamepanel);
    update("invoice",'uuid',null,"username",$usernamepanel);
    update("invoice",'Status',"active","username",$usernamepanel);
}

