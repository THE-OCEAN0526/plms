<?php

class User {
	private $conn;
	private $table_name = "users";
	
	public $id;
	public $staff_code;
	public $name;
	public $password;
	public $api_token;
	public $theme;

	public function __construct($db) {
		$this->conn = $db;
	}

	// 註冊新使用者
	public function register($data) {
    // 1. 檢查帳號是否已存在
    $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE staff_code = :staff_code LIMIT 1";
    $stmtCheck = $this->conn->prepare($checkQuery);
    $stmtCheck->bindParam(':staff_code', $data['staff_code']);
    $stmtCheck->execute();
    
    if ($stmtCheck->rowCount() > 0) {
        throw new Exception("此帳號已被註冊");
    }

    // 2. 準備寫入資料
    $query = "INSERT INTO " . $this->table_name . " 
              (staff_code, name, password, theme) 
              VALUES (:staff_code, :name, :password, 'light')";
    
    $stmt = $this->conn->prepare($query);

    // 密碼加密
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt->bindParam(':staff_code', $data['staff_code']);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':password', $hashedPassword);

    if ($stmt->execute()) {
        return $this->conn->lastInsertId();
    }
    return false;
}

	// 檢查帳號是否存在
	public function staffCodeExists() {
		$query = "SELECT id FROM " . $this->table_name . " WHERE staff_code = :staff_code LIMIT 1";
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(':staff_code', $this->staff_code);
		$stmt->execute();

		if ($stmt->rowCount() > 0) {
			return true;
		}
		return false;
	}

	public function login() {
		// 撈取使用者資料
		$query = "SELECT id, name, password, theme FROM " . $this->table_name . " WHERE staff_code = :staff_code LIMIT 1";

		$stmt = $this->conn->prepare($query);

		$this->staff_code = htmlspecialchars(strip_tags($this->staff_code));
		$stmt->bindParam(':staff_code', $this->staff_code);

		$stmt->execute();

		// 如果找到使用者
		if($stmt->rowCount() > 0) {

			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			// 驗證密碼
			if(password_verify($this->password, $row['password'])) {
				$this->id = $row['id'];
				$this->name = $row['name'];
				$this->theme = $row['theme'];

				// 生成新的 API Token
				$this->api_token = bin2hex(random_bytes(32));

				// 更新使用者的 API Token
				$updateQuery = "UPDATE " . $this->table_name . " SET api_token = :token WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                
                $updateStmt->bindParam(':token', $this->api_token);
                $updateStmt->bindParam(':id', $this->id);

				if($updateStmt->execute()) {
                    return true;
                }
			}
		}	
		return false;
	}

	public function updateTheme($newTheme) {
		$query = "UPDATE " . $this->table_name . " SET theme = :theme WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $newTheme = htmlspecialchars(strip_tags($newTheme));
        $stmt->bindParam(':theme', $newTheme);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
	}

	public function getAll($excludeId = null) {
		// 預設排除傳入的 ID (當前使用者)
		$query = "SELECT id, staff_code, name FROM " . $this->table_name;
		if ($excludeId) {
			$query .= " WHERE id != :excludeId";
		}
		$query .= " ORDER BY name ASC";
		
		$stmt = $this->conn->prepare($query);
		if ($excludeId) {
			$stmt->bindParam(':excludeId', $excludeId);
		}
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

}

?>