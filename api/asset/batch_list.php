<?php

include_once '../common.php';
include_once '../../config/Database.php';
include_once '../../classes/AssetBatch.php';
include_once '../../classes/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();

$auth = new AuthMiddleware($db);
// 這行代碼會檢查 Token。如果失敗，程式會在這裡直接結束(exit)，不會往下跑
$currentUser = $auth->authenticate();


$asset = new AssetBatch($db);

// 1. 接收 GET 參數 (如果沒傳就用預設值)
// page: 第幾頁 (預設 1)
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
// limit: 一頁幾筆 (預設 10)
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
// keyword: 搜尋關鍵字 (預設空)
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : "";

// 計算 SQL 的起始位置 (Offset)
$from_record_num = ($page - 1) * $limit;

// 2. 查詢資料
$stmt = $asset->readPaging($keyword, $from_record_num, $limit);
$num = $stmt->rowCount();

// 3. 查詢總筆數 (為了算總頁數)
$total_rows = $asset->countAll($keyword);
$total_pages = ceil($total_rows / $limit);

// 4. 組裝回應資料
if($num > 0) {
    $assets_arr = array();
    // 為了符合 React 的習慣，通常會包一個 data 陣列和 meta 分頁資訊
    $assets_arr["data"] = array();
    $assets_arr["meta"] = array(
        "total_records" => $total_rows,
        "current_page"  => $page,
        "total_pages"   => $total_pages,
        "limit"         => $limit
    );

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 將每一列資料加入陣列
        // extract($row) 可以把 $row['id'] 變成 $id 變數，但我習慣直接用 $row 比較清楚
        $item = array(
            "id" => $row['id'],
            "batch_no" => $row['batch_no'],
            "asset_name" => $row['asset_name'],
            "category" => $row['category'],
            "brand" => $row['brand'],
            "model" => $row['model'],
            "qty" => $row['qty_purchased'],
            "unit" => $row['unit'],
            // total_price 是資料庫算好的，直接拿
            "total_price" => $row['total_price'],
            "purchase_date" => $row['purchase_date'],
            // 組合財產編號範圍字串 (給前端顯示用)
            "property_range" => $row['pre_property_no'] . " " . $row['suf_property_no']
        );

        array_push($assets_arr["data"], $item);
    }

    // 回傳 JSON
    http_response_code(200);
    echo json_encode($assets_arr);

} else {
    // 查無資料
    http_response_code(200); // 這裡回 200 比較好，代表查詢成功只是沒資料
    echo json_encode(array(
        "message" => "查無資料",
        "data" => [],
        "meta" => array(
            "total_records" => 0,
            "current_page"  => $page,
            "total_pages"   => 0
        )
    ));
}
?>