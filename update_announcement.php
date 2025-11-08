<?php
//update_announcement.php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: announcements.php"); exit(); }

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php"); exit();
}

$id      = (int)$_POST['announcement_id'];
$title   = trim($_POST['title']);
$content = trim($_POST['content']);
$image   = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'images/uploads/announcement_images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $image = time() . '_' . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image);
}

try {
    if ($image) {
        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, image=? WHERE id=?");
        $stmt->execute([$title, $content, $image, $id]);
    } else {
        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=? WHERE id=?");
        $stmt->execute([$title, $content, $id]);
    }

    // ---- LOG ----
    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'announcement_update', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Updated announcement titled '{$title}'",
        ':target_id'  => $id
    ]);

    $_SESSION['announcement_message'] = "Announcement updated successfully.";
} catch (Exception $e) {
    $_SESSION['announcement_message'] = "Error: " . $e->getMessage();
}

header("Location: announcements.php");
exit();
?>