<?php
/* --------------------------------------------------------------
   add_stock.php 
   -------------------------------------------------------------- */
session_start();
require_once "config.php";

/* ---------- SECURITY ---------- */
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$adminId = $_SESSION['admin_id'];

/* ---------- INPUT ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$batchId  = $_POST['batch_id']  ?? null;
$quantity = $_POST['quantity'] ?? null;
$remarks  = trim($_POST['remarks'] ?? '');

if (!$batchId || !is_numeric($batchId) || !$quantity || !is_numeric($quantity) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data supplied.']);
    exit;
}

/* ---------- TRANSACTION ---------- */
try {
    $conn->beginTransaction();

    /* 1. Get current batch (locked) */
    $batchStmt = $conn->prepare("
        SELECT catalog_id, batch_lot_number, stocks, stock_status
        FROM medicine_batches
        WHERE id = :batch_id
        FOR UPDATE
    ");
    $batchStmt->execute([':batch_id' => $batchId]);
    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new Exception('Batch not found.');
    }

    $oldStocks = (int)$batch['stocks'];
    $newStocks = $oldStocks + (int)$quantity;

    /* 2. Update batch */
    $newStatus = $newStocks > 0 ? 'In Stock' : 'Out of Stock';

    $upd = $conn->prepare("
        UPDATE medicine_batches
        SET stocks = :stocks,
            stock_status = :stock_status
        WHERE id = :batch_id
    ");
    $upd->execute([
        ':stocks'       => $newStocks,
        ':stock_status'=> $newStatus,
        ':batch_id'    => $batchId
    ]);

    /* 3. Update catalog total-stock status */
    $catUpd = $conn->prepare("
        UPDATE medicines_catalog
        SET stock_status = CASE
            WHEN (SELECT SUM(stocks) FROM medicine_batches WHERE catalog_id = :catalog_id) > 0
                THEN 'In Stock'
            ELSE 'Out of Stock'
        END
        WHERE id = :catalog_id
    ");
    $catUpd->execute([':catalog_id' => $batch['catalog_id']]);

    /* 4. Log to medicine_history */
    $details = "Added $quantity unit(s) to Batch #{$batch['batch_lot_number']}. " .
           "Previous: $oldStocks → New: $newStocks." .
           (!empty($remarks) ? " Remarks: $remarks" : '');

    // Replace with structured details:
    $details = json_encode([
        'quantity' => (int)$quantity,
        'previous_stock' => $oldStocks,
        'new_stock' => $newStocks,
        'remarks' => $remarks
    ], JSON_UNESCAPED_UNICODE);

    $log = $conn->prepare("
        INSERT INTO medicine_history
            (catalog_id, batch_id, action_type, details, performed_by)
        VALUES (:catalog_id, :batch_id, 'add_stock', :details, :admin_id)
    ");
    $log->execute([
        ':catalog_id'=> $batch['catalog_id'],
        ':batch_id'  => $batchId,
        ':details'   => $details,
        ':admin_id'  => $adminId
    ]);

    $conn->commit();

    /* ---------- SUCCESS JSON ---------- */
    $_SESSION['batch_message'] = "Stock added successfully! (+$quantity unit(s))"; 
    echo json_encode(['success' => true, 'message' => 'Stock added successfully!']);

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['batch_message']  = 'Error adding stock: ' . $e->getMessage();
    echo json_encode(['success' => false, 'message' => 'Error adding stock']);
}
exit;
?>

