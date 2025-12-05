<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>📏 財產編號範圍邏輯測試</h1>";

function testBatchCreate($testName, $data) {
    $url = 'http://127.0.0.1/api/asset/batch_create.php';
    $ch = curl_init($url);
    $payload = json_encode($data);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $res = json_decode($result, true);
    
    echo "<h3>$testName</h3>";
    echo "<ul>";
    echo "<li>範圍: " . $data['suf_property_no'] . "</li>";
    echo "<li>數量: " . $data['qty_purchased'] . "</li>";
    
    if ($httpCode == 201) {
        echo "<li style='color:green'><b>✅ 成功</b>: " . $res['message'] . " (" . $res['detail'] . ")</li>";
    } else {
        echo "<li style='color:red'><b>❌ 失敗 ($httpCode)</b>: " . $res['message'] . "</li>";
    }
    echo "</ul><hr>";
}

// 1. 測試成功案例 (5台，編號 101-105)
$goodData = array(
    "batch_no" => "TEST-OK-01", "asset_name" => "正確電腦", "category" => "非消耗品",
    "qty_purchased" => 5, "unit" => "台", "unit_price" => 20000,
    "pre_property_no" => "3013208-63", "suf_property_no" => "101-105", // 101,102,103,104,105 = 5個
    "brand" => "ASUS", "model" => "B9", "spec" => "i7", 
    "purchase_date" => "2025-11-29", "life_years" => 5, 
    "accounting_items" => 1, "fund_source" => "校務基金"
);
testBatchCreate("Case 1: 正確資料 (數量5, 範圍101-105)", $goodData);

// 2. 測試失敗案例 (數量不符：填 5 台，但範圍給 101-102)
$badData = $goodData;
$badData['batch_no'] = "TEST-FAIL-01";
$badData['suf_property_no'] = "101-102"; // 只有 2 個
testBatchCreate("Case 2: 數量不符 (數量5, 範圍101-102)", $badData);

// 3. 測試失敗案例 (格式錯誤)
$badFormat = $goodData;
$badFormat['batch_no'] = "TEST-FAIL-02";
$badFormat['suf_property_no'] = "101,105"; // 格式不對
testBatchCreate("Case 3: 格式錯誤 (範圍寫成逗號)", $badFormat);
?>
