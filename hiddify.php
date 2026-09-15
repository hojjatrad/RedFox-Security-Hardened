<?php
require_once __DIR__ . '/lib/Security.php';
require_once 'config.php';
require_once 'request.php';

function getdatauser($username,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    if(!is_array($marzban_list_get)) return [];
    $usernameac = $username;
    $url =  $marzban_list_get['url_panel'].'/api/v2/admin/user/';
    $ch = curl_init();
    if (function_exists('redfox_apply_curl_proxy')) redfox_apply_curl_proxy($ch, 'panel');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch,CURLOPT_TIMEOUT_MS, 4000);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . base64_encode("{$marzban_list_get['secret_code']}:")
    ));

    redfox_apply_panel_curl_url_policy($ch, $url);
    $output = curl_exec($ch);
    curl_close($ch);
    if($output === false) return [];
    $data_useer = json_decode($output, true);
    if(!is_array($data_useer)) return [];
    if(isset($data_useer['message']))return $data_useer;
    if(count($data_useer) == 0)return [];
    foreach($data_useer as $data){
        if(!isset($data['name']))continue;
        if($data['name'] == $username){
            return $data;
        }
    }
    return [];
}
function serverstatus($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $url =  $marzban_list_get['url_panel'].'/api/v2/admin/server_status/';
    $headers = array(
            'Authorization: Basic ' . base64_encode("{$marzban_list_get['secret_code']}:")
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    return $response;
}

function adduserhi($location,array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $url =  $marzban_list_get['url_panel'].'/api/v2/admin/user/';
    $payload = json_encode($data,true);
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Hiddify-API-Key: '.$marzban_list_get['secret_code']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($payload);
    return $response;
}

function updateuserhi($username,$location,array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $paneldata = getdatauser($username,$location);
    if(!is_array($paneldata) || empty($paneldata['uuid'])) return ['ok'=>false,'error'=>'user_not_found'];
    $url =  $marzban_list_get['url_panel'].'/api/v2/admin/user/'.$paneldata['uuid']."/";
    $payload = json_encode($data,true);
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Hiddify-API-Key: '.$marzban_list_get['secret_code']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->PATCH($payload);
    return $response;
}

function removeuserhi($location,$uuid)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $url =  $marzban_list_get['url_panel']."/api/v2/admin/user/$uuid/";
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Hiddify-API-Key: '.$marzban_list_get['secret_code']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->delete();
    return $response;
}

