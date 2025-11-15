<?php
// generate_medicine_report.php
session_start();
require_once "config.php";

// Security
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'health_staff'])) {
    die("Access denied.");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$report_type = $_POST['report_type'] ?? '';
$date_from   = $_POST['date_from'] ?? '';
$date_to     = $_POST['date_to'] ?? '';

// CSV headers
$filename = "Medicine_Report_" . ucwords(str_replace('_', ' ', $report_type)) . "_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// UTF-8 BOM so Excel displays special characters correctly
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

try {
    switch ($report_type) {

        // ================================================================
        // 1. TOTAL STOCK REPORT (with correct Received column)
        // ================================================================
        case 'total_stock':
            fputcsv($output, [
                'Therapeutic Category',
                'Name of Item (Generic Name + Dosage)',
                'Brand',
                'Unit',
                'Manufacturing Date',
                'Expiration Date',
                'Batch/Lot No.',
                'P.O No.',
                'Source',
                'Beginning Balance',
                'Received',            // Only from "Add Stock" actions
                'Issued',
                'Disposed',            // ← NEW COLUMN
                'End Balance'
            ]);

            $sql = "
                SELECT 
                    mc.therapeutic_category,
                    CONCAT(mc.generic_name, ' ', COALESCE(mc.dosage,'')) AS item_name,
                    COALESCE(mc.brand_name, 'N/A') AS brand_name,
                    COALESCE(mc.unit, 'pcs') AS unit,
                    mb.manufacturing_date,
                    mb.expiration_date,
                    mb.batch_lot_number,
                    COALESCE(mb.pono, 'N/A') AS pono,
                    COALESCE(mb.source, 'N/A') AS source,
                    mb.stocks AS current_balance,
                    mb.initial_stocks AS beginning_balance,
                    mb.id AS batch_id
                FROM medicine_batches mb
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                WHERE mb.is_disposed = 0
                ORDER BY mc.therapeutic_category, mc.generic_name, mb.expiration_date
            ";

            $stmt = $conn->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $batch_id = $row['batch_id'];

                // 1. Received (only from add_stock)
                $received_stmt = $conn->prepare("
                    SELECT COALESCE(SUM(
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.quantity')) AS UNSIGNED)
                    ), 0) AS total_received
                    FROM medicine_history 
                    WHERE batch_id = :bid 
                    AND action_type = 'add_stock'
                ");
                $received_stmt->execute([':bid' => $batch_id]);
                $received = (int)$received_stmt->fetchColumn();

                // 2. Issued (claimed distributions)
                $issued_stmt = $conn->prepare("
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM medicine_distributions 
                    WHERE batch_id = :bid AND status = 'claimed'
                ");
                $issued_stmt->execute([':bid' => $batch_id]);
                $issued = (int)$issued_stmt->fetchColumn();

                // 3. Disposed (NEW)
                $disposed_stmt = $conn->prepare("
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM medicine_disposals 
                    WHERE batch_id = :bid
                ");
                $disposed_stmt->execute([':bid' => $batch_id]);
                $disposed = (int)$disposed_stmt->fetchColumn();

                // 4. Beginning Balance
                $beginning_balance = $row['beginning_balance'];

                // Optional fallback logic (if needed)
                if ($received == 0 && ($issued > 0 || $disposed > 0)) {
                    $received = $row['current_balance'] + $issued + $disposed - $beginning_balance;
                    if ($received < 0) $received = 0;
                }

                // Output row with Disposed column
                fputcsv($output, [
                    $row['therapeutic_category'],
                    $row['item_name'],
                    $row['brand_name'],
                    $row['unit'],
                    $row['manufacturing_date'] ?: 'N/A',
                    $row['expiration_date'] ?: 'N/A',
                    $row['batch_lot_number'],
                    $row['pono'],
                    $row['source'],
                    $beginning_balance,
                    $received,
                    $issued,
                    $disposed,                    // ← NEW: Disposed quantity
                    $row['current_balance']       // End Balance
                ]);
            }
            break;

        // ================================================================
        // The rest of the reports remain the same (only total_stock changed)
        // ================================================================
        case 'out_of_stock':
            fputcsv($output, ['Therapeutic Category','Generic Name','Brand','Dosage','Unit','Min Stock','Current Stock']);
            $stmt = $conn->query("SELECT * FROM medicines_catalog WHERE stock_status = 'Out of Stock'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cur = $conn->query("SELECT COALESCE(SUM(stocks),0) FROM medicine_batches WHERE catalog_id = {$row['id']} AND is_disposed = 0")->fetchColumn();
                fputcsv($output, [
                    $row['therapeutic_category'],
                    $row['generic_name'],
                    $row['brand_name'] ?: 'N/A',
                    $row['dosage'],
                    $row['unit'] ?: 'pcs',
                    $row['min_stock'],
                    $cur
                ]);
            }
            break;

        case 'low_stock':
            fputcsv($output, ['Therapeutic Category','Generic Name','Brand','Dosage','Unit','Min Stock','Current Stock']);
            $stmt = $conn->query("
                SELECT mc.*, COALESCE(SUM(mb.stocks),0) AS total_stock
                FROM medicines_catalog mc
                LEFT JOIN medicine_batches mb ON mc.id = mb.catalog_id AND mb.is_disposed = 0
                GROUP BY mc.id
                HAVING total_stock < mc.min_stock AND total_stock > 0
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['therapeutic_category'],
                    $row['generic_name'],
                    $row['brand_name'] ?: 'N/A',
                    $row['dosage'],
                    $row['unit'] ?: 'pcs',
                    $row['min_stock'],
                    $row['total_stock']
                ]);
            }
            break;

        case 'expired':
            fputcsv($output, ['Therapeutic Category','Item (Generic + Dosage)','Brand','Batch','Mfg Date','Exp Date','Source','Remaining']);
            $stmt = $conn->query("
                SELECT mc.therapeutic_category, mc.generic_name, mc.dosage, mc.brand_name,
                       mb.batch_lot_number, mb.manufacturing_date, mb.expiration_date, mb.source, mb.stocks
                FROM medicine_batches mb
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                WHERE mb.expiration_date < CURDATE() AND mb.is_disposed = 0
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['therapeutic_category'],
                    $row['generic_name'].' '.$row['dosage'],
                    $row['brand_name'] ?: 'N/A',
                    $row['batch_lot_number'],
                    $row['manufacturing_date'] ?: 'N/A',
                    $row['expiration_date'],
                    $row['source'] ?: 'N/A',
                    $row['stocks']
                ]);
            }
            break;

        case 'expiring_soon':
            fputcsv($output, ['Therapeutic Category','Item','Brand','Batch','Exp Date','Days Left','Stock']);
            $stmt = $conn->query("
                SELECT mc.therapeutic_category, mc.generic_name, mc.dosage, mc.brand_name,
                       mb.batch_lot_number, mb.expiration_date, mb.stocks,
                       DATEDIFF(mb.expiration_date, CURDATE()) AS days_left
                FROM medicine_batches mb
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                WHERE mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  AND mb.is_disposed = 0
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['therapeutic_category'],
                    $row['generic_name'].' '.$row['dosage'],
                    $row['brand_name'] ?: 'N/A',
                    $row['batch_lot_number'],
                    $row['expiration_date'],
                    $row['days_left'],
                    $row['stocks']
                ]);
            }
            break;

        case 'disposed':
            fputcsv($output, ['Date Disposed','Item','Brand','Batch','Qty','Reason','Performed By','Witness']);
            $stmt = $conn->query("
                SELECT md.disposal_date, mc.generic_name, mc.dosage, mc.brand_name,
                       mb.batch_lot_number, md.quantity, md.reason, a.full_name, md.witness_name
                FROM medicine_disposals md
                JOIN medicine_batches mb ON md.batch_id = mb.id
                JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                JOIN admin_staff a ON md.performed_by = a.id
                ORDER BY md.disposal_date DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    date('M d, Y g:i A', strtotime($row['disposal_date'])),
                    $row['generic_name'].' '.$row['dosage'],
                    $row['brand_name'] ?: 'N/A',
                    $row['batch_lot_number'],
                    $row['quantity'],
                    ucwords(str_replace('_',' ',$row['reason'])),
                    $row['full_name'],
                    $row['witness_name']
                ]);
            }
            break;

        default:
            fputcsv($output, ['Error','Invalid report type selected.']);
    }

} catch (Exception $e) {
    fputcsv($output, ['Error','Failed to generate report: '.$e->getMessage()]);
}

fclose($output);
exit();
?>