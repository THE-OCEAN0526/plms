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
        $currentUser = $this->auth->authenticate(); 
        
        $dashboard = new Dashboard($this->db, $currentUser['id'], $currentUser['role']);

        try {
            $response = [
                "user_info" => [
                    "name" => $currentUser['name'],
                    "role" => $currentUser['role']
                ],
                "cards" => $dashboard->getStats(),
                "recent" => $dashboard->getRecentActivity(),
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