<?php
include_once __DIR__ . '/../classes/AssetBatch.php';
include_once __DIR__ . '/../classes/AssetItem.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class AssetController {
    private $db;
    private $assetBatch;
    private $assetItem;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->assetBatch = new AssetBatch($db);
        $this->assetItem = new AssetItem($db);
        $this->auth = new AuthMiddleware($db);
    }

    // POST /api/assets (資產入庫)
    public function store() {
        // 驗證使用者身份，並取得當前操作者
        $currentUser = $this->auth->authenticate();

        // 接收輸入資料
        $data = json_decode(file_get_contents("php://input"));

        // 必填檢查
        if(empty($data->pre_property_no) || empty($data->add_date) || empty($data->asset_name) || !isset($data->suf_start_no) || !isset($data->suf_end_no)) {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整：日期、品名、起始號、結束號為必填"]);
            return;
        }

        if (intval($data->suf_start_no) > intval($data->suf_end_no)) {
            http_response_code(400);
            echo json_encode(["message" => "編號範圍錯誤：起始號不能大於結束號"]);
            return;
        }

        // 資料對應，將資料塞入 Model 
        $this->assetBatch->add_date = $data->add_date;
        $this->assetBatch->batch_no = $data->batch_no;
        $this->assetBatch->fund_source = $data->fund_source ?? null;
        $this->assetBatch->purchase_date = $data->purchase_date ?? null;
        $this->assetBatch->life_years = $data->life_years ?? null;
        $this->assetBatch->accounting_items = $data->accounting_items ?? null;
        $this->assetBatch->location = $data->location ?? null; // 這是 asset_batches 的 location ID

        $this->assetBatch->pre_property_no = $data->pre_property_no ?? '';
        $this->assetBatch->suf_start_no = intval($data->suf_start_no);
        $this->assetBatch->suf_end_no = intval($data->suf_end_no);
   
        $this->assetBatch->category = $data->category;
        $this->assetBatch->asset_name = $data->asset_name;
        $this->assetBatch->brand = $data->brand ?? '';
        $this->assetBatch->model = $data->model ?? '';
        $this->assetBatch->spec = $data->spec ?? '';
       
        $this->assetBatch->unit = $data->unit;
        $this->assetBatch->unit_price = $data->unit_price ?? 0;

        try {
            // 執行入庫，帶入操作者 ID 以記錄誰執行的動作
            if($this->assetBatch->create($currentUser['id'])) {
                http_response_code(201);
                echo json_encode([
                    "message" => "資產入庫成功",
                    "data" => [
                        "batch_id" => $this->assetBatch->id,
                        "qty" => ($this->assetBatch->suf_end_no - $this->assetBatch->suf_start_no + 1)
                    ]
                ]);
            }
        } catch (Exception $e) {
            // 捕捉 Model 拋出的錯誤 (包含 Trigger 的錯誤訊息)
            http_response_code(400);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }

    // GET /api/assets (資產列表 - 進階查詢)
    public function index() {
        $currentUser = $this->auth->authenticate();

        // 接收 GET 參數
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

        // 篩選條件
        $filters = [
            'keyword'  => $_GET['keyword'] ?? null,
            'status'   => $_GET['status'] ?? null,   // 例如: '閒置', '維修中'
            'category' => $_GET['category'] ?? null,  // 例如: '非消耗品'
            'owner_id' => $currentUser['id'], // 只看自己的資產
            'filter_scope' => $_GET['scope'] ?? null // 例如: 'maintainable' 可維修篩選
        ];

        // 呼叫 Model 查詢
        $result = $this->assetItem->search($filters, $page, $limit);

        // 回傳 JSON
        http_response_code(200);
        echo json_encode([
            "message" => "查詢成功",
            "meta" => [
                "user_scope"    => $currentUser['name'],
                "total_records" => $result['total'],
                "current_page"  => $result['page'],
                "total_pages"   => $result['total_pages'],
                "limit"         => $result['limit']
            ],
            "data" => $result['data']
        ]);
    }


    // GET /api/assets/{id} (單一資產詳情)
    public function show($params) {
        $id = $params['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "缺少 ID"]);
            return;
        }

        $item = $this->assetItem->readOne($id);

        if ($item) {
            http_response_code(200);
            echo json_encode(["data" => $item]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "找不到該資產"]);
        }
    }

    // GET /api/assets/{id}/history (資產履歷 - 整合異動與維修紀錄)
    public function history($params) {
        $this->auth->authenticate(); 

        $id = $params['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "缺少 ID"]);
            return;
        }

        // 1. 先確認資產存在
        $asset = $this->assetItem->readOne($id);
        if (!$asset) {
            http_response_code(404);
            echo json_encode(["message" => "找不到該資產"]);
            return;
        }

        // 2. 呼叫 Model 撈取履歷 (Union 查詢)
        $history = $this->assetItem->getHistory($id);

        http_response_code(200);
        echo json_encode([
            "asset_info" => [
                "id" => $asset['id'],
                "sub_no" => $asset['sub_no'],
                "name" => $asset['asset_name'],
                "category" => $asset['category'],
                "status" => $asset['status']
            ],
            "timeline" => $history
        ]);
    }

}
?>