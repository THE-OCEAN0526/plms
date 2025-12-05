<?php

// 不需要登入的 API，不用引入 Middleware。
include_once '../common.php';
include_once '../../config/Database.php';
include_once '../../classes/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->staff_code) && !empty($data->password)) {

    $user->staff_code = $data->staff_code;
    $user->password = $data->password;

    // 呼叫 User->login() 進行驗證與 Token 生成
    if($user->login()) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "登入成功",
            "data" => array(
                "id" => $user->id,
                "staff_code" => $user->staff_code,
                "name" => $user->name,
                "role" => $user->role,
                "token" => $user->api_token 
            )
        ));
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "登入失敗，帳號或密碼錯誤"));
    }

} else {
    http_response_code(400);
    echo json_encode(array("message" => "登入失敗，資料不完整"));
}

?>