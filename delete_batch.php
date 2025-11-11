<?php
// delete_batch.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['batch_id']) || empty($_POST['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'No batch ID provided']);
    exit();
}

$batch_id = (int)$_POST['batch_id'];
$admin_id = $_SESSION['admin_id'];

try {
    $conn->beginTransaction();

    // 1. Get batch + catalog
    $batchStmt = $conn->prepare("
        SELECT mb.*, mc.generic_name 
        FROM medicine_batches mb
        JOIN medicines_catalog mc ON mb.catalog_id = mc.id
        WHERE mb.id = :batch_id
    ");
    $batchStmt->execute([':batch_id' => $batch_id]);
    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new Exception("Batch not found.");
    }

    // 2. CHECK FOR ANY USAGE
    $usageCheck = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM medicine_distributions WHERE batch_id = :batch_id) AS distributions,
            (SELECT COUNT(*) FROM medicine_disposals WHERE batch_id = :batch_id) AS disposals,
            (SELECT COUNT(*) FROM medicine_history 
             WHERE batch_id = :batch_id 
               AND action_type NOT IN ('add_batch', 'delete_batch', 'update_batch')) AS other_actions
    ");
    $usageCheck->execute([':batch_id' => $batch_id]);
    $usage = $usageCheck->fetch(PDO::FETCH_ASSOC);

    $hasUsage = ($usage['distributions'] > 0 || $usage['disposals'] > 0 || $usage['other_actions'] > 0);

    if ($hasUsage) {
        throw new Exception("Cannot delete batch: It has been distributed, disposed, or adjusted. Use 'Dispose' instead.");
    }

    // Optional: Only allow if no stock changes (pure entry error)
    $initialStockCheck = $conn->prepare("
        SELECT details FROM medicine_history 
        WHERE batch_id = :batch_id AND action_type = 'add_batch'
        ORDER BY id ASC LIMIT 1
    ");
    $initialStockCheck->execute([':batch_id' => $batch_id]);
    $initialLog = $initialStockCheck->fetchColumn();

    if ($initialLog && strpos($initialLog, "Stocks: {$batch['stocks']}") === false) {
        throw new Exception("Cannot delete: Stock has been modified.");
    }

    // === SAFE TO DELETE ===
    // Log deletion
    $details = "Deleted unused batch: Batch/Lot no: #{$batch['batch_lot_number']}, Medicine: {$batch['generic_name']}, Stocks: {$batch['stocks']}";
    
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

    $activityStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) 
        VALUES (:admin_id, 'delete_batch', :details, :batch_id)
    ");
    $activityStmt->execute([
        ':admin_id' => $admin_id,
        ':details'  => $details,
        ':batch_id' => $batch_id
    ]);

    // Finally delete
    $deleteStmt = $conn->prepare("DELETE FROM medicine_batches WHERE id = :batch_id");
    $deleteStmt->execute([':batch_id' => $batch_id]);

    $conn->commit();
    $_SESSION['batch_message'] = "Unused batch deleted.";
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    $error = $e->getMessage();
    $_SESSION['batch_message'] = $error;
    echo json_encode(['success' => false, 'message' => $error]);
}
?>