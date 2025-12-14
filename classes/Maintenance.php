<?php
class Maintenance {
    private $conn;
    private $table_name = "asset_maintenance";

    public $id;
    public $item_id;
    public $send_date;
    public $action_type;
    public $vendor;

    public $maintain_result; // 維修結果/處理說明 (結案時填寫)
    public $result_status; // 維修成功 / 無法修復 (結案時填寫)
    public $cost; // (結案時填寫)
    public $finish_date; //(結案時填寫)
    public $is_deleted;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        // 先檢查該資產目前的狀態
        $sqlCheck = "SELECT status, item_condition FROM asset_items WHERE id = :id LIMIT 1";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->bindParam(":id", $this->item_id);
        $stmtCheck->execute();
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // 如果找不到資產，或是已經在維修中，應擋下
        if (!$row) return false;

        // 確定有找到，才讀取狀態、狀況
        $current_status = $row['status'];
        $current_condition = $row['item_condition'];

        // 寫入維修單，並記錄 prev_status 與 prev_condition
        $query = "INSERT INTO " . $this->table_name . "
                  SET 
                    item_id     = :item_id,
                    prev_status = :prev_status,
                    prev_condition = :prev_condition,
                    send_date   = :send_date,
                    action_type = :action_type,
                    vendor      = :vendor";

        $stmt = $this->conn->prepare($query);

        // 清理輸入資料
        $this->item_id = htmlspecialchars(strip_tags($this->item_id));
        $this->vendor  = htmlspecialchars(strip_tags($this->vendor));

        // 資料綁定
        $stmt->bindParam(":item_id", $this->item_id);
        $stmt->bindParam(":prev_status", $current_status);
        $stmt->bindParam(":prev_condition", $current_condition);
        $stmt->bindParam(":send_date", $this->send_date);
        $stmt->bindParam(":action_type", $this->action_type);
        $stmt->bindParam(":vendor", $this->vendor);

        if($stmt->execute()) {
            // 成功後取得新 ID
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // 更新維修單 (結案)
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    maintain_result = :maintain_result,
                    result_status   = :result_status,
                    cost            = :cost,
                    finish_date     = :finish_date
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->maintain_result = htmlspecialchars(strip_tags($this->maintain_result));

        $stmt->bindParam(":maintain_result", $this->maintain_result);
        $stmt->bindParam(":result_status", $this->result_status);
        $stmt->bindParam(":cost", $this->cost);
        $stmt->bindParam(":finish_date", $this->finish_date);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // 軟刪除 (取消送修) - 救援誤操作
    public function cancel() {
        try {
            $this->conn->beginTransaction();

            $query = "UPDATE " . $this->table_name . " 
                      SET is_deleted = 1 
                      WHERE id = :id AND is_deleted = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                $this->conn->commit(); 
                return true; // 視為操作完成（因為結果就是已刪除）
            }

            // 取得前世記憶 (狀態 + 狀況)
            $queryFind = "SELECT item_id, prev_status, prev_condition FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
            $stmtFind = $this->conn->prepare($queryFind);
            $stmtFind->bindParam(":id", $this->id);
            $stmtFind->execute();
            $row = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $itemId = $row['item_id'];
                $prevStatus = $row['prev_status'];
                $prevCondition = $row['prev_condition'];

                $queryRevert = "UPDATE asset_items 
                                SET status = :status, item_condition = :cond 
                                WHERE id = :item_id";
                $stmtRevert = $this->conn->prepare($queryRevert);
                $stmtRevert->bindParam(":status", $prevStatus);
                $stmtRevert->bindParam(":cond", $prevCondition);
                $stmtRevert->bindParam(":item_id", $itemId);
                $stmtRevert->execute();
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function readAll ($filters = [], page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $query = "SELECT 
                    m.id,
                    m.item_id,
                    m.send_date,
                    m.action_type,
                    m.vendor,
                    m.maintain_result,
                    m.result_status,
                    m.finish_date,
                    m.cost,
                    m.is_deleted,
                    -- 資產資訊 (顯示用)
                    b.asset_name,
                    CONCAT(b.pre_property_no, '-', i.sub_no) as full_property_no,
                    i.status as current_asset_status
                  FROM " . $this->table . " m
                  JOIN asset_items i ON m.item_id = i.id
                  JOIN asset_batches b ON i.batch_id = b.id
                  WHERE m.is_deleted = 0"; // 預設只顯示未刪除的

        $conditions = [];
        $params = [];

        // --- 搜尋條件 ---
        if (!empty($filters['keyword'])) {
            // 搜尋範圍：廠商、處理說明、財產編號、品名
            $conditions[] = "(
                m.vendor LIKE :keyword OR 
                m.maintain_result LIKE :keyword OR
                b.asset_name LIKE :keyword OR
                CONCAT(b.pre_property_no, '-', i.sub_no) LIKE :keyword
            )";
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        // --- 狀態篩選 (進行中 / 已結案) ---
        // 前端傳 'active' 代表維修中 (finish_date 是 NULL)
        if (!empty($filters['status']) && $filters['status'] == 'active') {
            $conditions[] = "m.finish_date IS NULL";
        }
        // 前端傳 'finished' 代表已結案 (finish_date 有值)
        elseif (!empty($filters['status']) && $filters['status'] == 'finished') {
            $conditions[] = "m.finish_date IS NOT NULL";
        }

        // 組合 SQL
        if (count($conditions) > 0) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        // 排序：預設依照送修日期 (新 -> 舊)
        $query .= " ORDER BY m.send_date DESC, m.id DESC";

        // --- 處理分頁 ---
        
        // A. 算總筆數
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

        // B. 加上 Limit
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

    // 2. 讀取單筆維修單 (用於編輯頁面回填)
    public function readOne($id) {
        $query = "SELECT 
                    m.*,
                    -- 資產資訊 (唯讀，給前端顯示這是修哪台用)
                    b.asset_name,
                    b.brand,
                    b.model,
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
}