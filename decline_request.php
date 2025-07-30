<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Check if request ID is provided
if (isset($_GET['id'])) {
    $request_id = $_GET['id'];

    // Update request status to "declined" and update the request_date
    $sql = "UPDATE medicine_requests SET request_status = 'declined', request_date = NOW() WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Request declined successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to decline request.";
    }
}

header("Location: medicine_requests.php");
exit();
?>
