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

        // 定義英文代碼與資料庫中文 ENUM 的對照表
        $actionMap = [
            'use'      => '使用',
            'loan'     => '借用',
            'return'   => '歸還',
            'transfer' => '移轉',
            'scrap'    => '報廢',
            'loss'     => '遺失',
            'correct'  => '校正'
        ];

        // 1. 基本必填與合法性檢查
        if (empty($data->item_ids) || !is_array($data->item_ids) || empty($data->action_type) || empty($data->action_date)) {
            $this->badRequest("資料不完整 (需 item_ids 陣列, action_type, action_date)");
            return;
        }

        $englishAction = $data->action_type;

        // 檢查 action_type 是否在定義的英文代碼中
        if (!array_key_exists($englishAction, $actionMap)) {
            $this->badRequest("無效的動作類型: " . $englishAction);
            return;
        }

        $chineseAction = $actionMap[$englishAction]; // 取得資料庫使用的中文值
        $itemIds = $data->item_ids;

        // 2. 狀態前置檢查 (Pre-check)
        try {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $queryCheck = "SELECT id, sub_no, status, item_condition FROM asset_items WHERE id IN ($placeholders)";
            $stmtCheck = $this->db->prepare($queryCheck);
            $stmtCheck->execute($itemIds);
            $itemsInDb = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

            if (count($itemsInDb) !== count($itemIds)) {
                $this->badRequest("部分資產 ID 不存在，請重新確認");
                return;
            }

            // 根據「英文動作」進行邏輯驗證，但比對的是「中文狀態」 (資料庫回傳中文)
            foreach ($itemsInDb as $item) {
                switch ($englishAction) {
                    case 'loan': // 借用
                        if ($item['status'] !== '閒置') {
                            $this->badRequest("操作失敗：資產 [{$item['sub_no']}] 目前狀態為「{$item['status']}」，只有「閒置」資產才能借出。");
                            return;
                        }
                        break;
                    case 'scrap': // 報廢
                        if ($item['item_condition'] !== '壞') {
                            $this->badRequest("操作失敗：資產 [{$item['sub_no']}] 狀況為「{$item['item_condition']}」，必須為「壞」才能報廢。");
                            return;
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            $this->sendError(500, "前置檢查發生錯誤: " . $e->getMessage());
            return;
        }

        // 3. 動作特定欄位檢查 (改用英文 Key 判斷)
        if (!$this->validateActionFields($englishAction, $data)) return;

        // 4. 資料庫交易與寫入
        try {
            $this->db->beginTransaction();

            foreach ($itemIds as $id) {
                $this->transaction->item_id = $id;
                $this->transaction->action_type = $chineseAction; // ★ 寫入資料庫必須用中文 ENUM 值
                $this->transaction->action_date = $data->action_date;
                $this->transaction->location_id = $data->location_id ?? null;
                $this->transaction->item_condition = $data->item_condition ?? '好';
                $this->transaction->note = $data->note ?? '';
                $this->transaction->borrower_id = $data->borrower_id ?? null;
                $this->transaction->borrower = $data->borrower ?? null;
                $this->transaction->expected_return_date = $data->expected_return_date ?? null;
                $this->transaction->new_owner_id = $data->new_owner_id ?? null;

                if (!$this->transaction->create()) {
                    throw new Exception("資產 ID $id 寫入失敗");
                }
            }

            $this->db->commit();
            http_response_code(201);
            echo json_encode([
                "message" => "批次異動紀錄新增成功", 
                "count" => count($itemIds),
                "action" => $englishAction
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->sendError(500, "批次寫入失敗，已全數復原。錯誤: " . $e->getMessage());
        }
    }

    // 驗證欄位邏輯改用英文代碼
    private function validateActionFields($action, $data) {
        switch ($action) {
            case 'use': // 使用
                if (empty($data->location_id)) { $this->badRequest("使用情境必須填寫：位置 (location_id)"); return false; }
                break;
            case 'loan': // 借用
                if (empty($data->borrower_id) && empty($data->borrower)) { $this->badRequest("借用必須填寫：借用人"); return false; }
                if (empty($data->expected_return_date)) { $this->badRequest("借用必須填寫：預計歸還日"); return false; }
                break;
            case 'return': // 歸還
                if (empty($data->location_id) || empty($data->item_condition)) { $this->badRequest("歸還必須填寫：位置與狀況"); return false; }
                break;
            case 'transfer': // 移轉
                if (empty($data->new_owner_id)) { $this->badRequest("移轉必須填寫：新擁有者"); return false; }
                break;
            case 'scrap': // 報廢
            case 'loss':  // 遺失
            case 'correct': // 校正
                if (empty($data->note)) { $this->badRequest("此情境必須填寫備註說明原因"); return false; }
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
?>