<?php
// delete_consultation.php
session_start();
require 'config.php';

// -------------------------------------------------
// 1. AUTHORIZATION
// -------------------------------------------------
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header('Location: admin_dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['view_patient_msg'] = 'No consultation ID provided.';
    header('Location: patient_management.php');
    exit();
}

$consultation_id = $_GET['id'];

// -------------------------------------------------
// 2. FETCH consultation + patient data in ONE query
// -------------------------------------------------
$stmt = $conn->prepare("
    SELECT 
        c.patient_id,
        c.consultation_date,
        p.first_name,
        p.middle_name,
        p.last_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.id = :id
");
$stmt->execute([':id' => $consultation_id]);
$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    $_SESSION['patient_message'] = 'Consultation not found.';
    header('Location: patient_management.php');
    exit();
}

// Build patient full name (same logic you use elsewhere)
$full_name = trim("{$consultation['first_name']} {$consultation['middle_name']} {$consultation['last_name']}");
$full_name = preg_replace('/\s+/', ' ', $full_name);   // collapse multiple spaces

$patient_id = $consultation['patient_id'];
$consult_date = $consultation['consultation_date'];   // YYYY-MM-DD

// -------------------------------------------------
// 3. DELETE + LOG
// -------------------------------------------------
try {
    $conn->beginTransaction();

    // ---- DELETE ----
    $del = $conn->prepare("DELETE FROM consultations WHERE id = :id");
    $del->execute([':id' => $consultation_id]);

    // ---- LOG (date + patient name) ----
    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'delete_consultation', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Deleted consultation of {$full_name} on {$consult_date}",
        ':target_id'  => $consultation_id          // keep the ID for traceability
    ]);

    $conn->commit();

    $_SESSION['patient_message'] = 'Consultation deleted successfully';

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['patient_message'] = 'Error deleting consultation: ' . $e->getMessage();
}

// -------------------------------------------------
// 4. REDIRECT back to the patient view
// -------------------------------------------------
header("Location: view_patient.php?id={$patient_id}");
exit();
?>