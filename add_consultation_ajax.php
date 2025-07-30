<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input data
    $patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : null;
    $consultation_type = isset($_POST['consultation_type']) ? trim($_POST['consultation_type']) : null;
    $consultation_date = isset($_POST['consultation_date']) ? $_POST['consultation_date'] : null;
    $reason_for_consultation = isset($_POST['reason_for_consultation']) ? trim($_POST['reason_for_consultation']) : null;
    $blood_pressure = isset($_POST['blood_pressure']) ? trim($_POST['blood_pressure']) : null;
    $temperature = isset($_POST['temperature']) ? trim($_POST['temperature']) : null;
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : null;
    $prescribed_medicine = isset($_POST['prescribed_medicine']) ? trim($_POST['prescribed_medicine']) : null;
    $treatment_given = isset($_POST['treatment_given']) ? trim($_POST['treatment_given']) : null;
    $consulting_physician_nurse = isset($_POST['consulting_physician_nurse']) ? trim($_POST['consulting_physician_nurse']) : null;

    // Validate required fields
    if (!$patient_id || !$consultation_type || !$consultation_date || !$reason_for_consultation || !$temperature || !$diagnosis) {
        echo json_encode(["status" => "error", "message" => "All required fields must be filled: Patient ID, Consultation Type, Date, Reason, Temperature, and Diagnosis"]);
        exit;
    }

    try {
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

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Consultation added successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to add consultation"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>