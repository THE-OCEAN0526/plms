<?php
// controllers/LocationController.php
include_once __DIR__ . '/../classes/Location.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class LocationController {
    private $db;
    private $locationModel;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->locationModel = new Location($db);
        $this->auth = new AuthMiddleware($db);
    }

    // GET /api/locations
    public function index() {
        // 1. 驗證登入 (依需求決定是否需要)
        $this->auth->authenticate();

        try {
            // 2. 呼叫 Model 撈資料
            $locations = $this->locationModel->getAll();

            // 3. 回傳 JSON
            http_response_code(200);
            echo json_encode([
                "message" => "取得地點列表成功",
                "data" => $locations
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "伺服器錯誤: " . $e->getMessage()]);
        }
    }
}
?>