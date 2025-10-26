<?php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$dependent_id = (int)$_GET['id'];
$primary_user_id = $_SESSION['user_id'];

try {
    // Verify the pending dependent belongs to the primary user
    $stmt = $conn->prepare("SELECT 1 FROM pending_dependent_relationships WHERE dependent_user_id = :dependent_id AND primary_user_id = :primary_user_id");
    $stmt->execute([':dependent_id' => $dependent_id, ':primary_user_id' => $primary_user_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pending dependent not found or not associated with your account']);
        exit();
    }

    // Begin transaction
    $conn->beginTransaction();

    // Delete from pending_dependent_relationships
    $stmt = $conn->prepare("DELETE FROM pending_dependent_relationships WHERE dependent_user_id = :dependent_id AND primary_user_id = :primary_user_id");
    $stmt->execute([':dependent_id' => $dependent_id, ':primary_user_id' => $primary_user_id]);

    // Delete from pending_users
    $stmt = $conn->prepare("DELETE FROM pending_users WHERE id = :dependent_id");
    $stmt->execute([':dependent_id' => $dependent_id]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Pending dependent cancelled successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error cancelling pending dependent: ' . $e->getMessage()]);
}
?>