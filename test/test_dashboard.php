<?php
// test/test_dashboard.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>📊 Dashboard API 測試</h1>";
echo "<hr>";

include_once '../config/Database.php';

$baseUrl = 'http://127.0.0.1/api';

// 1. 先登入取得 Token
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';

if (!$token) die("<span style='color:red'>❌ 登入失敗</span>");
echo "<span style='color:green'>✅ 登入成功，取得 Token</span><br>";

// 2. 呼叫 Dashboard API
echo "<h3>取得 Dashboard 資料 (GET /api/dashboard/summary)...</h3>";
$dashRes = sendRequest('GET', "$baseUrl/dashboard/summary", [], $token);
$data = json_decode($dashRes['body'], true);

if ($dashRes['http_code'] == 200) {
    echo "<div style='background:#f4f4f4; padding:10px; border-left: 5px solid green;'>";
    echo "<h3>✅ API 回傳成功</h3>";

    
    echo "<b>[數據卡片 Stats]</b><br>";
    echo "總數: " . ($data['stats']['total'] ?? 0) . "<br>";
    echo "閒置: " . ($data['stats']['idle'] ?? 0) . "<br>";
    echo "維修中: " . ($data['stats']['repair'] ?? 0) . "<br><br>";

    echo "<b>[近期動態 Recent] (最近3個月)</b><br>";
    if (empty($data['recent_activities'])) {
        echo "無近期資料<br>";
    } else {
        foreach ($data['recent_activities'] as $act) {
            echo "<li>[{$act['updated_at']}] {$act['asset_name']} - 狀態變更為: {$act['status']}</li>";
        }
    }
    
    echo "<br><b>[待辦事項 Todos]</b><br>";
    if (empty($data['todos'])) {
        echo "目前無待辦事項<br>";
    } else {
        foreach ($data['todos'] as $todo) {
            echo "<li style='color:orange'>{$todo['title']}: {$todo['message']}</li>";
        }
    }
    echo "</div>";

    // 顯示原始 JSON 供前端參考
    echo "<h3>原始 JSON 回應 (供前端參考):</h3>";
    echo "<textarea style='width:100%; height:300px;'>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</textarea>";

} else {
    echo "<span style='color:red'>❌ 失敗: " . $dashRes['body'] . "</span>";
}

// 輔助函式
function sendRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer " . $token;

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => $result];
}
?>