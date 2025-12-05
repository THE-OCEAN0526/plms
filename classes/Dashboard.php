<?php
class Dashboard {
    private $conn;
    private $user_id;
    private $is_admin;

    public function __construct($db, $user_id, $role) {
        $this->conn = $db;
        $this->user_id = $user_id;
        $this->is_admin = ($role === 'admin');
    }

    // 1. 取得數據卡片
    public function getStats() {
        $stats = [
            "total_value" => 0,
            "count_total" => 0,
            "count_in_use" => 0,
            "count_maintenance" => 0,
            "count_scrapped" => 0
        ];

        // 先查詢資產總值、數量和狀態統計
        $query = "SELECT 
                    SUM(b.unit_price) as total_value,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN i.status = '使用中' THEN 1 ELSE 0 END) as in_use,
                    SUM(CASE WHEN i.status = '維護' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN i.status = '報廢' THEN 1 ELSE 0 END) as scrapped
                  FROM asset_items i
                  JOIN asset_batches b ON i.batch_id = b.id";


        if (!$this->is_admin) {
            $query .= " WHERE i.custodian_id = :uid";
        }

        $stmt = $this->conn->prepare($query);

        if (!$this->is_admin) {
            $stmt->bindParam(":uid", $this->user_id);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $stats["total_value"] = $row['total_value'] ?? 0;
            $stats["count_total"] = $row['total_count'] ?? 0;
            $stats["count_in_use"] = $row['in_use'] ?? 0;
            $stats["count_maintenance"] = $row['maintenance'] ?? 0;
            $stats["count_scrapped"] = $row['scrapped'] ?? 0;
        }

        return $stats;
    }

    // 2. 取得近期動態
    public function getRecentActivity() {
        $query = "SELECT t.action_type, t.action_date, t.expected_return_date, t.note, b.asset_name, i.sub_no, t.new_status
                  FROM asset_transactions t
                  JOIN asset_items i ON t.item_id = i.id
                  JOIN asset_batches b ON i.batch_id = b.id";

        if (!$this->is_admin) {
            $query .= " WHERE t.actor_id = :uid OR t.new_custodian_id = :uid OR t.prev_custodian_id = :uid";
        }

        $query .= " ORDER BY t.action_date DESC LIMIT 5";

        $stmt = $this->conn->prepare($query);
        if (!$this->is_admin) {
            $stmt->bindParam(":uid", $this->user_id);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. 取得待辦事項
    public function getTodos() {
        $todos = [];

        // ==========================================
        // A. 檢查維修逾期 (超過 30 天)
        // ==========================================
        // 邏輯：狀態='維護' 且 '最新一筆維修單' 的日期超過 30 天
        $queryMaint = "SELECT i.id, b.asset_name, m.maintain_date, DATEDIFF(NOW(), m.maintain_date) as days
                       FROM asset_items i
                       JOIN asset_batches b ON i.batch_id = b.id
                       JOIN asset_maintenance m ON m.item_id = i.id 
                       WHERE i.status = '維護' 
                       AND m.id = (
                           SELECT MAX(id) FROM asset_maintenance sub_m 
                           WHERE sub_m.item_id = i.id
                       )
                       AND m.maintain_date < DATE_SUB(NOW(), INTERVAL 30 DAY)";

        if (!$this->is_admin) {
            $queryMaint .= " AND i.custodian_id = :uid";
        }
        
        $stmtMaint = $this->conn->prepare($queryMaint);
        if (!$this->is_admin) {
            $stmtMaint->bindParam(":uid", $this->user_id);
        }
        $stmtMaint->execute();

        while ($row = $stmtMaint->fetch(PDO::FETCH_ASSOC)) {
            $todos[] = [
                "type" => "warning", // 黃色警告
                "message" => "維修逾期：[{$row['asset_name']}] 已送修 {$row['days']} 天尚未歸還。"
            ];
        }

        // ==========================================
        // B. 檢查借用逾期 (超過預計歸還日) ★ 新增功能
        // ==========================================
        // 邏輯：狀態='使用中' 且 '最新一筆借用紀錄' 的預計歸還日 < 今天
        $queryBorrow = "SELECT i.id, b.asset_name, t.expected_return_date, DATEDIFF(NOW(), t.expected_return_date) as overdue_days
                        FROM asset_items i
                        JOIN asset_batches b ON i.batch_id = b.id
                        JOIN asset_transactions t ON t.item_id = i.id
                        WHERE i.status = '使用中'
                        AND t.id = (
                            SELECT MAX(id) FROM asset_transactions sub_t
                            WHERE sub_t.item_id = i.id AND sub_t.action_type = '借用'
                        )
                        AND t.expected_return_date IS NOT NULL
                        AND t.expected_return_date < CURDATE()"; // 小於今天代表逾期

        if (!$this->is_admin) {
            $queryBorrow .= " AND i.custodian_id = :uid";
        }

        $stmtBorrow = $this->conn->prepare($queryBorrow);
        if (!$this->is_admin) {
            $stmtBorrow->bindParam(":uid", $this->user_id);
        }
        $stmtBorrow->execute();

        while ($row = $stmtBorrow->fetch(PDO::FETCH_ASSOC)) {
            $todos[] = [
                "type" => "error", // 紅色警告 (借用逾期通常比較嚴重)
                "message" => "歸還逾期：[{$row['asset_name']}] 應於 {$row['expected_return_date']} 歸還 (已逾期 {$row['overdue_days']} 天)。"
            ];
        }

        return $todos;
    }
}
?>