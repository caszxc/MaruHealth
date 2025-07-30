<?php
session_start();
require 'config.php';

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $announcementId = $_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $imagePath = null;

    // Check if a new image was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/uploads/announcement_images/';
        $fileTmp = $_FILES['image']['tmp_name'];
        $fileName = basename($_FILES['image']['name']);
        $targetFile = $uploadDir . time() . '_' . $fileName;

        // Move the uploaded file to the destination folder
        if (move_uploaded_file($fileTmp, $targetFile)) {
            $imagePath = basename($targetFile);
        }
    }

    try {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, image = ? WHERE id = ?");
            $stmt->execute([$title, $content, $imagePath, $announcementId]);
        } else {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $announcementId]);
        }

        header("Location: announcements.php");
        exit();
    } catch (PDOException $e) {
        echo "Error updating announcement: " . $e->getMessage();
    }
} else {
    header("Location: announcements.php");
    exit();
}
