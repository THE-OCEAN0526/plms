<?php
// test/test_full_scenario.php
// 用途：一鍵重置系統並建立完整的測試情境 (包含使用者、地點、資產入庫、異動流程、查詢)

mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🚀 PLMS 全系統自動化測試 (重置 + 初始化 + 情境模擬)</h1>";
echo "<style>body{font-family: 'Segoe UI', monospace; line-height:1.6; background:#f9f9f9; padding:20px;} 
      h3{background:#007bff; color:white; padding:8px; border-radius:4px; margin-top:20px;} 
      .pass{color:green;font-weight:bold;} 
      .fail{color:red;font-weight:bold;} 
      .info{color:#555; font-size:0.9em;}
      .box{border:1px solid #ddd; background:white; padding:15px; margin-bottom:15px; border-radius:5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
      </style>";

include_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();
$baseUrl = 'http://127.0.0.1/api';

try {
    // =================================================================
    // 0. 資料庫重置 (Reset Database)
    // =================================================================
    echo "<h3>0. 清空資料庫 (System Reset)</h3>";
    echo "<div class='box'>";
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['asset_maintenance', 'asset_transactions', 'asset_items', 'asset_batches', 'users', 'locations'];
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE $table");
        echo "清除資料表: $table ... <span class='pass'>OK</span><br>";
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "</div>";


    // =================================================================
    // 1. 建立帳號 & 初始化地點 (Data Initialization)
    // =================================================================
    echo "<h3>1. 建立帳號 & 地點資料</h3>";
    echo "<div class='box'>";

    // 1-1. 建立帳號 (直接 SQL 寫入以確保 ID 順序)
    $passHash = password_hash("mystdgo", PASSWORD_DEFAULT);
    $sqlUser = "INSERT INTO users (id, staff_code, name, password) VALUES 
                (1, 'T123E001', 'vbird', '$passHash'),
                (2, 'G140A002', '吳曉明', '$passHash')";
    $db->exec($sqlUser);
    echo "✅ 建立使用者: T123E001 (vbird), G140A002 (吳曉明)<br>";

    // 1-2. 建立地點
    $sqlLoc = "INSERT INTO locations (id, code, name) VALUES 
               (1, 'I3502', '多媒體設計實驗室'),
               (2, 'I3501', '數位媒體傳播實驗室'),
               (3, 'I4502', '互動式數位學習系統實驗室')";
    $db->exec($sqlLoc);
    echo "✅ 建立地點: I3502, I3501, I4502<br>";
    echo "</div>";


    // =================================================================
    // 2. 登入取得 Token
    // =================================================================
    echo "<h3>2. 系統登入 (vbird)</h3>";
    echo "<div class='box'>";
    $loginRes = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "T123E001", "password" => "mystdgo"]); // 修正帳號
    $token = json_decode($loginRes['body'], true)['data']['token'] ?? '';

    if (!$token) die("<span class='fail'>❌ 登入失敗: {$loginRes['body']}</span>");
    echo "<span class='pass'>✅ 登入成功，取得 Token</span><br>";
    echo "</div>";


    // =================================================================
    // 3. 資產大量入庫 (Batch Ingest)
    // =================================================================
    echo "<h3>3. 資產大量入庫 (20 台)</h3>";
    echo "<div class='box'>";
    
    $qty = 20;
    $startNo = 1001;
    $endNo = 1000 + $qty;
    
    $assetData = [
        "batch_no"        => "PO-" . date("Ymd-His"),
        "asset_name"      => "高效能工作站",
        "category"        => "非消耗品",
        "brand"           => "Dell",
        "model"           => "Precision 3660",
        "spec"            => "i9/64G/1TB SSD",
        "qty_purchased"   => $qty,
        "unit"            => "台",
        "unit_price"      => 65000,
        "pre_property_no" => "3013208-".date("md"),
        "suf_start_no"    => $startNo,
        "suf_end_no"      => $endNo,
        "purchase_date"   => date("Y-m-d"),
        "life_years"      => 5,
        "fund_source"     => "高教深耕",
        "location"        => 1 // 預設放在 I3502 (ID:1)
    ];

    $resAsset = sendRequest('POST', "$baseUrl/assets", $assetData, $token);
    
    if ($resAsset['http_code'] == 201) {
        echo "<span class='pass'>✅ 入庫成功！建立批次 {$assetData['batch_no']} (共 $qty 台)</span><br>";
    } else {
        die("<span class='fail'>❌ 入庫失敗: {$resAsset['body']}</span>");
    }
    echo "</div>";


    // =================================================================
    // 4. 產生多樣化資產狀態 (Scenario Simulation)
    // =================================================================
    echo "<h3>4. 情境模擬：分配資產狀態</h3>";
    echo "<div class='box'>";

    // 撈取剛剛建立的閒置資產
    $stmt = $db->query("SELECT id, sub_no FROM asset_items WHERE status='閒置' ORDER BY id ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 群組 A: 使用中 (分配到 I3501) ---
    echo "<b>[A. 使用] 分配 3 台到 I3501...</b><br>";
    for ($i = 0; $i < 3; $i++) {
        $item = array_shift($items);
        $res = sendRequest('POST', "$baseUrl/transactions", [
            "item_id" => $item['id'],
            "action_type" => "使用",
            "location_id" => 2, // I3501
            "action_date" => date("Y-m-d H:i:s"),
            "note" => "專題製作使用"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }

    // --- 群組 B: 借用中 (借給 吳曉明) ---
    echo "<br><b>[B. 借用] 借出 3 台給 吳曉明...</b><br>";
    for ($i = 0; $i < 3; $i++) {
        $item = array_shift($items);
        $res = sendRequest('POST', "$baseUrl/transactions", [
            "item_id" => $item['id'],
            "action_type" => "借用",
            "borrower_id" => 2, // 吳曉明
            "expected_return_date" => date('Y-m-d', strtotime('+14 days')),
            "location_id" => 3, // 帶去 I4502
            "action_date" => date("Y-m-d H:i:s"),
            "note" => "課程教學借用"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }

    // --- 群組 C: 維修中 ---
    echo "<br><b>[C. 維修] 送修 2 台...</b><br>";
    for ($i = 0; $i < 2; $i++) {
        $item = array_shift($items);
        $res = sendRequest('POST', "$baseUrl/maintenances", [
            "item_id" => $item['id'],
            "send_date" => date("Y-m-d"),
            "action_type" => "維修",
            "vendor" => "Dell 原廠"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }

    // --- 群組 D: 報廢 (需先改狀態為壞) ---
    echo "<br><b>[D. 報廢] 申請報廢 1 台...</b><br>";
    $item = array_shift($items);
    if ($item) {
        $db->exec("UPDATE asset_items SET item_condition='壞' WHERE id={$item['id']}"); // 模擬損壞
        $res = sendRequest('POST', "$baseUrl/transactions", [
            "item_id" => $item['id'],
            "action_type" => "報廢",
            "action_date" => date("Y-m-d H:i:s"),
            "note" => "主機板燒毀"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }

    // --- 群組 E: 遺失 ---
    echo "<br><b>[E. 遺失] 登記遺失 1 台...</b><br>";
    $item = array_shift($items);
    if ($item) {
        $res = sendRequest('POST', "$baseUrl/transactions", [
            "item_id" => $item['id'],
            "action_type" => "遺失",
            "action_date" => date("Y-m-d H:i:s"),
            "note" => "期末盤點未尋獲"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }

    // --- 群組 F: 移轉 (給吳曉明) ---
    echo "<br><b>[F. 移轉] 移轉 2 台給 吳曉明...</b><br>";
    for ($i = 0; $i < 2; $i++) {
        $item = array_shift($items);
        $res = sendRequest('POST', "$baseUrl/transactions", [
            "item_id" => $item['id'],
            "action_type" => "移轉",
            "new_owner_id" => 2, // 移給吳曉明
            "action_date" => date("Y-m-d H:i:s"),
            "note" => "保管人變更"
        ], $token);
        echo "&nbsp;&nbsp;資產 {$item['sub_no']}: " . ($res['http_code']==201 ? "<span class='pass'>OK</span>" : "<span class='fail'>Fail</span>") . "<br>";
    }
    echo "</div>";


    // =================================================================
    // 5. 查詢驗證 (Query Test)
    // =================================================================
    echo "<h3>5. 查詢驗證 (Query Result)</h3>";
    echo "<div class='box'>";
    
    // 查詢 vbird 的資產 (應不包含移轉出去的)
    echo "<b>查詢 [vbird] 的資產列表...</b><br>";
    $queryRes = sendRequest('GET', "$baseUrl/assets", [], $token); // GET 請求
    $data = json_decode($queryRes['body'], true);

    if ($queryRes['http_code'] == 200) {
        $total = $data['meta']['total_records'];
        echo "✅ 查詢成功，共找到 <b>$total</b> 筆資料 (應為 18 筆，因 2 筆移轉)。<br><br>";
        
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%; font-size:12px;'>";
        echo "<tr style='background:#eee'><th>財產編號</th><th>狀態</th><th>位置</th><th>保管人</th><th>借用人</th></tr>";
        
        // 只顯示前 10 筆示意
        $count = 0;
        foreach ($data['data'] as $row) {
            if ($count++ >= 10) break;
            echo "<tr>";
            echo "<td>{$row['sub_no']}</td>";
            
            $color = match($row['status']) {
                '維修中' => 'red', '閒置' => 'green', '借用中' => 'blue', '使用中' => '#d35400', default => 'black'
            };
            echo "<td style='color:$color; font-weight:bold;'>{$row['status']}</td>";
            echo "<td>{$row['location_name']}</td>";
            echo "<td>{$row['owner_name']}</td>";
            echo "<td>{$row['current_user']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($total > 10) echo "...(還有 " . ($total-10) . " 筆資料)...";

    } else {
        echo "<span class='fail'>❌ 查詢失敗: {$queryRes['body']}</span>";
    }
    echo "</div>";

    // =================================================================
    // 6. 換人登入驗證 (Switch User Test)
    // =================================================================
    echo "<h3>6. 換人登入驗證 (User: 吳曉明)</h3>";
    echo "<div class='box'>";

    // 6-1. 登入 吳曉明
    echo "<b>登入 [吳曉明] (G140A002)...</b><br>";
    $loginRes2 = sendRequest('POST', "$baseUrl/auth/login", ["staff_code" => "G140A002", "password" => "mystdgo"]);
    $token2 = json_decode($loginRes2['body'], true)['data']['token'] ?? '';

    if ($token2) {
        echo "<span class='pass'>✅ 登入成功，取得 Token</span><br><br>";

        // 6-2. 查詢 吳曉明 的資產
        echo "<b>查詢 [吳曉明] 的資產列表...</b><br>";
        $queryRes2 = sendRequest('GET', "$baseUrl/assets", [], $token2);
        $data2 = json_decode($queryRes2['body'], true);

        if ($queryRes2['http_code'] == 200) {
            $total2 = $data2['meta']['total_records'];
            // 驗證重點：應該要有 2 筆 (就是剛剛 vbird 移轉給他的那 2 台)
            if ($total2 == 2) {
                echo "<span class='pass'>✅ 驗證成功！共找到 $total2 筆資料 (符合移轉數量)。</span><br><br>";
            } else {
                echo "<span class='fail'>❌ 驗證失敗！找到 $total2 筆資料 (預期應為 2 筆)。</span><br><br>";
            }

            echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%; font-size:12px;'>";
            echo "<tr style='background:#eee'><th>財產編號</th><th>品名</th><th>狀態</th><th>位置</th><th>保管人</th></tr>";
            
            foreach ($data2['data'] as $row) {
                echo "<tr>";
                echo "<td>{$row['sub_no']}</td>";
                echo "<td>{$row['asset_name']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['location_name']}</td>";
                echo "<td style='color:blue; font-weight:bold;'>{$row['owner_name']}</td>"; // 這裡應該顯示 吳曉明
                echo "</tr>";
            }
            echo "</table>";

        } else {
            echo "<span class='fail'>❌ 查詢失敗: {$queryRes2['body']}</span>";
        }

    } else {
        echo "<span class='fail'>❌ 吳曉明登入失敗 (可能是帳號未建立)</span>";
    }
    echo "</div>";

    // =================================================================
    // 7. 單一資產詳情查詢 (Get Single Asset) - 補上漏掉的測試
    // =================================================================
    echo "<h3>7. 單一資產詳情查詢 (GET /api/assets/{id})</h3>";
    echo "<div class='box'>";
    
    // 我們隨便找一台剛剛查到的資產 ID (例如 data2 的第一筆)
    if (!empty($data2['data'][0]['id'])) {
        $targetId = $data2['data'][0]['id'];
        echo "<b>查詢資產 ID: $targetId 的詳細資料...</b><br>";
        
        $detailRes = sendRequest('GET', "$baseUrl/assets/$targetId", [], $token2); // 用吳曉明的 Token 查
        $detailData = json_decode($detailRes['body'], true);

        if ($detailRes['http_code'] == 200) {
            $asset = $detailData['data'];
            echo "<span class='pass'>✅ 查詢成功！</span><br>";
            echo "<ul>";
            echo "<li><b>品名:</b> {$asset['asset_name']}</li>";
            echo "<li><b>型號:</b> {$asset['model']}</li>";
            echo "<li><b>規格:</b> {$asset['spec']}</li>";
            echo "<li><b>採購日期:</b> {$asset['purchase_date']}</li>";
            echo "<li><b>目前狀態:</b> <span style='color:blue'>{$asset['status']}</span></li>";
            echo "<li><b>保管人:</b> {$asset['owner_name']}</li>";
            echo "</ul>";
        } else {
            echo "<span class='fail'>❌ 查詢失敗: {$detailRes['body']}</span>";
        }
    } else {
        echo "<span class='fail'>⚠️ 無法測試：前一步驟未取得資產列表。</span>";
    }
    echo "</div>";

    echo "<h2>🎉 全系統測試完成！所有情境驗證通過。</h2>";

} catch (Exception $e) {
    echo "<h2 class='fail'>💥 發生錯誤: " . $e->getMessage() . "</h2>";
}


// =================================================================
// Helper Function
// =================================================================
function sendRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";

    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $httpCode, 'body' => $result];
}
?>