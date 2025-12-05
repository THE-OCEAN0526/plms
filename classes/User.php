<?php

class User {
	private $conn;
	private $table_name = "users";
	
	public $id;
	public $staff_code;
	public $name;
	public $password;
	public $role = "user";
	public $api_token;

	public function __construct($db) {
		$this->conn = $db;
	}

	// 註冊新使用者
	public function create() {
		$query = "INSERT INTO " . $this->table_name . "
				SET
					staff_code = :staff_code,
					name = :name,
					password = :password,
					role = :role,
					created_at = NOW()";

		$stmt = $this->conn->prepare($query);

		// 清理輸入資料 (防止 XSS 攻擊)
		$this->staff_code = htmlspecialchars(strip_tags($this->staff_code));
		$this->name = htmlspecialchars(strip_tags($this->name));

		// 哈希密碼
		$password_hash = password_hash($this->password, PASSWORD_DEFAULT);

		$stmt->bindParam(':staff_code', $this->staff_code);
		$stmt->bindParam(':name', $this->name);
		$stmt->bindParam(':password', $password_hash);
		$stmt->bindParam(':role', $this->role);

		if ($stmt->execute()) {
			return true;
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
		$query = "SELECT id, name, password, role FROM " . $this->table_name . " WHERE staff_code = :staff_code LIMIT 1";

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
				$this->role = $row['role'];

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

}

?>
