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
$method = $_SERVER['REQUEST_METHOD'];

$data = sanitize_recursive($data);
$stmt = $pdo->prepare("INSERT IGNORE INTO logs_api (header,data,time,ip,actions) VALUES (:header,:data,:time,:ip,:actions)");
$stmt->execute([
    ':header' => json_encode($headrs),
    ':data' => json_encode($data),
    ':time' => date('Y/m/d H:i:s'),
    ':ip' => redfox_client_ip(),
    ':actions' => (string)($data['actions'] ?? ''),
]);
switch ($data['actions']) {
    case 'services':
        if($method != "GET"){
    echo json_encode(array(
        'status' => false,
        'msg' => "method invalid; is mthod must GET"
        ));
    return;
}


        $limitVal = 0;
        if (isset($data['limit']) && is_numeric($data['limit'])) {
            $limitVal = max(0, min((int) $data['limit'], 10000));
        }
        if ($limitVal > 0) {
            $stmt = $pdo->prepare("SELECT id,id_user,username,time,price,type,status FROM service_other LIMIT :lim");
            $stmt->bindValue(':lim', $limitVal, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("SELECT id,id_user,username,time,price,type,status FROM service_other");
        }
        $stmt->execute();
        $users = $stmt->fetchAll();
        echo json_encode(array(
        'status' => true,
        'msg' => "Successful",
        'obj' => $users
        ));
        break;
    default:
        echo json_encode(array(
        'status' => false,
        'msg' => "Action Invalid"
        ));
        break;
}
