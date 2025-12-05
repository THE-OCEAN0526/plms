<?php

class Database {

	private $host = '127.0.0.1';
	private $db_name = 'plms_db';
	private $username = 'plms_user';
	private $password = 'plms_pass';
	public $conn;

	public function getConnection() {
		$this->conn = null;

		try {
			$dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
			$this->conn = new PDO($dsn, $this->username, $this->password);

			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		} catch(PDOExecption $exception) {
			echo "資料庫連線錯誤: " . $exception->getMessage();
		}

		return $this->conn;
	}
}
