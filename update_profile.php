<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name']);
    $gender = trim($_POST['gender']);
    $birthday = trim($_POST['birthday']);
    $address = trim($_POST['address']);
    $phone_number = trim($_POST['phone_number']);
    $email = trim($_POST['email']);

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($middle_name) || empty($gender) || 
        empty($birthday) || empty($address) || empty($phone_number) || empty($email)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/^\+?\d{10,15}$/', $phone_number)) {
        $response['message'] = 'Invalid phone number format.';
        echo json_encode($response);
        exit();
    }

    try {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'Email is already in use.';
            echo json_encode($response);
            exit();
        }

        // Check if phone number is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = :phone_number AND id != :user_id");
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'Phone number is already in use.';
            echo json_encode($response);
            exit();
        }

        // Update user details
        $update_stmt = $conn->prepare("
            UPDATE users 
            SET first_name = :first_name, 
                last_name = :last_name, 
                middle_name = :middle_name, 
                gender = :gender, 
                birthday = :birthday, 
                address = :address, 
                phone_number = :phone_number, 
                email = :email 
            WHERE id = :user_id
        ");
        $update_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $update_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $update_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
        $update_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
        $update_stmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
        $update_stmt->bindParam(':address', $address, PDO::PARAM_STR);
        $update_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $update_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($update_stmt->execute()) {
            // Update patient record if linked
            $patient_stmt = $conn->prepare("
                UPDATE patients 
                SET first_name = :first_name, 
                    last_name = :last_name, 
                    middle_name = :middle_name, 
                    birthdate = :birthday, 
                    sex = :gender, 
                    contact_number = :phone_number, 
                    address = :address 
                WHERE first_name = :old_first_name 
                AND last_name = :old_last_name 
                AND birthdate = :old_birthday
            ");
            $patient_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $patient_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $patient_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
            $patient_stmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
            $patient_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
            $patient_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
            $patient_stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $patient_stmt->bindParam(':old_first_name', $user['first_name'], PDO::PARAM_STR);
            $patient_stmt->bindParam(':old_last_name', $user['last_name'], PDO::PARAM_STR);
            $patient_stmt->bindParam(':old_birthday', $user['birthday'], PDO::PARAM_STR);
            $patient_stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Profile updated successfully.';
        } else {
            $response['message'] = 'Failed to update profile. Please try again.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit();
?>