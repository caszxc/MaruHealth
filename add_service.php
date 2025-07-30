<?php
// add_service.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Validate form data
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $serviceTitle = trim($_POST['serviceTitle'] ?? '');
    $serviceDescription = trim($_POST['serviceDescription'] ?? '');
    $serviceIntro = trim($_POST['serviceIntro'] ?? '');
    $serviceNames = $_POST['serviceName'] ?? [];
    $scheduleDays = $_POST['scheduleDay'] ?? [];
    $images = $_FILES['serviceImages'] ?? [];
    $serviceIcon = $_FILES['serviceIcon'] ?? null;

    if (empty($serviceTitle) || empty($serviceDescription)) {
        $_SESSION['error'] = "Title and description are required.";
        header("Location: content_management.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Handle service icon upload
        $iconPath = 'images/placeholder.png'; // Default icon path
        if ($serviceIcon && $serviceIcon['error'] === UPLOAD_ERR_OK) {
            $uploadDir = "images/uploads/service_images/icons/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileType = strtolower(pathinfo($serviceIcon['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (in_array($fileType, $allowedTypes) && $serviceIcon['size'] <= $maxSize) {
                $fileName = uniqid() . '.' . $fileType;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($serviceIcon['tmp_name'], $filePath)) {
                    $iconPath = $filePath;
                }
            }
        }

        // Insert new service
        $stmt = $conn->prepare("INSERT INTO services (name, description, icon_path, intro) VALUES (:name, :description, :icon_path, :intro)");
        $stmt->bindParam(':name', $serviceTitle);
        $stmt->bindParam(':description', $serviceDescription);
        $stmt->bindParam(':icon_path', $iconPath);
        $stmt->bindParam(':intro', $serviceIntro);
        $stmt->execute();
        $serviceId = $conn->lastInsertId();

        // Insert sub-services and schedules
        foreach ($serviceNames as $index => $serviceName) {
            if (!empty($serviceName)) {
                // Insert sub-service
                $subStmt = $conn->prepare("INSERT INTO sub_services (service_id, name) VALUES (:service_id, :name)");
                $subStmt->bindParam(':service_id', $serviceId);
                $subStmt->bindParam(':name', $serviceName);
                $subStmt->execute();
                $subServiceId = $conn->lastInsertId();

                // Insert schedules
                if (!empty($scheduleDays[$index])) {
                    foreach ($scheduleDays[$index] as $day) {
                        if (!empty($day)) {
                            $scheduleStmt = $conn->prepare("INSERT INTO schedules (sub_service_id, day_of_schedule) VALUES (:sub_service_id, :day)");
                            $scheduleStmt->bindParam(':sub_service_id', $subServiceId);
                            $scheduleStmt->bindParam(':day', $day);
                            $scheduleStmt->execute();
                        }
                    }
                }
            }
        }

        // Handle image uploads
        if (!empty($images['name'][0])) {
            $uploadDir = 'Uploads/service_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($images['name'] as $key => $name) {
                if ($images['error'][$key] === UPLOAD_ERR_OK) {
                    $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                    $maxSize = 5 * 1024 * 1024; // 5MB

                    if (in_array($fileType, $allowedTypes) && $images['size'][$key] <= $maxSize) {
                        $fileName = uniqid() . '.' . $fileType;
                        $filePath = $uploadDir . $fileName;

                        if (move_uploaded_file($images['tmp_name'][$key], $filePath)) {
                            $imageStmt = $conn->prepare("INSERT INTO service_images (service_id, image_path) VALUES (:service_id, :image_path)");
                            $imageStmt->bindParam(':service_id', $serviceId);
                            $imageStmt->bindParam(':image_path', $filePath);
                            $imageStmt->execute();
                        }
                    }
                }
            }
        }

        // Commit transaction
        $conn->commit();
        $_SESSION['success'] = "Service added successfully.";
        header("Location: content_management.php");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error adding service: " . $e->getMessage();
        header("Location: content_management.php");
        exit();
    }
} else {
    header("Location: content_management.php");
    exit();
}
?>