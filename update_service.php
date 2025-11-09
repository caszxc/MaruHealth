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
    $doctorNames = $_POST['doctorName'] ?? [];
    $imagesToDelete = !empty($_POST['imagesToDelete']) ? json_decode($_POST['imagesToDelete'], true) : [];

    // Validate required fields
    if (empty($serviceId) || empty($title) || empty($description)) {
        $_SESSION['service_message'] = "Invalid request.";
        header("Location: service_management.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // 1. Fetch existing sub-services and schedules for comparison
        $existingSubStmt = $conn->prepare("
            SELECT 
                ss.id, 
                ss.name, 
                ss.doctor_name,
                GROUP_CONCAT(s.day_of_schedule ORDER BY s.day_of_schedule SEPARATOR ',') AS days
            FROM sub_services ss
            LEFT JOIN schedules s ON ss.id = s.sub_service_id
            WHERE ss.service_id = :service_id
            GROUP BY ss.id
        ");
        $existingSubStmt->execute(['service_id' => $serviceId]);
        $existingSubServices = $existingSubStmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert existing sub-services to a comparable format
        $existingSubServiceData = [];
        foreach ($existingSubServices as $sub) {
            $existingSubServiceData[$sub['name']] = [
                'doctor_name' => $sub['doctor_name'],
                'days' => $sub['days'] ? explode(',', $sub['days']) : []
            ];
        }

        // 2. Prepare new sub-service data for comparison
        $newSubServiceData = [];
        $hasSubServices = false;
        $announcementContent = "Dear Barangay Marulas Residents,\n\n";
        $announcementContent .= "We are committed to providing the best healthcare services at the 3S Health Station. To better serve you, we have made some updates to our {$title} service schedules. These changes include new or revised sub-services, updated availability days, and assigned doctors to ensure smoother access to medical care.\n\n";
        $announcementContent .= "Here are the details of the updates:\n\n";

        foreach ($subServiceNames as $index => $subName) {
            if (trim($subName) === '') continue;
            $hasSubServices = true;
            $doctorName = !empty($doctorNames[$index]) ? trim($doctorNames[$index]) : null;
            $days = !empty($scheduleDays[$index]) ? array_filter(array_map('trim', $scheduleDays[$index]), fn($day) => !empty($day)) : [];
            $newSubServiceData[$subName] = [
                'doctor_name' => $doctorName,
                'days' => $days
            ];

            // Add to announcement content
            $daysList = !empty($days) ? implode(', ', $days) : 'TBD';
            $doctor = $doctorName ?? 'TBD';
            $announcementContent .= "Sub-Service: {$subName}\n";
            $announcementContent .= "Doctor: {$doctor}\n";
            $announcementContent .= "Schedule: {$daysList}\n";
        }

        // 3. Check if sub-services, schedules, or doctors have changed
        $subServicesChanged = false;

        // Check for new or modified sub-services
        foreach ($newSubServiceData as $name => $data) {
            if (!isset($existingSubServiceData[$name])) {
                // New sub-service
                $subServicesChanged = true;
                break;
            }
            // Check for changes in doctor or schedules
            $existing = $existingSubServiceData[$name];
            if ($data['doctor_name'] !== $existing['doctor_name'] ||
                json_encode($data['days']) !== json_encode($existing['days'])) {
                $subServicesChanged = true;
                break;
            }
        }

        // Check for deleted sub-services
        foreach ($existingSubServiceData as $name => $data) {
            if (!isset($newSubServiceData[$name])) {
                $subServicesChanged = true;
                break;
            }
        }

        // 4. Update service details
        $stmt = $conn->prepare("UPDATE services SET name = :name, description = :description, intro = :intro WHERE id = :id");
        $stmt->execute([
            'name' => $title,
            'description' => $description,
            'intro' => $intro,
            'id' => $serviceId
        ]);

        // 5. Handle service icon upload
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

        // 6. Delete existing sub-services and schedules
        $deleteSchedules = $conn->prepare("DELETE FROM schedules WHERE sub_service_id IN (SELECT id FROM sub_services WHERE service_id = :service_id)");
        $deleteSchedules->execute(['service_id' => $serviceId]);

        $deleteSubServices = $conn->prepare("DELETE FROM sub_services WHERE service_id = :service_id");
        $deleteSubServices->execute(['service_id' => $serviceId]);

        // 7. Re-insert sub-services and schedules
        $insertSub = $conn->prepare("INSERT INTO sub_services (service_id, name, doctor_name) VALUES (:service_id, :name, :doctor_name)");
        $insertSchedule = $conn->prepare("INSERT INTO schedules (sub_service_id, day_of_schedule) VALUES (:sub_service_id, :day)");

        foreach ($subServiceNames as $index => $subName) {
            if (trim($subName) === '') continue;

            $doctorName = !empty($doctorNames[$index]) ? trim($doctorNames[$index]) : null;
            $insertSub->execute([
                'service_id' => $serviceId,
                'name' => $subName,
                'doctor_name' => $doctorName,
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

        // 8. Insert announcement only if sub-services changed and there are valid sub-services
        if ($subServicesChanged && $hasSubServices) {
            $announcementTitle = "Updated {$title} Service Schedules";
            $adminId = $_SESSION['admin_id'];
            $announcementStmt = $conn->prepare("INSERT INTO announcements (title, content, admin_id, status) VALUES (:title, :content, :admin_id, 'active')");
            $announcementStmt->bindParam(':title', $announcementTitle);
            $announcementStmt->bindParam(':content', $announcementContent);
            $announcementStmt->bindParam(':admin_id', $adminId);
            $announcementStmt->execute();
        }

        // 9. Handle image deletions
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

        // 10. Handle multiple image uploads
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

        // Log the service update
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'service_update', :details, :target_id)
        ");
        $details = "Updated service titled '{$title}'";
        $logStmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => $details,
            ':target_id' => $serviceId
        ]);

        $_SESSION['service_message'] = "Service updated successfully.";
        header("Location: service_management.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['service_message'] = "Update failed: " . $e->getMessage();
        header("Location: service_management.php");
        exit();
    }
}
?>