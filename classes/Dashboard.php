<?php
class Dashboard {
    private $conn;
    private $user_id;

    public function __construct($db, $user_id) {
        $this->conn = $db;
        $this->user_id = $user_id;
    }

    // 1. 取得數據卡片
    public function getStats() {
        $stats = [
            "total" => 0,
            "idle" => 0,        // 閒置
            "in_use" => 0,      // 使用中
            "borrowed" => 0,    // 借用中
            "repair" => 0,      // 維修中
            "scrapped" => 0,    // 報廢
            "lost" => 0         // 遺失
        ];

        // 先查詢資產總值、數量和狀態統計
        $query = "SELECT status, COUNT(*) as count 
                  FROM asset_items 
                  WHERE owner_id = :uid 
                  GROUP BY status";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":uid", $this->user_id);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count = intval($row['count']);
            $stats["total"] += $count; // 加總

            // 對應資料庫 Enum 到前端顯示的 key
            switch ($row['status']) {
                case '閒置':   $stats["idle"] = $count; break;
                case '使用中': $stats["in_use"] = $count; break;
                case '借用中': $stats["borrowed"] = $count; break;
                case '維修中': $stats["repair"] = $count; break;
                case '報廢':   $stats["scrapped"] = $count; break;
                case '遺失':   $stats["lost"] = $count; break;
            }
        }
        return $stats;
    }

    // 2. 取得近期動態 (三個月內的 asset_items 更新紀錄)
    public function getRecentActivity() {
        $query = "SELECT 
                    i.id, 
                    i.sub_no, 
                    i.status, 
                    i.updated_at, 
                    i.item_condition,
                    b.asset_name,
                    b.brand,
                    b.model
                  FROM asset_items i
                  JOIN asset_batches b ON i.batch_id = b.id
                  WHERE i.owner_id = :uid 
                    AND i.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                  ORDER BY i.updated_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":uid", $this->user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. 取得待辦事項與警示 (Todos)
    // A. 維修超過 30 天還沒回來的
    public function getTodos() {
        $todos = [];

        // A. 檢查維修逾期 (狀態='維修中' 且 送修日期 > 30 天)
        // 這裡需要 Join asset_maintenance 找最新的一筆送修單
        $queryRepair = "SELECT i.id, b.asset_name, m.send_date, DATEDIFF(NOW(), m.send_date) as days
                        FROM asset_items i
                        JOIN asset_batches b ON i.batch_id = b.id
                        JOIN asset_maintenance m ON m.item_id = i.id
                        WHERE i.owner_id = :uid 
                          AND i.status = '維修中'
                          AND m.is_deleted = 0
                          AND m.finish_date IS NULL -- 尚未結案
                          AND m.send_date < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->conn->prepare($queryRepair);
        $stmt->bindParam(":uid", $this->user_id);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $todos[] = [
                "type" => "warning",
                "title" => "維修逾期警告",
                "message" => "資產 [{$row['asset_name']}] 送修已超過 {$row['days']} 天尚未結案。"
            ];
        }

        // B. 借出逾期
        $queryLoan = "SELECT b.asset_name, t.expected_return_date, IFNULL(u.name, t.borrower) as borrower_name, DATEDIFF(NOW(), t.expected_return_date) as overdue_days
                      FROM asset_transactions t
                      JOIN asset_items i ON t.item_id = i.id
                      JOIN asset_batches b ON i.batch_id = b.id
                      LEFT JOIN users u ON t.borrower_id = u.id
                      WHERE i.owner_id = :uid 
                        AND i.status = '借用中' AND t.action_type = '借用'
                        AND t.expected_return_date < NOW()
                        AND t.id = (SELECT MAX(id) FROM asset_transactions WHERE item_id = i.id AND action_type = '借用')";

        $stmtLoan = $this->conn->prepare($queryLoan);
        $stmtLoan->bindParam(":uid", $this->user_id);
        $stmtLoan->execute();

        while ($row = $stmtLoan->fetch(PDO::FETCH_ASSOC)) {
            $borrower = $row['borrower_name'] ?: "未知借用人";
            $todos[] = [
                "type" => "error", // 逾期通常比維修逾期更緊急
                "title" => "借用逾期警告",
                "message" => "資產 [{$row['asset_name']}] 被 [{$borrower}] 借用，已逾期 {$row['overdue_days']} 天。"
            ];
        }

        return $todos;
    }

    // 取得金額統計
    public function getAmounts() {
        $amounts = [
            "total_asset" => 0,     // 總資產金額
            "total_repair" => 0,    // 維修總金額
            "total_scrapped" => 0   // 報廢總金額
        ];

        // 1. 計算資產總金額 (目前名下所有資產的單價加總)
        // 邏輯：從 asset_items 找出我的資產，JOIN asset_batches 取得單價
        $qAsset = "SELECT SUM(b.unit_price) as total 
                   FROM asset_items i
                   JOIN asset_batches b ON i.batch_id = b.id
                   WHERE i.owner_id = :uid";
        
        // 2. 計算維修總金額 (名下資產所產生的所有維修費，不論資產現在歸誰。)
        $qRepair = "SELECT SUM(cost) as total 
                    FROM asset_maintenance
                    WHERE user_id = :uid AND is_deleted = 0";

        // 3. 計算報廢總金額 (目前狀態為 '報廢' 的資產原始單價加總)
        $qScrapped = "SELECT SUM(b.unit_price) as total 
                      FROM asset_items i
                      JOIN asset_batches b ON i.batch_id = b.id
                      WHERE i.owner_id = :uid AND i.status = '報廢'";

        // 執行查詢並賦值 (使用 floatval 處理 NULL 的情況)
        $stmt = $this->conn->prepare($qAsset);
        $stmt->execute([':uid' => $this->user_id]);
        $amounts["total_asset"] = floatval($stmt->fetchColumn());

        $stmt = $this->conn->prepare($qRepair);
        $stmt->execute([':uid' => $this->user_id]);
        $amounts["total_repair"] = floatval($stmt->fetchColumn());

        $stmt = $this->conn->prepare($qScrapped);
        $stmt->execute([':uid' => $this->user_id]);
        $amounts["total_scrapped"] = floatval($stmt->fetchColumn());

        return $amounts;
    }
}
?>