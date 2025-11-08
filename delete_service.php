<?php
// delete_service.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

if (!isset($_POST['serviceId'])) {
    echo json_encode(["success" => false, "message" => "No service ID"]);
    exit();
}

$serviceId = (int)$_POST['serviceId'];

try {
    $conn->beginTransaction();

    // Fetch the service title (name) before deletion for logging and messaging
    $titleStmt = $conn->prepare("SELECT name FROM services WHERE id = ?");
    $titleStmt->execute([$serviceId]);
    $serviceTitle = $titleStmt->fetchColumn();

    if (!$serviceTitle) {
        throw new Exception("Service not found.");
    }

    // Fetch files to delete
    $iconStmt = $conn->prepare("SELECT icon_path FROM services WHERE id = ?");
    $iconStmt->execute([$serviceId]);
    $icon = $iconStmt->fetchColumn();

    $imgStmt = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = ?");
    $imgStmt->execute([$serviceId]);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete service (cascades to sub_services, schedules, service_images via ON DELETE CASCADE if set)
    $del = $conn->prepare("DELETE FROM services WHERE id = ?");
    $del->execute([$serviceId]);

    // Delete associated files
    if ($icon && file_exists($icon) && $icon !== 'images/uploads/service_images/icons/icon-placeholder.png') {
        unlink($icon);
    }
    foreach ($images as $img) {
        if (file_exists($img)) {
            unlink($img);
        }
    }

    $conn->commit();

    // Log deletion using the service title
    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) 
        VALUES (?, 'service_delete', ?, ?)
    ");
    $logDetails = "Deleted service titled '{$serviceTitle}'";
    $log->execute([$_SESSION['admin_id'], $logDetails, $serviceId]);

    // Success message with title
    $successMessage = "Service '{$serviceTitle}' deleted successfully.";
    $_SESSION['service_message'] = $successMessage;

    echo json_encode([
        "success" => true, 
        "message" => $successMessage
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    $errorMessage = "Error: " . $e->getMessage();
    $_SESSION['service_message'] = $errorMessage;
    echo json_encode([
        "success" => false, 
        "message" => $errorMessage
    ]);
}
?>