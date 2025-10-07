<?php
// upload_profilePic.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $response = ['success' => false, 'message' => 'Unauthorized access.'];
    echo json_encode($response);
    exit();
}

$response = ['success' => false, 'message' => ''];

// Check if active_user_id is provided in the form
if (!isset($_POST['active_user_id']) || !is_numeric($_POST['active_user_id'])) {
    $response['message'] = 'Invalid user ID.';
    echo json_encode($response);
    exit();
}

$active_user_id = (int)$_POST['active_user_id'];

// Verify the active user exists and is either the primary user or a dependent
$stmt = $conn->prepare("SELECT id, primary_user_id FROM users WHERE id = :active_user_id");
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['primary_user_id'] !== null && $user['primary_user_id'] != $_SESSION['user_id'])) {
    $response['message'] = 'Invalid user or permission denied.';
    echo json_encode($response);
    exit();
}

// Check if a file was uploaded
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
    $fileName = $_FILES['profile_photo']['name'];
    $fileSize = $_FILES['profile_photo']['size'];
    $fileType = $_FILES['profile_photo']['type'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate file type and size
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if (!in_array($fileExtension, $allowedExtensions)) {
        $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
        echo json_encode($response);
        exit();
    }
    if ($fileSize > $maxFileSize) {
        $response['message'] = 'File size exceeds 5MB limit.';
        echo json_encode($response);
        exit();
    }

    // Generate unique filename using active_user_id
    $newFileName = "profile_" . $active_user_id . "." . $fileExtension;
    $uploadDir = "images/uploads/profile_pictures/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destPath = $uploadDir . $newFileName;

    // Move the uploaded file
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        try {
            // Update profile picture path in the database
            $stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :id");
            $stmt->execute([
                ':profile_picture' => $destPath,
                ':id' => $active_user_id
            ]);

            $response['success'] = true;
            $response['message'] = 'Profile picture updated successfully.';
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Error moving uploaded file.';
    }
} else {
    $response['message'] = 'No file uploaded or upload error.';
}

echo json_encode($response);
exit();
?>
