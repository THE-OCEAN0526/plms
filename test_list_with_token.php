<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔐 帶 Token 查詢資產測試</h1>";

// ★ 請填入您資料庫裡真實有效的 Token (從 users 表找一個)
$myToken = "65d6271183d435b9ee72624b44dd42aaf2b9c4560310ded4f031d5f32904592f"; 
// 例如: "a4f1d8c9e5b2..."

function getListWithToken($token) {
    $url = 'http://127.0.0.1/api/asset/batch_list.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // ★ 關鍵：把 Token 放在 Header 裡
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token // 標準寫法 Bearer + 空格 + Token
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $res = json_decode($result, true);
    
    echo "使用 Token: " . substr($token, 0, 10) . "...<br>";
    if ($httpCode == 200) {
        echo "<b style='color:green'>✅ 成功 (200)</b> - 看到 " . count($res['data']) . " 筆資料<br>";
    } else {
        echo "<b style='color:red'>❌ 失敗 ($httpCode)</b> - " . $res['message'] . "<br>";
    }
    echo "<hr>";
}

// 1. 測試：使用無效 Token (亂打)
echo "<h3>Case 1: 壞人 (亂打 Token)</h3>";
getListWithToken("bad_token_12345");

// 2. 測試：使用正確 Token
echo "<h3>Case 2: 好人 (正確 Token)</h3>";
// 如果您還沒填上面的 $myToken，請先去資料庫撈一下
// podman exec plms-db mariadb -u root -p -e "SELECT api_token FROM plms_db.users LIMIT 1;"
getListWithToken($myToken);
?>
