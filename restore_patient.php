<?php
//restore_patient.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: archived_patients.php");
    exit();
}

$patientId = (int)$_GET['id'];

try {
    // Update patient status to active
    $stmt = $conn->prepare("UPDATE patients SET status = 'active' WHERE id = :id");
    $stmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $stmt->execute();

    // Redirect back to archived patients with success message
    header("Location: archived_patients.php?message=Patient restored successfully");
    exit();
} catch (PDOException $e) {
    // Redirect back with error message
    header("Location: archived_patients.php?error=Failed to restore patient: " . $e->getMessage());
    exit();
}
?>