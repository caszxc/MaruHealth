<?php
session_start();
require_once "config.php"; // Include database connection

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
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

        // Delete from medicines_catalog table
        $stmt = $conn->prepare("DELETE FROM medicines_catalog WHERE id = ?");
        $stmt->execute([$id]);

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