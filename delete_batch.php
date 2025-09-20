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
    // Begin transaction
    $conn->beginTransaction();

    // Delete the batch from medicine_batches table
    $deleteStmt = $conn->prepare("DELETE FROM medicine_batches WHERE id = :batch_id");
    $deleteStmt->execute([':batch_id' => $batch_id]);

    // Check if any rows were affected
    if ($deleteStmt->rowCount() > 0) {
        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
    }
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting batch: ' . $e->getMessage()]);
}
?>