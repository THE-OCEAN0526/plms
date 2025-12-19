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

        if (empty($data->item_ids) || !is_array($data->item_ids) || empty($data->action_type)) {
            $this->badRequest("資料不完整 (需 item_ids 陣列, action_type)");
            return;
        }

        $englishAction = $data->action_type;
        if (!array_key_exists($englishAction, $actionMap)) {
            $this->badRequest("無效的動作類型: " . $englishAction);
            return;
        }

        $chineseAction = $actionMap[$englishAction];
        $itemIds = $data->item_ids;

        try {
            // 2. 狀態前置檢查 (嚴格規則檢查)
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $queryCheck = "SELECT id, sub_no, status, item_condition, owner_id FROM asset_items WHERE id IN ($placeholders)";
            $stmtCheck = $this->db->prepare($queryCheck);
            $stmtCheck->execute($itemIds);
            $itemsInDb = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

            if (count($itemsInDb) !== count($itemIds)) {
                $this->badRequest("部分資產 ID 不存在");
                return;
            }

            foreach ($itemsInDb as $item) {
                $status = $item['status'];
                $condition = $item['item_condition'];
                $subNo = $item['sub_no'];

                switch ($englishAction) {
                    case 'use':
                    case 'loan':
                        // 1. 使用/借用：只能在「閒置」時進行
                        if ($status !== '閒置') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 目前為「{$status}」，只有「閒置」資產才能開始使用或借出。");
                            return;
                        }
                        // 借用人不可為保管人
                        if ($englishAction === 'loan' && isset($data->borrower_id) && $data->borrower_id == $item['owner_id']) {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 的保管人不可作為借用人。");
                            return;
                        }
                        break;

                    case 'return':
                        // 2. 歸還：只能在「借用中」進行
                        if ($status !== '借用中') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 目前為「{$status}」，只有「借用中」的資產才能執行歸還。");
                            return;
                        }
                        break;

                    case 'transfer':
                        // 3. 移轉：必須是「閒置」，排除報廢、遺失、維修中
                        if ($status !== '閒置') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 目前為「{$status}」，只有「閒置」狀態的資產才能進行移轉。");
                            return;
                        }
                        if ($data->new_owner_id == $item['owner_id']) {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 不可移轉給目前的保管人。");
                            return;
                        }
                        break;

                    case 'scrap':
                        // 4. 報廢：必須是「壞」且尚未報廢
                        if ($status === '報廢') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 已經是報廢狀態。");
                            return;
                        }
                        if ($condition !== '壞') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 狀況為「{$condition}」，必須為「壞」才能報廢。");
                            return;
                        }
                        break;

                    case 'loss':
                        // 5. 遺失：已經報廢或遺失的資產不可再登記遺失
                        if ($status === '報廢' || $status === '遺失') {
                            $this->badRequest("操作失敗：資產 [{$subNo}] 已經是「{$status}」狀態，不可再登記遺失。");
                            return;
                        }
                        break;

                    case 'correct':
                        // 校正：無狀態限制，用於將資產復原至可用狀態
                        break;
                }
            }

            // 3. 欄位驗證
            if (!$this->validateActionFields($englishAction, $data)) return;

            // 4. 資料庫交易
            $this->db->beginTransaction();
            foreach ($itemIds as $id) {
                $this->transaction->item_id = $id;
                $this->transaction->action_type = $chineseAction;
                $this->transaction->location_id = $data->location_id ?? null;
                $this->transaction->item_condition = $data->item_condition ?? '好';
                $this->transaction->note = $data->note ?? '';
                $this->transaction->borrower_id = $data->borrower_id ?? null;
                $this->transaction->borrower = $data->borrower ?? null;
                $this->transaction->expected_return_date = $data->expected_return_date ?? null;
                $this->transaction->new_owner_id = $data->new_owner_id ?? null;

                if (!$this->transaction->create()) throw new Exception("ID $id 寫入失敗");
            }
            $this->db->commit();
            echo json_encode(["message" => "批次異動成功", "count" => count($itemIds)]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->sendError(500, "系統錯誤: " . $e->getMessage());
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
            
        // ★ 修改此處：將 correct 獨立出來
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