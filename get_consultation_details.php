<?php
// get_consultation_details.php
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
$primary_user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $primary_user_id;

// Validate active_user_id
$stmt = $conn->prepare("SELECT id, primary_user_id FROM users WHERE id = :active_user_id");
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($active_user_id != $primary_user_id && $user['primary_user_id'] != $primary_user_id)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user account']);
    exit();
}

try {
    // Fetch consultation for the patient linked to the active user
    $stmt = $conn->prepare("
        SELECT c.*, p.address AS patient_address
        FROM consultations c 
        JOIN patients p ON c.patient_id = p.id 
        JOIN users u ON p.first_name = u.first_name 
            AND p.last_name = u.last_name 
            AND p.birthdate = u.birthday 
        WHERE c.id = :consultation_id 
            AND u.id = :active_user_id
    ");
    $stmt->execute([
        ':consultation_id' => $consultation_id,
        ':active_user_id' => $active_user_id
    ]);

    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consultation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Consultation not found or you do not have permission to view it']);
        exit();
    }

    // Log for debugging
    error_log("Fetched consultation ID $consultation_id for user ID $active_user_id: address=" . ($consultation['patient_address'] ?? 'NULL'));

    // Prepare response
    $response = [
        'consultation_type' => $consultation['consultation_type'] ?? 'N/A',
        'consultation_date' => $consultation['consultation_date'] ? date('n/j/Y', strtotime($consultation['consultation_date'])) : 'N/A',
        'reason_for_consultation' => $consultation['reason_for_consultation'] ?? 'N/A',
        'blood_pressure' => $consultation['blood_pressure'] ?? 'N/A',
        'temperature' => $consultation['temperature'] ?? 'N/A',
        'diagnosis' => $consultation['diagnosis'] ?? 'N/A',
        'prescribed_medicine' => $consultation['prescribed_medicine'] ?? 'None',
        'treatment_given' => $consultation['treatment_given'] ?? 'None',
        'consulting_physician_nurse' => $consultation['consulting_physician_nurse'] ?? 'N/A',
        'address' => $consultation['patient_address'] ?? 'N/A' // Include address from patients table
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in get_consultation_details.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
    exit();
}
?>