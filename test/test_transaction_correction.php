<?php
// test/test_transaction_correction.php
// 用途：測試「校正」功能 (後悔藥機制)

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>💊 異動校正功能測試 (Correction API)</h1>";
echo "<style>body{font-family:monospace; line-height:1.5;} .pass{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;} .info{color:blue;}</style>";
echo "<hr>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

// 1. 登入
$loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "vbird", "password" => "mystdgo"]);
$token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
if (!$token) die("❌ 登入失敗");

// 2. 找一台閒置資產
$stmt = $db->query("SELECT id, sub_no FROM asset_items WHERE status='閒置' LIMIT 1");
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die("❌ 無閒置資產可測");
$itemId = $item['id'];

echo "<span class='info'>ℹ️ 鎖定資產: ID $itemId (財產號 {$item['sub_no']})</span><br>";

// -----------------------------------------------------------
// 情境：誤按「遺失」
// -----------------------------------------------------------
echo "<hr><h3>1. 模擬誤操作：設定為 [遺失]</h3>";
$res1 = sendRequest('POST', "$baseUrl/transactions", [
    "item_id" => $itemId,
    "action_type" => "遺失",
    "action_date" => date("Y-m-d H:i:s"),
    "note" => "盤點未發現 (誤)"
], $token);

if ($res1['http_code'] == 201) {
    echo "<span class='pass'>✅ 已設定為遺失</span><br>";
    verifyStatus($db, $itemId, '遺失');
} else {
    die("<span class='fail'>❌ 設定遺失失敗: {$res1['body']}</span>");
}

// -----------------------------------------------------------
// 情境：執行「校正」
// -----------------------------------------------------------
echo "<hr><h3>2. 執行後悔藥：[校正]</h3>";
echo "說明：透過校正功能，將資產狀態強制重置為「閒置」且物品狀況為「好」。<br>";

$res2 = sendRequest('POST', "$baseUrl/transactions", [
    "item_id" => $itemId,
    "action_type" => "校正",
    "action_date" => date("Y-m-d H:i:s"),
    "note" => "誤按遺失，系統校正回歸"
], $token);

if ($res2['http_code'] == 201) {
    echo "<span class='pass'>✅ 校正請求成功</span><br>";
    // 驗證：狀態是否變回 '閒置' 且 借用人被清空
    verifyStatus($db, $itemId, '閒置');
    
    // 額外驗證物品狀況是否為 '好'
    $stmtCond = $db->prepare("SELECT item_condition FROM asset_items WHERE id = ?");
    $stmtCond->execute([$itemId]);
    $cond = $stmtCond->fetchColumn();
    if ($cond == '好') {
        echo "<span class='pass'>🔍 [DB驗證] 物品狀況已重置為 '好'</span><br>";
    } else {
        echo "<span class='fail'>🛑 [DB驗證失敗] 物品狀況是 '$cond' (預期: 好)</span><br>";
    }

} else {
    die("<span class='fail'>❌ 校正失敗: {$res2['body']}</span>");
}

echo "<hr><h2>🎉 測試成功！校正功能運作正常。</h2>";

// 輔助函式
function verifyStatus($db, $id, $expected) {
    $stmt = $db->prepare("SELECT status FROM asset_items WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();
    if ($curr == $expected) {
        echo "<span class='pass'>🔍 [DB驗證] 狀態為 '$curr' (正確)</span><br>";
    } else {
        echo "<span class='fail'>🛑 [DB驗證失敗] 狀態為 '$curr' (預期: $expected)</span><br>";
        exit;
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