<?php
class AssetBatch {
    private $conn;
    private $table_batch = "asset_batches";
    private $table_item = "asset_items";

    // 屬性
    public $id;
    public $batch_no;
    public $pre_property_no;
    public $suf_property_no;
    public $category; 
    public $asset_name;
    public $brand;
    public $model;
    public $spec;
    public $qty_purchased;
    public $unit; 
    public $unit_price;
    // public $total_price;
    public $purchase_date;
    public $life_years;
    public $accounting_items;
    public $fund_source;
    public $add_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // 新增批次資產入庫
    public function create() {
        try {
            // 1. 前置檢查：解析財產編號後綴範圍
            // 預期格式 "11521-11540"
            $range_parts = explode('-', $this->suf_property_no);
            if (count($range_parts) !== 2) throw new Exception("編號範圍格式錯誤");

            $start_no = intval($range_parts[0]);
            $end_no   = intval($range_parts[1]);

            // 計算範圍內的數量
            $range_count = $end_no - $start_no + 1;

            if ($range_count <= 0) throw new Exception("編號範圍無效");
            // 範圍數量必須等於購買數量
            if ($range_count != $this->qty_purchased) throw new Exception("數量不符");

            // 2. 開始交易 
            // 關閉自動存檔 (Auto-Commit) 保護資料一致性
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table_batch . " 
                      SET 
                        batch_no        = :batch_no,
                        pre_property_no = :pre_property_no,
                        suf_property_no = :suf_property_no,
                        category        = :category,
                        asset_name      = :asset_name,
                        brand           = :brand,
                        model           = :model,
                        spec            = :spec,
                        qty_purchased   = :qty,
                        unit            = :unit,
                        unit_price      = :unit_price,
                        purchase_date   = :purchase_date,
                        life_years      = :life_years,
                        accounting_items= :accounting_items,
                        fund_source     = :fund_source,
                        add_date        = NOW()";
            
            $stmt = $this->conn->prepare($query);

            // 清理輸入
            $this->batch_no = htmlspecialchars(strip_tags($this->batch_no));
            $this->asset_name = htmlspecialchars(strip_tags($this->asset_name));
            
            // 綁定所有參數 (全都是必填)
            $stmt->bindParam(":batch_no", $this->batch_no);
            $stmt->bindParam(":asset_name", $this->asset_name);
            $stmt->bindParam(":qty", $this->qty_purchased);
            $stmt->bindParam(":unit_price", $this->unit_price);
            $stmt->bindParam(":category", $this->category);
            $stmt->bindParam(":unit", $this->unit);
            $stmt->bindParam(":pre_property_no", $this->pre_property_no);
            $stmt->bindParam(":suf_property_no", $this->suf_property_no);
            $stmt->bindParam(":brand", $this->brand);
            $stmt->bindParam(":model", $this->model);
            $stmt->bindParam(":spec", $this->spec);
            $stmt->bindParam(":purchase_date", $this->purchase_date);
            $stmt->bindParam(":life_years", $this->life_years);
            $stmt->bindParam(":accounting_items", $this->accounting_items);
            $stmt->bindParam(":fund_source", $this->fund_source);

            
            $stmt->execute();

            // 取得剛建立的批次 ID
            $new_batch_id = $this->conn->lastInsertId();

            // 3. 自動產生單品
            $queryItem = "INSERT INTO " . $this->table_item . "
                          SET batch_id=:batch_id, sub_no=:sub_no";

            $stmtItem = $this->conn->prepare($queryItem);

            // 產生單品資料
            for ($i = $start_no; $i <= $end_no; $i++) {
                $stmtItem->bindParam(":batch_id", $new_batch_id);
                $stmtItem->bindParam(":sub_no", $i);
                $stmtItem->execute();
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // 1: 先檢查是否有交易正在進行，才 Rollback
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            
            // 2: 在這裡攔截 SQL 錯誤並翻譯
            if ($e instanceof PDOException) {
                // 取得錯誤代碼
                $err = $e->errorInfo[1] ?? 0;
                if ($err == 1062) throw new Exception("入庫失敗：此財產編號組合已存在。");
                if ($err == 1265) throw new Exception("入庫失敗：單位或類別不符。");
            }
            // 為了讓 API 能顯示具體錯誤，把 Exception 往外拋
            throw $e; 
        }
    }

    // 取得所有批次資料
    public function readPaging($keyword, $from_record_num, $records_per_page) {
        $query = "SELECT * FROM " . $this->table_batch;

        // 加入搜尋條件
        if (!empty($keyword)) {
            $query .= " WHERE asset_name LIKE :keyword OR batch_no LIKE :keyword OR pre_property_no LIKE :keyword OR suf_property_no LIKE :keyword";
        }

        // 加入排序與分頁
        $query .= " ORDER BY id DESC LIMIT :from, :limit";

        $stmt = $this->conn->prepare($query);

    
        if (!empty($keyword)) {
            $keyword = "%{$keyword}%"; // 模糊搜尋
            $stmt->bindParam(":keyword", $keyword);
        }

        // 綁定分頁參數
        $stmt->bindParam(":from", $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $records_per_page, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // 3. 計算總筆數 (為了前端分頁條)
    public function countAll($keyword) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_batch;

        if (!empty($keyword)) {
            $query .= " WHERE asset_name LIKE :keyword OR batch_no LIKE :keyword OR pre_property_no LIKE :keyword OR suf_property_no LIKE :keyword";
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($keyword)) {
            $keyword = "%{$keyword}%";
            $stmt->bindParam(":keyword", $keyword);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
}
?>