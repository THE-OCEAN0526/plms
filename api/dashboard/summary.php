<?php

include_once '../common.php';
include_once '../../config/Database.php';
include_once '../../classes/Dashboard.php';
include_once '../../classes/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();

$auth = new AuthMiddleware($db);
$currentUser = $auth->authenticate(); 


$dashboard = new Dashboard($db, $currentUser['id'], $currentUser['role']);

// 3. 獲取各項數據
try {
    $response = [
        "user_info" => [
            "name" => $currentUser['name'],
            "role" => $currentUser['role']
        ],
        "cards" => $dashboard->getStats(),       // 數據卡片
        "recent" => $dashboard->getRecentActivity(), // 近期動態
        "todos" => $dashboard->getTodos()        // 待辦事項
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "系統錯誤: " . $e->getMessage()]);
}
?>