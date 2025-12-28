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

    public function store() {
        $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"));
        
        $actionMap = [
            'use'      => '使用',
            'loan'     => '借用',
            'return'   => '歸還',
            'transfer' => '移轉',
            'scrap'    => '報廢',
            'loss'     => '遺失',
            'correct'  => '校正'
        ];

        if (empty($data->item_ids) || !isset($actionMap[$data->action_type])) {
            return $this->badRequest("無效的請求資料");
        }

        $englishAction = $data->action_type;
        $chineseAction = $actionMap[$englishAction];
        
        // 欄位驗證
        if (!$this->validateActionFields($englishAction, $data)) return;

        try {
            // 批量取得資產現況
            $placeholders = implode(',', array_fill(0, count($data->item_ids), '?'));
            $stmt = $this->db->prepare("SELECT id, sub_no, status, item_condition, owner_id FROM asset_items WHERE id IN ($placeholders)");
            $stmt->execute($data->item_ids);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 處理時間
            $actionDate = $data->action_date ?? date('Y-m-d');
            if (strlen($actionDate) === 10) $actionDate .= " " . date("H:i:s");

            $this->db->beginTransaction();

            foreach ($items as $item) {
                // 呼叫 Model 的檢查邏輯
                $this->transaction->validateAssetStatus($englishAction, $item, $data);

                // 設定 Model 屬性並寫入
                $this->transaction->item_id = $item['id'];
                $this->transaction->action_type = $chineseAction;
                $this->transaction->action_date = $actionDate;
                $this->transaction->location_id = $data->location_id ?? null;
                $this->transaction->item_condition = $data->item_condition ?? $item['item_condition'];
                $this->transaction->note = $data->note ?? '';
                $this->transaction->borrower_id = $data->borrower_id ?? null;
                $this->transaction->borrower = $data->borrower ?? null;
                $this->transaction->expected_return_date = $data->expected_return_date ?? null;
                $this->transaction->new_owner_id = $data->new_owner_id ?? null;

                if (!$this->transaction->create()) throw new Exception("資產 {$item['sub_no']} 寫入失敗");
            }

            $this->db->commit();
            echo json_encode(["message" => "批次異動成功", "count" => count($items)]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->sendError(400, $e->getMessage());
        }
    }

    private function validateActionFields($action, $data) {
        switch ($action) {
            case 'use':
                if (empty($data->location_id)) { $this->badRequest("使用必須填寫位置"); return false; }
                break;
            case 'loan':
                if (empty($data->borrower_id) && empty($data->borrower)) { $this->badRequest("借用必須填寫借用人"); return false; }
                if (empty($data->expected_return_date)) { $this->badRequest("借用必須填寫預計歸還日"); return false; }
                break;
            case 'return':
                if (empty($data->location_id) || empty($data->item_condition)) { $this->badRequest("歸還必須填寫位置與狀況"); return false; }
                break;
            case 'transfer':
                if (empty($data->new_owner_id)) { $this->badRequest("移轉必須填寫新擁有者"); return false; }
                break;
                
            // 將 correct 獨立出來
            case 'correct':
                if (empty($data->location_id)) { 
                    $this->badRequest("執行「校正」時，必須指定資產目前找回後存放的位置。"); 
                    return false; 
                }
                if (empty($data->note)) { 
                    $this->badRequest("執行「校正」必須在備註說明原因。"); 
                    return false; 
                }
                break;

            case 'scrap':
            case 'loss':
                if (empty($data->note)) { $this->badRequest("此動作必須填寫備註說明原因"); return false; }
                break;
        }
        return true;
    }

    private function badRequest($msg) {
        http_response_code(400);
        echo json_encode(["message" => $msg]);
    }

    private function sendError($code, $message) {
        http_response_code($code);
        echo json_encode(["message" => $message]);
    }
}