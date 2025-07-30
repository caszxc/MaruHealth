<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if a file was uploaded
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
    $fileName = $_FILES['profile_photo']['name'];
    $fileSize = $_FILES['profile_photo']['size'];
    $fileType = $_FILES['profile_photo']['type'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        die("Invalid file type.");
    }

    $newFileName = "profile_" . $user_id . "." . $fileExtension;
    $uploadDir = "images/uploads/profile_pictures/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destPath = $uploadDir . $newFileName;
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        // Save file path in database (optional, if you store it)
        $stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :id");
        $stmt->execute([
            ':profile_picture' => $destPath,
            ':id' => $user_id
        ]);

        header("Location: profile.php");
        exit();
    } else {
        echo "Error moving uploaded file.";
    }
} else {
    echo "No file uploaded.";
}
?>
