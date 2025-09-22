<?php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
    $admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid medicine ID.']);
        exit();
    }

    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Fetch medicine details for history logging
        $currentStmt = $conn->prepare("SELECT * FROM medicines_catalog WHERE id = ?");
        $currentStmt->execute([$id]);
        $medicine = $currentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$medicine) {
            throw new PDOException("Medicine not found.");
        }

        // Log deletion in medicine_history before deletion
        $details = "Deleted medicine: {$medicine['generic_name']}" . ($medicine['brand_name'] ? " ({$medicine['brand_name']})" : "") . ", Dosage: {$medicine['dosage']} {$medicine['dosage_form']}";
        $historyStmt = $conn->prepare("INSERT INTO medicine_history (catalog_id, action_type, details, performed_by) VALUES (:catalog_id, 'delete_catalog', :details, :performed_by)");
        $historyStmt->execute([
            ':catalog_id' => $id,
            ':details' => $details,
            ':performed_by' => $admin_id
        ]);

        // Delete from medicines_catalog
        $deleteStmt = $conn->prepare("DELETE FROM medicines_catalog WHERE id = ?");
        $deleteStmt->execute([$id]);

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully.']);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error deleting medicine: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>