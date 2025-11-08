<?php
// add_patient_ajax.php
session_start();
require 'config.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Sanitize and validate inputs
    $family_number = trim($_POST['family_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '') ?: null;
    $last_name = trim($_POST['last_name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $bmi = !empty($_POST['bmi']) ? floatval($_POST['bmi']) : null;
    $bmi_status = trim($_POST['bmi_status'] ?? '') ?: null;
    $status = 'active';

    // === VALIDATION ===
    if (empty($family_number) || empty($first_name) || empty($last_name) || empty($birthdate) || empty($sex)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }

    if (!in_array($sex, ['Male', 'Female', 'Other'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid sex value']);
        exit();
    }

    if ($bmi_status && !in_array($bmi_status, ['Underweight', 'Normal', 'Overweight', 'Obese'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid BMI status']);
        exit();
    }

    // === BEGIN TRANSACTION ===
    $conn->beginTransaction();

    // Check if family_number exists
    $stmt = $conn->prepare("SELECT id, member_count FROM families WHERE family_number = :family_number FOR UPDATE");
    $stmt->execute([':family_number' => $family_number]);
    $family = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($family) {
        // Family exists → Increment member_count
        $update = $conn->prepare("UPDATE families SET member_count = member_count + 1 WHERE family_number = :family_number");
        $update->execute([':family_number' => $family_number]);
    } else {
        // Family does NOT exist → Create new family record
        $insert = $conn->prepare("INSERT INTO families (family_number, member_count) VALUES (:family_number, 1)");
        $insert->execute([':family_number' => $family_number]);
    }

    // Insert patient
    $query = "INSERT INTO patients (
        family_number, first_name, middle_name, last_name, birthdate, sex, 
        contact_number, address, weight, height, bmi, bmi_status, status
    ) VALUES (
        :family_number, :first_name, :middle_name, :last_name, :birthdate, :sex, 
        :contact_number, :address, :weight, :height, :bmi, :bmi_status, :status
    )";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':family_number' => $family_number,
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':birthdate' => $birthdate,
        ':sex' => $sex,
        ':contact_number' => $contact_number,
        ':address' => $address,
        ':weight' => $weight,
        ':height' => $height,
        ':bmi' => $bmi,
        ':bmi_status' => $bmi_status,
        ':status' => $status
    ]);

    // === NEW: LOG THE ACTION ===
    $patientId = $conn->lastInsertId();

    // LOG
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'add_patient_record', :details, :target_id)
    ");
    $logStmt->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Added patient: {$first_name} {$middle_name} {$last_name}",
        ':target_id'  => $patientId
    ]);

    $conn->commit();

    // SUCCESS – set session message (used after page reload)
    $_SESSION['patient_message'] = "Patient successfully added!";

    echo json_encode([
        'status'  => 'success',
        'message' => 'Patient successfully added!'
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['patient_message'] = "Error adding patient: " . $e->getMessage();

    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error. Please try again.'
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['patient_message'] = "Unexpected error: " . $e->getMessage();

    echo json_encode([
        'status'  => 'error',
        'message' => 'An unexpected error occurred.'
    ]);
}
?>