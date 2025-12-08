<?php
// test/test_maintenance_full.php
// 用途：全自動測試維修模組 (含送修、結案、取消、重複取消、以及掛單待結案)

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔧 維修模組完整測試 (Maintenance API)</h1>";
echo "<style>body{font-family:monospace; line-height:1.5;} .pass{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;} .info{color:blue;}</style>";
echo "<hr>";

include_once '../config/Database.php';

// 0. 初始化
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

// =================================================================
// 1. 登入取得 Token
// =================================================================
echo "<h3>1. 登入系統</h3>";
// 請確保帳號密碼正確
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';

if (!$token) die("<span class='fail'>❌ 登入失敗，無法進行後續測試。回應: {$loginRes['body']}</span>");
echo "<span class='pass'>✅ 登入成功 (Token 取得)</span><br>";


// =================================================================
// 2. 準備測試資產
// =================================================================
echo "<h3>2. 尋找測試用資產</h3>";
// 找一個目前是 '閒置' 的資產
$stmt = $db->query("SELECT id, sub_no, status FROM asset_items WHERE status='閒置' LIMIT 1");
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) die("<span class='fail'>❌ 資料庫中沒有 '閒置' 的資產，請先執行入庫 (test_asset_create.php)。</span>");

$itemId = $item['id'];
echo "<span class='info'>ℹ️ 鎖定資產: ID $itemId (財產號 {$item['sub_no']})，目前狀態: {$item['status']}</span><br>";


// =================================================================
// 情境 A: 標準送修 -> 結案 (Trigger 測試)
// =================================================================
echo "<hr><h3>🅰️ 情境 A: 標準送修 -> 結案 (Trigger 測試)</h3>";

// A-1. 送修
echo "<b>[動作] 送修資產...</b><br>";
$resA1 = sendRequest('POST', "$baseUrl/maintenances", [
    "item_id" => $itemId,
    "send_date" => date("Y-m-d"),
    "action_type" => "維修",
    "vendor" => "測試廠商A"
], $token);

if ($resA1['http_code'] == 201) {
    $maintIdA = json_decode($resA1['body'], true)['id'];
    echo "<span class='pass'>✅ 送修成功 (單號 ID: $maintIdA)</span><br>";
    verifyItemStatus($db, $itemId, '維修中');
} else {
    die("<span class='fail'>❌ 送修失敗: {$resA1['body']}</span>");
}

// A-2. 結案 (維修成功)
echo "<br><b>[動作] 廠商完修，老師結案...</b><br>";
$resA2 = sendRequest('PUT', "$baseUrl/maintenances/$maintIdA", [
    "maintain_result" => "更換電容",
    "result_status" => "維修成功", // Trigger 應該會把資產改回 '閒置'
    "finish_date" => date("Y-m-d"),
    "cost" => 500
], $token);

if ($resA2['http_code'] == 200) {
    echo "<span class='pass'>✅ 結案成功</span><br>";
    // 驗證 DB Trigger 是否運作 (維修成功 -> 閒置)
    verifyItemStatus($db, $itemId, '閒置');
} else {
    die("<span class='fail'>❌ 結案失敗: {$resA2['body']}</span>");
}


// =================================================================
// 情境 B: 送修後反悔 (取消/刪除) 與 重複刪除測試
// =================================================================
echo "<hr><h3>🅱️ 情境 B: 送修 -> 取消 (還原測試) & 重複刪除</h3>";

// B-1. 再次送修
echo "<b>[動作] 再次送修資產...</b><br>";
$resB1 = sendRequest('POST', "$baseUrl/maintenances", [
    "item_id" => $itemId,
    "send_date" => date("Y-m-d"),
    "action_type" => "維修",
    "vendor" => "測試廠商B"
], $token);

$maintIdB = json_decode($resB1['body'], true)['id'];
echo "<span class='pass'>✅ 再次送修成功 (單號 ID: $maintIdB)</span><br>";
verifyItemStatus($db, $itemId, '維修中');

// B-2. 取消送修 (第一次)
echo "<br><b>[動作] 發現填錯了，刪除維修單 (第一次)...</b><br>";
$resB2 = sendRequest('DELETE', "$baseUrl/maintenances/$maintIdB", [], $token);

if ($resB2['http_code'] == 200) {
    echo "<span class='pass'>✅ 刪除成功</span><br>";
    // 驗證是否還原成 '閒置' (讀取 prev_status)
    verifyItemStatus($db, $itemId, '閒置');
} else {
    die("<span class='fail'>❌ 刪除失敗: {$resB2['body']}</span>");
}

// B-3. 重複取消 (第二次) - 測試防呆
echo "<br><b>[動作] 手殘又刪除了一次 (第二次)...</b><br>";
$resB3 = sendRequest('DELETE', "$baseUrl/maintenances/$maintIdB", [], $token);

if ($resB3['http_code'] == 200) {
    echo "<span class='pass'>✅ 重複刪除測試通過 (伺服器回應 200 OK)</span><br>";
    echo "<span class='info'>ℹ️ 檢查資料庫狀態是否依然正確...</span><br>";
    verifyItemStatus($db, $itemId, '閒置');
} else {
    echo "<span class='fail'>❌ 重複刪除導致錯誤 (HTTP {$resB3['http_code']}): {$resB3['body']}</span>";
}


// =================================================================
// 情境 C: 僅送修，等待結案 (狀態應停留在 '維修中')
// =================================================================
echo "<hr><h3>©️ 情境 C: 僅送修，等待結案 (驗證狀態停留)</h3>";

// C-1. 送修
echo "<b>[動作] 送修資產 (預計不結案)...</b><br>";
$resC1 = sendRequest('POST', "$baseUrl/maintenances", [
    "item_id" => $itemId,
    "send_date" => date("Y-m-d"),
    "action_type" => "維修",
    "vendor" => "測試廠商C-待結案"
], $token);

if ($resC1['http_code'] == 201) {
    $maintIdC = json_decode($resC1['body'], true)['id'];
    echo "<span class='pass'>✅ 送修成功 (單號 ID: $maintIdC)</span><br>";
    
    // 驗證 DB 狀態 (重點：必須是 '維修中')
    verifyItemStatus($db, $itemId, '維修中');
    
    echo "<br><span class='info'>ℹ️ 測試結束。此資產 (ID: $itemId) 目前將保持在 [維修中] 狀態，等待日後處理。</span><br>";
    echo "<span class='info'>ℹ️ 若要再次執行此測試腳本，請記得該資產已非閒置狀態。</span>";
} else {
    die("<span class='fail'>❌ 送修失敗: {$resC1['body']}</span>");
}


echo "<hr><h2>🎉 測試結束！所有 API 邏輯驗證完成。</h2>";


// =================================================================
// 輔助函式
// =================================================================

function verifyItemStatus($db, $id, $expectedStatus) {
    $stmt = $db->prepare("SELECT status FROM asset_items WHERE id = ?");
    $stmt->execute([$id]);
    $currStatus = $stmt->fetchColumn();
    
    if ($currStatus === $expectedStatus) {
        echo "<span class='pass'>🔍 [DB驗證] 資產狀態為 '$currStatus' (符合預期)</span><br>";
    } else {
        echo "<span class='fail'>🛑 [DB驗證失敗] 資產狀態是 '$currStatus'，預期應為 '$expectedStatus'</span><br>";
        exit;
    }
}

function sendRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    $payload = json_encode($data);
    
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer " . $token;

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http_code' => $httpCode, 'body' => $result];
}
?><?php
