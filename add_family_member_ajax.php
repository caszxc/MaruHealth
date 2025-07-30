<?php
// add_family_member_ajax.php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Patient data
        $family_number = $_POST['family_number'] ?? null;
        $first_name = $_POST['first_name'] ?? null;
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'] ?? null;
        $birthdate = $_POST['birthdate'] ?? null;
        $sex = $_POST['sex'] ?? null;
        $civil_status = $_POST['civil_status'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $occupation = $_POST['occupation'] ?? null;
        $address = $_POST['address'] ?? null;
        $weight = $_POST['weight'] ? floatval($_POST['weight']) : null;
        $height = $_POST['height'] ? floatval($_POST['height']) : null;
        $bmi = $_POST['bmi'] ? floatval($_POST['bmi']) : null;
        $bmi_status = $_POST['bmi_status'] ?? null;

        // Validation
        if (empty($family_number) || empty($first_name) || empty($last_name) || empty($birthdate) || empty($sex) || empty($civil_status)) {
            echo json_encode(['status' => 'error', 'message' => 'Required fields are missing']);
            exit;
        }

        // Validate sex
        if (!in_array($sex, ['Male', 'Female'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid sex']);
            exit;
        }

        // Validate civil_status
        if (!in_array($civil_status, ['Single', 'Married', 'Divorced', 'Widowed'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid civil status']);
            exit;
        }

        // Validate contact_number if provided
        if (!empty($contact_number) && !preg_match('/^[0-9]{10,11}$/', $contact_number)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid contact number. Must be 10 or 11 digits.']);
            exit;
        }

        // Validate weight and height if provided
        if ($weight !== null && $weight <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Weight must be a positive number']);
            exit;
        }
        if ($height !== null && $height <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Height must be a positive number']);
            exit;
        }

        // Insert patient details into the patients table
        $query = "INSERT INTO patients (
            family_number, first_name, middle_name, last_name, birthdate, sex, civil_status, 
            contact_number, occupation, address, weight, height, bmi, bmi_status
        ) VALUES (
            :family_number, :first_name, :middle_name, :last_name, :birthdate, :sex, :civil_status, 
            :contact_number, :occupation, :address, :weight, :height, :bmi, :bmi_status
        )";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':family_number' => $family_number,
            ':first_name' => $first_name,
            ':middle_name' => $middle_name,
            ':last_name' => $last_name,
            ':birthdate' => $birthdate,
            ':sex' => $sex,
            ':civil_status' => $civil_status,
            ':contact_number' => $contact_number,
            ':occupation' => $occupation,
            ':address' => $address,
            ':weight' => $weight,
            ':height' => $height,
            ':bmi' => $bmi,
            ':bmi_status' => $bmi_status
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Family member successfully added']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>