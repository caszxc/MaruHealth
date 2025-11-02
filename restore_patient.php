<?php
// restore_patient.php
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
    $conn->beginTransaction();

    // Get patient info for logging
    $stmt = $conn->prepare("
        SELECT first_name, middle_name, last_name, family_number 
        FROM patients 
        WHERE id = :id AND status = 'archived'
    ");
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $conn->rollBack();
        header("Location: archived_patients.php?error=Patient not found or already active");
        exit();
    }

    $fullName = trim("{$patient['first_name']} " . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']);

    // Restore patient: set status to 'active'
    $updateStmt = $conn->prepare("UPDATE patients SET status = 'active' WHERE id = :id");
    $updateStmt->execute([':id' => $patientId]);

    // === LOG THE RESTORE ACTION ===
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'restore_patient', :details, :target_id)
    ");

    $logStmt->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Restored patient: {$fullName}",
        ':target_id'  => $patientId
    ]);
    // === END LOG ===

    $conn->commit();

    // Success redirect
    header("Location: archived_patients.php?message=Patient restored successfully");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Restore patient error (ID: $patientId): " . $e->getMessage());

    header("Location: archived_patients.php?error=Failed to restore patient");
    exit();
}
?>