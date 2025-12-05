<?php

class AuthMiddleware {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function authenticate() {
        $headers = $this->getAuthorizationHeader();

        // 1. 檢查有沒有帶 Token
        if(empty($headers)) {
            $this->denyAccess("未提供驗證 (Token missing)");
        }

        // 2. 解析 Token (通常格式是 "Bearer <token>")
        if(preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            $token = $matches[1];
        } else {
            // 如果前端沒加 Bearer，直接嘗試讀取整個 Header 當作 Token
            $token = $headers;
        }

        // 3. 去資料庫檢查 Token 是否有效
        $query = "SELECT id, name, role FROM " . $this->table_name . " WHERE api_token = :token LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }else {
            $this->denyAccess("無效的 Token (Invalid token)");
        }
    }

    private function getAuthorizationHeader() {
        $headers = null;
        if(isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // // Apache/Nginx 有時會在這裡
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            // 如果有 Authorization Header，就取出來
            if(isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    private function denyAccess($message) {
        http_response_code(401); // 401 Unauthorized
        echo json_encode(array(
            "message" => $message,
            "error" => "Unauthorized"
        ));
        exit(); // 直接殺死程式，不讓後面的代碼執行
    }

}