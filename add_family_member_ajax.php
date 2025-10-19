<?php
// add_family_member_ajax.php
session_start();
require 'config.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

        // Validate required fields
        if (empty($family_number) || empty($first_name) || empty($last_name) || empty($birthdate) || empty($sex)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit();
        }

        // Validate sex
        if (!in_array($sex, ['Male', 'Female', 'Other'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid sex value']);
            exit();
        }

        // Validate family number exists in families table
        $stmt = $conn->prepare("SELECT COUNT(*) FROM families WHERE family_number = :family_number");
        $stmt->execute([':family_number' => $family_number]);
        if ($stmt->fetchColumn() == 0) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid family number']);
            exit();
        }

        // Validate BMI status if provided
        if ($bmi_status && !in_array($bmi_status, ['Underweight', 'Normal', 'Overweight', 'Obese'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid BMI status']);
            exit();
        }

        // Insert patient details into the patients table
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

        // Update family member count
        $stmt = $conn->prepare("UPDATE families SET member_count = member_count + 1 WHERE family_number = :family_number");
        $stmt->execute([':family_number' => $family_number]);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Patient successfully added!']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>