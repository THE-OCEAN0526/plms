<?php
include_once __DIR__ . '/../classes/Dashboard.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class DashboardController {
    private $db;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->auth = new AuthMiddleware($db);
    }

    // GET /api/dashboard/summary
    public function summary() {
        // 驗證 Token，取得當前登入者資訊
        $currentUser = $this->auth->authenticate(); 
        
        $dashboard = new Dashboard($this->db, $currentUser['id']);

        try {
            $response = [
                // 數據卡片區
                "stats" => $dashboard->getStats(),
                // 金額統計區
                "amounts" => $dashboard->getAmounts(),
                // 近期動態區
                "recent_activities" => $dashboard->getRecentActivity(),
                // 待辦與警示區
                "todos" => $dashboard->getTodos()
            ];

            http_response_code(200);
            echo json_encode($response);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "系統錯誤: " . $e->getMessage()]);
        }
    }
}
?>