<?php
// /home/plms/web/index.php

$title = "Podman + Xdebug 測試";
$db_host = "127.0.0.1"; // 因為在同一個 Pod，所以直接用 localhost
$db_user = "root";
$db_pass = "my_secure_pass";
$db_name = "plms_db";

// --- [測試 1: Xdebug] ---
// 請在下一行 (echo) 設定一個紅色斷點 (Breakpoint)
echo "1. 開始測試 Xdebug...<br>"; 

$status = "Xdebug 運作中！"; // 您可以在變數面板檢查這個變數

// --- [測試 2: 資料庫連線] ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "2. 資料庫連線狀態：<span style='color:green'>成功 (Connected to MariaDB)</span><br>";
} catch (PDOException $e) {
    echo "2. 資料庫連線狀態：<span style='color:red'>失敗 (" . $e->getMessage() . ")</span><br>";
}

echo "<hr>";
phpinfo();
?>