<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportController {
    private $db;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->auth = new AuthMiddleware($db);
    }

    // 取得前綴
    public function getMetadata($params) {
        $user = $this->auth->authenticate();
        
        $batch = new AssetBatch($this->db);
        $results = [
            "prefixes" => $batch->getAllPrefixes($user['id']) 
        ];

        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    // 預覽功能
    public function preview($params) {
        $user = $this->auth->authenticate();
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $model = new AssetItem($this->db);
        
        // 傳入當前使用者 ID 作為強制過濾條件
        $result = $model->getReportData($data['report_type'], $data['filters'], $user['id']);
        
        header('Content-Type: application/json');
        echo json_encode(array_slice($result, 0, 50));
        exit;
    }

    // 匯出 Excel
    public function exportAssets($params) {
        $user = $this->auth->authenticate();

        $type = $_GET['type'] ?? 'asset_status';
        $filters = json_decode($_GET['filters'] ?? '{}', true);

        $model = new AssetItem($this->db);
        $data = $model->getReportData($type, $filters, $user['id']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $config = $this->getExportConfig($type);
        $headers = $config['headers'];
        $mapping = $config['mapping'];
        $fileName = $config['file_name'] . "_" . date('Ymd');

        //  寫入表頭並設定綠色樣式
        $colLetter = 'A';
        foreach ($headers as $headerText) {
            $sheet->setCellValue($colLetter . '1', $headerText);

            $style = $sheet->getStyle($colLetter . '1');
            $style->getFont()->setBold(true); // 粗體
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // 設定綠色背景 (FFC6EFCE 是 Excel 內建的淺綠色)
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE');
            $colLetter++;
        }

        // 寫入資料
        $rowIdx = 2;
        foreach ($data as $dbRow) {
            $colIdx = 'A';
            foreach ($mapping as $dbKey) {
                $value = $dbRow[$dbKey] ?? '';

                // 強制數字格式
                if ($dbKey === 'cost' || $dbKey === 'unit_price') {
                    $value = (float)$value;
                }

                $sheet->setCellValue($colIdx . $rowIdx, $value);
                $colIdx++;
            }
            $rowIdx++;
        }

        // 自動調整欄寬與外框
        $lastCol = chr(ord('A') + count($headers) - 1);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle("A1:{$lastCol}".($rowIdx-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 輸出檔案
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $encodedFileName = rawurlencode($fileName) . '.xlsx';
        header("Content-Disposition: attachment; filename=\"$encodedFileName\"; filename*=UTF-8''$encodedFileName");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // 報表配置清單
    private function getExportConfig($type) {
        switch ($type) {
            case 'asset_status':
                return [
                    'file_name' => '資產現況清冊',
                    'headers' => ['財產編號', '品名', '放置位置', '狀況', '狀態', '規格', '廠牌', '型號', '單價', '驗收日期', '使用年限', '會計項目', '經費來源', '增加單號'],
                    'mapping' => ['full_code', 'asset_name', 'location_name', 'item_condition', 'status', 'spec', 'brand', 'model', 'unit_price', 'purchase_date', 'life_years', 'accounting_items', 'fund_source', 'batch_no']
                ];
            case 'transaction_history':
                return [
                    'file_name' => '資產異動歷史',
                    'headers' => ['財產編號', '品名', '動作', '人員', '新保管人', '時間', '預計歸還', '位置', '備註'],
                    'mapping' => ['full_code', 'asset_name', 'action_type', 'borrower_name', 'new_owner_name', 'action_date', 'expected_return_date', 'location_name', 'note']
                ];
            case 'maintenance_history':
                return [
                    'file_name' => '維修保養紀錄',
                    'headers' => ['財產編號', '品名', '類型', '廠商', '故障描述', '處理結果', '判定', '費用', '送修日期', '完修日期'],
                    'mapping' => ['full_code', 'asset_name', 'action_type', 'vendor', 'issue_description', 'maintain_result', 'result_status', 'cost', 'send_date', 'finish_date']
                ];
            default:
                return ['file_name' => 'Report', 'headers' => [], 'mapping' => []];
        }
    }
}