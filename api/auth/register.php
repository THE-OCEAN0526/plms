<?php

include_once '../common.php';
include_once '../../config/Database.php';
include_once '../../classes/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->staff_code) &&
    !empty($data->name) &&
    !empty($data->password)
) {
    // 設定使用者屬性
    $user->staff_code = $data->staff_code;
    $user->name = $data->name;
    $user->password = $data->password;

    if($user->staffCodeExists()) {
        http_response_code(400);
        echo json_encode(array("message" => "註冊失敗，此帳號(編號)已存在"));
    } else {
        // 建立新使用者
        try {
            if($user->create()) {
                http_response_code(201);
                echo json_encode(array("message" => "註冊成功"));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "註冊失敗，系統錯誤"));
            }
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(array("message" => "註冊失敗:" . $e->getMessage()));
        }
    }

}else {
    http_response_code(400);
    echo json_encode(array("message" => "註冊失敗，資料不完整"));
}