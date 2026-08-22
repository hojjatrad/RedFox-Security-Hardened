<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/IntegrationAuth.php';
$data = rx_require_integration_json($pdo);
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');


$headrs = rx_integration_redacted_headers();
$setting = select("setting", "*");



$stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
$headerJson  = json_encode($headrs);
$dataJson    = json_encode($data);
$nowStr      = date('Y/m/d H:i:s');
$ipStr       = redfox_client_ip();
$actionStr   = $data['actions'] ?? 'log';
$stmt->bindParam(':header',  $headerJson);
$stmt->bindParam(':data',    $dataJson);
$stmt->bindParam(':time',    $nowStr);
$stmt->bindParam(':ip',      $ipStr);
$stmt->bindParam(':actions', $actionStr);
$stmt->execute();


$count_user = select("user","*",null,null,"count");
$stmt = $pdo->prepare("SELECT * FROM user WHERE agent != 'f'");
$stmt->execute();
$count_agent = $stmt->rowCount();
$count_invoice = select("invoice","*",null,null,"count");
echo json_encode(array(
    'count_user' => $count_user,
    'count_invoice' => $count_invoice,
    'count_agent' => $count_agent
    ));
