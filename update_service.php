<?php
// update_service.php 
session_start();
require_once "config.php";

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $serviceId = $_POST['serviceId'] ?? '';
    $title = $_POST['serviceTitle'] ?? '';
    $description = $_POST['serviceDescription'] ?? '';
    $intro = $_POST['serviceIntro'] ?? '';
    $subServiceNames = $_POST['serviceName'] ?? [];
    $scheduleDays = $_POST['scheduleDay'] ?? [];
    $imagesToDelete = !empty($_POST['imagesToDelete']) ? json_decode($_POST['imagesToDelete'], true) : [];

    // Validate required fields
    if (empty($serviceId) || empty($title) || empty($description)) {
        $_SESSION['error'] = "Required fields are missing.";
        header("Location: content_management.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // 1. Update service details
        $stmt = $conn->prepare("UPDATE services SET name = :name, description = :description, intro = :intro WHERE id = :id");
        $stmt->execute([
            'name' => $title,
            'description' => $description,
            'intro' => $intro,
            'id' => $serviceId
        ]);

        // 2. Handle service icon upload
        if (!empty($_FILES['serviceIcon']['name'])) {
            $uploadDir = "images/uploads/service_images/icons/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $fileName = basename($_FILES['serviceIcon']['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $uniqueFileName = uniqid() . '_' . $serviceId . '.' . $fileExtension;
            $targetFilePath = $uploadDir . $uniqueFileName;

            // Validate file type and size (5MB max)
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception("Invalid icon file type. Allowed types: jpg, jpeg, png, gif.");
            }
            if ($_FILES['serviceIcon']['size'] > 5242880) {
                throw new Exception("Icon file is too large. Maximum size is 5MB.");
            }

            // Move uploaded file
            if (move_uploaded_file($_FILES['serviceIcon']['tmp_name'], $targetFilePath)) {
                // Update icon_path in database
                $stmt = $conn->prepare("UPDATE services SET icon_path = :icon_path WHERE id = :id");
                $stmt->execute([
                    'icon_path' => $targetFilePath,
                    'id' => $serviceId
                ]);

                // Delete old icon if it exists
                $oldIconStmt = $conn->prepare("SELECT icon_path FROM services WHERE id = :id");
                $oldIconStmt->execute(['id' => $serviceId]);
                $oldIcon = $oldIconStmt->fetchColumn();
                if ($oldIcon && file_exists($oldIcon) && $oldIcon !== $targetFilePath) {
                    unlink($oldIcon);
                }
            } else {
                throw new Exception("Failed to upload service icon.");
            }
        }

        // 3. Delete existing sub-services and schedules
        $deleteSchedules = $conn->prepare("DELETE FROM schedules WHERE sub_service_id IN (SELECT id FROM sub_services WHERE service_id = :service_id)");
        $deleteSchedules->execute(['service_id' => $serviceId]);

        $deleteSubServices = $conn->prepare("DELETE FROM sub_services WHERE service_id = :service_id");
        $deleteSubServices->execute(['service_id' => $serviceId]);

        // 4. Re-insert sub-services and schedules
        $insertSub = $conn->prepare("INSERT INTO sub_services (service_id, name) VALUES (:service_id, :name)");
        $insertSchedule = $conn->prepare("INSERT INTO schedules (sub_service_id, day_of_schedule) VALUES (:sub_service_id, :day)");

        foreach ($subServiceNames as $index => $subName) {
            if (trim($subName) === '') continue; // Skip empty sub-service names

            $insertSub->execute([
                'service_id' => $serviceId,
                'name' => $subName
            ]);

            $subServiceId = $conn->lastInsertId();

            if (!empty($scheduleDays[$index]) && is_array($scheduleDays[$index])) {
                foreach ($scheduleDays[$index] as $day) {
                    if (trim($day) !== '') {
                        $insertSchedule->execute([
                            'sub_service_id' => $subServiceId,
                            'day' => $day
                        ]);
                    }
                }
            }
        }

        // 5. Handle image deletions
        if (!empty($imagesToDelete)) {
            $deleteImageStmt = $conn->prepare("DELETE FROM service_images WHERE service_id = :service_id AND image_path = :image_path");
            foreach ($imagesToDelete as $imagePath) {
                $deleteImageStmt->execute([
                    'service_id' => $serviceId,
                    'image_path' => $imagePath
                ]);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }

        // 6. Handle multiple image uploads
        if (!empty($_FILES['serviceImages']['name'][0])) {
            $uploadDir = "images/uploads/service_images/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $insertImage = $conn->prepare("INSERT INTO service_images (service_id, image_path) VALUES (:service_id, :image_path)");

            for ($i = 0; $i < count($_FILES['serviceImages']['name']); $i++) {
                $fileName = basename($_FILES['serviceImages']['name'][$i]);
                if (empty($fileName)) continue;

                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $uniqueFileName = uniqid() . '_' . $serviceId . '_' . $i . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $uniqueFileName;

                if (!in_array($fileExtension, $allowedExtensions)) {
                    error_log("Invalid file type: $fileName");
                    continue;
                }
                if ($_FILES['serviceImages']['size'][$i] > 5242880) {
                    error_log("Image file too large: $fileName");
                    continue;
                }

                if (move_uploaded_file($_FILES['serviceImages']['tmp_name'][$i], $targetFilePath)) {
                    $insertImage->execute([
                        'service_id' => $serviceId,
                        'image_path' => $targetFilePath
                    ]);
                } else {
                    error_log("Failed to upload image: $fileName");
                }
            }
        }

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Service updated successfully!";
        header("Location: content_management.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
        header("Location: content_management.php");
        exit();
    }
}
?>