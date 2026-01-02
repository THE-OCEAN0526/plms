<?php
// 環境設定
// === 強力除錯模式 ===
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mb_internal_encoding("UTF-8");

// CORS 設定 (全域處理)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 全域例外處理
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
    ]);
    exit;
});

// 全域錯誤處理 把普通的 Warning/Notice 轉換成例外
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    // 拋出例外，讓下面的 Exception Handler 接手
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 處理 "OPTIONS" 預檢請求 (Preflight Request)
// 瀏覽器在發送真正的請求前，會先送一個 OPTIONS 請求來探路
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


// 自動載入
spl_autoload_register(function ($className) {
    $paths = [
        '../config/',
        '../classes/',
        '../controllers/'
    ];

    foreach ($paths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            include_once $file;
            return;

        }
    }
});

// 引入 composer 的 autoload
require_once __DIR__ . '/../vendor/autoload.php';


// 初始化資料庫
$database = new Database();
$db = $database->getConnection();

// 設定路由
$router = new Router($db);


// --- Auth Routes (登入/註冊) ---
$router->add('POST', '/api/tokens', 'AuthController@login');
$router->add('POST', '/api/users', 'AuthController@register');
// 更新個人資料
$router->add('PUT', '/api/users/profile', 'AuthController@updateProfile');
// 更新使用者主題偏好
$router->add('PUT', '/api/users/theme', 'AuthController@updateTheme');
// 取得使用者
$router->add('GET', '/api/users', 'AuthController@index');

// --- Dashboard Routes (主控台) ---
$router->add('GET', '/api/dashboard', 'DashboardController@summary');

// --- Asset Routes (顯示資產) ---
// 取得列表
$router->add('GET', '/api/assets', 'AssetController@index');
// 取得單一資產
$router->add('GET', '/api/assets/{id}', 'AssetController@show');
// 取得資產履歷
$router->add('GET', '/api/assets/{id}/history', 'AssetController@history');
// 新增資產
$router->add('POST', '/api/assets', 'AssetController@store');
// 載入地點
$router->add('GET', '/api/locations', 'LocationController@index');
// 修改批次資料
$router->add('GET', '/api/batches', 'AssetController@getBatches');
$router->add('PUT', '/api/batches/{id}', 'AssetController@updateBatch');


// --- Transaction Routes (異動) ---
$router->add('POST', '/api/transactions', 'TransactionController@store');


// --- Maintenance Routes (維修) ---
// 送修
$router->add('POST', '/api/maintenances', 'MaintenanceController@create');
// 結案/修改 
$router->add('PUT', '/api/maintenances/{id}', 'MaintenanceController@update');
// 刪除/取消 
$router->add('DELETE', '/api/maintenances/{id}', 'MaintenanceController@delete');
// 取得列表 (搜尋、分頁)
$router->add('GET', '/api/maintenances', 'MaintenanceController@index');
// 取得單筆資料 (點擊編輯時用)
$router->add('GET', '/api/maintenances/{id}', 'MaintenanceController@show');

// ---- Reports Routes (報表) ---
$router->add('GET', '/api/reports/metadata', 'ReportController@getMetadata');
$router->add('POST', '/api/reports', 'ReportController@preview');
$router->add('GET', '/api/reports', 'ReportController@exportAssets');


// 執行分派
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
?>