<?php
// 設定頁面編碼，確保網頁顯示中文正常
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔐 PLMS 登入 API 自動測試報告</h1>";
echo "<p>測試目標: <a href='/api/login.php'>/api/auth/login.php</a></p>";
echo "<hr>";

// 定義一個發送 POST 請求的函式
function sendPostRequest($url, $data) {
    $ch = curl_init($url);
    $payload = json_encode($data);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // 執行請求
    $result = curl_exec($ch);
    // 取得 HTTP 狀態碼 (例如 200, 401)
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return array("code" => $httpCode, "response" => $result);
}

// ==========================================
// 測試案例 1：使用正確密碼登入
// ==========================================
echo "<h3>Case 1: 測試【正確】帳號密碼 (teacher05 / SafePassword)</h3>";

$loginData = array(
    "staff_code" => "utf8_test",
    "password"   => "pass" 
);

// Server 端自己連自己 localhost
$res = sendPostRequest('http://127.0.0.1/api/auth/login.php', $loginData);
$json = json_decode($res['response'], true);

// 判斷結果
if ($res['code'] == 200 && isset($json['name'])) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<strong>✅ 測試通過！(SUCCESS)</strong><br>";
    echo "HTTP 狀態碼: " . $res['code'] . "<br>";
    echo "登入者姓名: " . $json['name'] . " (確認中文無亂碼)<br>";
    echo "權限角色: " . $json['role'];
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>❌ 測試失敗！</strong><br>";
    echo "HTTP 狀態碼: " . $res['code'] . "<br>";
    echo "回應內容: " . htmlspecialchars($res['response']);
    echo "</div>";
}

echo "<br>";

// ==========================================
// 測試案例 2：使用錯誤密碼登入
// ==========================================
echo "<h3>Case 2: 測試【錯誤】帳號密碼 (teacher05 / WrongPass)</h3>";

$badData = array(
    "staff_code" => "teacher05",
    "password"   => "WrongPassword!!!" 
);

$res = sendPostRequest('http://127.0.0.1/api/auth/login.php', $badData);
$failJson = json_decode($res['response'], true);

// 判斷結果 (預期要是 401)
if ($res['code'] == 401) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<strong>✅ 測試通過！(SUCCESS)</strong> - 系統正確擋下了錯誤密碼<br>";
    echo "HTTP 狀態碼: " . $res['code'] . " (預期 401)<br>";
    echo "回應訊息: " . htmlspecialchars($failJson['message']);
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>❌ 測試失敗！(原本應該要失敗，卻成功了？)</strong><br>";
    echo "HTTP 狀態碼: " . $res['code'] . "<br>";
    echo "回應內容: " . htmlspecialchars($failJson['message']);
    echo "</div>";
}
?>
