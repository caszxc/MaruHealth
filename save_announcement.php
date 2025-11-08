<?php
//save_announcement.php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: announcements.php"); exit(); }

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php"); exit();
}

$title    = trim($_POST['title']);
$content  = trim($_POST['content']);
$admin_id = $_SESSION['admin_id'];
$imageName = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'images/uploads/announcement_images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $imageName = time() . '_' . basename($_FILES['image']['name']);
    $target    = $uploadDir . $imageName;
    move_uploaded_file($_FILES['image']['tmp_name'], $target);
}

try {
    $stmt = $conn->prepare("
        INSERT INTO announcements (title, content, image, admin_id, created_at, status)
        VALUES (?, ?, ?, ?, NOW(), 'active')
    ");
    $stmt->execute([$title, $content, $imageName, $admin_id]);

    $annId = $conn->lastInsertId();

    // ---- LOG ----
    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'announcement_create', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $admin_id,
        ':details'    => "Created announcement titled '{$title}'",
        ':target_id'  => $annId
    ]);

    $_SESSION['announcement_message'] = "Announcement posted successfully.";
} catch (Exception $e) {
    $_SESSION['announcement_message'] = "Error: " . $e->getMessage();
}

header("Location: announcements.php");
exit();
?>