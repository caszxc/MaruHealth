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
        $civil_status = $_POST['civil_status'];
        $contact_number = $_POST['contact_number'];
        $occupation = $_POST['occupation'];
        $address = $_POST['address'];
        $weight = $_POST['weight'];
        $height = $_POST['height'];
        $bmi = $_POST['bmi'];
        $bmi_status = $_POST['bmi_status'];

        

        // Insert family details into the families table
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
        echo "Patient successfully added!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>