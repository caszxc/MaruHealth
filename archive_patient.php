<?php
//archive_patient.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patient_management.php");
    exit();
}

$patientId = (int)$_GET['id'];

try {
    // Update patient status to archived
    $stmt = $conn->prepare("UPDATE patients SET status = 'archived' WHERE id = :id");
    $stmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $stmt->execute();

    // Redirect back to patient management with success message
    header("Location: patient_management.php?message=Patient archived successfully");
    exit();
} catch (PDOException $e) {
    // Redirect back with error message
    header("Location: patient_management.php?error=Failed to archive patient: " . $e->getMessage());
    exit();
}
?>