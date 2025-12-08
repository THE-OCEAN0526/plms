<?php
include_once __DIR__ . '/../classes/AssetBatch.php';
include_once __DIR__ . '/../classes/AuthMiddleware.php';

class AssetController {
    private $db;
    private $asset;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->asset = new AssetBatch($db);
        $this->auth = new AuthMiddleware($db);
    }

    // POST /api/assets (資產入庫)
    public function store() {
        // 驗證使用者身份，並取得當前操作者
        $currentUser = $this->auth->authenticate();

        // 接收輸入資料
        $data = json_decode(file_get_contents("php://input"));

        // 必填檢查
        if(empty($data->batch_no) || empty($data->asset_name) || !isset($data->suf_start_no) || !isset($data->suf_end_no)) {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整：批號、品名、起始號、結束號為必填"]);
            return;
        }

        if (intval($data->suf_start_no) > intval($data->suf_end_no)) {
            http_response_code(400);
            echo json_encode(["message" => "編號範圍錯誤：起始號不能大於結束號"]);
            return;
        }

        // 資料對應，將資料塞入 Model 
        $this->asset->batch_no = $data->batch_no;
        $this->asset->fund_source = $data->fund_source ?? null;
        $this->asset->purchase_date = $data->purchase_date ?? null;
        $this->asset->life_years = $data->life_years ?? null;
        $this->asset->accounting_items = $data->accounting_items ?? null;
        $this->asset->location = $data->location ?? null; // 這是 asset_batches 的 location ID

        $this->asset->pre_property_no = $data->pre_property_no ?? '';
        $this->asset->suf_start_no = intval($data->suf_start_no);
        $this->asset->suf_end_no = intval($data->suf_end_no);
        
        $this->asset->category = $data->category;
        $this->asset->asset_name = $data->asset_name;
        $this->asset->brand = $data->brand ?? '';
        $this->asset->model = $data->model ?? '';
        $this->asset->spec = $data->spec ?? '';
        
        $this->asset->unit = $data->unit;
        $this->asset->unit_price = $data->unit_price ?? 0;

        try {
            // 執行入庫，帶入操作者 ID 以記錄誰執行的動作
            if($this->asset->create($currentUser['id'])) {
                http_response_code(201);
                echo json_encode([
                    "message" => "資產入庫成功",
                    "data" => [
                        "batch_id" => $this->asset->id,
                        "batch_no" => $this->asset->batch_no,
                        "qty" => ($this->asset->suf_end_no - $this->asset->suf_start_no + 1)
                    ]
                ]);
            }
        } catch (Exception $e) {
            // 捕捉 Model 拋出的錯誤 (包含 Trigger 的錯誤訊息)
            http_response_code(400);
            echo json_encode(["message" => "入庫失敗：" . $e->getMessage()]);
        }
    }

    // index 方法也需要根據新的 suf_start_no/end_no 做 SQL 的微調，這裡先省略
    public function index() {
        // ... (保留原本架構，但 SELECT 查詢要改抓 suf_start_no 和 suf_end_no)
    }

}
?>