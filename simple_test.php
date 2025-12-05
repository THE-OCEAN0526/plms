<?php
// 最簡單的測試，只發一次請求
echo "Testing Batch List API...<br>";

$ch = curl_init('http://127.0.0.1/api/asset/batch_list.php?page=1&limit=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo "Raw Result:<br>";
echo "<pre>" . htmlspecialchars($result) . "</pre>";
?>
