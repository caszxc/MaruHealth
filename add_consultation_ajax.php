<?php
// add_consultation_ajax.php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input data
    $patient_id = $_POST['patient_id'] ?? null;
    $consultation_type = trim($_POST['consultation_type'] ?? '');
    $consultation_date = $_POST['consultation_date'] ?? null;
    $reason_for_consultation = trim($_POST['reason_for_consultation'] ?? '');
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $prescribed_medicine = trim($_POST['prescribed_medicine'] ?? '');
    $treatment_given = trim($_POST['treatment_given'] ?? '');
    $consulting_physician_nurse = trim($_POST['consulting_physician_nurse'] ?? '');

    // Validate required fields
    if (!$patient_id || !$consultation_type || !$consultation_date || !$reason_for_consultation || !$temperature || !$diagnosis) {
        echo json_encode(["status" => "error", "message" => "All required fields must be filled."]);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Insert consultation
        $sql = "INSERT INTO consultations (
            patient_id, consultation_type, consultation_date, reason_for_consultation, 
            blood_pressure, temperature, diagnosis, prescribed_medicine, 
            treatment_given, consulting_physician_nurse
        ) VALUES (
            :patient_id, :consultation_type, :consultation_date, :reason_for_consultation, 
            :blood_pressure, :temperature, :diagnosis, :prescribed_medicine, 
            :treatment_given, :consulting_physician_nurse
        )";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':consultation_type', $consultation_type, PDO::PARAM_STR);
        $stmt->bindParam(':consultation_date', $consultation_date, PDO::PARAM_STR);
        $stmt->bindParam(':reason_for_consultation', $reason_for_consultation, PDO::PARAM_STR);
        $stmt->bindParam(':blood_pressure', $blood_pressure, PDO::PARAM_STR);
        $stmt->bindParam(':temperature', $temperature, PDO::PARAM_STR);
        $stmt->bindParam(':diagnosis', $diagnosis, PDO::PARAM_STR);
        $stmt->bindParam(':prescribed_medicine', $prescribed_medicine, PDO::PARAM_STR);
        $stmt->bindParam(':treatment_given', $treatment_given, PDO::PARAM_STR);
        $stmt->bindParam(':consulting_physician_nurse', $consulting_physician_nurse, PDO::PARAM_STR);

        $stmt->execute();
        $consultation_id = $conn->lastInsertId(); // Get the new consultation ID

        // Fetch patient name for log
        $patientStmt = $conn->prepare("
            SELECT first_name, middle_name, last_name 
            FROM patients 
            WHERE id = :id
        ");
        $patientStmt->execute([':id' => $patient_id]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

        $full_name = trim("{$patient['first_name']} {$patient['middle_name']} {$patient['last_name']}");
        $full_name = preg_replace('/\s+/', ' ', $full_name); // Clean up extra spaces

        // Insert activity log
        $logSql = "
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'add_consultation', :details, :target_id)
        ";
        $logStmt = $conn->prepare($logSql);
        $logStmt->execute([
            ':admin_id'   => $_SESSION['admin_id'],
            ':details'    => "Added consultation for {$full_name} on {$consultation_date}",
            ':target_id'  => $consultation_id
        ]);

        $conn->commit();

        // Set success message (for view_patient.php flash)
        $_SESSION['patient_message'] = 'Consultation added successfully';

        echo json_encode([
            "status"  => "success",
            "message" => "Consultation added successfully"
        ]);

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['patient_message'] = 'Database error: ' . $e->getMessage();

        echo json_encode([
            "status"  => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>