<?php
// test/test_data_seeding.php
// 用途：快速產生多樣化的資產狀態 (用於豐富 Dashboard 數據)
// 包含：使用、借用、維修、報廢、遺失、移轉

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🎲 資產情境模擬與數據填充 (Data Seeding) - V3 (含報廢修正)</h1>";
echo "<hr>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

// -----------------------------------------------------------
// 1. 環境準備
// -----------------------------------------------------------

// 1-1. 登入
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
if (!$token) die("❌ 登入失敗");

// 1-2. 確保地點與人員
$db->exec("INSERT IGNORE INTO locations (id, code, name) VALUES (1, 'STORE', '倉庫'), (2, 'I305', '多媒體教室'), (3, 'LAB', '電腦教室')");
$db->exec("INSERT IGNORE INTO users (id, staff_code, name, password) VALUES (2, 'G140A002', '王小明(B老師)', '1234')");
$db->exec("INSERT IGNORE INTO users (id, staff_code, name, password) VALUES (3, 'S001', '陳小華', '1234')");

// -----------------------------------------------------------
// 2. 撈取閒置資產
// -----------------------------------------------------------
$stmt = $db->query("SELECT id, sub_no FROM asset_items WHERE status='閒置' ORDER BY id ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($items) < 12) {
    die("⚠️ 閒置資產太少 (" . count($items) . " 台)，請先執行 `test_asset_create.php` 產生更多資料。");
}

echo "取得 " . count($items) . " 台閒置資產，開始分配六大情境...<br><br>";

// -----------------------------------------------------------
// 3. 劇本分配
// -----------------------------------------------------------

// 群組 A: 3 台 -> 使用中
echo "<h3>1. 建立 [使用中] 資料 (3筆)</h3>";
for ($i = 0; $i < 3; $i++) {
    $item = array_shift($items);
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "使用",
        "location_id" => 2,
        "action_date" => date("Y-m-d H:i:s", strtotime("-".rand(1, 30)." days")),
        "note" => "教學使用"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "使用中", $res);
}

// 群組 B: 3 台 -> 借用中
echo "<h3>2. 建立 [借用中] 資料 (3筆)</h3>";
for ($i = 0; $i < 3; $i++) {
    $item = array_shift($items);
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "借用",
        "borrower_id" => 3,
        "expected_return_date" => date('Y-m-d', strtotime("+".rand(1, 14)." days")),
        "location_id" => 2,
        "action_date" => date("Y-m-d H:i:s", strtotime("-".rand(1, 5)." days")),
        "note" => "專題借用"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "借用中", $res);
}

// 群組 C: 2 台 -> 維修中
echo "<h3>3. 建立 [維修中] 資料 (2筆)</h3>";
for ($i = 0; $i < 2; $i++) {
    $item = array_shift($items);
    $payload = [
        "item_id" => $item['id'],
        "send_date" => date("Y-m-d", strtotime("-".rand(1, 20)." days")),
        "action_type" => "維修",
        "vendor" => "ASUS 原廠"
    ];
    $res = sendRequest('POST', "$baseUrl/maintenances", $payload, $token);
    printResult($item, "維修中", $res);
}

// 群組 D: 1 台 -> 報廢 (修正版)
echo "<h3>4. 建立 [報廢] 資料 (1筆)</h3>";
$item = array_shift($items);
if ($item) {
    // 【修正】先手動改成「壞」，才能通過 API 驗證
    $db->exec("UPDATE asset_items SET item_condition='壞' WHERE id = {$item['id']}");
    echo "<span style='color:blue'>ℹ️ (前置作業) 將資產 {$item['sub_no']} 狀況設定為「壞」</span><br>";

    $payload = [
        "item_id" => $item['id'],
        "action_type" => "報廢",
        "action_date" => date("Y-m-d H:i:s"),
        "note" => "螢幕破裂無法修復"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "報廢", $res);
}

// 群組 E: 1 台 -> 遺失
echo "<h3>5. 建立 [遺失] 資料 (1筆)</h3>";
$item = array_shift($items);
if ($item) {
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "遺失",
        "action_date" => date("Y-m-d H:i:s"),
        "note" => "盤點未發現"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "遺失", $res);
}

// 群組 F: 2 台 -> 移轉
echo "<h3>6. 建立 [移轉] 資料 (2筆)</h3>";
for ($i = 0; $i < 2; $i++) {
    $item = array_shift($items);
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "移轉",
        "new_owner_id" => 2,
        "action_date" => date("Y-m-d H:i:s"),
        "note" => "職務調整移交"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "移轉 (給ID:2)", $res);
}

echo "<hr><h2>🎉 資料填充完成！</h2>";

// 輔助函式
function printResult($item, $action, $res) {
    if ($res['http_code'] == 201) {
        echo "<span style='color:green'>✅ 資產 {$item['sub_no']} -> $action</span><br>";
    } else {
        echo "<span style='color:red'>❌ 資產 {$item['sub_no']} 失敗: {$res['body']}</span><br>";
    }
}

function sendRequest($method, $url, $data = [], $token = null) {
    // ... (原本的 cURL 設定) ...
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 【加入這段 Debug】: 如果解析失敗，印出原始回應讓我們看
    $jsonCheck = json_decode($result, true);
    if ($jsonCheck === null && $code != 204) {
        echo "<div style='background:red; color:white; padding:10px;'>";
        echo "<h3>💥 API 回傳了非 JSON 資料！</h3>";
        echo "<strong>URL:</strong> $url <br>";
        echo "<strong>HTTP Code:</strong> $code <br>";
        echo "<strong>原始回應:</strong> <pre>" . htmlspecialchars($result) . "</pre>";
        echo "</div>";
    }

    // 原本的除錯功能保留
    if ($code >= 400) {
       // ...
    }
    
    return ['http_code' => $code, 'body' => $result];
}
?>