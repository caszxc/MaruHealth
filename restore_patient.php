<?php
// restore_patient.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['patient_message'] = "Invalid patient ID.";
    header("Location: archived_patients.php");
    exit();
}

$patientId = (int)$_GET['id'];

try {
    $conn->beginTransaction();

    // ---- 1. Get patient name (for log + message) ----
    $stmt = $conn->prepare("
        SELECT first_name, middle_name, last_name, family_number 
        FROM patients 
        WHERE id = :id AND status = 'archived'
    ");
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $conn->rollBack();
        $_SESSION['patient_message'] = "Patient not found or already active.";
        header("Location: archived_patients.php");
        exit();
    }

    $fullName = trim("{$patient['first_name']} " . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']);

    // ---- 2. Restore patient ----
    $updateStmt = $conn->prepare("UPDATE patients SET status = 'active' WHERE id = :id");
    $updateStmt->execute([':id' => $patientId]);

    // ---- 3. Log the action ----
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'restore_patient', :details, :target_id)
    ");
    $logStmt->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Restored patient: {$fullName}",
        ':target_id'  => $patientId
    ]);

    $conn->commit();

    // ---- 4. SUCCESS MESSAGE (session) ----
    $_SESSION['patient_message'] = "Patient restored successfully.";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();

    error_log("Restore patient error (ID: $patientId): " . $e->getMessage());

    // ---- 5. ERROR MESSAGE (session) ----
    $_SESSION['patient_message'] = "Failed to restore patient. Please try again.";
}

// ---- 6. Redirect back (preserve search) ----
$redirect = "archived_patients.php";
if (!empty($_GET['search'])) {
    $redirect .= "?search=" . urlencode($_GET['search']);
}
header("Location: $redirect");
exit();
?>