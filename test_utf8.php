<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

include_once 'config/Database.php';
include_once 'classes/User.php';

echo "<h1>UTF-8 寫入測試</h1>";

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// 我們直接在程式碼裡面寫死中文，繞過 PowerShell
$user->staff_code = "utf8_test";
$user->name = "測試員"; // 硬編碼中文
$user->password = "pass";

if($user->staffCodeExists()) {
    echo "帳號 utf8_test 已存在，請先刪除資料庫中的這筆資料再測。<br>";
} else {
    if($user->create()) {
        echo "✅ 寫入成功！<br>";
    } else {
        echo "❌ 寫入失敗。<br>";
    }
}

// 馬上讀出來看看
$stmt = $db->prepare("SELECT name, HEX(name) as hex_code FROM users WHERE staff_code = 'utf8_test'");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>讀取結果：</h3>";
echo "姓名: " . $row['name'] . "<br>";
echo "HEX碼: " . $row['hex_code'] . "<br>";

if ($row['hex_code'] == 'E6B8ACE8A9A6E593A1') {
    echo "<h2 style='color:green'>恭喜！資料庫運作完全正常！(HEX碼正確)</h2>";
    echo "<p>如果是這樣，那就是 Windows PowerShell 傳送中文時編碼跑掉了，系統本身沒問題。</p>";
} else {
    echo "<h2 style='color:red'>糟糕，資料庫存進去還是壞的。</h2>";
}
?>
