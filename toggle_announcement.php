<?php
session_start();
require 'config.php';

if (isset($_GET['id']) && isset($_GET['action'])) {

    // --------------------------------------------------------------
    // 1. Authorization
    // --------------------------------------------------------------
    if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
        echo json_encode(["success" => false, "message" => "Unauthorized access"]);
        exit();
    }

    $id       = (int)$_GET['id'];
    $action   = $_GET['action'];
    $newStatus = ($action === 'unarchive') ? 'active' : 'archived';
    $oldStatus = $newStatus === 'active' ? 'archived' : 'active';

    try {
        // --------------------------------------------------------------
        // 2. Get the announcement title (once, before we change it)
        // --------------------------------------------------------------
        $titleStmt = $conn->prepare("SELECT title FROM announcements WHERE id = ?");
        $titleStmt->execute([$id]);
        $row   = $titleStmt->fetch(PDO::FETCH_ASSOC);
        $title = $row['title'] ?? 'Untitled';

        // --------------------------------------------------------------
        // 3. Toggle the status
        // --------------------------------------------------------------
        $toggleStmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
        $toggleStmt->execute([$newStatus, $id]);

        // --------------------------------------------------------------
        // 4. Log the action – now with the **title**
        // --------------------------------------------------------------
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'announcement_toggle', :details, :target_id)
        ");

        $details = "Changed announcement \"{$title}\" from {$oldStatus} to {$newStatus}.";
        $logStmt->execute([
            ':admin_id'   => $_SESSION['admin_id'],
            ':details'    => $details,
            ':target_id'  => $id
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Announcement status updated successfully"
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid request parameters"]);
}
?>