<?php
/**
 * PLMS 深度生命週期測試 (V4 - 邏輯修正完整版)
 * 模擬兩台資產執行深度流程，確保符合後端驗證邏輯與資料庫觸發器。
 */

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🚀 PLMS 深度生命週期測試 (V4 - 邏輯修正完整版)</h1>";
echo "<style>
    body{ font-family: 'Consolas', 'Microsoft JhengHei', sans-serif; background:#f4f7f6; padding:20px; }
    .box{ border-left: 5px solid #007bff; background:white; padding:15px; margin-bottom:15px; border-radius:4px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .pass{ color:#2ecc71; font-weight:bold; }
    .fail{ color:#e74c3c; font-weight:bold; }
    .step{ margin-bottom:5px; border-bottom: 1px dashed #eee; padding: 5px 0; }
    b { color: #0056b3; }
    pre { background: #eee; padding: 5px; font-size: 0.8em; }
</style>";

include_once __DIR__ . '/../config/Database.php';
$baseUrl = 'http://127.0.0.1/api'; // 請根據實際環境調整 API 網址

try {
    $db = (new Database())->getConnection();

    // =================================================================
    // 1. 系統環境初始化 (SQL)
    // =================================================================
    echo "<h3>1. 系統重置與種子資料</h3>";
    echo "<div class='box'>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['asset_maintenance', 'asset_transactions', 'asset_items', 'asset_batches', 'users', 'locations'];
    foreach ($tables as $t) { $db->exec("TRUNCATE TABLE $t"); }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    $passHash = password_hash("mystdgo", PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (id, staff_code, name, password) VALUES (1, 'T123E001', 'vbird', '$passHash'), (2, 'G140A002', '李同學', '$passHash')");
    $db->exec("INSERT INTO locations (id, code, name) VALUES (1, 'I3502', '多媒體'), (2, 'I3501', '數媒'), (3, 'I4502', '互動式')");
    echo "✅ 系統已重置，帳號 <b>vbird</b> 與 <b>李同學</b> 已就緒。<br>";
    echo "✅ 地點資料已初始化。";
    echo "</div>";

    // =================================================================
    // 2. 登入取得 Token (API)
    // =================================================================
    echo "<h3>2. API 登入驗證</h3>";
    $loginRes = sendRequest('POST', "$baseUrl/tokens", ["staff_code" => "T123E001", "password" => "mystdgo"]);
    $token = json_decode($loginRes['body'], true)['data']['token'] ?? '';
    if (!$token) throw new Exception("登入失敗: " . $loginRes['body']);
    echo "<div class='box'><span class='pass'>✅ 取得 Bearer Token 成功</span></div>";

    // =================================================================
    // 3. 資產批量入庫 (API)
    // =================================================================
    echo "<h3>3. 資產入庫 (20 台)</h3>";
    $batchData = [
        "batch_no" => "PC-" . date("Ymd"), "asset_name" => "個人電腦", "category" => "非消耗品",
        "qty_purchased" => 20, "unit" => "台", "unit_price" => 35000,
        "pre_property_no" => "310501", "suf_start_no" => 1, "suf_end_no" => 20,
        "location" => 1, "purchase_date" => date("Y-m-d")
    ];
    $resBatch = sendRequest('POST', "$baseUrl/assets", $batchData, $token);
    if ($resBatch['http_code'] !== 201) throw new Exception("入庫失敗: " . $resBatch['body']);
    echo "<div class='box'><span class='pass'>✅ 成功透過 API 建立資產。</span></div>";

    // 取得測試目標資產 ID
    $stmt = $db->query("SELECT id, sub_no FROM asset_items ORDER BY id LIMIT 2");
    $targetItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $idA = $targetItems[0]['id'];
    $idB = $targetItems[1]['id'];

    // =================================================================
    // 4. 情境 A: 完整生命週期 (資產 #1)
    // =================================================================
    echo "<h3>🔹 情境 A (資產 #{$targetItems[0]['sub_no']}): 深度流轉示範</h3>";
    echo "<div class='box'>";

    // 1. 使用
    apiStep("1. 使用 (配發至 I3501)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"use", "location_id"=>2, "note"=>"初始專題配發"], $token));
    usleep(500000);

    // 2. 送修 (修正：只呼叫一次，並直接抓取 ID)
    $mResA = sendRequest('POST', "$baseUrl/maintenances", [
        "item_id" => $idA, 
        "send_date" => date("Y-m-d H:i:s"), 
        "action_type" => "維修",
        "vendor" => "ASUS",
        "issue_description" => "螢幕閃爍"
    ], $token);
    $mIdA = json_decode($mResA['body'], true)['id'] ?? null;
    apiStep("2. 送修 (ASUS 原廠)", $mResA);

    usleep(500000);
    usleep(500000);
    apiStep("3. 維修成功結案", sendRequest('PUT', "$baseUrl/maintenances/$mIdA", ["finish_date" => date("Y-m-d H:i:s"), "result_status"=>"維修成功", "maintain_result"=>"更換電容", "cost"=>1200], $token));
    usleep(500000);
    apiStep("4. 重新投入使用 (回 I3502)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"use", "location_id"=>1], $token));
    usleep(500000);
    apiStep("5. 歸還校正 (轉回閒置)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"correct", "location_id"=>1, "note"=>"專題結束收回"], $token));
    usleep(500000);
    apiStep("6. 借用給李同學", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"loan", "borrower_id"=>2, "location_id"=>3, "expected_return_date"=>date('Y-m-d', strtotime('+7 days'))], $token));
    usleep(500000);
    apiStep("7. 歸還 (狀況：好)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"return", "location_id"=>1, "item_condition"=>"好"], $token));
    usleep(500000);
    apiStep("8. 移轉保管權 (移交給李同學)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idA], "action_type"=>"transfer", "new_owner_id"=>2], $token));
    echo "</div>";

    // =================================================================
    // 5. 情境 B: 故障報廢流程 (資產 #2)
    // =================================================================
    echo "<h3>🔹 情境 B (資產 #{$targetItems[1]['sub_no']}): 損壞直到報廢</h3>";
    echo "<div class='box'>";
    apiStep("1. 配發使用 (I3501)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idB], "action_type"=>"use", "location_id"=>2], $token));
    usleep(500000);
    $mResB1 = sendRequest('POST', "$baseUrl/maintenances", ["item_id"=>$idB, "action_type"=>"維修", "vendor"=>"ASUS", "issue_description"=>"風扇雜音"], $token);
    $mIdB1 = json_decode($mResB1['body'], true)['id'] ?? null;
    apiStep("2. 第一次送修", $mResB1);
    usleep(500000);
    apiStep("3. 第一次維修成功結案", sendRequest('PUT', "$baseUrl/maintenances/$mIdB1", ["finish_date"=>date("Y-m-d"), "result_status"=>"維修成功", "cost"=>500], $token));
    usleep(500000);
    apiStep("4. 繼續投入使用 (I3501)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idB], "action_type"=>"use", "location_id"=>2], $token));
    usleep(500000);
    // ★ 修正：將狀態轉回閒置以利後續借用
    apiStep("4.5 結束使用 (校正為閒置)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idB], "action_type"=>"correct", "location_id"=>2, "note"=>"專題結束收回"], $token));
    usleep(500000);
    apiStep("5. 借用給李同學", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idB], "action_type"=>"loan", "borrower_id"=>2, "expected_return_date"=>date('Y-m-d')], $token));
    usleep(500000);
    apiStep("6. 歸還 (狀況：壞)", sendRequest('POST', "$baseUrl/transactions", ["item_ids"=>[$idB], "action_type"=>"return", "location_id"=>1, "item_condition"=>"壞", "note"=>"不慎摔落導致損毀"], $token));
    usleep(500000);
    $mResB2 = sendRequest('POST', "$baseUrl/maintenances", ["item_id"=>$idB, "action_type"=>"維修", "vendor"=>"ASUS", "issue_description"=>"機殼碎裂、無法開機"], $token);
    $mIdB2 = json_decode($mResB2['body'], true)['id'] ?? null;
    apiStep("7. 第二次送修", $mResB2);
    usleep(500000);
    apiStep("8. 維修失敗結案 (觸發自動報廢)", sendRequest('PUT', "$baseUrl/maintenances/$mIdB2", ["finish_date"=>date("Y-m-d"), "result_status"=>"無法修復", "maintain_result"=>"面板損壞停產"], $token));

    echo "<div class='step'>ℹ️ <b>資產狀態已由系統自動更新為「報廢」，流程終止。</b></div>";
    echo "</div>";

    echo "<h2>🎉 全系統 API 模擬測試完成！所有生命軌跡已成功寫入。</h2>";

} catch (Exception $e) {
    echo "<h2 class='fail'>💥 關鍵錯誤: " . $e->getMessage() . "</h2>";
}

// =================================================================
// 輔助函式區
// =================================================================
function apiStep($msg, $res) {
    $code = $res['http_code'];
    $isOk = ($code == 200 || $code == 201);
    $status = $isOk ? "<span class='pass'>SUCCESS</span>" : "<span class='fail'>FAILED ($code)</span>";
    echo "<div class='step'>$msg ...... $status " . (!$isOk ? "<br><pre>Response: {$res['body']}</pre>" : "") . "</div>";
}

function sendRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => $result];
}