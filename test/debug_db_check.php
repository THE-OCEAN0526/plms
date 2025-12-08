<?php
// test/debug_db_check.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🩺 資料庫狀態診斷</h1>";
echo "<hr>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();

// 1. 檢查資料庫筆數
$countBatch = $db->query("SELECT COUNT(*) FROM asset_batches")->fetchColumn();
echo "<b>Asset Batches (批次表):</b> $countBatch 筆<br>";

$countItem = $db->query("SELECT COUNT(*) FROM asset_items")->fetchColumn();
echo "<b>Asset Items (單品表):</b> $countItem 筆<br>";

$countJoin = $db->query("SELECT COUNT(*) FROM asset_items i JOIN asset_batches b ON i.batch_id = b.id")->fetchColumn();
echo "<b>有效關聯資產 (Items with valid Batch):</b> $countJoin 筆<br>";

// 2. 檢查 AssetItem 類別是否存在
$classExists = file_exists('../classes/AssetItem.php');
echo "<b>classes/AssetItem.php 檔案存在?</b> " . ($classExists ? "<span style='color:green'>是</span>" : "<span style='color:red'>否 (這就是原因！)</span>") . "<br>";

echo "<hr>";

if ($countJoin == 0) {
    echo "<h2 style='color:red'>診斷結果：資料庫是空的 (或是無效資料)</h2>";
    echo "請修正 `test_asset_create.php` 並重新執行入庫。";
} else {
    echo "<h2 style='color:green'>診斷結果：資料庫有資料</h2>";
    if (!$classExists) {
        echo "但是 `AssetItem.php` 檔案遺失，所以 API 才會失敗。請建立該檔案。";
    } else {
        echo "資料與檔案都正常，請再次執行 `test_asset_list.php`。";
    }
}
?>