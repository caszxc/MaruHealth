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
    $doctorNames = $_POST['doctorName'] ?? [];
    $images = $_FILES['serviceImages'] ?? [];
    $serviceIcon = $_FILES['serviceIcon'] ?? null;

    if (empty($serviceTitle) || empty($serviceDescription)) {
        $_SESSION['error'] = "Title and description are required.";
        header("Location: service_management.php");
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

        // Initialize announcement content only if sub-services exist
        $hasSubServices = false;
        $announcementContent = "Dear Barangay Marulas Residents,\n\n";
        $announcementContent .= "We are committed to providing the best healthcare services at the 3S Health Station. To better serve you, we have made some updates to our {$serviceTitle} service schedules. These changes include new or revised sub-services, updated availability days, and assigned doctors to ensure smoother access to medical care.\n\n";
        $announcementContent .= "Here are the details of the updates:\n\n";

        // Insert sub-services and schedules
        foreach ($serviceNames as $index => $serviceName) {
            if (!empty($serviceName)) {
                $hasSubServices = true; // Mark that we have at least one valid sub-service
                // Insert sub-service with doctor_name
                $doctorName = !empty($doctorNames[$index]) ? trim($doctorNames[$index]) : null;
                $subStmt = $conn->prepare("INSERT INTO sub_services (service_id, name, doctor_name) VALUES (:service_id, :name, :doctor_name)");
                $subStmt->bindParam(':service_id', $serviceId);
                $subStmt->bindParam(':name', $serviceName);
                $subStmt->bindParam(':doctor_name', $doctorName, PDO::PARAM_STR | PDO::PARAM_NULL);
                $subStmt->execute();
                $subServiceId = $conn->lastInsertId();

                // Collect schedule days
                $days = [];
                if (!empty($scheduleDays[$index])) {
                    foreach ($scheduleDays[$index] as $day) {
                        if (!empty($day)) {
                            $scheduleStmt = $conn->prepare("INSERT INTO schedules (sub_service_id, day_of_schedule) VALUES (:sub_service_id, :day)");
                            $scheduleStmt->bindParam(':sub_service_id', $subServiceId);
                            $scheduleStmt->bindParam(':day', $day);
                            $scheduleStmt->execute();
                            $days[] = $day;
                        }
                    }
                }

                // Add sub-service details to announcement
                $daysList = !empty($days) ? implode(', ', $days) : 'TBD';
                $doctor = $doctorName ?? 'TBD';
                $announcementContent .= "Sub-Service: {$serviceName}\n";
                $announcementContent .= "Doctor: {$doctor}\n";
                $announcementContent .= "Schedule: {$daysList}\n";
                $announcementContent .= "Notes: Available for general consultations and minor illnesses. Walk-ins welcome from 8:00 AM to 4:00 PM.\n\n";
            }
        }

        // Insert announcement into announcements table only if sub-services exist
        if ($hasSubServices) {
            $announcementTitle = "New {$serviceTitle} Service Schedules";
            $adminId = $_SESSION['admin_id'];
            $announcementStmt = $conn->prepare("INSERT INTO announcements (title, content, admin_id, status) VALUES (:title, :content, :admin_id, 'active')");
            $announcementStmt->bindParam(':title', $announcementTitle);
            $announcementStmt->bindParam(':content', $announcementContent);
            $announcementStmt->bindParam(':admin_id', $adminId);
            $announcementStmt->execute();
        }

        // Handle image uploads
        if (!empty($images['name'][0])) {
            $uploadDir = 'images/uploads/service_images/';
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
        header("Location: service_management.php");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error adding service: " . $e->getMessage();
        header("Location: service_management.php");
        exit();
    }
} else {
    header("Location: service_management.php");
    exit();
}
?>