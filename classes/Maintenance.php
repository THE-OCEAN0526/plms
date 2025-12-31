<?php
class Maintenance {
    private $conn;
    private $table = "asset_maintenance";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 統一日期格式處理 (補全時分秒)
    private function formatDateTime($date) {
        if (empty($date)) return null;
        return (strlen($date) === 10) ? $date . " " . date("H:i:s") : $date;
    }

    
    private function getBaseSelect() {
        return "SELECT 
                    m.*, 
                    b.asset_name, b.brand, b.model, b.pre_property_no,
                    i.sub_no, 
                    CONCAT(b.pre_property_no, '-', i.sub_no) as full_property_no,
                    i.status as current_asset_status
                FROM " . $this->table . " m
                JOIN asset_items i ON m.item_id = i.id
                JOIN asset_batches b ON i.batch_id = b.id";
    }

    // 取得維修列表
    public function readAll($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $conditions = ["m.is_deleted = 0"];
        $params = [];

        // 處理篩選條件
        if (!empty($filters['user_id'])) {
            $conditions[] = "m.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['keyword'])) {
            $conditions[] = "(m.vendor LIKE :keyword OR m.maintain_result LIKE :keyword OR b.asset_name LIKE :keyword OR CONCAT(b.pre_property_no, '-', i.sub_no) LIKE :keyword)";
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        if (!empty($filters['status'])) {
            $conditions[] = ($filters['status'] == 'active') ? "m.finish_date IS NULL" : "m.finish_date IS NOT NULL";
        }

        $whereClause = " WHERE " . implode(" AND ", $conditions);

        // 執行總數查詢
        $stmtCount = $this->conn->prepare("SELECT COUNT(*) FROM " . $this->table . " m JOIN asset_items i ON m.item_id = i.id JOIN asset_batches b ON i.batch_id = b.id " . $whereClause);
        $stmtCount->execute($params);
        $total_rows = $stmtCount->fetchColumn();

        // 執行資料查詢
        $query = $this->getBaseSelect() . $whereClause . " ORDER BY m.send_date DESC, m.id DESC LIMIT " . (int)$offset . ", " . (int)$limit;
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->execute();

        return [
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total_rows,
            "page" => $page,
            "total_pages" => ceil($total_rows / $limit)
        ];
    }

    // 讀取單筆
    public function readOne($id) {
        $query = $this->getBaseSelect() . " WHERE m.id = :id AND m.is_deleted = 0 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 新增維修單
    public function create($data) {
        if (!isset($data['item_id'], $data['action_type'])) return false;

        // 取得維修前的資產狀態
        $stmtInfo = $this->conn->prepare("SELECT status, item_condition FROM asset_items WHERE id = :id");
        $stmtInfo->execute([':id' => $data['item_id']]);
        $item = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        if (!$item) return false;

        $query = "INSERT INTO " . $this->table . " 
                  (item_id, user_id, prev_status, prev_condition, send_date, action_type, vendor, issue_description) 
                  VALUES (:item_id, :user_id, :prev_status, :prev_condition, :send_date, :action_type, :vendor, :issue_description)";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':item_id'           => $data['item_id'],
            ':user_id'           => $data['user_id'],
            ':prev_status'       => $item['status'],
            ':prev_condition'    => $item['item_condition'],
            ':send_date'         => $this->formatDateTime($data['start_date'] ?? date('Y-m-d')),
            ':action_type'       => $data['action_type'],
            ':vendor'            => $data['vendor'],
            ':issue_description' => $data['issue_description'] ?? ''
        ]) ? $this->conn->lastInsertId() : false;
    }

    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        // 欄位映射表：'前端Key' => '資料庫欄位'
        $updateMap = [
            'action_type'       => 'action_type',
            'vendor'            => 'vendor',
            'issue_description' => 'issue_description',
            'maintain_result'   => 'maintain_result',
            'result_status'     => 'result_status',
            'cost'              => 'cost'
        ];

        // 處理一般欄位
        foreach ($updateMap as $apiKey => $dbCol) {
            if (isset($data[$apiKey]) || array_key_exists($apiKey, $data)) {
                $fields[] = "$dbCol = :$dbCol";
                $params[":$dbCol"] = $data[$apiKey];
            }
        }

        // 處理需要特殊格式化的日期欄位
        if (isset($data['start_date'])) {
            $fields[] = "send_date = :send_date";
            $params[':send_date'] = $this->formatDateTime($data['start_date']);
        }

        if (!empty($data['finish_date'])) {
            $fields[] = "finish_date = :finish_date";
            $params[':finish_date'] = $this->formatDateTime($data['finish_date']);
        }

        if (empty($fields)) return false;

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    // 軟刪除
    public function delete($id) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET is_deleted = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}