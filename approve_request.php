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

    // Update request status to "pending" and update the request_date
    $updateQuery = "UPDATE medicine_requests SET request_status = 'pending', request_date = NOW() WHERE id = :id";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Request approved successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to approve request.";
    }
}

header("Location: medicine_requests.php");
exit();
?>
