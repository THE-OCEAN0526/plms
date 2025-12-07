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

    // GET /api/assets (對應原本的 batch_list.php)
    public function index() {
        // 驗證 Token (如果不需要驗證可拿掉)
        $this->auth->authenticate();

        // 接收參數
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : "";

        $from_record_num = ($page - 1) * $limit;

        $stmt = $this->asset->readPaging($keyword, $from_record_num, $limit);
        $num = $stmt->rowCount();
        $total_rows = $this->asset->countAll($keyword);
        $total_pages = ceil($total_rows / $limit);

        if($num > 0) {
            $assets_arr = ["data" => [], "meta" => []];
            $assets_arr["meta"] = [
                "total_records" => $total_rows,
                "current_page"  => $page,
                "total_pages"   => $total_pages,
                "limit"         => $limit
            ];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $item = [
                    "id" => $row['id'],
                    "batch_no" => $row['batch_no'],
                    "asset_name" => $row['asset_name'],
                    "category" => $row['category'],
                    "brand" => $row['brand'],
                    "model" => $row['model'],
                    "qty" => $row['qty_purchased'],
                    "unit" => $row['unit'],
                    "total_price" => $row['total_price'],
                    "purchase_date" => $row['purchase_date'],
                    "property_range" => $row['pre_property_no'] . " " . $row['suf_property_no']
                ];
                array_push($assets_arr["data"], $item);
            }
            echo json_encode($assets_arr);
        } else {
            echo json_encode([
                "message" => "查無資料", 
                "data" => [],
                "meta" => ["total_records" => 0, "current_page" => $page, "total_pages" => 0]
            ]);
        }
    }

    // POST /api/assets (對應原本的 batch_create.php)
    public function store() {
        $currentUser = $this->auth->authenticate(); // 確保已登入
        
        $data = json_decode(file_get_contents("php://input"));

        // 簡易驗證
        if(empty($data->batch_no) || empty($data->asset_name) || empty($data->qty_purchased)) {
            http_response_code(400);
            echo json_encode(["message" => "資料不完整，批號、品名、數量為必填"]);
            return;
        }

        // 將資料塞入 Model (這裡配合你的資料庫更新)
        $this->asset->batch_no = $data->batch_no;
        $this->asset->asset_name = $data->asset_name;
        $this->asset->qty_purchased = $data->qty_purchased;
        $this->asset->unit_price = $data->unit_price ?? 0;
        $this->asset->category = $data->category;
        $this->asset->unit = $data->unit;
        $this->asset->pre_property_no = $data->pre_property_no;
        $this->asset->suf_property_no = $data->suf_property_no;
        $this->asset->brand = $data->brand;
        $this->asset->model = $data->model;
        $this->asset->spec = $data->spec;
        $this->asset->purchase_date = $data->purchase_date;
        $this->asset->life_years = $data->life_years;
        $this->asset->accounting_items = $data->accounting_items;
        $this->asset->fund_source = $data->fund_source;

        try {
            if($this->asset->create()) {
                http_response_code(201);
                echo json_encode([
                    "message" => "資產入庫成功",
                    "detail" => "已建立批次 {$this->asset->batch_no}"
                ]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(["message" => "入庫失敗：" . $e->getMessage()]);
        }
    }
}
?>