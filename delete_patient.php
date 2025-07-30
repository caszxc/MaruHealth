<?php
session_start();
require 'config.php';

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if an ID was provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: patient_management.php");
    exit();
}

$patient_id = $_GET['id'];

// Delete patient from database
$deleteStmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
if ($deleteStmt->execute([$patient_id])) {
    $_SESSION['success'] = "Patient deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete patient.";
}

// Redirect back to patient management page
header("Location: patient_management.php");
exit();
?>
