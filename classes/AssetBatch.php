<?php
class AssetBatch {
    private $conn;
    private $table_batch = "asset_batches";
    private $table_item = "asset_items";

    // 屬性
    public $id;
    public $pre_property_no;
    public $suf_start_no;   
    public $suf_end_no;     
    public $category;       
    public $asset_name;    
    public $brand;        
    public $model;          
    public $spec;          
    public $unit;          
    public $unit_price;    
    public $location;       
    public $purchase_date;
    public $add_date;
    public $life_years;    
    public $batch_no;
    public $accounting_items; 
    public $fund_source;    

   
    public function __construct($db) {
        $this->conn = $db;
    }

    // 統一日期格式處理 (補全時分秒)
    private function formatDateTime($date) {
        if (empty($date)) return null;
        return (strlen($date) === 10) ? $date . " " . date("H:i:s") : $date;
    }

    private function getCommonSetSql() {
        return "batch_no = :batch_no,
                pre_property_no = :pre_property_no,
                suf_start_no = :suf_start_no,
                suf_end_no = :suf_end_no,
                category = :category,
                asset_name = :asset_name,
                brand = :brand,
                model = :model,
                spec = :spec,
                unit = :unit,
                unit_price = :unit_price,
                location = :location,
                fund_source = :fund_source,
                purchase_date = :purchase_date,
                add_date = :add_date,
                life_years = :life_years,
                accounting_items = :accounting_items";
    }

    private function bindAllValues($stmt) {
        $stmt->bindValue(":batch_no", $this->batch_no);
        $stmt->bindValue(":pre_property_no", $this->pre_property_no);
        $stmt->bindValue(":suf_start_no", (int)$this->suf_start_no, PDO::PARAM_INT);
        $stmt->bindValue(":suf_end_no", (int)$this->suf_end_no, PDO::PARAM_INT);
        $stmt->bindValue(":category", $this->category);
        $stmt->bindValue(":asset_name", $this->asset_name);
        $stmt->bindValue(":brand", $this->brand ?? '');
        $stmt->bindValue(":model", $this->model ?? '');
        $stmt->bindValue(":spec", $this->spec ?? '');
        $stmt->bindValue(":unit", $this->unit);
        $stmt->bindValue(":unit_price", $this->unit_price);
        $stmt->bindValue(":location", $this->location ?: null, PDO::PARAM_INT);
        $stmt->bindValue(":fund_source", $this->fund_source ?? null);
        $stmt->bindValue(":purchase_date", !empty($this->purchase_date) ? $this->purchase_date : null);
        $stmt->bindValue(":add_date", $this->formatDateTime($this->add_date));
        $stmt->bindValue(":life_years", !empty($this->life_years) ? (int)$this->life_years : null, PDO::PARAM_INT);
        $stmt->bindValue(":accounting_items", !empty($this->accounting_items) ? (int)$this->accounting_items : null, PDO::PARAM_INT);
    }

    // 新增批次資產入庫
    public function create($creator_id) {
        try {
            // 基本驗證
            // 計算數量 (雖然 DB 會自動算，但這邊需要用迴圈產生單品)
            $qty = $this->suf_end_no - $this->suf_start_no + 1;

            if($qty <= 0) {
                throw new Exception("編號範圍無效 (結束編號必須大於起始編號)");
            }

            // 開始處理入庫邏輯
            $this->conn->beginTransaction();

            // 插入批次資料(asset_batches)
            // 注意：不插入 qty_purchased, total_price, add_date(資料庫無預設則需補NOW())
            $query = "INSERT INTO " . $this->table_batch . " 
                        SET 
                            creator_id      = :creator_id,
                            batch_no        = :batch_no,
                            pre_property_no = :pre_property_no,
                            suf_start_no    = :suf_start_no,
                            suf_end_no      = :suf_end_no,
                            category        = :category,
                            asset_name      = :asset_name,
                            brand           = :brand,
                            model           = :model,
                            spec            = :spec,
                            unit            = :unit,
                            unit_price      = :unit_price,
                            location        = :location,
                            purchase_date   = :purchase_date,
                            life_years      = :life_years,
                            accounting_items= :accounting_items,
                            fund_source     = :fund_source,
                            add_date        = :add_date";

            $stmt = $this->conn->prepare($query);

            
            // 綁定參數
            $this->bindAllValues($stmt);
            $stmt->bindValue(":creator_id", (int)$creator_id, PDO::PARAM_INT);
            $stmt->execute();

            // 取得剛建立的批次 ID
            $new_batch_id = $this->conn->lastInsertId();
            // $this->id = $new_batch_id; // 回存 ID 以便 Controller 使用
            $this->id = $this->conn->lastInsertId();

            // // 自動產生單品(asset_items)
            $this->rebuildItems($creator_id);
          

            // 提交交易
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // 發生錯誤，回滾交易
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            // 處理 MySQL 錯誤代碼 (例如 Trigger 擋下的重疊)
            if($e instanceof PDOException) {
                // Trigger 拋出的 SQLSTATE '45000'
                if ($e->getCode() == '45000') {
                    throw new Exception($e->errorInfo[2]); // 直接顯示 Trigger 的錯誤訊息
                }
                $errInfo = $e->errorInfo;
                if (isset($errInfo[1]) && $errInfo[1] == 1062) {
                    throw new Exception("入庫失敗：資料重複 (Unique constraint violation)。");
                }
            }
            throw $e;
        }
    }

        // 讀取該使用者建立的所有批次 (供列表顯示)
        public function search($creator_id, $page = 1, $limit = 10, $keyword = null) {
        $offset = ($page - 1) * $limit;
        $params = [':cid' => $creator_id];
        
        // 1. 構建條件子句
        $where = "WHERE b.creator_id = :cid";
        if (!empty($keyword)) {
            $where .= " AND (b.pre_property_no LIKE :kw OR b.asset_name LIKE :kw)";
            $params[':kw'] = "%{$keyword}%";
        }

        // 2. 計算總筆數
        $countSql = "SELECT COUNT(*) FROM {$this->table_batch} b {$where}";
        $stmtCount = $this->conn->prepare($countSql);
        foreach ($params as $key => $val) $stmtCount->bindValue($key, $val);
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();

        // 3. 查詢分頁資料
        $sql = "SELECT b.*, l.name as location_name 
                FROM {$this->table_batch} b
                LEFT JOIN locations l ON b.location = l.id
                {$where}
                ORDER BY b.id DESC
                LIMIT :limit OFFSET :offset";
                
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "total" => (int)$total,
            "page" => (int)$page,
            "limit" => (int)$limit,
            "total_pages" => ceil($total / $limit),
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // 更新批次資料及其關聯單品
    public function update($id, $creator_id) {
        try {
            $this->conn->beginTransaction();

            // 1. 取得舊資料
            $oldBatch = $this->getOldBatch($id, $creator_id);
        

            // 2. 判斷關鍵編號是否有變動
            $isRangeChanged = ((int)$oldBatch['suf_start_no'] !== (int)$this->suf_start_no || 
                            (int)$oldBatch['suf_end_no'] !== (int)$this->suf_end_no);

            if ($isRangeChanged) {
                $this->checkItemsIdle($id);
            }
            
            
            $query = "UPDATE {$this->table_batch} SET " . $this->getCommonSetSql() . " WHERE id = :id AND creator_id = :creator_id";
            $stmt = $this->conn->prepare($query);
            $this->bindAllValues($stmt);
            $stmt->bindValue(":id", (int)$id, PDO::PARAM_INT);
            $stmt->bindValue(":creator_id", (int)$creator_id, PDO::PARAM_INT);
            $stmt->execute();


            if ($isRangeChanged) {
                // 範圍變了打掉重練
                $this->rebuildItems($creator_id, $id);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    private function getOldBatch($id, $creator_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_batch} WHERE id = ? AND creator_id = ? FOR UPDATE");
        $stmt->execute([$id, $creator_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) throw new Exception("找不到該批次或無權限。");
        return $data;
    }

    private function checkItemsIdle($id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table_item} WHERE batch_id = ? AND status != '閒置'");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("此批次已有單品做過異動(撥發/維修)，禁止修改序號範圍。請先收回資產至閒置狀態。");
        }
    }

    // 重新產生單品 (給 create 與 update 共用)
    private function rebuildItems($creator_id, $batch_id = null) {
        $bid = $batch_id ?? $this->id;
        if ($batch_id) {
            $this->conn->prepare("DELETE FROM {$this->table_item} WHERE batch_id = ?")->execute([$bid]);
        }

        $query = "INSERT INTO {$this->table_item} SET batch_id=:bid, sub_no=:sno, status='閒置', item_condition='好', owner_id=:oid, location=:loc, updated_at=NOW()";
        $stmt = $this->conn->prepare($query);

        for ($i = (int)$this->suf_start_no; $i <= (int)$this->suf_end_no; $i++) {
            $propNo = $this->pre_property_no . str_pad($i, 3, '0', STR_PAD_LEFT);
            $stmt->execute([
                ":bid" => $bid,
                ":sno" => $i,
                ":oid" => $creator_id,
                ":loc" => $this->location
            ]);
        }
    }

    // 給ReportController用的，查詢所有單品中屬於該用戶的財產前綴
    public function getAllPrefixes($owner_id) {
        try {
            // 從單品表出發，去對應批次表的前綴
            $query = "SELECT DISTINCT b.pre_property_no 
                    FROM " . $this->table_batch . " b
                    INNER JOIN " . $this->table_item . " i ON b.id = i.batch_id
                    WHERE b.pre_property_no IS NOT NULL 
                    AND i.owner_id = :owner_id -- 強制過濾目前保管人
                    ORDER BY b.pre_property_no ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":owner_id", $owner_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
}

?>