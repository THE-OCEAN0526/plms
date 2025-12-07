<?php
class Router {
    private $routes = [];

    // 註冊路由的方法
    public function add($method, $path, $handler) {
        // 將路徑中的參數變數 (如 {id}) 轉換為正則表達式
        $pathRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[a-zA-Z0-9_]+)', $path);
        // 加入起始與結束符號，確保完全匹配
        $pathRegex = "#^" . $pathRegex . "$#";
        
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $pathRegex,
            'handler' => $handler
        ];
    }

    // 分派請求
    public function dispatch($httpMethod, $uri) {
        // 移除 Query String (例如 ?page=1)
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // 如果你的 API 放在 /api/ 底下，需要移除前綴以便比對 (視伺服器設定而定)
        // 這裡假設傳進來的 URI 已經包含 /api/，我們直接比對完整路徑
        
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($httpMethod) && preg_match($route['path'], $uri, $matches)) {
                
                // 過濾掉正則匹配中的數字索引，只保留具名參數
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                // 呼叫處理器 (Controller, Method)
                [$controllerName, $methodName] = $route['handler'];
                
                // 這裡需要依賴注入資料庫連線，稍後在 index.php 實作
                global $db; 
                $controller = new $controllerName($db);
                
                // 執行控制器方法，並帶入參數
                call_user_func([$controller, $methodName], $params);
                return;
            }
        }

        // 找不到路由
        http_response_code(404);
        echo json_encode(["message" => "404 Not Found - Endpoint does not exist"]);
    }
}
?>