<?php
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>📋 資產清單 API 測試</h1>";

function testList($testName, $params) {
    // 組合 GET 網址
    $url = 'http://127.0.0.1/api/asset/batch_list.php?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $res = json_decode($result, true);
    
    echo "<h3>$testName</h3>";
    echo "URL: <a href='$url' target='_blank'>$url</a><br>";
    
    if ($httpCode == 200) {
        $meta = $res['meta'];
        echo "<b>搜尋結果：</b> 共 {$meta['total_records']} 筆，目前第 {$meta['current_page']} / {$meta['total_pages']} 頁<br>";
        
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse; margin-top:5px;'>";
        echo "<tr style='background:#eee'><th>ID</th><th>品名</th><th>編號範圍</th><th>總價</th></tr>";
        
        foreach ($res['data'] as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['asset_name']} ({$item['batch_no']})</td>";
            echo "<td>{$item['property_range']}</td>";
            echo "<td>{$item['model']}</td>";
            echo "<td>{$item['total_price']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<b style='color:red'>失敗 ($httpCode)</b>";
    }
    echo "<hr>";
}

// 1. 測試：列出全部 (預設第1頁)
testList("Case 1: 列出全部資料 (無搜尋)", []);

// 2. 測試：搜尋關鍵字 "電腦" 或 "Server" (看您資料庫有什麼)
// 請根據您剛剛入庫的資料來改這裡的關鍵字
testList("Case 2: 搜尋關鍵字 '3013208-63'", ["keyword" => "3013208-63"]);

// 3. 測試：分頁 (每頁 1 筆，看第 2 頁)
testList("Case 3: 強制分頁 (每頁1筆，查第2頁)", ["limit" => 1, "page" => 1]);

?>