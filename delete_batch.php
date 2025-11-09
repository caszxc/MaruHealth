<?php
// delete_batch.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if batch_id is provided
if (!isset($_POST['batch_id']) || empty($_POST['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'No batch ID provided']);
    exit();
}

$batch_id = trim($_POST['batch_id']);
$admin_id = $_SESSION['admin_id'];

try {
    $conn->beginTransaction();

    // Fetch batch + catalog details
    $currentStmt = $conn->prepare("
        SELECT mb.*, mc.generic_name, mc.id AS catalog_id 
        FROM medicine_batches mb 
        JOIN medicines_catalog mc ON mb.catalog_id = mc.id 
        WHERE mb.id = :batch_id
    ");
    $currentStmt->execute([':batch_id' => $batch_id]);
    $batch = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new PDOException("Batch not found.");
    }

    // === 1. Log in medicine_history (NOW WITH catalog_id) ===
    $details = "Deleted batch: Lot {$batch['batch_lot_number']}, Medicine: {$batch['generic_name']}, Stocks: {$batch['stocks']}, Source: " . ($batch['source'] ?: 'N/A');
    
    $historyStmt = $conn->prepare("
        INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by) 
        VALUES (:catalog_id, :batch_id, 'delete_batch', :details, :performed_by)
    ");
    $historyStmt->execute([
        ':catalog_id' => $batch['catalog_id'],
        ':batch_id'   => $batch_id,
        ':details'    => $details,
        ':performed_by' => $admin_id
    ]);

    // === 2. Log in activity_logs ===
    $logDetails = "Deleted medicine batch: {$batch['generic_name']} (Lot: {$batch['batch_lot_number']}, Expiry: {$batch['expiration_date']}, Stocks: {$batch['stocks']})";
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) 
        VALUES (:admin_id, 'delete_batch', :details, :batch_id)
    ");
    $activityStmt->execute([
        ':admin_id' => $admin_id,
        ':details'  => $logDetails,
        ':batch_id' => $batch_id
    ]);

    // === 3. Delete the batch ===
    $deleteStmt = $conn->prepare("DELETE FROM medicine_batches WHERE id = :batch_id");
    $deleteStmt->execute([':batch_id' => $batch_id]);

    $conn->commit();
    $_SESSION['batch_message'] = "Batch deleted successfully.";
    echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
    
} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['batch_message'] = "Error deleting batch: " . $e->getMessage();
    echo json_encode(['success' => false, 'message' => 'Error deleting batch: ' . $e->getMessage()]);
}
?>