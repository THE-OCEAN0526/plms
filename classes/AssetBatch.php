<?php
class AssetBatch {
    private $conn;
    private $table_batch = "asset_batches";
    private $table_item = "asset_items";

    // 屬性
    public $id;
    public $batch_no;
    public $add_date;
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
    public $life_years;    
    public $accounting_items; 
    public $fund_source;    

    // 注意: qty_purchased 和 total_price 是資料庫自動生成 (Generated Columns)，不需要 PHP 插入

    public function __construct($db) {
        $this->conn = $db;
    }

    // 新增批次資產入庫
    public function create($owner_id) {
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

            // 如果前端有傳日期，我們補上「目前」的時分秒，確保排序精確
            // 如果沒傳，補上 00:00:00 確保它是當天第一筆
            if (empty($this->add_date)) {
                $this->add_date = date("Y-m-d H:i:s");
            } else if (strlen($this->add_date) == 10) {
                $this->add_date .= " 00:00:00";
            }

            // 綁定參數
            $stmt->bindParam(":batch_no", $this->batch_no);
            $stmt->bindParam(":pre_property_no", $this->pre_property_no);
            $stmt->bindParam(":suf_start_no", $this->suf_start_no);
            $stmt->bindParam(":suf_end_no", $this->suf_end_no);
            $stmt->bindParam(":category", $this->category);
            $stmt->bindParam(":asset_name", $this->asset_name);
            $stmt->bindParam(":brand", $this->brand);
            $stmt->bindParam(":model", $this->model);
            $stmt->bindParam(":spec", $this->spec);
            $stmt->bindParam(":unit", $this->unit);
            $stmt->bindParam(":unit_price", $this->unit_price);
            $stmt->bindParam(":location", $this->location);
            $stmt->bindParam(":purchase_date", $this->purchase_date);
            $stmt->bindParam(":life_years", $this->life_years);
            $stmt->bindParam(":accounting_items", $this->accounting_items);
            $stmt->bindParam(":fund_source", $this->fund_source);
            $stmt->bindParam(":add_date", $this->add_date);

            $stmt->execute();

            // 取得剛建立的批次 ID
            $new_batch_id = $this->conn->lastInsertId();
            $this->id = $new_batch_id; // 回存 ID 以便 Controller 使用

            // 自動產生單品(asset_items)
            $queryItem = "INSERT INTO " . $this->table_item . "
                          SET 
                              batch_id = :batch_id, 
                              sub_no = :sub_no,
                              status = '閒置',
                              item_condition = '好',
                              owner_id = :owner_id,
                              location = :location, 
                              updated_at = NOW()";

            $stmtItem = $this->conn->prepare($queryItem);

            // 產生單品資料
            for($i = $this->suf_start_no; $i <= $this->suf_end_no; $i++) {
                $stmtItem->bindParam(":batch_id", $new_batch_id);
                $stmtItem->bindParam(":sub_no", $i);
                $stmtItem->bindParam(":owner_id", $owner_id); // 財產擁有人
                $stmtItem->bindParam(":location", $this->location);
                $stmtItem->execute();
            }

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

    public function getAllPrefixes() {
        try {
            $query = "SELECT DISTINCT pre_property_no 
                      FROM " . $this->table_batch . " 
                      WHERE pre_property_no IS NOT NULL 
                      ORDER BY pre_property_no ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
}

?>