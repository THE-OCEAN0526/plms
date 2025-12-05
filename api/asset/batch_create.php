<?php

include_once '../common.php';
include_once '../../config/Database.php';
include_once '../../classes/AssetBatch.php';
include_once '../../classes/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();

$auth = new AuthMiddleware($db);
$currentUser = $auth->authenticate();

$asset = new AssetBatch($db);

$data = json_decode(file_get_contents("php://input"));

// 檢查「所有」欄位是否必填
if(
    !empty($data->pre_property_no) &&
    !empty($data->suf_property_no) &&
    !empty($data->asset_name) &&
    !empty($data->category) &&
    !empty($data->qty_purchased) &&
    isset($data->unit_price) &&
    !empty($data->unit) &&
    !empty($data->brand) &&
    !empty($data->model) &&
    !empty($data->spec) &&
    !empty($data->purchase_date) &&
    !empty($data->life_years) &&
    !empty($data->accounting_items) &&
    !empty($data->batch_no) &&
    !empty($data->fund_source)
) {
    // 1. 設定所有屬性
    $asset->batch_no = $data->batch_no;
    $asset->asset_name = $data->asset_name;
    $asset->qty_purchased = $data->qty_purchased;
    $asset->unit_price = $data->unit_price;
    $asset->category = $data->category;
    $asset->unit = $data->unit;
    $asset->pre_property_no = $data->pre_property_no;
    $asset->suf_property_no = $data->suf_property_no;
    $asset->brand = $data->brand;
    $asset->model = $data->model;
    $asset->spec = $data->spec;
    $asset->purchase_date = $data->purchase_date;
    $asset->life_years = $data->life_years;
    $asset->accounting_items = $data->accounting_items;
    $asset->fund_source = $data->fund_source;

    // 2. 執行入庫
    try {
        if($asset->create()) {
            http_response_code(201);
            echo json_encode(array(
                "message" => "資產入庫成功",
                "detail" => "已建立批次 {$asset->batch_no}，編號範圍 {$asset->suf_property_no}"
            ));
        }
    } catch (Exception $e) {
        // 捕捉 AssetBatch 拋出的驗證錯誤 (例如數量不符)
        http_response_code(400); // 400 Bad Request
        echo json_encode(array("message" => "入庫失敗：" . $e->getMessage()));
    }

} else {
    http_response_code(400);
    echo json_encode(array("message" => "資料不完整，所有欄位皆為必填！"));
}
?>