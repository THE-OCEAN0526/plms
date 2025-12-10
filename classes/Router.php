<?php
class Router {
    private $routes = [];
    private $db;

    public function  __construct($db) {
        $this->db = $db;
    }

    // 註冊路由的方法
    public function add($method, $path, $handler) {
        // 將路徑中的參數變數 (如 {id}) 轉換為正則表達式 
        // [^/]+ 代表「除了斜線以外的任何字元」，這樣可以支援 UUID 或包含 - 的 ID
        $pathRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
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
        
        // 如果 API 放在 /api/ 底下，需要移除前綴以便比對 (視伺服器設定而定)
        // 這裡假設傳進來的 URI 已經包含 /api/，直接比對完整路徑
        
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($httpMethod) && preg_match($route['path'], $uri, $matches)) {
                
                // 過濾掉正則匹配中的數字索引，只保留具名參數
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // 呼叫處理器 (Controller, Method)
                if (is_string($route['handler'])) {
                    //  支援 "Controller@method" 字串寫法
                    [$controllerName, $methodName] = explode('@', $route['handler']);
                } else {
                    // 
                    [$controllerName, $methodName] = $route['handler'];
                }

                if (class_exists($controllerName)) {
                   $controller = new $controllerName($this->db);

                   if (method_exists($controller, $methodName)) {
                    // 執行控制器方法，並帶入參數       
                        call_user_func([$controller, $methodName], $params);
                    } else {
                        $this->sendError(500, "Method $methodName not found in $controllerName");
                    }

                }else {
                    $this->sendError(500, "Controller $controllerName not found");
                }
                return;   
            }
        }

        $this->sendError(404, "Endpoint does not exist");
    }

    // 統一錯誤回傳格式
    private function sendError($code, $message) {
        http_response_code($code);
        echo json_encode(["error" => true, "message" => $message]);
    }
}
?>