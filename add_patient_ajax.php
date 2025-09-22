<?php
//add_patient_ajax.php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Patient data
        $family_number = $_POST['family_number'];
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'];
        $last_name = $_POST['last_name'];
        $birthdate = $_POST['birthdate'];
        $sex = $_POST['sex'];
        $contact_number = $_POST['contact_number'];
        $address = $_POST['address'];
        $weight = $_POST['weight'];
        $height = $_POST['height'];
        $bmi = $_POST['bmi'];
        $bmi_status = $_POST['bmi_status'];
        $status = 'active'; // Default status for new patients

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
        echo "Patient successfully added!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>