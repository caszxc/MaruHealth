<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No consultation ID provided']);
    exit();
}

$consultation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Verify that the consultation belongs to a patient linked to the logged-in user
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM consultations c 
        JOIN patients p ON c.patient_id = p.id 
        JOIN users u ON p.first_name = u.first_name 
            AND p.last_name = u.last_name 
            AND p.birthdate = u.birthday 
        WHERE c.id = :consultation_id 
            AND u.id = :user_id
    ");
    $stmt->execute([
        ':consultation_id' => $consultation_id,
        ':user_id' => $user_id
    ]);

    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consultation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Consultation not found or you do not have permission to view it']);
        exit();
    }

    // Prepare response
    $response = [
        'consultation_type' => $consultation['consultation_type'],
        'consultation_date' => date('n/j/Y', strtotime($consultation['consultation_date'])),
        'reason_for_consultation' => $consultation['reason_for_consultation'],
        'blood_pressure' => $consultation['blood_pressure'] ?? 'N/A',
        'temperature' => $consultation['temperature'] ?? 'N/A',
        'diagnosis' => $consultation['diagnosis'],
        'prescribed_medicine' => $consultation['prescribed_medicine'] ?? 'None',
        'treatment_given' => $consultation['treatment_given'] ?? 'None',
        'consulting_physician_nurse' => $consultation['consulting_physician_nurse'] ?? 'N/A'
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>