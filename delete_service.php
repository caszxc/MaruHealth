<?php
// delete_service.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo "Unauthorized access.";
    exit();
}

// Check if serviceId is provided
if (!isset($_POST['serviceId']) || empty($_POST['serviceId'])) {
    echo "No service selected for deletion.";
    exit();
}

$serviceId = (int)$_POST['serviceId'];

try {
    // Begin transaction
    $conn->beginTransaction();

    // Fetch the icon path and service images before deletion
    $stmt = $conn->prepare("SELECT icon_path FROM services WHERE id = :serviceId");
    $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch all associated service images
    $imageStmt = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = :serviceId");
    $imageStmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
    $imageStmt->execute();
    $serviceImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete the service (sub-services, schedules, and images are automatically deleted due to ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM services WHERE id = :serviceId");
    $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the icon file from the filesystem
    if ($service && !empty($service['icon_path']) && file_exists($service['icon_path']) && $service['icon_path'] !== 'images/placeholder.png') {
        unlink($service['icon_path']);
    }

    // Delete service images from the filesystem
    foreach ($serviceImages as $image) {
        if (!empty($image['image_path']) && file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }
    }

    // Commit transaction
    $conn->commit();

    echo "Service and associated files deleted successfully.";
} catch (PDOException $e) {
    // Roll back transaction on error
    $conn->rollBack();
    echo "Error deleting service: " . $e->getMessage();
}

$conn = null;
?>