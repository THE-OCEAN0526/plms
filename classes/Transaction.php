<?php
class Transaction {
    private $conn;
    private $table_name = "asset_transactions";

    // 屬性
    public $id;
    public $item_id;
    public $action_type;
    public $borrower;       // 無帳號借用人姓名
    public $borrower_id;    // 有帳號借用人 ID
    public $location_id;
    public $item_condition;
    public $action_date;
    public $expected_return_date;
    public $note;
    public $new_owner_id;   // 移轉時的新擁有者

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        
        $prev_owner_id = null;

        // 只有「移轉」情境，才需要記錄 "原擁有者" (prev_owner_id)
        if ($this->action_type === '移轉') {
            $queryCheck = "SELECT owner_id FROM asset_items WHERE id = :item_id LIMIT 1";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bindParam(":item_id", $this->item_id);
            $stmtCheck->execute();
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$row) return false; // 找不到資產

            $prev_owner_id = $row['owner_id'];
        }

        // 插入交易紀錄
        $query = "INSERT INTO " . $this->table_name . " 
                  SET 
                    item_id     = :item_id,
                    action_type = :action_type,
                    prev_owner_id = :prev_owner_id,
                    new_owner_id  = :new_owner_id,
                    borrower_id   = :borrower_id,
                    borrower      = :borrower,
                    location_id   = :location_id,
                    item_condition= :item_condition,
                    action_date   = NOW(),
                    expected_return_date = :expected_return_date,
                    note          = :note";

        $stmt = $this->conn->prepare($query);

        // 清理輸入
        $this->item_id = htmlspecialchars(strip_tags($this->item_id ?? ''));
        $this->action_type = htmlspecialchars(strip_tags($this->action_type ?? ''));
        $this->borrower = htmlspecialchars(strip_tags($this->borrower ?? ''));
        $this->note = htmlspecialchars(strip_tags($this->note ?? ''));

        // 綁定參數
        $stmt->bindParam(":item_id", $this->item_id);
        $stmt->bindParam(":action_type", $this->action_type);       
        $stmt->bindParam(":prev_owner_id", $prev_owner_id);
        $stmt->bindParam(":new_owner_id", $this->new_owner_id);
        $stmt->bindParam(":borrower_id", $this->borrower_id);
        $stmt->bindParam(":borrower", $this->borrower);
        $stmt->bindParam(":location_id", $this->location_id);
        $stmt->bindParam(":item_condition", $this->item_condition);
        $stmt->bindParam(":expected_return_date", $this->expected_return_date);
        $stmt->bindParam(":note", $this->note);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
}
?>