<?php
class Location {
    private $conn;
    private $table = "locations";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 取得所有地點列表
    public function getAll() {
        // 通常依照 id 或代碼排序，方便前端顯示
        $query = "SELECT id, code, name FROM " . $this->table . " ORDER BY id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>