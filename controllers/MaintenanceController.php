<?php
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

    // POST /api/maintenances (送修)
    public function store() {
        $this->auth->authenticate();

        $data = json_decode(file_get_contents("php://input"));

        // 驗證必填欄位 (送修時不需要 result)
        if (empty($data->item_id) || empty($data->send_date) || empty($data->action_type)) {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整 (需 item_id, send_date, action_type)"]);
            return;
        }

        $this->maintenance->item_id = $data->item_id;
        $this->maintenance->send_date = $data->send_date;
        $this->maintenance->action_type = $data->action_type;
        $this->maintenance->vendor = $data->vendor; // (廠商或學生名)

        if ($this->maintenance->create()) {
            http_response_code(201);
            echo json_encode([
                "message" => "送修單已建立",
                "id" => $this->maintenance->id
            ]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "送修失敗"]);
        }
    }

    // PUT /api/maintenances/{id} (結案/更新)
    public function update($params) {
        $this->auth->authenticate();

        $id = $params['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "缺少 ID"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        $this->maintenance->id = $id;
        $this->maintenance->cost = $data->cost ?? 0;
        $this->maintenance->finish_date = $data->finish_date ?? null;
        $this->maintenance->maintain_result = $data->maintain_result ?? ''; 
        $this->maintenance->result_status = $data->result_status ?? '';

        // 檢查：如果要結案 (有 finish_date)，則必須填寫 結果說明 與 狀態判定
        if (!empty($this->maintenance->finish_date)) {
            if (empty($this->maintenance->maintain_result) || empty($this->maintenance->result_status)) {
                http_response_code(400);
                echo json_encode(["message" => "結案時必須填寫 '處理說明(maintain_result)' 與 '狀態判定(result_status)'"]);
                return;
            }
        }

        if ($this->maintenance->update()) {
            http_response_code(200);
            echo json_encode(["message" => "維修單更新成功"]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "更新失敗"]);
        }
    }

    // DELETE /api/maintenances/{id} (取消/刪除)
    public function destroy($params) {
        $this->auth->authenticate();

        $id = $params['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "缺少 ID"]);
            return;
        }

        $this->maintenance->id = $id;
        
        if ($this->maintenance->cancel()) {
            http_response_code(200);
            echo json_encode(["message" => "維修單已刪除，資產狀態已復原"]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "刪除失敗"]);
        }
    }

    // GET /api/maintenances
    public function index () {
        // 接收參數
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $filters = [
            'keyword' => $_GET['keyword'] ?? null, // 搜尋廠商或資產
            'status' => $_GET['status'] ?? null    // 'active' 或 'finished'
        ];

        try {
            $result = $this->maintenance->readAll($filters, $page, $limit);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "查詢失敗", "error" => $e->getMessage()]);
        }
    }

    // GET /api/maintenances/{id}
    public function show($id) {
        try {
            $data = $this->maintenance->readOne($id);

            if ($data) {
                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "找不到此維修單"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "查詢失敗", "error" => $e->getMessage()]);
        }
    }
}