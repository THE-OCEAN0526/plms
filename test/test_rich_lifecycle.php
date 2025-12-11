<?php
// test/test_rich_lifecycle.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>📖 單一資產豐富生命週期測試 (修復版)</h1>";
echo "<style>
        body{font-family: 'Segoe UI', monospace; line-height:1.6; background:#f9f9f9; padding:20px;} 
        h3{background:#6c757d; color:white; padding:8px; border-radius:4px; margin-top:20px;} 
        .step{background:#fff; border-left:4px solid #007bff; padding:10px; margin-bottom:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
        .pass{color:green;font-weight:bold;} 
        .fail{color:red;font-weight:bold;} 
      </style>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

// 設定起始日期：2024-01-01
$currentTimestamp = strtotime("2024-01-01 09:00:00");

// 推進時間函式 (使用 +1 month 讓月份整齊)
function nextTime($str) {
    global $currentTimestamp;
    $currentTimestamp = strtotime($str, $currentTimestamp);
    return date("Y-m-d H:i:s", $currentTimestamp);
}

try {
    // 0. 重置與初始化
    echo "<h3>0. 系統初始化</h3>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['asset_maintenance', 'asset_transactions', 'asset_items', 'asset_batches', 'users', 'locations'] as $t) $db->exec("TRUNCATE TABLE $t");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    $passHash = password_hash("mystdgo", PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (id, staff_code, name, password) VALUES (1, 'T123E001', 'vbird', '$passHash'), (2, 'G140A002', '吳曉明', '$passHash'), (3, 'S001', '陳小華', '$passHash')");
    $db->exec("INSERT INTO locations (id, code, name) VALUES (1, 'STORE', '倉庫'), (2, 'I3502', '多媒體實驗室'), (3, 'I4502', '互動實驗室')");
    
    $loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "T123E001", "password" => "mystdgo"]);
    $token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
    
    // 1. 入庫 (2024-01-01)
    $d = date("Y-m-d", $currentTimestamp);
    echo "<div class='step'>📅 <b>[$d] 入庫：</b> 採購 5 台 MacBook Pro</div>";
    
    $assetData = [
        "batch_no" => "PO-20240101", "asset_name" => "MacBook Pro", "category" => "非消耗品",
        "brand" => "Apple", "model" => "M3 Pro", "qty_purchased" => 5, "unit" => "台", "unit_price" => 75000,
        "pre_property_no" => "3013208-113", "suf_start_no" => 1001, "suf_end_no" => 1005,
        "purchase_date" => $d, "life_years" => 5, "location" => 1
    ];
    sendRequest('POST', "$baseUrl/assets", $assetData, $token);
    $targetId = 1; 

    // 2. 使用 (2024-02-01)
    $d = nextTime("+1 month"); 
    echo "<div class='step'>📅 <b>[$d] 使用：</b> 開學了，配發到實驗室</div>";
    sendRequest('POST', "$baseUrl/transactions", [
        "item_id" => $targetId, "action_type" => "使用", "location_id" => 2, 
        "action_date" => $d, "note" => "開學配發"
    ], $token);

    // 3. 維修 1 (2024-06-01)
    $d = nextTime("+4 months");
    echo "<div class='step'>📅 <b>[$d] 維修：</b> 期中考操壞了，送修</div>";
    $maint1 = sendRequest('POST', "$baseUrl/maintenances", [
        "item_id" => $targetId, "send_date" => date("Y-m-d", strtotime($d)), 
        "action_type" => "維修", "vendor" => "Apple 原廠"
    ], $token);
    $maintId1 = json_decode($maint1['body'], true)['id'];

    // 4. 結案 1 (2024-06-15)
    $d = nextTime("+14 days");
    echo "<div class='step'>📅 <b>[$d] 完修：</b> 修好了，更換電池</div>";
    sendRequest('PUT', "$baseUrl/maintenances/$maintId1", [
        "maintain_result" => "更換電池", "result_status" => "維修成功", 
        "finish_date" => date("Y-m-d", strtotime($d)), "cost" => 3000
    ], $token);

    // 5. 借用 (2024-09-01) - 暑假過後
    // 先把日期跳到 9/1
    $currentTimestamp = strtotime("2024-09-01 09:00:00");
    $d = date("Y-m-d H:i:s", $currentTimestamp);
    echo "<div class='step'>📅 <b>[$d] 借用：</b> 學生陳小華借去比賽</div>";
    sendRequest('POST', "$baseUrl/transactions", [
        "item_id" => $targetId, "action_type" => "借用", "borrower_id" => 3, 
        "location_id" => 3, "expected_return_date" => date("Y-m-d", strtotime($d." +7 days")),
        "action_date" => $d, "note" => "金盾獎比賽"
    ], $token);

    // 6. 維修 2 (2024-10-01) - 這段之前漏掉了
    $d = nextTime("+1 month");
    echo "<div class='step'>📅 <b>[$d] 維修 (第2次)：</b> 比賽中途螢幕閃爍，緊急送修...</div>";
    $maint2 = sendRequest('POST', "$baseUrl/maintenances", [
        "item_id" => $targetId, "send_date" => date("Y-m-d", strtotime($d)), 
        "action_type" => "維修", "vendor" => "Apple 原廠"
    ], $token);
    $maintId2 = json_decode($maint2['body'], true)['id'];

    // 7. 結案 2 (2024-10-05) - 4天修好
    $d = nextTime("+4 days");
    echo "<div class='step'>📅 <b>[$d] 完修 (第2次)：</b> 換好螢幕了，取回...</div>";
    sendRequest('PUT', "$baseUrl/maintenances/$maintId2", [
        "maintain_result" => "更換螢幕排線", "result_status" => "維修成功", 
        "finish_date" => date("Y-m-d", strtotime($d)), "cost" => 5000
    ], $token);

    // 8. 誤觸借用 (2024-12-25)
    $currentTimestamp = strtotime("2024-12-25 09:00:00");
    $d = date("Y-m-d H:i:s", $currentTimestamp);
    echo "<div class='step'>📅 <b>[$d] 誤操作：</b> 聖誕節老師手殘按到借出...</div>";
    sendRequest('POST', "$baseUrl/transactions", [
        "item_id" => $targetId, "action_type" => "借用", "borrower_id" => 2, 
        "location_id" => 2, "expected_return_date" => date("Y-m-d", strtotime($d." +1 day")),
        "action_date" => $d, "note" => "手殘按錯"
    ], $token);

    // 9. 校正 (5 分鐘後)
    $d = nextTime("+5 minutes");
    echo "<div class='step'>📅 <b>[$d] 校正：</b> 馬上發現，執行校正</div>";
    sendRequest('POST', "$baseUrl/transactions", [
        "item_id" => $targetId, "action_type" => "校正", 
        "action_date" => $d, "note" => "系統校正回歸"
    ], $token);

    // 10. 移轉 (2025-01-01)
    $currentTimestamp = strtotime("2025-01-01 09:00:00");
    $d = date("Y-m-d H:i:s", $currentTimestamp);
    echo "<div class='step'>📅 <b>[$d] 移轉：</b> 新年新氣象，移交給吳曉明</div>";
    sendRequest('POST', "$baseUrl/transactions", [
        "item_id" => $targetId, "action_type" => "移轉", "new_owner_id" => 2,
        "action_date" => $d, "note" => "職務調整"
    ], $token);


    // =================================================================
    // 最終驗證：查看履歷表
    // =================================================================
    echo "<h3>4. 資產履歷表 (Timeline)</h3>";
    $histRes = sendRequest('GET', "$baseUrl/assets/$targetId/history", [], $token);
    $json = json_decode($histRes['body'], true);

    if ($histRes['http_code'] == 200) {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%; font-size:14px;'>";
        echo "<tr style='background:#343a40; color:white;'><th>時間</th><th>類型</th><th>動作</th><th>說明</th></tr>";
        
        foreach ($json['timeline'] as $row) {
            $bgColor = 'white';
            if ($row['action_type'] == '校正') $bgColor = '#fff3cd';
            if (strpos($row['action_type'], '維修') !== false) $bgColor = '#ffeef0';
            if ($row['action_type'] == '移轉') $bgColor = '#d4edda';
            if ($row['action_type'] == '入庫') $bgColor = '#d1ecf1'; // 新增入庫顏色

            echo "<tr style='background-color:$bgColor;'>";
            echo "<td>" . substr($row['event_date'], 0, 16) . "</td>";
            echo "<td>{$row['source_type']}</td>";
            echo "<td><b>{$row['action_type']}</b></td>";
            echo "<td>{$row['description']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) { echo $e->getMessage(); }

function sendRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if ($method == 'POST' || $method == 'PUT') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 檢查是否為 4xx 或 5xx 錯誤
    if ($code >= 400) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:10px; border:1px solid #f5c6cb; margin:10px 0;'>";
        echo "<b>⚠️ API Error ($url) - Code: $code</b><br>";
        echo "<pre>" . htmlspecialchars($result) . "</pre>";
        echo "</div>";
    }

    $json = json_decode($result, true);
    if ($json === null && $code < 400 && !empty($result)) {
        echo "<div style='background:orange; color:white; padding:5px;'>⚠️ API 回傳了無效的 JSON: <pre>" . htmlspecialchars($result) . "</pre></div>";
    }
    
    return ['http_code' => $code, 'body' => $result];
}
?>