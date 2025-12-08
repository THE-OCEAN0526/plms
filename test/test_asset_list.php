<?php
// test/test_asset_list.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔍 資產列表查詢測試 (Asset List API) - 需登入版</h1>";
echo "<hr>";

include_once '../config/Database.php';
$baseUrl = 'http://127.0.0.1/api';

// -----------------------------------------------------------------
// 1. 登入取得 Token (身分驗證)
// -----------------------------------------------------------------
echo "<h3>🔐 步驟 0: 登入系統</h3>";
$loginRes = sendPost("$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';

if (!$token) {
    die("<span style='color:red'>❌ 登入失敗，無法進行查詢測試。</span>");
}
echo "<span style='color:green'>✅ 登入成功，取得 Token</span><br>";


// -----------------------------------------------------------------
// 2. 開始測試查詢
// -----------------------------------------------------------------

// 測試 A: 查詢所有資產 (預設分頁)
echo "<h3>1. 查詢所有資產 (Page 1)</h3>";
$res1 = sendGet("$baseUrl/assets", $token);
printTable($res1);

// 測試 B: 篩選「維修中」
echo "<h3>2. 篩選狀態：[維修中]</h3>";
$res2 = sendGet("$baseUrl/assets?status=維修中", $token);
printTable($res2);

// 測試 C: 關鍵字搜尋 (例如搜 'ASUS' 或 '5001')
echo "<h3>3. 關鍵字搜尋：[ASUS]</h3>";
$res3 = sendGet("$baseUrl/assets?keyword=ASUS", $token);
printTable($res3);

// 測試 D: 篩選我的保管 (先確認 T12345 的 ID 是 1)
echo "<h3>4. 篩選擁有者：[ID: 1] (我的資產)</h3>";
$res4 = sendGet("$baseUrl/assets?owner_id=1", $token);
printTable($res4);


// =================================================================
// 輔助函式
// =================================================================

function printTable($response) {
    $data = json_decode($response['body'], true);
    
    // 檢查 HTTP 狀態碼
    if ($response['http_code'] !== 200) {
        echo "<span style='color:red'>❌ 查詢失敗 (HTTP {$response['http_code']}): " . ($data['message'] ?? 'Unknown Error') . "</span><br>";
        return;
    }

    if (!isset($data['data']) || empty($data['data'])) {
        echo "<span style='color:orange'>⚠️ 查無資料</span><br>";
        return;
    }

    echo "<b>總筆數:</b> " . ($data['meta']['total_records'] ?? 0) . " | ";
    echo "<b>頁次:</b> " . ($data['meta']['current_page'] ?? 1) . "/" . ($data['meta']['total_pages'] ?? 1) . "<br>";
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%; font-size:12px;'>";
    echo "<tr style='background:#eee'><th>ID</th><th>財產編號</th><th>品名</th><th>狀態</th><th>位置</th><th>保管人</th><th>借用人</th></tr>";
    
    foreach ($data['data'] as $row) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['sub_no']}</td>";
        echo "<td>{$row['asset_name']}</td>";
        
        // 狀態上色
        $color = 'black';
        if ($row['status'] == '維修中') $color = 'red';
        if ($row['status'] == '閒置') $color = 'green';
        if ($row['status'] == '借用中') $color = 'blue';
        echo "<td style='color:$color'>{$row['status']}</td>";
        
        echo "<td>{$row['location_name']}</td>";
        echo "<td>{$row['owner_name']}</td>";
        echo "<td>{$row['current_user']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

// 發送 GET 請求 (帶 Token)
function sendGet($url, $token) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => $result];
}

// 發送 POST 請求 (登入用)
function sendPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => $result];
}
?>