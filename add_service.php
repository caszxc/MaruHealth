<?php
//add_service.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    $_SESSION['service_message'] = "Unauthorized access.";
    header("Location: service_management.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['service_message'] = "Invalid request.";
    header("Location: service_management.php");
    exit();
}

$serviceTitle       = trim($_POST['serviceTitle'] ?? '');
$serviceDescription = trim($_POST['serviceDescription'] ?? '');
$serviceIntro       = trim($_POST['serviceIntro'] ?? '');
$serviceNames       = $_POST['serviceName'] ?? [];
$scheduleDays       = $_POST['scheduleDay'] ?? [];
$doctorNames        = $_POST['doctorName'] ?? [];
$images             = $_FILES['serviceImages'] ?? [];
$serviceIcon        = $_FILES['serviceIcon'] ?? null;

if (empty($serviceTitle) || empty($serviceDescription)) {
    $_SESSION['service_message'] = "Title and description are required.";
    header("Location: service_management.php");
    exit();
}

try {
    $conn->beginTransaction();

    // --- Icon upload ---
    $iconPath = 'images/uploads/service_images/icons/icon-placeholder.png';
    if ($serviceIcon && $serviceIcon['error'] === UPLOAD_ERR_OK) {
        $dir = "images/uploads/service_images/icons/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($serviceIcon['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array($ext, $allowed) && $serviceIcon['size'] <= 5*1024*1024) {
            $file = uniqid() . '.' . $ext;
            if (move_uploaded_file($serviceIcon['tmp_name'], $dir . $file)) {
                $iconPath = $dir . $file;
            }
        }
    }

    // --- Insert service ---
    $stmt = $conn->prepare("INSERT INTO services (name, description, icon_path, intro) VALUES (?, ?, ?, ?)");
    $stmt->execute([$serviceTitle, $serviceDescription, $iconPath, $serviceIntro]);
    $serviceId = $conn->lastInsertId();

    // --- Sub-services & schedules ---
    $hasSub = false;
    $announcement = "Dear Barangay Marulas Residents,\n\nWe have added new schedules for **{$serviceTitle}**:\n\n";
    foreach ($serviceNames as $i => $name) {
        if (empty(trim($name))) continue;
        $hasSub = true;
        $doc = !empty($doctorNames[$i]) ? trim($doctorNames[$i]) : null;
        $subStmt = $conn->prepare("INSERT INTO sub_services (service_id, name, doctor_name) VALUES (?, ?, ?)");
        $subStmt->execute([$serviceId, $name, $doc]);
        $subId = $conn->lastInsertId();

        $days = [];
        if (!empty($scheduleDays[$i]) && is_array($scheduleDays[$i])) {
            foreach ($scheduleDays[$i] as $day) {
                if (trim($day) !== '') {
                    $sched = $conn->prepare("INSERT INTO schedules (sub_service_id, day_of_schedule) VALUES (?, ?)");
                    $sched->execute([$subId, $day]);
                    $days[] = $day;
                }
            }
        }
        $announcement .= "- **{$name}** (Dr. {$doc}): " . (!empty($days) ? implode(', ', $days) : 'TBD') . "\n";
    }

    // --- Announcement ---
    if ($hasSub) {
        $annTitle = "New {$serviceTitle} Service";
        $annStmt = $conn->prepare("INSERT INTO announcements (title, content, admin_id, status) VALUES (?, ?, ?, 'active')");
        $annStmt->execute([$annTitle, $announcement, $_SESSION['admin_id']]);
    }

    // --- Images ---
    if (!empty($images['name'][0])) {
        $dir = "images/uploads/service_images/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        foreach ($images['name'] as $k => $name) {
            if ($images['error'][$k] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];
            if (in_array($ext, $allowed) && $images['size'][$k] <= 5*1024*1024) {
                $file = uniqid() . '.' . $ext;
                if (move_uploaded_file($images['tmp_name'][$k], $dir . $file)) {
                    $imgStmt = $conn->prepare("INSERT INTO service_images (service_id, image_path) VALUES (?, ?)");
                    $imgStmt->execute([$serviceId, $dir . $file]);
                }
            }
        }
    }

    $conn->commit();

    // --- Log ---
    $log = $conn->prepare("INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) VALUES (?, 'service_create', ?, ?)");
    $log->execute([$_SESSION['admin_id'], "Created service '{$serviceTitle}'", $serviceId]);

    $_SESSION['service_message'] = "Service added successfully.";
    header("Location: service_management.php");
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['service_message'] = "Error: " . $e->getMessage();
    header("Location: service_management.php");
    exit();
}
?>