<?php
// test/test_asset_create.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🏭 資產大量入庫測試 (Batch Create)</h1>";
echo "<hr>";

include_once '../config/Database.php';
$baseUrl = 'http://127.0.0.1/api';

// 1. 登入
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
if (!$token) die("❌ 登入失敗");

// 2. 準備入庫資料 (一次進貨 20 台)
// 使用隨機後綴避免重複 (例如 PO-20251207-XXXX)
$batchNo = "PO-" . date("Ymd-His");
$startNo = 1001; 
$endNo   = 1020; // 總共 20 台
$qty     = $endNo - $startNo + 1;

$assetData = [
    "batch_no"        => $batchNo,
    "asset_name"      => "多樣化測試筆電", // 改個名字區分
    "category"        => "非消耗品",
    "brand"           => "ASUS",
    "model"           => "ExpertBook B9",
    "spec"            => "i7/16G/512G",
    "qty_purchased"   => $qty,
    "unit"            => "台",
    "unit_price"      => 45000,
    "pre_property_no" => "3013208-".date("md"), // 隨機財產前綴
    "suf_start_no"    => $startNo,
    "suf_end_no"      => $endNo,
    "purchase_date"   => date("Y-m-d"),
    "life_years"      => 5,
    "fund_source"     => "深耕計畫",
    "location"        => 1 // 預設在倉庫
];

echo "<h3>準備入庫 $qty 台資產...</h3>";
echo "批號: $batchNo <br>財產編號範圍: $startNo ~ $endNo<br>";

$res = sendRequest('POST', "$baseUrl/assets", $assetData, $token);

if ($res['http_code'] == 201) {
    echo "<h2 style='color:green'>✅ 入庫成功！</h2>";
    echo "已建立 20 台閒置資產，現在可以執行 `test_data_seeding.php` 來分配狀態了。";
} else {
    echo "<h2 style='color:red'>❌ 入庫失敗</h2>";
    echo $res['body'];
}

function sendRequest($method, $url, $data, $token = null) { // 修正1: 加上 = null
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $headers = ['Content-Type: application/json'];
    
    // 修正2: 只有當 token 存在時才加入 Authorization Header
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['http_code' => $info['http_code'], 'body' => $result];
}
?>