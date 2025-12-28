<?php
class Transaction {
    private $conn;
    private $table_name = "asset_transactions";

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

    // 判斷資產是否允許執行該動作
    public function validateAssetStatus($englishAction, $item, $data) {
        $status = $item['status'];
        $condition = $item['item_condition'];
        $subNo = $item['sub_no'];

        switch ($englishAction) {
            case 'use':
            case 'loan':
                if ($status !== '閒置') {
                    throw new Exception("資產 [{$subNo}] 為「{$status}」，只有「閒置」才可使用/借出。");
                }
                if ($englishAction === 'loan' && ($data->borrower_id ?? null) == $item['owner_id']) {
                    throw new Exception("資產 [{$subNo}] 的保管人不可作為借用人。");
                }
                break;

            case 'return':
                if ($status !== '借用中') {
                    throw new Exception("資產 [{$subNo}] 為「{$status}」，只有「借用中」才可歸還。");
                }
                break;

            case 'transfer':
                if ($status !== '閒置') {
                    throw new Exception("資產 [{$subNo}] 為「{$status}」，只有「閒置」才可移轉。");
                }
                if (($data->new_owner_id ?? null) == $item['owner_id']) {
                    throw new Exception("資產 [{$subNo}] 不可移轉給目前的保管人。");
                }
                break;

            case 'scrap':
                if ($status === '報廢') throw new Exception("資產 [{$subNo}] 已經報廢。");
                if ($condition !== '壞') throw new Exception("資產 [{$subNo}] 必須為「壞」才能報廢。");
                break;

            case 'loss':
                if (in_array($status, ['報廢', '遺失'])) throw new Exception("資產 [{$subNo}] 已經是「{$status}」。");
                break;
        }
        return true;
    }

    public function create() {
        $prev_owner_id = null;

        // 只有「移轉」情境，才需要記錄 "原擁有者" (prev_owner_id)
        if ($this->action_type === '移轉') {
            $sql = "SELECT owner_id FROM asset_items WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $this->item_id]);
            $prev_owner_id = $stmt->fetchColumn();
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  SET 
                    item_id = :item_id, 
                    action_type = :action_type, 
                    prev_owner_id = :prev_owner_id,
                    new_owner_id = :new_owner_id, 
                    borrower_id = :borrower_id, 
                    borrower = :borrower,
                    location_id = :location_id, 
                    item_condition = :item_condition, 
                    action_date = :action_date, 
                    expected_return_date = :expected_return_date, 
                    note = :note";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ":item_id" => $this->item_id,
            ":action_type" => $this->action_type,
            ":prev_owner_id" => $prev_owner_id,
            ":new_owner_id" => $this->new_owner_id,
            ":borrower_id" => $this->borrower_id,
            ":borrower" => $this->borrower,
            ":location_id" => $this->location_id,
            ":item_condition" => $this->item_condition,
            ":action_date" => $this->action_date,
            ":expected_return_date" => $this->expected_return_date,
            ":note" => $this->note
        ]);
    }

}
?>