<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

// ★ 填入您之前登入取得的 Token
$token = "d753c85b2fff1dfbe2508be12b89229bedf32b7741940e57ba609323f5e07808"; 

echo "<h1>📊 Dashboard API 測試</h1>";

$url = 'http://127.0.0.1/api/dashboard/summary.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result, true);

echo "<pre>";
print_r($data);
echo "</pre>";
?>