<?php
class AssetItem {
    private $conn;
    private $table = "asset_items";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 進階查詢與分頁
    public function search($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        // 1. 建構 SQL 查詢
        $query = "SELECT 
                    i.id, 
                    i.batch_id,
                    i.sub_no, 
                    i.status, 
                    i.item_condition, 
                    i.updated_at,
                    b.pre_property_no,
                    b.asset_name, 
                    b.brand, 
                    b.model, 
                    b.spec,
                    l.name as location_name,
                    u_owner.name as owner_name,
                    IFNULL(i.borrower_name, u_borrower.name) as `current_user`
                  FROM " . $this->table . " i
                  JOIN asset_batches b ON i.batch_id = b.id
                  LEFT JOIN locations l ON i.location = l.id
                  LEFT JOIN users u_owner ON i.owner_id = u_owner.id
                  LEFT JOIN users u_borrower ON i.borrower_id = u_borrower.id";

        // 2. 動態加入篩選條件
        $conditions = []; // 條件
        $params = []; // 參數

        if (!empty($filters['keyword'])) {
            // 修正版搜尋邏輯
            // 假設資料庫裡 pre_property_no 存的是 "3013208-63"
            // 假設資料庫裡 sub_no 存的是 "10592"
            
            $conditions[] = "(
                -- 1. 搜尋前綴 (例如輸入 '3013208-63')
                b.pre_property_no LIKE :keyword OR 
                
                -- 2. 搜尋子序號 (例如輸入 '10592')
                i.sub_no LIKE :keyword OR 
                
                -- 3. 搜尋完整編號 (將兩者用 '-' 連接起來，例如輸入 '3013208-63-10592')
                CONCAT(b.pre_property_no, '-', i.sub_no) LIKE :keyword OR 
                
                -- 4. 搜尋其他欄位 (品名、廠牌、型號、批號)
                b.asset_name LIKE :keyword OR 
                b.brand LIKE :keyword OR 
                b.model LIKE :keyword
            )";
            
            $params[':keyword'] = "%" . $filters['keyword'] . "%";
        }

        // 2. 【新增】狀態篩選 (這是為了您的維修選單加的！)
        // 如果前端傳來 'status' => '可維修', 我們就自動排除那些不能修的
        if (!empty($filters['filter_scope']) && $filters['filter_scope'] == 'maintainable') {
            // 邏輯：排除 '維修中', '報廢', '遺失'
            $conditions[] = "i.status NOT IN ('維修中', '報廢', '遺失')";
        }

        if (!empty($filters['status'])) {
            $conditions[] = "i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['owner_id'])) {
            $conditions[] = "i.owner_id = :owner_id";
            $params[':owner_id'] = $filters['owner_id'];
        }
        
        if (!empty($filters['category'])) {
            $conditions[] = "b.category = :category";
            $params[':category'] = $filters['category'];
        }

        // 組合 WHERE 子句
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        // 加入排序
        $query .= " ORDER BY i.id DESC";

        // 3. 執行分頁查詢
        
        // A. 先算總筆數 (Total Count)
        $countQuery = "SELECT COUNT(*) FROM " . $this->table . " i JOIN asset_batches b ON i.batch_id = b.id WHERE " . (count($conditions) > 0 ? implode(" AND ", $conditions) : "1");
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total_rows = $stmtCount->fetchColumn();

        // B. 加上 LIMIT 設定分頁範圍 (跳過 0 筆，抓 10 筆 (第 1 ~ 10 筆))
        $query .= " LIMIT " . (int)$offset . ", " . (int)$limit;
        
        $stmt = $this->conn->prepare($query);

        // 綁定篩選參數 (如果有)
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        // 執行查詢
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            // 如果出錯，回傳空陣列或拋出更清楚的錯誤
            throw new Exception("資料查詢失敗: " . $e->getMessage());
        }

        return [
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total_rows,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => ceil($total_rows / $limit)
        ];
    }
    
    // 讀取單一資產詳情
    public function readOne($id) {
         $query = "SELECT 
                    i.*,
                    b.asset_name, b.brand, b.model, b.spec, b.category, b.purchase_date, b.life_years, b.unit_price,
                    l.name as location_name,
                    u.name as owner_name
                  FROM " . $this->table . " i
                  JOIN asset_batches b ON i.batch_id = b.id
                  LEFT JOIN locations l ON i.location = l.id
                  LEFT JOIN users u ON i.owner_id = u.id
                  WHERE i.id = :id LIMIT 1";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 取得資產完整履歷 (異動 + 維修 + 入庫)
    public function getHistory($id) {
        $query = "
            (
                -- 1. 異動紀錄 (領用、借用、歸還、移轉、報廢)
                SELECT 
                    'Transaction' as source_type,
                    t.action_date as event_date,
                    t.action_type,
                    CASE 
                        WHEN t.action_type = '借用' THEN IFNULL(u_b.name, t.borrower)
                        WHEN t.action_type = '移轉' THEN u_n.name
                        ELSE NULL 
                    END as target_name,
                    
                    -- 根據不同的 action_type 組合不同的 description
                    CASE 
                        WHEN t.action_type = '借用' THEN 
                            CONCAT('備註: ', IFNULL(t.note, '無'), '\n預計歸還: ', IFNULL(t.expected_return_date, '未定'))
                        WHEN t.action_type = '歸還' THEN 
                            CONCAT('備註: ', IFNULL(t.note, '無'), '\n物品狀況: ', IFNULL(t.item_condition, '正常'))
                        WHEN t.action_type IN ('領用', '使用', '移轉', '報廢') THEN 
                            CONCAT('備註: ', IFNULL(t.note, '無'))
                        ELSE IFNULL(t.note, '')
                    END as description,
                    
                    l.name as location,
                    t.id as sort_id
                FROM asset_transactions t
                LEFT JOIN users u_b ON t.borrower_id = u_b.id
                LEFT JOIN users u_n ON t.new_owner_id = u_n.id
                LEFT JOIN locations l ON t.location_id = l.id
                WHERE t.item_id = :id1
            )
            UNION ALL
            (
                -- 2. 維修紀錄 (送修)
                SELECT 
                    'Maintenance_Start' as source_type,
                    m.send_date as event_date,
                    CONCAT(m.action_type, ' (送修)') as action_type,
                    m.vendor as target_name,
                    
                    -- 送修只顯示故障描述
                    CONCAT('故障描述: ', IFNULL(m.issue_description, '未提供')) as description,
                    
                    NULL as location,
                    m.id as sort_id
                FROM asset_maintenance m
                WHERE m.item_id = :id2 AND m.is_deleted = 0
            )
            UNION ALL
            (
                -- 3. 維修紀錄 (結案)
                SELECT 
                    'Maintenance_End' as source_type,
                    m.finish_date as event_date,
                    CONCAT(m.action_type, ' (結案)') as action_type,
                    m.vendor as target_name,
                    
                    -- 結案顯示結果、描述、費用與日期
                    CONCAT(
                        '維修結果: ', IFNULL(m.result_status, '無'), 
                        '\n詳細說明: ', IFNULL(m.maintain_result, '無'),
                        '\n維修費用: $', IFNULL(m.cost, 0)
                    ) as description,
                    
                    NULL as location,
                    m.id as sort_id
                FROM asset_maintenance m
                WHERE m.item_id = :id3 AND m.is_deleted = 0 AND m.finish_date IS NOT NULL
            )
            UNION ALL
            (
                -- 4. 原始入庫紀錄
                SELECT 
                    'Ingest' as source_type,
                    b.add_date as event_date,
                    '入庫' as action_type,
                    NULL as target_name,
                    
                    -- 入庫顯示完整的資產詳細規格與資訊
                    CONCAT(
                        '廠牌: ', IFNULL(b.brand, '-'), 
                        '\n型號: ', IFNULL(b.model, '-'),
                        '\n規格: ', IFNULL(b.spec, '-'),
                        '\n單價: $', IFNULL(b.unit_price, 0),
                        '\n驗收日期: ', IFNULL(b.purchase_date, '-'),
                        '\n使用年限: ', IFNULL(b.life_years, 0), ' 年',
                        '\n經費來源: ', IFNULL(b.fund_source, '-')
                    ) as description,
                    
                    l.name as location,
                    0 as sort_id
                FROM asset_items i
                JOIN asset_batches b ON i.batch_id = b.id
                LEFT JOIN locations l ON b.location = l.id
                WHERE i.id = :id4
            )
            ORDER BY event_date ASC, sort_id ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id1", $id);
        $stmt->bindParam(":id2", $id);
        $stmt->bindParam(":id3", $id);
        $stmt->bindParam(":id4", $id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>