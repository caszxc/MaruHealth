<?php
// archive_patient.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['patient_message'] = "Invalid patient ID.";
    header("Location: patient_management.php");
    exit();
}

$patientId = (int)$_GET['id'];

try {
    $conn->beginTransaction();

    // ---- 1. Get patient name (for log + message) ----
    $stmt = $conn->prepare("
        SELECT first_name, middle_name, last_name, family_number 
        FROM patients 
        WHERE id = :id AND status = 'active'
    ");
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $conn->rollBack();
        $_SESSION['patient_message'] = "Patient not found or already archived.";
        header("Location: patient_management.php");
        exit();
    }

    $fullName = trim("{$patient['first_name']} {$patient['middle_name']} {$patient['last_name']}");

    // ---- 2. Archive the patient ----
    $updateStmt = $conn->prepare("UPDATE patients SET status = 'archived' WHERE id = :id");
    $updateStmt->execute([':id' => $patientId]);

    // ---- 3. Log the action ----
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'archive_patient', :details, :target_id)
    ");
    $logStmt->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Archived patient: {$fullName}",
        ':target_id'  => $patientId
    ]);

    $conn->commit();

    // ---- 4. SUCCESS MESSAGE (session) ----
    $_SESSION['patient_message'] = "Patient archived successfully.";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();

    error_log("Archive patient error (ID: $patientId): " . $e->getMessage());

    // ---- 5. ERROR MESSAGE (session) ----
    $_SESSION['patient_message'] = "Failed to archive patient. Please try again.";
}

// ---- 6. Always redirect back ----
header("Location: patient_management.php" . (!empty($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
exit();
?>