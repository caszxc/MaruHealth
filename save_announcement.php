<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if user is logged in as super admin or admin
    if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
        header("Location: admin_dashboard.php");
        exit();
    }

    $title = htmlspecialchars($_POST['title']);
    $content = htmlspecialchars($_POST['content']);
    $admin_id = $_SESSION['admin_id']; // Get the logged-in admin's ID
    $imageName = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/uploads/announcement_images/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imageName = basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $targetFile);
    }

    try {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, image, admin_id, created_at, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
        
        if ($stmt->execute([$title, $content, $imageName, $admin_id])) {
            header("Location: announcements.php");
            exit();
        } else {
            echo "Error saving post.";
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
}
?>