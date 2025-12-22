<?php
// controllers/MaintenanceController.php
include_once __DIR__ . '/../classes/Maintenance.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class MaintenanceController {
    private $db;
    private $maintenance;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->maintenance = new Maintenance($db);
        $this->auth = new AuthMiddleware($db);
    }

    // =================================================================
    // 1. 取得維修列表 (GET /api/maintenances)
    // =================================================================
    public function index() {
        // 驗證登入 (視需求開啟)
        $currentUser = $this->auth->authenticate();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        // 接收前端傳來的篩選條件 (目前keyword是備胎)
        // 假設維修累積到5000筆以上，讓後端用keyword
        $filters = [
            'user_id' => $currentUser['id'], // 只能看自己的
            'keyword' => $_GET['keyword'] ?? null, // 搜尋廠商...
            'status' => $_GET['status'] ?? null    // 'active' (維修中) 或 'finished' (已結案)
        ];

        try {
            $result = $this->maintenance->readAll($filters, $page, $limit);
            echo json_encode($result);
        } catch (Exception $e) {
            $this->sendError(500, "查詢失敗: " . $e->getMessage());
        }
    }

    // =================================================================
    // 2. 讀取單筆資料 (GET /api/maintenances/{id})
    // =================================================================
    public function show($params) {
        $this->auth->authenticate();
        $id = $params['id'] ?? null;
        
        if (!$id) {
            $this->sendError(400, "缺少 ID");
            return;
        }

        try {
            $data = $this->maintenance->readOne($id);

            if ($data) {
                echo json_encode($data);
            } else {
                $this->sendError(404, "找不到此維修單");
            }
        } catch (Exception $e) {
            $this->sendError(500, "查詢失敗: " . $e->getMessage());
        }
    }

    // =================================================================
    // 3. 新增維修單 (POST /api/maintenances)
    // =================================================================
    public function create() {
        $currentUser = $this->auth->authenticate(); // 驗證登入並取得當前使用者資訊

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            $this->sendError(400, "無效的 JSON 資料");
            return;
        }

        // 基本欄位驗證
        if (empty($data['item_id']) || empty($data['action_type']) || empty($data['vendor'])) {
            $this->sendError(400, "缺少必要欄位 (item_id, action_type, vendor 為必填)");
            return;
        }

        // 將 Token 解析出來的使用者 ID 注入資料中
        $data['user_id'] = $currentUser['id'];
        // 直接把陣列傳給 Model
        $newId = $this->maintenance->create($data);

        if ($newId) {
            http_response_code(201);
            echo json_encode(["message" => "維修單建立成功", "id" => $newId]);
        } else {
            $this->sendError(500, "建立失敗，可能是該資產狀態不正確 (已在維修中?)");
        }
    }

    // =================================================================
    // 4. 更新/結案維修單 (PUT /api/maintenances/{id})
    // =================================================================
    public function update($params) {
        $this->auth->authenticate();

        $id = $params['id'] ?? null; // 取出 ID
        
        if (!$id) {
            $this->sendError(400, "缺少 ID");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // ★ 重點：Model 的 update 現在很聰明，
        // 傳 issue_description 就改描述，傳 finish_date 就結案，
        // 所以 Controller 只要負責傳遞就好。
        if ($this->maintenance->update($id, $data)) {
            echo json_encode(["message" => "更新成功"]);
        } else {
            $this->sendError(500, "更新失敗 (可能沒有欄位變更或 ID 不存在)");
        }
    }

    // =================================================================
    // 5. 刪除維修單 (DELETE /api/maintenances/{id})
    // =================================================================
    public function delete($params) {
        $this->auth->authenticate();

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->sendError(400, "缺少 ID");
            return;
        }

        if ($this->maintenance->delete($id)) {
            echo json_encode(["message" => "維修單已刪除 (資產狀態已還原)"]);
        } else {
            $this->sendError(500, "刪除失敗");
        }
    }

    // --- 輔助函式：回傳錯誤 ---
    private function sendError($code, $message) {
        http_response_code($code);
        echo json_encode(["message" => $message, "error" => $code]);
    }
}
?>