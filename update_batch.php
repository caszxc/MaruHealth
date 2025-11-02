<?php
//update_batch.php
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
$required_fields = ['batch_id', 'batch_lot_number', 'expiration_date', 'stocks'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit();
    }
}

$batch_id = trim($_POST['batch_id']);
$batch_lot_number = trim($_POST['batch_lot_number']);
$pono = isset($_POST['pono']) ? trim($_POST['pono']) : null;
$manufacturing_date = !empty($_POST['manufacturing_date']) ? trim($_POST['manufacturing_date']) : null;
$expiration_date = trim($_POST['expiration_date']);
$stocks = (int) trim($_POST['stocks']);
$source = isset($_POST['source']) ? trim($_POST['source']) : null;
$admin_id = $_SESSION['admin_id'];

// Validate batch_id exists and get catalog_id
$batchStmt = $conn->prepare("SELECT catalog_id, stocks FROM medicine_batches WHERE id = :batch_id");
$batchStmt->execute([':batch_id' => $batch_id]);
$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit();
}

$catalog_id = $batch['catalog_id'];
$original_stocks = $batch['stocks'];

// Validate batch lot number uniqueness (exclude current batch)
$checkStmt = $conn->prepare("
    SELECT id FROM medicine_batches 
    WHERE batch_lot_number = :batch_lot_number 
    AND catalog_id = :catalog_id 
    AND id != :batch_id
");
$checkStmt->execute([
    ':batch_lot_number' => $batch_lot_number,
    ':catalog_id' => $catalog_id,
    ':batch_id' => $batch_id
]);
if ($checkStmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Batch lot number already exists for this medicine']);
    exit();
}

// Determine stock status
$catalogStmt = $conn->prepare("SELECT min_stock FROM medicines_catalog WHERE id = :catalog_id");
$catalogStmt->execute([':catalog_id' => $catalog_id]);
$min_stock = $catalogStmt->fetchColumn();
$stock_status = ($stocks <= 0) ? 'Out of Stock' : (($stocks <= $min_stock) ? 'Low Stock' : 'In Stock');

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

    // Fetch current batch details for comparison
    $currentStmt = $conn->prepare("SELECT * FROM medicine_batches WHERE id = :batch_id");
    $currentStmt->execute([':batch_id' => $batch_id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    // Update batch
    $updateStmt = $conn->prepare("
        UPDATE medicine_batches SET
            batch_lot_number = :batch_lot_number,
            pono = :pono,
            manufacturing_date = :manufacturing_date,
            expiration_date = :expiration_date,
            stocks = :stocks,
            stock_status = :stock_status,
            expiry_status = :expiry_status,
            source = :source
        WHERE id = :batch_id
    ");
    $updateStmt->execute([
        ':batch_lot_number' => $batch_lot_number,
        ':pono' => $pono,
        ':manufacturing_date' => $manufacturing_date,
        ':expiration_date' => $expiration_date,
        ':stocks' => $stocks,
        ':stock_status' => $stock_status,
        ':expiry_status' => $expiry_status,
        ':source' => $source,
        ':batch_id' => $batch_id
    ]);

    // Log changes in medicine_history
    $changes = [];
    if ($current['batch_lot_number'] !== $batch_lot_number) $changes[] = "Batch Lot Number: {$current['batch_lot_number']} to $batch_lot_number";
    if ($current['pono'] !== $pono) $changes[] = "PONO: {$current['pono']} to " . ($pono ?: 'N/A');
    if ($current['manufacturing_date'] !== $manufacturing_date) $changes[] = "Manufacturing Date: {$current['manufacturing_date']} to " . ($manufacturing_date ?: 'N/A');
    if ($current['expiration_date'] !== $expiration_date) $changes[] = "Expiration Date: {$current['expiration_date']} to $expiration_date";
    if ($current['stocks'] !== $stocks) $changes[] = "Stocks: {$current['stocks']} to $stocks";
    if ($current['source'] !== $source) $changes[] = "Source: {$current['source']} to " . ($source ?: 'N/A');

        if (!empty($changes)) {
            $details = "Updated batch: " . implode(', ', $changes);
            $historyStmt = $conn->prepare("INSERT INTO medicine_history (batch_id, action_type, details, performed_by) VALUES (:batch_id, 'update_batch', :details, :performed_by)");
            $historyStmt->execute([
                ':batch_id' => $batch_id,
                ':details' => $details,
                ':performed_by' => $admin_id
            ]);

            // Log in activity_logs
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) 
                VALUES (:admin_id, 'update_batch', :details, :batch_id)
            ");
            $logDetails = "Updated batch (Lot: $batch_lot_number): " . implode('; ', $changes);
            $logStmt->execute([
                ':admin_id' => $admin_id,
                ':details' => $logDetails,
                ':batch_id' => $batch_id
            ]);
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Batch updated successfully']);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error updating batch: ' . $e->getMessage()]);
}

?>