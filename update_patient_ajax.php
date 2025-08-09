<?php
// update_patient_ajax.php
session_start();
include 'config.php';

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $patient_id = $_POST['patient_id'] ?? null;
        $first_name = $_POST['first_name'] ?? null;
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'] ?? null;
        $civil_status = $_POST['civil_status'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $occupation = $_POST['occupation'] ?? null;
        $address = $_POST['address'] ?? null;
        $weight = $_POST['weight'] ? floatval($_POST['weight']) : null;
        $height = $_POST['height'] ? floatval($_POST['height']) : null;
        $bmi = $_POST['bmi'] ? floatval($_POST['bmi']) : null;
        $bmi_status = $_POST['bmi_status'] ?? null;

        // Validate required fields
        if (empty($patient_id) || empty($civil_status)) {
            echo json_encode(['status' => 'error', 'message' => 'Patient ID and civil status are required']);
            exit();
        }

        // Validate civil_status
        $valid_civil_statuses = ['Single', 'Married', 'Divorced', 'Widowed'];
        if (!in_array($civil_status, $valid_civil_statuses)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid civil status']);
            exit();
        }

        // Validate contact_number if provided
        if (!empty($contact_number) && !preg_match('/^[0-9]{10,11}$/', $contact_number)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid contact number. Must be 10 or 11 digits.']);
            exit();
        }

        // Validate weight and height if provided
        if ($weight !== null && $weight <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Weight must be a positive number']);
            exit();
        }
        if ($height !== null && $height <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Height must be a positive number']);
            exit();
        }

        // Prepare update query (excluding sex)
        $sql = "UPDATE patients SET 
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                civil_status = :civil_status,
                contact_number = :contact_number,
                occupation = :occupation,
                address = :address,
                weight = :weight,
                height = :height,
                bmi = :bmi,
                bmi_status = :bmi_status
                WHERE id = :patient_id";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':middle_name', $middle_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':civil_status', $civil_status);
        $stmt->bindParam(':contact_number', $contact_number);
        $stmt->bindParam(':occupation', $occupation);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':weight', $weight);
        $stmt->bindParam(':height', $height);
        $stmt->bindParam(':bmi', $bmi);
        $stmt->bindParam(':bmi_status', $bmi_status);
        
        // Execute the query
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Patient information updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>