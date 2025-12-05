<?php
// /home/plms/web/db_test.php

// 1. 設定資料庫連線資訊
// 因為 Web 和 DB 在同一個 Pod，所以 Host 用 127.0.0.1
$host = '127.0.0.1';
$db   = 'plms_db';
$user = 'plms_user';
$pass = 'plms_pass'; // ★如果您有改密碼，請這裡也要改
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

echo "<h1>PLMS 資料庫連線測試</h1>";

try {
    // 2. 建立連線 (如果這裡失敗，會直接跳到 catch)
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p style='color: green; font-weight: bold;'>✅ 連線成功！(PDO Connection OK)</p>";

    // 3. 執行查詢：撈出所有使用者
    $stmt = $pdo->query("SELECT id, staff_code, name, role, created_at FROM users");
    
    echo "<h3>使用者列表 (Users Table)：</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>帳號</th><th>姓名</th><th>權限</th><th>建立時間</th></tr>";

    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['staff_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (\PDOException $e) {
    // 4. 如果失敗，顯示錯誤訊息
    echo "<p style='color: red; font-weight: bold;'>❌ 連線失敗！</p>";
    echo "<p>錯誤訊息：" . $e->getMessage() . "</p>";
    echo "<p>請檢查：<br>1. 帳號密碼對嗎？<br>2. 資料庫名稱是 plms_db 嗎？<br>3. 容器有在運行嗎？</p>";
}
?>
