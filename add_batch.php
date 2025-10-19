<?php
//add_batch.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate input data
$required_fields = ['catalog_id', 'batch_lot_number', 'expiration_date', 'stocks'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit();
    }
}

$catalog_id = trim($_POST['catalog_id']);
$batch_lot_number = trim($_POST['batch_lot_number']);
$pono = isset($_POST['pono']) ? trim($_POST['pono']) : null;
$manufacturing_date = !empty($_POST['manufacturing_date']) ? trim($_POST['manufacturing_date']) : null;
$expiration_date = trim($_POST['expiration_date']);
$stocks = (int) trim($_POST['stocks']);
$source = isset($_POST['source']) ? trim($_POST['source']) : null;
$admin_id = $_SESSION['admin_id'];

// Validate catalog_id exists
$catalogStmt = $conn->prepare("SELECT id FROM medicines_catalog WHERE id = :catalog_id");
$catalogStmt->execute([':catalog_id' => $catalog_id]);
if ($catalogStmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid catalog ID']);
    exit();
}

// Validate batch lot number uniqueness
$batchStmt = $conn->prepare("SELECT id FROM medicine_batches WHERE batch_lot_number = :batch_lot_number AND catalog_id = :catalog_id");
$batchStmt->execute([':batch_lot_number' => $batch_lot_number, ':catalog_id' => $catalog_id]);
if ($batchStmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Batch lot number already exists for this medicine']);
    exit();
}

// Determine stock status
$stock_status = ($stocks <= 0) ? 'Out of Stock' : 'In Stock';

// Determine expiry status
$current_date = new DateTime();
$expiry_date = new DateTime($expiration_date);
$interval = $current_date->diff($expiry_date);
$days_to_expiry = $interval->days * ($interval->invert ? -1 : 1);

if ($days_to_expiry < 0) {
    $expiry_status = 'Expired';
} elseif ($days_to_expiry <= 7) {
    $expiry_status = 'Expiring within a week';
} elseif ($days_to_expiry <= 30) {
    $expiry_status = 'Expiring within a month';
} else {
    $expiry_status = 'Valid';
}

try {
    $conn->beginTransaction();

    // Insert new batch
    $insertStmt = $conn->prepare("
        INSERT INTO medicine_batches (
            catalog_id, batch_lot_number, pono, manufacturing_date, 
            expiration_date, stocks, stock_status, expiry_status, source
        ) VALUES (
            :catalog_id, :batch_lot_number, :pono, :manufacturing_date, 
            :expiration_date, :stocks, :stock_status, :expiry_status, :source
        )
    ");
    $insertStmt->execute([
        ':catalog_id' => $catalog_id,
        ':batch_lot_number' => $batch_lot_number,
        ':pono' => $pono,
        ':manufacturing_date' => $manufacturing_date,
        ':expiration_date' => $expiration_date,
        ':stocks' => $stocks,
        ':stock_status' => $stock_status,
        ':expiry_status' => $expiry_status,
        ':source' => $source
    ]);

    $batch_id = $conn->lastInsertId();

    // Log the action in medicine_history
    $details = "Added batch: Lot $batch_lot_number, Stocks: $stocks, Source: " . ($source ?: 'N/A');
    $historyStmt = $conn->prepare("INSERT INTO medicine_history (batch_id, action_type, details, performed_by) VALUES (:batch_id, 'add_batch', :details, :performed_by)");
    $historyStmt->execute([
        ':batch_id' => $batch_id,
        ':details' => $details,
        ':performed_by' => $admin_id
    ]);

    $conn->commit();

    header("Location: view_batches.php?catalog_id=" . urlencode($catalog_id));
    exit();
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error adding batch: ' . $e->getMessage()]);
    exit();
}

?>