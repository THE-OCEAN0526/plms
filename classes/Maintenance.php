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
}