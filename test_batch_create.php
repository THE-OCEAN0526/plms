<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>📦 資產入庫 API 自動測試 (UTF-8)</h1>";

// 1. 準備測試資料 (這裡的中文絕對是正確的 UTF-8)
$data = array(
    "batch_no"      => "PHP-TEST-2025",
    "asset_name"    => "高階測試伺服器",
    "category"      => "非消耗品",  // 必須完全對應資料庫 ENUM
    "qty_purchased" => 5,
    "unit"          => "台",        // 必須完全對應資料庫 ENUM
    "unit_price"    => 20000,
    // total_price 不傳，讓資料庫自動算
    "spec"          => "CPU: EPYC, RAM: 64G"
);

echo "<h3>發送資料：</h3>";
echo "<pre>" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

// 2. 發送 POST 請求
$url = 'http://127.0.0.1/api/asset/batch_create.php';
$ch = curl_init($url);
$payload = json_encode($data);

curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 3. 顯示結果
$resJson = json_decode($result, true);

echo "<h3>API 回應結果：</h3>";

if ($httpCode == 201) {
    echo "<div style='color: green; font-weight: bold; border: 1px solid green; padding: 10px;'>";
    echo "✅ 測試成功 (HTTP 201 Created)<br>";
    echo "訊息: " . $resJson['message'] . "<br>";
    echo "細節: " . $resJson['detail'];
    echo "</div>";
    
    // 這裡可以順便連資料庫查查看是不是真的進去了
    echo "<h4>資料庫驗證建議：</h4>";
    echo "請使用 SQL 查詢: <code>SELECT * FROM asset_batches WHERE batch_no='PHP-TEST-2025';</code>";
    
} else {
    echo "<div style='color: red; font-weight: bold; border: 1px solid red; padding: 10px;'>";
    echo "❌ 測試失敗 (HTTP $httpCode)<br>";
    echo "錯誤訊息: " . htmlspecialchars($resJson['message']);
    echo "</div>";
}
?>
