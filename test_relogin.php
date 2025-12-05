<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔄 重複登入 Token 變更測試</h1>";
echo "<p>測試目標：驗證每次登入是否都會刷新 Token (單一 Session 安全機制)</p>";
echo "<hr>";

// API 呼叫函式
function callLogin($staff_code, $password) {
    $url = 'http://127.0.0.1/api/auth/login.php';
    $data = array("staff_code" => $staff_code, "password" => $password);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

$user = "teacher01";
$pass = "MySafePassword";

// ==========================================
// 第 1 次登入
// ==========================================
echo "<h3>Step 1: 第一次登入...</h3>";
$res1 = callLogin($user, $pass);

if (isset($res1['data']['token'])) {
    $token1 = $res1['data']['token'];
    echo "拿到 Token A: <code style='color:blue'>$token1</code><br>";
} else {
    die("<b style='color:red'>第一次登入失敗，請檢查帳號密碼</b>");
}

echo "<br>";

// ==========================================
// 第 2 次登入 (模擬在另一台電腦登入)
// ==========================================
echo "<h3>Step 2: 第二次登入 (模擬重新登入)...</h3>";
// 休息 1 秒確保時間戳稍微不同 (雖然 random_bytes 不需要時間戳)
sleep(1); 

$res2 = callLogin($user, $pass);

if (isset($res2['data']['token'])) {
    $token2 = $res2['data']['token'];
    echo "拿到 Token B: <code style='color:green'>$token2</code><br>";
} else {
    die("<b style='color:red'>第二次登入失敗</b>");
}

echo "<hr>";

// ==========================================
// 比對結果
// ==========================================
echo "<h3>驗證結果：</h3>";

if ($token1 !== $token2) {
    echo "<div style='border: 2px solid green; padding: 15px; background-color: #e8f5e9;'>";
    echo "<h2 style='color: green; margin:0;'>✅ 測試通過！</h2>";
    echo "<p>Token 已成功變更。這代表舊的 Token (Token A) 已經在資料庫中被覆蓋，無法再使用了。</p>";
    echo "<ul>";
    echo "<li>舊 Token (尾數): ..." . substr($token1, -10) . "</li>";
    echo "<li>新 Token (尾數): ..." . substr($token2, -10) . "</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='border: 2px solid red; padding: 15px; background-color: #ffebee;'>";
    echo "<h2 style='color: red; margin:0;'>❌ 測試失敗！</h2>";
    echo "<p>警告：Token 沒有變更！這代表安全性不足，或者資料庫更新失敗。</p>";
    echo "</div>";
}
?>
