<?php
session_start();
require_once "config.php";

// Check if user is logged in as staff or super_admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['staff', 'super_admin'])) {
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

    // Fetch batch details for history logging
    $currentStmt = $conn->prepare("SELECT * FROM medicine_batches WHERE id = :batch_id");
    $currentStmt->execute([':batch_id' => $batch_id]);
    $batch = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new PDOException("Batch not found.");
    }

    // Log deletion in medicine_history
    $details = "Deleted batch: Lot {$batch['batch_lot_number']}, Stocks: {$batch['stocks']}, Source: " . ($batch['source'] ?: 'N/A');
    $historyStmt = $conn->prepare("INSERT INTO medicine_history (batch_id, action_type, details, performed_by) VALUES (:batch_id, 'delete_batch', :details, :performed_by)");
    $historyStmt->execute([
        ':batch_id' => $batch_id,
        ':details' => $details,
        ':performed_by' => $admin_id
    ]);

    // Delete the batch
    $deleteStmt = $conn->prepare("DELETE FROM medicine_batches WHERE id = :batch_id");
    $deleteStmt->execute([':batch_id' => $batch_id]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting batch: ' . $e->getMessage()]);
}
?>