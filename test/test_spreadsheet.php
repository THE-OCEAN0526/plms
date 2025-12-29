<?php
// test/test_spreadsheet.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/Database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "--- 正在從資料庫抓取資產資料 (根據實際 Schema) ---\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    // 修正後的 SQL：關聯 asset_batches 與 locations
    // 財產編號由 pre_property_no + sub_no 組成
    $query = "SELECT 
                CONCAT(b.pre_property_no, '-', i.sub_no) as full_code, 
                b.asset_name, 
                b.category, 
                l.name as location_name, 
                i.status, 
                i.updated_at 
              FROM asset_items i
              JOIN asset_batches b ON i.batch_id = b.id
              LEFT JOIN locations l ON i.location = l.id
              ORDER BY i.updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('資產清單');

    // 設定表頭
    $headers = ['財產編號', '資產名稱', '類別', '放置地點', '目前狀態', '最後異動時間'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }

    // 填入資料
    $row = 2;
    foreach ($assets as $item) {
        $sheet->setCellValue('A' . $row, $item['full_code']);
        $sheet->setCellValue('B' . $row, $item['asset_name']);
        $sheet->setCellValue('C' . $row, $item['category']);
        $sheet->setCellValue('D' . $row, $item['location_name'] ?? '未設定');
        $sheet->setCellValue('E' . $row, $item['status']);
        $sheet->setCellValue('F' . $row, $item['updated_at']);
        $row++;
    }

    // 自動調整寬度
    foreach (range('A', 'F') as $colLetter) {
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $outputPath = __DIR__ . '/asset_report.xlsx';
    $writer->save($outputPath);

    echo "✅ 成功！已根據實際 Schema 產出報表。\n";
    echo "檔案路徑：$outputPath\n";
    echo "總共導出 " . count($assets) . " 筆資料。\n";

} catch (Exception $e) {
    echo "❌ 錯誤：" . $e->getMessage() . "\n";
}