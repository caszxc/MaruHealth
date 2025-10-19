<?php
session_start(); // Add session_start to access admin_id
require 'config.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    // Check if user is logged in as super admin or admin
    if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
        echo json_encode(["success" => false, "message" => "Unauthorized access"]);
        exit();
    }

    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $newStatus = ($action === 'unarchive') ? 'active' : 'archived';

    try {
        $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
        if ($stmt->execute([$newStatus, $id])) {
            // Log the announcement toggle
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
                VALUES (:admin_id, 'announcement_toggle', :details, :target_id)
            ");
            $details = "Changed announcement ID {$id} status to {$newStatus}";
            $logStmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':details' => $details,
                ':target_id' => $id
            ]);

            echo json_encode(["success" => true, "message" => "Announcement status updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update announcement status"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request parameters"]);
}
?>