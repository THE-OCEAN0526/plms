<?php
// 設定編碼，確保中文顯示正常
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔐 PLMS 身份驗證流程全自動測試</h1>";
echo "<p>測試目標：註冊 -> 登入 -> 取得 Token</p>";
echo "<hr>";

// 定義通用的 API 發送函式
function callAPI($endpoint, $data) {
    $url = 'http://127.0.0.1/api/auth/' . $endpoint; // 注意路徑是 api/auth/
    $ch = curl_init($url);
    $payload = json_encode($data);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return array("code" => $httpCode, "body" => json_decode($result, true));
}

// ==========================================
// 1. 測試註冊 (Register)
// ==========================================
echo "<h3>Step 1: 註冊新使用者 (Register)</h3>";

$userData = array(
    "staff_code" => "teacher01",
    "name"       => "王小明",
    "password"   => "MySafePassword",
    "role"       => "user"
);

$resReg = callAPI('register.php', $userData);

if ($resReg['code'] == 201) {
    echo "<div style='color: green; border:1px solid green; padding:10px;'>";
    echo "✅ <b>註冊成功 (HTTP 201)</b><br>";
    echo "訊息: " . $resReg['body']['message'];
    echo "</div>";
} else {
    echo "<div style='color: red; border:1px solid red; padding:10px;'>";
    echo "❌ <b>註冊失敗 (HTTP " . $resReg['code'] . ")</b><br>";
    echo "訊息: " . ($resReg['body']['message'] ?? '未知錯誤');
    echo "</div>";
    // 如果註冊失敗，就不繼續測登入了
    exit;
}

echo "<br>";

// ==========================================
// 2. 測試登入 (Login)
// ==========================================
echo "<h3>Step 2: 嘗試登入並取得 Token (Login)</h3>";

$loginData = array(
    "staff_code" => "teacher01",
    "password"   => "MySafePassword"
);

$resLogin = callAPI('login.php', $loginData);

if ($resLogin['code'] == 200) {
    echo "<div style='color: blue; border:1px solid blue; padding:10px;'>";
    echo "✅ <b>登入成功 (HTTP 200)</b><br>";
    echo "訊息: " . $resLogin['body']['message'] . "<br>";
    echo "-------------------------------------------------<br>";
    
    // 檢查是否有回傳 data 結構
    if (isset($resLogin['body']['data'])) {
        $user = $resLogin['body']['data'];
        echo "<b>登入者資訊：</b><br>";
        echo "姓名: " . $user['name'] . "<br>";
        echo "權限: " . $user['role'] . "<br>";
        
        // ★ 重點檢查 Token
        if (!empty($user['token'])) {
            echo "<br><span style='background: yellow; color: black; padding: 3px;'>🔑 <b>成功取得 Token:</b></span><br>";
            echo "<code style='font-size: 1.2em;'>" . $user['token'] . "</code>";
        } else {
            echo "<br><b style='color:red'>❌ 警告：沒有收到 Token！請檢查資料庫欄位或程式碼。</b>";
        }
    } else {
        echo "<b style='color:red'>❌ 格式錯誤：找不到 data 欄位</b>";
    }
    
    echo "</div>";
} else {
    echo "<div style='color: red; border:1px solid red; padding:10px;'>";
    echo "❌ <b>登入失敗 (HTTP " . $resLogin['code'] . ")</b><br>";
    echo "訊息: " . ($resLogin['body']['message'] ?? '未知錯誤');
    echo "</div>";
}
?>
