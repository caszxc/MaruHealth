<?php
// archive_patient.php
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
    $conn->beginTransaction();

    // Get patient name for log details
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, family_number FROM patients WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $conn->rollBack();
        header("Location: patient_management.php?error=Patient not found or already archived");
        exit();
    }

    $fullName = trim("{$patient['first_name']} {$patient['middle_name']} {$patient['last_name']}");

    // Update patient status to archived
    $updateStmt = $conn->prepare("UPDATE patients SET status = 'archived' WHERE id = :id");
    $updateStmt->execute([':id' => $patientId]);

    // === LOG THE ARCHIVE ACTION ===
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'archive_patient', :details, :target_id)
    ");

    $logStmt->execute([
        ':admin_id'    => $_SESSION['admin_id'],
        ':details'     => "Archived patient: {$fullName}",
        ':target_id'   => $patientId
    ]);
    // === END LOG ===

    $conn->commit();

    // Redirect with success
    header("Location: patient_management.php?message=Patient archived successfully");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Optional: log error to file
    error_log("Archive patient error (ID: $patientId): " . $e->getMessage());

    header("Location: patient_management.php?error=Failed to archive patient");
    exit();
}
?>