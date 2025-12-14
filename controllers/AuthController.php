<?php
include_once __DIR__ . '/../classes/User.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class AuthController {
    private $db;
    private $user;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->auth = new AuthMiddleware($db);
    }

    // POST /api/auth/login
    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->staff_code) && !empty($data->password)) {
            $this->user->staff_code = $data->staff_code;
            $this->user->password = $data->password;

            if($this->user->login()) {
                http_response_code(200);
                echo json_encode([
                    "message" => "登入成功",
                    "data" => [
                        "id" => $this->user->id,
                        "staff_code" => $this->user->staff_code,
                        "name" => $this->user->name,
                        "theme" => $this->user->theme,
                        "token" => $this->user->api_token 
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["message" => "登入失敗，帳號或密碼錯誤"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "登入失敗，資料不完整"]);
        }
    }

    // POST /api/auth/register
    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->staff_code) && !empty($data->name) && !empty($data->password)) {
            $this->user->staff_code = $data->staff_code;
            $this->user->name = $data->name;
            $this->user->password = $data->password;

            if($this->user->staffCodeExists()) {
                http_response_code(400);
                echo json_encode(["message" => "此帳號已存在"]);
            } else {
                if($this->user->create()) {
                    http_response_code(201);
                    echo json_encode([
                        "message" => "註冊成功",
                        "data" => [
                            "id" => $this->user->id,
                            "staff_code" => $this->user->staff_code,
                            "name" => $this->user->name,
                            "token" => $this->user->api_token // 這裡就有 Token 了
                        ]
                    ]);
                } else {
                    http_response_code(503);
                    echo json_encode(["message" => "系統錯誤"]);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整"]);
        }
    }

    public function updateTheme() {
        // 1. 驗證 Token
        $currentUser = $this->auth->authenticate(); 
        
        $data = json_decode(file_get_contents("php://input"));

        // 2. 檢查參數
        if (!empty($data->theme) && in_array($data->theme, ['light', 'dark'])) {
            // 設定要更新的 User ID
            $this->user->id = $currentUser['id'];
            
            // 3. 呼叫 Model 更新
            if ($this->user->updateTheme($data->theme)) {
                http_response_code(200);
                echo json_encode(["message" => "樣式已儲存"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "更新失敗"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "參數錯誤 (theme 只能是 light 或 dark)"]);
        }
    } 
}
?>