<?php
// setup_demo.php - 自動升級資料庫結構並生成演示資料 (Owner/Borrower 分離版)
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🚀 PLMS 系統結構升級與資料生成</h1>";
echo "<style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: #2e7d32; font-weight: bold; }
        .error { color: #c62828; font-weight: bold; }
        .info { color: #1565c0; }
        .code-box { background: #f5f5f5; padding: 10px; border-left: 4px solid #1565c0; font-family: monospace; margin: 10px 0; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
      </style>";

include_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();


try {
    // ==========================================
    // Phase 2: 清空與重建資料 (Data Seeding)
    // ==========================================
    echo "<hr><h3>🌱 Phase 2: 重建演示資料...</h3>";

    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE asset_maintenance");
    $db->exec("TRUNCATE TABLE asset_transactions");
    $db->exec("TRUNCATE TABLE asset_items");
    $db->exec("TRUNCATE TABLE asset_batches");
    $db->exec("TRUNCATE TABLE users");
    $db->exec("TRUNCATE TABLE locations");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<span class='success'>✅ 資料表已清空。</span><br>";

    // // 1. 建立使用者
    // // -------------------------------------------------------------
    // $tokenVbird = bin2hex(random_bytes(32));
    // $tokenWang  = bin2hex(random_bytes(32));
    // $passDefault = password_hash("mystdgo", PASSWORD_DEFAULT);

    // // vbird (ID 1), 王小明 (ID 2)
    // $sqlUser = "INSERT INTO users (id, staff_code, name, password, api_token, created_at) VALUES 
    //             (1, 'T12345', 'vbird', '$passDefault', '$tokenVbird', NOW()),
    //             (2, 'G140A002', '王小明', '$passDefault', '$tokenWang', NOW())";
    // $db->exec($sqlUser);

    // echo "<div class='code-box'>
    //       <b>[vbird]</b> (ID: 1) Token: $tokenVbird<br>
    //       <b>[王小明]</b> (ID: 2) Token: $tokenWang
    //       </div>";

    // // 2. 建立地點
    // // -------------------------------------------------------------
    // $db->exec("INSERT INTO locations (id, code, name) VALUES (1, 'STORE', '總務處倉庫'), (2, 'I305', '多媒體教室 I305'), (3, 'LAB1', '電腦教室一')");
    // echo "✅ 地點建立完成。<br>";

    // // 3. 採購資產 (入庫)
    // // -------------------------------------------------------------
    // // Batch A: 筆電 (vbird 購買)
    // $db->exec("INSERT INTO asset_batches (id, batch_no, asset_name, category, qty_purchased, unit, unit_price, pre_property_no, suf_property_no, add_date) VALUES 
    //            (1, 'PO-2025-A', 'ASUS ExpertBook B9', '非消耗品', 10, '台', 45000, '3013208-63', '1001-1010', NOW())");

    // // 建立 10 台單品
    // // ★ 邏輯：入庫時，Owner = 購買人(vbird), Borrower = NULL, Status = 閒置
    // $stmtItem = $db->prepare("INSERT INTO asset_items (batch_id, sub_no, status, owner_id, borrower_id, location_id, updated_at) VALUES (1, :sub, '閒置', 1, NULL, 1, NOW())");
    // for ($i=1001; $i<=1010; $i++) $stmtItem->execute([':sub' => $i]);
    
    // echo "✅ [入庫] ASUS 筆電 10 台 (Owner: vbird, Borrower: NULL)<br>";

    // // Batch B: 投影機 (vbird 購買)
    // $db->exec("INSERT INTO asset_batches (id, batch_no, asset_name, category, qty_purchased, unit, unit_price, pre_property_no, suf_property_no, add_date) VALUES 
    //            (2, 'PO-2025-B', 'Epson 投影機', '非消耗品', 5, '台', 28000, '3013208-22', '2001-2005', NOW())");

    // $stmtItem = $db->prepare("INSERT INTO asset_items (batch_id, sub_no, status, owner_id, borrower_id, location_id, updated_at) VALUES (2, :sub, '閒置', 1, NULL, 1, NOW())");
    // for ($i=2001; $i<=2005; $i++) $stmtItem->execute([':sub' => $i]);

    // echo "✅ [入庫] Epson 投影機 5 台 (Owner: vbird, Borrower: NULL)<br>";


    // // 4. 情境模擬
    // // -------------------------------------------------------------
    // echo "<h3>🎬 模擬異動情境...</h3>";

    // // --- Scenario A: 正常借用 (王小明 借 筆電 #1, #2) ---
    // // 邏輯：Owner 不變 (vbird), Borrower 變成 (王小明)
    // $returnDate = date('Y-m-d', strtotime('+30 days'));
    
    // // 更新 Item
    // $db->exec("UPDATE asset_items SET status='使用中', borrower_id=2, location_id=2 WHERE id IN (1, 2)");

    // // 寫入 Log (prev_owner=1, new_owner=1, borrower=2)
    // $sqlTransA = "INSERT INTO asset_transactions 
    //               (item_id, action_type, prev_owner_id, new_owner_id, borrower_id, prev_location_id, new_location_id, prev_status, new_status, action_date, expected_return_date, note) 
    //               VALUES 
    //               (1, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', NOW(), '$returnDate', '教學用'),
    //               (2, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', NOW(), '$returnDate', '教學用')";
    // $db->exec($sqlTransA);
    // echo "🔵 [借用] 筆電 #1, #2 借給 王小明 (產權: vbird 沒變, 借用人: 王小明)<br>";


    // // --- Scenario B: 逾期未還 (王小明 借 投影機 #11) ---
    // // 設定預計歸還日為昨天
    // $borrowDateOld = date('Y-m-d H:i:s', strtotime('-7 days'));
    // $returnDateOver = date('Y-m-d', strtotime('-1 days'));

    // $db->exec("UPDATE asset_items SET status='使用中', borrower_id=2, location_id=2 WHERE id = 11");

    // $db->exec("INSERT INTO asset_transactions 
    //            (item_id, action_type, prev_owner_id, new_owner_id, borrower_id, prev_location_id, new_location_id, prev_status, new_status, action_date, expected_return_date, note) 
    //            VALUES 
    //            (11, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', '$borrowDateOld', '$returnDateOver', '專題演講')");
    // echo "<span class='error'>🔴 [逾期] 投影機 #11 借給 王小明 (已逾期 1 天)</span><br>";


    // // --- Scenario C: 維修中 (筆電 #3) ---
    // // 邏輯：Owner 不變, Borrower 清空 (因為送修了，不在任何人手上), Status='維護'
    // // 動作類型：我們用 '維修' (因為前面已經 ALTER TABLE 加上去了)
    // $maintainDate = date('Y-m-d', strtotime('-45 days'));
    
    // $db->exec("UPDATE asset_items SET status='維護', borrower_id=NULL, location_id=NULL WHERE id = 3");
    
    // // 建立維修單 (Maintenance Table)
    // $db->exec("INSERT INTO asset_maintenance (item_id, applicant_id, maintain_date, type, description, vendor, created_at) VALUES 
    //            (3, 1, '$maintainDate', '維修', '無法開機', 'ASUS 原廠', '$maintainDate 10:00:00')");

    // // 建立交易 Log
    // // prev_owner=1, new_owner=1, borrower=NULL
    // $db->exec("INSERT INTO asset_transactions 
    //            (item_id, action_type, prev_owner_id, new_owner_id, borrower_id, prev_location_id, new_location_id, prev_status, new_status, action_date, note) 
    //            VALUES 
    //            (3, '維修', 1, 1, NULL, 1, NULL, '閒置', '維護', '$maintainDate 10:00:00', '送修')");
    
    // echo "<span class='info'>🟠 [維修] 筆電 #3 送修中 (超過 30 天，應觸發黃色警告)</span><br>";


    // // --- Scenario D: 報廢 (投影機 #12) ---
    // // 邏輯：Owner 不變 (還是 vbird 的財產，只是爛掉了), Borrower=NULL, Status='報廢'
    // $db->exec("UPDATE asset_items SET status='報廢', borrower_id=NULL, location_id=1 WHERE id = 12");

    // $db->exec("INSERT INTO asset_transactions 
    //            (item_id, action_type, prev_owner_id, new_owner_id, borrower_id, prev_location_id, new_location_id, prev_status, new_status, action_date, note) 
    //            VALUES 
    //            (12, '報廢', 1, 1, NULL, 1, 1, '閒置', '報廢', NOW(), '鏡頭破裂')");

    // echo "⚫ [報廢] 投影機 #12 已報廢<br>";

    // echo "<hr><h2>🎉 系統重置成功！所有資料與結構已符合新邏輯。</h2>";

} catch (PDOException $e) {
    echo "<h2 class='error'>❌ 執行失敗</h2>";
    echo "SQL 錯誤: " . $e->getMessage();
    // 顯示詳細錯誤以便除錯
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>