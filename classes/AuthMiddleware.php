<?php

class AuthMiddleware {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function authenticate() {
        $headers = $this->getAuthorizationHeader();
        $token = null;

        // 1. 優先從 Header 檢查 Token
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $token = $matches[1];
            } else {
                $token = $headers;
            }
        } 
        // 2. 如果 Header 是空的，從 GET 參數檢查 (針對 Excel 匯出的 window.location.href)
        else if (isset($_GET['api_token'])) {
            $token = $_GET['api_token'];
        }

        // 檢查最終有沒有取得 Token
        if(empty($token)) {
            $this->denyAccess("未提供驗證 (Token missing)");
        }


        // 3. 去資料庫檢查 Token 是否有效
        $query = "SELECT id, name, staff_code FROM " . $this->table_name . " WHERE api_token = :token LIMIT 1";
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