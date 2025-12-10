<?php
class AssetItem {
    private $conn;
    private $table = "asset_items";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 進階查詢與分頁
    public function search($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        // 1. 建構 SQL 查詢
        $query = "SELECT 
                    i.id, 
                    i.sub_no, 
                    i.status, 
                    i.item_condition, 
                    i.updated_at,
                    b.asset_name, 
                    b.brand, 
                    b.model, 
                    b.spec,
                    l.name as location_name,
                    u_owner.name as owner_name,
                    IFNULL(i.borrower_name, u_borrower.name) as `current_user`
                  FROM " . $this->table . " i
                  JOIN asset_batches b ON i.batch_id = b.id
                  LEFT JOIN locations l ON i.location = l.id
                  LEFT JOIN users u_owner ON i.owner_id = u_owner.id
                  LEFT JOIN users u_borrower ON i.borrower_id = u_borrower.id";

        // 2. 動態加入篩選條件
        $conditions = []; // 條件
        $params = []; // 參數

        if (!empty($filters['keyword'])) {
            $conditions[] = "(i.sub_no LIKE :keyword OR b.asset_name LIKE :keyword OR b.brand LIKE :keyword OR b.model LIKE :keyword)";
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        if (!empty($filters['status'])) {
            $conditions[] = "i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['owner_id'])) {
            $conditions[] = "i.owner_id = :owner_id";
            $params[':owner_id'] = $filters['owner_id'];
        }
        
        if (!empty($filters['category'])) {
            $conditions[] = "b.category = :category";
            $params[':category'] = $filters['category'];
        }

        // 組合 WHERE 子句
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        // 加入排序
        $query .= " ORDER BY i.id DESC";

        // 3. 執行分頁查詢
        
        // A. 先算總筆數 (Total Count)
        $countQuery = "SELECT COUNT(*) FROM " . $this->table . " i JOIN asset_batches b ON i.batch_id = b.id WHERE " . (count($conditions) > 0 ? implode(" AND ", $conditions) : "1");
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total_rows = $stmtCount->fetchColumn();

        // B. 加上 LIMIT 設定分頁範圍 (跳過 0 筆，抓 10 筆 (第 1 ~ 10 筆))
        $query .= " LIMIT " . (int)$offset . ", " . (int)$limit;
        
        $stmt = $this->conn->prepare($query);

        // 綁定篩選參數 (如果有)
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        // 執行查詢
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            // 如果出錯，回傳空陣列或拋出更清楚的錯誤
            throw new Exception("資料查詢失敗: " . $e->getMessage());
        }

        return [
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total_rows,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => ceil($total_rows / $limit)
        ];
    }
    
    // 讀取單一資產詳情
    public function readOne($id) {
         $query = "SELECT 
                    i.*,
                    b.asset_name, b.brand, b.model, b.spec, b.category, b.purchase_date, b.life_years, b.unit_price,
                    l.name as location_name,
                    u.name as owner_name
                  FROM " . $this->table . " i
                  JOIN asset_batches b ON i.batch_id = b.id
                  LEFT JOIN locations l ON i.location = l.id
                  LEFT JOIN users u ON i.owner_id = u.id
                  WHERE i.id = :id LIMIT 1";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 取得資產完整履歷 (異動 + 維修 + 入庫)
    public function getHistory($id) {
        $query = "
            (
                -- 1. 異動紀錄
                SELECT 
                    'Transaction' as source_type,
                    t.action_date as event_date,
                    t.action_type,
                    u.name as operator,
                    IFNULL(t.note, '') as description,
                    l.name as location,
                    t.id as sort_id
                FROM asset_transactions t
                LEFT JOIN users u ON t.borrower_id = u.id
                LEFT JOIN locations l ON t.location_id = l.id
                WHERE t.item_id = :id1
            )
            UNION ALL
            (
                -- 2. 維修紀錄 (送修)
                SELECT 
                    'Maintenance' as source_type,
                    m.send_date as event_date,
                    CONCAT(m.action_type, ' (送修)') as action_type,
                    m.vendor as operator,
                    '送廠維修' as description,
                    NULL as location,
                    m.id as sort_id
                FROM asset_maintenance m
                WHERE m.item_id = :id2 AND m.is_deleted = 0
            )
            UNION ALL
            (
                -- 3. 維修紀錄 (結案)
                SELECT 
                    'Maintenance_End' as source_type,
                    m.finish_date as event_date,
                    CONCAT(m.action_type, ' (結案)') as action_type,
                    m.vendor as operator,
                    CONCAT(m.result_status, ': ', IFNULL(m.maintain_result, '')) as description,
                    NULL as location,
                    m.id as sort_id
                FROM asset_maintenance m
                WHERE m.item_id = :id3 AND m.is_deleted = 0 AND m.finish_date IS NOT NULL
            )
            UNION ALL
            (
                -- 4. 【新增】入庫紀錄 (從 Batch 撈)
                SELECT 
                    'Ingest' as source_type,
                    b.add_date as event_date, /* 或 purchase_date */
                    '入庫' as action_type,
                    '系統' as operator,
                    CONCAT('批號: ', b.batch_no) as description,
                    l.name as location,
                    0 as sort_id /* 讓它排在同一天的最前面 */
                FROM asset_items i
                JOIN asset_batches b ON i.batch_id = b.id
                LEFT JOIN locations l ON b.location = l.id
                WHERE i.id = :id4
            )
            -- 這裡改成 ASC (從古至今) 比較像看故事
            ORDER BY event_date ASC, sort_id ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id1", $id);
        $stmt->bindParam(":id2", $id);
        $stmt->bindParam(":id3", $id);
        $stmt->bindParam(":id4", $id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>