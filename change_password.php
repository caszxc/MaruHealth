<?php
// change_password.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit();
    }

    if ($new_password !== $confirm_password) {
        $response['message'] = 'New password and confirmation do not match.';
        echo json_encode($response);
        exit();
    }

    if (strlen($new_password) < 8) {
        $response['message'] = 'New password must be at least 8 characters long.';
        echo json_encode($response);
        exit();
    }

    try {
        // Fetch the current password hash from the database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $response['message'] = 'User not found.';
            echo json_encode($response);
            exit();
        }

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $response['message'] = 'Current password is incorrect.';
            echo json_encode($response);
            exit();
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Begin transaction to ensure atomic updates
        $conn->beginTransaction();

        // Update the primary user's password
        $update_stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
        $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();

        // Sync the password to all dependents
        $sync_stmt = $conn->prepare("UPDATE users SET password = :password WHERE primary_user_id = :primary_user_id");
        $sync_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $sync_stmt->bindParam(':primary_user_id', $user_id, PDO::PARAM_INT);
        $sync_stmt->execute();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Password updated successfully for you and your dependents.';
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit();
?>