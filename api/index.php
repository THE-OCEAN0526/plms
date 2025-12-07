<?php
// 1. 環境設定
// === 強力除錯模式 ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mb_internal_encoding("UTF-8");

// 2. CORS 設定 (全域處理)
header("Access-Control-Allow-Origin: *");
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

// 3. 引入核心檔案
include_once '../config/Database.php';
include_once '../classes/Router.php';

// 引入控制器
include_once '../controllers/AuthController.php';
include_once '../controllers/AssetController.php';
include_once '../controllers/DashboardController.php';

// 4. 初始化資料庫
$database = new Database();
$db = $database->getConnection();

// 5. 設定路由
$router = new Router();

// --- Auth Routes ---
$router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
$router->add('POST', '/api/auth/register', [AuthController::class, 'register']);

// --- Asset Routes (RESTful 風格) ---
// 取得列表 (GET /api/assets)
$router->add('GET', '/api/assets', [AssetController::class, 'index']);
// 新增資產 (POST /api/assets)
$router->add('POST', '/api/assets', [AssetController::class, 'store']);
// 未來可擴充: 取得單一資產 (GET /api/assets/{id})
// $router->add('GET', '/api/assets/{id}', [AssetController::class, 'show']);

// --- Dashboard Routes ---
$router->add('GET', '/api/dashboard/summary', [DashboardController::class, 'summary']);


// 6. 執行分派
// 取得目前的 URI
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($method, $uri);
?>