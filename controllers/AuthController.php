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

    // GET /api/users
    public function index() {
        // 驗證登入，確保只有系統使用者能查看清單
        $this->auth->authenticate();

        try {
            $users = $this->user->getAll();
            http_response_code(200);
            echo json_encode([
                "message" => "取得使用者資源成功",
                "data" => $users
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "伺服器錯誤: " . $e->getMessage()]);
        }
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
        $data = json_decode(file_get_contents("php://input"), true);

        // 基本驗證
        if (empty($data['staff_code']) || empty($data['password']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(["message" => "請提供完整的註冊資訊"]);
            return;
        }

        $user = new User($this->db);
        try {
            $userId = $user->register($data);
            if ($userId) {
                http_response_code(201);
                echo json_encode(["message" => "註冊成功", "id" => $userId]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(["message" => $e->getMessage()]);
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

    public function updateProfile($params) {
    // 驗證身份並取得目前資料庫中的使用者資訊 ($user 包含 id, name, staff_code 等)
    $auth = new AuthMiddleware($this->db);
    $currentUser = $auth->authenticate();

    $data = json_decode(file_get_contents("php://input"), true);

    try {
        $userModel = new User($this->db);

        // 【彈性處理】如果前端沒傳某個欄位，則沿用資料庫現有的值
        $newName = !empty($data['name']) ? $data['name'] : $currentUser['name'];
        $newStaffCode = !empty($data['staff_code']) ? $data['staff_code'] : $currentUser['staff_code'];
        $newPassword = !empty($data['password']) ? $data['password'] : null;

        // 執行更新
        $success = $userModel->update(
            $currentUser['id'], 
            $newName, 
            $newStaffCode, 
            $newPassword
        );

        if ($success) {
            echo json_encode([
                "message" => "資料更新成功", 
                "success" => true,
                "data" => ["name" => $newName, "staff_code" => $newStaffCode]
            ]);
        } else {
            throw new Exception("更新失敗");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["message" => $e->getMessage()]);
    }
}
}
?>