<?php
session_start();
require_once "config.php";

// Security check
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'health_staff'])) {
    die("Access denied.");
}

require_once 'vendor/autoload.php'; // If using Composer
// OR: require_once 'vendor/PhpSpreadsheet/src/Bootstrap.php'; // Manual install

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$report_type = $_POST['report_type'] ?? '';
$date_from   = $_POST['date_from'] ?? '';
$date_to     = $_POST['date_to'] ?? '';

if (empty($report_type)) {
    die("No report type selected.");
}

// Set filename
$filename = "Medicine_Report_" . ucwords(str_replace('_', ' ', $report_type)) . "_" . date('Y-m-d') . ".xlsx";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Title
$sheet->setCellValue('A1', getSetting($conn, 'site_name', 'MaruHealth') . ' - Barangay Marulas 3S Health Center');
$sheet->setCellValue('A2', ucwords(str_replace('_', ' ', $report_type)) . " Report");
$sheet->setCellValue('A3', "Generated on: " . date('F d, Y h:i A'));
if ($date_from && $date_to) {
    $sheet->setCellValue('A4', "Period: " . date('M d, Y', strtotime($date_from)) . " to " . date('M d, Y', strtotime($date_to)));
}

// Style title
$sheet->getStyle('A1:A4')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setSize(16);
$sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A1:F1');
$sheet->mergeCells('A2:F2');
$sheet->mergeCells('A3:F3');
if ($date_from && $date_to) $sheet->mergeCells('A4:F4');

$row = 6;

$data = [];

// Queries per report type
switch ($report_type) {
    case 'total_stock':
        $sql = "SELECT 
                    mc.generic_name,
                    mc.brand_name,
                    mc.dosage,
                    mc.dosage_form,
                    mc.unit,
                    COALESCE(SUM(mb.stocks), 0) as total_stock,
                    mc.min_stock
                FROM medicines_catalog mc
                LEFT JOIN medicine_batches mb ON mc.id = mb.catalog_id AND mb.is_disposed = 0
                GROUP BY mc.id
                HAVING total_stock > 0
                ORDER BY mc.generic_name";

        $headers = ['Generic Name', 'Brand Name', 'Dosage', 'Form', 'Unit', 'Total Stock', 'Min Stock'];
        break;

    case 'out_of_stock':
        $sql = "SELECT generic_name, brand_name, dosage, dosage_form, unit, min_stock 
                FROM medicines_catalog 
                WHERE stock_status = 'Out of Stock'";
        $headers = ['Generic Name', 'Brand Name', 'Dosage', 'Form', 'Unit', 'Min Stock'];
        break;

    case 'low_stock':
        $sql = "SELECT 
                    mc.generic_name,
                    mc.brand_name,
                    mc.dosage,
                    mc.dosage_form,
                    mc.unit,
                    COALESCE(SUM(mb.stocks), 0) as current_stock,
                    mc.min_stock
                FROM medicines_catalog mc
                LEFT JOIN medicine_batches mb ON mc.id = mb.catalog_id AND mb.is_disposed = 0
                GROUP BY mc.id
                HAVING current_stock > 0 AND current_stock < mc.min_stock
                ORDER BY current_stock ASC";
        $headers = ['Generic Name', 'Brand Name', 'Dosage', 'Form', 'Unit', 'Current Stock', 'Min Stock'];
        break;

    case 'disposed':
        $sql = "SELECT 
                    mc.generic_name,
                    mc.brand_name,
                    mb.batch_lot_number,
                    md.quantity,
                    md.disposal_date,
                    md.reason,
                    a.full_name as disposed_by
                FROM medicine_disposals md
                JOIN medicine_batches mb ON md.batch_id = mb.id
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                JOIN admin_staff a ON md.performed_by = a.id
                WHERE 1=1";
        if ($date_from) $sql .= " AND DATE(md.disposal_date) >= '$date_from'";
        if ($date_to) $sql .= " AND DATE(md.disposal_date) <= '$date_to'";
        $sql .= " ORDER BY md.disposal_date DESC";
        $headers = ['Generic Name', 'Brand', 'Batch', 'Qty Disposed', 'Disposal Date', 'Reason', 'Disposed By'];
        break;

    case 'expired':
        $sql = "SELECT 
                    mc.generic_name,
                    mc.brand_name,
                    mb.batch_lot_number,
                    mb.expiration_date,
                    mb.stocks
                FROM medicine_batches mb
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                WHERE mb.expiration_date < CURDATE() AND mb.is_disposed = 0";
        $headers = ['Generic Name', 'Brand', 'Batch', 'Expiration Date', 'Remaining Stock'];
        break;

    case 'expiring_soon':
        $sql = "SELECT 
                    mc.generic_name,
                    mc.brand_name,
                    mb.batch_lot_number,
                    mb.expiration_date,
                    mb.stocks,
                    DATEDIFF(mb.expiration_date, CURDATE()) as days_left
                FROM medicine_batches mb
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                WHERE mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  AND mb.is_disposed = 0
                ORDER BY mb.expiration_date ASC";
        $headers = ['Generic Name', 'Brand', 'Batch', 'Expiration Date', 'Stock', 'Days Left'];
        break;

    default:
        die("Invalid report type");
}

$stmt = $conn->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $row, $h);
    $col++;
}

// Style header row
$headerRange = 'A' . $row . ':' . $col . $row;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF800000');
$sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row++;

// Fill data
foreach ($data as $item) {
    $col = 'A';
    foreach ($item as $key => $value) {
        if ($key === 'expiration_date' || $key === 'disposal_date') {
            $sheet->setCellValue($col . $row, $value ? date('M d, Y', strtotime($value)) : 'N/A');
        } elseif ($key === 'days_left') {
            $sheet->setCellValue($col . $row, $value . " days");
        } else {
            $sheet->setCellValue($col . $row, $value ?? 'N/A');
        }
        $col++;
    }
    $row++;
}

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add borders
$sheet->getStyle('A6:' . $sheet->getHighestColumn() . ($row - 1))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Add filter
$sheet->setAutoFilter('A' . ($row - count($data) - 1) . ':' . $sheet->getHighestColumn() . ($row - 1));

// Footer
$sheet->setCellValue('A' . ($row + 2), "Total Records: " . count($data));
$sheet->getStyle('A' . ($row + 2))->getFont()->setBold(true);

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>