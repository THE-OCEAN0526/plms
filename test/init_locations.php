<?php
// test/init_locations.php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🏢 初始化地點資料</h1>";
echo "<hr>";

include_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // 檢查是否已經有地點，避免重複插入
    $check = $db->query("SELECT COUNT(*) FROM locations WHERE id = 1")->fetchColumn();

    if ($check == 0) {
        // 插入地點資料
        $sql = "INSERT INTO locations (id, code, name) VALUES 
                (1, 'STORE', '總務處倉庫'), 
                (2, 'I305', '多媒體教室 I305'), 
                (3, 'LAB1', '電腦教室一')";
        
        $db->exec($sql);
        echo "<h2 style='color:green'>✅ 地點資料建立成功！</h2>";
        echo "已建立：總務處倉庫 (ID: 1), 多媒體教室 (ID: 2), 電腦教室 (ID: 3)<br>";
    } else {
        echo "<h2 style='color:orange'>⚠️ 地點資料已存在，跳過建立。</h2>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ 錯誤</h2>";
    echo "SQL 錯誤: " . $e->getMessage();
}
?>