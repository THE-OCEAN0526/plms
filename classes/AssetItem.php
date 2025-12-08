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

        // 建構 SQL 查詢
        // 需要 JOIN 批次表(取得品名)、地點表、使用者表(取得擁有人與借用人姓名)
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
                    IFNULL(i.borrower_name, u_borrower.name) as current_user /* 借用人: 優先顯示紀錄的姓名(無帳號)，若無則顯示關聯帳號的姓名 */
                  FROM " . $this->table . " i
                  JOIN asset_batches b ON i.batch_id = b.id
                  LEFT JOIN locations l ON i.location = l.id
                  LEFT JOIN users u_owner ON i.owner_id = u_owner.id
                  LEFT JOIN users u_borrower ON i.borrower_id = u_borrower.id";

        // 動態加入篩選條件
        $conditions = [];
        $params = [];

        // A. 關鍵字搜尋 (查 財產編號、品名、廠牌、型號)
        if (!empty($filters['keyword'])) {
            $conditions[] = "(i.sub_no LIKE :keyword OR b.asset_name LIKE :keyword OR b.brand LIKE :keyword OR b.model LIKE :keyword)";
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        // B. 狀態篩選 (例如: 只看 '閒置' 或 '維修中')
        if (!empty($filters['status'])) {
            $conditions[] = "i.status = :status";
            $params[':status'] = $filters['status'];
        }

        // C. 擁有者篩選 (例如: 只看 '我的保管')
        if (!empty($filters['owner_id'])) {
            $conditions[] = "i.owner_id = :owner_id";
            $params[':owner_id'] = $filters['owner_id'];
        }
        
        // D. 資產類別篩選 (例如: 只看 '非消耗品')
        if (!empty($filters['category'])) {
            $conditions[] = "b.category = :category";
            $params[':category'] = $filters['category'];
        }

        // 組合 WHERE 子句
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        // 加入排序 (預設依更新時間排序，方便看最近變動)
        $query .= " ORDER BY i.id DESC";

        // 執行分頁查詢
        // 為了取得總筆數 (Total Count)，需要先算一次 (或是用 SQL_CALC_FOUND_ROWS)
        $countQuery = "SELECT COUNT(*) FROM " . $this->table . " i JOIN asset_batches b ON i.batch_id = b.id WHERE " . (count($conditions) > 0 ? implode(" AND ", $conditions) : "1");
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total_rows = $stmtCount->fetchColumn();

        // 加上 LIMIT
        $query .= " LIMIT :offset, :limit";
        $stmt = $this->conn->prepare($query);

        // 綁定所有參數
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        // 綁定分頁 (必須是整數)
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        $stmt->execute();

        return [
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total_rows,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => ceil($total_rows / $limit)
        ];
    }
    
    // 讀取單一資產詳情 (包含基本資訊)
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
}
?>