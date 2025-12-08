<?php
// test/test_data_seeding.php
// 用途：快速產生多樣化的資產狀態 (用於豐富 Dashboard 數據)
// 包含：使用、借用、維修、報廢、遺失、移轉

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🎲 資產情境模擬與數據填充 (Data Seeding) - V2</h1>";
echo "<hr>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

// -----------------------------------------------------------
// 1. 環境準備
// -----------------------------------------------------------

// 1-1. 登入 (主帳號 T12345)
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
if (!$token) die("❌ 登入失敗");

// 1-2. 確保地點存在
$db->exec("INSERT IGNORE INTO locations (id, code, name) VALUES (1, 'STORE', '倉庫'), (2, 'I305', '多媒體教室'), (3, 'LAB', '電腦教室')");

// 1-3. 確保有「接收移轉」的第二位老師 (ID: 2)
$db->exec("INSERT IGNORE INTO users (id, staff_code, name, password) VALUES (2, 'G140A002', '王小明(B老師)', '1234')");

// 1-4. 確保有「借用」的學生 (ID: 3)
$db->exec("INSERT IGNORE INTO users (id, staff_code, name, password) VALUES (3, 'S001', '陳小華', '1234')");


// -----------------------------------------------------------
// 2. 撈取閒置資產
// -----------------------------------------------------------
$stmt = $db->query("SELECT id, sub_no FROM asset_items WHERE status='閒置' ORDER BY id ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($items) < 12) { // 需求量增加到 12 台
    die("⚠️ 閒置資產太少 (" . count($items) . " 台)，請先執行 `test_asset_create.php` 產生更多資料。");
}

echo "取得 " . count($items) . " 台閒置資產，開始分配六大情境...<br><br>";


// -----------------------------------------------------------
// 3. 劇本分配
// -----------------------------------------------------------

// 群組 A: 3 台 -> 使用中 (分配到教室)
echo "<h3>1. 建立 [使用中] 資料 (3筆)</h3>";
for ($i = 0; $i < 3; $i++) {
    $item = array_shift($items);
    
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "使用",
        "location_id" => 2, // 教室
        "action_date" => date("Y-m-d H:i:s", strtotime("-".rand(1, 30)." days")),
        "note" => "教學使用"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "使用中", $res);
}

// 群組 B: 3 台 -> 借用中 (借給學生)
echo "<h3>2. 建立 [借用中] 資料 (3筆)</h3>";
for ($i = 0; $i < 3; $i++) {
    $item = array_shift($items);

    $payload = [
        "item_id" => $item['id'],
        "action_type" => "借用",
        "borrower_id" => 3, // 陳小華
        "expected_return_date" => date('Y-m-d', strtotime("+".rand(1, 14)." days")),
        "location_id" => 2,
        "action_date" => date("Y-m-d H:i:s", strtotime("-".rand(1, 5)." days")),
        "note" => "專題借用"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "借用中", $res);
}

// 群組 C: 2 台 -> 維修中 (送修)
echo "<h3>3. 建立 [維修中] 資料 (2筆)</h3>";
for ($i = 0; $i < 2; $i++) {
    $item = array_shift($items);

    $payload = [
        "item_id" => $item['id'],
        "send_date" => date("Y-m-d", strtotime("-".rand(1, 20)." days")),
        "action_type" => "維修",
        "vendor" => "ASUS 原廠"
    ];
    // 注意：維修是打 /api/maintenances
    $res = sendRequest('POST', "$baseUrl/maintenances", $payload, $token);
    printResult($item, "維修中", $res);
}

// 群組 D: 1 台 -> 報廢
echo "<h3>4. 建立 [報廢] 資料 (1筆)</h3>";
$item = array_shift($items);
$payload = [
    "item_id" => $item['id'],
    "action_type" => "報廢",
    "action_date" => date("Y-m-d H:i:s"),
    "note" => "螢幕破裂無法修復"
];
$res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
printResult($item, "報廢", $res);

// 群組 E: 1 台 -> 遺失
echo "<h3>5. 建立 [遺失] 資料 (1筆)</h3>";
$item = array_shift($items);
$payload = [
    "item_id" => $item['id'],
    "action_type" => "遺失",
    "action_date" => date("Y-m-d H:i:s"),
    "note" => "盤點未發現"
];
$res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
printResult($item, "遺失", $res);

// 群組 F: 2 台 -> 移轉 (給王小明老師) 【新增部分】
echo "<h3>6. 建立 [移轉] 資料 (2筆)</h3>";
echo "說明：將資產移轉給 王小明 (ID: 2)，這會導致資產從您的保管清單中消失。<br>";

for ($i = 0; $i < 2; $i++) {
    $item = array_shift($items);
    
    $payload = [
        "item_id" => $item['id'],
        "action_type" => "移轉",
        "new_owner_id" => 2, // 移給王小明
        "action_date" => date("Y-m-d H:i:s"),
        "note" => "職務調整移交"
    ];
    $res = sendRequest('POST', "$baseUrl/transactions", $payload, $token);
    printResult($item, "移轉 (給ID:2)", $res);
}

echo "<hr><h2>🎉 資料填充完成！請重新整理 Dashboard 查看效果。</h2>";

// 輔助函式
function printResult($item, $action, $res) {
    if ($res['http_code'] == 201) {
        echo "<span style='color:green'>✅ 資產 {$item['sub_no']} -> $action</span><br>";
    } else {
        echo "<span style='color:red'>❌ 資產 {$item['sub_no']} 失敗: {$res['body']}</span><br>";
    }
}

function sendRequest($method, $url, $data, $token = null) { // 修正1: 加上 = null
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $headers = ['Content-Type: application/json'];
    
    // 修正2: 只有當 token 存在時才加入 Authorization Header
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['http_code' => $info['http_code'], 'body' => $result];
}
?>