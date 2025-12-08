<?php
include_once __DIR__ . '/../classes/Transaction.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class TransactionController {
    private $db;
    private $transaction;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->transaction = new Transaction($db);
        $this->auth = new AuthMiddleware($db);
    }

    // POST /api/transactions
    public function store() {
        $this->auth->authenticate();

        $data = json_decode(file_get_contents("php://input"));

        // 1. 基本必填
        if (empty($data->item_id) || empty($data->action_type) || empty($data->action_date)) {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整 (需 item_id, action_type, action_date)"]);
            return;
        }

        // 2. 根據不同情境做額外檢查
        switch ($data->action_type) {
            case '使用':
                if (empty($data->location_id)) {
                    $this->badRequest("使用情境必須填寫：位置 (location_id)"); return;
                }
                break;

            case '借用':
                // 借用人 ID 或 姓名 擇一必填
                if (empty($data->borrower_id) && empty($data->borrower)) {
                    $this->badRequest("借用情境必須填寫：借用人ID (borrower_id) 或 借用人姓名 (borrower)"); return;
                }
                if (empty($data->expected_return_date)) {
                    $this->badRequest("借用情境必須填寫：預計歸還日"); return;
                }
                break;

            case '歸還':
                if (empty($data->location_id) || empty($data->item_condition)) {
                    $this->badRequest("歸還情境必須填寫：歸還位置 (location_id) 與 物品狀況 (item_condition)"); return;
                }
                break;
            
            case '移轉':
                if (empty($data->new_owner_id)) {
                    $this->badRequest("移轉情境必須填寫：新擁有者 (new_owner_id)"); return;
                }
                break;
            
            case '報廢':
                // 檢查備註
                if (empty($data->note)) {
                    $this->badRequest("報廢情境必須填寫：備註 (note) 說明原因"); return;
                }

                // 檢查物品狀況，必須是 "壞" 才能報廢
                $queryCond = "SELECT item_condition FROM asset_items WHERE id = :id";
                $stmtCond = $this->db->prepare($queryCond);
                $stmtCond->execute([':id' => $data->item_id]);
                $currCond = $stmtCond->fetchColumn();

                if ($currCond !== '壞') {
                    $this->badRequest("操作失敗：該資產狀況為「{$currCond}」，必須為「壞」才能申請報廢。(請先透過維修流程判定為無法修復)"); 
                    return;
                }
            case '遺失':
                if (empty($data->note)) {
                    // 報廢遺失通常建議必填備註
                    $this->badRequest("報廢/遺失情境建議填寫：備註 (note) 說明原因"); return;
                }
                break;
            case '校正':
                if (empty($data->note)) {
                    $this->badRequest("校正情境必須填寫：備註 (note) 說明原因 (例如：誤按遺失，系統校正)"); return;
                }
                break;
                
            default:
                $this->badRequest("無效的動作類型"); return;
        }

        // 3. 資料填入 Model
        $this->transaction->item_id = $data->item_id;
        $this->transaction->action_type = $data->action_type;
        $this->transaction->action_date = $data->action_date;
        $this->transaction->location_id = $data->location_id ?? null;
        $this->transaction->item_condition = $data->item_condition ?? '好';
        $this->transaction->note = $data->note ?? '';
        
        $this->transaction->borrower_id = $data->borrower_id ?? null;
        $this->transaction->borrower = $data->borrower ?? null;
        $this->transaction->expected_return_date = $data->expected_return_date ?? null;
        $this->transaction->new_owner_id = $data->new_owner_id ?? null;

        // 4. 執行
        if ($this->transaction->create()) {
            http_response_code(201);
            echo json_encode([
                "message" => "異動紀錄新增成功", 
                "action" => $data->action_type,
                "id" => $this->transaction->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "新增失敗"]);
        }
    }

    private function badRequest($msg) {
        http_response_code(400);
        echo json_encode(["message" => $msg]);
    }
}
?>