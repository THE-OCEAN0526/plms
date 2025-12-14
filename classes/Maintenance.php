<?php
class Maintenance {
    private $conn;
    private $table = "asset_maintenance";

    // 雖然 PHP 屬性沒宣告也能跑，但定義出來比較嚴謹
    public $id;
    public $item_id;
    public $send_date;
    public $action_type;
    public $vendor;
    public $issue_description; // ★ 新增
    public $maintain_result;
    public $result_status;
    public $finish_date;
    public $cost;
    public $is_deleted;

    public function __construct($db) {
        $this->conn = $db;
    }

    // =================================================================
    // 1. 取得維修列表 (已修正：補上 pre_property_no, sub_no, issue_description)
    // =================================================================
    public function readAll($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $query = "SELECT 
                    m.id,
                    m.item_id,
                    m.send_date,
                    m.action_type,
                    m.vendor,
                    m.issue_description,  -- ★ 新增：故障描述
                    m.maintain_result,
                    m.result_status,
                    m.finish_date,
                    m.cost,
                    m.is_deleted,
                    -- 資產資訊 (分開欄位，方便前端 Autocomplete 格式化)
                    b.asset_name,
                    b.pre_property_no,    -- ★ 新增：獨立前綴
                    i.sub_no,             -- ★ 新增：獨立後綴
                    CONCAT(b.pre_property_no, '-', i.sub_no) as full_property_no,
                    i.status as current_asset_status
                  FROM " . $this->table . " m
                  JOIN asset_items i ON m.item_id = i.id
                  JOIN asset_batches b ON i.batch_id = b.id
                  WHERE m.is_deleted = 0";

        $conditions = [];
        $params = [];

        // 搜尋邏輯 (同時搜 廠商、故障描述、處理結果、編號、品名)
        if (!empty($filters['keyword'])) {
            $conditions[] = "(
                m.vendor LIKE :keyword OR 
                m.issue_description LIKE :keyword OR -- ★ 讓使用者也能搜故障原因
                m.maintain_result LIKE :keyword OR
                b.asset_name LIKE :keyword OR
                CONCAT(b.pre_property_no, '-', i.sub_no) LIKE :keyword
            )";
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        if (!empty($filters['status']) && $filters['status'] == 'active') {
            $conditions[] = "m.finish_date IS NULL";
        } elseif (!empty($filters['status']) && $filters['status'] == 'finished') {
            $conditions[] = "m.finish_date IS NOT NULL";
        }

        if (count($conditions) > 0) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY m.send_date DESC, m.id DESC";

        // 分頁總數計算
        $countQuery = "SELECT COUNT(*) FROM " . $this->table . " m 
                       JOIN asset_items i ON m.item_id = i.id
                       JOIN asset_batches b ON i.batch_id = b.id
                       WHERE m.is_deleted = 0";
        if (count($conditions) > 0) {
            $countQuery .= " AND " . implode(" AND ", $conditions);
        }
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total_rows = $stmtCount->fetchColumn();

        // 加上 LIMIT
        $query .= " LIMIT " . (int)$offset . ", " . (int)$limit;

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return [
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total_rows,
            "page" => $page,
            "total_pages" => ceil($total_rows / $limit)
        ];
    }

    // =================================================================
    // 2. 讀取單筆 (已修正：補上獨立欄位)
    // =================================================================
    public function readOne($id) {
        $query = "SELECT 
                    m.*,
                    b.asset_name,
                    b.brand,
                    b.model,
                    b.pre_property_no,    -- ★ 新增
                    i.sub_no,             -- ★ 新增
                    CONCAT(b.pre_property_no, '-', i.sub_no) as full_property_no
                  FROM " . $this->table . " m
                  JOIN asset_items i ON m.item_id = i.id
                  JOIN asset_batches b ON i.batch_id = b.id
                  WHERE m.id = :id AND m.is_deleted = 0
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =================================================================
    // 3. 新增維修單 (已修正：加入 issue_description)
    // =================================================================
    public function create($data) {
        if (!isset($data['item_id']) || !isset($data['action_type'])) {
            return false;
        }

        // 先查快照 (為了記錄送修前的狀態)
        $queryInfo = "SELECT status, item_condition FROM asset_items WHERE id = :id";
        $stmtInfo = $this->conn->prepare($queryInfo);
        $stmtInfo->execute([':id' => $data['item_id']]);
        $currentItem = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$currentItem) return false;

        $query = "INSERT INTO " . $this->table . " 
                  (item_id, prev_status, prev_condition, send_date, action_type, vendor, issue_description) 
                  VALUES 
                  (:item_id, :prev_status, :prev_condition, :send_date, :action_type, :vendor, :issue_description)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':item_id', $data['item_id']);
        $stmt->bindValue(':prev_status', $currentItem['status']);
        $stmt->bindValue(':prev_condition', $currentItem['item_condition']);
        $stmt->bindValue(':send_date', $data['send_date'] ?? date('Y-m-d'));
        $stmt->bindValue(':action_type', $data['action_type']);
        $stmt->bindValue(':vendor', $data['vendor']);
        // ★ 新增：綁定故障描述
        $stmt->bindValue(':issue_description', $data['issue_description'] ?? ''); 

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // =================================================================
    // 4. 更新維修單 (已修正：支援全欄位修改，包含編輯與結案)
    // =================================================================
    public function update($id, $data) {
        // 我們這裡採取「有傳什麼就改什麼」的策略
        // 這樣同一個 update 方法可以支援「修改基本資料」與「結案」兩種情境
        
        $fields = [];
        $params = [':id' => $id];

        // --- A. 基本欄位 (隨時可改) ---
        if (isset($data['action_type'])) {
            $fields[] = "action_type = :action_type";
            $params[':action_type'] = $data['action_type'];
        }
        if (isset($data['vendor'])) {
            $fields[] = "vendor = :vendor";
            $params[':vendor'] = $data['vendor'];
        }
        if (isset($data['send_date'])) {
            $fields[] = "send_date = :send_date";
            $params[':send_date'] = $data['send_date'];
        }
        if (isset($data['issue_description'])) { // ★ 支援修改故障描述
            $fields[] = "issue_description = :issue_description";
            $params[':issue_description'] = $data['issue_description'];
        }

        // --- B. 結案欄位 (結案時才會傳) ---
        if (array_key_exists('finish_date', $data)) { // 使用 array_key_exists 允許傳 NULL (雖然通常不會)
            $fields[] = "finish_date = :finish_date";
            $params[':finish_date'] = $data['finish_date'];
        }
        if (array_key_exists('maintain_result', $data)) {
            $fields[] = "maintain_result = :maintain_result";
            $params[':maintain_result'] = $data['maintain_result'];
        }
        if (array_key_exists('result_status', $data)) {
            $fields[] = "result_status = :result_status";
            $params[':result_status'] = $data['result_status'];
        }
        if (isset($data['cost'])) {
            $fields[] = "cost = :cost";
            $params[':cost'] = $data['cost'];
        }

        if (empty($fields)) {
            return false; // 沒東西要改
        }

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // 綁定所有參數
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        return $stmt->execute();
    }

    // =================================================================
    // 5. 軟刪除 (這裡使用 Trigger 邏輯)
    // =================================================================
    public function delete($id) {
        // ★ 注意：這裡我們只把 is_deleted 設為 1
        // ★ 資產狀態還原的動作，現在由資料庫 Trigger (trg_maintenance_update) 自動處理
        // ★ 這樣比較安全，因為不管用 PHP 刪除還是手動 SQL 刪除，狀態都會自動還原
        
        $query = "UPDATE " . $this->table . " SET is_deleted = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
?>