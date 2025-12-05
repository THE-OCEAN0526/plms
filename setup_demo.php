<?php
// setup_demo.php - 建立全方位演示資料 (含逾期、報廢、維修情境)
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🚀 PLMS 系統重置與全功能演示資料生成</h1>";

include_once 'config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    echo "<h3>1. 清空並重置資料庫...</h3>";
    
    // 1. 清空所有資料表
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE asset_maintenance");
    $db->exec("TRUNCATE TABLE asset_transactions");
    $db->exec("TRUNCATE TABLE asset_items");
    $db->exec("TRUNCATE TABLE asset_batches");
    $db->exec("TRUNCATE TABLE users");
    $db->exec("TRUNCATE TABLE locations");
    
   
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // echo "✅ 資料庫結構同步完成。<br><hr>";

//     // 2. 建立基礎資料
//     echo "<h3>2. 建立使用者與地點...</h3>";
    
//     $passAdmin = password_hash("admin123", PASSWORD_DEFAULT);
//     $passUser  = password_hash("MySafePassword", PASSWORD_DEFAULT);

//     $sqlUser = "INSERT INTO users (id, staff_code, name, role, password, created_at) VALUES 
//                 (1, 'admin', '系統管理員', 'admin', '$passAdmin', NOW()),
//                 (2, 'teacher01', '王小明', 'user', '$passUser', NOW())";
//     $db->exec($sqlUser);

//     $sqlLoc = "INSERT INTO locations (id, code, name) VALUES 
//                (1, 'STORE', '總務處倉庫'),
//                (2, 'I305', '多媒體教室 I305'),
//                (3, 'LAB1', '電腦教室一')";
//     $db->exec($sqlLoc);
    
//     echo "✅ 基礎資料建立完成。<br>";

//     // 3. 採購資產 (Batch A & B)
//     echo "<h3>3. 模擬採購與入庫...</h3>";
    
//     // A: ASUS 筆電 10 台
//     $sqlBatchA = "INSERT INTO asset_batches (id, batch_no, asset_name, category, qty_purchased, unit, unit_price, pre_property_no, suf_property_no, add_date) VALUES 
//                   (1, 'PO-20250101-A', 'ASUS ExpertBook B9', '非消耗品', 10, '台', 45000, '3013208-63', '1001-1010', NOW())";
//     $db->exec($sqlBatchA);

//     $stmtItem = $db->prepare("INSERT INTO asset_items (batch_id, sub_no, status, custodian_id, location_id, updated_at) VALUES (1, :sub, '閒置', 1, 1, NOW())");
//     for ($i=1001; $i<=1010; $i++) {
//         $stmtItem->execute([':sub' => $i]);
//     }
//     echo "✅ [入庫] ASUS 筆電 10 台 (預設：閒置/倉庫)<br>";

//     // B: Epson 投影機 5 台
//     $sqlBatchB = "INSERT INTO asset_batches (id, batch_no, asset_name, category, qty_purchased, unit, unit_price, pre_property_no, suf_property_no, add_date) VALUES 
//                   (2, 'PO-20250101-B', 'Epson 高亮度投影機', '非消耗品', 5, '台', 28000, '3013208-22', '2001-2005', NOW())";
//     $db->exec($sqlBatchB);

//     $stmtItem = $db->prepare("INSERT INTO asset_items (batch_id, sub_no, status, custodian_id, location_id, updated_at) VALUES (2, :sub, '閒置', 1, 1, NOW())");
//     for ($i=2001; $i<=2005; $i++) {
//         $stmtItem->execute([':sub' => $i]);
//     }
//     echo "✅ [入庫] Epson 投影機 5 台 (預設：閒置/倉庫)<br><hr>";

//     // 4. 製造豐富的情境
//     echo "<h3>4. 模擬資產異動情境...</h3>";

//     // --- 情境 A: 正常借用 (3台筆電) ---
//     // 物品 1, 2, 3 -> 借給 王小明 (teacher01)
//     $db->exec("UPDATE asset_items SET status='使用中', custodian_id=2, location_id=2 WHERE id IN (1, 2, 3)");
    
//     $returnDateOK = date('Y-m-d', strtotime('+30 days')); // 還很久才到期
//     $sqlTrans = "INSERT INTO asset_transactions (item_id, action_type, actor_id, prev_custodian_id, new_custodian_id, prev_location_id, new_location_id, prev_status, new_status, action_date, expected_return_date, note) VALUES 
//                  (1, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', NOW(), '$returnDateOK', '教學使用'),
//                  (2, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', NOW(), '$returnDateOK', '教學使用'),
//                  (3, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', NOW(), '$returnDateOK', '教學使用')";
//     $db->exec($sqlTrans);
//     echo "🔵 [借用] 3 台筆電借給王小明 (正常使用中)<br>";

//     // --- 情境 B: 逾期借用 (1台投影機) ---
//     // 物品 11 -> 借給 王小明，但應該昨天就要還
//     $db->exec("UPDATE asset_items SET status='使用中', custodian_id=2, location_id=2 WHERE id = 11");
    
//     $returnDateOver = date('Y-m-d', strtotime('-1 days')); // 昨天到期
//     $db->exec("INSERT INTO asset_transactions (item_id, action_type, actor_id, prev_custodian_id, new_custodian_id, prev_location_id, new_location_id, prev_status, new_status, action_date, expected_return_date, note) VALUES 
//                (11, '借用', 1, 1, 2, 1, 2, '閒置', '使用中', DATE_SUB(NOW(), INTERVAL 7 DAY), '$returnDateOver', '專題演講使用')");
//     echo "🔴 [逾期] 1 台投影機借給王小明 (已逾期 1 天)<br>";

//     // --- 情境 C: 維修中且逾期 (1台筆電) ---
//     // 物品 3 (原本借給王小明的) -> 壞了送修 -> 修太久了
//     $repairDate = date('Y-m-d', strtotime('-45 days')); // 45天前送修
    
//     $db->exec("UPDATE asset_items SET status='維護' WHERE id = 3");
    
//     $db->exec("INSERT INTO asset_maintenance (item_id, applicant_id, maintain_date, type, description, vendor, created_at) VALUES 
//                (3, 2, '$repairDate', '維修', '硬碟讀取失敗', '原廠維修中心', '$repairDate 10:00:00')");
               
//     // 雖然 action_type 移除了 '維修'，但為了紀錄狀態變更，我們可以用 '移轉' 或是不寫入 transaction，
//     // 但為了讓 Dashboard 的「近期動態」有東西顯示，我們用 '移轉' 代表送修動作。
//     $db->exec("INSERT INTO asset_transactions (item_id, action_type, actor_id, prev_custodian_id, new_custodian_id, prev_location_id, new_location_id, prev_status, new_status, action_date, note) VALUES 
//                (3, '移轉', 2, 2, 2, 2, 2, '使用中', '維護', '$repairDate 10:00:00', '設備故障送修')");

//     echo "🟠 [維修] 1 台筆電送修中 (送修超過 45 天)<br>";

//     // --- 情境 D: 資產報廢 (1台投影機) ---
//     // 物品 12 -> 壞太嚴重，直接報廢
//     $db->exec("UPDATE asset_items SET status='報廢', location_id=1 WHERE id = 12");
    
//     $db->exec("INSERT INTO asset_transactions (item_id, action_type, actor_id, prev_custodian_id, new_custodian_id, prev_location_id, new_location_id, prev_status, new_status, action_date, note) VALUES 
//                (12, '報廢', 1, 1, 1, 1, 1, '閒置', '報廢', NOW(), '鏡頭破損無法修復')");
               
//     echo "⚫ [報廢] 1 台投影機已報廢<br><hr>";

//     echo "<h2 style='color:green'>🎉 演示資料建置完成！</h2>";
//     echo "<h3>請使用 teacher01 / MySafePassword 登入查看效果：</h3>";
//     echo "<ul>";
//     echo "<li><b>我的保管總數</b>：應為 4 (3台筆電 + 1台投影機)</li>";
//     echo "<li><b>使用中</b>：應為 2 (因為有一台拿去修了，一台逾期但仍算使用中) -> 修正：Dashboard 邏輯是 status='使用中'，所以是 3 台 (2筆電+1投影機)</li>";
//     echo "<li><b>維修中</b>：應為 1</li>";
//     echo "<li><b>已報廢</b>：應為 0 (因為報廢品通常會繳回倉庫，變成 Admin 的保管物)</li>";
//     echo "<li><b>鈴鐺通知</b>：應有 2 則 (1個紅色逾期歸還，1個黃色維修逾期)</li>";
//     echo "</ul>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ 建置失敗</h2>";
    echo "SQL 錯誤: " . $e->getMessage();
}
?>