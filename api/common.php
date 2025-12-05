<?php
// 1. 環境設定
ini_set('display_errors', 0); // 正式環境建議關閉，開發時可改 1
mb_internal_encoding("UTF-8");

// 2. CORS 設定 (允許 React 等前端跨域呼叫)
header("Access-Control-Allow-Origin: *"); // 允許所有來源存取
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 3. 處理 "OPTIONS" 預檢請求 (Preflight Request)
// 瀏覽器在發送真正的請求前，會先送一個 OPTIONS 請求來探路
// 如果這裡不直接回傳 200，瀏覽器會以為伺服器壞了
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>