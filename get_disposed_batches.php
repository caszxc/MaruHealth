<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$catalog_id = $_POST['catalog_id'] ?? '';
if (!is_numeric($catalog_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid catalog']);
    exit();
}

try {
    $sql = "
        SELECT 
            mb.batch_lot_number,
            md.quantity AS disposed_qty,
            md.stock_before_disposal AS remaining_at_that_time,
            md.reason,
            md.witness_name,
            a.full_name AS performed_by,
            DATE_FORMAT(md.disposal_date, '%b %d, %Y %l:%i %p') AS disposal_date,
            mb.stocks AS current_stocks_now
        FROM medicine_disposals md
        JOIN medicine_batches mb ON md.batch_id = mb.id
        JOIN admin_staff a ON md.performed_by = a.id
        WHERE mb.catalog_id = ?
        ORDER BY md.disposal_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$catalog_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>