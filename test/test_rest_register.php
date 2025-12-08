<?php
// test/test_rest_register.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🧪 RESTful API 註冊測試 (TDD)</h1>";
echo "<p>測試目標 URL: <code>http://127.0.0.1/api/auth/register</code> (無 .php 後綴)</p>";
echo "<hr>";

// 1. 準備測試資料 (使用時間戳記避免帳號重複)
$timestamp = time();
$userData = array(
    "staff_code" => "vbird",
    "name"       => "vbird",
    "password"   => "mystdgo"
);

echo "<h3>1. 準備發送的資料：</h3>";
echo "<pre>" . json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

// 2. 發送 POST 請求 (使用 CURL)
$url = 'http://127.0.0.1/api/auth/register'; 

$ch = curl_init($url);
$payload = json_encode($userData);

curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 執行
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 3. 解析並顯示結果
$resJson = json_decode($result, true);

echo "<h3>2. 伺服器回應：</h3>";

if ($httpCode == 201) {
    echo "<div style='color: green; border: 2px solid green; padding: 15px; background: #e8f5e9;'>";
    echo "<h2>✅ 測試成功 (HTTP 201 Created)</h2>";
    echo "<b>訊息:</b> " . htmlspecialchars($resJson['message']) . "<br>";
    if(isset($resJson['data']['token'])) {
         echo "<b>Token:</b> " . substr($resJson['data']['token'], 0, 15) . "...<br>";
    }
    echo "</div>";
} elseif ($httpCode == 404) {
    echo "<div style='color: orange; border: 2px solid orange; padding: 15px; background: #fff3e0;'>";
    echo "<h2>⚠️ 找不到路徑 (HTTP 404)</h2>";
    echo "請確認 .htaccess 設定是否正確。<br>";
    echo "</div>";
} else {
    echo "<div style='color: red; border: 2px solid red; padding: 15px; background: #ffebee;'>";
    echo "<h2>❌ 測試失敗 (HTTP $httpCode)</h2>";
    echo "<b>原始回應:</b> " . htmlspecialchars($result) . "<br>";
    echo "<b>訊息:</b> " . htmlspecialchars($resJson['message'] ?? '無錯誤訊息');
    echo "</div>";
}
?>